<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\TourPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OwnerController extends Controller
{
    /**
     * Owner dashboard: only destinations, packages, bookings owned by this user.
     */
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        // Gate staff have no dashboard: /owner takes them straight to the
        // scanner, and every other portal route lacks their permissions.
        if ($user->isGateStaff()) {
            return redirect()->route('owner.scanner');
        }

        $destinations = $user->destinations()
            ->withCount('tourPackages')
            ->latest()
            ->get();

        $destinationIds = $destinations->pluck('id');

        $packages = TourPackage::whereIn('destination_id', $destinationIds)
            ->with('destination')
            ->latest()
            ->take(5)
            ->get();

        $bookings = Booking::whereIn('tour_package_id', function ($q) use ($destinationIds) {
            $q->select('id')->from('tour_packages')->whereIn('destination_id', $destinationIds);
        })
            ->with(['tourPackage.destination', 'user'])
            ->latest()
            ->take(5)
            ->get();

        $pendingRequests = BookingChangeRequest::whereIn('booking_id', function ($q) use ($destinationIds) {
            $q->select('b.id')
                ->from('bookings as b')
                ->join('tour_packages as tp', 'b.tour_package_id', '=', 'tp.id')
                ->whereIn('tp.destination_id', $destinationIds);
        })
            ->where('status', 'pending')
            ->with('booking.tourPackage.destination')
            ->latest()
            ->take(5)
            ->get();

        return view('owner.dashboard', [
            'destinations' => $destinations,
            'packages' => $packages,
            'bookings' => $bookings,
            'pendingRequests' => $pendingRequests,
            'destinationCount' => $destinations->count(),
            'packageCount' => $packages->count(),
            'bookingCount' => $bookings->count(),
            'pendingCount' => $pendingRequests->count(),
        ]);
    }
}
