<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payment Management Controller
 * Handles payment status updates, verification, and debugging
 */
class PaymentController extends Controller
{
    public function __construct(
        protected MidtransService $midtransService
    ) {}

    /**
     * Get detailed payment status for a booking
     * This is what the Android app should call to check if payment is complete
     */
    public function getStatus(Request $request, int $bookingId): JsonResponse
    {
        // Ownership is enforced by the relationship; a foreign booking is a
        // 404, never a 500 or a leak of another customer's payment state.
        $booking = $request->user()
            ->bookings()
            ->with('payment')
            ->findOrFail($bookingId);

        try {
            $payment = $booking->payment;

            $data = [
                'booking_id' => $booking->id,
                'booking_status' => $booking->booking_status,
                'payment_status' => $booking->payment_status,
                'total_price' => $booking->total_price,
                'bookingStatus' => $booking->booking_status,
                'status' => $booking->payment_status,
            ];

            if ($payment) {
                $data['payment'] = [
                    'id' => $payment->id,
                    'status' => $payment->payment_status,
                    'method' => $payment->payment_method,
                    'channel' => $payment->payment_channel,
                    'transaction_status' => $payment->transaction_status,
                    'amount' => (float) $payment->amount,
                    'midtrans_transaction_id' => $payment->midtrans_transaction_id,
                    'midtrans_order_id' => $payment->midtrans_order_id,
                    'paid_at' => $payment->paid_at,
                    'updated_at' => $payment->updated_at,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get payment status', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment status',
            ], 500);
        }
    }

    /**
     * Verify and sync payment status from Midtrans
     * Call this after payment is completed to update status immediately
     * (Use when webhook is delayed or failed)
     */
    public function verifyAndSync(Request $request, int $bookingId): JsonResponse
    {
        $booking = $request->user()
            ->bookings()
            ->with('payment')
            ->findOrFail($bookingId);

        try {
            $payment = $booking->payment;

            if (!$payment || !$payment->midtrans_order_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment record found. Please create payment first.',
                ], 400);
            }

            Log::info('Payment verification requested', [
                'booking_id' => $booking->id,
                'current_payment_status' => $payment->payment_status,
                'order_id' => $payment->midtrans_order_id,
            ]);

