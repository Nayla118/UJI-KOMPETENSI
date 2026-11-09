<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $destinationIds = $user->destinations->pluck('id');

        $query = Booking::whereIn('tour_package_id', function ($q) use ($destinationIds) {
            $q->select('id')->from('tour_packages')->whereIn('destination_id', $destinationIds);
        });

        $date = $request->date ? CarbonImmutable::parse($request->date) : CarbonImmutable::today();

        $todayBookings = $query->clone()->whereDate('booking_date', $date)->get();
        $todayCheckedIn = $todayBookings->where('booking_status', 'confirmed')->count();
        $todayTickets = $todayBookings->sum('people_count');
        $revenueTotal = (int) $query->clone()->where('payment_status', 'paid')->sum('total_price');

        // 14-day trend
        $fourteenDays = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = $date->copy()->subDays($i);
            $count = Booking::whereIn('tour_package_id', function ($q) use ($destinationIds, $d) {
                $q->select('id')->from('tour_packages')
                    ->whereIn('destination_id', $destinationIds)
                    ->whereDate('booking_date', $d);
            })->sum('people_count');

            $fourteenDays[] = [
                'date' => $d->format('d M'),
                'count' => (int) $count,
            ];
        }

        // Per-destination summary
        $destSummary = [];
        foreach ($user->destinations as $dest) {
            $pkgIds = $dest->tourPackages->pluck('id');
            $totalBookings = Booking::whereIn('tour_package_id', $pkgIds)->count();
            $usedTickets = Booking::whereIn('tour_package_id', $pkgIds)
                ->where('booking_status', 'confirmed')->sum('people_count');
            $revenue = (int) Booking::whereIn('tour_package_id', $pkgIds)
                ->where('payment_status', 'paid')->sum('total_price');

            $destSummary[] = [
                'name' => $dest->name,
                'bookings' => (int) $totalBookings,
                'checked_in' => (int) $usedTickets,
                'revenue' => $revenue,
            ];
        }

        return view('owner.laporan', [
            'date' => $date,
            'todayBookings' => $todayBookings,
            'todayCheckedIn' => $todayCheckedIn,
            'todayTickets' => (int) $todayTickets,
            'revenueTotal' => $revenueTotal,
            'showRevenue' => $user->hasPermission('reports.revenue'),
            'fourteenDays' => $fourteenDays,
            'destSummary' => $destSummary,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = auth()->user();
        $destinationIds = $user->destinations->pluck('id');

        $query = Booking::whereIn('tour_package_id', function ($q) use ($destinationIds) {
            $q->select('id')->from('tour_packages')->whereIn('destination_id', $destinationIds);
        })
            ->with(['tourPackage.destination', 'user']);

        if ($request->has('date_from')) {
            $query->whereDate('booking_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('booking_date', '<=', $request->date_to);
        }

        $bookings = $query->get();

        $filename = 'travelo_checkin_report_'.CarbonImmutable::now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($bookings) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Kode Booking',
                'Traveller',
                'Paket',
                'Destinasi',
                'Tanggal Kunjungan',
                'Jumlah Orang',
                'Status Booking',
                'Total (IDR)',
            ]);

            foreach ($bookings as $b) {
                fputcsv($handle, [
                    'TRX-'.$b->id,
                    $b->user?->name ?? '-',
                    $b->tourPackage?->title ?? '-',
                    $b->tourPackage->destination?->name ?? '-',
                    $b->booking_date?->format('d M Y') ?? '-',
                    $b->people_count,
                    ucfirst($b->booking_status),
                    number_format((int) $b->total_price, 0, ',', '.'),
                ]);
            }

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
