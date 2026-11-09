<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A promo code was rejected by the server. The reason code is stable and
 * used by clients to show a specific message, while the message is a
 * human-readable fallback.
 */
class PromoValidationException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message
    ) {
        parent::__construct($message);
    }
}
