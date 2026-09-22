#!/usr/bin/env php
<?php

/**
 * Midtrans Webhook Test with Real Booking
 * 
 * Usage: php test-webhook-real.php
 * 
 * This creates a test booking and simulates a real Midtrans webhook
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Booking;
use App\Models\Payment;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Support\Facades\Http;

echo "===========================================\n";
echo "  MIDTRANS WEBHOOK TEST (REAL BOOKING)\n";
echo "===========================================\n\n";

// Find or create test user
$user = User::first();
if (!$user) {
    echo "❌ No users found. Please create a user first.\n";
    exit(1);
}

echo "Using user: {$user->name} (ID: {$user->id})\n";

// Find or create test tour package
$tourPackage = TourPackage::first();
if (!$tourPackage) {
    echo "❌ No tour packages found. Please create a tour package first.\n";
    exit(1);
}

echo "Using tour package: {$tourPackage->title} (ID: {$tourPackage->id})\n\n";

// Create test booking
echo "Creating test booking...\n";
$booking = Booking::create([
    'user_id' => $user->id,
    'tour_package_id' => $tourPackage->id,
    'booking_date' => now()->addDays(30),
    'people_count' => 2,
    'total_price' => $tourPackage->price * 2,
    'payment_status' => 'pending',
    'booking_status' => 'pending',
]);

echo "✅ Booking created: ID {$booking->id}\n\n";

// Get base URL
$baseUrl = config('app.url');
$webhookUrl = $baseUrl . '/api/midtrans/callback';

echo "Testing webhook with real booking:\n";
echo "→ {$webhookUrl}\n\n";

// Test successful payment
echo "Simulating successful payment...\n";
echo "-------------------------------------------\n";

$payload = [
    'order_id' => 'BOOKING-' . $booking->id,
    'status_code' => '200',
    'transaction_status' => 'capture',
    'transaction_id' => 'test-success-' . time(),
    'payment_type' => 'credit_card',
    'gross_amount' => $booking->total_price,
    'fraud_status' => 'accept',
];

try {
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])->post($webhookUrl, $payload);
    
    $statusCode = $response->status();
    $body = $response->json();
    
    echo "Status Code: {$statusCode}\n";
    echo "Response: " . json_encode($body, JSON_PRETTY_PRINT) . "\n\n";
    
    if ($statusCode === 200) {
        echo "✅ Webhook received successfully!\n\n";
        
        // Refresh booking from database
        $booking->refresh();
        $payment = $booking->payment;
        
        echo "Booking Status After Webhook:\n";
        echo "-------------------------------------------\n";
        echo "Booking ID: {$booking->id}\n";
        echo "Payment Status: {$booking->payment_status}\n";
        echo "Booking Status: {$booking->booking_status}\n";
        
        if ($payment) {
            echo "\nPayment Record:\n";
            echo "-------------------------------------------\n";
            echo "Payment ID: {$payment->id}\n";
            echo "Payment Status: {$payment->payment_status}\n";
            echo "Payment Method: {$payment->payment_method}\n";
            echo "Amount: Rp " . number_format($payment->amount, 0, ',', '.') . "\n";
            echo "Midtrans Transaction ID: {$payment->midtrans_transaction_id}\n";
        }
        
        // Verify success
        echo "\n===========================================\n";
        if ($booking->payment_status === 'paid' && $booking->booking_status === 'confirmed') {
            echo "✅ SUCCESS! Payment and booking status updated correctly!\n";
        } else {
            echo "⚠️  WARNING: Status not updated as expected\n";
            echo "Expected: payment_status='paid', booking_status='confirmed'\n";
            echo "Got: payment_status='{$booking->payment_status}', booking_status='{$booking->booking_status}'\n";
        }
        echo "===========================================\n";
    } else {
        echo "❌ FAILED (Status: {$statusCode})\n";
    }
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n";
echo "Next Steps:\n";
echo "1. Check admin panel: http://127.0.0.1:8000/admin/bookings/{$booking->id}\n";
echo "2. Check Laravel logs: storage/logs/laravel.log\n";
echo "3. Test in Midtrans Dashboard with this booking ID: {$booking->id}\n";
