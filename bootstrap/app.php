<?php

use App\Domain\Shared\DomainError;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\LimitJsonBody;
use App\Http\Middleware\NoIndex;
use App\Http\Middleware\ResolveKiosk;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackVisitor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
        $middleware->web(append: [SetLocale::class, ResolveKiosk::class, SecurityHeaders::class]);
        // API v1 keeps the cookie session so staff/kiosk sessions work for POS & scanner calls (T-045).
        $middleware->api(prepend: [EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ResolveKiosk::class]);
        $middleware->alias([
            'staff' => EnsureStaff::class,
            'track' => TrackVisitor::class,
            'noindex' => NoIndex::class,
            'json.limit' => LimitJsonBody::class,
            '2fa' => EnsureTwoFactorEnrolled::class,
        ]);
        // Gateway callbacks/webhooks are verified otherwise (merchant API / shared secrets).
        $middleware->validateCsrfTokens(except: ['payments/sadad/*', 'sadad-callback', 'webhooks/*']);
        $middleware->encryptCookies(except: []);
        $middleware->redirectGuestsTo(fn (Request $r) => route('login', ['next' => '/'.ltrim($r->path(), '/')]));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $isApi = fn (Request $r) => $r->is('api/*') || $r->expectsJson() && ! $r->header('X-Livewire');

        // F-API-007 / T-038 / T-095 uniform envelope {success:false, code, error, ...}.
        $exceptions->render(function (DomainError $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return response()->json($e->toArray(), $e->status, ['Cache-Control' => 'no-store']);
            }
        });
        $exceptions->render(function (ValidationException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return response()->json(['success' => false, 'code' => 'validation_failed', 'error' => 'The order details are invalid', 'errors' => collect($e->errors())->flatten()->values()], 400);
            }
        });
        $exceptions->render(function (AuthenticationException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return response()->json(['success' => false, 'code' => 'unauthorized', 'error' => __('errors.unauthorized')], 401);
            }
        });
        $exceptions->render(function (AuthorizationException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return response()->json(['success' => false, 'code' => 'forbidden', 'error' => __('errors.forbidden')], 403);
            }
        });
        $exceptions->render(function (ThrottleRequestsException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return response()->json(['success' => false, 'code' => 'too_many_requests', 'error' => __('errors.too_many_requests')], 429, $e->getHeaders());
            }
        });
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return response()->json(['success' => false, 'code' => 'method_not_allowed', 'error' => 'Use POST'], 405);
            }
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return response()->json(['success' => false, 'code' => 'not_found', 'error' => 'Not found'], 404);
            }
        });
        $exceptions->render(function (Throwable $e, Request $r) use ($isApi) {
            if ($isApi($r) && ! $e instanceof HttpExceptionInterface) {
                return response()->json(['success' => false, 'code' => 'internal_error', 'error' => 'Something went wrong'], 500);
            }
        });
    })->create();

// Read-only hosts (Vercel): api/index.php points these at a writable temp directory.
if ($storage = getenv('APP_STORAGE_PATH')) {
    $app->useStoragePath($storage);
}
if ($bootstrapCache = getenv('APP_BOOTSTRAP_CACHE')) {
    $app->useBootstrapPath($bootstrapCache); // cached config/routes/packages live here
}

return $app;
