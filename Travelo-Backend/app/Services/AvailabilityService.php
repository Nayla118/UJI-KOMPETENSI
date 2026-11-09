<?php

namespace App\Services;

use App\Exceptions\BookingUnavailableException;
use App\Models\Booking;
use App\Models\TourPackage;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for per-date capacity.
 *
 * Default capacity comes from `tour_packages.max_people`; an explicit row in
 * `tour_package_date_availabilities` can lower/raise it or close the date.
 * Seats are consumed by every booking that is not cancelled/failed/expired,
 * so a pending payment temporarily holds its seats.
 */
class AvailabilityService
{
    /**
     * Booking statuses that still hold seats.
     */
    public const HOLDING_BOOKING_STATUSES = ['pending', 'confirmed', 'completed'];

    /**
     * Payment statuses that never hold seats, even if the booking status was
     * not moved to cancelled (defensive against manual admin edits).
     */
    public const RELEASED_PAYMENT_STATUSES = ['failed', 'expired'];

    /**
     * @return array{
     *     date: string,
     *     capacity: int,
     *     booked: int,
     *     remaining: int,
     *     is_closed: bool,
     *     available: bool,
     *     reason: ?string,
     *     message: string,
     *     note: ?string
     * }
     */
    public function forDate(TourPackage $package, string|CarbonInterface $date, int $people = 1): array
    {
        $day = $this->normaliseDate($date);
        $override = $package->dateAvailabilities()
            ->whereDate('date', $day->toDateString())
            ->first();

        $capacity = (int) ($override?->capacity ?? $package->max_people);
        $booked = $this->bookedSeats($package->id, $day);
        $remaining = max(0, $capacity - $booked);
        $isClosed = (bool) ($override?->is_closed ?? false);
        $people = max(1, $people);

        $reason = null;
        $message = '';

        if ($isClosed) {
            $reason = 'closed';
            $message = 'This date is closed for this tour package.';
        } elseif ($remaining < $people) {
            $reason = 'full';
            $message = $remaining === 0
                ? 'This date is fully booked.'
                : "Only {$remaining} seat(s) left on this date.";
        } else {
            $message = "{$remaining} seat(s) left on this date.";
        }

        return [
            'date' => $day->toDateString(),
            'capacity' => $capacity,
            'booked' => $booked,
            'remaining' => $remaining,
            'is_closed' => $isClosed,
            'available' => $reason === null,
            'reason' => $reason,
            'message' => $message,
            'note' => $override?->note,
        ];
    }

    /**
     * Month view used by the Android date section and the admin calendar.
     *
     * @return array{
     *     tour_package_id: int,
     *     default_capacity: int,
     *     month: string,
     *     days: array<int, array<string, mixed>>
     * }
     */
    public function forMonth(TourPackage $package, string $month, int $people = 1): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01', config('app.timezone'));

        if ($start === false) {
            $start = CarbonImmutable::now(config('app.timezone'))->startOfMonth();
        }

        $start = $start->startOfMonth();
        $end = $start->endOfMonth();

