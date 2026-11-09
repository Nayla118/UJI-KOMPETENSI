<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\DeviceToken;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Throwable;

/**
 * Firebase Cloud Messaging delivery.
 *
 * Push is deliberately best-effort: every failure is caught and logged so a
 * missing/placeholder service account can never break booking, payment,
 * scanning, moderation, or change-request flows. The database is always the
 * source of truth; the app can still poll/refresh.
 *
 * Messages are data-only. The Android client owns notification presentation so
 * every notification carries the same deep-link extras (`type`, `screen`, and
 * the relevant id) through one PendingIntent path, whether the app is in the
 * foreground, background, or not running.
 */
class PushNotificationService
{
    public const CHANNEL_ID = 'travelo_updates';

    public const TYPE_PAYMENT_PAID = 'payment_paid';

    public const TYPE_PAYMENT_FAILED = 'payment_failed';

    public const TYPE_PAYMENT_EXPIRED = 'payment_expired';

    public const TYPE_PAYMENT_AFTER_EXPIRY = 'payment_after_expiry';

    public const TYPE_PAYMENT_UPDATE = 'payment_update';

    public const TYPE_TICKET_USED = 'ticket_used';

    public const TYPE_CHANGE_REQUEST_APPROVED = 'change_request_approved';

    public const TYPE_CHANGE_REQUEST_REJECTED = 'change_request_rejected';

    public const TYPE_CHANGE_REQUEST_PENDING = 'change_request_pending';

    public const TYPE_CHANGE_REQUEST_UPDATED = 'change_request_updated';

    public const TYPE_REVIEW_MODERATED = 'review_moderated';

    public const TYPE_TRIP_REMINDER = 'trip_reminder';

    public const SCREEN_BOOKING_DETAIL = 'booking_detail';

    public const SCREEN_E_TICKET = 'e_ticket';

    public const SCREEN_REVIEW = 'review';

    public const SCREEN_DESTINATION = 'destination';

