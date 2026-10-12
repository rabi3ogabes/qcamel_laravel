<?php

namespace App\Enums;

enum PaymentErrorSource: string
{
    use Concerns;

    case Sadad = 'sadad';
    case Bank = 'bank';
    case Site = 'site';

    /** R-124: the single shared keyword list (fixes D-091). */
    public const BANK_KEYWORDS = ['card', 'بطاقة', 'declin', 'مرفوض', 'insufficient', 'رصيد', 'bank', 'بنك', 'expired card', 'منتهية', 'cvv', '3d', 'authorization', 'تفويض', 'issuer'];

    public const SADAD_KEYWORDS = ['sadad', 'سداد', 'merchant', 'checksum', 'gateway', 'بوابة'];

    public static function langKey(): string
    {
        return 'payment_error_source';
    }

    public static function classify(?string $message, ?string $code = null): self
    {
        $text = mb_strtolower(trim(($message ?? '').' '.($code ?? '')));
        foreach (self::BANK_KEYWORDS as $k) {
            if (str_contains($text, $k)) {
                return self::Bank;
            }
        }
        foreach (self::SADAD_KEYWORDS as $k) {
            if (str_contains($text, $k)) {
                return self::Sadad;
            }
        }

        return self::Site;
    }

    /** F-PAY-053 server failure source: bank or sadad (never site). */
    public static function forFailureReason(?string $reason): self
    {
        return self::classify($reason) === self::Bank ? self::Bank : self::Sadad;
    }

    public function color(): string
    {
        return match ($this) {
            self::Sadad => 'warning',
            self::Bank => 'danger',
            self::Site => 'gray',
        };
    }
}
