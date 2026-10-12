<?php

namespace App\Domain\Staff;

use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\ActivityType;
use App\Enums\AttendanceStatus;
use App\Events\AttendanceChanged;
use App\Models\PosUser;
use App\Models\StaffAttendance;
use App\Support\Audit;
use App\Support\QatarClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Team attendance (F-ADM-STF-013…023, F-STF-018…027, R-011, R-139, D-065):
 * read by any staff incl. kiosk; writes by account staff with
 * attendance.edit only; Qatar dates; future dates refused server-side.
 */
class AttendanceService
{
    public function canEdit(Actor $actor): bool
    {
        return $actor->isAccount() && $actor->can('attendance.edit');
    }

    private function assertDate(string $date): string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! CarbonImmutable::hasFormat($date, 'Y-m-d')) {
            throw new DomainError('bad_input', 400, __('attendance.bad_date'));
        }
        if ($date > QatarClock::today()) {
            throw new DomainError('bad_input', 400, __('attendance.future_date'));
        }

        return $date;
    }

    private function assertEditor(Actor $actor): void
    {
        if (! $this->canEdit($actor)) {
            throw new DomainError('account_required', 403, __('attendance.account_required'));
        }
    }

    /** @return array{users:\Illuminate\Support\Collection, records:\Illuminate\Support\Collection, can_edit:bool} */
    public function list(string $from, string $to, Actor $actor): array
    {
        if ($from > $to) {
            throw new DomainError('bad_range', 400, __('attendance.bad_range'));
        }

        return [
            'users' => PosUser::query()->active()->get(['id', 'name', 'icon', 'is_active']),
            'records' => StaffAttendance::query()->whereBetween('attendance_date', [$from, $to])->with('marker:id,name')->get(),
            'can_edit' => $this->canEdit($actor),
        ];
    }

    /** status null → clear (tap the active status again, R-181). */
    public function set(string $date, string $posUserId, ?string $status, Actor $actor): void
    {
        $this->assertEditor($actor);
        $date = $this->assertDate($date);
        if (! PosUser::query()->whereKey($posUserId)->exists()) {
            throw new DomainError('bad_input', 400, __('attendance.bad_user'));
        }
        if ($status === null) {
            StaffAttendance::query()->where('pos_user_id', $posUserId)->whereDate('attendance_date', $date)->delete();
        } else {
            $s = AttendanceStatus::tryFrom($status) ?? throw new DomainError('bad_status', 400, __('attendance.bad_status'));
            StaffAttendance::query()->updateOrCreate(
                ['pos_user_id' => $posUserId, 'attendance_date' => $date],
                ['status' => $s, 'marked_by' => $actor->userId(), 'marked_at' => now()],
            );
        }
        event(new AttendanceChanged($date));
        Audit::log(ActivityType::StaffRoleChanged, __('attendance.audit_set', ['date' => $date]), null, ['pos_user_id' => $posUserId, 'status' => $status, 'date' => $date], $actor);
    }

    /** @return list<string> ids marked present */
    public function bulkPresent(string $date, Actor $actor): array
    {
        $this->assertEditor($actor);
        $date = $this->assertDate($date);
        $ids = PosUser::query()->active()->pluck('id')->all();
        DB::transaction(function () use ($ids, $date, $actor) {
            foreach ($ids as $id) {
                StaffAttendance::query()->updateOrCreate(
                    ['pos_user_id' => $id, 'attendance_date' => $date],
                    ['status' => AttendanceStatus::Present, 'marked_by' => $actor->userId(), 'marked_at' => now()],
                );
            }
        });
        event(new AttendanceChanged($date));
        Audit::log(ActivityType::StaffRoleChanged, __('attendance.audit_bulk', ['date' => $date]), null, ['count' => count($ids)], $actor);

        return $ids;
    }

    public function clearDay(string $date, Actor $actor): int
    {
        $this->assertEditor($actor);
        $date = $this->assertDate($date);
        $n = StaffAttendance::query()->whereDate('attendance_date', $date)->delete();
        event(new AttendanceChanged($date));
        Audit::log(ActivityType::StaffRoleChanged, __('attendance.audit_clear', ['date' => $date]), null, ['deleted' => $n], $actor);

        return $n;
    }
}
