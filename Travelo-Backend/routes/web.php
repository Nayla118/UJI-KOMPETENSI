<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AvailabilityController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\ChangeRequestController;
use App\Http\Controllers\Admin\DestinationController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\OwnerController as AdminOwnerController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\TourPackageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Owner\OwnerAvailabilityController;
use App\Http\Controllers\Owner\OwnerBookingController;
use App\Http\Controllers\Owner\OwnerChangeRequestController;
use App\Http\Controllers\Owner\OwnerController as OwnerPortalController;
use App\Http\Controllers\Owner\OwnerDestinationController;
use App\Http\Controllers\Owner\OwnerPaymentController;
use App\Http\Controllers\Owner\OwnerProfileController;
use App\Http\Controllers\Owner\OwnerPromoCodeController;
use App\Http\Controllers\Owner\OwnerReportController;
use App\Http\Controllers\Owner\OwnerStaffController;
use App\Http\Controllers\Owner\OwnerTicketController;
use App\Http\Controllers\Owner\OwnerTourPackageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
// global admin permission may reach them.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'permission:admin.manage'])->group(function () {
    // Dashboard
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');

    // Analytics reports (admin only). CSV is the canonical export; the PDF
    // option is a print-ready HTML document.
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/data', [ReportController::class, 'data'])->name('reports.data');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    // Destinations (admin manages ALL destinations)
    Route::match(['delete', 'post'], 'destinations/destroy-all', [DestinationController::class, 'bulkDestroy'])->name('destinations.destroy-all');
    Route::resource('destinations', DestinationController::class);

    // Tour Packages (admin manages ALL packages)
    Route::match(['delete', 'post'], 'tour-packages/destroy-all', [TourPackageController::class, 'bulkDestroy'])->name('tour-packages.destroy-all');
    Route::resource('tour-packages', TourPackageController::class)->names('tour-packages');
    Route::get('/tour-packages/{tourPackage}/availability', [AvailabilityController::class, 'index'])
        ->name('tour-packages.availability');
    Route::post('/tour-packages/{tourPackage}/availability', [AvailabilityController::class, 'store'])
        ->name('tour-packages.availability.store');
    Route::delete('/tour-packages/{tourPackage}/availability/{availability}', [AvailabilityController::class, 'destroy'])
        ->name('tour-packages.availability.destroy');

    // Promo codes / vouchers (admin only)
    Route::patch('/promos/{promo}/toggle', [PromoCodeController::class, 'toggle'])->name('promos.toggle');
    Route::resource('promos', PromoCodeController::class)->parameters(['promos' => 'promo']);

    // Bookings (admin sees ALL bookings)
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.updateStatus');
    Route::patch('/bookings/{booking}/payment', [BookingController::class, 'updatePaymentStatus'])->name('bookings.updatePaymentStatus');

    // Cancellation approvals (admin approves ALL)
    Route::get('/change-requests', [ChangeRequestController::class, 'index'])->name('change-requests.index');
    Route::patch('/change-requests/{changeRequest}/approve', [ChangeRequestController::class, 'approve'])->name('change-requests.approve');
    Route::patch('/change-requests/{changeRequest}/reject', [ChangeRequestController::class, 'reject'])->name('change-requests.reject');
    Route::patch('/change-requests/{changeRequest}/refund', [ChangeRequestController::class, 'markRefunded'])->name('change-requests.refund');
    Route::patch('/change-requests/{changeRequest}/refund/retry', [ChangeRequestController::class, 'retryRefund'])->name('change-requests.refund-retry');

    // Payments (admin sees ALL)
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

    // Verified-visit reviews (admin only; scanner owner must not moderate)
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}/moderate', [ReviewController::class, 'moderate'])->name('reviews.moderate');

    // Activity Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
});

