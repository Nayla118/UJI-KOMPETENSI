<?php

namespace App\Console\Commands;

use App\Services\BookingExpiryService;
use Illuminate\Console\Command;

class ExpireUnpaidBookings extends Command
{
    protected $signature = 'travelo:expire-unpaid-bookings';

    protected $description = 'Cancel unpaid bookings whose payment window has passed and release their seats';

    public function handle(BookingExpiryService $expiry): int
    {
        $result = $expiry->expireDue();

        $this->info(
            "Unpaid bookings expired: {$result['expired']}; skipped: {$result['skipped']}."
        );

        return self::SUCCESS;
    }
}
