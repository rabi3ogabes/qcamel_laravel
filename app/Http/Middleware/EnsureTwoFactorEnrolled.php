<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** U-AUTH-01 / R-149: admins must enrol TOTP before using the panel. */
class EnsureTwoFactorEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled()) {
            return redirect()->route('security.two-factor');
        }
        if ($user instanceof User && $user->must_reset_password && ! $request->routeIs('security.*')) {
            return redirect()->route('security.password');
        }

        return $next($request);
    }
}
