<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for review eligibility and public aggregates.
 *
 * Client-supplied user ids, destination ids, average ratings and check-in
 * flags are never trusted. Eligibility always comes from the booking's own
 * payment/ticket records, and the destination is derived from the booking's
 * tour package.
 */
class ReviewService
{
    public const ELIGIBLE = 'eligible';
    public const NOT_BOOKED = 'not_booked';
    public const UNPAID = 'unpaid';
    public const NOT_CHECKED_IN = 'not_checked_in';
    public const REVIEWED = 'reviewed';
    public const SUPERSEDED = 'superseded';
    public const CANCELLED = 'cancelled';

    /**
     * Public aggregate for one destination. Hidden reviews never contribute.
     */
    public function summary(int $destinationId): array
    {
        $rows = Review::query()
            ->published()
            ->where('destination_id', $destinationId)
            ->select('rating', DB::raw('COUNT(*) as total'))
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $distribution = [
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
            '5' => 0,
        ];

        $count = 0;
        $sum = 0;

        foreach ($rows as $rating => $total) {
            $rating = (int) $rating;
            $total = (int) $total;

            if ($rating >= Review::MIN_RATING && $rating <= Review::MAX_RATING) {
                $distribution[(string) $rating] = $total;
                $count += $total;
                $sum += ($rating * $total);
            }
        }

        return [
            'average' => $count > 0 ? round($sum / $count, 2) : null,
            'count' => $count,
            'distribution' => $distribution,
        ];
    }

    /**
     * Published reviews for a destination, newest first.
     */
    public function publicReviews(int $destinationId, int $perPage = 10): LengthAwarePaginator
    {
        return Review::query()
            ->published()
            ->where('destination_id', $destinationId)
            ->with([
                'user:id,name',
                'booking:id,ticket_status,ticket_used_at',
            ])
            ->latest()
            ->latest('id')
            ->paginate(max(1, min($perPage, 50)));
    }

    /**
     * Destination-level state for the signed-in traveller.
     *
     * One destination can have many visits, but a review belongs to one
     * visit. Once a visit has been reviewed, an older unreviewed visit must
     * not bring the "rate your visit" prompt back. Only a visit that is newer
     * than the latest reviewed visit may unlock a new review.
     *
     * @return array{status: string, booking: ?Booking, review: ?Review}
     */
    public function eligibilityForDestination(User $user, Destination $destination): array
    {
        $bookings = Booking::query()
            ->where('user_id', $user->id)
            ->whereHas('tourPackage', fn ($query) => $query->where('destination_id', $destination->id))
            ->with(['review', 'payment', 'tourPackage.destination'])
            ->get();

        if ($bookings->isEmpty()) {
            return ['status' => self::NOT_BOOKED, 'booking' => null, 'review' => null];
        }

        $states = $bookings
            ->map(fn (Booking $booking) => [
                'booking' => $booking,
                'review' => $booking->review,
                'status' => $this->statusForBooking($booking, $booking->review),
            ])
            ->sortByDesc(fn (array $state) => $this->visitSortKey($state['booking']))
            ->values();

        $latestReviewed = $states->firstWhere('status', self::REVIEWED);

        // A new visit only counts when it happened after the latest reviewed
        // visit. An older unreviewed ticket is superseded by that review.
        $newerEligible = $states->first(function (array $state) use ($latestReviewed) {
            if ($state['status'] !== self::ELIGIBLE) {
                return false;
            }

            return $latestReviewed === null
                || $this->isVisitNewerThan($state['booking'], $latestReviewed['booking']);
        });

        if ($newerEligible !== null) {
            return $newerEligible;
        }

        if ($latestReviewed !== null) {
            return $latestReviewed;
        }

        foreach ([self::NOT_CHECKED_IN, self::UNPAID, self::CANCELLED] as $priority) {
            $state = $states->firstWhere('status', $priority);

            if ($state !== null) {
                return $state;
            }
        }

        return ['status' => self::NOT_BOOKED, 'booking' => null, 'review' => null];
    }

    /**
     * Chronological comparison for two visits. ticket_used_at is the official
     * visit time; booking_date and the id break ties deterministically.
     */
    public function isVisitNewerThan(Booking $candidate, Booking $reference): bool
    {
        return $this->visitSortKey($candidate) > $this->visitSortKey($reference);
    }

    private function visitSortKey(Booking $booking): string
    {
        $visitedAt = $booking->ticket_used_at ?? $booking->booking_date;

        return sprintf(
            '%020d:%020d',
            $visitedAt?->getTimestamp() ?? 0,
            $booking->id
        );
    }

