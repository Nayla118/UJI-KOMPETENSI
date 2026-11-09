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

    'payment' => [
        // Every new booking gets payment_expires_at = now + this many minutes.
        // The scheduled travelo:expire-unpaid-bookings command releases the
        // seats once the deadline passes.
        'ttl_minutes' => (int) env('TRAVELO_PAYMENT_TTL_MINUTES', 60),

        // Best-effort call to Midtrans' expire endpoint for a pending
        // transaction. A network failure never blocks the local expiry.
        'expire_midtrans_transaction' => (bool) env('TRAVELO_EXPIRE_MIDTRANS', true),
    ],

    'reports' => [
        // Aggregated dashboard/report payloads are cached for a few minutes.
        // The cache key contains the date range and period, so different
        // filters never share an entry.
        'cache_ttl' => (int) env('TRAVELO_REPORTS_CACHE_TTL', 300),

        // Bounds the cost of one report request (one year by default).
        'max_range_days' => (int) env('TRAVELO_REPORTS_MAX_RANGE_DAYS', 366),
    ],

    'refund' => [
        // Cancellation refund tiers, based on days before the visit date
        // (computed in TRAVELO_TICKET_TIMEZONE).
        //
        //  >= 7 days -> 100%
        //   3-6 days -> 50%
        //    < 3 days -> 0%
        'tiers' => [
            [
                'min_days' => 7,
                'percentage' => 100,
                'label' => 'Full refund (7+ days before trip)',
            ],
            [
                'min_days' => 3,
                'percentage' => 50,
                'label' => 'Partial refund (3-6 days before trip)',
            ],
            [
                'min_days' => 0,
                'percentage' => 0,
                'label' => 'No refund (less than 3 days before trip)',
            ],
        ],

        // When true, approved cancellations are refunded automatically via
        // Midtrans Refund API (queued job with idempotency key). When false
        // (default), refund stays manual: admin marks it refunded after the
        // money has actually been returned.
        'auto_refund' => (bool) env('TRAVELO_AUTO_REFUND', true),
    ],
];
