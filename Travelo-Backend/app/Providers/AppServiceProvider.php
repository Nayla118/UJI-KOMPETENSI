<?php

namespace App\Providers;

use App\Contracts\FirebaseIdentityVerifier;
use App\Mail\Transport\BrevoApiTransport;
use App\Models\Booking;
use App\Models\User;
use App\Observers\BookingObserver;
use App\Policies\BookingPolicy;
use App\Policies\DestinationPolicy;
use App\Policies\TicketPolicy;
use App\Services\FirebaseTokenVerifier;
use Illuminate\Support\Facades\Gate;
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

        // Promo quota is released on any cancellation/expiry transition.
        Booking::observe(BookingObserver::class);

        // Centralized authorization policies.
        Gate::define('permission', fn (User $user, string $permission): bool => $user->hasPermission($permission));

        Gate::define('destination.view', [DestinationPolicy::class, 'view']);
        Gate::define('destination.manage', [DestinationPolicy::class, 'manage']);
        Gate::define('destination.scan', [DestinationPolicy::class, 'scan']);

        Gate::define('booking.view', [BookingPolicy::class, 'view']);
        Gate::define('booking.manage', [BookingPolicy::class, 'manage']);

        Gate::define('ticket.redeem', [TicketPolicy::class, 'redeem']);
        Gate::define('ticket.redeem-for-booking', [TicketPolicy::class, 'redeemForBooking']);
    }
}
