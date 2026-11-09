<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Privacy retention: identity documents are removed after the configured
// number of days past the visit date. See config/travelo.php.
Schedule::command('travelo:purge-identity-documents')->dailyAt('02:00');

// Push reminder for tomorrow's confirmed trips.
Schedule::command('travelo:send-trip-reminders')->dailyAt('09:00');

// Release seats and promo quota for bookings whose payment window ran out.
// Every minute keeps the hold close to the configured TTL; withoutOverlapping
// protects against a slow Midtrans request stacking runs.
Schedule::command('travelo:expire-unpaid-bookings')
    ->everyMinute()
    ->withoutOverlapping();
