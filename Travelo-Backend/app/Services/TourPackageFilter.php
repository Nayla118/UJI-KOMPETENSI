<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies every optional GET /api/tour-packages filter and sort.
 *
 * Reviews are aggregated through the destination (a package inherits its
 * destination's published rating), and popularity counts only paid,
 * confirmed/completed bookings. Both are correlated subqueries, so filtering
 * or sorting a page never triggers N+1 queries.
 */
class TourPackageFilter
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        if (filled($filters['q'] ?? null)) {
            $term = trim((string) $filters['q']);

            $query->where(function (Builder $sub) use ($term) {
                $sub->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhereHas('destination', function (Builder $destination) use ($term) {
                        $destination->where('name', 'like', "%{$term}%")
                            ->orWhere('city', 'like', "%{$term}%")
                            ->orWhere('country', 'like', "%{$term}%");
                    });
            });
        }

        if (filled($filters['destination_id'] ?? null)) {
            $query->where('destination_id', $filters['destination_id']);
        }

        if (filled($filters['category'] ?? null)) {
            $query->whereHas('destination', function (Builder $destination) use ($filters) {
                $destination->where('category', $filters['category']);
            });
        }

        if (filled($filters['location'] ?? null)) {
            $location = trim((string) $filters['location']);

            $query->whereHas('destination', function (Builder $destination) use ($location) {
                $destination->where('city', 'like', "%{$location}%")
                    ->orWhere('country', 'like', "%{$location}%")
                    ->orWhere('name', 'like', "%{$location}%");
            });
        }

        // Legacy exact-country filter kept for existing clients.
        if (filled($filters['country'] ?? null)) {
            $query->whereHas('destination', function (Builder $destination) use ($filters) {
                $destination->where('country', $filters['country']);
            });
        }

        if (filled($filters['min_price'] ?? null)) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (filled($filters['max_price'] ?? null)) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (filled($filters['duration'] ?? null)) {
            $this->applyDuration($query, (string) $filters['duration']);
        }

        if (filled($filters['available_date'] ?? null)) {
            $this->availability->applyAvailableOnDate(
                $query,
                (string) $filters['available_date']
            );
        }

        if (isset($filters['min_rating']) && $filters['min_rating'] !== null) {
            // SQLite binds PDO floats as text; casting the bound value keeps
            // the comparison numeric on both SQLite and MySQL.
            $rating = $this->publishedRatingSubquery();

            $query->whereRaw(
                '('.$rating->toSql().') >= CAST(? AS DECIMAL(10,2))',
                [...$rating->getBindings(), (float) $filters['min_rating']]
            );
        }

        return $query;
    }

    public function sort(Builder $query, ?string $sort): Builder
    {
        switch ($sort) {
            case 'price_asc':
                return $query->orderBy('price', 'asc')->orderBy('id');

            case 'price_desc':
                return $query->orderByDesc('price')->orderByDesc('id');

            case 'rating_desc':
                return $query
                    ->orderByDesc($this->publishedRatingSubquery())
                    ->orderByDesc('tour_packages.id');

            case 'popular':
                return $query
                    ->orderByDesc($this->successfulBookingsSubquery())
                    ->orderByDesc('tour_packages.id');

            case 'newest':
            default:
                return $query
                    ->orderByDesc('tour_packages.created_at')
                    ->orderByDesc('tour_packages.id');
        }
    }

    /**
     * Published destination reviews only; hidden rows are excluded.
     */
    private function publishedRatingSubquery()
    {
        return Review::query()
            ->selectRaw('coalesce(avg(reviews.rating), 0)')
            ->whereColumn('reviews.destination_id', 'tour_packages.destination_id')
            ->where('reviews.status', Review::STATUS_PUBLISHED);
    }

    /**
     * Paid, confirmed/completed bookings for this package only.
     */
    private function successfulBookingsSubquery()
    {
        return Booking::query()
            ->selectRaw('count(*)')
            ->whereColumn('bookings.tour_package_id', 'tour_packages.id')
            ->where('bookings.payment_status', Booking::SUCCESSFUL_PAYMENT_STATUS)
            ->whereIn('bookings.booking_status', Booking::SUCCESSFUL_BOOKING_STATUSES);
    }

    private function applyDuration(Builder $query, string $duration): void
    {
        match ($duration) {
            '1' => $query->where('duration_days', 1),
            '2-3' => $query->whereBetween('duration_days', [2, 3]),
            '4+' => $query->where('duration_days', '>=', 4),
            default => null,
        };
    }
}