    public function latestReviewedVisit(User $user, int $destinationId): ?Booking
    {
        return Booking::query()
            ->where('user_id', $user->id)
            ->whereHas('tourPackage', fn ($query) => $query->where('destination_id', $destinationId))
            ->whereHas('review')
            ->get()
            ->sortByDesc(fn (Booking $booking) => $this->visitSortKey($booking))
            ->first();
    }

    /**
     * State for one booking, used by My Bookings / Ticket History.
     *
     * @return array{status: string, review: ?Review}
     */
    public function bookingEligibility(Booking $booking): array
    {
        $review = $booking->relationLoaded('review')
            ? $booking->review
            : $booking->review()->first();

        return [
            'status' => $this->statusForBooking($booking, $review),
            'review' => $review,
        ];
    }

    /**
     * Strict server-side eligibility. A booking must be owned by this user,
     * paid with a real paid_at, confirmed, not cancelled/refunded, and its
     * ticket must have been redeemed at the gate.
     */
    public function statusForBooking(Booking $booking, ?Review $review = null): string
    {
        if ($review !== null) {
            return self::REVIEWED;
        }

        $booking->loadMissing('payment');

        /** @var Payment|null $payment */
        $payment = $booking->payment;

        if ($booking->booking_status === 'cancelled'
            || in_array($booking->payment_status, ['failed', 'expired'], true)
            || ($payment !== null && in_array($payment->transaction_status, [
                'refund',
                'partial_refund',
                'chargeback',
                'cancel',
                'deny',
                'failure',
                'expire',
            ], true))) {
            return self::CANCELLED;
        }

        if ($booking->payment_status !== 'paid'
            || $booking->booking_status !== 'confirmed'
            || $payment === null
            || $payment->paid_at === null) {
            return self::UNPAID;
        }

        if ($booking->ticket_status !== Booking::TICKET_USED
            || $booking->ticket_used_at === null) {
            return self::NOT_CHECKED_IN;
        }

        return self::ELIGIBLE;
    }

    /**
     * Create the one review allowed for this booking.
     *
     * The database unique index on booking_id is the concurrency guard: if
     * two requests pass the pre-check at the same time, exactly one insert
     * wins and the other receives a clean "already reviewed" response.
     *
     * @return array{ok: bool, status: int, reason: string, review: ?Review}
     */
    public function submit(User $user, Booking $booking, int $rating, ?string $comment): array
    {
        if ($rating < Review::MIN_RATING || $rating > Review::MAX_RATING) {
            return $this->failure(422, 'invalid_rating');
        }

        try {
            return DB::transaction(function () use ($user, $booking, $rating, $comment) {
                /** @var Booking|null $fresh */
                $fresh = Booking::query()
                    ->whereKey($booking->id)
                    ->lockForUpdate()
                    ->first();

                if ($fresh === null) {
                    return $this->failure(404, 'booking_not_found');
                }

                $fresh->load(['review', 'payment', 'tourPackage.destination']);

                if ((int) $fresh->user_id !== (int) $user->id) {
                    // Do not reveal another traveller's booking.
                    return $this->failure(404, 'booking_not_found');
                }

                $state = $this->statusForBooking($fresh, $fresh->review);

                if ($state !== self::ELIGIBLE) {
                    return $this->failure(
                        409,
                        $state === self::REVIEWED ? 'already_reviewed' : $state
                    );
                }

                $destinationId = $fresh->tourPackage?->destination_id;

                if ($destinationId === null) {
                    return $this->failure(422, 'destination_not_found');
                }

                $latestReviewed = $this->latestReviewedVisit($user, $destinationId);

                if ($latestReviewed !== null
                    && ! $this->isVisitNewerThan($fresh, $latestReviewed)) {
                    // The traveller already reviewed a later visit at this
                    // destination; do not offer or accept an older one again.
                    return $this->failure(409, 'superseded');
                }

                $review = Review::create([
                    'booking_id' => $fresh->id,
                    'user_id' => $user->id,
                    'destination_id' => $destinationId,
                    'rating' => $rating,
                    'comment' => $this->cleanComment($comment),
                    'status' => Review::STATUS_PUBLISHED,
                ]);

                return [
                    'ok' => true,
                    'status' => 201,
                    'reason' => 'created',
                    'review' => $review->load(['destination', 'booking']),
                ];
            });
        } catch (QueryException $exception) {
            // Unique constraint race: another request created the review first.
            $existing = Review::query()->where('booking_id', $booking->id)->first();

            if ($existing !== null) {
                return $this->failure(409, 'already_reviewed');
            }

            throw $exception;
        }
    }

