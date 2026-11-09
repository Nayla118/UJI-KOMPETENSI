<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Destination;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\RefundPolicyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tiered cancellation refund policy: thresholds, amounts, promo discount
 * handling, and preview endpoint ownership.
 */
class RefundPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Destination $destination;

    private TourPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Traveller',
            'email' => 'traveller-'.uniqid().'@example.com',
        ]);

        $this->destination = Destination::create([
            'name' => 'Bali '.uniqid(),
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

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->user->id,
            'tour_package_id' => $this->package->id,
            'booking_date' => now()->addDays(10)->toDateString(),
            'people_count' => 2,
            'subtotal_amount' => 2000000,
            'discount_amount' => 0,
            'total_price' => 2000000,
            'payment_status' => 'paid',
            'booking_status' => 'confirmed',
        ], $overrides));
    }

    private function service(): RefundPolicyService
    {
        return app(RefundPolicyService::class);
    }

    // ---------- Tier thresholds ----------

    public function test_seven_or_more_days_before_trip_gets_full_refund(): void
    {
        $booking = $this->booking(['booking_date' => now()->addDays(7)->toDateString()]);

        $result = $this->service()->calculate($booking);

        $this->assertTrue($result['eligible']);
        $this->assertSame(100, $result['percentage']);
        $this->assertSame(2000000.0, $result['refund_amount']);
        $this->assertSame('full', $result['tier']);
    }

    public function test_six_days_before_trip_gets_partial_refund(): void
    {
        $booking = $this->booking(['booking_date' => now()->addDays(6)->toDateString()]);

        $result = $this->service()->calculate($booking);

        $this->assertTrue($result['eligible']);
        $this->assertSame(50, $result['percentage']);
        $this->assertSame(1000000.0, $result['refund_amount']);
        $this->assertSame('partial', $result['tier']);
    }

    public function test_three_days_before_trip_gets_partial_refund(): void
    {
        $booking = $this->booking(['booking_date' => now()->addDays(3)->toDateString()]);

        $result = $this->service()->calculate($booking);

        $this->assertTrue($result['eligible']);
        $this->assertSame(50, $result['percentage']);
        $this->assertSame(1000000.0, $result['refund_amount']);
    }

    public function test_two_days_before_trip_gets_no_refund(): void
    {
        $booking = $this->booking(['booking_date' => now()->addDays(2)->toDateString()]);

        $result = $this->service()->calculate($booking);

        $this->assertTrue($result['eligible']);
        $this->assertSame(0, $result['percentage']);
        $this->assertSame(0.0, $result['refund_amount']);
        $this->assertSame('none', $result['tier']);
    }

    public function test_today_gets_no_refund(): void
    {
        $booking = $this->booking(['booking_date' => now()->toDateString()]);

        $result = $this->service()->calculate($booking);

        $this->assertTrue($result['eligible']);
        $this->assertSame(0, $result['percentage']);
        $this->assertSame(0.0, $result['refund_amount']);
        $this->assertSame('none', $result['tier']);
    }

    // ---------- Not eligible cases ----------

    public function test_unpaid_booking_is_not_refundable(): void
    {
        $booking = $this->booking([
            'payment_status' => 'pending',
            'booking_status' => 'pending',
            'total_price' => 2000000,
        ]);

        $result = $this->service()->calculate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertSame('not_paid', $result['reason']);
        $this->assertSame(0, $result['percentage']);
    }

    public function test_used_ticket_is_not_refundable(): void
    {
        $booking = $this->booking([
            'ticket_status' => Booking::TICKET_USED,
            'ticket_used_at' => now(),
        ]);

        $result = $this->service()->calculate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertSame('ticket_used', $result['reason']);
    }

    public function test_past_visit_is_not_refundable(): void
    {
        $booking = $this->booking(['booking_date' => now()->subDay()->toDateString()]);

        $result = $this->service()->calculate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertSame('past_visit', $result['reason']);
    }

    // ---------- Timezone ----------

    public function test_days_before_uses_ticket_timezone(): void
    {
        // Same day -> 0 days before, computed in Jakarta time.
        config(['travelo.ticket.timezone' => 'Asia/Jakarta']);

        $jakartaToday = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        $booking = $this->booking(['booking_date' => $jakartaToday->toDateString()]);

        $result = $this->service()->calculate(
            $booking,
            CarbonImmutable::now('Asia/Jakarta')
        );

        $this->assertSame(0, $result['days_before']);
        $this->assertSame('none', $result['tier']);
    }

    // ---------- Promo discount ----------

    public function test_refund_is_based_on_amount_paid_not_subtotal(): void
    {
        $booking = $this->booking([
            'subtotal_amount' => 2000000,
            'discount_amount' => 500000,
            'total_price' => 1500000,
            'booking_date' => now()->addDays(7)->toDateString(),
        ]);

        $result = $this->service()->calculate($booking);

        $this->assertTrue($result['eligible']);
        $this->assertSame(100, $result['percentage']);
        $this->assertSame(1500000.0, $result['refund_amount']);
    }

    // ---------- Preview endpoint ----------

    public function test_refund_preview_returns_tier_and_amount_for_owner(): void
    {
        Sanctum::actingAs($this->user);

        $booking = $this->booking(['booking_date' => now()->addDays(6)->toDateString()]);

        $this->getJson("/api/bookings/{$booking->id}/refund-preview")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.percentage', 50)
            ->assertJsonPath('data.refund_amount', 1000000)
            ->assertJsonPath('data.tier', 'partial')
            ->assertJsonPath('data.eligible', true);
    }

    public function test_refund_preview_requires_ownership(): void
    {
        Sanctum::actingAs($this->user);

        $stranger = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Stranger',
            'email' => 'stranger-'.uniqid().'@example.com',
        ]);

        $booking = $this->booking();

        // Create a booking owned by the stranger (user_id set directly).
        $otherBooking = Booking::create(array_merge([
            'user_id' => $stranger->id,
            'tour_package_id' => $this->package->id,
            'booking_date' => now()->addDays(6)->toDateString(),
            'people_count' => 1,
            'subtotal_amount' => 1000000,
            'discount_amount' => 0,
            'total_price' => 1000000,
            'payment_status' => 'paid',
            'booking_status' => 'confirmed',
        ]));

        $this->getJson("/api/bookings/{$otherBooking->id}/refund-preview")
            ->assertNotFound();
    }
}
