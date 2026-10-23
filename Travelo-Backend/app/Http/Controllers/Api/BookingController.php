<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MyReviewResource;
use App\Models\Booking;
use App\Models\BookingVisitor;
use App\Models\TourPackage;
use App\Services\MidtransService;
use App\Services\ReviewService;
use App\Services\SavedIdentityService;
use App\Services\TicketService;
use App\Services\VisitorIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    public function __construct(
        protected MidtransService $midtransService,
        protected TicketService $tickets,
        protected VisitorIdentityService $documents,
        protected SavedIdentityService $identities,
        protected ReviewService $reviews
    ) {}

    /**
     * Get user's bookings
     */
    public function myBookings(Request $request): JsonResponse
    {
        $bookings = $request->user()
            ->bookings()
            ->with([
                'tourPackage.destination' => fn ($query) => $query
                    ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                    ->withCount('publishedReviews as reviews_count'),
                'payment',
                'visitors',
                'review',
            ])
            ->latest()
            ->get();

        $latestReviewedByDestination = $this->latestReviewedByDestination($bookings);

        return response()->json([
            'success' => true,
            'data' => $bookings->map(fn (Booking $booking) => $this->bookingPayload(
                $booking,
                $latestReviewedByDestination[$booking->tourPackage?->destination_id] ?? null
            )),
        ]);
    }

    /**
     * Latest reviewed visit per destination for this booking collection.
     *
     * @param  iterable<int, Booking>  $bookings
     * @return array<int, Booking>
     */
    private function latestReviewedByDestination(iterable $bookings): array
    {
        $latest = [];

        foreach ($bookings as $booking) {
            if ($booking->review === null) {
                continue;
            }

            $destinationId = $booking->tourPackage?->destination_id;

            if ($destinationId === null) {
                continue;
            }

            $current = $latest[$destinationId] ?? null;

            if ($current === null || $this->reviews->isVisitNewerThan($booking, $current)) {
                $latest[$destinationId] = $booking;
            }
        }

        return $latest;
    }

    /**
     * Booking payload with safe review state. The raw review relation is
     * replaced by the owner resource so another user's ids can never leak.
     */
    private function bookingPayload(Booking $booking, ?Booking $latestReviewedVisit = null): array
    {
        $state = $this->reviews->bookingEligibility($booking);
        $review = $state['review'];

        if ($state['status'] === ReviewService::ELIGIBLE
            && $latestReviewedVisit !== null
            && ! $this->reviews->isVisitNewerThan($booking, $latestReviewedVisit)) {
            // This older visit was followed by a visit that has already been
            // reviewed. Keep its ticket history, but do not offer a new rating.
            $state['status'] = ReviewService::SUPERSEDED;
        }

        if ($review !== null) {
            $review->loadMissing('destination');
            $review->setRelation('booking', $booking);
        }

        $payload = $booking->toArray();
        unset($payload['review']);

        $payload['review'] = $review !== null
            ? (new MyReviewResource($review))->resolve()
            : null;
        $payload['review_status'] = $state['status'];
        $payload['can_review'] = $state['status'] === ReviewService::ELIGIBLE;

        $destination = $booking->tourPackage?->destination;

        if ($destination !== null && isset($payload['tour_package'])) {
            $count = (int) ($destination->reviews_count ?? 0);
            $average = $count > 0
                ? round((float) $destination->reviews_avg_rating, 2)
                : null;

            // Booking payloads must not leak the legacy static package or
            // destination rating. Both mirror the published review aggregate.
            $payload['tour_package']['rating'] = $average;
            $payload['tour_package']['review_count'] = $count;

            if (isset($payload['tour_package']['destination'])) {
                $payload['tour_package']['destination']['rating'] = $average;
                $payload['tour_package']['destination']['review_count'] = $count;
            }
        }

        return $payload;
    }

    /**
     * Create new booking.
     *
     * `visitors` is optional for backward compatibility with older app
     * versions. When supplied it must describe exactly `people_count`
     * travellers, including the booker. Documents are uploaded afterwards
     * through the dedicated visitor endpoint, which is what keeps this
     * request JSON-only.
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'tour_package_id' => 'required|exists:tour_packages,id',
            'booking_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'people_count' => 'required|integer|min:1',
            'visitors' => 'sometimes|array|min:1',
            'visitors.*.name' => 'required|string|min:2|max:100',
            'visitors.*.identity_type' => 'required|in:ktp,kia,student_card,other',
            'visitors.*.identity_type_other' => 'nullable|string|max:60',
            'visitors.*.identity_number' => ['required', 'string', 'min:4', 'max:48', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\-\/ ]{3,47}$/'],
            'visitors.*.date_of_birth' => 'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before:today',
            'visitors.*.phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{5,29}$/'],
            'visitors.*.is_booker' => 'sometimes|boolean',
            'privacy_consent' => 'sometimes|boolean',
            'reuse_saved_identity_document' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tourPackage = TourPackage::findOrFail($request->tour_package_id);

        if ($request->people_count > $tourPackage->max_people) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum '.$tourPackage->max_people.' people allowed',
            ], 422);
        }

        $visitors = $input['visitors'] ?? null;
        $hasVisitors = is_array($visitors);

        if ($hasVisitors) {
            // Convenience default: the first traveller is the booker unless
            // the client explicitly marks someone else.
            $bookerIndexes = array_keys(array_filter(
                $visitors,
                fn ($visitor) => (bool) ($visitor['is_booker'] ?? false)
            ));

            if ($bookerIndexes === []) {
                $visitors[0]['is_booker'] = true;
            }

            $visitorErrors = $this->validateVisitorSet($visitors, (int) $request->people_count);

            if ($visitorErrors !== []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $visitorErrors,
                ], 422);
            }

            if (!$request->boolean('privacy_consent')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please accept the privacy notice before adding visitor identities.',
                    'errors' => [
                        'privacy_consent' => ['Privacy consent is required when visitor identities are provided.'],
                    ],
                ], 422);
            }
        }

        $reuseSavedDocument = $request->boolean('reuse_saved_identity_document');

        if ($reuseSavedDocument && !$hasVisitors) {
            return response()->json([
                'success' => false,
                'message' => 'Provide the booker identity row before reusing a saved document.',
                'errors' => [
                    'reuse_saved_identity_document' => ['Visitor identities are required when reusing a saved document.'],
                ],
            ], 422);
        }

        $savedIdentity = $reuseSavedDocument
            ? $this->identities->forUser($request->user())
            : null;

        if ($reuseSavedDocument && !$savedIdentity?->can_reuse_document) {
            return response()->json([
                'success' => false,
                'message' => 'Your saved identity document is not available. Upload it again for this booking.',
                'errors' => [
                    'reuse_saved_identity_document' => ['The saved identity document is missing or needs to be replaced.'],
                ],
            ], 422);
        }

        if ($reuseSavedDocument && $savedIdentity && $hasVisitors) {
            $bookerData = null;

            foreach ($visitors as $visitor) {
                if (!empty($visitor['is_booker'])) {
                    $bookerData = $visitor;
                    break;
                }
            }

            $submittedNumber = strtoupper(trim((string) ($bookerData['identity_number'] ?? '')));
            $savedNumber = strtoupper(trim((string) $savedIdentity->getRawOriginal('identity_number')));

            if (
                $bookerData === null
                || ($bookerData['identity_type'] ?? null) !== $savedIdentity->identity_type
                || $submittedNumber !== $savedNumber
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your saved identity document belongs to a different identity. Upload a new document for this booking.',
                    'errors' => [
                        'reuse_saved_identity_document' => ['The booker identity does not match the saved document.'],
                    ],
                ], 422);
            }
        }

        $totalPrice = $tourPackage->price * $request->people_count;

        try {
            $booking = DB::transaction(function () use ($request, $visitors, $hasVisitors, $totalPrice, $reuseSavedDocument, $savedIdentity) {
                $booking = Booking::create([
                    'user_id' => $request->user()->id,
                    'tour_package_id' => $request->tour_package_id,
                    'booking_date' => $request->booking_date,
                    'people_count' => $request->people_count,
                    'total_price' => $totalPrice,
                    'payment_status' => 'pending',
                    'booking_status' => 'pending',
                    'privacy_consent_at' => $hasVisitors ? now() : null,
                    'privacy_consent_version' => $hasVisitors
                        ? (string) config('travelo.privacy_policy_version')
                        : null,
                ]);

                $bookerVisitor = null;

                if ($hasVisitors) {
                    foreach ($visitors as $visitor) {
                        $created = $booking->visitors()->create([
                            'is_booker' => (bool) ($visitor['is_booker'] ?? false),
                            'name' => trim((string) $visitor['name']),
                            'identity_type' => $visitor['identity_type'],
                            'identity_type_other' => $visitor['identity_type_other'] ?? null,
                            'identity_number' => trim((string) $visitor['identity_number']),
                            'date_of_birth' => $visitor['date_of_birth'] ?? null,
                            'phone' => $visitor['phone'] ?? null,
                            'document_status' => BookingVisitor::DOC_MISSING,
                        ]);

                        if ($created->is_booker) {
                            $bookerVisitor = $created;
                        }
                    }
                }

                if ($bookerVisitor) {
                    // Save/refresh the account-level identity even when the
                    // traveller uploaded no document yet.
                    $bookerIdentity = $this->identities->rememberVisitor($request->user(), $bookerVisitor);

                    if ($reuseSavedDocument) {
                        $source = $bookerIdentity ?? $savedIdentity;

                        if (!$source) {
                            throw new \RuntimeException('The saved identity is not available.');
                        }

                        $this->identities->copyToVisitor($source, $bookerVisitor);
                    }
                }

                return $booking;
            });
        } catch (\RuntimeException $e) {
            \Log::warning('Booking saved identity reuse failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'We could not reuse your saved identity document. Upload it again and retry.',
            ], 422);
        }

        // Generate Midtrans Snap token. Never log the token or the full
        // booking payload: both are sensitive.
        try {
            \Log::info('Creating Snap token for booking', ['booking_id' => $booking->id]);

            $snapToken = $this->midtransService->createSnapToken($booking, $request->user());

            if (empty($snapToken)) {
                throw new \Exception('Midtrans returned an empty snap token.');
            }
        } catch (\Exception $e) {
            \Log::error('Failed to create Snap token', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            // A reused identity document is a private file; do not leave it
            // behind when the booking is rolled back.
            $this->documents->deleteFilesForBooking($booking);
            $booking->delete();

            return response()->json([
                'success' => false,
                'message' => 'We could not start the payment. Please try again.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking created successfully',
            'data' => [
                'booking' => $booking->load(['tourPackage.destination', 'payment', 'visitors']),
                'snap_token' => $snapToken,
            ],
        ], 201);
    }

    /**
     * Get single booking
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $booking = $request->user()
            ->bookings()
            ->with([
                'tourPackage.destination' => fn ($query) => $query
                    ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                    ->withCount('publishedReviews as reviews_count'),
                'payment',
                'visitors',
                'review',
            ])
            ->findOrFail($id);

        $destinationId = $booking->tourPackage?->destination_id;
        $latestReviewed = $destinationId !== null
            ? $this->reviews->latestReviewedVisit($request->user(), $destinationId)
            : null;

        return response()->json([
            'success' => true,
            'data' => $this->bookingPayload($booking, $latestReviewed),
        ]);
    }

    /**
     * Return a backend-verified e-ticket for one of the caller's bookings.
     *
     * Enforces ownership, a confirmed payment, and (for bookings that carry
     * visitor rows) complete visitor identity data. The signed token is
     * deterministic for a given ticket version, so reopening the ticket
     * shows the same QR without reissuing it.
     */
    public function eTicket(Request $request, int $id): JsonResponse
    {
        $booking = $request->user()
            ->bookings()
            ->with(['tourPackage.destination', 'payment', 'visitors', 'user'])
            ->findOrFail($id);

        if (!$booking->isPaidAndConfirmed()) {
            return response()->json([
                'success' => false,
                'message' => 'An e-ticket is available only after payment is confirmed.',
                'data' => [
                    'booking_id' => $booking->id,
                    'booking_status' => $booking->booking_status,
                    'payment_status' => $booking->payment_status,
                ],
            ], 409);
        }

        if (!$booking->hasCompleteVisitorIdentity()) {
            return response()->json([
                'success' => false,
                'message' => 'Complete every visitor identity document before opening the e-ticket.',
                'data' => [
                    'booking_id' => $booking->id,
                    'booking_status' => $booking->booking_status,
                    'payment_status' => $booking->payment_status,
                    'identity_status' => 'incomplete',
                    'visitors_expected' => (int) $booking->people_count,
                    'visitors_on_file' => $booking->visitors->count(),
                    'visitors_with_document' => $booking->visitors->filter->has_document->count(),
                ],
            ], 409);
        }

        $issued = $this->tickets->issue($booking);
        $state = $this->tickets->evaluate($booking, $issued['payload']);

        return response()->json([
            'success' => true,
            'data' => [
                'reference' => $issued['payload']['reference'],
                'ticket_token' => $issued['token'],
                'issued_at' => $issued['issued_at'],
                'booking_id' => $booking->id,
                'tour_title' => $booking->tourPackage?->title,
                'booking_date' => $booking->booking_date?->toDateString(),
                'people_count' => $booking->people_count,
                'holder_name' => $request->user()->name,
                'usage_status' => $state['usage_status'],
                'payment_status' => $booking->payment_status,
                'booking_status' => $booking->booking_status,
                'valid_until' => $state['valid_until'],
                'destination' => $state['destination'],
                'visitors' => $booking->visitors->map(fn (BookingVisitor $visitor) => [
                    'name' => $visitor->name,
                    'is_booker' => $visitor->is_booker,
                    'identity_type' => $visitor->identity_type,
                    'identity_type_label' => $visitor->identity_type_label,
                    'document_status' => $visitor->document_status,
                ])->values(),
            ],
        ]);
    }

    /**
     * Public gate check for a signed e-ticket token.
     *
     * Authenticity (signature) and entry validity (payment, date, usage) are
     * reported separately. Only the minimum trip details are returned; no
     * identity numbers, documents or auth tokens.
     */
    public function verifyTicket(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|max:2048',
        ]);

        $payload = $this->tickets->verifyToken((string) $request->input('token'));

        if ($payload === null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => false,
                    'authentic' => false,
                    'reason' => TicketService::REASON_INVALID_TOKEN,
                ],
            ]);
        }

        $booking = Booking::with(['tourPackage.destination', 'payment', 'visitors', 'user'])
            ->find($payload['booking_id'] ?? 0);

        if (!$booking) {
            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => false,
                    'authentic' => false,
                    'reason' => TicketService::REASON_INVALID_TOKEN,
                ],
            ]);
        }

        $state = $this->tickets->evaluate($booking, $payload);

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => $state['valid'],
                'authentic' => $state['authentic'],
                'reason' => $state['reason'],
                'reference' => $state['reference'],
                'booking_id' => $booking->id,
                'tour_title' => $booking->tourPackage?->title,
                'destination' => $state['destination'],
                'booking_date' => $state['visit_date'],
                'valid_until' => $state['valid_until'],
                'usage_status' => $state['usage_status'],
                'payment_status' => $state['payment_status'],
                'booking_status' => $state['booking_status'],
                'people_count' => $booking->people_count,
                'holder_name' => $booking->user?->name,
            ],
        ]);
    }

    /**
     * Create Snap token for existing booking (Android app integration)
     */
    public function createSnapToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $booking = Booking::findOrFail($request->booking_id);

        // Verify ownership
        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Check if payment already exists
        $existingPayment = $booking->payment;
        if ($existingPayment && $existingPayment->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Booking already paid',
            ], 400);
        }

        try {
            $snapToken = $this->midtransService->createSnapToken($booking, $request->user());
        } catch (\Exception $e) {
            \Log::error('Failed to create Snap token for existing booking', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'We could not start the payment. Please try again.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Snap token created successfully',
            'data' => [
                'snap_token' => $snapToken,
                'booking' => $booking->load(['tourPackage.destination', 'visitors']),
            ],
        ], 200);
    }

    /**
     * Cross-field visitor validation that is hard to express with wildcard
     * rules: exact count, exactly one booker, KIA needs a birth date and
     * "other" needs a label.
     *
     * @param  array<int, array<string, mixed>>  $visitors
     * @return array<string, array<int, string>>
     */
    private function validateVisitorSet(array $visitors, int $peopleCount): array
    {
        $errors = [];

        if (count($visitors) !== $peopleCount) {
            $errors['visitors'][] = "Provide details for exactly {$peopleCount} travellers.";

            return $errors;
        }

        $bookers = 0;
        $numbers = [];

        foreach ($visitors as $index => $visitor) {
            $type = $visitor['identity_type'] ?? null;
            $number = trim((string) ($visitor['identity_number'] ?? ''));

            if (($visitor['is_booker'] ?? false)) {
                $bookers++;
            }

            if ($type === BookingVisitor::TYPE_OTHER && trim((string) ($visitor['identity_type_other'] ?? '')) === '') {
                $errors["visitors.{$index}.identity_type_other"][] = 'Name the official identity document.';
            }

            if ($type === BookingVisitor::TYPE_KIA && empty($visitor['date_of_birth'])) {
                $errors["visitors.{$index}.date_of_birth"][] = 'A birth date is required for a KIA.';
            }

            if ($number !== '') {
                $numbers[] = strtoupper($number);
            }
        }

        if ($bookers > 1) {
            $errors['visitors'][] = 'Only one traveller can be marked as the booker.';
        }

        if (count($numbers) !== count(array_unique($numbers))) {
            $errors['visitors'][] = 'The same identity number was entered more than once.';
        }

        return $errors;
    }
}
