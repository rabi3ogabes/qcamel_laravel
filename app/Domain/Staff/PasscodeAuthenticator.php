<?php

namespace App\Domain\Staff;

use App\Domain\Messaging\TransactionalMailer;
use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Models\KioskDevice;
use App\Settings\FeatureFlagSettings;
use App\Support\Audit;
use App\Support\QatarClock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Team passcode → server-side kiosk session (F-AUTH-009/010, F-STF-003…006,
 * R-140…R-144, D-008, D-009, T-085…T-091, T-122). The browser only holds an
 * opaque HttpOnly device-token cookie; the passcode is never stored or re-sent.
 */
class PasscodeAuthenticator
{
    public const COOKIE = 'qc_kiosk';

    public const GLOBAL_ID = 'team-passcode';

    /** R-140: not_configured | too_short | revoked | disabled | null. */
    public function problem(): ?string
    {
        try {
            if (! app(FeatureFlagSettings::class)->passcode_login_enabled) {
                return 'disabled';
            }
        } catch (\Throwable) {
            // settings unavailable → keep env rules only
        }
        $hash = trim((string) config('qcamelmc.auth.passcode_hash'));
        $plain = trim((string) config('qcamelmc.auth.passcode_plain'));
        if ($hash === '' && $plain === '') {
            return 'not_configured';
        }
        if ($plain !== '') {
            if (mb_strlen($plain) < (int) config('qcamelmc.auth.passcode_min_length', 8)) {
                return 'too_short';
            }
            if (in_array($plain, (array) config('qcamelmc.auth.passcode_revoked', []), true)) {
                return 'revoked';
            }
        }
        if ($hash !== '') {
            foreach ((array) config('qcamelmc.auth.passcode_revoked', []) as $revoked) {
                if (Hash::check($revoked, $hash)) {
                    return 'revoked';
                }
            }
        }

        return null;
    }

    /** T-086: exact, case-sensitive after trimming surrounding spaces; constant time. */
    public function verify(mixed $passcode): bool
    {
        if (! is_string($passcode)) {
            return false;
        }
        $supplied = trim($passcode);
        if ($supplied === '') {
            return false;
        }
        $hash = trim((string) config('qcamelmc.auth.passcode_hash'));
        if ($hash !== '') {
            return Hash::check($supplied, $hash);
        }
        $plain = trim((string) config('qcamelmc.auth.passcode_plain'));

        return $plain !== '' && hash_equals($plain, $supplied);
    }

    /** R-142: CF-Connecting-IP, else first X-Forwarded-For (trusted proxy aware), ≤ 64 chars. */
    public static function callerIp(Request $request): ?string
    {
        $cf = trim((string) $request->header('CF-Connecting-IP'));
        if ($cf !== '') {
            return mb_substr($cf, 0, 64);
        }
        $ip = $request->ip();

        return $ip ? mb_substr($ip, 0, 64) : null;
    }

    /**
     * @return array<string,mixed> response body (always HTTP 200 for the UI)
     */
    public function attempt(mixed $passcode, Request $request, ?string $deviceName = null): array
    {
        if ($passcode === '__warmup__') {
            return ['ok' => false, 'warmup' => true]; // T-091 not recorded
        }
        if ($problem = $this->problem()) {
            return ['ok' => false, 'unavailable' => $problem]; // T-085 not recorded
        }

        $now = QatarClock::nowUtc();
        $ip = self::callerIp($request);
        $callerId = 'passcode:'.($ip ?? 'unknown');
        $since = $now->subMinutes(max((int) config('qcamelmc.auth.passcode_ip_block'), (int) config('qcamelmc.auth.passcode_global_block')));

        $caller = Throttle::evaluate(Throttle::attemptsFor($callerId, $since), (int) config('qcamelmc.auth.passcode_ip_max'), (int) config('qcamelmc.auth.passcode_ip_window') * 60, (int) config('qcamelmc.auth.passcode_ip_block') * 60, $now);
        $global = Throttle::evaluate(Throttle::attemptsFor(self::GLOBAL_ID, $since), (int) config('qcamelmc.auth.passcode_global_max'), (int) config('qcamelmc.auth.passcode_global_window') * 60, (int) config('qcamelmc.auth.passcode_global_block') * 60, $now);
        if ($caller['blocked'] || $global['blocked']) {
            return ['ok' => false, 'blocked' => true, 'attempts_left' => 0, 'retry_after_seconds' => max($caller['retry_after'], $global['retry_after'])];
        }

        $ok = $this->verify($passcode);
        $ua = $request->userAgent();
        try {
            Throttle::record($callerId, 'passcode', $ok, $ip, $ua);
            if (! $ok) {
                Throttle::record(self::GLOBAL_ID, 'passcode', false, $ip, $ua); // only wrong guesses (T-090)
            }
        } catch (\Throwable) {
            // a log outage still answers correctly (T-091)
        }

        if ($ok) {
            $this->startKioskSession($request, $deviceName);

            return ['ok' => true];
        }
        $left = max(0, $caller['attempts_left'] - 1);
        $out = ['ok' => false, 'attempts_left' => $left];
        if ($caller['attempts_left'] - 1 <= 0) {
            $out += ['blocked' => true, 'retry_after_seconds' => (int) config('qcamelmc.auth.passcode_ip_block') * 60];
            // U-AUTH-04: passcode lockouts alert the admin too.
            app(TransactionalMailer::class)->sendLoginAlert($callerId, 'passcode', (int) config('qcamelmc.auth.passcode_ip_max'), $ip, $ua);
            Audit::log(ActivityType::LoginBlocked, __('auth.audit_blocked', ['identifier' => $callerId]), null, ['attempts' => (int) config('qcamelmc.auth.passcode_ip_max'), 'block_minutes' => (int) config('qcamelmc.auth.passcode_ip_block'), 'kind' => 'passcode'], Actor::guest());
        }

        return $out;
    }

    /** R-141: 10-day device session bound to a kiosk_devices row. */
    public function startKioskSession(Request $request, ?string $deviceName = null): KioskDevice
    {
        $token = Str::random(64);
        $days = (int) config('qcamelmc.auth.passcode_session_days', 10);
        $device = KioskDevice::create([
            'name' => mb_substr(trim((string) $deviceName) ?: __('auth.kiosk_default_name'), 0, 80),
            'kind' => 'scanner',
            'token_hash' => hash('sha256', $token),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 300),
            'last_ip' => self::callerIp($request),
            'last_seen_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);
        Cookie::queue(Cookie::make(self::COOKIE, $token, $days * 24 * 60, '/', null, config('session.secure'), true, false, 'lax'));
        $request->attributes->set('kiosk', $device);

        return $device;
    }

    /** Resolve the kiosk device from the cookie (middleware). */
    public static function resolve(Request $request): ?KioskDevice
    {
        $token = $request->cookie(self::COOKIE);
        if (! is_string($token) || strlen($token) < 32) {
            return null;
        }
        $device = KioskDevice::query()->where('token_hash', hash('sha256', $token))->first();

        return $device && $device->isUsable() ? $device : null;
    }

    public static function forget(Request $request): void
    {
        if ($device = self::resolve($request)) {
            $device->forceFill(['revoked_at' => now()])->save();
        }
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /** U-AUTH-02: rotating the passcode invalidates every kiosk session. */
    public static function revokeAll(): int
    {
        return KioskDevice::query()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }
}
