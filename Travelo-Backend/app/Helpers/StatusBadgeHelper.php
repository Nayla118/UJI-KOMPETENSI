<?php

/**
 * Helper functions for status badges
 */

if (!function_exists('payment_status_badge')) {
    function payment_status_badge(?string $status): string
    {
        return match ($status) {
            'paid' => 'bg-green-100 text-green-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'failed' => 'bg-red-100 text-red-800',
            'expired' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}

if (!function_exists('booking_status_badge')) {
    function booking_status_badge(?string $status): string
    {
        return match ($status) {
            'confirmed' => 'bg-green-100 text-green-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'completed' => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
