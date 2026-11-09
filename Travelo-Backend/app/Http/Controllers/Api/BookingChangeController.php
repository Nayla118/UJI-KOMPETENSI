<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingChangeRequest;
use App\Services\BookingChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingChangeController extends Controller
{
    public function __construct(protected BookingChangeService $changes) {}

    /**
     * Change-request history for one of the caller's bookings.
     */
    public function index(Request $request, int $bookingId): JsonResponse
    {
        $booking = $request->user()
            ->bookings()
            ->findOrFail($bookingId);

        $requests = $booking->changeRequests()
            ->limit(20)
            ->get()
            ->map(fn (BookingChangeRequest $change) => $this->payload($change))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Submit a cancellation request for admin approval.
     */
    public function store(Request $request, int $bookingId): JsonResponse
    {
        $validator = validator($request->all(), [
            'type' => ['nullable', 'in:cancellation'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $booking = $request->user()
            ->bookings()
            ->with('tourPackage')
            ->findOrFail($bookingId);

        $validated = $validator->validated();

        $result = $this->changes->request(
            $booking,
            $request->user(),
            $validated['reason'] ?? null
        );

        if (! $result['ok']) {
            $status = ($result['code'] ?? '') === 'forbidden' ? 403 : 422;

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => ['reason' => $result['code'] ?? 'unavailable'],
            ], $status);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $this->payload($result['request']),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(BookingChangeRequest $change): array
    {
        return [
            'id' => $change->id,
            'booking_id' => $change->booking_id,
            'type' => $change->type,
            'status' => $change->status,
            'original_booking_date' => $change->original_booking_date?->toDateString(),
            'reason' => $change->reason,
            'admin_note' => $change->admin_note,
            'refund_status' => $change->refund_status,
            'refund_percentage' => $change->refund_percentage,
            'refund_amount' => $change->refund_amount !== null ? (float) $change->refund_amount : null,
            'refund_reference' => $change->refund_reference,
            'refund_failure_reason' => $change->refund_failure_reason,
            'refunded_at' => $change->refunded_at?->toIso8601String(),
            'reviewed_at' => $change->reviewed_at?->toIso8601String(),
            'created_at' => $change->created_at?->toIso8601String(),
        ];
    }
}
