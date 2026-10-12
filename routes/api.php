<?php

use App\Http\Controllers\Api\V1\AdminToolsController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PublicMiscController;
use App\Http\Controllers\Api\V1\ScannerController;
use App\Http\Controllers\Api\V1\StaffLookupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API v1 (F-API-001…012, Phase 15) — same Actions/Queries as the web,
| uniform {success:false, code, error} envelope (bootstrap/app.php).
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('json.limit')->group(function () {
    // Public
    Route::get('/events', [EventController::class, 'index'])->middleware('throttle:availability');
    Route::get('/events/{event}/availability', [EventController::class, 'availability'])->middleware('throttle:availability');
    Route::post('/availability/check', [EventController::class, 'check'])->middleware('throttle:availability');
    Route::post('/release-expired', [EventController::class, 'releaseExpired'])->middleware('throttle:availability');
    Route::post('/person-count', [EventController::class, 'personCount'])->middleware('throttle:person-count');

    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:orders');
    Route::match(['GET', 'POST'], '/orders/status', [OrderController::class, 'status'])->middleware('throttle:status');
    Route::get('/invoice/{reference}', [OrderController::class, 'invoice'])->middleware('throttle:invoice');

    Route::post('/payments/verify', [PaymentController::class, 'verify'])->middleware('throttle:verify');
    Route::post('/payments/sadad/restart', [PaymentController::class, 'restart'])->middleware('throttle:pay-again');
    Route::post('/payment-errors', [PublicMiscController::class, 'paymentError'])->middleware('throttle:payment-errors');

    // Staff (session, kiosk or Sanctum token) — R-138
    Route::prefix('staff')->group(function () {
        Route::middleware('staff:scanner.admit')->group(function () {
            Route::post('/scanner/checkin', [ScannerController::class, 'checkin']);
            Route::post('/scanner/lookup', [ScannerController::class, 'lookup']);
            Route::post('/scanner/search', [ScannerController::class, 'search']);
            Route::post('/scanner/history', [ScannerController::class, 'history']);
            Route::post('/scanner/sync', [ScannerController::class, 'sync']);
        });
        Route::post('/scanner/manual', [ScannerController::class, 'manual'])->middleware('staff:checkin.manual');
        Route::post('/scanner/reset', [ScannerController::class, 'reset'])->middleware('staff:checkin.reset');
        Route::get('/gate-board', [ScannerController::class, 'gateBoard'])->middleware('staff:gate.view');
        Route::get('/customers/lookup', [StaffLookupController::class, 'customers'])->middleware(['staff:pos.sell', 'throttle:staff-lookup']);
        Route::post('/attendance', AttendanceController::class)->middleware('staff:attendance.view');
    });

    // Admin tools (T-096: 401 guest, 403 non-admin, 204 OPTIONS)
    Route::prefix('admin')->middleware('staff:settings.manage')->group(function () {
        Route::post('/qr/generate', [AdminToolsController::class, 'generateQr']);
        Route::post('/qr/backfill', [AdminToolsController::class, 'backfillQr']);
        Route::post('/qr/regenerate', [AdminToolsController::class, 'regenerateQr']);
        Route::post('/relay', [AdminToolsController::class, 'relay'])->withoutMiddleware('json.limit')->middleware('json.limit:200000');
        Route::post('/sadad/diagnose', [AdminToolsController::class, 'diagnose']);
        Route::post('/sadad/transaction', [AdminToolsController::class, 'transaction']);
    });
    Route::post('/admin/orders/{order}/cancel', [AdminToolsController::class, 'cancel'])->middleware('staff:orders.cancel');
    Route::post('/admin/stock/recount', [AdminToolsController::class, 'recount'])->middleware('staff:stock.recount');
});
