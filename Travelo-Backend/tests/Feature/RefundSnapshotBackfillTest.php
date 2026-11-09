<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\Destination;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Legacy refunds were full refunds but predate the snapshot columns. The
 * additive data migration must fill only the missing snapshot and must never
 * touch an existing value or a request that is not refunded.
 */
class RefundSnapshotBackfillTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Traveller',
            'email' => 'traveller-'.uniqid().'@example.com',
        ]);

        $destination = Destination::create([
            'name' => 'Bali '.uniqid(),
            'city' => 'Denpasar',
            'country' => 'Indonesia',
        ]);

        $package = TourPackage::create([
            'destination_id' => $destination->id,
            'title' => 'Bali Trip',
            'price' => 1000000,
            'duration_days' => 3,
            'max_people' => 10,
        ]);

        $this->booking = Booking::create([
            'user_id' => $user->id,
            'tour_package_id' => $package->id,
            'booking_date' => now()->addDays(10)->toDateString(),
            'people_count' => 2,
            'subtotal_amount' => 2000000,
            'discount_amount' => 500000,
            'total_price' => 1500000,
            'payment_status' => 'paid',
            'booking_status' => 'cancelled',
        ]);
    }

    public function test_backfill_uses_the_paid_total_for_legacy_refunded_rows_only(): void
    {
        $legacy = $this->changeRequest([
            'refund_status' => BookingChangeRequest::REFUND_REFUNDED,
            'refund_percentage' => null,
            'refund_amount' => null,
            'refunded_at' => now()->subDay(),
        ]);

        $alreadySnapshotted = $this->changeRequest([
            'refund_status' => BookingChangeRequest::REFUND_REFUNDED,
            'refund_percentage' => 50,
            'refund_amount' => 750000,
            'refunded_at' => now()->subDay(),
        ]);

        $notRefunded = $this->changeRequest([
            'refund_status' => BookingChangeRequest::REFUND_PENDING,
            'refund_percentage' => null,
            'refund_amount' => null,
        ]);

        (require database_path(
            'migrations/2026_11_08_000001_backfill_refund_snapshot_on_booking_change_requests.php'
        ))->up();

        $legacy->refresh();
        $this->assertSame(100, $legacy->refund_percentage);
        $this->assertEqualsWithDelta(1500000, (float) $legacy->refund_amount, 0.01);

        $alreadySnapshotted->refresh();
        $this->assertSame(50, $alreadySnapshotted->refund_percentage);
        $this->assertEqualsWithDelta(750000, (float) $alreadySnapshotted->refund_amount, 0.01);

        $notRefunded->refresh();
        $this->assertNull($notRefunded->refund_percentage);
        $this->assertNull($notRefunded->refund_amount);

        $this->assertDatabaseCount('booking_change_requests', 3);
    }

    private function changeRequest(array $overrides): BookingChangeRequest
    {
        return BookingChangeRequest::create(array_merge([
            'booking_id' => $this->booking->id,
            'user_id' => $this->booking->user_id,
            'type' => BookingChangeRequest::TYPE_CANCELLATION,
            'status' => BookingChangeRequest::STATUS_APPROVED,
            'original_booking_date' => $this->booking->booking_date->toDateString(),
            'refund_status' => BookingChangeRequest::REFUND_REFUNDED,
        ], $overrides));
    }
}
