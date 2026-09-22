#!/usr/bin/env php
<?php

/**
 * Midtrans Webhook Test Script
 * 
 * Usage: php test-webhook.php
 * 
 * This script simulates a Midtrans webhook notification
 * to test if your endpoint is working correctly.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

echo "===========================================\n";
echo "  MIDTRANS WEBHOOK TEST\n";
echo "===========================================\n\n";

// Get the base URL
$baseUrl = config('app.url');
$webhookUrl = $baseUrl . '/api/midtrans/callback';

echo "Testing webhook URL:\n";
echo "→ {$webhookUrl}\n\n";

// Test payload (successful payment)
$testPayloads = [
    [
        'name' => 'Successful Payment (Capture)',
        'data' => [
            'order_id' => 'BOOKING-TEST-1',
            'status_code' => '200',
            'transaction_status' => 'capture',
            'transaction_id' => 'test-capture-123',
            'payment_type' => 'credit_card',
            'gross_amount' => '1000000',
            'fraud_status' => 'accept',
        ]
    ],
    [
        'name' => 'Pending Payment',
        'data' => [
            'order_id' => 'BOOKING-TEST-2',
            'status_code' => '201',
            'transaction_status' => 'pending',
            'transaction_id' => 'test-pending-123',
            'payment_type' => 'credit_card',
            'gross_amount' => '1000000',
        ]
    ],
    [
        'name' => 'Denied Payment',
        'data' => [
            'order_id' => 'BOOKING-TEST-3',
            'status_code' => '202',
            'transaction_status' => 'deny',
            'transaction_id' => 'test-deny-123',
            'payment_type' => 'credit_card',
            'gross_amount' => '1000000',
        ]
    ],
];

foreach ($testPayloads as $test) {
    echo "Testing: {$test['name']}\n";
    echo "-------------------------------------------\n";
    
    try {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($webhookUrl, $test['data']);
        
        $statusCode = $response->status();
        $body = $response->json();
        
        echo "Status Code: {$statusCode}\n";
        echo "Response: " . json_encode($body, JSON_PRETTY_PRINT) . "\n";
        
        if ($statusCode === 200) {
            echo "✅ SUCCESS\n";
        } else {
            echo "❌ FAILED (Status: {$statusCode})\n";
        }
    } catch (\Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "===========================================\n";
echo "Test completed!\n";
echo "Check storage/logs/laravel.log for details\n";
echo "===========================================\n";
