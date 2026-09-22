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

        // Generate Midtrans Snap token
        try {
            \Log::info('Generating Snap token for booking: ' . $booking->id);
            \Log::info('Booking details: ' . json_encode($booking->toArray()));
            \Log::info('Midtrans config - Server Key: ' . (config('services.midtrans.server_key') ? 'SET' : 'NOT SET'));
            \Log::info('Midtrans config - Is Production: ' . (config('services.midtrans.is_production') ? 'YES' : 'NO (SANDBOX)'));

            $snapToken = $this->midtransService->createSnapToken($booking, $request->user());

            \Log::info('Snap token generated: ' . ($snapToken ? 'YES' : 'NO'));
            \Log::info('Snap token value: ' . ($snapToken ?? 'NULL'));

            if (empty($snapToken)) {
                \Log::error('Snap token is EMPTY!');
                throw new \Exception('Midtrans returned empty snap token. Check Midtrans credentials.');
            }
        } catch (\Exception $e) {
            \Log::error('Failed to create Snap token: ' . $e->getMessage());
            \Log::error('Exception trace: ' . $e->getTraceAsString());

            $booking->delete();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment: ' . $e->getMessage(),
            ], 500);
        }

        \Log::info('Returning response with snap_token');

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
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment: ' . $e->getMessage(),
            ], 500);
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
