<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DestinationController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\TourPackageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Named login route for auth redirect
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// Guest routes (login)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

// Admin routes (protected)
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    // Dashboard
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');

    // Destinations
    Route::match(['delete', 'post'], 'destinations/destroy-all', [DestinationController::class, 'bulkDestroy'])->name('destinations.destroy-all');
    Route::resource('destinations', DestinationController::class);

    // Tour Packages
    Route::match(['delete', 'post'], 'tour-packages/destroy-all', [TourPackageController::class, 'bulkDestroy'])->name('tour-packages.destroy-all');
    Route::resource('tour-packages', TourPackageController::class)->names('tour-packages');

    // Bookings
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.updateStatus');
    Route::patch('/bookings/{booking}/payment', [BookingController::class, 'updatePaymentStatus'])->name('bookings.updatePaymentStatus');

    // Payments
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

    // Users (from Firebase)
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Export Routes
    Route::get('/export/bookings', [ExportController::class, 'exportBookings'])->name('export.bookings');
    Route::get('/export/users', [ExportController::class, 'exportUsers'])->name('export.users');
    Route::get('/export/destinations', [ExportController::class, 'exportDestinations'])->name('export.destinations');
    Route::get('/export/tour-packages', [ExportController::class, 'exportTourPackages'])->name('export.tour-packages');    // Import Routes
    Route::get('/import', [ImportController::class, 'index'])->name('import.index');
    Route::get('/import/destinations', [ImportController::class, 'destinations'])->name('import.destinations');
    Route::match(['get', 'post'], '/import/preview-destinations', [ImportController::class, 'previewDestinations'])->name('import.preview-destinations');
    Route::post('/import/destinations', [ImportController::class, 'importDestinations'])->name('import.destinations.process');
    Route::get('/import/tour-packages', [ImportController::class, 'tourPackages'])->name('import.tour-packages');
    Route::match(['get', 'post'], '/import/preview-tour-packages', [ImportController::class, 'previewTourPackages'])->name('import.preview-tour-packages');
    Route::post('/import/tour-packages', [ImportController::class, 'importTourPackages'])->name('import.tour-packages.process');
    Route::get('/import/download-template/{type}', [ImportController::class, 'downloadTemplate'])->name('import.download-template');

    // Activity Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
});

// Fallback route for serving storage files directly (for symlink issues)
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    
    if (!file_exists($filePath)) {
        abort(404);
    }
    
    return response()->file($filePath);
})->where('path', '.*')->name('storage.serve');
