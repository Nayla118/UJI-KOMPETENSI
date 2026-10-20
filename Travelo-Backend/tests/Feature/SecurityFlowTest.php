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

    public function test_e_ticket_requires_ownership_and_a_confirmed_paid_booking(): void
    {
        $owner = $this->makeUser('owner');
        $attacker = $this->makeUser('attacker');
        [$booking] = $this->makeBooking($owner, [
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
}
