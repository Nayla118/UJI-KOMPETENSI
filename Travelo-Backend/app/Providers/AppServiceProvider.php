<?php

namespace App\Providers;

use App\Contracts\FirebaseIdentityVerifier;
use App\Mail\Transport\BrevoApiTransport;
use App\Services\FirebaseTokenVerifier;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Authentication must never trust a client-decoded JWT. The official
        // Firebase Admin SDK verifies signature, issuer, audience and expiry.
        $this->app->bind(FirebaseIdentityVerifier::class, FirebaseTokenVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Brevo transactional email over its HTTP API (API key, not SMTP key).
        Mail::extend('brevo-api', function (array $config) {
            return new BrevoApiTransport((string) ($config['key'] ?? ''));
        });
    }
}
