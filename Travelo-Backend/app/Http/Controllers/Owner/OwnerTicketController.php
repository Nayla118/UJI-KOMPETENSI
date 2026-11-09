<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerTicketController extends Controller
{
    public function scanner(Request $request): View
    {
        $user = auth()->user();

        $destinations = $user->destinations()->orderBy('name')->get();

        return view('owner.scanner', [
            'destinations' => $destinations,
            'isGateStaff' => $user->isGateStaff(),
        ]);
    }

    public function redeem(Request $request): RedirectResponse
    {
        // The browser scanner posts `token`; keep the field name aligned with
        // the shared admin endpoint instead of validating a phantom field.
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $user = auth()->user();
        $destinationId = $request->integer('destination_id');

        if ($destinationId !== null) {
            $owned = $user->isAdmin()
                || $user->destinations()->whereKey($destinationId)->exists();

            if (! $owned) {
                abort(403, 'Anda tidak memiliki akses ke destinasi ini.');
            }
        }

        // Delegate to the shared ticket redemption logic in Admin TicketController.
        return app(TicketController::class)->redeem($request);
    }

    /**
     * The signed-in user's own scans. No other staff member's history is
     * exposed, and the view never shows money or traveller contact details.
     */
    public function history(Request $request): View
    {
        $bookings = Booking::query()
            ->where('ticket_used_by', auth()->id())
            ->with(['tourPackage.destination'])
            ->orderByDesc('ticket_used_at')
            ->paginate(20);

        return view('owner.scanner-history', compact('bookings'));
    }
}