// Owner portal (destinations assigned via destination_user). Admin may also
// access it. Every route is guarded by a named permission; the role only
// decides which permissions are granted.
Route::prefix('owner')->name('owner.')->middleware(['auth', 'active', 'permission:portal.access'])->group(function () {
    Route::get('/', [OwnerPortalController::class, 'dashboard'])->name('dashboard');

    // Own destinations. Manager can view/edit but not create/delete.
    Route::resource('destinations', OwnerDestinationController::class)
        ->only(['index', 'show'])->middleware('permission:destinations.view');
    Route::resource('destinations', OwnerDestinationController::class)
        ->only(['create', 'store'])->middleware('permission:destinations.create');
    Route::resource('destinations', OwnerDestinationController::class)
        ->only(['edit', 'update'])->middleware('permission:destinations.update');
    Route::delete('/destinations/{destination}', [OwnerDestinationController::class, 'destroy'])
        ->name('destinations.destroy')->middleware('permission:destinations.delete');

    // Own tour packages. Manager can view/edit but not create/delete.
    Route::resource('tour-packages', OwnerTourPackageController::class)
        ->only(['index', 'show'])->middleware('permission:packages.view');
    Route::resource('tour-packages', OwnerTourPackageController::class)
        ->only(['create', 'store'])->middleware('permission:packages.create');
    Route::resource('tour-packages', OwnerTourPackageController::class)
        ->only(['edit', 'update'])->middleware('permission:packages.update');
    Route::delete('/tour-packages/{tourPackage}', [OwnerTourPackageController::class, 'destroy'])
        ->name('tour-packages.destroy')->middleware('permission:packages.delete');

    Route::middleware('permission:availability.manage')->group(function () {
        Route::get('/tour-packages/{tourPackage}/availability', [OwnerAvailabilityController::class, 'index'])->name('tour-packages.availability');
        Route::post('/tour-packages/{tourPackage}/availability', [OwnerAvailabilityController::class, 'store'])->name('tour-packages.availability.store');
        Route::delete('/tour-packages/{tourPackage}/availability/{availability}', [OwnerAvailabilityController::class, 'destroy'])->name('tour-packages.availability.destroy');
    });

    // Own bookings
    Route::get('/bookings', [OwnerBookingController::class, 'index'])
        ->name('bookings.index')->middleware('permission:bookings.view');
    Route::get('/bookings/{booking}', [OwnerBookingController::class, 'show'])
        ->name('bookings.show')->middleware('permission:bookings.view');

    // Own change requests (cancellation approvals for own destinations)
    Route::middleware('permission:change_requests.decide')->group(function () {
        Route::get('/change-requests', [OwnerChangeRequestController::class, 'index'])->name('change-requests.index');
        Route::patch('/change-requests/{changeRequest}/approve', [OwnerChangeRequestController::class, 'approve'])->name('change-requests.approve');
        Route::patch('/change-requests/{changeRequest}/reject', [OwnerChangeRequestController::class, 'reject'])->name('change-requests.reject');
        Route::patch('/change-requests/{changeRequest}/refund', [OwnerChangeRequestController::class, 'markRefunded'])->name('change-requests.refund')->middleware('permission:refunds.manage');
    });

    // Scanner for own destinations + own scan history.
    Route::get('/scanner', [OwnerTicketController::class, 'scanner'])
        ->name('scanner')->middleware('permission:tickets.redeem');
    Route::post('/tickets/redeem', [OwnerTicketController::class, 'redeem'])
        ->name('tickets.redeem')->middleware('permission:tickets.redeem');
    Route::get('/scanner/history', [OwnerTicketController::class, 'history'])
        ->name('scanner.history')->middleware('permission:scanner.history');

    // Promo management (owner only; scoped to own destinations/packages).
    Route::middleware('permission:promos.manage')->group(function () {
        Route::get('/promos', [OwnerPromoCodeController::class, 'index'])->name('promos.index');
        Route::get('/promos/create', [OwnerPromoCodeController::class, 'create'])->name('promos.create');
        Route::post('/promos', [OwnerPromoCodeController::class, 'store'])->name('promos.store');
        Route::get('/promos/{promo}/edit', [OwnerPromoCodeController::class, 'edit'])->name('promos.edit');
        Route::patch('/promos/{promo}', [OwnerPromoCodeController::class, 'update'])->name('promos.update');
        Route::patch('/promos/{promo}/toggle', [OwnerPromoCodeController::class, 'toggle'])->name('promos.toggle');
        Route::delete('/promos/{promo}', [OwnerPromoCodeController::class, 'destroy'])->name('promos.destroy');
    });

    // Payments for own destinations (owner and manager; never gate_staff).
    Route::middleware('permission:payments.view')->group(function () {
        Route::get('/payments', [OwnerPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [OwnerPaymentController::class, 'show'])->name('payments.show');
    });

    // Owner-managed staff (manager + gate_staff only). Deactivate, never delete.
    Route::middleware('permission:staff.manage')->group(function () {
        Route::get('/staff', [OwnerStaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [OwnerStaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [OwnerStaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}/edit', [OwnerStaffController::class, 'edit'])->name('staff.edit');
        Route::patch('/staff/{staff}', [OwnerStaffController::class, 'update'])->name('staff.update');
        Route::patch('/staff/{staff}/toggle-active', [OwnerStaffController::class, 'toggleActive'])->name('staff.toggle-active');
    });

    // Owner profile
    Route::get('/profile', [OwnerProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profile', [OwnerProfileController::class, 'update'])->name('profile.update');

    // Owner check-in / revenue report.
    Route::get('/laporan', [OwnerReportController::class, 'index'])
        ->name('laporan.index')->middleware('permission:reports.checkin,reports.revenue');
    Route::get('/laporan/export', [OwnerReportController::class, 'export'])
        ->name('laporan.export')->middleware('permission:reports.checkin,reports.revenue');
});

// Gate/scanner endpoints. The scanner is open to admin, owner, manager and
// gate_staff; reissue and document review remain restricted by permission.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'active'])->group(function () {
    Route::middleware('permission:tickets.redeem')->group(function () {
        Route::get('/scanner', [TicketController::class, 'scanner'])->name('scanner');
        Route::post('/tickets/redeem', [TicketController::class, 'redeem'])->name('tickets.redeem');
    });

    Route::post('/bookings/{booking}/ticket/reissue', [TicketController::class, 'reissue'])
        ->name('tickets.reissue')->middleware('permission:tickets.reissue');

    Route::middleware('permission:visitor_documents.view')->group(function () {
        Route::patch('/visitors/{visitor}/document-review', [TicketController::class, 'reviewDocument'])->name('visitors.document-review');
        Route::get('/visitors/{visitor}/document', [TicketController::class, 'downloadDocument'])->name('visitors.document');
    });

    // Owner management (admin only).
    Route::middleware('permission:admin.owners.manage')->group(function () {
        Route::get('/owners', [AdminOwnerController::class, 'index'])->name('owners.index');
        Route::get('/owners/create', [AdminOwnerController::class, 'create'])->name('owners.create');
        Route::post('/owners', [AdminOwnerController::class, 'store'])->name('owners.store');
        Route::get('/owners/{owner}/edit', [AdminOwnerController::class, 'edit'])->name('owners.edit');
        Route::patch('/owners/{owner}', [AdminOwnerController::class, 'update'])->name('owners.update');
        Route::patch('/owners/{owner}/toggle-active', [AdminOwnerController::class, 'toggleActive'])->name('owners.toggle-active');
        Route::patch('/owners/{owner}/reset-password', [AdminOwnerController::class, 'resetPassword'])->name('owners.reset-password');
        Route::delete('/owners/{owner}', [AdminOwnerController::class, 'destroy'])->name('owners.destroy');
    });
});

// Fallback route for serving public storage files directly (for symlink issues).
// Resolve the real path and require it to stay inside storage/app/public so a
// crafted ../ path can never reach private identity documents or .env.
Route::get('/storage/{path}', function ($path) {
    $publicRoot = realpath(storage_path('app/public'));
    $filePath = realpath(storage_path('app/public/'.$path));

    abort_unless($publicRoot !== false && $filePath !== false, 404);

    $prefix = rtrim($publicRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

    abort_unless(str_starts_with($filePath, $prefix), 404);
    abort_unless(is_file($filePath), 404);

    return response()->file($filePath, [
        'X-Content-Type-Options' => 'nosniff',
    ]);
})->where('path', '.*')->name('storage.serve');
