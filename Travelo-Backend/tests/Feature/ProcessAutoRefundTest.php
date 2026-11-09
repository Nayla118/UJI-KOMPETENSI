<?php

namespace Tests\Feature;

use App\Jobs\ProcessAutoRefund;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\Destination;
use App\Models\Payment;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The queued Midtrans refund must send the snapshot stored at approval time
 * (`refund_amount`), never the booking total. A partial 50% refund therefore
 * sends half the amount even when the booking was paid in full.
 */
class ProcessAutoRefundTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;

    private BookingChangeRequest $change;

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
            'discount_amount' => 0,
            'total_price' => 2000000,
            'payment_status' => 'paid',
            'booking_status' => 'cancelled',
        ]);

        Payment::create([
            'booking_id' => $this->booking->id,
            'midtrans_transaction_id' => 'midtrans-transaction-123',
            'midtrans_order_id' => 'BOOKING-'.$this->booking->id,
            'payment_status' => Payment::STATUS_PAID,
            'payment_method' => 'qris',
            'amount' => 2000000,
            'paid_at' => now(),
            'transaction_status' => 'settlement',
        ]);

        // Approval already happened: the snapshot is 50% = 1,000,000 while
        // the booking total remains 2,000,000.
        $this->change = BookingChangeRequest::create([
            'booking_id' => $this->booking->id,
            'user_id' => $user->id,
            'type' => BookingChangeRequest::TYPE_CANCELLATION,
            'status' => BookingChangeRequest::STATUS_APPROVED,
            'original_booking_date' => $this->booking->booking_date->toDateString(),
            'refund_status' => BookingChangeRequest::REFUND_PROCESSING,
            'refund_percentage' => 50,
            'refund_amount' => 1000000,
            'reviewed_at' => now(),
        ]);
    }

    public function test_auto_refund_sends_the_snapshot_amount_not_the_booking_total(): void
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('refundTransaction')
                ->once()
                ->withArgs(function (
                    string $transactionId,
                    string $refundKey,
                    int $amount,
                    string $reason
                ) {
                    return $transactionId === 'midtrans-transaction-123'
                        && $refundKey === 'travelo_refund_'.$this->change->id
                        && $amount === 1000000
                        && str_contains($reason, (string) $this->booking->id);
                })
                ->andReturn([
                    'success' => true,
                    'reference' => 'midtrans-refund-001',
                ]);
        });

        $this->mock(PushNotificationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('notifyRefundResult')
                ->once()
                ->withArgs(function (Booking $booking, float $amount, bool $success, ?string $reason) {
                    return $booking->id === $this->booking->id
                        && (int) $amount === 1000000
                        && $success === true
                        && $reason === null;
                });
        });

        $job = new ProcessAutoRefund($this->change);
        $job->handle(
            app(MidtransService::class),
            app(PushNotificationService::class)
        );

        $this->change->refresh();

        $this->assertSame(BookingChangeRequest::REFUND_REFUNDED, $this->change->refund_status);
        $this->assertSame('midtrans-refund-001', $this->change->refund_reference);
        $this->assertNotNull($this->change->refunded_at);
        $this->assertEqualsWithDelta(1000000, (float) $this->change->refund_amount, 0.01);

        // The booking total is untouched; only the snapshot was refunded.
        $this->assertEqualsWithDelta(2000000, (float) $this->booking->fresh()->total_price, 0.01);
    }

    public function test_refunded_request_is_never_sent_to_midtrans_again(): void
    {
        $this->change->update(['refund_status' => BookingChangeRequest::REFUND_REFUNDED]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('refundTransaction');
        });

        $this->mock(PushNotificationService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('notifyRefundResult');
        });

        $job = new ProcessAutoRefund($this->change);
        $job->handle(
            app(MidtransService::class),
            app(PushNotificationService::class)
        );

        $this->assertSame(
            BookingChangeRequest::REFUND_REFUNDED,
            $this->change->fresh()->refund_status
        );
    }
}
