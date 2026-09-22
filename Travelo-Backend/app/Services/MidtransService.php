<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Midtrans\Snap;

class MidtransService
{
    protected Snap $snap;

    public function generateOrderId(Booking $booking): string
    {
        return 'BOOKING-' . $booking->id . '-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
    }

    public function extractBookingIdFromOrderId(string $orderId): ?int
    {
        if (preg_match('/^BOOKING-(\d+)(?:-.+)?$/', $orderId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    public function __construct()
    {
        \Midtrans\Config::$serverKey = config('services.midtrans.server_key');
        \Midtrans\Config::$isProduction = config('services.midtrans.is_production', false);
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        $this->snap = new Snap();
    }

    /**
     * Create Snap token for booking payment
     */
    public function createSnapToken(Booking $booking, User $user): string
    {
        try {
            $orderId = $this->generateOrderId($booking);
            $tourPackage = $booking->tourPackage;

            Log::info('MidtransService: Creating snap token');
            Log::info('Order ID: ' . $orderId);
            Log::info('Server Key configured: ' . (config('services.midtrans.server_key') ? 'Yes' : 'No'));

            $params = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (int) $booking->total_price,
                ],
                'customer_details' => [
                    'first_name' => $user->name ?? 'Customer',
                    'email' => $user->email ?? 'customer@example.com',
                ],
                'item_details' => [
                    [
                        'id' => $tourPackage->id,
                        'name' => $tourPackage->title ?? 'Tour Package',
                        'price' => (int) $tourPackage->price,
                        'quantity' => $booking->people_count,
                    ]
                ],
                'callbacks' => [
                    'finish' => config('app.frontend_url') . '/booking/success?booking_id=' . $booking->id,
                    'error' => config('app.frontend_url') . '/booking/error?booking_id=' . $booking->id,
                    'pending' => config('app.frontend_url') . '/booking/pending?booking_id=' . $booking->id,
                ],
            ];

            Log::info('Snap params: ' . json_encode($params));

            $snapToken = $this->snap->getSnapToken($params);

            Log::info('Snap token received: ' . $snapToken);

            if (empty($snapToken)) {
                Log::error('Snap token is empty!');
                throw new \Exception('Midtrans returned empty snap token');
            }

            // Create or update payment record with order_id (avoid duplicates)
            Payment::updateOrCreate(
                ['booking_id' => $booking->id],
                [
                    'midtrans_order_id' => $orderId,
                    'payment_status' => 'pending',
                    'amount' => $booking->total_price,
                ]
            );

            Log::info('Payment record created/updated');

            return $snapToken;
        } catch (\Exception $e) {
            Log::error('MidtransService Exception: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Verify payment status via Midtrans API with timeout
     */
    public function verifyPayment(string $orderId): array
    {
        $serverKey = config('services.midtrans.server_key');
        $isProduction = config('services.midtrans.is_production', false);
        $baseUrl = $isProduction ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';

        $url = "{$baseUrl}/v2/{$orderId}/status";

        Log::info('Midtrans API request', [
            'url' => $url,
            'server_key_prefix' => substr($serverKey ?? '', 0, 10),
            'is_production' => $isProduction,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($serverKey . ':'),
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        Log::info('Midtrans API response', [
            'http_code' => $httpCode,
            'curl_errno' => $curlErrno,
            'curl_error' => $error,
            'response_body' => $response ? substr($response, 0, 500) : null,
        ]);

        if ($response === false || $httpCode !== 200) {
            Log::warning('Midtrans verify failed', [
                'order_id' => $orderId,
                'http_code' => $httpCode,
                'curl_errno' => $curlErrno,
                'error' => $error,
                'response' => $response,
            ]);
            throw new \Exception('Midtrans API request failed: ' . ($error ?: "HTTP {$httpCode}: {$response}"));
        }

        $transactionArray = json_decode($response, true);

        return [
            'status' => $transactionArray['transaction_status'] ?? null,
            'fraud_status' => $transactionArray['fraud_status'] ?? null,
            'payment_type' => $transactionArray['payment_type'] ?? null,
            'transaction_id' => $transactionArray['transaction_id'] ?? null,
            'gross_amount' => $transactionArray['gross_amount'] ?? null,
            'raw' => $transactionArray,
        ];
    }

    public function mapMidtransStatusToLocalStatus(?string $transactionStatus, ?string $fraudStatus = null): string
    {
        return match ($transactionStatus) {
            'capture' => $fraudStatus === 'challenge' ? 'pending' : 'paid',
            'settlement' => 'paid',
            'authorize' => 'pending',
            'pending' => 'pending',
            'deny', 'cancel' => 'failed',
            'expire' => 'expired',
            default => 'pending',
        };
    }

    public function syncBookingPaymentStatus(Booking $booking, array $midtransData): array
    {
        $transactionStatus = $midtransData['transaction_status']
            ?? $midtransData['status']
            ?? null;
        $fraudStatus = $midtransData['fraud_status'] ?? null;
        $paymentType = $midtransData['payment_type'] ?? null;
        $transactionId = $midtransData['transaction_id'] ?? null;
        $orderId = $midtransData['order_id'] ?? null;
        $grossAmount = $midtransData['gross_amount'] ?? $booking->total_price;

        $paymentStatus = $this->mapMidtransStatusToLocalStatus($transactionStatus, $fraudStatus);

        // Prepare payment update data
        $paymentData = [
            'midtrans_transaction_id' => $transactionId,
            'midtrans_order_id' => $orderId,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentType,
            'amount' => $grossAmount,
        ];

        // Add paid_at timestamp when payment is completed
        if ($paymentStatus === 'paid') {
            $paymentData['paid_at'] = now();
        }

        $payment = Payment::updateOrCreate(
            ['booking_id' => $booking->id],
            $paymentData
        );

        $bookingStatus = match ($paymentStatus) {
            'paid' => 'confirmed',
            'failed', 'expired' => 'cancelled',
            default => 'pending',
        };

        $booking->update([
            'payment_status' => $paymentStatus,
            'booking_status' => $bookingStatus,
        ]);

        Log::info('Midtrans payment synchronized', [
            'booking_id' => $booking->id,
            'order_id' => $midtransData['order_id'] ?? null,
            'transaction_status' => $transactionStatus,
            'fraud_status' => $fraudStatus,
            'local_payment_status' => $paymentStatus,
            'local_booking_status' => $bookingStatus,
            'transaction_id' => $transactionId,
        ]);

        return [
            'booking' => $booking->fresh(['payment']),
            'payment' => $payment,
            'payment_status' => $paymentStatus,
            'booking_status' => $bookingStatus,
        ];
    }
}
