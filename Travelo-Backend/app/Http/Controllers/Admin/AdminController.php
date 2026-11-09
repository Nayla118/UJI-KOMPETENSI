<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(private ReportService $reports) {}

    /**
     * Dashboard summary. Uses the same service as the full reports page so a
     * number can never disagree between the two screens. The default window is
     * the last 30 days; the reports page exposes the date/period filters.
     */
    public function index(): View
    {
        $end = CarbonImmutable::now(config('app.timezone'))->endOfDay();
        $start = $end->subDays(29)->startOfDay();

        $report = $this->reports->summary($start, $end, ReportService::PERIOD_DAY);

        $recentBookings = Booking::with(['user', 'tourPackage.destination'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', [
            'report' => $report,
            'chartData' => $this->reports->chartPayload($report),
            'recentBookings' => $recentBookings,
        ]);
    }
}
