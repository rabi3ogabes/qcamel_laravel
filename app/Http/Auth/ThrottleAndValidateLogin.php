<?php

namespace App\Http\Auth;

use App\Domain\Staff\LoginThrottle;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * First pipe of the Fortify login pipeline (F-AUTH-004 rebuild, R-145…R-148,
 * D-010, T-092): lockout enforced server-side per e-mail AND IP; failures
 * recorded; users without a staff/panel role refused with the original text.
 */
class ThrottleAndValidateLogin
{
    public function __construct(private readonly LoginThrottle $throttle) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $email = mb_strtolower(trim((string) $request->input('email')));
        $state = $this->throttle->state($email, $request->ip());
        if ($state['blocked']) {
            session()->flash('login_lock', $state['retry_after'] ?: 1800);
            throw ValidationException::withMessages(['email' => __('auth.blocked_toast')]);
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            $after = $this->throttle->record($email, $request, false);
            if ($after['blocked']) {
                session()->flash('login_lock', $after['retry_after'] ?: 1800);
            } else {
                session()->flash('attempts_left', $after['attempts_left']);
            }
            throw ValidationException::withMessages(['email' => __('auth.invalid_credentials')]);
        }

        if (! $user->is_active || $user->getRoleNames()->isEmpty()) {
            $this->throttle->record($email, $request, false);
            throw ValidationException::withMessages(['email' => __('auth.unauthorized_admin')]);
        }

        $this->throttle->record($email, $request, true);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();

        return $next($request);
    }
}
