<?php

namespace App\Http\Auth;

use App\Enums\Role;
use App\Http\Controllers\Auth\PasscodeController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-148 / F-AUTH-005 role routing after login: admins → safe intended path
 * or the dashboard; supervisor/cashier/gatekeeper → /staff; accountant/viewer
 * → dashboard (read-only resources); others signed out.
 */
class LoginResponse implements LoginResponseContract, TwoFactorLoginResponse
{
    public function toResponse($request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $next = PasscodeController::safeNext($request->input('next') ?? session()->pull('login.next'));

        if (! $user || ! $user->is_active || $user->getRoleNames()->isEmpty()) {
            Auth::guard('web')->logout();

            return redirect()->route('login')->withErrors(['email' => __('auth.unauthorized_admin')]);
        }

        if ($user->hasAnyRole(Role::panelRoles())) {
            $target = $next ?? '/admin';
        } else {
            $target = ($next && str_starts_with($next, '/staff')) ? $next : '/staff';
        }

        return $request->wantsJson() ? new JsonResponse(['two_factor' => false, 'redirect' => $target]) : redirect()->to($target)->with('toast', __('auth.signed_in'));
    }
}
