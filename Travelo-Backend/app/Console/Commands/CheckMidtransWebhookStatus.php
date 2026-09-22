<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckMidtransWebhookStatus extends Command
{
    protected $signature = 'midtrans:check-webhook';
    protected $description = 'Check Midtrans webhook configuration and recent transactions';

    public function handle()
    {
        $this->info('Checking Midtrans Configuration...');
        $this->info('');

        // Check configuration
        $serverKey = config('services.midtrans.server_key');
        $isProduction = config('services.midtrans.is_production', false);
        $merchantId = config('services.midtrans.merchant_id');

        if (!$serverKey) {
            $this->error('❌ Server Key not configured');
            return 1;
        }

        $this->info('✓ Server Key: ' . substr($serverKey, 0, 10) . '...');
        $this->info('✓ Production Mode: ' . ($isProduction ? 'YES' : 'NO (SANDBOX)'));
        $this->info('✓ Merchant ID: ' . $merchantId);
        $this->info('');

        // Check recent bookings
        $this->info('Recent Bookings with Payment Status:');
        $this->info('');

        $bookings = \App\Models\Booking::with('payment', 'user')
            ->latest()
            ->limit(5)
            ->get();

        if ($bookings->isEmpty()) {
            $this->warn('No bookings found');
            return 0;
        }

        foreach ($bookings as $booking) {
            $payment = $booking->payment;
            $statusColor = match ($payment?->payment_status) {
                'paid' => "\033[32m✓\033[0m",  // Green
                'pending' => "\033[33m⏳\033[0m", // Yellow
                'failed' => "\033[31m✗\033[0m",   // Red
                default => '?'
            };

            $orderId = $payment?->midtrans_order_id ?? 'N/A';
            $transId = $payment?->midtrans_transaction_id ?? 'N/A';

            $this->line(
                sprintf("  %s Booking #%d | User: %s | Status: %s | Amount: %s",
                    $statusColor,
                    $booking->id,
                    $booking->user->name,
                    $payment?->payment_status ?? 'none',
                    $payment?->amount ?? '0'
                )
            );

            if ($payment) {
                $this->line("    Order ID: $orderId");
                $this->line("    Transaction ID: $transId");
                if ($payment->paid_at) {
                    $this->line("    Paid At: " . $payment->paid_at->format('Y-m-d H:i:s'));
                }
            }
            $this->line('');
        }

        $this->info('To configure webhook in Midtrans Dashboard:');
        $this->line('1. Login to ' . (config('services.midtrans.is_production') ? 'https://dashboard.midtrans.com' : 'https://dashboard.sandbox.midtrans.com'));
        $this->line('2. Go to Settings → Webhook Configuration');
        $this->line('3. Set Webhook URL to: ' . config('app.url') . '/api/midtrans/callback');
        $this->line('4. Transaction Statuses: Select All');
        $this->line('');
        $this->line('Configuration URL format:');
        $this->line('  ' . config('app.url') . '/api/midtrans/callback');
        $this->line('  ' . config('app.url') . '/api/midtrans/notification');

        return 0;
    }
}
