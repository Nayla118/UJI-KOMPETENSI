<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\MidtransService;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Payment deadline + automatic expiry.
 *
 * Background: a pending booking holds seats. Once the payment window passes,
 * the scheduler must release those seats (and promo quota) without ever
 * cancelling a booking that a Midtrans webhook already settled.
 *
 * Time is frozen with Carbon::setTestNow so "before/after the deadline" is
 * deterministic. No test performs a real Midtrans network call.
 */
class BookingPaymentExpiryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Destination $destination;

    private TourPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-25 10:00:00'));

        config([
            'travelo.payment.ttl_minutes' => 60,
            'travelo.payment.expire_midtrans_transaction' => true,
        ]);

        $this->user = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Expiry Tester',
            'email' => 'expiry-'.uniqid().'@example.com',
        ]);

        $this->destination = Destination::create([
            'name' => 'Bali',
            'city' => 'Denpasar',
            'country' => 'Indonesia',
        ]);

        $this->package = TourPackage::create([
            'destination_id' => $this->destination->id,
            'title' => 'Bali Trip',
            'price' => 1000000,
            'duration_days' => 3,
            'max_people' => 10,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------- helpers ----------

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->user->id,
            'tour_package_id' => $this->package->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 2,
            'subtotal_amount' => 2000000,
            'discount_amount' => 0,
            'total_price' => 2000000,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
            'payment_expires_at' => now()->addMinutes(60),
        ], $overrides));
    }

    private function paymentFor(Booking $booking, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'booking_id' => $booking->id,
            'midtrans_order_id' => 'BOOKING-'.$booking->id.'-20261025100000-1234',
            'payment_status' => 'pending',
            'amount' => $booking->total_price,
        ], $overrides));
    }

    private function makePromo(array $overrides = []): PromoCode
    {
        return PromoCode::create(array_merge([
            'code' => 'EXPIRY10',
            'description' => 'Expiry test promo',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 10,
            'min_transaction' => 0,
            'per_user_limit' => 1,
            'used_count' => 1,
            'is_active' => true,
        ], $overrides));
    }

    private function runExpiry(): void
    {
        $this->artisan('travelo:expire-unpaid-bookings')->assertExitCode(0);
    }

    private function seatsBooked(string $date): int
    {
        return app(AvailabilityService::class)
            ->forDate($this->package, $date, 1)['booked'];
    }

    // ---------- Booking creation ----------

    public function test_new_booking_receives_the_configured_deadline_and_exposes_countdown_fields(): void
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->once()->andReturn('snap-token');
        });

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'tour_package_id' => $this->package->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 2,
        ])->assertCreated();

        $booking = Booking::query()->firstOrFail();

        $this->assertSame(
            now()->addMinutes(60)->toDateTimeString(),
            $booking->payment_expires_at->toDateTimeString()
        );

        $response
            ->assertJsonPath('data.booking.payment_expired', false)
            ->assertJsonPath('data.booking.payment_expires_in_seconds', 3600)
            ->assertJsonPath(
                'data.booking.payment_expires_at',
                now()->addHour()->toIso8601ZuluString()
            )
            ->assertJsonPath(
                'data.booking.server_time',
                now()->toIso8601ZuluString()
            );
    }

    public function test_payment_status_endpoint_reports_server_side_remaining_seconds(): void
    {
        $booking = $this->booking(['payment_expires_at' => now()->addSeconds(90)]);
        $this->paymentFor($booking);

        Sanctum::actingAs($this->user);

        $this->getJson('/api/bookings/'.$booking->id.'/payment-status')
            ->assertOk()
            ->assertJsonPath('data.payment_expires_in_seconds', 90)
            ->assertJsonPath('data.payment_expired', false)
            ->assertJsonPath('data.server_time', now()->toIso8601ZuluString());

        // One second past the deadline: the backend already reports expired
        // even before the scheduler has run.
        Carbon::setTestNow(Carbon::parse('2026-10-25 10:01:31'));

        $this->getJson('/api/bookings/'.$booking->id.'/payment-status')
            ->assertOk()
            ->assertJsonPath('data.payment_expires_in_seconds', 0)
            ->assertJsonPath('data.payment_expired', true);
    }

    public function test_snap_token_cannot_be_created_after_the_window_closed(): void
    {
        $booking = $this->booking(['payment_expires_at' => now()->subSecond()]);
        $this->paymentFor($booking);

        Sanctum::actingAs($this->user);

        $this->postJson('/api/payments/create-snap-token', [
            'booking_id' => $booking->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'payment_expired');
    }

    // ---------- Scheduler behaviour ----------

    public function test_expired_booking_is_cancelled_and_releases_quota(): void
    {
        $booking = $this->booking(['payment_expires_at' => now()->subMinute()]);
        $payment = $this->paymentFor($booking);

        $date = $booking->booking_date->toDateString();
        $this->assertSame(2, $this->seatsBooked($date));

        $this->runExpiry();

        $booking->refresh();
        $payment->refresh();

        $this->assertSame('cancelled', $booking->booking_status);
        $this->assertSame('expired', $booking->payment_status);
        $this->assertSame('expired', $payment->payment_status);

        // Cancel/expired bookings no longer hold seats.
        $this->assertSame(0, $this->seatsBooked($date));

        $this->assertDatabaseHas('activity_logs', [
            'description' => 'Booking payment expired',
            'subject_id' => $booking->id,
        ]);
    }

    public function test_booking_before_its_deadline_is_untouched(): void
    {
        $booking = $this->booking(['payment_expires_at' => now()->addMinute()]);
        $payment = $this->paymentFor($booking);

        $this->runExpiry();

        $this->assertSame('pending', $booking->fresh()->booking_status);
        $this->assertSame('pending', $booking->fresh()->payment_status);
        $this->assertSame('pending', $payment->fresh()->payment_status);
        $this->assertSame(2, $this->seatsBooked($booking->booking_date->toDateString()));
        $this->assertDatabaseMissing('activity_logs', [
            'description' => 'Booking payment expired',
        ]);
    }

    public function test_paid_booking_is_never_expired(): void
    {
        $booking = $this->booking([
            'payment_status' => 'paid',
            'booking_status' => 'confirmed',
            'payment_expires_at' => now()->subHour(),
        ]);
        $this->paymentFor($booking, ['payment_status' => 'paid', 'paid_at' => now()]);

        $this->runExpiry();

        $this->assertSame('confirmed', $booking->fresh()->booking_status);
        $this->assertSame('paid', $booking->fresh()->payment_status);
        $this->assertSame(2, $this->seatsBooked($booking->booking_date->toDateString()));
    }

    public function test_a_settled_payment_is_skipped_even_if_the_booking_row_still_looks_pending(): void
    {
        // Simulates a webhook that wrote the payment row but whose booking
        // update has not been observed yet. The command must trust the fresh
        // payment state and leave the booking alone.
        $booking = $this->booking(['payment_expires_at' => now()->subMinute()]);
        $this->paymentFor($booking, ['payment_status' => 'paid', 'paid_at' => now()]);

        $this->runExpiry();

        $this->assertSame('pending', $booking->fresh()->booking_status);
        $this->assertSame('pending', $booking->fresh()->payment_status);
        $this->assertSame(2, $this->seatsBooked($booking->booking_date->toDateString()));
    }

    public function test_command_is_idempotent_and_releases_promo_quota_once(): void
    {
        $promo = $this->makePromo();
        $booking = $this->booking([
            'discount_amount' => 200000,
            'promo_code_id' => $promo->id,
            'total_price' => 1800000,
            'payment_expires_at' => now()->subMinute(),
        ]);
        $this->paymentFor($booking);

        PromoRedemption::create([
            'promo_code_id' => $promo->id,
            'user_id' => $this->user->id,
            'booking_id' => $booking->id,
            'discount_amount' => 200000,
            'status' => PromoRedemption::STATUS_APPLIED,
        ]);

        $this->mock(PushNotificationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('notifyBookingExpired')->once();
        });

        $this->runExpiry();
        $this->runExpiry();

        $this->assertSame(0, $promo->fresh()->used_count);
        $this->assertSame(
            PromoRedemption::STATUS_RELEASED,
            PromoRedemption::query()->firstOrFail()->status
        );
        $this->assertSame(1, ActivityLog::query()
            ->where('description', 'Booking payment expired')
            ->count());
    }

    public function test_a_midtrans_expire_failure_does_not_block_local_expiry(): void
    {
        $booking = $this->booking(['payment_expires_at' => now()->subMinute()]);
        $payment = $this->paymentFor($booking);

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($payment) {
            $mock->shouldReceive('expireTransaction')
                ->once()
                ->with($payment->midtrans_order_id)
                ->andThrow(new \RuntimeException('network down'));
        });

        $this->runExpiry();

        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertSame('expired', $booking->fresh()->payment_status);
        $this->assertSame(0, $this->seatsBooked($booking->booking_date->toDateString()));
    }

    public function test_expiry_sends_a_push_notification(): void
    {
        $booking = $this->booking(['payment_expires_at' => now()->subMinute()]);

        $this->mock(PushNotificationService::class, function (MockInterface $mock) use ($booking) {
            $mock->shouldReceive('notifyBookingExpired')
                ->once()
                ->withArgs(fn (Booking $sent) => $sent->id === $booking->id);
        });

        $this->runExpiry();
    }

    // ---------- Late webhook after expiry ----------

    public function test_late_paid_webhook_revives_a_booking_when_seats_are_still_available(): void
    {
        $promo = $this->makePromo();
        $booking = $this->booking([
            'discount_amount' => 200000,
            'promo_code_id' => $promo->id,
            'total_price' => 1800000,
            'payment_expires_at' => now()->subMinute(),
        ]);
        $this->paymentFor($booking);

        PromoRedemption::create([
            'promo_code_id' => $promo->id,
            'user_id' => $this->user->id,
            'booking_id' => $booking->id,
            'discount_amount' => 200000,
            'status' => PromoRedemption::STATUS_APPLIED,
        ]);

        $this->mock(PushNotificationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('notifyBookingExpired')->once();
            $mock->shouldReceive('notifyPayment')->once();
        });

        $this->runExpiry();

        $this->assertSame(0, $promo->fresh()->used_count);

        $result = app(MidtransService::class)->syncBookingPaymentStatus(
            $booking->fresh(),
            [
                'order_id' => 'BOOKING-'.$booking->id.'-20261025100000-1234',
                'transaction_id' => 'txn-late-paid',
                'transaction_status' => 'settlement',
                'payment_type' => 'qris',
                'gross_amount' => '1800000',
                'status_code' => '200',
            ]
        );

        $this->assertSame('paid', $result['payment_status']);
        $this->assertSame('confirmed', $result['booking_status']);

        $booking->refresh();
        $this->assertSame('confirmed', $booking->booking_status);
        $this->assertSame('paid', $booking->payment_status);

        // Seats and the discount the customer actually paid are honored.
        $this->assertSame(2, $this->seatsBooked($booking->booking_date->toDateString()));
        $this->assertSame(1, $promo->fresh()->used_count);
        $this->assertSame(
            PromoRedemption::STATUS_APPLIED,
            PromoRedemption::query()->firstOrFail()->status
        );
        $this->assertDatabaseHas('activity_logs', [
            'description' => 'Booking revived after late payment',
            'subject_id' => $booking->id,
        ]);
    }

    public function test_late_paid_webhook_does_not_revive_a_booking_when_no_seats_remain(): void
    {
        $this->package->update(['max_people' => 3]);

        $booking = $this->booking(['payment_expires_at' => now()->subMinute()]);
        $this->paymentFor($booking);

        $this->mock(PushNotificationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('notifyBookingExpired')->once();
            $mock->shouldReceive('notifyLatePaymentRequiresRefund')->once();
        });

        $this->runExpiry();

        // Another traveller takes every remaining seat while the late payment
        // is still travelling through Midtrans.
        $this->booking([
            'user_id' => $this->user->id,
            'people_count' => 3,
            'total_price' => 3000000,
        ]);

        $result = app(MidtransService::class)->syncBookingPaymentStatus(
            $booking->fresh(),
            [
                'order_id' => 'BOOKING-'.$booking->id.'-20261025100000-1234',
                'transaction_id' => 'txn-late-paid-no-seats',
                'transaction_status' => 'settlement',
                'payment_type' => 'qris',
                'gross_amount' => '2000000',
                'status_code' => '200',
            ]
        );

        $this->assertSame('paid', $result['payment_status']);
        $this->assertSame('cancelled', $result['booking_status']);

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('cancelled', $booking->booking_status);

        // The money is recorded (payment_status paid) for a refund, but the
        // released seats are not handed back.
        $this->assertSame(
            3,
            $this->seatsBooked($booking->booking_date->toDateString())
        );
        $this->assertDatabaseHas('activity_logs', [
            'description' => 'Late payment requires refund',
            'subject_id' => $booking->id,
        ]);
    }

    public function test_expiry_uses_the_configured_ttl(): void
    {
        config(['travelo.payment.ttl_minutes' => 5]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->once()->andReturn('snap-token');
        });

        Sanctum::actingAs($this->user);

        $this->postJson('/api/bookings', [
            'tour_package_id' => $this->package->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 1,
        ])->assertCreated();

        $booking = Booking::query()->firstOrFail();

        $this->assertSame(
            now()->addMinutes(5)->toDateTimeString(),
            $booking->payment_expires_at->toDateTimeString()
        );
    }
}
