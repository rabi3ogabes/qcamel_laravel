<?php

namespace App\Enums;

enum SadadEnvironment: string
{
    use Concerns;

    case Auto = 'auto';
    case Sandbox = 'sandbox';
    case Live = 'live';

    public static function langKey(): string
    {
        return 'sadad_environment';
    }
}
