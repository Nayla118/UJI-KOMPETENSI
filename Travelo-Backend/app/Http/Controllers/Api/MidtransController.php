<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransController extends Controller
{
    public function __construct(
        protected MidtransService $midtransService
    ) {}

    /**
     * Verify webhook signature from Midtrans
     */
    private function verifySignature(Request $request, string $signature): bool
    {
        $serverKey = config('services.midtrans.server_key');
        $orderId = $request->order_id;
        $statusCode = $request->status_code;
        $grossAmount = $request->gross_amount;

        $data = $orderId . $statusCode . $grossAmount . $serverKey;
        $hash = hash('sha512', $data);

        return $hash === $signature;
    }

    /**
     * Handle Midtrans webhook callback
     * This is called by Midtrans when payment status changes
     */
    public function callback(Request $request): JsonResponse
    {
        Log::info('=== MIDTRANS WEBHOOK RECEIVED ===');
        Log::info('Order ID: ' . ($request->order_id ?? 'missing'));
        Log::info('Transaction Status: ' . ($request->transaction_status ?? 'missing'));
        Log::info('Status Code: ' . ($request->status_code ?? 'missing'));
        Log::info('Full payload: ' . json_encode($request->all()));

        // Verify signature for security
        if ($request->has('signature_key')) {
            if (!$this->verifySignature($request, $request->signature_key)) {
                Log::error('Invalid webhook signature');
                // Still return 200 to avoid Midtrans retrying
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid signature',
                ], 200);
            }
            Log::info('✓ Webhook signature verified successfully');
        } else {
            Log::warning('No signature provided in webhook');
        }

        // Validate required fields
        if (!$request->has('order_id') || !$request->has('transaction_status')) {
            Log::error('Missing required webhook fields', [
                'has_order_id' => $request->has('order_id'),
                'has_transaction_status' => $request->has('transaction_status'),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Missing required fields',
            ], 200);
        }

        $orderId = $request->order_id;

        // Extract booking ID from order_id
        $bookingId = $this->midtransService->extractBookingIdFromOrderId($orderId);
        Log::info('Extracted Booking ID: ' . ($bookingId ?? 'null'));

        if (!$bookingId) {
            Log::error('Could not extract booking ID from order_id', ['order_id' => $orderId]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid order ID format',
            ], 200);
        }

        $booking = Booking::find($bookingId);

        if (!$booking) {
            Log::error('Booking not found', ['booking_id' => $bookingId, 'order_id' => $orderId]);
            // Return 200 to avoid retries
            return response()->json([
                'success' => false,
                'message' => 'Booking not found',
            ], 200);
        }

        try {
            // Use transaction to ensure consistency
            DB::beginTransaction();

            try {
                $syncResult = $this->midtransService->syncBookingPaymentStatus($booking, $request->all());
                DB::commit();
            } catch (\Exception $syncEx) {
                DB::rollBack();
                throw $syncEx;
            }

            Log::info('Webhook processed successfully', [
                'booking_id' => $booking->id,
                'old_status' => $booking->getOriginal('payment_status'),
                'new_status' => $syncResult['payment_status'],
                'booking_status' => $syncResult['booking_status'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error processing webhook', [
                'booking_id' => $booking->id,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 anyway - webhook shouldn't fail due to our errors
            return response()->json([
                'success' => false,
                'message' => 'Error processing: ' . $e->getMessage(),
            ], 200);
        }
    }

    /**
     * Handle notification from Midtrans
     */
    public function notification(Request $request): JsonResponse
    {
        return $this->callback($request);
    }

    /**
     * Sync booking status directly from Midtrans API.
     * Useful when webhook delivery is delayed or missed.
     */
    public function syncBookingStatus(Request $request, int $bookingId): JsonResponse
    {
        $booking = $request->user()
            ->bookings()
            ->with('payment')
            ->findOrFail($bookingId);

        // Get the actual order ID from the payment record if it exists
        $payment = $booking->payment;
        if ($payment && $payment->midtrans_order_id) {
            $orderId = $payment->midtrans_order_id;
        } else {
            // Fallback: try to find the most recent order ID from Midtrans
            $orderId = 'BOOKING-' . $booking->id;
        }

        try {
            $midtransStatus = $this->midtransService->verifyPayment($orderId);
            $syncResult = $this->midtransService->syncBookingPaymentStatus($booking, $midtransStatus['raw'] ?? $midtransStatus);

            Log::info('Manual sync completed', [
                'booking_id' => $booking->id,
                'order_id' => $orderId,
                'new_status' => $syncResult['payment_status'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Booking payment status synchronized successfully',
                'data' => $syncResult['booking'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync booking payment status', [
                'booking_id' => $booking->id,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to synchronize payment status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current payment status for a booking
     * Direct endpoint for clients to check payment status
     */
    public function getPaymentStatus(Request $request, int $bookingId): JsonResponse
    {
        $booking = $request->user()
            ->bookings()
            ->with('payment')
            ->findOrFail($bookingId);

        $payment = $booking->payment;

        return response()->json([
            'success' => true,
            'data' => [
                'booking_id' => $booking->id,
                'booking_status' => $booking->booking_status,
                'payment_status' => $booking->payment_status,
                'payment' => [ 
                    'id' => $payment->id ?? null,
                    'status' => $payment->payment_status ?? 'pending',
                    'method' => $payment->payment_method ?? null,
                    'amount' => $payment->amount ?? null,
                    'transaction_id' => $payment->midtrans_transaction_id ?? null,
                    'updated_at' => $payment->updated_at ?? null,
                ] ?? null,
            ],
        ]);
    }
}
