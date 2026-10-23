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
