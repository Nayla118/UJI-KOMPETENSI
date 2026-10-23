<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedIdentity;
use App\Services\SavedIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner-scoped endpoint for reusing the booker's identity.
 *
 * This is the only API that returns the raw identity number for the current
 * account. It is authenticated, never cached, and limited to fields needed to
 * prefill the booking form. Storage paths are never exposed.
 */
class IdentityProfileController extends Controller
{
    public function __construct(
        protected SavedIdentityService $identities
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $identity = $this->identities->forUser($user);

        $response = response()->json([
            'success' => true,
            'data' => $identity ? $this->payload($identity) : null,
        ]);

        return $response->header('Cache-Control', 'no-store, private, max-age=0');
    }

    /**
     * Privacy request: forget the account-level identity. Booking rows and
     * their documents continue to follow their own retention schedule.
     */
    public function destroy(Request $request): JsonResponse
    {
        $identity = SavedIdentity::where('user_id', $request->user()->id)->first();

        if ($identity) {
            $this->identities->deleteIdentity($identity);
        }

        return response()->json([
            'success' => true,
            'message' => 'Saved identity deleted.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SavedIdentity $identity): array
    {
        return [
            'name' => $identity->name,
            'identity_type' => $identity->identity_type,
            'identity_type_other' => $identity->identity_type_other,
            // Needed to prefill the form. The response is owner-only/no-store.
            'identity_number' => $identity->getRawOriginal('identity_number'),
            'date_of_birth' => $identity->date_of_birth?->toDateString(),
            'phone' => $identity->phone,
            'has_document' => $identity->has_document,
            'can_reuse_document' => $identity->can_reuse_document,
            'document_status' => $identity->document_status,
            'document_uploaded_at' => $identity->document_uploaded_at?->toIso8601String(),
            'updated_at' => $identity->updated_at?->toIso8601String(),
        ];
    }
}
