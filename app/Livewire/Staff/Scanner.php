<?php

namespace App\Livewire\Staff;

use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Domain\Tickets\CheckInService;
use App\Models\PosUser;
use App\Models\TicketHolder;
use App\Settings\EventSettings;
use App\Support\Text;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * GATE — smart ticket scanner (F-GATE-001…050). Every decision is made by
 * CheckInService; the screen never derives payment state (one server
 * `admissible` flag per ticket).
 */
#[Layout('components.layouts.staff', ['theme' => 'dark'])]
class Scanner extends Component
{
    #[Session(key: 'scanner.staff')]
    public ?string $staffId = null;

    #[Session(key: 'scanner.continuous')]
    public bool $continuous = false;

    #[Session(key: 'scanner.history_all')]
    public bool $historyAll = false;

    public string $manual = '';

    public bool $showManual = false;

    /** idle | review | success | already | error */
    #[Locked]
    public string $phase = 'idle';

    /** @var list<array<string,mixed>> tickets found by the last lookup */
    #[Locked]
    public array $tickets = [];

    /** @var list<string> */
    public array $selected = [];

    #[Locked]
    public bool $fromCamera = false;

    #[Locked]
    public ?array $alert = null;   // already-scanned / error payload

    #[Locked]
    public ?array $done = null;    // success summary

    /** @var array{same_day:list<array>, other_days:array<string,list<array>>} */
    #[Locked]
    public array $related = ['same_day' => [], 'other_days' => []];

    #[Locked]
    public ?array $flash = null;   // continuous-mode quick result

    public function mount(): void
    {
        if ($this->staffId && ! PosUser::query()->active()->whereKey($this->staffId)->exists()) {
            $this->staffId = null;
        }
    }

    private function staffLabel(): ?string
    {
        return $this->staffId ? PosUser::query()->whereKey($this->staffId)->value('name') : null;
    }

    private function reset_(): void
    {
        $this->phase = 'idle';
        $this->tickets = [];
        $this->selected = [];
        $this->alert = null;
        $this->done = null;
        $this->related = ['same_day' => [], 'other_days' => []];
    }

    // ---- Lookup -----------------------------------------------------------------------

    public function searchManual(): void
    {
        $code = strtoupper(trim($this->manual));
        if ($code === '') {
            $this->toast('error', __('ops.scanner.enter_ref'));

            return;
        }
        $this->process($code, false);
    }

    public function process(string $raw, bool $fromCamera = false): void
    {
        $actor = Actor::current();
        $svc = app(CheckInService::class);
        $this->reset_();
        $this->fromCamera = $fromCamera;
        $code = Text::normalizeScannedCode($raw);
        $type = CheckInService::classify($code);
        rescue(fn () => $svc->logSearch($code, $type, $actor), report: false);

        // Continuous mode: admit straight away, flash the verdict, keep the camera running.
        if ($this->continuous && $fromCamera && $type === 'ticket_code') {
            $this->quickAdmit($code);

            return;
        }

        $result = match ($type) {
            'phone' => $svc->byPhone($code, $actor),
            'booking_reference' => $svc->byReference($code, $actor),
            default => $svc->lookup($code, $actor),
        };
        $rows = array_values($result['results'] ?? []);
        if ($type === 'ticket_code') {
            $rows = array_values(array_filter($rows, fn ($r) => strtoupper((string) $r['ticket_code']) === strtoupper($code))) ?: array_slice($rows, 0, 1);
        }

        if ($rows === []) {
            $msg = $result['message'] ?? match ($type) {
                'phone' => __('ops.scanner.no_phone_tickets'),
                'booking_reference' => __('ops.scanner.booking_not_found'),
                default => __('ops.scanner.not_found'),
            };
            $this->showError($msg);

            return;
        }

        $this->tickets = $rows;
        $open = array_values(array_filter($rows, fn ($r) => ! $r['is_present'] && $r['admissible']));

        if (count($rows) === 1) {
            $t = $rows[0];
            if ($t['is_present']) {
                $this->alert = ['kind' => 'already', 'ticket' => $t];
                $this->phase = 'already';
                $this->dispatch('sfx', name: 'error');
                $this->dispatch('scanner-already', camera: $fromCamera);

                return;
            }
            if (! $t['admissible']) {
                $this->showError($t['refusal_message'] ?? __('ops.scanner.invalid'), $t);

                return;
            }
            $this->selected = [$t['holder_id']];
        } elseif ($open === []) {
            $first = $rows[0];
            $msg = collect($rows)->every(fn ($r) => $r['is_present']) ? __('gate.already_used') : ($first['refusal_message'] ?? __('ops.scanner.invalid'));
            $this->showError($msg, $first);

            return;
        } else {
            $this->selected = count($open) === 1 ? [$open[0]['holder_id']] : [];
            $this->toast('info', __('ops.scanner.found_many', ['n' => count($open)]));
        }

        // Same-phone family tickets (F-GATE-031/032 rebuild: same-day ones are selectable).
        $holder = TicketHolder::query()->with('order.event')->find($rows[0]['holder_id']);
        if ($holder) {
            $known = array_column($rows, 'holder_id');
            $rel = $svc->related($holder, $actor);
            $rel['same_day'] = array_values(array_filter($rel['same_day'], fn ($r) => ! in_array($r['holder_id'], $known, true)));
            $this->related = $rel;
        }
        $this->phase = 'review';
        $this->dispatch('sfx', name: 'alert');
    }

    private function showError(string $message, ?array $ticket = null): void
    {
        $this->alert = ['kind' => 'error', 'message' => $message, 'ticket' => $ticket];
        $this->phase = 'error';
        $this->dispatch('sfx', name: 'error');
        $this->dispatch('scanner-idle');
    }

