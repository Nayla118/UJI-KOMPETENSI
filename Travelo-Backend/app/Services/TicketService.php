<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for signed e-tickets.
 *
 * The token format is unchanged for existing tickets: base64url(payload)
 * "." HMAC-SHA256(payload, APP_KEY). New tokens only add a `version` field
 * when a ticket has been reissued (version > 1), so previously issued
 * tokens remain byte-for-byte valid.
 *
 * The token never contains identity documents, identity numbers, auth
 * tokens or any personal data beyond the booking reference.
 */
class TicketService
{
    public const REASON_INVALID_TOKEN = 'invalid_token';
    public const REASON_NOT_PAID = 'not_paid';
    public const REASON_NOT_DUE = 'not_due';
    public const REASON_EXPIRED = 'expired';
    public const REASON_USED = 'used';
    public const REASON_FORBIDDEN = 'forbidden_destination';

    public function reference(Booking $booking): string
    {
        return sprintf('TRV-%06d', $booking->id);
    }

    /**
     * Issue (or re-read) the signed token for a paid booking.
     *
     * @return array{token: string, payload: array<string, mixed>, issued_at: ?string}
     */
    public function issue(Booking $booking): array
    {
        $booking->loadMissing('payment');

        $version = max(1, (int) ($booking->ticket_version ?: 1));
        $issuedAt = $booking->payment?->paid_at?->toIso8601String();

        $payload = [
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'reference' => $this->reference($booking),
            'issued_at' => $issuedAt,
        ];

        // Keep version 1 tokens identical to the legacy format.
        if ($version > 1) {
            $payload['version'] = $version;
        }

        $encoded = $this->encodePayload($payload);

        return [
            'token' => $encoded.'.'.$this->sign($encoded),
            'payload' => $payload,
            'issued_at' => $issuedAt,
        ];
    }

    /**
     * Verify signature and decode the payload. Returns null for anything
     * that is not a Travelo-signed ticket.
     *
     * @return array<string, mixed>|null
     */
    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;

        if ($encoded === '' || $signature === '') {
            return null;
        }

