<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\Payment;
use App\Models\Review;
use App\Models\TourPackage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only analytics for the admin dashboard and report page.
 *
 * Design notes:
 * - Every aggregate is computed in SQL (GROUP BY over an indexed date
 *   column). Only the small, already-aggregated result sets are processed in
 *   PHP, so no per-booking queries (N+1) are possible.
 * - Date buckets use the database value, which Travelo stores in
 *   `config('app.timezone')` (Asia/Jakarta). DATE() is supported by SQLite
 *   and MySQL, so the same queries run on both drivers.
 * - Revenue is recognised when a booking is paid (`payments.paid_at`, with
 *   `bookings.created_at` as fallback for legacy rows) and only for
 *   `bookings.payment_status = paid`. Refunds are shown separately as a
 *   series/card and subtracted only for the "net revenue" figure; gross and
 *   refunds never silently cancel each other out. This matches the manual
 *   refund policy: money is recognised when collected, the refund is a later
 *   event. The refund value is the snapshot stored on the change request
 *   (`refund_amount`), so tiered (partial) refunds are reported correctly.
 * - The composed summary is cached for a few minutes under a key that
 *   contains the date range and period, so two different filters can never
 *   share a cache entry.
 */
class ReportService
{
    public const PERIOD_DAY = 'day';

    public const PERIOD_WEEK = 'week';

    public const PERIOD_MONTH = 'month';

    public const PERIODS = [
        self::PERIOD_DAY,
        self::PERIOD_WEEK,
        self::PERIOD_MONTH,
    ];

    /**
     * Booking statuses that count as a completed sale for "best seller"
     * rankings. A cancelled/refunded booking is not a sold package.
     */
    private const SOLD_BOOKING_STATUSES = Booking::SUCCESSFUL_BOOKING_STATUSES;

    /**
     * Build the whole report payload. The range is normalised (start <= end,
     * capped at the configured maximum) and the result is cached.
     *
     * @return array<string, mixed>
     */
    public function summary(CarbonInterface $start, CarbonInterface $end, string $period = self::PERIOD_DAY): array
    {
        [$start, $end] = $this->normaliseRange($start, $end);
        $period = $this->normalisePeriod($period);

        $key = sprintf(
            // v2: refund totals now come from the per-request snapshot
            // (`refund_amount`), so old v1 cache entries must not survive a
            // deploy that introduced partial refunds.
            'travelo:reports:summary:v2:%s:%s:%s',
            $start->toDateString(),
            $end->toDateString(),
            $period
        );

        return Cache::remember(
            $key,
            (int) config('travelo.reports.cache_ttl', 300),
            fn () => $this->buildSummary($start, $end, $period)
        );
    }

