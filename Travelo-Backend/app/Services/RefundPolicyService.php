<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\CarbonImmutable;

/**
 * Tiered cancellation refund policy.
 *
 * Refunds are computed from the amount actually paid (`total_price`, which
 * already includes any promo discount), never from the raw subtotal. The
 * tier boundary is measured in whole days before the visit date, in the
 * ticket timezone (`TRAVELO_TICKET_TIMEZONE`, default Asia/Jakarta).
 *
 * Tiers (config/travelo.php -> refund.tiers):
 *   >= 7 days before trip  -> 100%
 *   3-6 days before trip   -> 50%
 *   <  3 days before trip  -> 0%
 */
class RefundPolicyService
{
    public const TIER_FULL = 'full';

    public const TIER_PARTIAL = 'partial';

    public const TIER_NONE = 'none';

    /**
     * Calculate the refund for a booking at a given point in time.
     *
     * @return array{
     *     percentage: int,
     *     refund_amount: float,
     *     tier_label: string,
     *     tier: string,
     *     days_before: int,
     *     eligible: bool,
     *     reason?: string,
     * }
     */
    public function calculate(Booking $booking, ?\DateTimeInterface $at = null): array
    {
        $at = $at ?? CarbonImmutable::now(
            (string) config('travelo.ticket.timezone', config('app.timezone'))
        );

        $tiers = (array) config('travelo.refund.tiers', []);

        // Not paid -> nothing to refund.
        if ($booking->payment_status !== 'paid') {
            return $this->result(
                0, 0.0, self::TIER_NONE, 'Not eligible (booking not paid)',
                0, false, 'not_paid'
            );
        }

        // Visit date already passed -> nothing to refund. Check this before
        // ticket usage because an elapsed visit date also makes the ticket
        // "expired" in the accessor, which would mask the real reason.
        $visitDate = $booking->booking_date?->toDateString();
        $today = $at->toDateString();

        if ($visitDate !== null && $visitDate < $today) {
            return $this->result(
                0, 0.0, self::TIER_NONE, 'Not eligible (visit date has passed)',
                0, false, 'past_visit'
            );
        }

        // Ticket already used -> cannot be cancelled/refunded.
        if ($booking->ticket_usage_status !== Booking::TICKET_UNUSED) {
            return $this->result(
                0, 0.0, self::TIER_NONE, 'Not eligible (ticket already used or expired)',
                0, false, 'ticket_used'
            );
        }

        // Days before the trip, in the ticket timezone. Compare whole days
        // (start of day to start of day) so the boundary is inclusive on the
        // lower side regardless of the time-of-day.
        $daysBefore = (int) $at->startOfDay()->diffInDays(
            $booking->booking_date?->startOfDay() ?? $at->startOfDay(),
            false
        );

        $percentage = 0;
        $tier = self::TIER_NONE;
        $tierLabel = 'No refund';

        foreach ($tiers as $tierConfig) {
            $minDays = (int) ($tierConfig['min_days'] ?? 0);
            if ($daysBefore >= $minDays) {
                $percentage = (int) ($tierConfig['percentage'] ?? 0);
                $tier = $percentage >= 100 ? self::TIER_FULL
                    : ($percentage > 0 ? self::TIER_PARTIAL : self::TIER_NONE);
                $tierLabel = (string) ($tierConfig['label'] ?? $tierLabel);
                break;
            }
        }

        $refundAmount = round((float) $booking->total_price * $percentage / 100, 2);

        return $this->result(
            $percentage, $refundAmount, $tier, $tierLabel,
            $daysBefore, true
        );
    }

    /**
     * Human-readable policy summary for the client (Android cancellation
     * preview screen). No amounts are computed here; the preview endpoint
     * supplies the money figures.
     */
    public function policySummary(): array
    {
        $tiers = (array) config('travelo.refund.tiers', []);
        $summary = [];

        foreach ($tiers as $tierConfig) {
            $summary[] = [
                'min_days' => (int) ($tierConfig['min_days'] ?? 0),
                'percentage' => (int) ($tierConfig['percentage'] ?? 0),
                'label' => (string) ($tierConfig['label'] ?? ''),
            ];
        }

        return [
            'timezone' => (string) config('travelo.ticket.timezone', config('app.timezone')),
            'tiers' => $summary,
            'auto_refund' => (bool) config('travelo.refund.auto_refund', false),
        ];
    }

    private function result(
        int $percentage,
        float $refundAmount,
        string $tier,
        string $tierLabel,
        int $daysBefore,
        bool $eligible,
        ?string $reason = null
    ): array {
        return [
            'percentage' => $percentage,
            'refund_amount' => $refundAmount,
            'tier_label' => $tierLabel,
            'tier' => $tier,
            'days_before' => $daysBefore,
            'eligible' => $eligible,
        ] + ($reason !== null ? ['reason' => $reason] : []);
    }
}
