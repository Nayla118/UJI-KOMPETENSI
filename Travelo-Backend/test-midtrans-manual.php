<?php

/**
 * Manual Midtrans Webhook Test
 * 
 * Copy paste this code to your browser console or run via PHP CLI
 * to test if your webhook is working correctly.
 */

// Configuration
$webhookUrl = 'https://postcartilaginous-erlinda-unchicly.ngrok-free.dev/api/midtrans/callback';

// Test payload (simulating Midtrans notification)
$testPayloads = [
    [
        'name' => '✅ Payment Success (Capture)',
        'data' => [
            'order_id' => 'BOOKING-15',
            'status_code' => '200',
            'transaction_status' => 'capture',
            'transaction_id' => 'manual-test-' . time(),
            'payment_type' => 'credit_card',
            'gross_amount' => '1000000',
            'fraud_status' => 'accept',
            'signature_key' => 'test_signature',
        ]
    ],
    [
        'name' => '⏳ Payment Pending',
        'data' => [
            'order_id' => 'BOOKING-15',
            'status_code' => '201',
            'transaction_status' => 'pending',
            'transaction_id' => 'manual-test-pending-' . time(),
            'payment_type' => 'bank_transfer',
            'gross_amount' => '1000000',
        ]
    ],
    [
        'name' => '❌ Payment Denied',
        'data' => [
            'order_id' => 'BOOKING-15',
            'status_code' => '202',
            'transaction_status' => 'deny',
            'transaction_id' => 'manual-test-deny-' . time(),
            'payment_type' => 'credit_card',
            'gross_amount' => '1000000',
        ]
    ],
];

echo "===========================================\n";
echo "  MANUAL MIDTRANS WEBHOOK TEST\n";
echo "  Testing: {$webhookUrl}\n";
echo "===========================================\n\n";

foreach ($testPayloads as $test) {
    echo "Testing: {$test['name']}\n";
    echo str_repeat('-', 43) . "\n";
    
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test['data']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: {$httpCode}\n";
    echo "Response: {$response}\n";
    
    if ($httpCode === 200) {
        echo "Status: ✅ SUCCESS\n";
    } else {
        echo "Status: ❌ FAILED\n";
    }
    
    echo "\n";
}

echo "===========================================\n";
echo "Test completed!\n";
echo "Check Laravel logs for details\n";
echo "===========================================\n";