    /**
     * Uncached composition, useful for exports (which should always reflect
     * the current data) and for tests.
     *
     * @return array<string, mixed>
     */
    public function buildSummary(CarbonInterface $start, CarbonInterface $end, string $period = self::PERIOD_DAY): array
    {
        [$start, $end] = $this->normaliseRange($start, $end);
        $period = $this->normalisePeriod($period);

        $revenue = $this->revenue($start, $end, $period);
        $bookings = $this->bookings($start, $end, $period);
        $occupancy = $this->occupancy($start, $end);
        $cancellations = $this->cancellations($start, $end);
        $users = $this->newUsers($start, $end, $period);
        $ratings = $this->ratings($start, $end);

        // Refund totals are shared by the revenue and cancellation sections.
        $cancellations['refunded_amount'] = $revenue['refunds'];

        return [
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'period' => $period,
                'days' => (int) $start->diffInDays($end) + 1,
            ],
            'revenue' => $revenue,
            'bookings' => $bookings,
            'top_packages' => $this->topPackages($start, $end),
            'top_destinations' => $this->topDestinations($start, $end),
            'occupancy' => $occupancy,
            'cancellations' => $cancellations,
            'users' => $users,
            'ratings' => $ratings,
            'cards' => [
                'revenue_gross' => $revenue['gross'],
                'revenue_refunds' => $revenue['refunds'],
                'revenue_net' => $revenue['net'],
                'bookings' => $bookings['total'],
                'cancelled' => $cancellations['cancelled'],
                'cancellation_rate' => $cancellations['rate'],
                'new_users' => $users['total'],
                'average_rating' => $ratings['average'],
                'review_count' => $ratings['count'],
                'occupancy_rate' => $occupancy['overall_rate'],
                'occupancy_booked' => $occupancy['overall_booked'],
                'occupancy_capacity' => $occupancy['overall_capacity'],
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Paid revenue, refunds and net revenue with a per-period series.
     *
     * @return array<string, mixed>
     */
    public function revenue(CarbonInterface $start, CarbonInterface $end, string $period = self::PERIOD_DAY): array
    {
        $dailyRevenue = $this->dailyMap(
            Booking::query()
                ->leftJoin('payments', 'payments.booking_id', '=', 'bookings.id')
                ->where('bookings.payment_status', Payment::STATUS_PAID)
                ->whereRaw(
                    'COALESCE(payments.paid_at, bookings.created_at) BETWEEN ? AND ?',
                    [$start->toDateTimeString(), $end->toDateTimeString()]
                )
                ->groupByRaw('DATE(COALESCE(payments.paid_at, bookings.created_at))')
                ->selectRaw('DATE(COALESCE(payments.paid_at, bookings.created_at)) as bucket, SUM(bookings.total_price) as total')
        );

        $dailyRefunds = $this->dailyMap(
            BookingChangeRequest::query()
                ->where('refund_status', BookingChangeRequest::REFUND_REFUNDED)
                ->whereNotNull('refunded_at')
                ->whereBetween('refunded_at', [
                    $start->toDateTimeString(),
                    $end->toDateTimeString(),
                ])
                ->groupByRaw('DATE(refunded_at)')
                ->selectRaw('DATE(refunded_at) as bucket, SUM(COALESCE(refund_amount, 0)) as total')
        );

        $gross = array_sum($dailyRevenue);
        $refunds = array_sum($dailyRefunds);

        $revenueBuckets = $this->bucketSeries($dailyRevenue, $start, $end, $period);
        $refundBuckets = $this->bucketSeries($dailyRefunds, $start, $end, $period);

        $series = [];

        foreach ($revenueBuckets as $date => $value) {
            $refund = $refundBuckets[$date] ?? 0.0;

            $series[] = [
                'date' => $date,
                'label' => $this->formatBucketLabel($date, $period),
                'gross' => round($value, 2),
                'refunds' => round($refund, 2),
                'net' => round($value - $refund, 2),
            ];
        }

        return [
            'gross' => round($gross, 2),
            'refunds' => round($refunds, 2),
            'net' => round($gross - $refunds, 2),
            'series' => $series,
            // Documented convention: revenue is recognised at payment time.
            // Refunds are counted on `refunded_at`, never back-dated.
            'basis' => 'paid_at',
            'refund_basis' => 'refunded_at',
        ];
    }

    /**
     * Booking counts by status and the daily/period trend.
     *
     * @return array<string, mixed>
     */
    public function bookings(CarbonInterface $start, CarbonInterface $end, string $period = self::PERIOD_DAY): array
    {
        $statusCounts = Booking::query()
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupBy('booking_status')
            ->selectRaw('booking_status, COUNT(*) as total')
            ->pluck('total', 'booking_status');

        $byStatus = [];

        foreach (['pending', 'confirmed', 'cancelled', 'completed'] as $status) {
            $byStatus[$status] = (int) ($statusCounts[$status] ?? 0);
        }

        // Any unexpected legacy status is still accounted for.
        foreach ($statusCounts as $status => $total) {
            if (! array_key_exists($status, $byStatus)) {
                $byStatus[$status] = (int) $total;
            }
        }

        $rows = Booking::query()
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupByRaw('DATE(created_at)')
            ->selectRaw(
                'DATE(created_at) as bucket, COUNT(*) as total, '
                .'SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as paid, '
                .'SUM(CASE WHEN booking_status = ? THEN 1 ELSE 0 END) as cancelled',
                [Payment::STATUS_PAID, 'cancelled']
            )
            ->get();

        $totalDaily = [];
        $paidDaily = [];
        $cancelledDaily = [];

        foreach ($rows as $row) {
            $date = CarbonImmutable::parse($row->bucket)->toDateString();
            $totalDaily[$date] = (float) $row->total;
            $paidDaily[$date] = (float) $row->paid;
            $cancelledDaily[$date] = (float) $row->cancelled;
        }

        $totalBuckets = $this->bucketSeries($totalDaily, $start, $end, $period);
        $paidBuckets = $this->bucketSeries($paidDaily, $start, $end, $period);
        $cancelledBuckets = $this->bucketSeries($cancelledDaily, $start, $end, $period);

        $series = [];

        foreach ($totalBuckets as $date => $value) {
            $series[] = [
                'date' => $date,
                'label' => $this->formatBucketLabel($date, $period),
                'total' => (int) $value,
                'paid' => (int) ($paidBuckets[$date] ?? 0),
                'cancelled' => (int) ($cancelledBuckets[$date] ?? 0),
            ];
        }

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'series' => $series,
        ];
    }

    /**
     * Top five packages by sold bookings (paid + confirmed/completed).
     *
     * @return array<int, array<string, mixed>>
     */
    public function topPackages(CarbonInterface $start, CarbonInterface $end, int $limit = 5): array
    {
        return Booking::query()
            ->join('tour_packages', 'tour_packages.id', '=', 'bookings.tour_package_id')
            ->join('destinations', 'destinations.id', '=', 'tour_packages.destination_id')
            ->whereIn('bookings.booking_status', self::SOLD_BOOKING_STATUSES)
            ->where('bookings.payment_status', Payment::STATUS_PAID)
            ->whereBetween('bookings.created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupBy('tour_packages.id', 'tour_packages.title', 'destinations.name')
            ->selectRaw(
                'tour_packages.id as id, tour_packages.title as title, destinations.name as destination, '
                .'COUNT(*) as bookings, SUM(bookings.people_count) as people, SUM(bookings.total_price) as revenue'
            )
            ->orderByDesc('bookings')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'destination' => (string) $row->destination,
                'bookings' => (int) $row->bookings,
                'people' => (int) $row->people,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * Top five destinations by sold bookings.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topDestinations(CarbonInterface $start, CarbonInterface $end, int $limit = 5): array
    {
        return Booking::query()
            ->join('tour_packages', 'tour_packages.id', '=', 'bookings.tour_package_id')
            ->join('destinations', 'destinations.id', '=', 'tour_packages.destination_id')
            ->whereIn('bookings.booking_status', self::SOLD_BOOKING_STATUSES)
            ->where('bookings.payment_status', Payment::STATUS_PAID)
            ->whereBetween('bookings.created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupBy('destinations.id', 'destinations.name')
            ->selectRaw(
                'destinations.id as id, destinations.name as name, '
                .'COUNT(*) as bookings, SUM(bookings.people_count) as people, SUM(bookings.total_price) as revenue'
            )
            ->orderByDesc('bookings')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'bookings' => (int) $row->bookings,
                'people' => (int) $row->people,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * Occupancy per package for the visit-date range.
     *
     * Capacity follows the same rules as AvailabilityService: the package's
     * `max_people` per date, replaced by a date override when present, and
     * zero for a closed date. Seats consumed are the same holding statuses and
     * release rules, so the numbers always agree with the availability API.
     *
     * @return array<string, mixed>
     */
    public function occupancy(CarbonInterface $start, CarbonInterface $end): array
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $days = (int) $start->diffInDays($end) + 1;

        $packages = TourPackage::query()
            ->leftJoin('tour_package_date_availabilities as a', function ($join) use ($startDate, $endDate) {
                $join->on('a.tour_package_id', '=', 'tour_packages.id')
                    ->whereBetween('a.date', [$startDate, $endDate]);
            })
            ->groupBy('tour_packages.id', 'tour_packages.title', 'tour_packages.max_people')
            ->selectRaw(
                'tour_packages.id as id, tour_packages.title as title, tour_packages.max_people as max_people, '
                .'(tour_packages.max_people * ?) + COALESCE(SUM(CASE '
                .'WHEN a.id IS NULL THEN 0 '
                .'WHEN a.is_closed = 1 THEN -tour_packages.max_people '
                .'WHEN a.capacity IS NOT NULL THEN (a.capacity - tour_packages.max_people) '
                .'ELSE 0 END), 0) as capacity',
                [$days]
            )
            ->get();

        $booked = Booking::query()
            ->whereBetween('booking_date', [$startDate, $endDate])
            ->whereIn('booking_status', AvailabilityService::HOLDING_BOOKING_STATUSES)
            ->whereNotIn('payment_status', AvailabilityService::RELEASED_PAYMENT_STATUSES)
            ->groupBy('tour_package_id')
            ->selectRaw('tour_package_id, SUM(people_count) as booked')
            ->pluck('booked', 'tour_package_id');

        $rows = $packages
            ->map(function ($package) use ($booked) {
                $capacity = max(0, (int) $package->capacity);
                $people = max(0, (int) ($booked[$package->id] ?? 0));
                $rate = $capacity > 0 ? round($people / $capacity * 100, 1) : 0.0;

                return [
                    'id' => (int) $package->id,
                    'title' => (string) $package->title,
                    'capacity' => $capacity,
                    'booked' => $people,
                    'occupancy_rate' => $rate,
                ];
            })
            ->sortByDesc('occupancy_rate')
            ->values();

        $totalCapacity = (int) $rows->sum('capacity');
        $totalBooked = (int) $rows->sum('booked');

        return [
            'days' => $days,
            'packages' => $rows->all(),
            'overall_capacity' => $totalCapacity,
            'overall_booked' => $totalBooked,
            'overall_rate' => $totalCapacity > 0
                ? round($totalBooked / $totalCapacity * 100, 1)
                : 0.0,
        ];
    }

    /**
     * Cancellation rate, change-request statuses, refund statuses and the most
     * common cancellation reasons for the period.
     *
     * @return array<string, mixed>
     */
    public function cancellations(CarbonInterface $start, CarbonInterface $end): array
    {
        $range = [$start->toDateTimeString(), $end->toDateTimeString()];

        $total = Booking::query()->whereBetween('created_at', $range)->count();
        $cancelled = Booking::query()
            ->whereBetween('created_at', $range)
            ->where('booking_status', 'cancelled')
            ->count();

        $requests = BookingChangeRequest::query()
            ->whereBetween('created_at', $range);

        $requestStatus = [];

        foreach (BookingChangeRequest::STATUSES as $status) {
            $requestStatus[$status] = 0;
        }

        foreach ($requests->clone()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status') as $status => $count) {
            $requestStatus[$status] = (int) $count;
        }

        $refundStatus = [
            BookingChangeRequest::REFUND_NOT_APPLICABLE => 0,
            BookingChangeRequest::REFUND_PENDING => 0,
            BookingChangeRequest::REFUND_REFUNDED => 0,
        ];

        foreach ($requests->clone()
            ->groupBy('refund_status')
            ->selectRaw('refund_status, COUNT(*) as total')
            ->pluck('total', 'refund_status') as $status => $count) {
            $refundStatus[$status] = (int) $count;
        }

        $reasons = $requests->clone()
            ->whereNotNull('reason')
            ->where('reason', '<>', '')
            ->groupBy('reason')
            ->selectRaw('reason, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'reason' => (string) $row->reason,
                'total' => (int) $row->total,
            ])
            ->all();

        // Refunds are attributed to the day the money went back, so use
        // `refunded_at` rather than the request creation date.
        $refundedAmount = (float) BookingChangeRequest::query()
            ->where('refund_status', BookingChangeRequest::REFUND_REFUNDED)
            ->whereNotNull('refunded_at')
            ->whereBetween('refunded_at', $range)
            ->sum('refund_amount');

        return [
            'total_bookings' => $total,
            'cancelled' => $cancelled,
            'rate' => $total > 0 ? round($cancelled / $total * 100, 1) : 0.0,
            'request_status' => $requestStatus,
            'refund_status' => $refundStatus,
            'reasons' => $reasons,
            'refunded_amount' => round($refundedAmount, 2),
        ];
    }

    /**
     * New traveller accounts per period. Admin/staff accounts are excluded:
     * "new users" means customer sign-ups.
     *
     * @return array<string, mixed>
     */
    public function newUsers(CarbonInterface $start, CarbonInterface $end, string $period = self::PERIOD_DAY): array
    {
        $daily = $this->dailyMap(
            User::query()
                ->where('role', User::ROLE_TRAVELLER)
                ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->groupByRaw('DATE(created_at)')
                ->selectRaw('DATE(created_at) as bucket, COUNT(*) as total')
        );

        $buckets = $this->bucketSeries($daily, $start, $end, $period);

        $series = [];

        foreach ($buckets as $date => $value) {
            $series[] = [
                'date' => $date,
                'label' => $this->formatBucketLabel($date, $period),
                'total' => (int) $value,
            ];
        }

        return [
            'total' => (int) array_sum($daily),
            'series' => $series,
        ];
    }

    /**
     * Average rating of published reviews plus the 1-5 distribution.
     *
     * @return array<string, mixed>
     */
    public function ratings(CarbonInterface $start, CarbonInterface $end): array
    {
        $aggregate = Review::query()
            ->where('status', Review::STATUS_PUBLISHED)
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->first();

        $count = (int) ($aggregate->total ?? 0);
        $average = $count > 0 ? round((float) $aggregate->average, 2) : 0.0;

        $distribution = [];

        foreach (range(Review::MIN_RATING, Review::MAX_RATING) as $rating) {
            $distribution[(string) $rating] = 0;
        }

        $rows = Review::query()
            ->where('status', Review::STATUS_PUBLISHED)
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupBy('rating')
            ->selectRaw('rating, COUNT(*) as total')
            ->pluck('total', 'rating');

        foreach ($rows as $rating => $total) {
            $distribution[(string) (int) $rating] = (int) $total;
        }

        return [
            'average' => $average,
            'count' => $count,
            'distribution' => $distribution,
        ];
    }

    /**
     * Chart-ready payload shared by the dashboard and the reports page. The
     * view embeds this JSON; the Vite-bundled Chart.js script reads it.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function chartPayload(array $report): array
    {
        $revenue = $report['revenue']['series'];
        $bookings = $report['bookings']['series'];
        $status = $report['bookings']['by_status'];
        $packages = $report['top_packages'];
        $occupancy = array_slice($report['occupancy']['packages'], 0, 8);
        $users = $report['users']['series'];
        $ratings = $report['ratings']['distribution'];

        return [
            'revenue' => [
                'labels' => array_column($revenue, 'label'),
                'gross' => array_column($revenue, 'gross'),
                'net' => array_column($revenue, 'net'),
                'refunds' => array_column($revenue, 'refunds'),
            ],
            'bookings' => [
                'labels' => array_column($bookings, 'label'),
                'total' => array_column($bookings, 'total'),
                'paid' => array_column($bookings, 'paid'),
                'cancelled' => array_column($bookings, 'cancelled'),
            ],
            'status' => [
                'labels' => array_map(fn ($key) => ucfirst((string) $key), array_keys($status)),
                'values' => array_values($status),
            ],
            'topPackages' => [
                'labels' => array_column($packages, 'title'),
                'bookings' => array_column($packages, 'bookings'),
                'revenue' => array_column($packages, 'revenue'),
            ],
            'occupancy' => [
                'labels' => array_column($occupancy, 'title'),
                'values' => array_column($occupancy, 'occupancy_rate'),
            ],
            'users' => [
                'labels' => array_column($users, 'label'),
                'values' => array_column($users, 'total'),
            ],
            'ratings' => [
                'labels' => array_keys($ratings),
                'values' => array_values($ratings),
            ],
        ];
    }

    /**
     * Flatten a report into labelled CSV/print sections.
     *
     * @param  array<string, mixed>  $report
     * @return array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, mixed>>}>
     */
    public function exportSections(array $report): array
    {
        $range = $report['range'];
        $cards = $report['cards'];
        $sections = [];

        $sections[] = [
            'title' => sprintf(
                'Travelo report %s to %s (%s)',
                $range['start'],
                $range['end'],
                $range['period']
            ),
            'headers' => ['Metric', 'Value'],
            'rows' => [
                ['Gross revenue (paid)', $this->money($cards['revenue_gross'])],
                ['Refunds (refunded)', $this->money($cards['revenue_refunds'])],
                ['Net revenue', $this->money($cards['revenue_net'])],
                ['Bookings', $cards['bookings']],
                ['Cancelled bookings', $cards['cancelled']],
                ['Cancellation rate', $cards['cancellation_rate'].'%'],
                ['New travellers', $cards['new_users']],
                ['Average rating (published)', $cards['average_rating']],
                ['Published reviews', $cards['review_count']],
                ['Occupancy', $cards['occupancy_rate'].'%'],
                ['Seats filled', $cards['occupancy_booked']],
                ['Seat capacity', $cards['occupancy_capacity']],
            ],
        ];

        $sections[] = [
            'title' => 'Revenue by period',
            'headers' => ['Period', 'Gross revenue', 'Refunds', 'Net revenue'],
            'rows' => array_map(fn ($row) => [
                $row['label'],
                $this->money($row['gross']),
                $this->money($row['refunds']),
                $this->money($row['net']),
            ], $report['revenue']['series']),
        ];

        $sections[] = [
            'title' => 'Bookings by status',
            'headers' => ['Status', 'Bookings'],
            'rows' => array_map(
                fn ($status, $total) => [ucfirst((string) $status), $total],
                array_keys($report['bookings']['by_status']),
                array_values($report['bookings']['by_status'])
            ),
        ];

        $sections[] = [
            'title' => 'Booking trend',
            'headers' => ['Period', 'Bookings', 'Paid', 'Cancelled'],
            'rows' => array_map(fn ($row) => [
                $row['label'],
                $row['total'],
                $row['paid'],
                $row['cancelled'],
            ], $report['bookings']['series']),
        ];

        $sections[] = [
            'title' => 'Top packages',
            'headers' => ['Rank', 'Package', 'Destination', 'Bookings', 'Travellers', 'Revenue'],
            'rows' => array_values(array_map(
                fn ($index, $row) => [
                    $index + 1,
                    $row['title'],
                    $row['destination'],
                    $row['bookings'],
                    $row['people'],
                    $this->money($row['revenue']),
                ],
                array_keys($report['top_packages']),
                $report['top_packages']
            )),
        ];

        $sections[] = [
            'title' => 'Top destinations',
            'headers' => ['Rank', 'Destination', 'Bookings', 'Travellers', 'Revenue'],
            'rows' => array_values(array_map(
                fn ($index, $row) => [
                    $index + 1,
                    $row['name'],
                    $row['bookings'],
                    $row['people'],
                    $this->money($row['revenue']),
                ],
                array_keys($report['top_destinations']),
                $report['top_destinations']
            )),
        ];

        $sections[] = [
            'title' => 'Occupancy by package',
            'headers' => ['Package', 'Seats filled', 'Capacity', 'Occupancy'],
            'rows' => array_map(fn ($row) => [
                $row['title'],
                $row['booked'],
                $row['capacity'],
                $row['occupancy_rate'].'%',
            ], $report['occupancy']['packages']),
        ];

        $cancellations = $report['cancellations'];
        $refundRows = [
            ['Cancellation rate', $cancellations['rate'].'%'],
            ['Cancelled bookings', $cancellations['cancelled']],
        ];

        foreach ($cancellations['request_status'] as $status => $total) {
            $refundRows[] = ['Cancellation request: '.$status, $total];
        }

        foreach ($cancellations['refund_status'] as $status => $total) {
            $refundRows[] = ['Refund status: '.$status, $total];
        }

        $refundRows[] = ['Refunded amount', $this->money($cancellations['refunded_amount'])];

        $sections[] = [
            'title' => 'Cancellations and refunds',
            'headers' => ['Metric', 'Value'],
            'rows' => $refundRows,
        ];

        if (! empty($cancellations['reasons'])) {
            $sections[] = [
                'title' => 'Top cancellation reasons',
                'headers' => ['Reason', 'Requests'],
                'rows' => array_map(
                    fn ($row) => [$row['reason'], $row['total']],
                    $cancellations['reasons']
                ),
            ];
        }

        $sections[] = [
            'title' => 'New travellers',
            'headers' => ['Period', 'New travellers'],
            'rows' => array_map(fn ($row) => [$row['label'], $row['total']], $report['users']['series']),
        ];

        $sections[] = [
            'title' => 'Ratings',
            'headers' => ['Rating', 'Published reviews'],
            'rows' => array_map(
                fn ($rating, $total) => ['Average: '.$rating.' stars', $total],
                array_keys($report['ratings']['distribution']),
                array_values($report['ratings']['distribution'])
            ),
        ];

        return $sections;
    }

    // ---------- internals ----------

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function normaliseRange(CarbonInterface $start, CarbonInterface $end): array
    {
        $start = CarbonImmutable::instance($start)->startOfDay();
        $end = CarbonImmutable::instance($end)->endOfDay();

        if ($end->lessThan($start)) {
            // A backwards filter must still produce a usable, inclusive range
            // instead of silently querying nothing.
            $swappedStart = CarbonImmutable::instance($end)->startOfDay();
            $swappedEnd = CarbonImmutable::instance($start)->endOfDay();
            $start = $swappedStart;
            $end = $swappedEnd;
        }

        $maxDays = max(1, (int) config('travelo.reports.max_range_days', 366));

        if ($start->diffInDays($end) + 1 > $maxDays) {
            $start = $end->subDays($maxDays - 1)->startOfDay();
        }

        return [$start, $end];
    }

    private function normalisePeriod(string $period): string
    {
        return in_array($period, self::PERIODS, true) ? $period : self::PERIOD_DAY;
    }

    /**
     * @param  Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @return array<string, float>
     */
    private function dailyMap($query): array
    {
        $out = [];

        foreach ($query->pluck('total', 'bucket') as $bucket => $total) {
            if ($bucket === null) {
                continue;
            }

            $out[CarbonImmutable::parse($bucket)->toDateString()] = (float) $total;
        }

        return $out;
    }

    /**
     * Roll daily values up to day/week/month buckets, filling empty buckets
     * with zero so charts never hide a quiet period.
     *
     * @param  array<string, float>  $daily
     * @return array<string, float>
     */
    private function bucketSeries(array $daily, CarbonInterface $start, CarbonInterface $end, string $period): array
    {
        $buckets = [];
        $cursor = $this->bucketStart(CarbonImmutable::instance($start), $period);
        $last = $end;

        while ($cursor->lessThanOrEqualTo($last)) {
            $buckets[$cursor->toDateString()] = 0.0;
            $cursor = $this->nextBucket($cursor, $period);
        }

        foreach ($daily as $date => $value) {
            $key = $this->bucketKey($date, $period);

            if (array_key_exists($key, $buckets)) {
                $buckets[$key] += (float) $value;
            }
        }

        return $buckets;
    }

    private function bucketKey(string $date, string $period): string
    {
        return $this->bucketStart(CarbonImmutable::parse($date), $period)->toDateString();
    }

    private function bucketStart(CarbonImmutable $date, string $period): CarbonImmutable
    {
        return match ($period) {
            self::PERIOD_WEEK => $date->startOfWeek(CarbonInterface::MONDAY),
            self::PERIOD_MONTH => $date->startOfMonth(),
            default => $date->startOfDay(),
        };
    }

    private function nextBucket(CarbonImmutable $cursor, string $period): CarbonImmutable
    {
        return match ($period) {
            self::PERIOD_WEEK => $cursor->addWeek(),
            self::PERIOD_MONTH => $cursor->addMonth(),
            default => $cursor->addDay(),
        };
    }

    private function formatBucketLabel(string $date, string $period): string
    {
        $bucket = CarbonImmutable::parse($date);

        return match ($period) {
            self::PERIOD_WEEK => 'Week of '.$bucket->format('d M Y'),
            self::PERIOD_MONTH => $bucket->format('M Y'),
            default => $bucket->format('d M'),
        };
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
