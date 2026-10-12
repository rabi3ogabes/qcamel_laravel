<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    use Concerns;

    case Present = 'present';
    case Absent = 'absent';

    public static function langKey(): string
    {
        return 'attendance_status';
    }
}
