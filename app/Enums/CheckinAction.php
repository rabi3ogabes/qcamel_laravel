<?php

namespace App\Enums;

enum CheckinAction: string
{
    use Concerns;

    case Admit = 'admit';
    case Reset = 'reset';
    case Rejected = 'rejected';

    public static function langKey(): string
    {
        return 'checkin_action';
    }
}
