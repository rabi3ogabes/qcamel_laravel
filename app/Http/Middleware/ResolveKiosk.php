<?php

namespace App\Http\Middleware;

use App\Domain\Staff\PasscodeAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the passcode kiosk device from its HttpOnly cookie (R-141,
 * D-008) and applies the sliding 10-day renewal on staff pages.
 */
class ResolveKiosk
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($device = PasscodeAuthenticator::resolve($request)) {
            $request->attributes->set('kiosk', $device);
            if ($request->is('staff', 'staff/*') && (! $device->last_seen_at || $device->last_seen_at->lt(now()->subMinutes(5)))) {
                $days = (int) config('qcamelmc.auth.passcode_session_days', 10);
                $device->forceFill([
                    'last_seen_at' => now(),
                    'last_ip' => PasscodeAuthenticator::callerIp($request),
                    'expires_at' => now()->addDays($days),
                ])->saveQuietly();
                Cookie::queue(Cookie::make(PasscodeAuthenticator::COOKIE, (string) $request->cookie(PasscodeAuthenticator::COOKIE), $days * 24 * 60, '/', null, config('session.secure'), true, false, 'lax'));
            }
        }

        return $next($request);
    }
}
