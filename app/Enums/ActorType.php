<?php

namespace App\Enums;

enum ActorType: string
{
    use Concerns;

    case Admin = 'admin';
    case Guest = 'guest';
    case System = 'system';
    case Staff = 'staff';

    public static function langKey(): string
    {
        return 'actor_type';
    }
}
