<?php

namespace App\Contracts;

interface FirebaseIdentityVerifier
{
    /**
     * Verify a Firebase ID token and return the trusted identity claims.
     *
     * Implementations MUST verify the JWT signature, issuer, audience and
     * validity window. Decoding the payload without verification is never
     * acceptable for authentication.
     *
     * @return array{
     *     uid: string,
     *     email: ?string,
     *     name: ?string,
     *     photo: ?string,
     *     provider: ?string,
     *     email_verified: bool
     * }
     *
     * @throws \App\Exceptions\InvalidFirebaseTokenException
     */
    public function verify(string $idToken): array;
}
