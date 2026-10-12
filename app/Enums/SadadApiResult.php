<?php

namespace App\Enums;

enum SadadApiResult: string
{
    use Concerns;

    case Success = 'success';
    case Failed = 'failed';
    case InProgress = 'in_progress';
    case NotFound = 'not_found';
    case Unavailable = 'unavailable';

    public static function langKey(): string
    {
        return 'sadad_api_result';
    }
}
