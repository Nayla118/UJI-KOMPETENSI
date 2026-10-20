<?php

namespace App\Services;

use App\Contracts\FirebaseIdentityVerifier;
use App\Exceptions\InvalidFirebaseTokenException;
use Kreait\Firebase\JWT\IdTokenVerifier;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Throwable;

/**
 * Verifies Firebase ID tokens through the official `kreait/firebase-tokens`
 * verifier.
 *
 * `verifyIdToken()` validates the RS256 signature against Google's public
 * keys, the issuer (https://securetoken.google.com/{project}), the audience
 * (project id), the exp/iat window and the required auth_time claim. It only
 * needs the public project id, not a service-account private key.
 */
class FirebaseTokenVerifier implements FirebaseIdentityVerifier
{
    private ?IdTokenVerifier $verifier = null;

    public function verify(string $idToken): array
    {
        try {
            $token = $this->verifier()->verifyIdToken($idToken);
        } catch (Throwable $e) {
            throw InvalidFirebaseTokenException::because($e->getMessage(), $e);
        }

        // kreait/firebase-tokens returns a SecureToken whose claims are exposed
        // as a plain array via payload(). It intentionally has no claims()
        // method; calling one made every real login fail with a 503.
        return $this->identityFromClaims($token->payload());
    }

    /**
     * @param array<string, mixed> $claims
     * @return array{
     *     uid: string,
     *     email: ?string,
     *     name: ?string,
     *     photo: ?string,
     *     provider: ?string,
     *     email_verified: bool
     * }
     */
    protected function identityFromClaims(array $claims): array
    {
        $uid = $claims['sub'] ?? null;

        if (!is_string($uid) || $uid === '') {
            throw InvalidFirebaseTokenException::because('missing subject (sub) claim');
        }

        $email = $claims['email'] ?? null;
        $name = $claims['name'] ?? $claims['displayName'] ?? null;
        $photo = $claims['picture'] ?? null;
        $firebase = $claims['firebase'] ?? null;

        $provider = is_array($firebase) && isset($firebase['sign_in_provider'])
            ? (string) $firebase['sign_in_provider']
            : null;

        return [
            'uid' => $uid,
            'email' => is_string($email) && $email !== '' ? $email : null,
            'name' => is_string($name) && $name !== '' ? $name : null,
            'photo' => is_string($photo) && $photo !== '' ? $photo : null,
            'provider' => $provider,
            'email_verified' => (bool) ($claims['email_verified'] ?? false),
        ];
    }

    private function verifier(): IdTokenVerifier
    {
        if ($this->verifier !== null) {
            return $this->verifier;
        }

        $projectId = (string) config('firebase.projects.app.project_id', '');

        if ($projectId === '') {
            throw InvalidFirebaseTokenException::because('Firebase project id is not configured');
        }

        // Cache Google's signing keys between requests instead of downloading
        // them on every login. The cache adapter handles key rotation/expiry.
        $cache = new FilesystemAdapter(
            'firebase_id_tokens',
            3600,
            storage_path('framework/cache/firebase')
        );

        return $this->verifier = IdTokenVerifier::createWithProjectIdAndCache($projectId, $cache);
    }
}
