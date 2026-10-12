<?php

namespace App\Enums;

enum PaymentMethod: string
{
    use Concerns;

    case Sadad = 'sadad';
    case CashPos = 'cash_pos';

    public static function langKey(): string
    {
        return 'payment_method';
    }

    public function shortLabel(): string
    {
        return __('enums.payment_method_short.'.$this->value);
    }

    public function icon(): string
    {
        return $this === self::Sadad ? 'credit-card' : 'banknote';
    }
}