    private function quickAdmit(string $code): void
    {
        try {
            $res = app(CheckInService::class)->admitByCode($code, Actor::current(), $this->staffLabel(), 'camera');
        } catch (DomainError $e) {
            $res = ['success' => false, 'message' => $e->userMessage()];
        }
        $ok = (bool) ($res['success'] ?? false);
        $this->flash = [
            'ok' => $ok, 'message' => $res['message'] ?? '', 'name' => $res['ticket']['ticket_holder_name'] ?? null,
            'type' => $res['ticket']['ticket_type'] ?? null, 'type_label' => $res['ticket']['ticket_type_label'] ?? null,
            'collect' => (bool) ($res['collect_cash'] ?? false), 'at' => now()->timestamp,
        ];
        $this->dispatch('sfx', name: $ok ? 'checkin' : 'error', type: $res['ticket']['ticket_type'] ?? null);
        $this->dispatch('scanner-idle');
    }

    // ---- Selection & confirm (F-GATE-019…028) -------------------------------------------

    public function toggle(string $holderId): void
    {
        $t = collect($this->tickets)->concat($this->related['same_day'])->firstWhere('holder_id', $holderId);
        if (! $t || $t['is_present'] || ! $t['admissible']) {
            if ($t && $t['is_present']) {
                $this->toast('info', __('gate.already_marked'));
            }

            return;
        }
        $this->selected = in_array($holderId, $this->selected, true)
            ? array_values(array_diff($this->selected, [$holderId]))
            : [...$this->selected, $holderId];
    }

    public function selectAll(): void
    {
        $open = collect($this->tickets)->filter(fn ($r) => ! $r['is_present'] && $r['admissible'])->pluck('holder_id')->all();
        $this->selected = count(array_intersect($open, $this->selected)) === count($open) ? [] : $open;
    }

    public function addFamily(): void
    {
        $ids = collect($this->related['same_day'])->filter(fn ($r) => ! $r['is_present'] && $r['admissible'])->pluck('holder_id')->all();
        $this->selected = array_values(array_unique([...$this->selected, ...$ids]));
    }

    public function confirm(): void
    {
        if ($this->selected === []) {
            $this->toast('error', __('ops.scanner.pick_some'));

            return;
        }
        $results = app(CheckInService::class)->admitMany($this->selected, Actor::current(), $this->staffLabel(), $this->fromCamera ? 'camera' : 'manual');
        $ok = array_values(array_filter($results, fn ($r) => $r['success'] ?? false));
        $failed = array_values(array_filter($results, fn ($r) => ! ($r['success'] ?? false)));

        if ($ok === []) {
            $this->alert = ['kind' => 'error', 'message' => implode("\n", array_unique(array_map(fn ($r) => $r['message'] ?? __('ops.scanner.error_title'), $failed)))];
            $this->phase = 'error';
            $this->dispatch('sfx', name: 'error');

            return;
        }
        $tickets = array_map(fn ($r) => $r['ticket'], $ok);
        $this->done = [
            'count' => count($ok),
            'types' => collect($tickets)->countBy('ticket_type_label')->all(),
            'holders' => collect($tickets)->map(fn ($t) => ['name' => $t['ticket_holder_name'], 'type' => $t['ticket_type_label']])->all(),
            'collect' => collect($ok)->contains(fn ($r) => $r['collect_cash'] ?? false),
            'failures' => array_map(fn ($r) => $r['message'] ?? '', $failed),
        ];
        $this->phase = 'success';
        $this->selected = [];
        $this->toast('success', __('ops.scanner.confirmed_n', ['n' => count($ok)]));
        $top = collect($tickets)->sortBy(fn ($t) => ['vip' => 0, 'normal' => 1, 'parking' => 2][$t['ticket_type']] ?? 3)->first();
        $this->dispatch('sfx', name: 'checkin', type: $top['ticket_type'] ?? null);
        $this->dispatch('scanner-done', camera: $this->fromCamera);
    }

    public function newScan(): void
    {
        $this->reset_();
        $this->manual = '';
        $this->dispatch('scanner-resume');
    }

    public function closeAlert(): void
    {
        $camera = $this->fromCamera;
        $this->reset_();
        if ($camera) {
            $this->dispatch('scanner-resume');
        }
    }

    // ---- History & undo (F-GATE-035/036) ------------------------------------------------

    public function undo(string $holderId, string $reason = ''): void
    {
        try {
            $res = app(CheckInService::class)->reset($holderId, Actor::current(), Text::limit(trim($reason), 300));
        } catch (DomainError $e) {
            $this->toast('error', $e->userMessage() ?: __('ops.scanner.undo_failed'));

            return;
        }
        ($res['success'] ?? false)
            ? $this->toast('success', $res['message'])
            : $this->toast('error', $res['message'] ?? __('ops.scanner.undo_failed'));
    }

    protected function toast(string $type, string $message, ?string $title = null, int $duration = 4500): void
    {
        $this->dispatch('toast', type: $type, message: $message, title: $title, duration: $duration);
    }

    public function render()
    {
        $actor = Actor::current();
        $eventId = $this->historyAll ? null : rescue(fn () => app(EventSettings::class)->current_event_id, null, false);
        $history = app(CheckInService::class)->history($eventId, (int) config('qcamelmc.gate.history_limit', 30));

        return view('livewire.staff.scanner', [
            'staff' => PosUser::query()->active()->orderBy('name')->get(['id', 'name', 'icon']),
            'history' => $history,
            'canUndo' => $actor->can('checkin.reset'),
            'pii' => $actor->canSeePii(),
        ])->title(__('ops.scanner.title'));
    }
}