    public function isConfigured(): bool
    {
        $path = (string) config('firebase.projects.app.credentials', '');

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return false;
        }

        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json)
            && ! empty($json['project_id'])
            && is_string($json['private_key'] ?? null)
            && str_contains($json['private_key'], 'BEGIN PRIVATE KEY');
    }

    /**
     * @param  array<string, scalar|null>  $data
     * @return array{configured: bool, sent: int, failed: int}
     */
    public function sendToUser(User|int|null $user, string $title, string $body, array $data = []): array
    {
        $userId = $user instanceof User ? $user->id : $user;

        if ($userId === null) {
            return ['configured' => $this->isConfigured(), 'sent' => 0, 'failed' => 0];
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $userId)
            ->get();

        if ($tokens->isEmpty()) {
            return ['configured' => $this->isConfigured(), 'sent' => 0, 'failed' => 0];
        }

        if (! $this->isConfigured()) {
            Log::debug('Push notification skipped: Firebase service account is not configured.', [
                'user_id' => $userId,
                'tokens' => $tokens->count(),
            ]);

            return ['configured' => false, 'sent' => 0, 'failed' => 0];
        }

        $sent = 0;
        $failed = 0;

        foreach ($tokens as $deviceToken) {
            if ($this->sendToToken($deviceToken, $title, $body, $data)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['configured' => true, 'sent' => $sent, 'failed' => $failed];
    }

    /**
     * Data-only payload: the Android service builds the notification and its
     * PendingIntent, so cold-start and warm-start taps both receive the extras.
     *
     * @param  array<string, scalar|null>  $data
     */
    private function sendToToken(DeviceToken $deviceToken, string $title, string $body, array $data): bool
    {
        try {
            /** @var Messaging $messaging */
            $messaging = app(Messaging::class);

            $payload = array_map(
                fn ($value) => $value === null ? '' : (string) $value,
                $data
            );
            $payload['title'] = $title;
            $payload['body'] = $body;

            $messaging->send([
                'token' => $deviceToken->token,
                'data' => $payload,
                'android' => [
                    'priority' => 'high',
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '5',
                        'apns-push-type' => 'background',
                    ],
                    'payload' => [
                        'aps' => [
                            'content-available' => 1,
                        ],
                    ],
                ],
            ]);

            $deviceToken->forceFill(['last_used_at' => now()])->save();

            return true;
        } catch (Throwable $e) {
            $message = $e->getMessage();

            // Firebase rejects tokens for uninstalled apps with these codes.
            if (
                stripos($message, 'not-registered') !== false
                || stripos($message, 'invalid-registration-token') !== false
                || stripos($message, 'invalid-argument') !== false
            ) {
                $deviceToken->delete();
            }

            Log::warning('Push notification failed', [
                'device_token_id' => $deviceToken->id,
                'user_id' => $deviceToken->user_id,
                'error' => $message,
            ]);

            return false;
        }
    }

    /**
     * Every payload is deliberately small and contains identifiers only. Ticket
     * tokens, identity documents, prices, names, and contact details stay in
     * the database behind the authenticated API.
     *
     * @param  array<string, scalar|null>  $ids
     * @return array<string, scalar>
     */
    private function payload(string $type, string $screen, array $ids = []): array
    {
        $data = [
            'type' => $type,
            'screen' => $screen,
        ];

        foreach ($ids as $key => $value) {
            if ($value !== null && $value !== '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    public function notifyPayment(Booking $booking, string $paymentStatus): void
    {
        $booking->loadMissing('tourPackage.destination');

        [$title, $body] = match ($paymentStatus) {
            'paid' => [
                'Payment confirmed',
                'Your payment for '.$this->tripName($booking).' is confirmed. Your e-ticket is ready.',
            ],
            'failed' => [
                'Payment failed',
                'We could not confirm your payment for '.$this->tripName($booking).'. Open the app to try again.',
            ],
            'expired' => [
                'Payment expired',
                'The payment window for '.$this->tripName($booking).' has expired. You can start a new payment.',
            ],
            default => [
                'Payment update',
                'Your payment status for '.$this->tripName($booking).' changed to '.$paymentStatus.'.',
            ],
        };

        [$type, $screen] = match ($paymentStatus) {
            'paid' => [self::TYPE_PAYMENT_PAID, self::SCREEN_E_TICKET],
            'failed' => [self::TYPE_PAYMENT_FAILED, self::SCREEN_BOOKING_DETAIL],
            'expired' => [self::TYPE_PAYMENT_EXPIRED, self::SCREEN_BOOKING_DETAIL],
            default => [self::TYPE_PAYMENT_UPDATE, self::SCREEN_BOOKING_DETAIL],
        };

        $this->sendToUser(
            $booking->user_id,
            $title,
            $body,
            $this->payload($type, $screen, ['booking_id' => $booking->id])
        );
    }

    public function notifyBookingExpired(Booking $booking): void
    {
        $booking->loadMissing('tourPackage.destination');

        $this->sendToUser(
            $booking->user_id,
            'Booking expired',
            'The payment window for '.$this->tripName($booking).' ran out, so the booking was released. Book again to reserve your seats.',
            $this->payload(
                self::TYPE_PAYMENT_EXPIRED,
                self::SCREEN_BOOKING_DETAIL,
                ['booking_id' => $booking->id]
            )
        );
    }

    public function notifyLatePaymentRequiresRefund(Booking $booking): void
    {
        $booking->loadMissing('tourPackage.destination');

        $this->sendToUser(
            $booking->user_id,
            'Payment received after expiry',
            'We received your payment for '.$this->tripName($booking).', but the seats had already been released. Our team will contact you about a refund.',
            $this->payload(
                self::TYPE_PAYMENT_AFTER_EXPIRY,
                self::SCREEN_BOOKING_DETAIL,
                ['booking_id' => $booking->id]
            )
        );
    }

    public function notifyTicketScanned(Booking $booking): void
    {
        $booking->loadMissing('tourPackage.destination');

        $this->sendToUser(
            $booking->user_id,
            'Ticket scanned',
            'Your ticket for '.$this->tripName($booking).' was scanned at the gate.',
            $this->payload(
                self::TYPE_TICKET_USED,
                self::SCREEN_E_TICKET,
                ['booking_id' => $booking->id]
            )
        );
    }

    public function notifyChangeRequest(BookingChangeRequest $request, string $outcome): void
    {
        $request->loadMissing(['booking.tourPackage']);
        $booking = $request->booking;
        $trip = $booking ? $this->tripName($booking) : 'your booking';

        [$title, $body] = match ($outcome) {
            BookingChangeRequest::STATUS_APPROVED => [
                'Cancellation approved',
                'Your cancellation for '.$trip.' was approved. Our team will process the refund manually.',
            ],
            BookingChangeRequest::STATUS_REJECTED => [
                'Cancellation request rejected',
                'Your cancellation request for '.$trip.' was rejected. Open the app to read the note.',
            ],
            default => [
                'Cancellation request update',
                'Your cancellation request for '.$trip.' is now '.$request->status.'.',
            ],
        };

        $type = match ($outcome) {
            BookingChangeRequest::STATUS_APPROVED => self::TYPE_CHANGE_REQUEST_APPROVED,
            BookingChangeRequest::STATUS_REJECTED => self::TYPE_CHANGE_REQUEST_REJECTED,
            BookingChangeRequest::STATUS_PENDING => self::TYPE_CHANGE_REQUEST_PENDING,
            default => self::TYPE_CHANGE_REQUEST_UPDATED,
        };

        $this->sendToUser(
            $request->user_id,
            $title,
            $body,
            $this->payload($type, self::SCREEN_BOOKING_DETAIL, [
                'booking_id' => $booking?->id,
                'change_request_id' => $request->id,
            ])
        );
    }

    public function notifyReviewModerated(Review $review, string $action): void
    {
        $review->loadMissing(['destination', 'user']);

        $destination = $review->destination?->name ?? 'your destination';
        [$title, $body] = $action === 'hide'
            ? [
                'Review hidden',
                'Your review for '.$destination.' was hidden after moderation. Open the app to read the note.',
            ]
            : [
                'Review published',
                'Your review for '.$destination.' is visible again.',
            ];

        $this->sendToUser(
            $review->user_id,
            $title,
            $body,
            $this->payload(self::TYPE_REVIEW_MODERATED, self::SCREEN_REVIEW, [
                'review_id' => $review->id,
                'destination_id' => $review->destination_id,
            ])
        );
    }

    public function notifyTripReminder(Booking $booking): void
    {
        $booking->loadMissing('tourPackage.destination');

        $date = $booking->booking_date?->format('d M Y') ?? 'tomorrow';

        $this->sendToUser(
            $booking->user_id,
            'Trip reminder',
            'Your trip '.$this->tripName($booking).' is on '.$date.'. Have your e-ticket ready.',
            $this->payload(
                self::TYPE_TRIP_REMINDER,
                self::SCREEN_E_TICKET,
                ['booking_id' => $booking->id]
            )
        );
    }

    public function notifyRefundResult(
        Booking $booking,
        float $amount,
        bool $success,
        ?string $failureReason = null
    ): void {
        $booking->loadMissing('tourPackage.destination');

        $title = $success
            ? 'Refund processed'
            : 'Refund failed';

        $body = $success
            ? 'Your refund of IDR '.number_format((int) $amount, 0, ',', '.').' for '.$this->tripName($booking).' has been processed.'
            : 'Refund for '.$this->tripName($booking).' could not be processed automatically. '.($failureReason ?? 'Please contact support.');

        $this->sendToUser(
            $booking->user_id,
            $title,
            $body,
            $this->payload(
                self::TYPE_TRIP_REMINDER,
                self::SCREEN_BOOKING_DETAIL,
                ['booking_id' => $booking->id]
            )
        );
    }

    private function tripName(Booking $booking): string
    {
        return $booking->tourPackage?->title ?? 'Booking #'.$booking->id;
    }
}
