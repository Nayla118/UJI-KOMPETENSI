<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingVisitor;
use App\Models\User;
use App\Services\TicketService;
use App\Services\VisitorIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff-facing operations for the gate scanner.
 *
 * Authorization is enforced here (and again in TicketService for the
 * destination check): admins cover every destination, staff only the
 * destinations assigned in staff_destinations.
 */
class TicketController extends Controller
{
    public function __construct(
        protected TicketService $tickets,
        protected VisitorIdentityService $documents
    ) {}

    /**
     * Camera + manual-token scanner used by admin and gate staff.
     */
    public function scanner(Request $request): View
    {
        $staff = $request->user();

        abort_unless($staff instanceof User && $staff->isStaffOrAdmin(), 403);

        $destinations = $staff->isAdmin()
            ? collect()
            : $staff->staffDestinations()->orderBy('name')->pluck('name');

        return view('admin.scanner', [
            'isAdmin' => $staff->isAdmin(),
            'destinations' => $destinations,
        ]);
    }

    /**
     * Atomically redeem a signed ticket token at the gate.
     */
    public function redeem(Request $request): JsonResponse
    {
        $staff = $request->user();

        abort_unless($staff instanceof User && $staff->isStaffOrAdmin(), 403);

        $validator = validator($request->all(), [
            'token' => 'required|string|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->tickets->redeem((string) $request->input('token'), $staff);

        if ($result['ok']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ]);
        }

        $status = match ($result['reason']) {
            TicketService::REASON_FORBIDDEN => 403,
            TicketService::REASON_INVALID_TOKEN => 422,
            default => 409,
        };

        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'reason' => $result['reason'],
            'data' => $result['data'],
        ], $status);
    }

    /**
     * Legitimate reissue: invalidates old tokens by bumping the version.
     */
    public function reissue(Request $request, Booking $booking): JsonResponse
    {
        $staff = $request->user();

        abort_unless($staff instanceof User && $staff->isAdmin(), 403);

        $booking->loadMissing('tourPackage.destination', 'payment');

        $result = $this->tickets->reissue($booking);

        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 409);
        }

        ActivityLog::log(
            'Ticket reissued',
            Booking::class,
            $booking->id,
            User::class,
            $staff->id,
            ['ticket_version' => $booking->refresh()->ticket_version]
        );

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'booking_id' => $booking->id,
                'ticket_version' => $booking->ticket_version,
                'ticket_token' => $result['token'],
            ],
        ]);
    }

    /**
     * Manual document review. This is the only path to `verified`.
     */
    public function reviewDocument(Request $request, BookingVisitor $visitor): JsonResponse
    {
        $staff = $request->user();

        abort_unless($staff instanceof User && $staff->isStaffOrAdmin(), 403);

        $visitor->loadMissing('booking.tourPackage.destination');

        $destinationId = $visitor->booking?->tourPackage?->destination_id;

        abort_unless($staff->canRedeemForDestination($destinationId), 403);

        $validator = validator($request->all(), [
            'status' => 'required|in:verified,rejected',
            'reason' => 'required_if:status,rejected|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$visitor->has_document) {
            return response()->json([
                'success' => false,
                'message' => 'There is no document on file to review.',
            ], 409);
        }

        $visitor->forceFill([
            'document_status' => $request->input('status'),
            'document_reviewed_at' => now(),
            'document_reviewed_by' => $staff->id,
            'document_rejection_reason' => $request->input('status') === BookingVisitor::DOC_REJECTED
                ? $request->input('reason')
                : null,
        ])->save();

        ActivityLog::log(
            'Visitor identity document reviewed',
            BookingVisitor::class,
            $visitor->id,
            User::class,
            $staff->id,
            [
                'booking_id' => $visitor->booking_id,
                'status' => $visitor->document_status,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Document review saved.',
            'data' => $visitor->refresh(),
        ]);
    }

    /**
     * Staff-only document view, scoped to the staff member's destination.
     */
    public function downloadDocument(Request $request, BookingVisitor $visitor)
    {
        $staff = $request->user();

        abort_unless($staff instanceof User && $staff->isStaffOrAdmin(), 403);

        $visitor->loadMissing('booking.tourPackage.destination');

        $destinationId = $visitor->booking?->tourPackage?->destination_id;

        abort_unless($staff->canRedeemForDestination($destinationId), 403);

        return $this->documents->download($visitor);
    }
}
