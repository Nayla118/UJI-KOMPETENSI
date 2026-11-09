<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAutoRefund;
use App\Models\BookingChangeRequest;
use App\Services\BookingChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChangeRequestController extends Controller
{
    public function __construct(protected BookingChangeService $changes) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', BookingChangeRequest::STATUS_PENDING);

        if (! in_array($status, BookingChangeRequest::STATUSES, true)) {
            $status = BookingChangeRequest::STATUS_PENDING;
        }

        $query = BookingChangeRequest::query()
            ->with([
                'booking.tourPackage.destination',
                'booking.user:id,name,email',
                'user:id,name,email',
                'reviewer:id,name',
            ]);

        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->whereHas('user', fn ($user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));

                if (ctype_digit($search)) {
                    $sub->orWhere('booking_id', (int) $search)
                        ->orWhere('id', (int) $search);
                }
            });
        }

        $requests = $query->latest('id')->paginate(20)->appends($request->query());

        $stats = [
            'pending' => BookingChangeRequest::query()->where('status', BookingChangeRequest::STATUS_PENDING)->count(),
            'approved' => BookingChangeRequest::query()->where('status', BookingChangeRequest::STATUS_APPROVED)->count(),
            'rejected' => BookingChangeRequest::query()->where('status', BookingChangeRequest::STATUS_REJECTED)->count(),
        ];

        return view('admin.change-requests.index', compact('requests', 'stats', 'status'));
    }

    public function approve(Request $request, BookingChangeRequest $changeRequest): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->changes->approve(
            $changeRequest,
            $request->user(),
            $validated['admin_note'] ?? null
        );

        if (! $result['ok']) {
            return back()->withErrors(['change_request' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    public function reject(Request $request, BookingChangeRequest $changeRequest): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $result = $this->changes->reject(
            $changeRequest,
            $request->user(),
            $validated['admin_note']
        );

        if (! $result['ok']) {
            return back()->withErrors(['change_request' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    public function markRefunded(Request $request, BookingChangeRequest $changeRequest): RedirectResponse
    {
        $validated = $request->validate([
            'refund_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->changes->markRefunded(
            $changeRequest,
            $request->user(),
            $validated['refund_note'] ?? null
        );

        if (! $result['ok']) {
            return back()->withErrors(['change_request' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    public function retryRefund(Request $request, BookingChangeRequest $changeRequest): RedirectResponse
    {
        if (! $changeRequest->isRefundable()) {
            return back()->withErrors(['change_request' => 'This refund cannot be retried.']);
        }

        ProcessAutoRefund::retry($changeRequest);

        return back()->with('success', 'Refund retry has been queued.');
    }
}
