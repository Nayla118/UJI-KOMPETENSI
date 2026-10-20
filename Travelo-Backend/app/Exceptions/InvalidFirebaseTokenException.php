<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class InvalidFirebaseTokenException extends RuntimeException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self('The Firebase ID token could not be verified: '.$reason, 0, $previous);
    }
}
