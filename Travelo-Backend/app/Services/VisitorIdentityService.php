<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingVisitor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private storage for visitor identity documents.
 *
 * - random file names, never the customer's original name
 * - private disk only (storage/app/private), never /storage
 * - upload => status "pending"; only staff review can mark "verified"
 * - retention: documents are purged N days after the visit date
 */
class VisitorIdentityService
{
    public function disk(): string
    {
        return (string) config('travelo.identity.disk', 'local');
    }

    /**
     * Store a validated upload, replacing any previous document.
     */
    public function store(BookingVisitor $visitor, UploadedFile $file): BookingVisitor
    {
        // Keep the previous document until the replacement has been written
        // and the database row points at it. Otherwise a failed write would
        // leave the visitor referencing a file that no longer exists.
        $previousPath = $visitor->getRawOriginal('document_path');

        $extension = $this->safeExtension($file);
        $path = sprintf(
            'visitor-identities/%d/%s.%s',
            $visitor->booking_id,
            Str::random(48),
            $extension
        );

        $stored = Storage::disk($this->disk())->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        if ($stored === false) {
            throw new \RuntimeException('Unable to store the identity document.');
        }

        try {
            $visitor->forceFill([
                'document_path' => $path,
                'document_mime' => $this->safeMime($file),
                'document_size' => (int) $file->getSize(),
                // An upload proves a file exists, nothing more. Never "verified".
                'document_status' => BookingVisitor::DOC_PENDING,
                'document_uploaded_at' => now(),
                'document_reviewed_at' => null,
                'document_reviewed_by' => null,
                'document_rejection_reason' => null,
                'document_purged_at' => null,
            ])->save();
        } catch (\Throwable $e) {
            // Do not leave an orphaned new file behind if the row was not saved.
            Storage::disk($this->disk())->delete($path);

            throw $e;
        }

        if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $path) {
            Storage::disk($this->disk())->delete($previousPath);
        }

        // Deliberately no name / number / path in the log.
        Log::info('Visitor identity document stored', [
            'visitor_id' => $visitor->id,
            'booking_id' => $visitor->booking_id,
            'mime' => $visitor->document_mime,
            'size' => $visitor->document_size,
        ]);

        return $visitor->refresh();
    }

    /**
     * Traveller-initiated deletion (privacy request / replacement).
     */
    public function delete(BookingVisitor $visitor): void
    {
        $this->deleteFileOnly($visitor);

        $visitor->forceFill([
            'document_path' => null,
            'document_mime' => null,
            'document_size' => null,
            'document_status' => BookingVisitor::DOC_MISSING,
            'document_uploaded_at' => null,
            'document_reviewed_at' => null,
            'document_reviewed_by' => null,
            'document_rejection_reason' => null,
        ])->save();
    }

    /**
     * Remove the file for a single visitor without touching other fields.
     */
    private function deleteFileOnly(BookingVisitor $visitor): void
    {
        $path = $visitor->getRawOriginal('document_path');

        if (is_string($path) && $path !== '') {
            Storage::disk($this->disk())->delete($path);
        }
    }

    /**
     * Streaming download for the owner or authorized staff.
     */
    public function download(BookingVisitor $visitor): StreamedResponse
    {
        $path = $visitor->getRawOriginal('document_path');
        $disk = Storage::disk($this->disk());

        abort_if(!is_string($path) || $path === '' || !$disk->exists($path), 404);

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';

        return $disk->response($path, sprintf('identity-%d.%s', $visitor->id, $extension), [
            'Content-Type' => $visitor->document_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Remove every private file attached to a booking's visitors. Used when a
     * booking is rolled back and will be deleted. Database rows are not
     * touched; the caller deletes the booking afterwards.
     */
    public function deleteFilesForBooking(Booking $booking): void
    {
        foreach ($booking->visitors as $visitor) {
            $path = $visitor->getRawOriginal('document_path');

            if (is_string($path) && $path !== '') {
                Storage::disk($this->disk())->delete($path);
            }
        }
    }

    /**
     * Retention: purge documents whose visit date passed `retention_days`
     * ago. Returns the number of documents removed.
     */
    public function purgeExpired(): int
    {
        $days = max(1, (int) config('travelo.identity.retention_days', 90));
        $cutoff = now()->subDays($days)->toDateString();

        $purged = 0;

        BookingVisitor::query()
            ->whereNotNull('document_path')
            ->whereHas('booking', function ($query) use ($cutoff) {
                $query->whereDate('booking_date', '<', $cutoff);
            })
            ->select(['id', 'booking_id', 'document_path'])
            ->chunkById(100, function ($visitors) use (&$purged) {
                foreach ($visitors as $visitor) {
                    Storage::disk($this->disk())->delete((string) $visitor->getRawOriginal('document_path'));

                    BookingVisitor::whereKey($visitor->id)->update([
                        'document_path' => null,
                        'document_mime' => null,
                        'document_size' => null,
                        'document_status' => BookingVisitor::DOC_PURGED,
                        'document_purged_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $purged++;
                }
            });

        return $purged;
    }

    /**
     * Derive the extension from the validated content, not the client name.
     */
    private function safeExtension(UploadedFile $file): string
    {
        $mime = $this->safeMime($file);

        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    private function safeMime(UploadedFile $file): string
    {
        // getMimeType() inspects the file content on the server.
        return (string) ($file->getMimeType() ?: 'application/octet-stream');
    }
}
