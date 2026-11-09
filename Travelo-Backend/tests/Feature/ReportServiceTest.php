<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\Destination;
use App\Models\Payment;
use App\Models\Review;
use App\Models\TourPackage;
use App\Models\TourPackageDateAvailability;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Analytics contract for the admin dashboard and reports page.
 *
 * The fixtures are fully controlled so every number below can be checked by
 * hand:
 *
 *   range: 2026-03-01 .. 2026-03-04 (4 days, Asia/Jakarta)
 *
 *   P1 "Bali Highlights" (max 50/day, one closed date, one 80-seat override)
 *     paid confirmed   4 people  5,000,000  paid 03-01
 *     paid completed   2 people  2,500,000  paid 03-02
 *   P2 "Yogyakarta Heritage" (max 20/day)
 *     pending          3 people  9,000,000  (never revenue)
 *     failed           1 person   1,000,000  (never revenue)
 *     cancelled+paid   2 people  1,000,000  paid 03-01, refunded 03-03
 *
 *   gross revenue 8,500,000 · refunds 1,000,000 · net 7,500,000
 *   top package P1: 2 paid bookings, 7,500,000
 *   occupancy: P1 6/180, P2 3/80, overall 9/260
 *   cancellation rate 1/5 = 20.0%
 *   new travellers 2 (a third one falls outside the range)
 *   published ratings 5 and 3 -> average 4.00 (a hidden 1 is ignored)
 */
