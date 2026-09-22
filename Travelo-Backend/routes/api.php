<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DestinationController;
use App\Http\Controllers\Api\MidtransController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TourPackageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('/destinations', [DestinationController::class, 'index']);
Route::get('/destinations/popular', [DestinationController::class, 'popular']);
Route::get('/destinations/{id}', [DestinationController::class, 'show']);

Route::get('/tour-packages', [TourPackageController::class, 'index']);
Route::get('/tour-packages/{id}', [TourPackageController::class, 'show']);
Route::get('/tour-packages/destination/{destinationId}', [TourPackageController::class, 'byDestination']);

// Authentication
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Midtrans webhook (no auth required)
Route::post('/midtrans/callback', [MidtransController::class, 'callback']);
Route::post('/midtrans/notification', [MidtransController::class, 'notification']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Booking routes
    Route::get('/my-bookings', [BookingController::class, 'myBookings']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    
    // Payment routes - CRITICAL: Call these after payment to update status
    Route::get('/bookings/{bookingId}/payment-status', [PaymentController::class, 'getStatus']);
    Route::post('/bookings/{bookingId}/payment/verify-and-sync', [PaymentController::class, 'verifyAndSync']);
    Route::post('/bookings/{bookingId}/payment/retry-sync', [PaymentController::class, 'retrySync']);
    Route::post('/bookings/{bookingId}/payment/mark-as-paid', [PaymentController::class, 'markAsPaid']);
    
    // Legacy Midtrans endpoints (deprecated, use PaymentController instead)
    Route::post('/bookings/{bookingId}/sync-payment-status', [MidtransController::class, 'syncBookingStatus']);
    Route::get('/bookings/{bookingId}/legacy-payment-status', [MidtransController::class, 'getPaymentStatus']);
    
    Route::post('/payments/create-snap-token', [BookingController::class, 'createSnapToken']);
});