        $overrides = $package->dateAvailabilities()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($row) => $row->date->toDateString());

        $bookedByDate = $this->bookedSeatsByDate(
            $package->id,
            $start->toDateString(),
            $end->toDateString()
        );

        $days = [];
        $cursor = $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            $override = $overrides->get($key);
            $capacity = (int) ($override?->capacity ?? $package->max_people);
            $booked = (int) ($bookedByDate[$key] ?? 0);
            $remaining = max(0, $capacity - $booked);
            $isClosed = (bool) ($override?->is_closed ?? false);
            $available = ! $isClosed && $remaining >= max(1, $people);

            $days[] = [
                'date' => $key,
                'capacity' => $capacity,
                'booked' => $booked,
                'remaining' => $remaining,
                'is_closed' => $isClosed,
                'available' => $available,
                'reason' => $isClosed ? 'closed' : ($available ? null : 'full'),
                'note' => $override?->note,
                'has_override' => $override !== null,
            ];

            $cursor = $cursor->addDay();
        }

        return [
            'tour_package_id' => $package->id,
            'default_capacity' => (int) $package->max_people,
            'month' => $start->format('Y-m'),
            'days' => $days,
        ];
    }

    /**
     * Constrain a tour package query to packages with at least one seat left
     * on the given date.
     *
     * This mirrors forDate()/bookedSeats() in SQL so a whole page can be
     * filtered without one availability query per row:
     * - an override row may lower/raise capacity or close the date;
     * - pending/confirmed/completed bookings still hold seats;
     * - failed/expired payments release their seats.
     */
    public function applyAvailableOnDate(Builder $query, string|CarbonInterface $date): Builder
    {
        $day = $this->normaliseDate($date);
        $dateString = $day->toDateString();
        $nextDayString = $day->addDay()->toDateString();

        return $query
            ->whereNotExists(function ($sub) use ($dateString, $nextDayString) {
                $sub->selectRaw('1')
                    ->from('tour_package_date_availabilities')
                    ->whereColumn(
                        'tour_package_date_availabilities.tour_package_id',
                        'tour_packages.id'
                    )
                    ->where('tour_package_date_availabilities.date', '>=', $dateString)
                    ->where('tour_package_date_availabilities.date', '<', $nextDayString)
                    ->where('tour_package_date_availabilities.is_closed', true);
            })
            ->whereRaw(
                '(COALESCE((SELECT capacity FROM tour_package_date_availabilities WHERE tour_package_id = tour_packages.id AND date >= ? AND date < ? LIMIT 1), tour_packages.max_people)'
                .' - (SELECT COALESCE(SUM(people_count), 0) FROM bookings WHERE tour_package_id = tour_packages.id AND booking_date >= ? AND booking_date < ? AND booking_status IN (?, ?, ?) AND payment_status NOT IN (?, ?))) >= 1',
                [
                    $dateString,
                    $nextDayString,
                    $dateString,
                    $nextDayString,
                    ...self::HOLDING_BOOKING_STATUSES,
                    ...self::RELEASED_PAYMENT_STATUSES,
                ]
            );
    }

    public function bookedSeats(int $tourPackageId, string|CarbonInterface $date): int
    {
        return (int) Booking::query()
            ->where('tour_package_id', $tourPackageId)
            ->whereDate('booking_date', $this->normaliseDate($date)->toDateString())
            ->whereIn('booking_status', self::HOLDING_BOOKING_STATUSES)
            ->whereNotIn('payment_status', self::RELEASED_PAYMENT_STATUSES)
            ->sum('people_count');
    }

    /**
     * @return array<string, int> Y-m-d => booked seats
     */
    private function bookedSeatsByDate(int $tourPackageId, string $start, string $end): array
    {
        return Booking::query()
            ->where('tour_package_id', $tourPackageId)
            ->whereBetween('booking_date', [$start, $end])
            ->whereIn('booking_status', self::HOLDING_BOOKING_STATUSES)
            ->whereNotIn('payment_status', self::RELEASED_PAYMENT_STATUSES)
            ->groupBy('booking_date')
            ->selectRaw('booking_date, SUM(people_count) as booked')
            ->pluck('booked', 'booking_date')
            ->mapWithKeys(fn ($total, $date) => [
                CarbonImmutable::parse($date)->toDateString() => (int) $total,
            ])
            ->all();
    }

    /**
     * Used inside the booking transaction after locking the package row.
     *
     * @throws BookingUnavailableException
     */
    public function ensureAvailable(TourPackage $package, string|CarbonInterface $date, int $people): array
    {
        $availability = $this->forDate($package, $date, $people);

        if (! $availability['available']) {
            throw new BookingUnavailableException(
                $availability['message'].' Please choose another date.',
                (string) $availability['reason'],
                $availability['remaining']
            );
        }

        return $availability;
    }

    public function normaliseDate(string|CarbonInterface $date): CarbonImmutable
    {
        if ($date instanceof CarbonInterface) {
            return CarbonImmutable::instance($date)->startOfDay();
        }

        return CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();
    }
}
