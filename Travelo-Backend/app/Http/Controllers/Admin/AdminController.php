<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\TourPackage;
use Illuminate\View\View;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Dashboard index
     */
    public function index(): View
    {
        // Current stats
        $totalDestinations = Destination::count();
        $totalTourPackages = TourPackage::count();
        $totalBookings = Booking::count();
        $totalRevenue = Booking::where('payment_status', 'paid')->sum('total_price');

        // Last month stats for comparison
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $lastMonthDestinations = Destination::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $lastMonthTourPackages = TourPackage::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $lastMonthBookings = Booking::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $lastMonthRevenue = Booking::where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('total_price');

        // Calculate growth percentages
        $destinationGrowth = $lastMonthDestinations > 0 ? round(($totalDestinations - $lastMonthDestinations) / $lastMonthDestinations * 100) : 0;
        $tourPackageGrowth = $lastMonthTourPackages > 0 ? round(($totalTourPackages - $lastMonthTourPackages) / $lastMonthTourPackages * 100) : 0;
        $bookingGrowth = $lastMonthBookings > 0 ? round(($totalBookings - $lastMonthBookings) / $lastMonthBookings * 100) : 0;
        $revenueGrowth = $lastMonthRevenue > 0 ? round(($totalRevenue - $lastMonthRevenue) / $lastMonthRevenue * 100) : 0;

        // Popular destinations (by booking count)
        $popularDestinations = Destination::withCount(['tourPackages' => function ($query) {
            $query->whereHas('bookings');
        }])
            ->with(['tourPackages.bookings'])
            ->get()
            ->map(function ($destination) {
                $destination->total_bookings = $destination->tourPackages->sum(function ($tp) {
                    return $tp->bookings->count();
                });
                return $destination;
            })
            ->sortByDesc('total_bookings')
            ->take(4);

        // Top destination for hero section
        $topDestination = $popularDestinations->first();

        // Revenue overview data (last 6 months)
        $revenueData = [];
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();
            $months[] = $monthStart->format('M');
            $revenueData[] = Booking::where('payment_status', 'paid')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('total_price');
        }

        // Recent bookings
        $recentBookings = Booking::with(['user', 'tourPackage.destination'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalDestinations',
            'totalTourPackages',
            'totalBookings',
            'totalRevenue',
            'destinationGrowth',
            'tourPackageGrowth',
            'bookingGrowth',
            'revenueGrowth',
            'popularDestinations',
            'topDestination',
            'revenueData',
            'months',
            'recentBookings'
        ));
    }
}
