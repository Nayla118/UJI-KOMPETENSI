<?php

/**
 * Travelo domain settings that must stay out of controller code.
 *
 * Identity documents are deliberately stored on the private `local` disk
 * (storage/app/private). They are never exposed through /storage.
 */
return [

    'privacy_policy_version' => env('TRAVELO_PRIVACY_VERSION', '2026-10-22'),

    'identity' => [
        // Private disk. Do not switch this to `public`.
        'disk' => env('TRAVELO_IDENTITY_DISK', 'local'),

        // 5 MB. Enforced server-side in addition to the client hint.
        'max_kb' => (int) env('TRAVELO_IDENTITY_MAX_KB', 5120),

        // Content-based validation (Laravel inspects the file, not the name).
        'mimes' => ['jpg', 'jpeg', 'png', 'pdf'],

        // Documents are deleted this many days after the visit date. A daily
        // command (travelo:purge-identity-documents) enforces it.
        'retention_days' => (int) env('TRAVELO_IDENTITY_RETENTION_DAYS', 90),
    ],

    'ticket' => [
        // A ticket is valid for the whole visit date in this timezone.
        'timezone' => env('TRAVELO_TICKET_TIMEZONE', 'Asia/Jakarta'),
    ],
];
