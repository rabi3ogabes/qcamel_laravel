<?php

namespace App\Enums;

enum DeviceKind: string
{
    use Concerns;

    case Scanner = 'scanner';
    case Pos = 'pos';
    case Board = 'board';

    public static function langKey(): string
    {
        return 'device_kind';
    }
}
