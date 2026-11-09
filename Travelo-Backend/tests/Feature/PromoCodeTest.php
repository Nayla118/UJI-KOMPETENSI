<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\PromoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Promo codes are validated and priced on the server.
 *
 * These tests pin every rejection reason, the percent/fixed calculations,
 * quota consistency across sequential requests, quota release on
 * cancellation (idempotent) and the Midtrans amount contract.
 */
class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    private User $admin;

    private Destination $destination;

    private TourPackage $package;

    private TourPackage $otherPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Promo Tester',
            'email' => 'promo-'.uniqid().'@example.com',
        ]);

        $this->otherUser = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Other Tester',
            'email' => 'other-'.uniqid().'@example.com',
        ]);

        $this->admin = User::create([
            'firebase_uid' => 'admin-'.uniqid(),
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->destination = Destination::create([
            'name' => 'Bali',
            'city' => 'Denpasar',
            'country' => 'Indonesia',
        ]);

        $otherDestination = Destination::create([
            'name' => 'Lombok',
            'city' => 'Mataram',
            'country' => 'Indonesia',
        ]);

        $this->package = TourPackage::create([
            'destination_id' => $this->destination->id,
            'title' => 'Bali Trip',
            'price' => 1000000,
            'duration_days' => 3,
            'max_people' => 10,
        ]);

        $this->otherPackage = TourPackage::create([
            'destination_id' => $otherDestination->id,
            'title' => 'Lombok Trip',
            'price' => 1000000,
            'duration_days' => 3,
            'max_people' => 10,
        ]);
    }

    private function makePromo(array $overrides = []): PromoCode
    {
        return PromoCode::create(array_merge([
            'code' => 'SUMMER10',
            'description' => 'Test promo',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 10,
            'min_transaction' => 0,
            'per_user_limit' => 1,
            'used_count' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->user->id,
            'tour_package_id' => $this->package->id,
            'booking_date' => now()->addDays(10)->toDateString(),
            'people_count' => 2,
            'subtotal_amount' => 2000000,
            'discount_amount' => 0,
            'total_price' => 2000000,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ], $overrides));
    }

    private function preview(string $code, array $overrides = [])
    {
        Sanctum::actingAs($this->user);

        return $this->postJson('/api/promos/validate', array_merge([
            'code' => $code,
            'tour_package_id' => $this->package->id,
            'people' => 2,
            'date' => now()->addDays(7)->toDateString(),
        ], $overrides));
    }

    private function createBookingWithPromo(string $code, array $overrides = [])
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token');
        });

        Sanctum::actingAs($this->user);

        return $this->postJson('/api/bookings', array_merge([
            'tour_package_id' => $this->package->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 2,
            'promo_code' => $code,
        ], $overrides));
    }

    // ---------- Validation failures ----------

    public function test_unknown_code_is_rejected(): void
    {
        $this->preview('DOESNOTEXIST')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', PromoService::REASON_NOT_FOUND);
    }

    public function test_inactive_code_is_rejected(): void
    {
        $this->makePromo(['is_active' => false]);

        $this->preview('SUMMER10')
            ->assertStatus(422)
            ->assertJsonPath('reason', PromoService::REASON_INACTIVE);
    }

    public function test_code_that_has_not_started_is_rejected(): void
    {
        $this->makePromo(['starts_at' => now()->addDay()]);

        $this->preview('SUMMER10')
            ->assertStatus(422)
            ->assertJsonPath('reason', PromoService::REASON_NOT_STARTED);
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->makePromo(['expires_at' => now()->subDay()]);

        $this->preview('SUMMER10')
            ->assertStatus(422)
            ->assertJsonPath('reason', PromoService::REASON_EXPIRED);
    }

    public function test_quota_exhausted_code_is_rejected(): void
    {
        $this->makePromo(['usage_limit' => 1, 'used_count' => 1]);

        $this->preview('SUMMER10')
            ->assertStatus(422)
            ->assertJsonPath('reason', PromoService::REASON_QUOTA_EXHAUSTED);
    }

    public function test_per_user_limit_is_rejected(): void
    {
        $promo = $this->makePromo(['per_user_limit' => 1]);
        $booking = $this->makeBooking();

        PromoRedemption::create([
            'promo_code_id' => $promo->id,
            'user_id' => $this->user->id,
            'booking_id' => $booking->id,
            'discount_amount' => 100000,
            'status' => PromoRedemption::STATUS_APPLIED,
        ]);

        $this->preview('SUMMER10')
            ->assertStatus(422)
            ->assertJsonPath('reason', PromoService::REASON_PER_USER_LIMIT);
    }

    public function test_below_minimum_transaction_is_rejected(): void
    {
        $this->makePromo(['min_transaction' => 5000000]);

        $this->preview('SUMMER10')
            ->assertStatus(422)
            ->assertJsonPath('reason', PromoService::REASON_MIN_TRANSACTION);
    }

    public function test_code_restricted_to_another_package_is_rejected(): void
    {
        $this->makePromo(['tour_package_id' => $this->otherPackage->id]);

        $this->preview('SUMMER10')
            ->assertStatus(422)
            ->assertJsonPath('reason', PromoService::REASON_NOT_APPLICABLE);
    }

    public function test_code_restricted_to_another_destination_is_rejected(): void
    {
        $this->makePromo(['destination_id' => $this->otherPackage->destination_id]);

        $this->preview('SUMMER10')
            ->assertStatus(422)
            ->assertJsonPath('reason', PromoService::REASON_NOT_APPLICABLE);
    }

    // ---------- Calculations ----------

    public function test_percent_discount_is_capped_by_max_discount(): void
    {
        $this->makePromo([
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 50,
            'max_discount' => 300000,
        ]);

        $response = $this->preview('SUMMER10')->assertOk();

        $this->assertSame(2000000.0, (float) $response->json('data.subtotal'));
        $this->assertSame(300000.0, (float) $response->json('data.discount'));
        $this->assertSame(1700000.0, (float) $response->json('data.total'));
    }

    public function test_fixed_discount_is_applied(): void
    {
        $this->makePromo([
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 250000,
        ]);

        $response = $this->preview('SUMMER10')->assertOk();

        $this->assertSame(250000.0, (float) $response->json('data.discount'));
        $this->assertSame(1750000.0, (float) $response->json('data.total'));
    }

    public function test_discount_can_never_make_the_total_negative(): void
    {
        $this->makePromo([
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 5000000,
        ]);

        $response = $this->preview('SUMMER10')->assertOk();

        $this->assertSame(2000000.0, (float) $response->json('data.discount'));
        $this->assertSame(0.0, (float) $response->json('data.total'));
    }

    // ---------- Booking + quota consistency ----------

    public function test_booking_uses_server_computed_prices_and_ignores_client_amounts(): void
    {
        $promo = $this->makePromo();

        $response = $this->createBookingWithPromo('summer10', [
            // A malicious client must not be able to influence the price.
            'total_price' => 1,
            'discount_amount' => 1999999,
            'subtotal_amount' => 1,
        ])->assertStatus(201);

        $booking = Booking::query()->firstOrFail();

        $this->assertSame(2000000.0, (float) $booking->subtotal_amount);
        $this->assertSame(200000.0, (float) $booking->discount_amount);
        $this->assertSame(1800000.0, (float) $booking->total_price);
        $this->assertSame($promo->id, $booking->promo_code_id);
        $this->assertSame('SUMMER10', $booking->promo_code);

        $this->assertSame(1800000.0, (float) $response->json('data.booking.total_price'));
        $this->assertSame('SUMMER10', $response->json('data.booking.promo_code'));

        $this->assertDatabaseHas('promo_redemptions', [
            'promo_code_id' => $promo->id,
            'booking_id' => $booking->id,
            'status' => PromoRedemption::STATUS_APPLIED,
        ]);
        $this->assertSame(1, $promo->fresh()->used_count);
    }

    public function test_usage_limit_holds_across_two_sequential_bookings(): void
    {
        $promo = $this->makePromo([
            'usage_limit' => 1,
            'per_user_limit' => 5,
        ]);

        $this->createBookingWithPromo('SUMMER10')->assertStatus(201);

        $this->assertSame(1, $promo->fresh()->used_count);

        // Same user, second attempt: the single quota slot is gone.
        $this->createBookingWithPromo('SUMMER10')
            ->assertStatus(422)
            ->assertJsonPath('reason', PromoService::REASON_QUOTA_EXHAUSTED);

        $this->assertSame(1, $promo->fresh()->used_count);
        $this->assertSame(1, Booking::query()->count());
        $this->assertSame(1, PromoRedemption::query()->count());
    }

    public function test_per_user_limit_holds_across_two_sequential_bookings(): void
    {
        $promo = $this->makePromo([
            'usage_limit' => null,
            'per_user_limit' => 1,
        ]);

        $this->createBookingWithPromo('SUMMER10')->assertStatus(201);

        $this->createBookingWithPromo('SUMMER10')
            ->assertStatus(422)
            ->assertJsonPath('reason', PromoService::REASON_PER_USER_LIMIT);

        $this->assertSame(1, $promo->fresh()->used_count);

        // Another traveller is still allowed to use the code.
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token');
        });

        Sanctum::actingAs($this->otherUser);

        $this->postJson('/api/bookings', [
            'tour_package_id' => $this->package->id,
            'booking_date' => now()->addDays(8)->toDateString(),
            'people_count' => 2,
            'promo_code' => 'SUMMER10',
        ])->assertStatus(201);

        $this->assertSame(2, $promo->fresh()->used_count);
    }

    public function test_cancellation_releases_the_promo_once_only(): void
    {
        $promo = $this->makePromo();

        $this->createBookingWithPromo('SUMMER10')->assertStatus(201);
        $booking = Booking::query()->firstOrFail();

        $this->assertSame(1, $promo->fresh()->used_count);

        $this->actingAs($this->admin)
            ->patch(route('admin.bookings.updateStatus', $booking), [
                'booking_status' => 'cancelled',
            ])
            ->assertRedirect();

        $this->assertSame(0, $promo->fresh()->used_count);
        $this->assertSame(
            PromoRedemption::STATUS_RELEASED,
            PromoRedemption::query()->firstOrFail()->status
        );

        // A second release (e.g. another cancel path) must not decrement again.
        app(PromoService::class)->releaseForBooking($booking->fresh());

        $this->assertSame(0, $promo->fresh()->used_count);
        $this->assertSame(1, PromoRedemption::query()->count());
    }

    public function test_midtrans_expiry_cancels_booking_and_releases_promo(): void
    {
        $promo = $this->makePromo();

        // Build the booking directly: createBookingWithPromo() swaps in a
        // mocked MidtransService, but this test exercises the real status
        // transition path.
        $booking = $this->makeBooking([
            'subtotal_amount' => 2000000,
            'discount_amount' => 200000,
            'promo_code_id' => $promo->id,
            'total_price' => 1800000,
        ]);

        PromoRedemption::create([
            'promo_code_id' => $promo->id,
            'user_id' => $this->user->id,
            'booking_id' => $booking->id,
            'discount_amount' => 200000,
            'status' => PromoRedemption::STATUS_APPLIED,
        ]);

        $promo->update(['used_count' => 1]);

        $result = app(MidtransService::class)->syncBookingPaymentStatus($booking->load('tourPackage'), [
            'order_id' => 'BOOKING-'.$booking->id.'-20260101000000-1234',
            'transaction_id' => 'txn-promo-expired',
            'transaction_status' => 'expire',
            'payment_type' => 'qris',
            'gross_amount' => '1800000',
            'status_code' => '200',
        ]);

        $this->assertSame('cancelled', $result['booking_status']);
        $this->assertSame(0, $promo->fresh()->used_count);
        $this->assertSame(
            PromoRedemption::STATUS_RELEASED,
            PromoRedemption::query()->firstOrFail()->status
        );
    }

    // ---------- Midtrans amount contract ----------

    public function test_midtrans_gross_amount_uses_the_final_total(): void
    {
        $promo = $this->makePromo([
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 500000,
        ]);

        $booking = $this->makeBooking([
            'discount_amount' => 500000,
            'promo_code_id' => $promo->id,
            'total_price' => 1500000,
        ])->load('tourPackage');

        $params = app(MidtransService::class)->buildSnapParams(
            $booking,
            $this->user,
            'BOOKING-TEST-1'
        );

        $this->assertSame(1500000, $params['transaction_details']['gross_amount']);

        $discountLine = collect($params['item_details'])
            ->firstWhere('id', 'PROMO');

        $this->assertNotNull($discountLine);
        $this->assertSame(-500000, $discountLine['price']);
    }

    // ---------- Admin authorization + CRUD ----------

    public function test_regular_user_cannot_access_admin_promo_crud(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.promos.index'))
            ->assertForbidden();

        $this->actingAs($this->user)
            ->post(route('admin.promos.store'), [
                'code' => 'HACKED',
                'discount_type' => 'fixed',
                'discount_value' => 100000,
                'per_user_limit' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('promo_codes', ['code' => 'HACKED']);
    }

    public function test_admin_can_create_a_promo_and_it_is_logged(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.promos.store'), [
                'code' => 'newyear25',
                'description' => 'New year sale',
                'discount_type' => 'percent',
                'discount_value' => 25,
                'max_discount' => 500000,
                'min_transaction' => 1000000,
                'usage_limit' => 100,
                'per_user_limit' => 2,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.promos.index'));

        $promo = PromoCode::query()->firstOrFail();

        $this->assertSame('NEWYEAR25', $promo->code);
        $this->assertSame('percent', $promo->discount_type);
        $this->assertTrue($promo->is_active);

        $this->assertDatabaseHas('activity_logs', [
            'description' => 'Promo code created',
            'subject_id' => $promo->id,
        ]);
        $this->assertSame(1, ActivityLog::query()->count());
    }

    public function test_admin_can_deactivate_a_promo(): void
    {
        $promo = $this->makePromo();

        $this->actingAs($this->admin)
            ->patch(route('admin.promos.toggle', $promo))
            ->assertRedirect();

        $this->assertFalse($promo->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', [
            'description' => 'Promo code deactivated',
            'subject_id' => $promo->id,
        ]);
    }

    public function test_admin_can_update_a_promo_and_it_is_logged(): void
    {
        $promo = $this->makePromo();

        $this->actingAs($this->admin)
            ->put(route('admin.promos.update', $promo), [
                'code' => 'summer20',
                'description' => 'Updated promo',
                'discount_type' => 'percent',
                'discount_value' => 20,
                'max_discount' => 400000,
                'min_transaction' => 500000,
                'usage_limit' => 50,
                'per_user_limit' => 3,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.promos.index'));

        $promo->refresh();

        $this->assertSame('SUMMER20', $promo->code);
        $this->assertSame('20.00', $promo->discount_value);
        $this->assertSame(3, $promo->per_user_limit);
        $this->assertDatabaseHas('activity_logs', [
            'description' => 'Promo code updated',
            'subject_id' => $promo->id,
        ]);
    }

    public function test_admin_form_rejects_invalid_promo_rules(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.promos.store'), [
                'code' => 'TOOMUCH',
                'discount_type' => 'percent',
                'discount_value' => 150,
                'per_user_limit' => 1,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('discount_value');

        $this->makePromo();

        $this->actingAs($this->admin)
            ->post(route('admin.promos.store'), [
                'code' => 'summer10',
                'discount_type' => 'fixed',
                'discount_value' => 100000,
                'per_user_limit' => 1,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, PromoCode::query()->count());
    }

    public function test_admin_can_see_the_redemption_history_page(): void
    {
        $promo = $this->makePromo();
        $booking = $this->makeBooking([
            'discount_amount' => 200000,
            'promo_code_id' => $promo->id,
            'total_price' => 1800000,
        ]);

        PromoRedemption::create([
            'promo_code_id' => $promo->id,
            'user_id' => $this->user->id,
            'booking_id' => $booking->id,
            'discount_amount' => 200000,
            'status' => PromoRedemption::STATUS_APPLIED,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.promos.show', $promo))
            ->assertOk()
            ->assertSee('SUMMER10')
            ->assertSee('Promo Tester');
    }
}
