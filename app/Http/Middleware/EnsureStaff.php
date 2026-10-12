<?php

namespace App\Http\Middleware;

use App\Domain\Shared\Actor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff routes (F-AUTH-021/022 rebuild, R-138, T-087, T-088, T-122):
 * any staff identity (account role or kiosk) holding the given permission.
 * Kiosk sessions never reach admin-only routes. Guests → unified login.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $actor = Actor::current();
        if ($actor->isGuest()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'code' => 'unauthorized', 'error' => __('errors.unauthorized')], 401);
            }

            return redirect()->guest(route('login', ['next' => '/'.ltrim($request->path(), '/')]));
        }
        if (! $actor->isStaff() || ($permission && ! $actor->can($permission))) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'code' => 'forbidden', 'error' => __('errors.forbidden')], 403);
            }

            return response()->view('errors.not-staff', [], 403);
        }
        $request->attributes->set('actor', $actor);

        return $next($request);
    }
}
