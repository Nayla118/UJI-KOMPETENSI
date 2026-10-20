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

    /**
     * Semua payment_type Midtrans yang didukung Snap.
     * Dipakai sebagai `enabled_payments` agar tidak ada e-wallet/kartu
     * yang ke-hidden oleh setting dashboard merchant.
     * Lihat: https://docs.midtrans.com
     */
    public const ENABLED_PAYMENTS = [
        'credit_card',
        'qris',
        'gopay',
        'shopeepay',
        'ovo',
        'dana',
        'linkaja',
        'bank_transfer',
        'echannel',
        'bca_klikpay',
        'bri_epay',
        'cimb_clicks',
        'danamon_online',
        'akulaku',
        'kredivo',
        'uob_ezpay',
        'cstore',
    ];

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
        $serverKey = (string) config('services.midtrans.server_key');
        $isProduction = (bool) config('services.midtrans.is_production', false);

        // Fail fast on misconfiguration: sandbox keys look like
        // "SB-Mid-server-...", production keys like "Mid-server-...".
        // A mismatched pair makes Snap + Status API + webhook signature ALL
        // fail, which surfaces as "stuck pending".
        if ($serverKey === '') {
            Log::critical('MIDTRANS_SERVER_KEY is not configured');
        } elseif (!$isProduction && !str_starts_with($serverKey, 'SB-Mid-server-')) {
            Log::warning('MIDTRANS_SERVER_KEY does not look like a Sandbox key (expected SB-Mid-server-...) while MIDTRANS_IS_PRODUCTION=false. Verify + webhook will fail.');
        } elseif ($isProduction && str_starts_with($serverKey, 'SB-Mid-')) {
            Log::warning('Sandbox MIDTRANS_SERVER_KEY used while MIDTRANS_IS_PRODUCTION=true. Verify + webhook will fail.');
        }

        \Midtrans\Config::$serverKey = $serverKey;
        \Midtrans\Config::$isProduction = $isProduction;
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
                // Tampilkan SEMUA metode: kartu + semua e-wallet + VA + gerai + paylater.
                // Tanpa ini, metode yang tampil tergantung setting dashboard merchant
                // dan bisa ada e-wallet yang ke-hidden sehingga "gabisa paid".
                'enabled_payments' => self::ENABLED_PAYMENTS,
                'credit_card' => [
                    'secure' => true,
                    // Simpan kartu untuk pembayaran berikutnya (opsional, aman).
                    'save_card' => false,
                ],
                // Snap URL kedaluwarsa 24 jam agar user bisa bayar via semua
                // metode lambat (VA, gerai, QRIS) tanpa order_id expired duluan.
                'expiry' => [
                    'start_time' => now()->format('Y-m-d H:i:s O'),
                    'unit' => 'hour',
                    'duration' => 24,
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

            // Never log the full Snap payload: it contains the customer's name,
            // email, item details and price. Log only non-identifying metadata.
            Log::info('MidtransService: Snap payload prepared', [
                'booking_id' => $booking->id,
                'order_id' => $orderId,
                'gross_amount' => (int) $booking->total_price,
            ]);

            $snapToken = $this->snap->getSnapToken($params);

            Log::info('MidtransService: Snap token issued', [
                'booking_id' => $booking->id,
                'order_id' => $orderId,
            ]);

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
                // Persist the fresh token so clients can poll/reuse it.
                $payment->snap_token = $snapToken;
                $payment->expires_at = now()->addHours(24);
                // Reset detail metode saat token baru dibuat (user belum pilih metode).
                if (empty($payment->payment_method)) {
                    $payment->payment_method = null;
                }
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
            'order_id' => $orderId,
            'is_production' => $isProduction,
            'server_key_configured' => !empty($serverKey),
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

        // The response may contain VA numbers, transaction IDs and customer
        // details. Keep it out of the logs; the sanitized status is logged by
        // the callers instead.
        Log::info('Midtrans API response', [
            'order_id' => $orderId,
            'http_code' => $httpCode,
            'curl_errno' => $curlErrno,
            'curl_error' => $error,
        ]);

        // 404 "Transaction doesn't exist" is NORMAL right after Snap token
        // creation (user hasn't picked a payment method yet). Treat as pending
        // instead of throwing, so the app doesn't show a scary error.
        // Midtrans answers either HTTP 200 + body status_code 404 (Snap txns)
        // or a real HTTP 404 — both mean "no payment attempt yet".
        if ($response !== false && ($httpCode === 200 || $httpCode === 404)) {
            $transactionArray = json_decode($response, true);
            if (is_array($transactionArray)
                && (($transactionArray['status_code'] ?? null) === '404'
                    || ($transactionArray['status_code'] ?? null) === 404
                    || str_contains(strtolower($transactionArray['status_message'] ?? ''), "doesn't exist")
                    || str_contains(strtolower($transactionArray['status_message'] ?? ''), 'not found'))
            ) {
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
            // Real HTTP 404 with empty/non-JSON body: also "not paid yet".
            if ($httpCode === 404 && !is_array($transactionArray)) {
                return [
                    'status' => 'pending',
                    'fraud_status' => null,
                    'payment_type' => null,
                    'transaction_id' => null,
                    'gross_amount' => null,
                    'not_found' => true,
                    'raw' => [
                        'transaction_status' => 'pending',
                        'order_id' => $orderId,
                    ],
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
     * Returns the best result with strict priority:
     *  1. paid on ANY order (user may have paid on an older Snap URL)
     *  2. the CURRENT (latest) order's own status — so a fresh pending
     *     payment is never overwritten by an old order's expired/failed
     *     (fixes "Pay Again after expire shows expired again").
     *  3. any other pending result, 4. error if nothing verifiable.
     * Terminal failed/expired of OLD orders are ignored when the
     * current order has its own live status.
     */
    public function verifyBookingAcrossOrders(Booking $booking): array
    {
        $candidates = $this->getOrderCandidates($booking);
        if (empty($candidates)) {
            throw new \Exception('No payment record found. Please create payment first.');
        }

        $currentOrderId = $candidates[0];
        $currentData = null;
        $fallbackPending = null;
        $errors = [];

        foreach ($candidates as $index => $orderId) {
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
                'is_current' => $index === 0,
                'transaction_status' => $data['status'] ?? null,
                'local' => $local,
            ]);
            if ($local === 'paid') {
                return $data;
            }
            if ($index === 0) {
                $currentData = $data;
            } elseif ($fallbackPending === null && empty($data['not_found'])) {
                $fallbackPending = $data;
            } elseif ($fallbackPending === null && ($data['not_found'] ?? false)) {
                $fallbackPending = $data;
            }
        }

        // Current order has a live answer from Midtrans (pending / failed /
        // expired / cancelled): it is the truth for this payment attempt.
        if ($currentData !== null) {
            return $currentData;
        }

        if ($fallbackPending !== null) {
            return $fallbackPending;
        }

        throw new \Exception('Could not verify any order (' . implode('; ', $errors) . ')');
    }

    /**
     * Map status Midtrans -> status lokal. Mapping konsisten:
     *  pending <- pending / authorize / capture+challenge / unknown
     *  paid    <- settlement / capture+accept
     *  failed  <- deny / failure / cancel (dibatalkan user; status mentah
     *             'cancel' tetap disimpan di transaction_status agar UI bisa
     *             menampilkan "Cancelled" secara distinct)
     *  expired <- expire
     *  refund / partial_refund / chargeback: jangan downgrade paid -> tetap paid
     *  (refund administratif, bukan gagal bayar), kecuali belum pernah paid.
     *
     * Aturan per metode (semua bisa paid):
     * - credit_card: capture+accept=paid, capture+challenge=pending
     * - qris/gopay/shopeepay/ovo/dana/linkaja/bank_transfer/echannel/
     *   bca_klikpay/bri_epay/cimb_clicks/danamon_online/akulaku/kredivo/
     *   uob_ezpay/cstore: settlement=paid, pending=pending
     */
    public function mapMidtransStatusToLocalStatus(?string $transactionStatus, ?string $fraudStatus = null): string
    {
        $status = strtolower((string) ($transactionStatus ?? ''));
        $fraud = strtolower((string) ($fraudStatus ?? ''));

        return match ($status) {
            'capture' => $fraud === 'challenge' ? 'pending' : 'paid',
            'settlement' => 'paid',
            'authorize' => 'pending',
            'pending' => 'pending',
            'deny', 'failure', 'cancel' => 'failed',
            'expire' => 'expired',
            // Jangan ubah paid jadi failed hanya karena refund admin.
            'refund', 'partial_refund', 'chargeback' => 'paid',
            default => 'pending',
        };
    }

    /**
     * Normalisasi payload Midtrans yang beda-beda per metode
     * menjadi [payment_method, payment_channel, metadata].
     * payment_method = payment_type resmi (gopay, qris, credit_card, dst).
     * payment_channel = detail: bank (bca/bri/bni/...), store (indomaret/alfamart), dsb.
     */
    public function normalizeMidtransPayment(array $midtransData): array
    {
        $paymentType = strtolower((string) ($midtransData['payment_type'] ?? ''));
        $channel = null;
        $metadata = [];

        switch ($paymentType) {
            case 'credit_card':
                $channel = strtolower((string) ($midtransData['bank'] ?? ''));
                if (!empty($midtransData['masked_card'])) {
                    $metadata['masked_card'] = $midtransData['masked_card'];
                }
                if (!empty($midtransData['bank'])) {
                    $metadata['bank'] = $midtransData['bank'];
                }
                break;

            case 'bank_transfer':
                // BCA/BRI/BNI: va_numbers[0]{bank, va_number}
                // Permata: permata_va_number | Mandiri eChannel: bill_key/biller_code
                $vaNumbers = $midtransData['va_numbers'] ?? [];
                if (is_array($vaNumbers) && !empty($vaNumbers[0]['bank'])) {
                    $channel = strtolower((string) $vaNumbers[0]['bank']);
                    $metadata['va_numbers'] = $vaNumbers;
                } elseif (!empty($midtransData['permata_va_number'])) {
                    $channel = 'permata';
                    $metadata['permata_va_number'] = $midtransData['permata_va_number'];
                } elseif (!empty($midtransData['bill_key'])) {
                    $channel = 'mandiri';
                    $metadata['bill_key'] = $midtransData['bill_key'];
                    $metadata['biller_code'] = $midtransData['biller_code'] ?? null;
                }
                break;

            case 'echannel':
                $channel = 'mandiri';
                $metadata['bill_key'] = $midtransData['bill_key'] ?? null;
                $metadata['biller_code'] = $midtransData['biller_code'] ?? null;
                break;

            case 'qris':
            case 'gopay':
            case 'shopeepay':
            case 'ovo':
            case 'dana':
            case 'linkaja':
                // E-wallet: channel = payment_type itu sendiri agar label bagus.
                $channel = $paymentType !== '' ? $paymentType : null;
                foreach (['qr_string', 'deeplink', 'actions'] as $k) {
                    if (!empty($midtransData[$k])) {
                        $metadata[$k] = $midtransData[$k];
                    }
                }
                break;

            case 'cstore':
                // store: indomaret / alfamart, payment_code untuk bayar di kasir.
                $channel = strtolower((string) ($midtransData['store'] ?? ''));
                if (!empty($midtransData['payment_code'])) {
                    $metadata['payment_code'] = $midtransData['payment_code'];
                }
                if (!empty($midtransData['store'])) {
                    $metadata['store'] = $midtransData['store'];
                }
                break;

            default:
                // bca_klikpay, bri_epay, cimb_clicks, danamon_online,
                // akulaku, kredivo, uob_ezpay, dsb: simpan apa adanya.
                $channel = $paymentType !== '' ? $paymentType : null;
                break;
        }

        // Simpan actions (QR/deeplink/VA) bila ada agar bisa ditampilkan ulang.
        if (!empty($midtransData['actions']) && empty($metadata['actions'])) {
            $metadata['actions'] = $midtransData['actions'];
        }

        return [$paymentType !== '' ? $paymentType : null, $channel, $metadata];
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

        $existing = Payment::where('booking_id', $booking->id)->first();

        // Idempotency: same order + same raw transaction_status already
        // applied (duplicate webhook delivery) -> skip writes entirely.
        if (
            $existing
            && $orderId !== null && $existing->midtrans_order_id === $orderId
            && $existing->transaction_status !== null && $existing->transaction_status === $transactionStatus
            && $existing->payment_status === $paymentStatus
        ) {
            Log::info('Midtrans sync: duplicate notification ignored', [
                'booking_id' => $booking->id,
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
            ]);

            return [
                'booking' => $booking->fresh(['payment']),
                'payment' => $existing,
                'payment_status' => $existing->payment_status,
                'booking_status' => $booking->booking_status,
            ];
        }

        // Idempotency + no-downgrade: once paid, never regress to pending /
        // failed / expired / cancelled on out-of-order or stale notifications
        // (e.g. pending for a newer order_id arriving after settlement of an
        // older one). Paid is forever.
        if ($existing && $existing->payment_status === 'paid' && $paymentStatus !== 'paid') {
            Log::info('Midtrans sync: keeping paid state, ignoring stale notification', [
                'booking_id' => $booking->id,
                'incoming_status' => $paymentStatus,
                'incoming_transaction_status' => $transactionStatus,
                'order_id' => $orderId,
            ]);

            return [
                'booking' => $booking->fresh(['payment']),
                'payment' => $existing,
                'payment_status' => 'paid',
                'booking_status' => $booking->booking_status,
            ];
        }

        // Gross amount validation: the notification must match the booking
        // total. A mismatch means tampered/forged or wrong-order data, so a
        // "paid" claim MUST NOT confirm the booking. Keep local state and log.
        if ((int) $grossAmount !== (int) $booking->total_price) {
            Log::warning('Midtrans gross_amount mismatch', [
                'booking_id' => $booking->id,
                'booking_total' => $booking->total_price,
                'midtrans_gross' => $grossAmount,
                'order_id' => $orderId,
            ]);

            if ($paymentStatus === 'paid') {
                Log::error('Midtrans sync: refusing to mark paid on amount mismatch', [
                    'booking_id' => $booking->id,
                    'order_id' => $orderId,
                ]);

                return [
                    'booking' => $booking->fresh(['payment']),
                    'payment' => $existing,
                    'payment_status' => $existing?->payment_status ?? 'pending',
                    'booking_status' => $booking->booking_status,
                ];
            }
        }

        // Normalize per-method details (bank VA, store code, e-wallet actions...).
        [$normalizedMethod, $channel, $metadata] = $this->normalizeMidtransPayment($midtransData);

        // Prepare payment update data.
        // FIX: never wipe stored midtrans_order_id / transaction_id with null
        // (verifyPayment 'raw' may lack order_id in some paths).
        $paymentData = [
            'midtrans_transaction_id' => $transactionId ?? $existing?->midtrans_transaction_id,
            'midtrans_order_id' => $orderId ?? $existing?->midtrans_order_id,
            'payment_status' => $paymentStatus,
            'payment_method' => $normalizedMethod ?? $paymentType ?? $existing?->payment_method,
            'amount' => $booking->total_price,
            'payment_channel' => $channel ?? $existing?->payment_channel,
            'transaction_status' => $transactionStatus ?? $existing?->transaction_status,
            'fraud_status' => $fraudStatus ?? $existing?->fraud_status,
        ];

        if (!empty($metadata)) {
            $paymentData['metadata'] = array_merge($existing?->metadata ?? [], $metadata);
        }

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