            // If payment is already marked as paid, return immediately
            if ($payment->payment_status === 'paid' && $payment->paid_at) {
                Log::info('Payment already confirmed', ['booking_id' => $booking->id]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment is already confirmed',
                    'data' => [
                        'booking_id' => $booking->id,
                        'booking_status' => $booking->booking_status,
                        'payment_status' => $payment->payment_status,
                        'bookingStatus' => $booking->booking_status,
                        'status' => $payment->payment_status,
                    ],
                ]);
            }

            // Check ALL order_ids ever issued for this booking (current + history).
            // Fixes "paid on older Snap URL but verify only checks the latest".
            try {
                $candidates = $this->midtransService->getOrderCandidates($booking);
                Log::info('Checking Midtrans for orders', ['booking_id' => $booking->id, 'orders' => $candidates]);

                $transactionData = $this->midtransService->verifyBookingAcrossOrders($booking);

                if ($transactionData && !empty($transactionData['raw'])) {
                    Log::info('Got Midtrans response', [
                        'transaction_status' => $transactionData['status'] ?? 'unknown',
                        'fraud_status' => $transactionData['fraud_status'] ?? 'unknown',
                    ]);

                    // Use DB transaction to ensure atomicity
                    DB::beginTransaction();

                    try {
                        $result = $this->midtransService->syncBookingPaymentStatus(
                            $booking,
                            $transactionData['raw']
                        );

                        DB::commit();
                    } catch (\Exception $syncEx) {
                        DB::rollBack();
                        throw $syncEx;
                    }

                    $booking->refresh();
                    $booking->load('payment');

                    Log::info('Payment sync completed', [
                        'booking_id' => $booking->id,
                        'new_payment_status' => $booking->payment->payment_status ?? 'unknown',
                        'new_booking_status' => $booking->booking_status,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Payment status verified',
                        'data' => [
                            'booking_id' => $booking->id,
                            'booking_status' => $booking->booking_status,
                            'payment_status' => $booking->payment->payment_status,
                            'bookingStatus' => $booking->booking_status,
                            'status' => $booking->payment->payment_status,
                        ],
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Midtrans verification timeout/failed', [
                    'booking_id' => $booking->id,
                    'order_id' => $payment->midtrans_order_id,
                    'error' => $e->getMessage(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Could not verify this payment with Midtrans. Please try again.',
                    'data' => [
                        'booking_id' => $booking->id,
                        'booking_status' => $booking->booking_status,
                        'payment_status' => $booking->payment->payment_status,
                        'bookingStatus' => $booking->booking_status,
                        'status' => $booking->payment->payment_status,
                    ],
                ], 502);
            }

            return response()->json([
                'success' => false,
                'message' => 'Could not verify payment',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Retry payment sync for a specific booking
     * Use if payment was successful but status didn't update
     */
    public function retrySync(Request $request, int $bookingId): JsonResponse
    {
        // IDOR fix: only the owner may ask the server to reconcile a booking.
        // Scoping through the relationship also returns 404 for foreign ids.
        $booking = $request->user()
            ->bookings()
            ->with('payment')
            ->findOrFail($bookingId);

        try {
            $payment = $booking->payment;

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment record exists',
                ], 404);
            }

            // Try to get the current status from Midtrans
            $attempts = 3;
            $lastError = null;

            for ($i = 0; $i < $attempts; $i++) {
                try {
                    $transactionData = $this->midtransService->verifyBookingAcrossOrders($booking);
                    
                    if ($transactionData && !empty($transactionData['raw'])) {
                        DB::beginTransaction();

                        try {
                            $result = $this->midtransService->syncBookingPaymentStatus(
                                $booking,
                                $transactionData['raw']
                            );
                            DB::commit();
                        } catch (\Exception $syncEx) {
                            DB::rollBack();
                            throw $syncEx;
                        }

                        $booking->refresh();

                        return response()->json([
                            'success' => true,
                            'message' => 'Payment status successfully updated',
                            'data' => [
                                'booking_status' => $booking->booking_status,
                                'payment_status' => $booking->payment_status,
                            ],
                        ]);
                    }
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    if ($i < $attempts - 1) {
                        sleep(1);
                    }
                }
            }

            if ($lastError !== null) {
                Log::warning('Payment retry exhausted attempts', [
                    'booking_id' => $booking->id,
                    'error' => $lastError,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment status could not be synchronized. Please try again later.',
            ], 503);
        } catch (\Exception $e) {
            Log::error('Payment retry failed', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment synchronization failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Confirm a payment ONLY after Midtrans verification.
     * Previously this endpoint marked any booking paid without checking
     * Midtrans, which fakes success. Now it queries the Midtrans Status API
     * across all order_ids for the booking and only marks paid when Midtrans
     * actually reports settlement/capture(accept). Otherwise it syncs the
     * real status (pending/failed/expired) and returns it.
     */
    public function markAsPaid(Request $request, int $bookingId): JsonResponse
    {
        // Verify user is owner of booking
        $booking = Booking::with('payment')->findOrFail($bookingId);

        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $payment = $booking->payment;

        if (!$payment || !$payment->midtrans_order_id) {
            return response()->json([
                'success' => false,
                'message' => 'No payment record found',
            ], 404);
        }

        // Already paid: nothing to do.
        if ($payment->payment_status === 'paid' && $payment->paid_at) {
            return response()->json([
                'success' => true,
                'message' => 'Payment is already confirmed',
                'data' => [
                    'booking_status' => $booking->booking_status,
                    'payment_status' => $booking->payment_status,
                ],
            ]);
        }

        // Verify with Midtrans before touching the database.
        try {
            $transactionData = $this->midtransService->verifyBookingAcrossOrders($booking);
        } catch (\Exception $e) {
            Log::warning('mark-as-paid verification failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment could not be confirmed by Midtrans. Please try again.',
            ], 502);
        }

        if (empty($transactionData['raw'])) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not confirmed by Midtrans',
            ], 502);
        }

        DB::beginTransaction();

        try {
            $result = $this->midtransService->syncBookingPaymentStatus(
                $booking,
                $transactionData['raw']
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to sync payment on confirm', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment confirmation failed. Please try again.',
            ], 500);
        }

        if (($result['payment_status'] ?? null) !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Payment not confirmed by Midtrans. Current status: ' . ($result['payment_status'] ?? 'unknown'),
                'data' => [
                    'booking_status' => $result['booking_status'],
                    'payment_status' => $result['payment_status'],
                ],
            ], 409);
        }

        Log::info('Payment confirmed via Midtrans verification', [
            'booking_id' => $booking->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment confirmed by Midtrans',
            'data' => [
                'booking_status' => $result['booking_status'],
                'payment_status' => $result['payment_status'],
            ],
        ]);
    }
}
