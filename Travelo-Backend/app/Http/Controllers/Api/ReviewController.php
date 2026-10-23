<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MyReviewResource;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    public function __construct(protected ReviewService $reviews) {}

    /**
     * Public rating summary from published reviews only.
     */
    public function summary(int $destinationId): JsonResponse
    {
        $destination = Destination::find($destinationId);

        if ($destination === null) {
            return $this->notFound('Destination not found');
        }

        return response()->json([
            'success' => true,
            'data' => $this->reviews->summary($destination->id),
        ]);
    }

    /**
     * Public verified reviews for a destination.
     */
    public function index(Request $request, int $destinationId): JsonResponse
    {
        $destination = Destination::find($destinationId);

        if ($destination === null) {
            return $this->notFound('Destination not found');
        }

        $paginator = $this->reviews->publicReviews(
            $destination->id,
            (int) $request->query('per_page', 5)
        );

        $reviews = collect($paginator->items())
            ->map(fn (Review $review) => (new ReviewResource($review))->resolve())
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $this->reviews->summary($destination->id),
                'reviews' => $reviews,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    /**
     * Eligibility for the signed-in traveller at one destination.
     */
    public function eligibility(Request $request, int $destinationId): JsonResponse
    {
        $destination = Destination::find($destinationId);

        if ($destination === null) {
            return $this->notFound('Destination not found');
        }

        $state = $this->reviews->eligibilityForDestination($request->user(), $destination);
        $review = $state['review'];

        if ($review !== null) {
            $review->loadMissing(['destination', 'booking']);
        }

        $hidden = $review !== null && $review->status === Review::STATUS_HIDDEN;

        return response()->json([
            'success' => true,
            'data' => [
                'destination_id' => $destination->id,
                'status' => $state['status'],
                'can_review' => $state['status'] === ReviewService::ELIGIBLE,
                'booking_id' => $state['booking']?->id,
                'review' => $review !== null
                    ? (new MyReviewResource($review))->resolve()
                    : null,
                'message' => $this->reviews->messageFor($state['status'], $hidden),
            ],
        ]);
    }

    /**
     * Submit the single review for one of the caller's bookings.
     */
    public function store(Request $request, int $bookingId): JsonResponse
    {
        $booking = Booking::find($bookingId);

        if ($booking === null) {
            return $this->notFound('Booking not found');
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->reviews->submit(
            $request->user(),
            $booking,
            (int) $request->input('rating'),
            $request->input('comment')
        );

        if (! $result['ok']) {
            return $this->failure($result['reason'], $result['status']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Thank you. Your verified review is now published.',
            'data' => (new MyReviewResource($result['review']))->resolve(),
        ], 201);
    }

    public function update(Request $request, int $reviewId): JsonResponse
    {
        $review = Review::find($reviewId);

        if ($review === null) {
            return $this->notFound('Review not found');
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->reviews->update(
            $request->user(),
            $review,
            (int) $request->input('rating'),
            $request->input('comment')
        );

        if (! $result['ok']) {
            return $this->failure($result['reason'], $result['status']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your review was updated.',
            'data' => (new MyReviewResource($result['review']))->resolve(),
        ]);
    }

    public function destroy(Request $request, int $reviewId): JsonResponse
    {
        $review = Review::find($reviewId);

        if ($review === null) {
            return $this->notFound('Review not found');
        }

        $result = $this->reviews->delete($request->user(), $review);

        if (! $result['ok']) {
            return $this->failure($result['reason'], $result['status']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your review was deleted.',
        ]);
    }

    /**
     * Owner-only list, including hidden reviews and moderation state.
     */
    public function mine(Request $request): JsonResponse
    {
        $reviews = Review::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'destination',
                'booking:id,ticket_status,ticket_used_at',
            ])
            ->latest()
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => collect($reviews->items())
                ->map(fn (Review $review) => (new MyReviewResource($review))->resolve())
                ->values(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 404);
    }

    private function failure(string $reason, int $status): JsonResponse
    {
        $messages = [
            'already_reviewed' => 'This visit has already been reviewed.',
            'superseded' => 'A newer visit at this destination has already been reviewed.',
            'review_hidden' => 'Hidden reviews cannot be changed. Please contact support.',
            'eligible' => 'This visit can be reviewed.',
            'reviewed' => 'This visit has already been reviewed.',
            'not_checked_in' => 'Only tickets scanned at the gate can be reviewed.',
            'unpaid' => 'Only paid and confirmed bookings can be reviewed.',
            'cancelled' => 'Cancelled or refunded bookings cannot be reviewed.',
            'not_booked' => 'No eligible booking was found for this destination.',
            'destination_not_found' => 'The booked destination could not be found.',
            'invalid_rating' => 'Rating must be a whole number from 1 to 5.',
            'booking_not_found' => 'Booking not found.',
            'review_not_found' => 'Review not found.',
            'invalid_action' => 'Invalid moderation action.',
        ];

        return response()->json([
            'success' => false,
            'message' => $messages[$reason] ?? 'This review action is not allowed.',
            'reason' => $reason,
        ], $status);
    }
}
