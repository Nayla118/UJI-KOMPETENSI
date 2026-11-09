<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin-only analytics. Access is enforced by the `role:admin` route group:
 * scanner staff and travellers must never see company-wide revenue.
 */
class ReportController extends Controller
{
    public function __construct(
        private ReportService $reports,
        private ExportService $exports
    ) {}

    public function index(Request $request): View
    {
        [$start, $end, $period] = $this->filters($request);
        $report = $this->reports->summary($start, $end, $period);

        return view('admin.reports.index', [
            'report' => $report,
            'chartData' => $this->reports->chartPayload($report),
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'period' => $period,
        ]);
    }

    /**
     * JSON feed for client-side charts. Kept admin-only like the page; the
     * payload is also embedded in the view so the initial render needs no
     * extra request.
     */
    public function data(Request $request): JsonResponse
    {
        [$start, $end, $period] = $this->filters($request);
        $report = $this->reports->summary($start, $end, $period);

        return response()->json([
            'success' => true,
            'data' => $report,
            'charts' => $this->reports->chartPayload($report),
        ]);
    }

    /**
     * CSV (required) and print-ready PDF export.
     *
     * The project has no PDF renderer dependency; like the existing booking /
     * user exports, "pdf" returns a clean printable HTML document that the
     * browser turns into a PDF via Print → Save as PDF. This avoids adding a
     * heavy package and keeps the export dependency-free. It is documented in
     * the reports page, README and DOKUMENTASI.
     */
    public function export(Request $request): Response
    {
        $validated = $request->validate([
            'format' => ['nullable', Rule::in(['csv', 'pdf'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'period' => ['nullable', Rule::in(ReportService::PERIODS)],
        ]);

        [$start, $end, $period] = $this->filters($request);
        // Exports bypass the report cache: an operator exporting a report
        // expects the numbers that exist right now, not a 5-minute-old copy.
        $report = $this->reports->buildSummary($start, $end, $period);
        $sections = $this->reports->exportSections($report);

        $filename = sprintf(
            'travelo-report_%s_%s',
            $report['range']['start'],
            $report['range']['end']
        );

        if (($validated['format'] ?? 'csv') === 'pdf') {
            return response(
                view('admin.reports.print', [
                    'report' => $report,
                    'sections' => $sections,
                ])->render()
            )
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Content-Disposition', 'inline; filename="'.$filename.'.html"');
        }

        return response($this->exports->sectionsToCsv($sections))
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'.csv"');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'period' => ['nullable', Rule::in(ReportService::PERIODS)],
        ]);

        $timezone = config('app.timezone');

        $end = ! empty($validated['end_date'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['end_date'], $timezone)
            : CarbonImmutable::now($timezone)->endOfDay();

        $start = ! empty($validated['start_date'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['start_date'], $timezone)
            : $end->subDays(29)->startOfDay();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end->startOfDay(), $start];
        }

        return [
            $start->startOfDay(),
            $end->endOfDay(),
            $validated['period'] ?? ReportService::PERIOD_DAY,
        ];
    }
}
