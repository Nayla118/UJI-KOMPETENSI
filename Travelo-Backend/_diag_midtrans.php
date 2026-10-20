<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Services\MidtransService;

$svc = new MidtransService();

foreach ([30, 29, 12] as $id) {
    $booking = Booking::find($id);
    if (!$booking) {
        echo "booking#{$id} not found\n";
        continue;
    }
    echo "== booking#{$id} (local payment_status={$booking->payment_status}) ==\n";
    foreach ($svc->getOrderCandidates($booking) as $orderId) {
        try {
            $data = $svc->verifyPayment($orderId);
            $local = $svc->mapMidtransStatusToLocalStatus($data['status'] ?? null, $data['fraud_status'] ?? null);
            $raw = $data['raw'] ?? [];
            echo sprintf(
                "  %s -> midtrans=%s fraud=%s type=%s settlement_time=%s local=%s\n",
                $orderId,
                $data['status'] ?? '-',
                $data['fraud_status'] ?? '-',
                $data['payment_type'] ?? ($raw['payment_type'] ?? '-'),
                $raw['settlement_time'] ?? ($raw['transaction_time'] ?? '-'),
                $local
            );
        } catch (\Exception $e) {
            echo "  {$orderId} -> ERROR: {$e->getMessage()}\n";
        }
    }
}
