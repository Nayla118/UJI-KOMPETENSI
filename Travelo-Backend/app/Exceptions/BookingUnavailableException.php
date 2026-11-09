<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside the booking transaction when the requested date has been
 * closed or no longer has enough seats. The controller turns it into a 409
 * so the app can show a helpful "choose another date" message.
 */
class BookingUnavailableException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason = 'unavailable',
        public readonly ?int $remaining = null
    ) {
        parent::__construct($message);
    }
}
