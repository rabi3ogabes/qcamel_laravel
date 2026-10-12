<?php

use App\Http\Controllers\Auth\AccountSecurityController;
use App\Http\Controllers\Auth\PasscodeController;
use App\Http\Controllers\Auth\StaffLogoutController;
use App\Http\Controllers\Payments\SadadCallbackController;
use App\Http\Controllers\Payments\SadadRedirectController;
use App\Http\Controllers\Payments\SadadWebhookController;
use App\Http\Controllers\Public\InvoiceDownloadController;
use App\Http\Controllers\Public\PresenceController;
use App\Http\Controllers\Public\SeoController;
use App\Http\Controllers\Staff\HolderQrController;
use App\Http\Controllers\Staff\ReceiptImageController;
use App\Http\Controllers\Webhooks\MailEventsController;
use App\Http\Controllers\Webhooks\N8nResponseController;
use App\Livewire\Public;
use App\Livewire\Staff;
use App\Support\PublicSettings;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website (F-PUB-001, Part 1.5)
|--------------------------------------------------------------------------
*/
Route::middleware('track')->group(function () {
    Route::get('/', Public\Home::class)->name('home');
    Route::get('/events/{event}/tickets', Public\TicketSelection::class)->name('tickets.select');
    Route::get('/checkout', Public\Checkout::class)->name('checkout');
    Route::get('/booking', Public\BookingLookup::class)->name('booking.lookup')->middleware('throttle:booking-lookup');
});

Route::permanentRedirect('/tickets', '/');
Route::get('/tickets/{event}', fn (string $event) => redirect()->route('tickets.select', $event, 301));
Route::redirect('/sadad-redirect', '/checkout');

Route::middleware('noindex')->group(function () {
    Route::get('/payment/result', Public\PaymentResult::class)->name('payment.result');
    // F-PAY-027: legacy return path — GET renders the result page, POST runs the gateway callback.
    Route::get('/sadad-callback', Public\PaymentResult::class)->name('payment.legacy');
    Route::get('/payment/redirect/{reference}', SadadRedirectController::class)->name('payment.redirect');
    Route::get('/confirmation/{reference?}', Public\Confirmation::class)->name('confirmation');
    Route::middleware('throttle:invoice')->group(function () {
        Route::get('/invoice/{reference}', Public\InvoicePage::class)->name('invoice.show');
        Route::get('/invoice/{reference}/pdf', [InvoiceDownloadController::class, 'pdf'])->name('invoice.pdf');
    });
});

Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, config('qcamelmc.locales'), true)) {
        session(['locale' => $locale]);
    }

    return redirect()->back(fallback: '/');
})->name('lang.switch');

Route::post('/v', PresenceController::class)->middleware('throttle:presence')->name('presence');
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);

/*
|--------------------------------------------------------------------------
| Payments & inbound webhooks (CSRF-exempt, verified otherwise)
|--------------------------------------------------------------------------
*/
Route::match(['GET', 'POST'], '/payments/sadad/callback', SadadCallbackController::class)->middleware('throttle:sadad')->name('payments.sadad.callback');
Route::post('/sadad-callback', SadadCallbackController::class)->middleware('throttle:sadad');
Route::post('/payments/sadad/webhook', SadadWebhookController::class)->middleware('throttle:sadad')->name('payments.sadad.webhook');
Route::post('/webhooks/n8n/response', N8nResponseController::class)->middleware('throttle:n8n')->name('webhooks.n8n');
Route::post('/webhooks/mail/{provider}', MailEventsController::class)->middleware('throttle:mail-webhook')->name('webhooks.mail');

/*
|--------------------------------------------------------------------------
| Unified login (F-AUTH-001…016) — POST /login is Fortify with our pipeline
|--------------------------------------------------------------------------
*/
Route::post('/staff/passcode', PasscodeController::class)->name('staff.passcode');
Route::match(['GET', 'OPTIONS'], '/staff/passcode', fn () => response()->json(['ok' => false, 'error' => 'method_not_allowed'], request()->isMethod('OPTIONS') ? 204 : 405));
Route::post('/staff/logout', StaffLogoutController::class)->name('staff.logout');
Route::redirect('/admin/login', '/login');

Route::middleware('auth')->prefix('account')->group(function () {
    Route::get('/security/two-factor', [AccountSecurityController::class, 'twoFactor'])->name('security.two-factor');
    Route::get('/security/password', [AccountSecurityController::class, 'password'])->name('security.password');
    Route::post('/security/password', [AccountSecurityController::class, 'updatePassword'])->name('security.password.update');
});

/*
|--------------------------------------------------------------------------
| Staff apps (R-138): any staff identity incl. kiosk, per permission
|--------------------------------------------------------------------------
*/
Route::get('/staff/manifest.webmanifest', function () {
    $brand = PublicSettings::current();

    return response()->json([
        'name' => ($brand['festival_name'] ?? __('common.app_name')).' — '.__('ops.shell.hub'),
        'short_name' => __('ops.shell.hub'),
        'start_url' => '/staff',
        'scope' => '/staff',
        'display' => 'standalone',
        'background_color' => '#120708',
        'theme_color' => '#9C1638',
        'dir' => app()->getLocale() === 'en' ? 'ltr' : 'rtl',
        'lang' => app()->getLocale(),
        'icons' => [['src' => asset('favicon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'], ['src' => asset('favicon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']],
    ], 200, ['Content-Type' => 'application/manifest+json']);
})->name('staff.manifest');

Route::prefix('staff')->name('staff.')->group(function () {
    Route::get('/', Staff\Hub::class)->middleware('staff:staff.hub')->name('hub');
    Route::get('/pos', Staff\Pos::class)->middleware('staff:pos.sell')->name('pos');
    Route::get('/scanner', Staff\Scanner::class)->middleware('staff:scanner.admit')->name('scanner');
    Route::get('/receipts', Staff\Receipts::class)->middleware('staff:receipts.capture')->name('receipts');
    Route::get('/receipts/{receipt}/image', ReceiptImageController::class)->middleware(['staff:receipts.capture', 'signed'])->name('receipts.image');
    Route::get('/live-bookings', Staff\LiveBookings::class)->middleware('staff:live.view')->name('live');
    Route::get('/gate-board', Staff\GateBoard::class)->middleware('staff:gate.view')->name('gate');
    Route::get('/attendance', Staff\Attendance::class)->middleware('staff:attendance.view')->name('attendance');
    Route::get('/tickets/{reference}', Staff\TicketViewer::class)->middleware('staff:staff.hub')->name('tickets');
    Route::get('/qr/{holder}.png', HolderQrController::class)->middleware('staff:staff.hub')->name('qr');
    Route::redirect('/gate', '/staff/gate-board');
});

// Legacy URLs of the original SPA (bookmarks keep working).
Route::redirect('/admin/pos', '/staff/pos');
Route::redirect('/admin/qr-scanner', '/staff/scanner');
Route::redirect('/admin/pos-receipts', '/staff/receipts');
Route::get('/admin/tickets', fn () => request('ref') ? redirect()->route('staff.tickets', ['reference' => request('ref')]) : redirect('/admin'));
Route::redirect('/live-bookings', '/staff/live-bookings');
Route::redirect('/live-visitors', '/admin/live-visitors');
Route::get('/admin/dashboard/{path?}', fn (?string $path = null) => redirect('/admin/'.($path === null || $path === '' ? 'orders' : $path)))->where('path', '.*');
