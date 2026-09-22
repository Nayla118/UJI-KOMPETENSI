<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Booking;
use App\Models\Payment;

// Get the most recent bookings
$bookings = Booking::with('payment','user','tourPackage')
    ->latest()
    ->limit(5)
    ->get();

echo "\n=== RECENT BOOKINGS STATUS ===\n\n";

foreach ($bookings as $booking) {
    echo "Booking ID: " . $booking->id . "\n";
    echo "  User: " . ($booking->user->name ?? 'N/A') . "\n";
    echo "  Package: " . ($booking->tourPackage->title ?? 'N/A') . "\n";
    echo "  Booking Status: " . $booking->booking_status . "\n";
    echo "  Payment Status: " . $booking->payment_status . "\n";
    
    if ($booking->payment) {
        echo "  Payment Record:\n";
        echo "    - Status: " . $booking->payment->payment_status . "\n";
        echo "    - Amount: Rp " . number_format($booking->payment->amount, 0, ',', '.') . "\n";
        echo "    - Method: " . ($booking->payment->payment_method ?? 'pending') . "\n";
        echo "    - Transaction ID: " . ($booking->payment->midtrans_transaction_id ?? 'null') . "\n";
        echo "    - Order ID: " . ($booking->payment->midtrans_order_id ?? 'null') . "\n";
        echo "    - Paid At: " . ($booking->payment->paid_at ?? 'null') . "\n";
        echo "    - Created: " . $booking->payment->created_at . "\n";
        echo "    - Updated: " . $booking->payment->updated_at . "\n";
    } else {
        echo "  Payment Record: MISSING!\n";
    }
    echo "\n";
}

echo "Total pending payments: " . 
    Payment::where('payment_status', 'pending')->count() . "\n";
echo "Total paid payments: " . 
    Payment::where('payment_status', 'paid')->count() . "\n";
echo "\n";
?>
