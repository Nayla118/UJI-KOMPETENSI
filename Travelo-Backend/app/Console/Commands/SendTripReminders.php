<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendTripReminders extends Command
{
    protected $signature = 'travelo:send-trip-reminders {--days=1 : Days before the visit date}';

    protected $description = 'Send a push reminder for paid, confirmed trips happening in the next N days';

    public function handle(PushNotificationService $push): int
    {
        $days = max(1, (int) $this->option('days'));
        $date = now()
            ->timezone((string) config('travelo.ticket.timezone', config('app.timezone')))
            ->addDays($days)
            ->toDateString();

        $bookings = Booking::query()
            ->with(['tourPackage.destination'])
            ->whereDate('booking_date', $date)
            ->where('payment_status', 'paid')
            ->where('booking_status', 'confirmed')
            ->where('ticket_status', Booking::TICKET_UNUSED)
            ->get();

        $sent = 0;

        foreach ($bookings as $booking) {
            // Idempotent even if the scheduler or an operator runs the command
            // twice on the same day.
            $key = 'travelo:trip-reminder:'.$booking->id.':'.$date;

            if (! Cache::add($key, true, now()->addDays(3))) {
                continue;
            }

            $push->notifyTripReminder($booking);
            $sent++;
        }

        $this->info("Trip reminders processed for {$date}: {$sent} booking(s).");

        return self::SUCCESS;
    }
}
