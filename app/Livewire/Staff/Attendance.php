<?php

namespace App\Livewire\Staff;

use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Domain\Staff\AttendanceService;
use App\Support\Formats;
use App\Support\QatarClock;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * F-STF-018…027: daily present/absent register over active POS users,
 * 7-day summary, bulk mark, clear day (confirmed). Kiosk = read-only.
 * Also embedded in the admin panel (Team → Attendance).
 */
#[Layout('components.layouts.staff')]
class Attendance extends Component
{
    #[Url(as: 'd')]
    public string $date = '';

    public bool $embedded = false;

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
        $today = QatarClock::today();
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) || $this->date > $today) {
            $this->date = $today;
        }
    }

    public function shift(int $days): void
    {
        $next = CarbonImmutable::parse($this->date)->addDays($days)->format('Y-m-d');
        $this->date = min($next, QatarClock::today());
    }

    public function updatedDate(string $v): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) || $v > QatarClock::today()) {
            $this->date = QatarClock::today();
        }
    }

    public function setStatus(string $posUserId, ?string $status, AttendanceService $svc): void
    {
        $this->guarded(fn () => $svc->set($this->date, $posUserId, $status, Actor::current()), __('attendance.save_failed'));
    }

    public function markAll(AttendanceService $svc): void
    {
        if ($this->guarded(fn () => $svc->bulkPresent($this->date, Actor::current()), __('attendance.update_failed'))) {
            $this->toast('success', __('attendance.mark_all_done'));
        }
    }

    public function clearDay(AttendanceService $svc): void
    {
        if ($this->guarded(fn () => $svc->clearDay($this->date, Actor::current()), __('attendance.clear_failed'))) {
            $this->dispatch('close-dialog', 'att-clear');
            $this->toast('success', __('attendance.cleared'));
        }
    }

    /** Staff layout uses the Alpine toast store; inside Filament use its notifications. */
    private function toast(string $type, string $message, ?string $title = null): void
    {
        if ($this->embedded) {
            Notification::make()->status($type === 'error' ? 'danger' : $type)
                ->title($title ?? $message)->body($title ? $message : null)->send();

            return;
        }
        $this->dispatch('toast', type: $type, title: $title, message: $message);
    }

    private function guarded(callable $fn, string $fallback): bool
    {
        try {
            $fn();

            return true;
        } catch (DomainError $e) {
            $this->toast('error', $e->userMessage(), $e->errorCode === 'account_required' ? __('attendance.read_only_toast') : null);
        } catch (\Throwable $e) {
            report($e);
            $this->toast('error', $fallback);
        }

        return false;
    }

    public function render(AttendanceService $svc)
    {
        $from = CarbonImmutable::parse($this->date)->subDays(6)->format('Y-m-d');
        $data = $svc->list($from, $this->date, Actor::current());
        $users = $data['users']->sortBy('name')->values();
        $byKey = [];
        foreach ($data['records'] as $r) {
            $byKey[$r->pos_user_id.'|'.$r->attendance_date->format('Y-m-d')] = $r;
        }
        $days = collect(range(6, 0))->map(fn ($i) => CarbonImmutable::parse($this->date)->subDays($i)->format('Y-m-d'))->all();
        $todayRecords = collect($users)->map(fn ($u) => $byKey[$u->id.'|'.$this->date] ?? null);
        $present = $todayRecords->filter(fn ($r) => $r?->status?->value === 'present')->count();
        $absent = $todayRecords->filter(fn ($r) => $r?->status?->value === 'absent')->count();

        return view('livewire.staff.attendance', [
            'users' => $users,
            'byKey' => $byKey,
            'days' => $days,
            'canEdit' => $data['can_edit'],
            'stats' => ['total' => $users->count(), 'present' => $present, 'absent' => $absent, 'unmarked' => $users->count() - $present - $absent],
            'label' => Formats::longDate($this->date.' 12:00:00'),
            'isToday' => $this->date === QatarClock::today(),
        ])->title(__('attendance.title'));
    }
}
