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
        try {
            $booking = $request->user()
                ->bookings()
                ->with('payment')
                ->findOrFail($bookingId);

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
        try {
            $booking = $request->user()
                ->bookings()
                ->with('payment')
                ->findOrFail($bookingId);

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

            $orderId = $payment->midtrans_order_id;

            // Try to verify with Midtrans with timeout
            try {
                Log::info('Checking Midtrans for order', ['order_id' => $orderId]);
                
                $transactionData = $this->midtransService->verifyPayment($orderId);

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
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Could not verify payment with Midtrans: ' . $e->getMessage(),
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
                'message' => 'Verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry payment sync for a specific booking
     * Use if payment was successful but status didn't update
     */
    public function retrySync(Request $request, int $bookingId): JsonResponse
    {
        try {
            $booking = Booking::findOrFail($bookingId);
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
                    $orderId = $payment->midtrans_order_id ?? 'BOOKING-' . $booking->id;
                    
                    $transactionData = $this->midtransService->verifyPayment($orderId);
                    
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

            return response()->json([
                'success' => false,
                'message' => 'Could not sync payment after multiple attempts: ' . $lastError,
            ], 500);
        } catch (\Exception $e) {
            Log::error('Payment retry failed', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Retry failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manually mark a payment as paid (ADMIN ONLY - for emergency use)
     * Should only be used if payment was confirmed outside normal flow
     */
    public function markAsPaid(Request $request, int $bookingId): JsonResponse
    {
        // Verify user is owner of booking
        $booking = Booking::findOrFail($bookingId);
        
        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $payment = $booking->payment;

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment record found',
                ], 404);
            }

            // Update payment
            $payment->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $payment->payment_method ?? 'manual_confirmation',
            ]);

            // Update booking
            $booking->update([
                'payment_status' => 'paid',
                'booking_status' => 'confirmed',
            ]);

            DB::commit();

            Log::warning('Payment manually marked as paid', [
                'booking_id' => $booking->id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment marked as paid successfully',
                'data' => [
                    'booking_status' => $booking->booking_status,
                    'payment_status' => $booking->payment_status,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to mark payment as paid', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark payment: ' . $e->getMessage(),
            ], 500);
        }
    }
}
