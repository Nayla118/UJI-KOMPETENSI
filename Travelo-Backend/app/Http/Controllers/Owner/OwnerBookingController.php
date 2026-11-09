<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerBookingController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $destinationIds = $user->destinations->pluck('id');

        $query = Booking::whereIn('tour_package_id', function ($q) use ($destinationIds) {
            $q->select('id')->from('tour_packages')->whereIn('destination_id', $destinationIds);
        })->with(['tourPackage.destination', 'user']);

        if ($request->has('status') && $request->status) {
            $query->where('booking_status', $request->status);
        }

        $bookings = $query->latest()->paginate(15)->appends($request->query());

        return view('owner.bookings.index', compact('bookings'));
    }

    public function show(Booking $booking): View
    {
        $this->authorizeBooking($booking);

        $booking->load([
            'tourPackage.destination',
            'user',
            'payment',
            'visitors',
            'latestChangeRequest',
        ]);

        return view('owner.bookings.show', compact('booking'));
    }

    private function authorizeBooking(Booking $booking): void
    {
        $user = auth()->user();
        $destinationId = $booking->tourPackage?->destination_id;
        if ($destinationId === null || (! $user->isAdmin() && ! $user->destinations()->whereKey($destinationId)->exists())) {
            abort(403, 'Anda tidak memiliki akses ke booking ini.');
        }
    }
}
