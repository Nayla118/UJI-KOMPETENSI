<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TourPackage;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    public function __construct(
        protected MidtransService $midtransService
    ) {}

    /**
     * Get user's bookings
     */
    public function myBookings(Request $request): JsonResponse
    {
        $bookings = $request->user()
            ->bookings()
            ->with(['tourPackage.destination', 'payment'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    /**
     * Create new booking
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tour_package_id' => 'required|exists:tour_packages,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'people_count' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tourPackage = TourPackage::findOrFail($request->tour_package_id);

        // Validate people count
        if ($request->people_count > $tourPackage->max_people) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum ' . $tourPackage->max_people . ' people allowed',
            ], 422);
        }

        // Calculate total price
        $totalPrice = $tourPackage->price * $request->people_count;

        // Create booking
        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'tour_package_id' => $request->tour_package_id,
            'booking_date' => $request->booking_date,
            'people_count' => $request->people_count,
            'total_price' => $totalPrice,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ]);

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
                'booking' => $booking->load(['tourPackage.destination', 'payment']),
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
            ->with(['tourPackage.destination', 'payment'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    /**
     * Return a backend-verified e-ticket for one of the caller's bookings.
     *
     * The app must not invent a reference locally: this endpoint enforces
     * ownership and a confirmed, paid booking, then returns a reference plus
     * an HMAC-signed ticket token that can be validated by the backend.
     */
    public function eTicket(Request $request, int $id): JsonResponse
    {
        $booking = $request->user()
            ->bookings()
            ->with(['tourPackage', 'payment'])
            ->findOrFail($id);

        if (
            $booking->payment_status !== 'paid'
            || $booking->booking_status !== 'confirmed'
            || $booking->payment?->paid_at === null
        ) {
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

        $reference = sprintf('TRV-%06d', $booking->id);
        $issuedAt = $booking->payment->paid_at->toIso8601String();
        $payload = [
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'reference' => $reference,
            'issued_at' => $issuedAt,
        ];

        $encodedPayload = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encodedPayload, (string) config('app.key'));

        return response()->json([
            'success' => true,
            'data' => [
                'reference' => $reference,
                'ticket_token' => $encodedPayload.'.'.$signature,
                'issued_at' => $issuedAt,
                'booking_id' => $booking->id,
                'tour_title' => $booking->tourPackage?->title,
                'booking_date' => $booking->booking_date?->toDateString(),
                'people_count' => $booking->people_count,
                'holder_name' => $request->user()->name,
            ],
        ]);
    }

    /**
     * Public gate check for a signed e-ticket token. Returns only the minimum
     * trip details needed to admit the traveller; it never trusts local app
     * data and never exposes payment/user records.
     */
    public function verifyTicket(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|max:2048',
        ]);

        $token = (string) $request->input('token');
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return response()->json(['success' => true, 'data' => ['valid' => false]]);
        }

        [$encodedPayload, $signature] = $parts;
        $expected = hash_hmac('sha256', $encodedPayload, (string) config('app.key'));

        if (!hash_equals($expected, $signature)) {
            return response()->json(['success' => true, 'data' => ['valid' => false]]);
        }

        $decoded = base64_decode(strtr($encodedPayload, '-_', '+/'), true);
        $payload = $decoded !== false ? json_decode($decoded, true) : null;

        if (!is_array($payload) || empty($payload['booking_id'])) {
            return response()->json(['success' => true, 'data' => ['valid' => false]]);
        }

        $booking = Booking::with(['tourPackage', 'payment', 'user'])->find($payload['booking_id']);

        if (
            !$booking
            || $booking->payment_status !== 'paid'
            || $booking->booking_status !== 'confirmed'
            || $booking->payment?->paid_at === null
            || ($payload['reference'] ?? null) !== sprintf('TRV-%06d', $booking->id)
        ) {
            return response()->json(['success' => true, 'data' => ['valid' => false]]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => true,
                'reference' => $payload['reference'],
                'booking_id' => $booking->id,
                'tour_title' => $booking->tourPackage?->title,
                'booking_date' => $booking->booking_date?->toDateString(),
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
                'booking' => $booking->load(['tourPackage.destination']),
            ],
        ], 200);
    }
}
