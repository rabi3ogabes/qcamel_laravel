<?php

namespace App\Enums;

/** Sadad transactionstatus.name → Arabic label + colour (F-PAY-058, F-ADM-SDD-012). */
final class SadadLiveStatus
{
    public const SUCCESS = ['SUCCESS'];

    public const FAILED = ['FAILED', 'REJECTED'];

    public const REFUND = ['REFUND', 'REFUNDED'];

    public const PROGRESS = ['INPROGRESS', 'IN PROGRESS', 'PENDING'];

    public const HOLD = ['ONHOLD', 'ON HOLD'];

    public static function normalize(?string $name): string
    {
        $n = strtoupper(trim((string) $name));

        return $n === '' ? 'UNKNOWN' : $n;
    }

    public static function label(?string $name): string
    {
        $n = self::normalize($name);
        $key = 'enums.sadad_live.'.str_replace(' ', '_', $n);
        $t = __($key);

        return $t === $key ? $n : $t;
    }

    public static function color(?string $name): string
    {
        $n = self::normalize($name);

        return match (true) {
            in_array($n, self::SUCCESS, true) => 'success',
            in_array($n, self::FAILED, true) => 'danger',
            in_array($n, self::REFUND, true) => 'info',
            in_array($n, self::PROGRESS, true) => 'warning',
            default => 'gray',
        };
    }

    /** Filter groups of F-ADM-TRX-008. */
    public static function group(string $filter): array
    {
        return match ($filter) {
            'success' => self::SUCCESS,
            'failed' => self::FAILED,
            'progress' => self::PROGRESS,
            default => [],
        };
    }
}
