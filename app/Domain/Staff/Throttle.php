<?php

namespace App\Domain\Staff;

use App\Models\LoginAttempt;
use Carbon\CarbonImmutable;

/**
 * Port of evaluateThrottle (F-AUTH-015, R-142…R-145, T-089, T-090):
 * streak = consecutive failures from the newest back to the first success;
 * blocked when ≥ max failures, the max-th newest is inside the window and
 * newest + block > now (further wrong attempts extend the block).
 */
final class Throttle
{
    /**
     * @param  iterable<array{attempted_at:mixed, success:bool}>  $attempts any order
     * @return array{blocked:bool, retry_after:int, attempts_left:int}
     */
    public static function evaluate(iterable $attempts, int $max, int $windowSeconds, int $blockSeconds, CarbonImmutable $now): array
    {
        $rows = [];
        foreach ($attempts as $a) {
            $rows[] = ['at' => CarbonImmutable::parse($a['attempted_at'])->getTimestamp(), 'success' => (bool) $a['success']];
        }
        usort($rows, fn ($a, $b) => $b['at'] <=> $a['at']);
        $streak = [];
        foreach ($rows as $r) {
            if ($r['success']) {
                break;
            }
            $streak[] = $r['at'];
        }
        $nowTs = $now->getTimestamp();
        $inWindow = array_filter($streak, fn ($at) => $nowTs - $at <= $windowSeconds);
        $left = max(0, $max - count($inWindow));

        if (count($streak) >= $max) {
            $decisive = $streak[$max - 1];
            $newest = $streak[0];
            if ($nowTs - $decisive <= $windowSeconds && $newest + $blockSeconds > $nowTs) {
                return ['blocked' => true, 'retry_after' => (int) ceil($newest + $blockSeconds - $nowTs), 'attempts_left' => 0];
            }
        }

        return ['blocked' => false, 'retry_after' => 0, 'attempts_left' => $left];
    }

    /** Attempts of an identifier since the given instant (max 200, newest first). */
    public static function attemptsFor(string $identifier, CarbonImmutable $since): array
    {
        return LoginAttempt::query()
            ->where('identifier', $identifier)
            ->where('attempted_at', '>=', $since)
            ->orderByDesc('attempted_at')
            ->limit(200)
            ->get(['attempted_at', 'success'])
            ->map(fn ($r) => ['attempted_at' => $r->attempted_at, 'success' => $r->success])
            ->all();
    }

    public static function record(string $identifier, string $kind, bool $success, ?string $ip, ?string $ua): void
    {
        LoginAttempt::create([
            'identifier' => mb_substr($identifier, 0, 160), 'kind' => $kind, 'success' => $success,
            'ip_address' => $ip ? mb_substr($ip, 0, 64) : null, 'user_agent' => $ua ? mb_substr($ua, 0, 300) : null,
            'attempted_at' => now(),
        ]);
    }
}