class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $reports;

    private CarbonImmutable $start;

    private CarbonImmutable $end;

    private User $admin;

    private User $owner;

    private User $travellerA;

    private User $travellerB;

    private User $travellerC;

    private Destination $bali;

    private Destination $yogyakarta;

    private TourPackage $baliPackage;

    private TourPackage $yogyakartaPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reports = app(ReportService::class);
        $this->start = CarbonImmutable::parse('2026-03-01', config('app.timezone'));
        $this->end = CarbonImmutable::parse('2026-03-04', config('app.timezone'))->endOfDay();

        $this->admin = $this->makeUser('Report Admin', User::ROLE_ADMIN);
        $this->owner = $this->makeUser('Report Owner', User::ROLE_OWNER);
        $this->travellerA = $this->makeUser('Traveller A', User::ROLE_TRAVELLER);
        $this->travellerB = $this->makeUser('Traveller B', User::ROLE_TRAVELLER);
        $this->travellerC = $this->makeUser('Traveller C', User::ROLE_TRAVELLER);

        $this->stamp($this->travellerA, 'created_at', '2026-03-01 09:00:00');
        $this->stamp($this->travellerB, 'created_at', '2026-03-02 09:00:00');
        // Outside the report range: must not be counted as a "new traveller".
        $this->stamp($this->travellerC, 'created_at', '2026-02-01 09:00:00');

        $this->bali = Destination::create([
            'name' => 'Bali',
            'city' => 'Denpasar',
            'country' => 'Indonesia',
            'description' => 'Island destination',
            'rating' => 4.5,
        ]);

        $this->yogyakarta = Destination::create([
            'name' => 'Yogyakarta',
            'city' => 'Yogyakarta',
            'country' => 'Indonesia',
            'description' => 'Heritage destination',
            'rating' => 4.2,
        ]);

        $this->baliPackage = TourPackage::create([
            'destination_id' => $this->bali->id,
            'title' => 'Bali Highlights',
            'price' => 1250000,
            'duration_days' => 3,
            'max_people' => 50,
        ]);

        $this->yogyakartaPackage = TourPackage::create([
            'destination_id' => $this->yogyakarta->id,
            'title' => 'Yogyakarta Heritage',
            'price' => 1000000,
            'duration_days' => 2,
            'max_people' => 20,
        ]);

        // Reuse the availability rules: 50 seats/day, one closed date (-50)
        // and one 80-seat date (+30) => 180 seats across the four days.
        TourPackageDateAvailability::create([
            'tour_package_id' => $this->baliPackage->id,
            'date' => '2026-03-02',
            'capacity' => null,
            'is_closed' => true,
            'note' => 'Maintenance',
        ]);

        TourPackageDateAvailability::create([
            'tour_package_id' => $this->baliPackage->id,
            'date' => '2026-03-03',
            'capacity' => 80,
            'is_closed' => false,
            'note' => 'Extra guides',
        ]);

        $paidOne = $this->makeBooking($this->travellerA, $this->baliPackage, [
            'booking_date' => '2026-03-03',
            'people_count' => 4,
            'total_price' => 5000000,
            'payment_status' => Booking::SUCCESSFUL_PAYMENT_STATUS,
            'booking_status' => 'confirmed',
        ], '2026-03-01 10:00:00');

        $this->pay($paidOne, 5000000, '2026-03-01 10:05:00');

        $paidTwo = $this->makeBooking($this->travellerB, $this->baliPackage, [
            'booking_date' => '2026-03-02',
            'people_count' => 2,
            'total_price' => 2500000,
            'payment_status' => Booking::SUCCESSFUL_PAYMENT_STATUS,
            'booking_status' => 'completed',
        ], '2026-03-02 10:00:00');

        $this->pay($paidTwo, 2500000, '2026-03-02 10:15:00');

        // Pending payment: counted as a booking, never as revenue.
        $pending = $this->makeBooking($this->travellerB, $this->yogyakartaPackage, [
            'booking_date' => '2026-03-03',
            'people_count' => 3,
            'total_price' => 9000000,
            'payment_status' => Payment::STATUS_PENDING,
            'booking_status' => 'pending',
        ], '2026-03-02 12:00:00');

        // A failed payment is excluded from revenue and releases its seats.
        $failed = $this->makeBooking($this->travellerC, $this->yogyakartaPackage, [
            'booking_date' => '2026-03-03',
            'people_count' => 1,
            'total_price' => 1000000,
            'payment_status' => Payment::STATUS_FAILED,
            'booking_status' => 'pending',
        ], '2026-03-03 08:00:00');

        Payment::create([
            'booking_id' => $failed->id,
            'midtrans_order_id' => 'REPORT-FAILED-'.$failed->id,
            'payment_status' => Payment::STATUS_FAILED,
            'payment_method' => 'qris',
            'amount' => 1000000,
            'transaction_status' => 'deny',
        ]);

        // Paid, later cancelled and manually refunded. Gross keeps the money
        // that was collected; the refund is reported on its own date.
        $refunded = $this->makeBooking($this->travellerC, $this->yogyakartaPackage, [
            'booking_date' => '2026-03-04',
            'people_count' => 2,
            'total_price' => 1000000,
            'payment_status' => Booking::SUCCESSFUL_PAYMENT_STATUS,
            'booking_status' => 'cancelled',
        ], '2026-03-01 11:00:00');

        $this->pay($refunded, 1000000, '2026-03-01 11:05:00');

        $request = BookingChangeRequest::create([
            'booking_id' => $refunded->id,
            'user_id' => $this->travellerC->id,
            'type' => BookingChangeRequest::TYPE_CANCELLATION,
            'status' => BookingChangeRequest::STATUS_APPROVED,
            'original_booking_date' => '2026-03-04',
            'reason' => 'Flight cancelled',
            'refund_status' => BookingChangeRequest::REFUND_REFUNDED,
            'refund_percentage' => 100,
            'refund_amount' => 1000000,
            'refunded_at' => '2026-03-03 12:00:00',
        ]);

        $this->stamp($request, 'created_at', '2026-03-02 12:30:00');

        // Published reviews feed the rating average; the hidden one must not.
        $reviewOne = Review::create([
            'booking_id' => $paidOne->id,
            'user_id' => $this->travellerA->id,
            'destination_id' => $this->bali->id,
            'rating' => 5,
            'comment' => 'Excellent trip',
            'status' => Review::STATUS_PUBLISHED,
        ]);

        $reviewTwo = Review::create([
            'booking_id' => $paidTwo->id,
            'user_id' => $this->travellerB->id,
            'destination_id' => $this->bali->id,
            'rating' => 3,
            'comment' => 'Good, but busy',
            'status' => Review::STATUS_PUBLISHED,
        ]);

        $hidden = Review::create([
            'booking_id' => $pending->id,
            'user_id' => $this->travellerB->id,
            'destination_id' => $this->yogyakarta->id,
            'rating' => 1,
            'comment' => 'Held for moderation',
            'status' => Review::STATUS_HIDDEN,
        ]);

        $this->stamp($reviewOne, 'created_at', '2026-03-03 09:00:00');
        $this->stamp($reviewTwo, 'created_at', '2026-03-04 09:00:00');
        $this->stamp($hidden, 'created_at', '2026-03-04 10:00:00');
    }

    // ---------- Revenue ----------

    public function test_revenue_counts_paid_payments_only_and_reports_refunds_separately(): void
    {
        $revenue = $this->reports->revenue($this->start, $this->end, ReportService::PERIOD_DAY);

        $this->assertEqualsWithDelta(8500000, $revenue['gross'], 0.01);
        $this->assertEqualsWithDelta(1000000, $revenue['refunds'], 0.01);
        $this->assertEqualsWithDelta(7500000, $revenue['net'], 0.01);
        $this->assertSame('paid_at', $revenue['basis']);
        $this->assertSame('refunded_at', $revenue['refund_basis']);

        // Pending (9,000,000) and failed (1,000,000) payments are excluded:
        // only the three paid bookings make up the gross total.
        $gross = array_sum(array_column($revenue['series'], 'gross'));
        $this->assertEqualsWithDelta(8500000, $gross, 0.01);

        $byDate = collect($revenue['series'])->keyBy('date');

        $this->assertEqualsWithDelta(6000000, $byDate['2026-03-01']['gross'], 0.01);
        $this->assertEqualsWithDelta(2500000, $byDate['2026-03-02']['gross'], 0.01);
        $this->assertEqualsWithDelta(0, $byDate['2026-03-03']['gross'], 0.01);

        // The refund appears on its own date and is never back-dated into the
        // original sale day.
        $this->assertEqualsWithDelta(0, $byDate['2026-03-01']['refunds'], 0.01);
        $this->assertEqualsWithDelta(1000000, $byDate['2026-03-03']['refunds'], 0.01);
        $this->assertEqualsWithDelta(-1000000, $byDate['2026-03-03']['net'], 0.01);

        // Empty days stay in the series so charts never hide a quiet period.
        $this->assertCount(4, $revenue['series']);
    }

    public function test_revenue_can_be_aggregated_by_month(): void
    {
        $revenue = $this->reports->revenue($this->start, $this->end, ReportService::PERIOD_MONTH);

        $this->assertCount(1, $revenue['series']);
        $this->assertSame('2026-03-01', $revenue['series'][0]['date']);
        $this->assertSame('Mar 2026', $revenue['series'][0]['label']);
        $this->assertEqualsWithDelta(8500000, $revenue['series'][0]['gross'], 0.01);
        $this->assertEqualsWithDelta(1000000, $revenue['series'][0]['refunds'], 0.01);
        $this->assertEqualsWithDelta(7500000, $revenue['series'][0]['net'], 0.01);
    }

    // ---------- Bookings ----------

    public function test_bookings_are_counted_by_status_and_trend(): void
    {
        $bookings = $this->reports->bookings($this->start, $this->end, ReportService::PERIOD_DAY);

        $this->assertSame(5, $bookings['total']);
        $this->assertSame([
            'pending' => 2,
            'confirmed' => 1,
            'cancelled' => 1,
            'completed' => 1,
        ], $bookings['by_status']);

        $this->assertCount(4, $bookings['series']);
        $this->assertSame(5, array_sum(array_column($bookings['series'], 'total')));
        $this->assertSame(3, array_sum(array_column($bookings['series'], 'paid')));
        $this->assertSame(1, array_sum(array_column($bookings['series'], 'cancelled')));

        $byDate = collect($bookings['series'])->keyBy('date');
        $this->assertSame(2, $byDate['2026-03-01']['total']);
        $this->assertSame(2, $byDate['2026-03-01']['paid']);
        $this->assertSame(1, $byDate['2026-03-01']['cancelled']);
        $this->assertSame(2, $byDate['2026-03-02']['total']);
        $this->assertSame(1, $byDate['2026-03-03']['total']);
        $this->assertSame(0, $byDate['2026-03-04']['total']);
    }

    public function test_top_packages_and_destinations_exclude_unpaid_and_cancelled_bookings(): void
    {
        $packages = $this->reports->topPackages($this->start, $this->end);
        $destinations = $this->reports->topDestinations($this->start, $this->end);

        $this->assertCount(1, $packages);
        $this->assertSame($this->baliPackage->id, $packages[0]['id']);
        $this->assertSame('Bali Highlights', $packages[0]['title']);
        $this->assertSame(2, $packages[0]['bookings']);
        $this->assertSame(6, $packages[0]['people']);
        $this->assertEqualsWithDelta(7500000, $packages[0]['revenue'], 0.01);

        // Yogyakarta has a pending, a failed and a cancelled booking: none of
        // them is a sold package.
        $this->assertCount(1, $destinations);
        $this->assertSame('Bali', $destinations[0]['name']);
        $this->assertSame(2, $destinations[0]['bookings']);
        $this->assertEqualsWithDelta(7500000, $destinations[0]['revenue'], 0.01);
    }

    // ---------- Occupancy ----------

    public function test_occupancy_reuses_the_availability_capacity_rules(): void
    {
        $occupancy = $this->reports->occupancy($this->start, $this->end);

        $this->assertSame(4, $occupancy['days']);

        $rows = collect($occupancy['packages'])->keyBy('id');

        // 50 seats x 4 days - 50 (closed) + 30 (80 instead of 50) = 180.
        $this->assertSame(180, $rows[$this->baliPackage->id]['capacity']);
        $this->assertSame(6, $rows[$this->baliPackage->id]['booked']);
        $this->assertEqualsWithDelta(3.3, $rows[$this->baliPackage->id]['occupancy_rate'], 0.05);

        // Only the pending booking holds seats on P2; the failed one released
        // them. 20 seats x 4 days = 80.
        $this->assertSame(80, $rows[$this->yogyakartaPackage->id]['capacity']);
        $this->assertSame(3, $rows[$this->yogyakartaPackage->id]['booked']);
        $this->assertEqualsWithDelta(3.8, $rows[$this->yogyakartaPackage->id]['occupancy_rate'], 0.05);

        $this->assertSame(260, $occupancy['overall_capacity']);
        $this->assertSame(9, $occupancy['overall_booked']);
        $this->assertEqualsWithDelta(3.5, $occupancy['overall_rate'], 0.05);

        // Sorted by occupancy, highest first.
        $this->assertSame($this->yogyakartaPackage->id, $occupancy['packages'][0]['id']);
    }

    // ---------- Cancellations, users and ratings ----------

    public function test_cancellation_rate_refund_breakdown_and_reasons(): void
    {
        $cancellations = $this->reports->cancellations($this->start, $this->end);

        $this->assertSame(5, $cancellations['total_bookings']);
        $this->assertSame(1, $cancellations['cancelled']);
        $this->assertEqualsWithDelta(20.0, $cancellations['rate'], 0.01);

        $this->assertSame(1, $cancellations['request_status'][BookingChangeRequest::STATUS_APPROVED]);
        $this->assertSame(0, $cancellations['request_status'][BookingChangeRequest::STATUS_PENDING]);
        $this->assertSame(1, $cancellations['refund_status'][BookingChangeRequest::REFUND_REFUNDED]);
        $this->assertSame(0, $cancellations['refund_status'][BookingChangeRequest::REFUND_PENDING]);
        $this->assertEqualsWithDelta(1000000, $cancellations['refunded_amount'], 0.01);

        $this->assertNotEmpty($cancellations['reasons']);
        $this->assertSame('Flight cancelled', $cancellations['reasons'][0]['reason']);
        $this->assertSame(1, $cancellations['reasons'][0]['total']);
    }

    public function test_net_revenue_uses_the_partial_refund_snapshot_not_the_booking_total(): void
    {
        $request = BookingChangeRequest::query()->firstOrFail();
        $request->update([
            'refund_percentage' => 50,
            'refund_amount' => 400000,
        ]);

        $revenue = $this->reports->revenue($this->start, $this->end, ReportService::PERIOD_DAY);

        // Gross is still the money collected. Only the snapshot amount is
        // subtracted, so a 50% refund does not turn into a full refund.
        $this->assertEqualsWithDelta(8500000, $revenue['gross'], 0.01);
        $this->assertEqualsWithDelta(400000, $revenue['refunds'], 0.01);
        $this->assertEqualsWithDelta(8100000, $revenue['net'], 0.01);

        $byDate = collect($revenue['series'])->keyBy('date');
        $this->assertEqualsWithDelta(400000, $byDate['2026-03-03']['refunds'], 0.01);
        $this->assertEqualsWithDelta(-400000, $byDate['2026-03-03']['net'], 0.01);

        $cancellations = $this->reports->cancellations($this->start, $this->end);
        $this->assertEqualsWithDelta(400000, $cancellations['refunded_amount'], 0.01);
    }

    public function test_new_travellers_and_published_ratings(): void
    {
        $users = $this->reports->newUsers($this->start, $this->end, ReportService::PERIOD_DAY);

        // Admin, owner and the traveller created outside the range are not
        // "new travellers".
        $this->assertSame(2, $users['total']);
        $this->assertSame(2, array_sum(array_column($users['series'], 'total')));
        $byDate = collect($users['series'])->keyBy('date');
        $this->assertSame(1, $byDate['2026-03-01']['total']);
        $this->assertSame(1, $byDate['2026-03-02']['total']);
        $this->assertSame(0, $byDate['2026-03-03']['total']);

        $ratings = $this->reports->ratings($this->start, $this->end);

        $this->assertSame(2, $ratings['count']);
        $this->assertEqualsWithDelta(4.0, $ratings['average'], 0.01);
        $this->assertSame(0, $ratings['distribution']['1']);
        $this->assertSame(1, $ratings['distribution']['3']);
        $this->assertSame(0, $ratings['distribution']['4']);
        $this->assertSame(1, $ratings['distribution']['5']);
    }

    // ---------- Summary, charts and cache ----------

    public function test_summary_composes_cards_and_chart_payload(): void
    {
        $report = $this->reports->summary($this->start, $this->end);
        $charts = $this->reports->chartPayload($report);

        $this->assertEqualsWithDelta(8500000, $report['cards']['revenue_gross'], 0.01);
        $this->assertEqualsWithDelta(1000000, $report['cards']['revenue_refunds'], 0.01);
        $this->assertEqualsWithDelta(7500000, $report['cards']['revenue_net'], 0.01);
        $this->assertSame(5, $report['cards']['bookings']);
        $this->assertEqualsWithDelta(20.0, $report['cards']['cancellation_rate'], 0.01);
        $this->assertSame(2, $report['cards']['new_users']);
        $this->assertEqualsWithDelta(4.0, $report['cards']['average_rating'], 0.01);

        $this->assertCount(4, $charts['revenue']['labels']);
        $this->assertEqualsWithDelta(8500000, array_sum($charts['revenue']['gross']), 0.01);
        $this->assertEqualsWithDelta(7500000, array_sum($charts['revenue']['net']), 0.01);
        $this->assertSame(5, array_sum($charts['bookings']['total']));
        $this->assertSame(['Pending', 'Confirmed', 'Cancelled', 'Completed'], $charts['status']['labels']);
        $this->assertSame([2, 1, 1, 1], $charts['status']['values']);
        $this->assertSame(['Bali Highlights'], $charts['topPackages']['labels']);
        $this->assertEqualsWithDelta(7500000, $charts['topPackages']['revenue'][0], 0.01);
        $this->assertSame([1, 2, 3, 4, 5], $charts['ratings']['labels']);
        $this->assertSame([0, 0, 1, 0, 1], $charts['ratings']['values']);
    }

    public function test_summary_is_cached_under_a_filter_specific_key(): void
    {
        $first = $this->reports->summary($this->start, $this->end, ReportService::PERIOD_DAY);
        $this->assertEqualsWithDelta(8500000, $first['revenue']['gross'], 0.01);

        // Mutate the data behind the service. The cached payload must stay
        // stable for the same filter...
        DB::table('bookings')->where('total_price', 5000000)->update(['total_price' => 6000000]);

        $cached = $this->reports->summary($this->start, $this->end, ReportService::PERIOD_DAY);
        $this->assertEqualsWithDelta(8500000, $cached['revenue']['gross'], 0.01);

        // ...but a different filter is a different cache key...
        $shorter = $this->reports->summary(
            CarbonImmutable::parse('2026-03-03', config('app.timezone')),
            $this->end,
            ReportService::PERIOD_DAY
        );
        $this->assertEqualsWithDelta(0, $shorter['revenue']['gross'], 0.01);

        // ...and clearing the cache reflects the current data.
        Cache::flush();

        $fresh = $this->reports->summary($this->start, $this->end, ReportService::PERIOD_DAY);
        $this->assertEqualsWithDelta(9500000, $fresh['revenue']['gross'], 0.01);
    }

    // ---------- HTTP surface ----------

    public function test_admin_can_open_the_reports_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.index', [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-04',
                'period' => 'day',
            ]))
            ->assertOk()
            ->assertSee('Export CSV')
            ->assertSee('Bali Highlights')
            ->assertSee('2026-03-01')
            ->assertSee('2026-03-04');
    }

    public function test_admin_can_open_the_dashboard_fed_by_the_same_service(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Revenue trend')
            ->assertSee('Booking trend')
            ->assertSee('Top packages');
    }

    public function test_admin_can_fetch_chart_data_as_json(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.reports.data', [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-04',
                'period' => 'day',
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.range.start', '2026-03-01')
            ->assertJsonPath('data.range.end', '2026-03-04')
            ->assertJsonPath('data.range.period', 'day')
            ->assertJsonStructure([
                'success',
                'data' => ['range', 'revenue', 'bookings', 'top_packages', 'occupancy', 'cancellations', 'users', 'ratings', 'cards'],
                'charts' => ['revenue', 'bookings', 'status', 'topPackages', 'occupancy', 'users', 'ratings'],
            ]);

        $this->assertEqualsWithDelta(
            8500000,
            $response->json('data.revenue.gross'),
            0.01
        );
        $this->assertCount(1, $response->json('data.top_packages'));
    }

    public function test_date_filters_narrow_every_aggregate(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.reports.data', [
                'start_date' => '2026-03-03',
                'end_date' => '2026-03-04',
                'period' => 'day',
            ]))
            ->assertOk()
            ->assertJsonPath('data.range.start', '2026-03-03')
            ->assertJsonPath('data.range.end', '2026-03-04');

        // No payment was collected in these two days, but the refund happened
        // on 03-03, so gross is zero and net is negative.
        $this->assertEqualsWithDelta(0, $response->json('data.revenue.gross'), 0.01);
        $this->assertEqualsWithDelta(1000000, $response->json('data.revenue.refunds'), 0.01);
        $this->assertEqualsWithDelta(-1000000, $response->json('data.revenue.net'), 0.01);

        // Only the failed payment booking was created on 03-03. The refunded
        // booking was created on 03-01, so it is outside the narrowed window.
        $this->assertSame(1, $response->json('data.bookings.total'));
        $this->assertEqualsWithDelta(0.0, $response->json('data.cancellations.rate'), 0.01);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.reports.data', ['period' => 'year']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('period');

        $this->actingAs($this->admin)
            ->getJson(route('admin.reports.data', ['start_date' => '01-03-2026']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('start_date');
    }

    public function test_admin_can_export_a_valid_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.export', [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-04',
                'period' => 'day',
                'format' => 'csv',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString(
            'attachment; filename="travelo-report_2026-03-01_2026-03-04.csv"',
            $response->headers->get('content-disposition')
        );

        $csv = $response->getContent();

        $this->assertIsString($csv);
        $this->assertStringContainsString('Travelo report 2026-03-01 to 2026-03-04 (day)', $csv);
        $this->assertStringContainsString('Gross revenue (paid)', $csv);
        $this->assertStringContainsString('8500000.00', $csv);
        $this->assertStringContainsString('Bali Highlights', $csv);
        $this->assertStringContainsString('Yogyakarta Heritage', $csv);

        $lines = array_values(array_filter(
            preg_split("/\r\n|\n|\r/", trim($csv)),
            fn ($line) => trim($line) !== ''
        ));

        $this->assertGreaterThan(30, count($lines));

        // Every non-blank line must parse as CSV with at least one column.
        foreach ($lines as $line) {
            $columns = str_getcsv($line);
            $this->assertNotEmpty($columns);
        }
    }

    public function test_admin_can_open_the_print_ready_pdf_export(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.export', [
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-04',
                'period' => 'day',
                'format' => 'pdf',
            ]))
            ->assertOk();

        $this->assertStringContainsString(
            'text/html',
            $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            'inline; filename="travelo-report_2026-03-01_2026-03-04.html"',
            $response->headers->get('content-disposition')
        );

        $response->assertSee('Travelo Analytics Report')
            ->assertSee('Revenue by period')
            ->assertSee('Bali Highlights');

        $this->assertStringContainsString('<table>', $response->getContent());
    }

    public function test_owner_and_travellers_cannot_access_analytics(): void
    {
        foreach ([$this->owner, $this->travellerA] as $user) {
            $this->actingAs($user)
                ->get(route('admin.reports.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->getJson(route('admin.reports.data'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('admin.reports.export', ['format' => 'csv']))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('admin.dashboard'))
                ->assertForbidden();
        }
    }

    // ---------- helpers ----------

    private function makeUser(string $name, string $role): User
    {
        return User::create([
            'firebase_uid' => 'uid-'.strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'-'.uniqid().'@example.com',
            'role' => $role,
        ]);
    }

    private function makeBooking(User $user, TourPackage $package, array $attributes, string $createdAt): Booking
    {
        $booking = Booking::create(array_merge([
            'user_id' => $user->id,
            'tour_package_id' => $package->id,
            'booking_date' => '2026-03-01',
            'people_count' => 1,
            'total_price' => 1000000,
            'payment_status' => Payment::STATUS_PENDING,
            'booking_status' => 'pending',
        ], $attributes));

        $this->stamp($booking, 'created_at', $createdAt);

        return $booking;
    }

    private function pay(Booking $booking, float $amount, string $paidAt): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'midtrans_order_id' => 'REPORT-ORDER-'.$booking->id,
            'payment_status' => Payment::STATUS_PAID,
            'payment_method' => 'qris',
            'amount' => $amount,
            'paid_at' => $paidAt,
            'transaction_status' => 'settlement',
        ]);
    }

    /**
     * Model timestamps are not mass assignable. Writing the raw column keeps
     * the fixture deterministic without triggering observers.
     */
    private function stamp(object $model, string $column, string $value): void
    {
        DB::table($model->getTable())
            ->where('id', $model->getKey())
            ->update([$column => $value]);
    }
}
