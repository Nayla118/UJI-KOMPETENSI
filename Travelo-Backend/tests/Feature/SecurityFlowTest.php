<?php

namespace Tests\Feature;

use App\Contracts\FirebaseIdentityVerifier;
use App\Exceptions\InvalidFirebaseTokenException;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Payment;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Security regressions for the auth/payment/ticket boundaries.
 *
 * These tests never call Firebase or Midtrans over the network.
 */
class SecurityFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $suffix = ''): User
    {
        return User::create([
            'firebase_uid' => 'uid-'.$suffix.'-'.uniqid(),
            'name' => 'Test User',
            'email' => 'user-'.$suffix.'-'.uniqid().'@example.com',
        ]);
    }

    /**
     * @return array{0: Booking, 1: TourPackage}
     */
    private function makeBooking(User $user, array $overrides = []): array
    {
        $destination = Destination::create([
            'name' => 'Bali',
            'city' => 'Denpasar',
            'country' => 'Indonesia',
        ]);

        $tour = TourPackage::create([
            'destination_id' => $destination->id,
            'title' => 'Bali Trip',
            'price' => 1000000,
            'duration_days' => 3,
            'max_people' => 10,
        ]);

        $booking = Booking::create(array_merge([
            'user_id' => $user->id,
            'tour_package_id' => $tour->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 2,
            'total_price' => 2000000,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ], $overrides));

        return [$booking, $tour];
    }

    /**
     * @return array{uid: string, email: string, name: string, photo: ?string, provider: ?string, email_verified: bool}
     */
    private function identity(array $overrides = []): array
    {
        return array_merge([
            'uid' => 'firebase-uid-'.uniqid(),
            'email' => 'firebase-'.uniqid().'@example.com',
            'name' => 'Firebase User',
            'photo' => null,
            'provider' => 'password',
            'email_verified' => true,
        ], $overrides);
    }

    private function mockVerifier(array $identity): void
    {
        $this->mock(FirebaseIdentityVerifier::class, function (MockInterface $mock) use ($identity) {
            $mock->shouldReceive('verify')->once()->andReturn($identity);
        });
    }

    public function test_malformed_firebase_token_is_rejected_by_the_real_verifier(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'id_token' => 'not-a-real-jwt',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_register_requires_a_firebase_id_token(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'No Token',
            'email' => 'no-token@example.com',
        ])->assertStatus(422);
    }

    public function test_register_with_a_verified_firebase_identity_creates_a_verified_user(): void
    {
        $identity = $this->identity(['provider' => 'google.com', 'email_verified' => true]);
        $this->mockVerifier($identity);

        $response = $this->postJson('/api/auth/register', [
            'id_token' => 'valid-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $identity['email']);

        $user = User::where('email', $identity['email'])->firstOrFail();
        $this->assertSame($identity['uid'], $user->firebase_uid);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_login_does_not_relink_an_existing_email_to_a_different_firebase_uid(): void
    {
        $existing = User::create([
            'firebase_uid' => 'original-uid',
            'name' => 'Original',
            'email' => 'victim@example.com',
        ]);

        $this->mockVerifier($this->identity([
            'uid' => 'different-uid',
            'email' => 'victim@example.com',
            'email_verified' => true,
            'provider' => 'google.com',
        ]));

        $this->postJson('/api/auth/login', ['id_token' => 'valid-token'])
            ->assertStatus(409);

        $this->assertSame('original-uid', $existing->fresh()->firebase_uid);
    }

    public function test_login_does_not_provision_an_unverified_email_password_account(): void
    {
        $identity = $this->identity([
            'email_verified' => false,
            'provider' => 'password',
        ]);
        $this->mockVerifier($identity);

        $this->postJson('/api/auth/login', ['id_token' => 'valid-token'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('users', ['email' => $identity['email']]);
    }

    public function test_existing_firebase_uid_can_still_sign_in(): void
    {
        $user = User::create([
            'firebase_uid' => 'known-uid',
            'name' => 'Known User',
            'email' => 'known@example.com',
        ]);

        $this->mockVerifier($this->identity([
            'uid' => 'known-uid',
            'email' => 'known@example.com',
            'email_verified' => false,
            'provider' => 'password',
        ]));

        $this->postJson('/api/auth/login', ['id_token' => 'valid-token'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertNotNull($user->fresh());
    }

    public function test_profile_update_requires_authentication(): void
    {
        $this->putJson('/api/auth/profile', [
            'name' => 'Anonymous',
        ])->assertUnauthorized();
    }

    public function test_user_can_update_their_own_profile_details(): void
    {
        $user = $this->makeUser('profile');
        $originalEmail = $user->email;
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'name' => '  Nayla Aisyah  ',
            'phone' => '  +62 812-3456-7890  ',
            'date_of_birth' => '1998-04-12',
            'city' => '  Bandung  ',
            'email' => 'attacker@example.com',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Nayla Aisyah')
            ->assertJsonPath('data.phone', '+62 812-3456-7890')
            ->assertJsonPath('data.date_of_birth', '1998-04-12')
            ->assertJsonPath('data.city', 'Bandung')
            ->assertJsonPath('data.email', $originalEmail);

        $user->refresh();
        $this->assertSame('Nayla Aisyah', $user->name);
        $this->assertSame('+62 812-3456-7890', $user->phone);
        $this->assertSame('1998-04-12', $user->date_of_birth->toDateString());
        $this->assertSame('Bandung', $user->city);
        $this->assertSame($originalEmail, $user->email);
    }

    public function test_profile_update_can_clear_optional_fields(): void
    {
        $user = $this->makeUser('clear');
        $user->update([
            'phone' => '081234567890',
            'date_of_birth' => '1990-01-01',
            'city' => 'Jakarta',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'name' => 'Still Here',
        ])->assertOk()
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.date_of_birth', null)
            ->assertJsonPath('data.city', null);
    }

    public function test_profile_update_rejects_invalid_phone_and_future_birth_date(): void
    {
        $user = $this->makeUser('invalid');
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'name' => 'Valid Name',
            'phone' => 'not-a-phone',
            'date_of_birth' => now()->addDay()->toDateString(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['phone', 'date_of_birth']);

        $this->assertNull($user->fresh()->phone);
        $this->assertNull($user->fresh()->date_of_birth);
    }

    public function test_an_edited_local_name_survives_the_next_firebase_sign_in(): void
    {
        $user = User::create([
            'firebase_uid' => 'profile-name-uid',
            'name' => 'Edited Local Name',
            'email' => 'profile-name@example.com',
        ]);

        $this->mockVerifier($this->identity([
            'uid' => 'profile-name-uid',
            'email' => 'profile-name@example.com',
            'name' => 'Provider Display Name',
            'email_verified' => true,
            'provider' => 'google.com',
        ]));

        $this->postJson('/api/auth/login', ['id_token' => 'valid-token'])
            ->assertOk();

        $this->assertSame('Edited Local Name', $user->fresh()->name);
    }

    public function test_payment_retry_sync_cannot_touch_another_users_booking(): void
    {
        $owner = $this->makeUser('owner');
        $attacker = $this->makeUser('attacker');
        [$booking] = $this->makeBooking($owner);

        Payment::create([
            'booking_id' => $booking->id,
            'payment_status' => 'pending',
            'payment_method' => 'qris',
            'amount' => 2000000,
        ]);

        Sanctum::actingAs($attacker);

        $this->postJson("/api/bookings/{$booking->id}/payment/retry-sync")
            ->assertNotFound();
    }

    public function test_mark_as_paid_cannot_touch_another_users_booking(): void
    {
        $owner = $this->makeUser('mark-paid-owner');
        $attacker = $this->makeUser('mark-paid-attacker');
        [$booking] = $this->makeBooking($owner);

        Payment::create([
            'booking_id' => $booking->id,
            'payment_status' => 'pending',
            'payment_method' => 'qris',
            'amount' => 2000000,
        ]);

        Sanctum::actingAs($attacker);

        $this->postJson("/api/bookings/{$booking->id}/payment/mark-as-paid")
            ->assertNotFound();
    }

    public function test_e_ticket_requires_ownership_and_a_confirmed_paid_booking(): void
    {
        $owner = $this->makeUser('owner');
        $attacker = $this->makeUser('attacker');
        [$booking] = $this->makeBooking($owner, [
            'booking_date' => now()->toDateString(),
            'payment_status' => 'paid',
            'booking_status' => 'confirmed',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'payment_status' => 'paid',
            'payment_method' => 'qris',
            'amount' => 2000000,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($attacker);
        $this->getJson("/api/bookings/{$booking->id}/e-ticket")->assertNotFound();

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/bookings/{$booking->id}/e-ticket");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reference', sprintf('TRV-%06d', $booking->id))
            ->assertJsonPath('data.booking_id', $booking->id);

        $token = $response->json('data.ticket_token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        $this->postJson('/api/tickets/verify', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.reference', sprintf('TRV-%06d', $booking->id));

        $this->postJson('/api/tickets/verify', ['token' => $token.'tampered'])
            ->assertOk()
            ->assertJsonPath('data.valid', false);
    }

    public function test_e_ticket_is_not_issued_for_an_unpaid_booking(): void
    {
        $user = $this->makeUser('unpaid');
        [$booking] = $this->makeBooking($user);

        Sanctum::actingAs($user);

        $this->getJson("/api/bookings/{$booking->id}/e-ticket")
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_midtrans_callback_without_a_signature_is_rejected(): void
    {
        $user = $this->makeUser('webhook');
        [$booking] = $this->makeBooking($user);

        $orderId = 'BOOKING-'.$booking->id.'-20240101000000-1234';

        $this->postJson('/api/midtrans/callback', [
            'order_id' => $orderId,
            'transaction_id' => 'forged-txn',
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'gross_amount' => '2000000',
            'status_code' => '200',
        ])->assertStatus(401);

        $this->assertSame('pending', $booking->fresh()->payment_status);
    }

    public function test_midtrans_callback_with_a_valid_signature_is_processed(): void
    {
        config(['services.midtrans.server_key' => 'test-server-key']);

        $user = $this->makeUser('signed');
        [$booking] = $this->makeBooking($user);

        $orderId = 'BOOKING-'.$booking->id.'-20240101000000-1234';
        $grossAmount = '2000000';
        $statusCode = '200';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.'test-server-key');

        $this->postJson('/api/midtrans/callback', [
            'order_id' => $orderId,
            'transaction_id' => 'valid-txn',
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'gross_amount' => $grossAmount,
            'status_code' => $statusCode,
            'signature_key' => $signature,
        ])->assertOk();

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('confirmed', $booking->booking_status);
    }

    public function test_staff_cannot_reach_admin_management_routes_but_can_reach_gate_endpoints(): void
    {
        $staff = User::create([
            'firebase_uid' => 'staff-'.uniqid(),
            'name' => 'Gate Staff',
            'email' => 'staff-'.uniqid().'@example.com',
            'role' => User::ROLE_STAFF,
            'password' => bcrypt('synthetic-password'),
        ]);

        $this->actingAs($staff)->get('/admin')->assertForbidden();
        $this->actingAs($staff)->get('/admin/users')->assertForbidden();
        $this->actingAs($staff)->get('/admin/export/users')->assertForbidden();

        // The ticket foundation is intentionally available to gate staff.
        $this->actingAs($staff)
            ->postJson('/admin/tickets/redeem', ['token' => 'not-a-valid-token'])
            ->assertStatus(422);
    }

    public function test_traveller_cannot_reach_the_admin_panel_or_gate_endpoints(): void
    {
        $traveller = $this->makeUser('traveller-panel');

        $this->actingAs($traveller)->get('/admin')->assertForbidden();
        $this->actingAs($traveller)
            ->postJson('/admin/tickets/redeem', ['token' => 'not-a-valid-token'])
            ->assertForbidden();
    }

    public function test_traveller_password_cannot_open_an_admin_session(): void
    {
        $email = 'traveller-login-'.uniqid().'@example.com';

        User::create([
            'firebase_uid' => 'traveller-login-'.uniqid(),
            'name' => 'Traveller',
            'email' => $email,
            'role' => User::ROLE_TRAVELLER,
            'password' => bcrypt('synthetic-password'),
        ]);

        $this->post('/admin/login', [
            'email' => $email,
            'password' => 'synthetic-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_can_reach_admin_management_routes(): void
    {
        $admin = User::create([
            'firebase_uid' => 'admin-'.uniqid(),
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'role' => User::ROLE_ADMIN,
            'password' => bcrypt('synthetic-password'),
        ]);

        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_storage_fallback_cannot_escape_the_public_directory(): void
    {
        $name = 'security-probe-'.uniqid().'.txt';
        $privateFile = storage_path('app/private/'.$name);

        if (!is_dir(dirname($privateFile))) {
            mkdir(dirname($privateFile), 0777, true);
        }
        file_put_contents($privateFile, 'synthetic-private-probe');

        try {
            $response = $this->get('/storage/..%2Fprivate%2F'.$name);

            $this->assertContains($response->status(), [403, 404]);
            $this->assertStringNotContainsString(
                'synthetic-private-probe',
                (string) $response->getContent()
            );
        } finally {
            @unlink($privateFile);
        }

        $publicFile = storage_path('app/public/'.$name);
        file_put_contents($publicFile, 'synthetic-public-probe');

        try {
            $response = $this->get('/storage/'.$name);

            $response->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff');
        } finally {
            @unlink($publicFile);
        }
    }
}
