<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\BookingChangeRequest;
use App\Services\BookingChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerChangeRequestController extends Controller
{
    public function __construct(private BookingChangeService $changes) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $destinationIds = $user->destinations->pluck('id');

        $query = BookingChangeRequest::whereIn('booking_id', function ($q) use ($destinationIds) {
            $q->select('b.id')
                ->from('bookings as b')
                ->join('tour_packages as tp', 'b.tour_package_id', '=', 'tp.id')
                ->whereIn('tp.destination_id', $destinationIds);
        })->with(['booking.tourPackage.destination', 'user']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(15)->appends($request->query());

        return view('owner.change-requests.index', compact('requests'));
    }

    public function approve(BookingChangeRequest $changeRequest): RedirectResponse
    {
        $this->authorizeChangeRequest($changeRequest);

        $admin = auth()->user();

        $result = $this->changes->approve($changeRequest, $admin, null);

        if (! $result['ok']) {
            return back()->withErrors(['change_request' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    public function reject(Request $request, BookingChangeRequest $changeRequest): RedirectResponse
    {
        $this->authorizeChangeRequest($changeRequest);

        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $admin = auth()->user();

        $result = $this->changes->reject($changeRequest, $admin, $validated['admin_note']);

        if (! $result['ok']) {
            return back()->withErrors(['change_request' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    public function markRefunded(Request $request, BookingChangeRequest $changeRequest): RedirectResponse
    {
        $this->authorizeChangeRequest($changeRequest);

        $validated = $request->validate([
            'refund_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->changes->markRefunded(
            $changeRequest,
            auth()->user(),
            $validated['refund_note'] ?? null
        );

        if (! $result['ok']) {
            return back()->withErrors(['change_request' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    private function authorizeChangeRequest(BookingChangeRequest $changeRequest): void
    {
        $user = auth()->user();
        $destinationId = $changeRequest->booking->tourPackage?->destination_id;
        if ($destinationId === null || (! $user->isAdmin() && ! $user->destinations()->whereKey($destinationId)->exists())) {
            abort(403, 'Anda tidak memiliki akses ke permintaan ini.');
        }
    }
}
