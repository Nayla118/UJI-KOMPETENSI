<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Destination;
use App\Models\Payment;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end payment state machine tests (no Midtrans network calls).
 *
 * Covers acceptance criteria:
 *  settlement/capture-valid -> paid + confirmed
 *  pending                 -> pending (waiting)
 *  deny/failure            -> failed
 *  expire                  -> expired
 *  cancel                  -> cancelled
 *  duplicate / out-of-order / late notifications are safe (idempotent,
 *  no downgrade from paid, gross_amount validated).
 */
class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private MidtransService $service;

    private User $user;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        // MidtransService constructor only reads config; no network here.
        $this->service = new MidtransService();

        // NOTE: users table has no password/remember_token columns
        // (Firebase auth), so the default UserFactory cannot be used.
        $this->user = User::create([
            'firebase_uid' => 'test-uid-' . uniqid(),
            'name' => 'Test User',
            'email' => 'test-' . uniqid() . '@example.com',
        ]);

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

        $this->booking = Booking::create([
            'user_id' => $this->user->id,
            'tour_package_id' => $tour->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 2,
            'total_price' => 2000000,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ]);
    }

    private function notification(array $overrides = []): array
    {
        return array_merge([
            'order_id' => 'BOOKING-' . $this->booking->id . '-20240101000000-1234',
            'transaction_id' => 'txn-abc-123',
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'gross_amount' => '2000000',
            'status_code' => '200',
        ], $overrides);
    }

    // ---------- 1. Payment berhasil ----------

    public function test_settlement_marks_paid_and_confirmed(): void
    {
        $result = $this->service->syncBookingPaymentStatus($this->booking, $this->notification());

        $this->assertSame('paid', $result['payment_status']);
        $this->assertSame('confirmed', $result['booking_status']);
        $this->assertSame('paid', $this->booking->fresh()->payment_status);
        $this->assertSame('confirmed', $this->booking->fresh()->booking_status);
        $this->assertNotNull($this->booking->fresh()->payment->paid_at);
    }

    public function test_all_midtrans_methods_can_reach_paid(): void
    {
        $paidTypes = [
            ['payment_type' => 'credit_card', 'transaction_status' => 'capture', 'fraud_status' => 'accept', 'bank' => 'bca', 'masked_card' => '481111-1114'],
            ['payment_type' => 'qris', 'transaction_status' => 'settlement'],
            ['payment_type' => 'gopay', 'transaction_status' => 'settlement'],
            ['payment_type' => 'shopeepay', 'transaction_status' => 'settlement'],
            ['payment_type' => 'ovo', 'transaction_status' => 'settlement'],
            ['payment_type' => 'dana', 'transaction_status' => 'settlement'],
            ['payment_type' => 'linkaja', 'transaction_status' => 'settlement'],
            ['payment_type' => 'bank_transfer', 'transaction_status' => 'settlement', 'va_numbers' => [['bank' => 'bca', 'va_number' => '1234567890']]],
            ['payment_type' => 'bank_transfer', 'transaction_status' => 'settlement', 'permata_va_number' => '8567000012345678'],
            ['payment_type' => 'bank_transfer', 'transaction_status' => 'settlement', 'bill_key' => '88888', 'biller_code' => '70012'],
            ['payment_type' => 'echannel', 'transaction_status' => 'settlement', 'bill_key' => '88888', 'biller_code' => '70012'],
            ['payment_type' => 'bca_klikpay', 'transaction_status' => 'settlement'],
            ['payment_type' => 'bri_epay', 'transaction_status' => 'settlement'],
            ['payment_type' => 'cimb_clicks', 'transaction_status' => 'settlement'],
            ['payment_type' => 'danamon_online', 'transaction_status' => 'settlement'],
            ['payment_type' => 'akulaku', 'transaction_status' => 'settlement'],
            ['payment_type' => 'kredivo', 'transaction_status' => 'settlement'],
            ['payment_type' => 'uob_ezpay', 'transaction_status' => 'settlement'],
            ['payment_type' => 'cstore', 'transaction_status' => 'settlement', 'store' => 'indomaret', 'payment_code' => 'IND12345'],
        ];

        foreach ($paidTypes as $i => $payload) {
            $booking = $this->freshBooking();
            $result = $this->service->syncBookingPaymentStatus(
                $booking,
                $this->notificationFor($booking, $payload)
            );

            $this->assertSame('paid', $result['payment_status'], 'payload #' . $i . ' ' . json_encode($payload));
            $this->assertSame('confirmed', $result['booking_status'], 'payload #' . $i);
        }
    }

    // ---------- 2. Masih pending ----------

    public function test_pending_stays_pending(): void
    {
        $result = $this->service->syncBookingPaymentStatus(
            $this->booking,
            $this->notification(['transaction_status' => 'pending', 'payment_type' => 'bank_transfer'])
        );

        $this->assertSame('pending', $result['payment_status']);
        $this->assertSame('pending', $result['booking_status']);
    }

    public function test_capture_challenge_stays_pending(): void
    {
        $this->assertSame(
            'pending',
            $this->service->mapMidtransStatusToLocalStatus('capture', 'challenge')
        );
    }

    // ---------- 3/4/5. Expired / cancelled / failed ----------

    public function test_expire_marks_expired(): void
    {
        $result = $this->service->syncBookingPaymentStatus(
            $this->booking,
            $this->notification(['transaction_status' => 'expire'])
        );

        $this->assertSame('expired', $result['payment_status']);
        $this->assertSame('cancelled', $result['booking_status']);
    }

    public function test_cancel_marks_failed_with_cancelled_booking(): void
    {
        $result = $this->service->syncBookingPaymentStatus(
            $this->booking,
            $this->notification(['transaction_status' => 'cancel'])
        );

        // Payment stays inside DB enum (failed); booking becomes cancelled;
        // raw 'cancel' is preserved so the app can show a distinct state.
        $this->assertSame('failed', $result['payment_status']);
        $this->assertSame('cancelled', $result['booking_status']);
        $this->assertSame('cancel', $this->booking->fresh()->payment->transaction_status);
    }

    public function test_deny_and_failure_mark_failed(): void
    {
        foreach (['deny', 'failure'] as $status) {
            $booking = $this->freshBooking();
            $result = $this->service->syncBookingPaymentStatus(
                $booking,
                $this->notificationFor($booking, ['transaction_status' => $status])
            );

            $this->assertSame('failed', $result['payment_status'], $status);
            $this->assertSame('cancelled', $result['booking_status'], $status);
        }
    }

    // ---------- 6/7/8. Terlambat, ganda, out-of-order ----------

    public function test_duplicate_settlement_is_idempotent(): void
    {
        $payload = $this->notification();

        $first = $this->service->syncBookingPaymentStatus($this->booking, $payload);
        $paymentId = $first['payment']->id;
        $paidAt = $first['payment']->paid_at;

        $second = $this->service->syncBookingPaymentStatus($this->booking->fresh(), $payload);

        $this->assertSame('paid', $second['payment_status']);
        $this->assertSame($paymentId, $second['payment']->id);
        $this->assertSame(1, Payment::where('booking_id', $this->booking->id)->count());
        $this->assertEquals($paidAt->toDateTimeString(), $second['payment']->paid_at->toDateTimeString());
    }

    public function test_pending_after_settlement_does_not_downgrade(): void
    {
        $this->service->syncBookingPaymentStatus($this->booking, $this->notification());

        $result = $this->service->syncBookingPaymentStatus(
            $this->booking->fresh(),
            $this->notification([
                'order_id' => 'BOOKING-' . $this->booking->id . '-20240102000000-9999',
                'transaction_status' => 'pending',
            ])
        );

        $this->assertSame('paid', $result['payment_status']);
        $this->assertSame('paid', $this->booking->fresh()->payment->payment_status);
        $this->assertSame('confirmed', $this->booking->fresh()->booking_status);
    }

    public function test_paid_with_wrong_amount_is_rejected(): void
    {
        $result = $this->service->syncBookingPaymentStatus(
            $this->booking,
            $this->notification(['gross_amount' => '1'])
        );

        $this->assertNotSame('paid', $result['payment_status']);
        $this->assertNotSame('confirmed', $this->booking->fresh()->booking_status);
        $this->assertNull($this->booking->fresh()->payment?->paid_at);
    }

    // ---------- Normalisasi per metode ----------

    public function test_normalize_payment_methods(): void
    {
        [$method, $channel] = $this->service->normalizeMidtransPayment([
            'payment_type' => 'bank_transfer',
            'va_numbers' => [['bank' => 'bca', 'va_number' => '123']],
        ]);
        $this->assertSame('bank_transfer', $method);
        $this->assertSame('bca', $channel);

        [$method, $channel] = $this->service->normalizeMidtransPayment([
            'payment_type' => 'cstore', 'store' => 'alfamart', 'payment_code' => 'ALF123',
        ]);
        $this->assertSame('cstore', $method);
        $this->assertSame('alfamart', $channel);

        [$method, $channel] = $this->service->normalizeMidtransPayment(['payment_type' => 'qris']);
        $this->assertSame('qris', $method);
        $this->assertSame('qris', $channel);

        [$method, $channel] = $this->service->normalizeMidtransPayment([
            'payment_type' => 'credit_card', 'bank' => 'bni', 'masked_card' => '481111-1114',
        ]);
        $this->assertSame('credit_card', $method);
        $this->assertSame('bni', $channel);
    }

    // ---------- Order id helpers ----------

    public function test_order_id_round_trip(): void
    {
        $orderId = $this->service->generateOrderId($this->booking);

        $this->assertSame($this->booking->id, $this->service->extractBookingIdFromOrderId($orderId));
        $this->assertNull($this->service->extractBookingIdFromOrderId('INVALID-123'));
    }

    // ---------- verifyBookingAcrossOrders: paid lama menang, order baru prioritas ----------

    public function test_verify_prefers_paid_and_current_order(): void
    {
        $payment = Payment::create([
            'booking_id' => $this->booking->id,
            'midtrans_order_id' => 'BOOKING-' . $this->booking->id . '-NEW',
            'order_history' => ['BOOKING-' . $this->booking->id . '-OLD'],
            'payment_status' => 'pending',
            'amount' => 2000000,
        ]);

        // Case A: old order settled -> paid wins even though current is pending.
        $stub = $this->stubService([
            'BOOKING-' . $this->booking->id . '-NEW' => ['status' => 'pending', 'raw' => ['order_id' => 'NEW', 'transaction_status' => 'pending']],
            'BOOKING-' . $this->booking->id . '-OLD' => ['status' => 'settlement', 'raw' => ['order_id' => 'OLD', 'transaction_status' => 'settlement']],
        ]);
        $data = $stub->verifyBookingAcrossOrders($this->booking->fresh());
        $this->assertSame('settlement', $data['status']);

        // Case B: old order expired, current pending -> current wins (no false expired).
        $stub = $this->stubService([
            'BOOKING-' . $this->booking->id . '-NEW' => ['status' => 'pending', 'raw' => ['order_id' => 'NEW', 'transaction_status' => 'pending']],
            'BOOKING-' . $this->booking->id . '-OLD' => ['status' => 'expire', 'raw' => ['order_id' => 'OLD', 'transaction_status' => 'expire']],
        ]);
        $data = $stub->verifyBookingAcrossOrders($this->booking->fresh());
        $this->assertSame('pending', $data['status']);

        // Case C: current expired -> expired is the truth for this attempt.
        $stub = $this->stubService([
            'BOOKING-' . $this->booking->id . '-NEW' => ['status' => 'expire', 'raw' => ['order_id' => 'NEW', 'transaction_status' => 'expire']],
            'BOOKING-' . $this->booking->id . '-OLD' => ['status' => 'pending', 'raw' => ['order_id' => 'OLD', 'transaction_status' => 'pending']],
        ]);
        $data = $stub->verifyBookingAcrossOrders($this->booking->fresh());
        $this->assertSame('expire', $data['status']);
    }

    // ---------- helpers ----------

    private function freshBooking(): Booking
    {
        return Booking::create([
            'user_id' => $this->user->id,
            'tour_package_id' => $this->booking->tour_package_id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 1,
            'total_price' => 2000000,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ]);
    }

    private function notificationFor(Booking $booking, array $overrides): array
    {
        return $this->notification(array_merge(
            ['order_id' => 'BOOKING-' . $booking->id . '-20240101000000-1234'],
            $overrides
        ));
    }

    /**
     * MidtransService with stubbed network layer.
     *
     * @param  array<string, array{status: string, raw: array}>  $responses
     */
    private function stubService(array $responses): MidtransService
    {
        return new class($responses) extends MidtransService
        {
            public function __construct(private array $responses)
            {
                // Skip parent constructor (config/logs) — pure logic under test.
            }

            public function verifyPayment(string $orderId): array
            {
                if (!isset($this->responses[$orderId])) {
                    throw new \Exception('404 Transaction doesn\'t exist');
                }

                $entry = $this->responses[$orderId];

                return [
                    'status' => $entry['status'],
                    'fraud_status' => $entry['raw']['fraud_status'] ?? null,
                    'payment_type' => $entry['raw']['payment_type'] ?? null,
                    'transaction_id' => $entry['raw']['transaction_id'] ?? null,
                    'gross_amount' => $entry['raw']['gross_amount'] ?? null,
                    'raw' => $entry['raw'],
                ];
            }
        };
    }
}
