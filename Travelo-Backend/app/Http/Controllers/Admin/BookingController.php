<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TourPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    /**
     * Display bookings list
     */
    public function index(Request $request): View
    {
        $query = Booking::with(['user', 'tourPackage.destination']);

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('booking_status', $request->status);
        }

        // Filter by payment status
        if ($request->has('payment_status') && $request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                    ->orWhereHas('tourPackage', function ($tq) use ($search) {
                        $tq->where('title', 'like', "%{$search}%");
                    })
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $bookings = $query->latest()->paginate(10)->appends($request->query());

        // Stats
        $totalRevenue = Booking::where('payment_status', 'paid')->sum('total_price');
        $activeBookings = Booking::whereIn('booking_status', ['confirmed', 'pending'])->count();
        $pendingCount = Booking::where('booking_status', 'pending')->count();

        return view('admin.bookings.index', compact('bookings', 'totalRevenue', 'activeBookings', 'pendingCount'));
    }

    /**
     * Show booking details
     */
    public function show(Booking $booking): View
    {
        $booking->load(['user', 'tourPackage.destination', 'payment']);
        return view('admin.bookings.show', compact('booking'));
    }

    /**
     * Update booking status
     */
    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'booking_status' => 'required|in:pending,confirmed,cancelled,completed',
        ]);

        $booking->update(['booking_status' => $validated['booking_status']]);

        return redirect()->route('admin.bookings.show', $booking->id)
            ->with('success', 'Booking status updated successfully.');
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'payment_status' => 'required|in:pending,paid,failed,expired',
        ]);

        $booking->update(['payment_status' => $validated['payment_status']]);

        return redirect()->route('admin.bookings.show', $booking->id)
            ->with('success', 'Payment status updated successfully.');
    }
}