        $expected = $this->sign($encoded);

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);

        if (!is_array($payload) || !isset($payload['booking_id'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Full status for a decoded ticket. Authenticity (signature) is kept
     * separate from whether the ticket may be used right now.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    public function evaluate(Booking $booking, ?array $payload = null): array
    {
        $booking->loadMissing(['payment', 'tourPackage.destination', 'visitors', 'user']);

        $usage = $booking->ticket_usage_status;
        $paid = $booking->isPaidAndConfirmed();
        $reference = $this->reference($booking);
        $version = (int) ($payload['version'] ?? 1);
        $bookingVersion = max(1, (int) ($booking->ticket_version ?: 1));
        $today = $this->today();
        $visitDate = $booking->booking_date?->toDateString();

        $authentic = $payload !== null
            && ($payload['reference'] ?? null) === $reference
            && (int) ($payload['booking_id'] ?? 0) === (int) $booking->id
            && $version === $bookingVersion;

        $reason = null;

        if (!$authentic) {
            $reason = self::REASON_INVALID_TOKEN;
        } elseif (!$paid) {
            $reason = self::REASON_NOT_PAID;
        } elseif ($usage === Booking::TICKET_USED) {
            $reason = self::REASON_USED;
        } elseif ($usage === Booking::TICKET_EXPIRED) {
            $reason = self::REASON_EXPIRED;
        } elseif ($visitDate !== null && $visitDate > $today) {
            // The gate may only redeem on the visit date itself (Asia/Jakarta).
            $reason = self::REASON_NOT_DUE;
        }

        $destination = $booking->tourPackage?->destination;

        return [
            'authentic' => $authentic,
            'valid' => $authentic && $reason === null,
            'reason' => $reason,
            'usage_status' => $usage,
            'payment_status' => $booking->payment_status,
            'booking_status' => $booking->booking_status,
            'visit_date' => $booking->booking_date?->toDateString(),
            'valid_until' => $this->validUntil($booking),
            'destination' => $destination ? [
                'id' => $destination->id,
                'name' => $destination->name,
                'city' => $destination->city,
            ] : null,
            'visitor_count' => $booking->visitors->count(),
            'holder_name' => $booking->user?->name,
            'reference' => $reference,
            'ticket_used_at' => $booking->ticket_used_at?->toIso8601String(),
        ];
    }

    /**
     * Atomically redeem a ticket for gate staff.
     *
     * The conditional UPDATE is what prevents double entry: even two
     * simultaneous scans can only flip "unused" -> "used" once.
     *
     * @return array{ok: bool, reason: ?string, message: string, data: array<string, mixed>}
     */
    public function redeem(string $token, User $staff): array
    {
        $payload = $this->verifyToken($token);

        if ($payload === null) {
            return $this->redemptionFailure(self::REASON_INVALID_TOKEN, 'This ticket is not valid.');
        }

        $booking = Booking::with(['payment', 'tourPackage.destination', 'visitors', 'user'])
            ->find($payload['booking_id'] ?? 0);

        if (!$booking) {
            return $this->redemptionFailure(self::REASON_INVALID_TOKEN, 'This ticket is not valid.');
        }

        $state = $this->evaluate($booking, $payload);

        if (!$state['authentic']) {
            return $this->redemptionFailure(self::REASON_INVALID_TOKEN, 'This ticket is not valid.');
        }

        $destinationId = $booking->tourPackage?->destination_id;

        if (!$staff->canRedeemForDestination($destinationId)) {
            return $this->redemptionFailure(
                self::REASON_FORBIDDEN,
                'You are not authorized to redeem tickets for this destination.'
            );
        }

        if ($state['reason'] === self::REASON_NOT_PAID) {
            return $this->redemptionFailure(self::REASON_NOT_PAID, 'Payment for this booking is not confirmed.');
        }

        if ($state['reason'] === self::REASON_USED) {
            return $this->redemptionFailure(self::REASON_USED, 'This ticket has already been used.');
        }

        if ($state['reason'] === self::REASON_EXPIRED) {
            return $this->redemptionFailure(
                self::REASON_EXPIRED,
                'This ticket has expired.',
                $this->ticketDateData($booking)
            );
        }

        if ($state['reason'] === self::REASON_NOT_DUE) {
            return $this->redemptionFailure(
                self::REASON_NOT_DUE,
                'This ticket is for '.$this->displayVisitDate($booking).'. It cannot be redeemed before the visit date.',
                $this->ticketDateData($booking)
            );
        }

        $today = $this->today();

        // Atomic claim: only one concurrent request can match ticket_status.
        // The date must be exactly today (Asia/Jakarta), so a future ticket
        // cannot be burned early even if the pre-check is bypassed by a race.
        $affected = DB::table('bookings')
            ->where('id', $booking->id)
            ->where('ticket_status', Booking::TICKET_UNUSED)
            ->where('payment_status', 'paid')
            ->where('booking_status', 'confirmed')
            ->whereDate('booking_date', $today)
            ->update([
                'ticket_status' => Booking::TICKET_USED,
                'ticket_used_at' => now(),
                'ticket_used_by' => $staff->id,
                'updated_at' => now(),
            ]);

        if ($affected !== 1) {
            $booking->refresh();

            if ($booking->ticket_usage_status === Booking::TICKET_USED) {
                return $this->redemptionFailure(
                    self::REASON_USED,
                    'This ticket has already been used.'
                );
            }

            $visitDate = $booking->booking_date?->toDateString();

            if ($visitDate !== null && $visitDate > $today) {
                return $this->redemptionFailure(
                    self::REASON_NOT_DUE,
                    'This ticket is for '.$this->displayVisitDate($booking).'. It cannot be redeemed before the visit date.',
                    $this->ticketDateData($booking)
                );
            }

            if ($booking->ticket_usage_status === Booking::TICKET_EXPIRED) {
                return $this->redemptionFailure(
                    self::REASON_EXPIRED,
                    'This ticket has expired.',
                    $this->ticketDateData($booking)
                );
            }

            return $this->redemptionFailure(
                self::REASON_INVALID_TOKEN,
                'This ticket can no longer be redeemed.'
            );
        }

        $booking->refresh()->load(['payment', 'tourPackage.destination', 'visitors', 'user']);

        // Operational audit trail without any identity data.
        ActivityLog::log(
            'Ticket redeemed',
            Booking::class,
            $booking->id,
            User::class,
            $staff->id,
            [
                'booking_id' => $booking->id,
                'destination_id' => $destinationId,
                'used_at' => $booking->ticket_used_at?->toIso8601String(),
            ]
        );

        return [
            'ok' => true,
            'reason' => null,
            'message' => 'Ticket redeemed successfully.',
            'data' => $this->evaluate($booking, $payload),
        ];
    }

    /**
     * Legitimate reissue: bump the ticket version, invalidating old tokens.
     * Only an admin should call this. Used tickets cannot be reissued.
     *
     * @return array{ok: bool, message: string, token: ?string}
     */
    public function reissue(Booking $booking): array
    {
        $booking->loadMissing('payment');

        if (!$booking->isPaidAndConfirmed()) {
            return ['ok' => false, 'message' => 'Only a paid, confirmed booking can be reissued.', 'token' => null];
        }

        if ($booking->ticket_usage_status === Booking::TICKET_USED) {
            return ['ok' => false, 'message' => 'A used ticket cannot be reissued.', 'token' => null];
        }

        $booking->forceFill([
            'ticket_version' => max(1, (int) $booking->ticket_version) + 1,
        ])->save();

        $booking->refresh();

        return [
            'ok' => true,
            'message' => 'Ticket reissued. Previous tokens are no longer valid.',
            'token' => $this->issue($booking)['token'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: false, reason: string, message: string, data: array<string, mixed>}
     */
    private function redemptionFailure(string $reason, string $message, array $data = []): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'message' => $message,
            'data' => $data,
        ];
    }

    private function today(): string
    {
        return now()
            ->timezone((string) config('travelo.ticket.timezone', config('app.timezone')))
            ->toDateString();
    }

    private function displayVisitDate(Booking $booking): string
    {
        return $booking->booking_date?->format('d M Y') ?? 'its scheduled date';
    }

    /**
     * @return array{visit_date: ?string, reference: string}
     */
    private function ticketDateData(Booking $booking): array
    {
        return [
            'visit_date' => $booking->booking_date?->toDateString(),
            'reference' => $this->reference($booking),
        ];
    }

    private function validUntil(Booking $booking): ?string
    {
        if ($booking->booking_date === null) {
            return null;
        }

        return $booking->booking_date->copy()
            ->timezone((string) config('travelo.ticket.timezone', config('app.timezone')))
            ->endOfDay()
            ->toIso8601String();
    }

    private function encodePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode((string) $json), '+/', '-_'), '=');
    }

    private function sign(string $encodedPayload): string
    {
        return hash_hmac('sha256', $encodedPayload, (string) config('app.key'));
    }
}
