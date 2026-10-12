<?php

namespace App\Enums;

enum CheckInMode: string
{
    use Concerns;

    case Checkin = 'checkin';
    case Lookup = 'lookup';
    case Search = 'search';
    case Manual = 'manual';
    case History = 'history';
    case Reset = 'reset';

    public static function langKey(): string
    {
        return 'checkin_mode';
    }
}
