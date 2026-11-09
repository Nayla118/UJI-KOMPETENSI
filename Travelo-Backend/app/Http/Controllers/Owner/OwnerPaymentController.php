<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only payment history for destinations the user is assigned to.
 * Owner and manager share this screen; gate_staff never has payments.view.
 */
class OwnerPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $payments = $this->query($request)
            ->latest()
            ->paginate(15)
            ->appends($request->query());

        return view('owner.payments.index', compact('payments'));
    }

    public function show(Request $request, Payment $payment): View
    {
        $payment = $this->query($request)
            ->with(['booking.user', 'booking.tourPackage.destination'])
            ->findOrFail($payment->id);

        return view('owner.payments.show', compact('payment'));
    }

    private function query(Request $request)
    {
        $actor = $request->user();
        $query = Payment::query()->with(['booking.tourPackage.destination', 'booking.user']);

        if ($actor->isAdmin()) {
            return $query;
        }

        $destinationIds = $actor->destinations()->pluck('destinations.id');

        return $query->whereHas('booking.tourPackage', function ($packages) use ($destinationIds) {
            $packages->whereIn('destination_id', $destinationIds);
        });
    }
}
