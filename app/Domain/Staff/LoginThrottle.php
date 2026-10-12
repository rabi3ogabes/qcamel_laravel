<?php

namespace App\Domain\Staff;

use App\Domain\Messaging\TransactionalMailer;
use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Models\LoginAttempt;
use App\Support\Audit;
use App\Support\QatarClock;
use Illuminate\Http\Request;

/**
 * Account login throttle enforced inside the login pipeline (R-145…R-147,
 * F-AUTH-014, D-010, T-092): per e-mail AND per IP, 5 failures in 15 min →
 * 30 min lock measured from the latest failure. Not callable anonymously as
 * a "record" endpoint; separate keys from the passcode throttle.
 */
class LoginThrottle
{
    public static function emailKey(string $email): string
    {
        return mb_substr(mb_strtolower(trim($email)), 0, 160);
    }

    public static function ipKey(?string $ip): string
    {
        return 'login-ip:'.($ip ?? 'unknown');
    }

    /** @return array{blocked:bool, retry_after:int, attempts_left:int} */
    public function state(string $email, ?string $ip): array
    {
        $now = QatarClock::nowUtc();
        $since = $now->subMinutes(max((int) config('qcamelmc.auth.login_block'), (int) config('qcamelmc.auth.login_window')));
        $max = (int) config('qcamelmc.auth.login_max', 5);
        $win = (int) config('qcamelmc.auth.login_window', 15) * 60;
        $blk = (int) config('qcamelmc.auth.login_block', 30) * 60;
        $byEmail = Throttle::evaluate(Throttle::attemptsFor(self::emailKey($email), $since), $max, $win, $blk, $now);
        $byIp = Throttle::evaluate(Throttle::attemptsFor(self::ipKey($ip), $since), $max, $win, $blk, $now);

        return [
            'blocked' => $byEmail['blocked'] || $byIp['blocked'],
            'retry_after' => max($byEmail['retry_after'], $byIp['retry_after']),
            'attempts_left' => min($byEmail['attempts_left'], $byIp['attempts_left']),
        ];
    }

    /** Record an attempt; on the attempt that starts a block → alert + audit once. */
    public function record(string $email, Request $request, bool $success): array
    {
        $ip = $request->ip();
        $ua = $request->userAgent();
        $wasBlocked = $this->state($email, $ip)['blocked'];
        Throttle::record(self::emailKey($email), 'account', $success, $ip, $ua);
        Throttle::record(self::ipKey($ip), 'account', $success, $ip, $ua);
        $state = $this->state($email, $ip);
        if (! $success && $state['blocked'] && ! $wasBlocked) {
            $identifier = self::emailKey($email);
            app(TransactionalMailer::class)->sendLoginAlert($identifier, 'account', (int) config('qcamelmc.auth.login_max', 5), $ip, $ua);
            Audit::log(ActivityType::LoginBlocked, __('auth.audit_blocked', ['identifier' => $identifier]), null, ['attempts' => (int) config('qcamelmc.auth.login_max', 5), 'block_minutes' => (int) config('qcamelmc.auth.login_block', 30), 'kind' => 'account'], Actor::guest());
        }

        return $state;
    }

    /** U-AUTH-03 unblock action. */
    public static function unblock(string $identifier): int
    {
        return LoginAttempt::query()->where('identifier', $identifier)->where('success', false)->delete();
    }
}