    /**
     * @return array{ok: bool, status: int, reason: string, review: ?Review}
     */
    public function update(User $user, Review $review, int $rating, ?string $comment): array
    {
        if ($rating < Review::MIN_RATING || $rating > Review::MAX_RATING) {
            return $this->failure(422, 'invalid_rating');
        }

        if ((int) $review->user_id !== (int) $user->id) {
            return $this->failure(404, 'review_not_found');
        }

        $review->refresh();

        if (! $review->isPublished()) {
            // Hidden reviews must not be edited back into the public list.
            return $this->failure(409, 'review_hidden');
        }

        $review->update([
            'rating' => $rating,
            'comment' => $this->cleanComment($comment),
        ]);

        return [
            'ok' => true,
            'status' => 200,
            'reason' => 'updated',
            'review' => $review->fresh(['destination', 'booking']),
        ];
    }

    /**
     * @return array{ok: bool, status: int, reason: string}
     */
    public function delete(User $user, Review $review): array
    {
        if ((int) $review->user_id !== (int) $user->id) {
            return $this->failure(404, 'review_not_found');
        }

        if (! $review->isPublished()) {
            // Deleting a hidden review would free the booking for a fresh
            // submission and bypass the moderation decision.
            return $this->failure(409, 'review_hidden');
        }

        $review->delete();

        return ['ok' => true, 'status' => 200, 'reason' => 'deleted', 'review' => null];
    }

    /**
     * Admin moderation. Every change is written to the audit log.
     *
     * @return array{ok: bool, status: int, reason: string, review: ?Review}
     */
    public function moderate(User $admin, Review $review, string $action, string $reason): array
    {
        if (! in_array($action, ['hide', 'restore'], true)) {
            return $this->failure(422, 'invalid_action');
        }

        $reason = trim($reason);

        if ($reason === '') {
            return $this->failure(422, 'invalid_reason');
        }

        $previousStatus = $review->status;
        $newStatus = $action === 'hide'
            ? Review::STATUS_HIDDEN
            : Review::STATUS_PUBLISHED;

        DB::transaction(function () use (
            $admin,
            $action,
            $newStatus,
            $previousStatus,
            $reason,
            $review
        ) {
            $review->update([
                'status' => $newStatus,
                'moderation_reason' => $reason,
                'moderated_by' => $admin->id,
                'moderated_at' => now(),
            ]);

            ActivityLog::create([
                'log_name' => 'review_moderation',
                'description' => $action === 'hide'
                    ? 'Review hidden by admin'
                    : 'Review restored by admin',
                'subject_type' => Review::class,
                'subject_id' => $review->id,
                'causer_type' => User::class,
                'causer_id' => $admin->id,
                'properties' => [
                    'action' => $action,
                    'previous_status' => $previousStatus,
                    'new_status' => $newStatus,
                    'reason' => $reason,
                    'destination_id' => $review->destination_id,
                    'booking_id' => $review->booking_id,
                    // Never copy the review text into the audit log.
                ],
            ]);
        });

        return [
            'ok' => true,
            'status' => 200,
            'reason' => $action === 'hide' ? 'hidden' : 'restored',
            'review' => $review->fresh(['user', 'destination', 'booking']),
        ];
    }

    public function messageFor(string $status, bool $reviewHidden = false): string
    {
        return match ($status) {
            self::ELIGIBLE => 'Your ticket was checked in. You can rate this destination now.',
            self::REVIEWED => $reviewHidden
                ? 'Your review is under moderation.'
                : 'You already reviewed this visit.',
            self::SUPERSEDED => 'A newer visit at this destination has already been reviewed.',
            self::NOT_CHECKED_IN => 'Rating unlocks after your ticket is scanned at the gate.',
            self::UNPAID => 'Complete and confirm your payment before rating a visit.',
            self::CANCELLED => 'This booking was cancelled or refunded, so it cannot be rated.',
            default => 'Book and visit this destination to leave a verified review.',
        };
    }

    public function failure(int $status, string $reason): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'reason' => $reason,
            'review' => null,
        ];
    }

    private function cleanComment(?string $comment): ?string
    {
        if ($comment === null) {
            return null;
        }

        $comment = trim($comment);

        return $comment === '' ? null : $comment;
    }
}
