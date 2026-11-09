<?php

namespace App\Http\Controllers\Concerns;

use App\Models\TourPackage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Shared detail-content handling for the admin and owner package forms so the
 * two panels cannot drift apart.
 */
trait ManagesTourPackageContent
{
    /**
     * Accept either a submitted array (repeater) or one-item-per-line text.
     *
     * @return list<string>
     */
    protected function parseList(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $items = preg_split('/\r\n|\r|\n/', $value) ?: [];
        } else {
            $items = [];
        }

        return array_values(array_filter(
            array_map(fn ($item) => trim((string) $item), $items),
            fn (string $item) => $item !== ''
        ));
    }

    /**
     * Replace the package itinerary with the submitted rows, preserving the
     * order the editor submitted (drag/up-down order in the form).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function syncItinerary(TourPackage $tourPackage, array $rows): void
    {
        $tourPackage->itineraryDays()->delete();

        $sort = 0;
        $fallbackDay = 1;

        foreach ($rows as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));

            if ($title === '' && $description === '') {
                $fallbackDay++;

                continue;
            }

            $dayNumber = (int) ($row['day_number'] ?? 0);
            if ($dayNumber < 1) {
                $dayNumber = $fallbackDay;
            }

            $tourPackage->itineraryDays()->create([
                'day_number' => $dayNumber,
                'title' => $title !== '' ? $title : 'Day '.$dayNumber,
                'description' => $description !== '' ? $description : null,
                'sort_order' => $sort,
            ]);

            $sort++;
            $fallbackDay = $dayNumber + 1;
        }
    }

    /**
     * Persist new gallery uploads on the public disk.
     *
     * @param  array<int, UploadedFile>  $files
     */
    protected function storeGalleryImages(TourPackage $tourPackage, array $files): void
    {
        $sort = (int) $tourPackage->images()->max('sort_order');

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $tourPackage->images()->create([
                'path' => $file->store('tour-packages/gallery', 'public'),
                'sort_order' => ++$sort,
            ]);
        }
    }

    /**
     * Delete gallery photos the editor explicitly asked to remove.
     *
     * @param  array<int, int|string>  $ids
     */
    protected function deleteGalleryImages(TourPackage $tourPackage, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $images = $tourPackage->images()->whereIn('id', $ids)->get();

        foreach ($images as $image) {
            $this->deletePublicFile($image->path);
            $image->delete();
        }
    }

    /**
     * Remove a stored cover/gallery file, but never delete an absolute URL
     * that points outside our disk (used by demo seeds).
     */
    protected function deletePublicFile(?string $path): void
    {
        if (empty($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    /**
     * Delete every gallery file and row for a package (used on destroy).
     */
    protected function deleteAllGalleryImages(TourPackage $tourPackage): void
    {
        foreach ($tourPackage->images()->get() as $image) {
            $this->deletePublicFile($image->path);
            $image->delete();
        }
    }
}
