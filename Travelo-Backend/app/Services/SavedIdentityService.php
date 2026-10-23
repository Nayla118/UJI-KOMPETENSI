<?php

namespace App\Services;

use App\Models\BookingVisitor;
use App\Models\SavedIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Account-level identity reuse for the booker.
 *
 * Scalar identity data is copied from the booking's booker row. The private
 * document is copied into the account's own folder, then copied again into a
 * new booking folder when the traveller reuses it. Keeping copies separate
 * means the booking retention command cannot delete the account's saved file,
 * and deleting/replacing one booking's file cannot break another.
 */
class SavedIdentityService
{
    public function disk(): string
    {
        return (string) config('travelo.identity.disk', 'local');
    }

    /**
     * Return the account's saved identity if it has one.
     *
     * There is deliberately no lazy backfill from old bookings: a real delete
     * must stay deleted, and the account holder is the only person who can
     * confirm which previous booker row belongs to them.
     */
    public function forUser(User $user): ?SavedIdentity
    {
        return SavedIdentity::where('user_id', $user->id)->first();
    }

    /**
     * Keep the account's scalar identity in sync with its booker row.
     */
    public function rememberVisitor(User $user, BookingVisitor $visitor): ?SavedIdentity
    {
        $name = trim((string) $visitor->name);
        $number = trim((string) $visitor->getRawOriginal('identity_number'));

        if ($name === '' || $number === '' || !in_array($visitor->identity_type, BookingVisitor::TYPES, true)) {
            return null;
        }

        $identity = SavedIdentity::firstOrNew(['user_id' => $user->id]);

        if ($identity->exists && $identity->has_document) {
            $sameType = $identity->identity_type === $visitor->identity_type;
            $sameNumber = strtoupper(trim((string) $identity->getRawOriginal('identity_number')))
                === strtoupper($number);

            if (!$sameType || !$sameNumber) {
                // The saved document belongs to the previous identity number.
                // Keep the booking file for staff review, but do not offer the
                // account copy for the new identity.
                $this->forgetDocument($identity);
            }
        }

        $identity->fill([
            'name' => $name,
            'identity_type' => $visitor->identity_type,
            'identity_type_other' => $visitor->identity_type_other,
            'identity_number' => $number,
            'date_of_birth' => $visitor->date_of_birth?->toDateString(),
            'phone' => $visitor->phone,
        ]);

        if (!$identity->exists) {
            $identity->document_status = BookingVisitor::DOC_MISSING;
        }

        $identity->save();

        return $identity;
    }

    /**
     * Copy a booker's uploaded document into account storage. The previous
     * saved copy is removed only after the new row is safely saved.
     */
    public function rememberDocument(User $user, BookingVisitor $visitor): ?SavedIdentity
    {
        $identity = $this->rememberVisitor($user, $visitor);

        if (!$identity) {
            return null;
        }

        $source = $visitor->getRawOriginal('document_path');

        if (!$visitor->has_document || !is_string($source) || $source === '') {
            return $identity;
        }

        $disk = Storage::disk($this->disk());

        if (!$disk->exists($source)) {
            return $identity;
        }

        $destination = sprintf(
            'saved-identities/%d/%s.%s',
            $user->id,
            Str::random(48),
            $this->extensionForMime($visitor->document_mime)
        );

        if (!$disk->copy($source, $destination)) {
            throw new RuntimeException('Unable to copy the saved identity document.');
        }

        $previous = $identity->getRawOriginal('document_path');

        try {
            $identity->forceFill([
                'document_path' => $destination,
                'document_mime' => $visitor->document_mime,
                'document_size' => $visitor->document_size,
                'document_status' => $visitor->document_status,
                'document_uploaded_at' => $visitor->document_uploaded_at ?? now(),
                'document_reviewed_at' => $visitor->document_reviewed_at,
                'document_reviewed_by' => $visitor->getRawOriginal('document_reviewed_by'),
                'document_rejection_reason' => $visitor->document_rejection_reason,
            ])->save();
        } catch (\Throwable $e) {
            $disk->delete($destination);

            throw $e;
        }

        if (is_string($previous) && $previous !== '' && $previous !== $destination) {
            $disk->delete($previous);
        }

        // Deliberately no name, number or path in the log.
        Log::info('Saved identity document updated', [
            'user_id' => $user->id,
            'visitor_id' => $visitor->id,
            'mime' => $identity->document_mime,
            'size' => $identity->document_size,
        ]);

        return $identity->refresh();
    }

    /**
     * Copy the account's document into a new booking visitor row.
     *
     * The copy is marked pending, never auto-verified. The booking owner still
     * needs a readable document, so this is enough to request payment and an
     * e-ticket, while staff may review the copy at the gate.
     */
    public function copyToVisitor(SavedIdentity $identity, BookingVisitor $visitor): BookingVisitor
    {
        $disk = Storage::disk($this->disk());
        $source = $identity->getRawOriginal('document_path');

        if (!$identity->has_document || !is_string($source) || $source === '' || !$disk->exists($source)) {
            throw new RuntimeException('The saved identity document is not available.');
        }

        $destination = sprintf(
            'visitor-identities/%d/%s.%s',
            $visitor->booking_id,
            Str::random(48),
            $this->extensionForMime($identity->document_mime)
        );

        if (!$disk->copy($source, $destination)) {
            throw new RuntimeException('Unable to copy the saved identity document into the booking.');
        }

        try {
            $visitor->forceFill([
                'document_path' => $destination,
                'document_mime' => $identity->document_mime,
                'document_size' => $identity->document_size,
                'document_status' => BookingVisitor::DOC_PENDING,
                'document_uploaded_at' => now(),
                'document_reviewed_at' => null,
                'document_reviewed_by' => null,
                'document_rejection_reason' => null,
                'document_purged_at' => null,
            ])->save();
        } catch (\Throwable $e) {
            $disk->delete($destination);

            throw $e;
        }

        Log::info('Saved identity document reused for booking', [
            'booking_id' => $visitor->booking_id,
            'visitor_id' => $visitor->id,
        ]);

        return $visitor->refresh();
    }

    /**
     * Remove the account's saved document while keeping scalar identity data.
     */
    public function forgetDocument(SavedIdentity $identity): SavedIdentity
    {
        $path = $identity->getRawOriginal('document_path');

        if (is_string($path) && $path !== '') {
            Storage::disk($this->disk())->delete($path);
        }

        $identity->forceFill([
            'document_path' => null,
            'document_mime' => null,
            'document_size' => null,
            'document_status' => BookingVisitor::DOC_MISSING,
            'document_uploaded_at' => null,
            'document_reviewed_at' => null,
            'document_reviewed_by' => null,
            'document_rejection_reason' => null,
        ])->save();

        return $identity->refresh();
    }

    /**
     * Privacy request: delete the account-level identity and its private copy.
     * Historical booking rows keep their own retention schedule.
     */
    public function deleteIdentity(SavedIdentity $identity): void
    {
        $this->forgetDocument($identity);
        $identity->delete();
    }

    private function extensionForMime(?string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
