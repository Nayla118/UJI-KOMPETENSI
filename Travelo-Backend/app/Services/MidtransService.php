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
            // NOTE (2026-09-22 fix): Midtrans Snap REJECTS reusing an order_id
            // ("order_id sudah digunakan" / "has already been taken", HTTP 400).
            // So every Pay must use a FRESH order_id. To still catch payments
            // made on an older Snap URL, we keep history in
            // payments.order_history and verify ALL candidates.
            $existingPayment = Payment::where('booking_id', $booking->id)->first();
            if ($existingPayment && $existingPayment->payment_status === 'paid') {
                throw new \Exception('Booking already paid');
            }
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

            // Create or update payment record. Always use the fresh order_id
            // (reuse causes HTTP 400), but push the previous order_id into
            // order_history so verify can check every Snap URL ever issued.
            $payment = Payment::firstOrNew(['booking_id' => $booking->id]);
            if (($payment->exists && $payment->payment_status === 'paid') || ($existingPayment && $existingPayment->payment_status === 'paid')) {
                throw new \Exception('Booking already paid');
            }
            $history = $existingPayment?->order_history ?? $payment->order_history ?? [];
            if (!is_array($history)) {
                $history = [];
            }
            $previousOrderId = $existingPayment?->midtrans_order_id ?? ($payment->exists ? $payment->midtrans_order_id : null);
            if (!empty($previousOrderId) && $previousOrderId !== $orderId && !in_array($previousOrderId, $history, true)) {
                $history[] = $previousOrderId;
                // Keep history bounded (last 10).
                $history = array_values(array_slice($history, -10));
            }
            $payment->midtrans_order_id = $orderId;
            $payment->order_history = $history;
            if ($payment->payment_status !== 'paid') {
                if (!$payment->exists || empty($payment->payment_status)) {
                    $payment->payment_status = 'pending';
                }
                $payment->amount = $booking->total_price;
                $payment->save();
            }

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

        // 404 "Transaction doesn't exist" is NORMAL right after Snap token
        // creation (user hasn't picked a payment method yet). Treat as pending
        // instead of throwing, so the app doesn't show a scary error.
        if ($httpCode === 200 && $response !== false) {
            $transactionArray = json_decode($response, true);
            if (($transactionArray['status_code'] ?? null) === '404'
                || str_contains(strtolower($transactionArray['status_message'] ?? ''), "doesn't exist")) {
                return [
                    'status' => 'pending',
                    'fraud_status' => null,
                    'payment_type' => null,
                    'transaction_id' => null,
                    'gross_amount' => null,
                    'not_found' => true,
                    'raw' => array_merge($transactionArray, [
                        'transaction_status' => 'pending',
                        'order_id' => $orderId,
                    ]),
                ];
            }
        }

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

    /**
     * All order_ids ever issued for this booking (current + history).
     */
    public function getOrderCandidates(Booking $booking): array
    {
        $payment = $booking->payment ?? Payment::where('booking_id', $booking->id)->first();
        if (!$payment) {
            return [];
        }
        $candidates = [];
        if (!empty($payment->midtrans_order_id)) {
            $candidates[] = $payment->midtrans_order_id;
        }
        $history = $payment->order_history ?? [];
        if (!is_array($history)) {
            $history = [];
        }
        foreach (array_reverse($history) as $old) {
            if (!empty($old) && !in_array($old, $candidates, true)) {
                $candidates[] = $old;
            }
        }
        return $candidates;
    }

    /**
     * Verify EVERY order_id for this booking against Midtrans.
     * Returns the best result: paid wins over pending; pending wins over
     * not_found/error. Fixes "paid token A but verify only checks token B".
     */
    public function verifyBookingAcrossOrders(Booking $booking): array
    {
        $candidates = $this->getOrderCandidates($booking);
        if (empty($candidates)) {
            throw new \Exception('No payment record found. Please create payment first.');
        }

        $lastPending = null;
        $errors = [];
        foreach ($candidates as $orderId) {
            try {
                $data = $this->verifyPayment($orderId);
            } catch (\Exception $e) {
                $errors[] = $orderId . ': ' . $e->getMessage();
                continue;
            }
            $local = $this->mapMidtransStatusToLocalStatus($data['status'] ?? null, $data['fraud_status'] ?? null);
            Log::info('Midtrans history check', [
                'booking_id' => $booking->id,
                'order_id' => $orderId,
                'transaction_status' => $data['status'] ?? null,
                'local' => $local,
            ]);
            if ($local === 'paid') {
                return $data;
            }
            // Prefer real terminal states over not_found.
            if (in_array($local, ['failed', 'expired'], true)) {
                return $data;
            }
            if (empty($data['not_found']) && $lastPending === null) {
                $lastPending = $data;
            } elseif ($lastPending === null && $data['not_found'] ?? false) {
                $lastPending = $data;
            }
        }

        if ($lastPending !== null) {
            return $lastPending;
        }

        throw new \Exception('Could not verify any order (' . implode('; ', $errors) . ')');
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

        // Prepare payment update data.
        // FIX: never wipe stored midtrans_order_id / transaction_id with null
        // (verifyPayment 'raw' may lack order_id in some paths).
        $existing = Payment::where('booking_id', $booking->id)->first();
        $paymentData = [
            'midtrans_transaction_id' => $transactionId ?? $existing?->midtrans_transaction_id,
            'midtrans_order_id' => $orderId ?? $existing?->midtrans_order_id,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentType ?? $existing?->payment_method,
            'amount' => $grossAmount,
        ];

        // Add paid_at timestamp when payment is completed (never clear it).
        if ($paymentStatus === 'paid') {
            $paymentData['paid_at'] = $existing?->paid_at ?? now();
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
