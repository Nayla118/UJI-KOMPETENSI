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
 * Gate-facing operations for the scanner.
 *
 * Authorization is enforced here (and again in TicketService for the
 * destination check): admins cover every destination, owners only the
 * destinations assigned in destination_user.
 */
class TicketController extends Controller
{
    public function __construct(
        protected TicketService $tickets,
        protected VisitorIdentityService $documents
    ) {}

    /**
     * Camera + manual-token scanner used by admin and owners.
     */
    public function scanner(Request $request): View
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->hasPermission('tickets.redeem'), 403);

        $destinations = $user->isAdmin()
            ? collect()
            : $user->destinations()->orderBy('name')->pluck('name');

        return view('admin.scanner', [
            'isAdmin' => $user->isAdmin(),
            'isGateStaff' => $user->isGateStaff(),
            'destinations' => $destinations,
        ]);
    }

    /**
     * Atomically redeem a signed ticket token at the gate.
     */
    public function redeem(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->hasPermission('tickets.redeem'), 403);

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

        $result = $this->tickets->redeem((string) $request->input('token'), $user);

        // Gate staff only receive operational fields: valid/invalid, booker,
        // package, people count and visit date. No money, email/phone,
        // identity number, document, reference or destination.
        $data = $user->isGateStaff()
            ? $this->gateStaffScanPayload($result)
            : $result['data'];

        if ($result['ok']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $data,
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
            'data' => $data,
        ], $status);
    }

    /**
     * @param  array{ok: bool, reason: ?string, message: string, data: array<string, mixed>}  $result
     * @return array<string, mixed>
     */
    private function gateStaffScanPayload(array $result): array
    {
        $data = $result['data'];

        return array_filter([
            'valid' => (bool) ($data['valid'] ?? $result['ok']),
            'holder_name' => $data['holder_name'] ?? null,
            'package' => $data['package'] ?? null,
            'visitor_count' => $data['visitor_count'] ?? null,
            'visit_date' => $data['visit_date'] ?? null,
        ], static fn ($value) => $value !== null);
    }

    /**
     * Legitimate reissue: invalidates old tokens by bumping the version.
     */
    public function reissue(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->hasPermission('tickets.reissue'), 403);

        $booking->loadMissing('tourPackage.destination', 'payment');

        $result = $this->tickets->reissue($booking);

        if (! $result['ok']) {
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
            $user->id,
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
        $user = $request->user();

        abort_unless($user instanceof User && $user->hasPermission('visitor_documents.view'), 403);

        $visitor->loadMissing('booking.tourPackage.destination');

        $destinationId = $visitor->booking?->tourPackage?->destination_id;

        abort_unless($user->canRedeemForDestination($destinationId), 403);

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

        if (! $visitor->has_document) {
            return response()->json([
                'success' => false,
                'message' => 'There is no document on file to review.',
            ], 409);
        }

        $visitor->forceFill([
            'document_status' => $request->input('status'),
            'document_reviewed_at' => now(),
            'document_reviewed_by' => $user->id,
            'document_rejection_reason' => $request->input('status') === BookingVisitor::DOC_REJECTED
                ? $request->input('reason')
                : null,
        ])->save();

        ActivityLog::log(
            'Visitor identity document reviewed',
            BookingVisitor::class,
            $visitor->id,
            User::class,
            $user->id,
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
     * Document view, scoped to the user's destination.
     */
    public function downloadDocument(Request $request, BookingVisitor $visitor)
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->hasPermission('visitor_documents.view'), 403);

        $visitor->loadMissing('booking.tourPackage.destination');

        $destinationId = $visitor->booking?->tourPackage?->destination_id;

        abort_unless($user->canRedeemForDestination($destinationId), 403);

        return $this->documents->download($visitor);
    }
}
