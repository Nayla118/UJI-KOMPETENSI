<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only review moderation. Scanner staff never reach this controller:
 * it lives in the role:admin web group, not the scanner group.
 */
class ReviewController extends Controller
{
    public function __construct(protected ReviewService $reviews) {}

    public function index(Request $request): View
    {
        $query = Review::query()
            ->with([
                'user:id,name,email',
                'destination:id,name,city,country',
                'booking:id,booking_date,ticket_status,ticket_used_at',
                'moderator:id,name',
            ]);

        $status = (string) $request->query('status', '');

        if (in_array($status, Review::STATUSES, true)) {
            $query->where('status', $status);
        }

        $destinationId = (int) $request->query('destination_id', 0);

        if ($destinationId > 0) {
            $query->where('destination_id', $destinationId);
        }

        $rating = (int) $request->query('rating', 0);

        if ($rating >= Review::MIN_RATING && $rating <= Review::MAX_RATING) {
            $query->where('rating', $rating);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));

                if (ctype_digit($search)) {
                    $sub->orWhere('id', (int) $search)
                        ->orWhere('booking_id', (int) $search);
                }
            });
        }

        $reviews = $query->latest()->latest('id')->paginate(20)->appends($request->query());

        $destinations = Destination::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $stats = [
            'total' => Review::query()->count(),
            'published' => Review::query()->where('status', Review::STATUS_PUBLISHED)->count(),
            'hidden' => Review::query()->where('status', Review::STATUS_HIDDEN)->count(),
        ];

        return view('admin.reviews.index', compact(
            'reviews',
            'destinations',
            'stats',
            'status',
            'destinationId',
            'rating'
        ));
    }

    public function moderate(Request $request, Review $review): RedirectResponse
    {
        abort_unless($request->user() instanceof User && $request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'action' => 'required|in:hide,restore',
            'reason' => ['required', 'string', 'min:3', 'max:500', 'regex:/\S/'],
        ]);

        $result = $this->reviews->moderate(
            $request->user(),
            $review,
            $validated['action'],
            $validated['reason']
        );

        if (! $result['ok']) {
            return back()->withErrors(['review' => 'The review could not be updated.']);
        }

        return back()->with(
            'success',
            $validated['action'] === 'hide'
                ? 'Review hidden. It no longer contributes to public ratings.'
                : 'Review restored. It contributes to public ratings again.'
        );
    }
}
