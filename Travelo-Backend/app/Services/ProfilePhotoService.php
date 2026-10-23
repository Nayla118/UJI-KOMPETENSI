<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Public profile photo storage.
 *
 * Only raster images are accepted. Files are stored under randomized names on
 * the public disk (never the customer's original filename), and the previous
 * locally uploaded file is removed only after the new database value is saved.
 */
class ProfilePhotoService
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function store(User $user, UploadedFile $file): User
    {
        $mime = strtolower((string) $file->getMimeType());
        $extension = self::EXTENSIONS[$mime] ?? null;

        if ($extension === null) {
            throw new \InvalidArgumentException('Unsupported profile photo type.');
        }

        $previousPath = $user->getRawOriginal('photo');
        $path = sprintf(
            '%s/%d/%s.%s',
            User::PROFILE_PHOTO_DIRECTORY,
            $user->id,
            Str::random(48),
            $extension
        );

        $stored = Storage::disk('public')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        if ($stored === false) {
            throw new \RuntimeException('Unable to store the profile photo.');
        }

        try {
            $user->forceFill(['photo' => $path])->save();
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }

        if (is_string($previousPath)
            && str_starts_with($previousPath, User::PROFILE_PHOTO_DIRECTORY.'/')
            && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        Log::info('Profile photo updated', [
            'user_id' => $user->id,
            'mime' => $mime,
            'size' => (int) $file->getSize(),
        ]);

        return $user->refresh();
    }
}
