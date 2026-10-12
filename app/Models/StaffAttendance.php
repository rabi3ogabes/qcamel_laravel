<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part 4.3 staff_attendance (R-011, R-139). */
class StaffAttendance extends Model
{
    use HasUuids;

    protected $table = 'staff_attendance';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'attendance_date' => 'date:Y-m-d',
            'marked_at' => 'immutable_datetime',
        ];
    }

    public function posUser(): BelongsTo
    {
        return $this->belongsTo(PosUser::class)->withTrashed();
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
