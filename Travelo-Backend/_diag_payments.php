<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;
use App\Models\Booking;

$rows = Payment::orderByDesc('updated_at')->limit(10)->get();
foreach ($rows as $p) {
    echo sprintf(
        "payment#%d booking#%d status=%s method=%s channel=%s tx_status=%s order=%s paid_at=%s updated=%s history=%s\n",
        $p->id,
        $p->booking_id,
        $p->payment_status,
        $p->payment_method ?? '-',
        $p->payment_channel ?? '-',
        $p->transaction_status ?? '-',
        $p->midtrans_order_id ?? '-',
        $p->paid_at ?? '-',
        $p->updated_at,
        json_encode($p->order_history ?? [])
    );
    $b = Booking::find($p->booking_id);
    if ($b) {
        echo "   booking: payment_status={$b->payment_status} booking_status={$b->booking_status} total={$b->total_price}\n";
    }
}

echo "---- recent bookings ----\n";
foreach (Booking::orderByDesc('id')->limit(5)->get() as $b) {
    echo sprintf("booking#%d payment_status=%s booking_status=%s total=%s created=%s\n", $b->id, $b->payment_status, $b->booking_status, $b->total_price, $b->created_at);
}
