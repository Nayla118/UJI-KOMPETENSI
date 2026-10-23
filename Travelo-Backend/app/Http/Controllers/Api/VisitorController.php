<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingVisitor;
use App\Services\SavedIdentityService;
use App\Services\VisitorIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Owner-scoped visitor identity endpoints.
 *
 * Every lookup goes through the authenticated user's bookings relationship,
 * so a visitor id belonging to another account is always a 404. Responses
 * never include the raw identity number or the private document path.
 */
class VisitorController extends Controller
{
    public function __construct(
        protected VisitorIdentityService $documents,
        protected SavedIdentityService $identities
    ) {}

    /**
     * List visitors (metadata only) for one of the caller's bookings.
     */
    public function index(Request $request, int $bookingId): JsonResponse
    {
        $booking = $this->ownedBooking($request, $bookingId);

        return response()->json([
            'success' => true,
            'data' => $booking->visitors()->orderByDesc('is_booker')->orderBy('id')->get(),
        ]);
    }

    /**
     * Correct visitor data before the ticket is used.
     */
    public function update(Request $request, int $bookingId, int $visitorId): JsonResponse
    {
        $booking = $this->ownedBooking($request, $bookingId);
        $visitor = $booking->visitors()->findOrFail($visitorId);

        if ($booking->ticket_usage_status === Booking::TICKET_USED) {
            return response()->json([
                'success' => false,
                'message' => 'Visitor data cannot be changed after the ticket has been used.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|min:2|max:100',
            'identity_type' => ['sometimes', Rule::in(BookingVisitor::TYPES)],
            'identity_type_other' => 'nullable|string|max:60',
            'identity_number' => ['sometimes', 'string', 'min:4', 'max:48', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\-\/ ]{3,47}$/'],
            'date_of_birth' => 'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before:today',
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{5,29}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $type = $data['identity_type'] ?? $visitor->identity_type;

        if ($type === BookingVisitor::TYPE_OTHER
            && trim((string) ($data['identity_type_other'] ?? $visitor->identity_type_other)) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => ['identity_type_other' => ['Name the official identity document.']],
            ], 422);
        }

        if ($type === BookingVisitor::TYPE_KIA
            && empty($data['date_of_birth'] ?? $visitor->date_of_birth)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => ['date_of_birth' => ['A birth date is required for a KIA.']],
            ], 422);
        }

        $identityChanged = (isset($data['identity_type']) && $data['identity_type'] !== $visitor->identity_type)
            || (isset($data['identity_number']) && $data['identity_number'] !== $visitor->getRawOriginal('identity_number'));

        $visitor->fill($data);

        if ($identityChanged && $visitor->has_document) {
            // The file on disk still belongs to the old identity data; a
            // staff review is required again. It is never auto-verified.
            $visitor->forceFill([
                'document_status' => BookingVisitor::DOC_PENDING,
                'document_reviewed_at' => null,
                'document_reviewed_by' => null,
                'document_rejection_reason' => null,
            ]);
        }

        $visitor->save();

        if ($visitor->is_booker) {
            // The service also drops the account-level document when the
            // identity type/number changed and no longer matches the file.
            $this->identities->rememberVisitor($request->user(), $visitor);
        }

        return response()->json([
            'success' => true,
            'message' => 'Visitor data updated.',
            'data' => $visitor->refresh(),
        ]);
    }

    /**
     * Upload or replace the identity document for one visitor.
     */
    public function uploadDocument(Request $request, int $bookingId, int $visitorId): JsonResponse
    {
        $booking = $this->ownedBooking($request, $bookingId);
        $visitor = $booking->visitors()->findOrFail($visitorId);

        if ($booking->ticket_usage_status === Booking::TICKET_USED) {
            return response()->json([
                'success' => false,
                'message' => 'Identity documents cannot be changed after the ticket has been used.',
            ], 409);
        }

        $maxKb = max(1, (int) config('travelo.identity.max_kb', 5120));
        $mimes = implode(',', (array) config('travelo.identity.mimes', ['jpg', 'jpeg', 'png', 'pdf']));

        $validator = Validator::make($request->all(), [
            'document' => ['required', 'file', 'max:'.$maxKb, 'mimes:'.$mimes],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'The identity document must be a JPG, PNG or PDF up to '.round($maxKb / 1024).' MB.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $visitor = $this->documents->store($visitor, $request->file('document'));

        if ($visitor->is_booker) {
            try {
                $this->identities->rememberDocument($request->user(), $visitor);
            } catch (\Throwable $e) {
                // The booking upload succeeded; account-level reuse can be
                // repaired on the next upload. Do not expose any PII in logs.
                \Log::warning('Saved identity document sync failed', [
                    'user_id' => $request->user()->id,
                    'visitor_id' => $visitor->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded. It is waiting for manual review; it is not verified yet.',
            'data' => $visitor,
        ], 201);
    }

    /**
     * Delete the document (privacy / replacement flow).
     */
    public function deleteDocument(Request $request, int $bookingId, int $visitorId): JsonResponse
    {
        $booking = $this->ownedBooking($request, $bookingId);
        $visitor = $booking->visitors()->findOrFail($visitorId);

        if ($booking->ticket_usage_status === Booking::TICKET_USED) {
            return response()->json([
                'success' => false,
                'message' => 'Identity documents cannot be deleted after the ticket has been used.',
            ], 409);
        }

        $this->documents->delete($visitor);

        if ($visitor->is_booker) {
            $saved = $request->user()->savedIdentity;

            if ($saved) {
                // A privacy deletion removes the account-level copy too.
                $this->identities->forgetDocument($saved);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Document deleted.',
            'data' => $visitor->refresh(),
        ]);
    }

    /**
     * Stream the caller's own document. Never publicly addressable.
     */
    public function downloadDocument(Request $request, int $bookingId, int $visitorId)
    {
        $booking = $this->ownedBooking($request, $bookingId);
        $visitor = $booking->visitors()->findOrFail($visitorId);

        return $this->documents->download($visitor);
    }

    private function ownedBooking(Request $request, int $bookingId): Booking
    {
        return $request->user()
            ->bookings()
            ->findOrFail($bookingId);
    }
}
