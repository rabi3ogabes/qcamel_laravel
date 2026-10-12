<?php

namespace App\Enums;

enum OrderSource: string
{
    use Concerns;

    case Web = 'web';
    case Pos = 'pos';

    public static function langKey(): string
    {
        return 'order_source';
    }
}
