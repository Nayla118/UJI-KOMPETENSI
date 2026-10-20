<?php

namespace Tests\Unit;

use App\Exceptions\InvalidFirebaseTokenException;
use App\Services\FirebaseTokenVerifier;
use Tests\TestCase;

class FirebaseTokenVerifierTest extends TestCase
{
    private function mapper(): FirebaseTokenVerifier
    {
        return new class extends FirebaseTokenVerifier {
            /**
             * @param array<string, mixed> $claims
             * @return array<string, mixed>
             */
            public function map(array $claims): array
            {
                return $this->identityFromClaims($claims);
            }
        };
    }

    public function test_it_maps_firebase_claims_to_an_identity(): void
    {
        $identity = $this->mapper()->map([
            'sub' => 'firebase-uid-123',
            'email' => 'traveler@example.com',
            'name' => 'Traveler',
            'picture' => 'https://example.com/avatar.png',
            'email_verified' => true,
            'firebase' => ['sign_in_provider' => 'google.com'],
        ]);

        $this->assertSame('firebase-uid-123', $identity['uid']);
        $this->assertSame('traveler@example.com', $identity['email']);
        $this->assertSame('Traveler', $identity['name']);
        $this->assertSame('https://example.com/avatar.png', $identity['photo']);
        $this->assertSame('google.com', $identity['provider']);
        $this->assertTrue($identity['email_verified']);
    }

    public function test_it_uses_display_name_and_defaults_missing_claims_safely(): void
    {
        $identity = $this->mapper()->map([
            'sub' => 'firebase-uid-456',
            'displayName' => 'Display Name',
        ]);

        $this->assertSame('Display Name', $identity['name']);
        $this->assertNull($identity['email']);
        $this->assertNull($identity['photo']);
        $this->assertNull($identity['provider']);
        $this->assertFalse($identity['email_verified']);
    }

    public function test_a_token_without_a_subject_is_rejected(): void
    {
        $this->expectException(InvalidFirebaseTokenException::class);

        $this->mapper()->map(['email' => 'traveler@example.com']);
    }
}
