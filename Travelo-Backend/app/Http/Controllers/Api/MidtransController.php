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
     * Verify webhook signature from Midtrans:
     * SHA512(order_id + status_code + gross_amount + server_key).
     */
    private function verifySignature(Request $request, string $signature): bool
    {
        $serverKey = (string) config('services.midtrans.server_key');
        $orderId = (string) $request->input('order_id');
        $statusCode = (string) $request->input('status_code');
        $grossAmount = (string) $request->input('gross_amount');

        if ($serverKey === '' || $orderId === '' || $statusCode === '' || $grossAmount === '') {
            return false;
        }

        $hash = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        return hash_equals($hash, strtolower($signature));
    }

    /**
     * Handle Midtrans webhook callback
     * This is called by Midtrans when payment status changes
     */
    public function callback(Request $request): JsonResponse
    {
        Log::info('Midtrans webhook received', [
            'order_id' => $request->input('order_id'),
            'transaction_status' => $request->input('transaction_status'),
        ]);

        // A webhook without a valid signature is an unauthenticated payment
        // claim. Never process it: previously a missing signature was only
        // logged as a warning and the payload was still trusted.
        $signature = $request->input('signature_key');

        if (!is_string($signature) || !$this->verifySignature($request, $signature)) {
            Log::warning('Rejected Midtrans webhook with invalid or missing signature', [
                'order_id' => $request->input('order_id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid signature',
            ], 401);
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
                'message' => 'Webhook processing failed',
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
     * Public health check for webhook wiring (no secrets leaked).
     * Use to verify ngrok/base URL reaches Laravel and config is sane.
     */
    public function health(): JsonResponse
    {
        $serverKey = (string) config('services.midtrans.server_key');
        $isProduction = (bool) config('services.midtrans.is_production', false);

        return response()->json([
            'success' => true,
            'data' => [
                'server_key_configured' => $serverKey !== '',
                'server_key_format' => $serverKey === ''
                    ? 'missing'
                    : (str_starts_with($serverKey, 'SB-Mid-server-') ? 'sandbox' : (str_starts_with($serverKey, 'Mid-server-') ? 'production' : 'unknown')),
                'is_production' => $isProduction,
                'config_consistent' => $serverKey === ''
                    ? false
                    : ($isProduction ? str_starts_with($serverKey, 'Mid-server-') : str_starts_with($serverKey, 'SB-Mid-server-')),
                'app_url' => config('app.url'),
                'webhook_callback' => rtrim((string) config('app.url'), '/') . '/api/midtrans/callback',
                'webhook_notification' => rtrim((string) config('app.url'), '/') . '/api/midtrans/notification',
            ],
        ]);
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

        try {
            $midtransStatus = $this->midtransService->verifyBookingAcrossOrders($booking);
            $syncResult = $this->midtransService->syncBookingPaymentStatus($booking, $midtransStatus['raw'] ?? $midtransStatus);

            Log::info('Manual sync completed', [
                'booking_id' => $booking->id,
                'order_id' => $midtransStatus['raw']['order_id'] ?? null,
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
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to synchronize payment status. Please try again.',
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
                    'channel' => $payment->payment_channel ?? null,
                    'transaction_status' => $payment->transaction_status ?? null,
                    'amount' => $payment->amount ?? null,
                    'transaction_id' => $payment->midtrans_transaction_id ?? null,
                    'updated_at' => $payment->updated_at ?? null,
                ] ?? null,
            ],
        ]);
    }
}
