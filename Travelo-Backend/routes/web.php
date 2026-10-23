<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DestinationController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\TourPackageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\TicketController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return redirect('/admin');
});

/*
|--------------------------------------------------------------------------
| Midtrans Snap callback landings (/booking/success|pending|error)
|--------------------------------------------------------------------------
| These are the URLs passed to Snap in `callbacks`. The Android payment
| WebView intercepts them, so they are only actually loaded when the
| redirect happens outside the app (e-wallet hand-off in another browser /
| wallet webview). Returning 404 there looks exactly like a broken payment,
| so render a small, honest status page instead.
*/
Route::get('/booking/{result}', function (string $result, Request $request) {
    $bookingId = $request->query('booking_id');
    $copy = match ($result) {
        'success' => [
            'title' => __('travelo.callback.success.title'),
            'body' => __('travelo.callback.success.body'),
            'tone' => '#4F7D66',
        ],
        'pending' => [
            'title' => __('travelo.callback.pending.title'),
            'body' => __('travelo.callback.pending.body'),
            'tone' => '#8F5F36',
        ],
        default => [
            'title' => __('travelo.callback.error.title'),
            'body' => __('travelo.callback.error.body'),
            'tone' => '#B03A4E',
        ],
    };

    return response()->view('booking-callback', [
        'title' => $copy['title'],
        'body' => $copy['body'],
        'tone' => $copy['tone'],
        'bookingId' => $bookingId,
        'result' => $result,
    ]);
})->where('result', 'success|pending|error');


// Named login route for auth redirect
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// Guest routes (login)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

// Admin routes (protected). Authentication alone is not enough: these
// management screens expose every user, booking and export, so only the
// admin role may reach them.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
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

    // Verified-visit reviews (admin only; scanner staff must not moderate)
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}/moderate', [ReviewController::class, 'moderate'])->name('reviews.moderate');

    // Activity Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
});

// Gate scanner. Admin covers all destinations; staff only their
// staff_destinations. Keeping this in a separate group lets gate staff use
// the scanner without granting access to the management screens above.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin,staff'])->group(function () {
    Route::get('/scanner', [TicketController::class, 'scanner'])->name('scanner');
    Route::post('/tickets/redeem', [TicketController::class, 'redeem'])->name('tickets.redeem');
    Route::post('/bookings/{booking}/ticket/reissue', [TicketController::class, 'reissue'])->name('tickets.reissue');
    Route::patch('/visitors/{visitor}/document-review', [TicketController::class, 'reviewDocument'])->name('visitors.document-review');
    Route::get('/visitors/{visitor}/document', [TicketController::class, 'downloadDocument'])->name('visitors.document');
});

// Fallback route for serving public storage files directly (for symlink issues).
// Resolve the real path and require it to stay inside storage/app/public so a
// crafted ../ path can never reach private identity documents or .env.
Route::get('/storage/{path}', function ($path) {
    $publicRoot = realpath(storage_path('app/public'));
    $filePath = realpath(storage_path('app/public/' . $path));

    abort_unless($publicRoot !== false && $filePath !== false, 404);

    $prefix = rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    abort_unless(str_starts_with($filePath, $prefix), 404);
    abort_unless(is_file($filePath), 404);

    return response()->file($filePath, [
        'X-Content-Type-Options' => 'nosniff',
    ]);
})->where('path', '.*')->name('storage.serve');
