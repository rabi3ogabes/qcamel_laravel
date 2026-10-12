<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Part 6.3 rule 6 security baseline: CSP with nonces, HSTS, frame
 * protection, Referrer-Policy, Permissions-Policy (camera only where needed).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $isAdmin = $request->is('admin', 'admin/*', 'livewire/*');
        $camera = $request->is('staff/scanner*', 'staff/receipts*') ? 'camera=(self)' : 'camera=()';
        $connect = "'self' ws: wss: https://*.clarity.ms";
        $reverb = config('broadcasting.connections.reverb.options.host');
        if ($reverb) {
            $connect .= ' '.(config('broadcasting.connections.reverb.options.scheme', 'https') === 'https' ? 'wss://' : 'ws://').$reverb.':*';
        }
        if (app()->isLocal()) {
            $connect .= ' http://localhost:* http://127.0.0.1:* ws://localhost:* ws://127.0.0.1:*';
        }
        $scriptDev = app()->isLocal() ? " http://localhost:* http://127.0.0.1:*" : '';

        // Alpine needs 'unsafe-eval'. Public/staff pages use nonces; the Filament panel
        // injects inline bootstrap scripts, so it keeps 'unsafe-inline' (behind auth + 2FA).
        $scriptSrc = $isAdmin
            ? "script-src 'self' 'unsafe-inline' 'unsafe-eval'".$scriptDev
            : "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' https://www.clarity.ms https://*.clarity.ms".$scriptDev;
        $csp = implode('; ', [
            "default-src 'self'",
            $scriptSrc,
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com".$scriptDev,
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "media-src 'self' blob: https:",
            "connect-src {$connect}",
            "frame-src 'self' https://www.youtube.com https://www.google.com",
            "frame-ancestors 'none'",
            "form-action 'self' https://sadadqa.com https://www.sadadqa.com https://secure.sadadqa.com",
            "base-uri 'self'",
            "object-src 'none'",
        ]);

        $headers = [
            'Content-Security-Policy' => $csp,
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => $camera.', microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=63072000; includeSubDomains; preload';
        }
        foreach ($headers as $k => $v) {
            if (! $response->headers->has($k)) {
                $response->headers->set($k, $v);
            }
        }
        if ($isAdmin) {
            $response->headers->remove('Cross-Origin-Opener-Policy');
        }

        return $response;
    }
}
