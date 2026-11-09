<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Review;
use App\Models\TourPackage;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies the public destination catalog filters and sort to one query.
 *
 * All aggregates use correlated subqueries so the list query stays a single
 * round trip: no per-row rating or popularity query (no N+1).
 */
class DestinationFilter
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
                $sub->where('name', 'like', "%{$term}%")
                    ->orWhere('city', 'like', "%{$term}%")
                    ->orWhere('country', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if (filled($filters['category'] ?? null)) {
            $query->where('category', $filters['category']);
        }

        if (filled($filters['location'] ?? null)) {
            $location = trim((string) $filters['location']);

            $query->where(function (Builder $sub) use ($location) {
                $sub->where('city', 'like', "%{$location}%")
                    ->orWhere('country', 'like', "%{$location}%")
                    ->orWhere('name', 'like', "%{$location}%");
            });
        }

        // Legacy exact-country filter kept for existing clients.
        if (filled($filters['country'] ?? null)) {
            $query->where('country', $filters['country']);
        }

        // Destinations have no price/duration/quota of their own. These
        // filters mean "has at least one tour package that matches".
        if ($this->needsPackageConstraint($filters)) {
            $query->whereHas('tourPackages', function (Builder $packages) use ($filters) {
                $this->applyPackageConstraints($packages, $filters);
            });
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
                return $query
                    ->orderBy(
                        TourPackage::query()
                            ->selectRaw('coalesce(min(price), 999999999999)')
                            ->whereColumn('tour_packages.destination_id', 'destinations.id'),
                        'asc'
                    )
                    ->orderBy('destinations.id');

            case 'price_desc':
                return $query
                    ->orderByDesc(
                        TourPackage::query()
                            ->selectRaw('coalesce(min(price), 0)')
                            ->whereColumn('tour_packages.destination_id', 'destinations.id')
                    )
                    ->orderByDesc('destinations.id');

            case 'rating_desc':
                return $query
                    ->orderByDesc($this->publishedRatingSubquery())
                    ->orderByDesc('destinations.id');

            case 'popular':
                return $query
                    ->orderByDesc($this->successfulBookingsSubquery())
                    ->orderByDesc('destinations.id');

            case 'newest':
            default:
                return $query
                    ->orderByDesc('destinations.created_at')
                    ->orderByDesc('destinations.id');
        }
    }

    /**
     * Average of published reviews only. Hidden reviews never move the score.
     */
    private function publishedRatingSubquery()
    {
        return Review::query()
            ->selectRaw('coalesce(avg(reviews.rating), 0)')
            ->whereColumn('reviews.destination_id', 'destinations.id')
            ->where('reviews.status', Review::STATUS_PUBLISHED);
    }

    /**
     * Number of paid, confirmed/completed bookings across the destination's
     * packages. Pending or cancelled bookings do not count.
     */
    private function successfulBookingsSubquery()
    {
        return Booking::query()
            ->selectRaw('count(*)')
            ->join(
                'tour_packages as popularity_packages',
                'popularity_packages.id',
                '=',
                'bookings.tour_package_id'
            )
            ->whereColumn('popularity_packages.destination_id', 'destinations.id')
            ->where('bookings.payment_status', Booking::SUCCESSFUL_PAYMENT_STATUS)
            ->whereIn('bookings.booking_status', Booking::SUCCESSFUL_BOOKING_STATUSES);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function needsPackageConstraint(array $filters): bool
    {
        return filled($filters['min_price'] ?? null)
            || filled($filters['max_price'] ?? null)
            || filled($filters['duration'] ?? null)
            || filled($filters['available_date'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyPackageConstraints(Builder $packages, array $filters): void
    {
        if (filled($filters['min_price'] ?? null)) {
            $packages->where('price', '>=', $filters['min_price']);
        }

        if (filled($filters['max_price'] ?? null)) {
            $packages->where('price', '<=', $filters['max_price']);
        }

        if (filled($filters['duration'] ?? null)) {
            $this->applyDuration($packages, (string) $filters['duration']);
        }

        if (filled($filters['available_date'] ?? null)) {
            $this->availability->applyAvailableOnDate(
                $packages,
                (string) $filters['available_date']
            );
        }
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
