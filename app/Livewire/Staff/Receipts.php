<?php

namespace App\Livewire\Staff;

use App\Domain\Receipts\ParseReceiptAction;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\ActivityType;
use App\Events\PosReceiptChanged;
use App\Exports\ReceiptsExport;
use App\Models\Event;
use App\Models\PosReceipt;
use App\Settings\BusinessSettings;
use App\Support\Audit;
use App\Support\Money;
use App\Support\QatarClock;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * RCP — card-receipt reader (F-RCP-001…023): OCR first, private storage,
 * duplicate guard, debounced count edits, summary priced from settings,
 * styled Excel export, signed short-lived image links.
 */
#[Layout('components.layouts.staff')]
class Receipts extends Component
{
    use WithFileUploads;

    #[Validate('image|mimes:jpeg,jpg,png,webp,heic,heif|max:8192')]
    public $photo;

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'to')]
    public string $to = '';

    #[Url(as: 'event')]
    public ?string $eventId = null;

    public function mount(): void
    {
        $today = QatarClock::today();
        $this->from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->from) ? $this->from : $today;
        $this->to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->to) ? $this->to : $today;
    }

    private function canManage(): bool
    {
        return Actor::current()->can('receipts.manage');
    }

    public function updatedPhoto(ParseReceiptAction $parse): void
    {
        $this->validateOnly('photo');
        $actor = Actor::current();
        $key = 'receipts:'.($actor->identifier() ?? request()->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->toast('error', __('receipts.rate_limited'));

            return;
        }
        RateLimiter::hit($key, 60);
        try {
            $parse->handle($this->photo, $actor, $this->eventId);
            $this->toast('success', __('ops.receipts.saved'));
            $this->dispatch('sfx', name: 'success');
        } catch (DomainError $e) {
            $this->toast('error', $e->userMessage());
        } catch (Throwable $e) {
            report($e);
            $this->toast('error', __('ops.receipts.failed'));
        } finally {
            $this->photo = null;
        }
    }

    public function updateCount(string $id, string $field, mixed $value): void
    {
        if (! $this->canManage() || ! in_array($field, ['normal_tickets', 'vip_tickets', 'parking_tickets'], true)) {
            $this->toast('error', __('errors.forbidden'));

            return;
        }
        $n = max(0, min(999, (int) $value));
        $r = PosReceipt::query()->find($id);
        if (! $r) {
            return;
        }
        $old = $r->{$field};
        $r->update([$field => $n]);
        event(new PosReceiptChanged($r->id, 'updated'));
        Audit::log(ActivityType::SettingsChanged, __('audit.receipt_counts', ['seq' => $r->seq_number ?? '—']), $r, ['field' => $field, 'old' => $old, 'new' => $n], Actor::current());
    }

    public function delete(string $id): void
    {
        if (! $this->canManage()) {
            $this->toast('error', __('errors.forbidden'));

            return;
        }
        $r = PosReceipt::query()->find($id);
        if ($r) {
            Audit::log(ActivityType::SettingsChanged, __('audit.receipt_deleted', ['seq' => $r->seq_number ?? '—']), null, $r->only(['amount_qar', 'seq_number', 'auth_number', 'card_number_masked', 'time']), Actor::current());
            $r->deleteImage();
            $r->delete();
            event(new PosReceiptChanged($id, 'deleted'));
            $this->toast('success', __('ops.receipts.deleted'));
        }
        $this->dispatch('close-dialog');
    }

    private function prices(): array
    {
        $b = rescue(fn () => app(BusinessSettings::class), null, false);

        return ['normal' => (string) ($b?->receipt_price_normal ?? '200'), 'vip' => (string) ($b?->receipt_price_vip ?? '300'), 'parking' => (string) ($b?->receipt_price_parking ?? '500')];
    }

    private function query()
    {
        [$start] = QatarClock::dayRange(min($this->from, $this->to));
        [, $end] = QatarClock::dayRange(max($this->from, $this->to));

        return PosReceipt::query()->whereBetween('created_at', [$start, $end])
            ->when($this->eventId, fn ($q) => $q->where('event_id', $this->eventId))
            ->latest();
    }

    public function export()
    {
        if (! $this->canManage()) {
            $this->toast('error', __('errors.forbidden'));

            return null;
        }
        $rows = $this->query()->get();
        if ($rows->isEmpty()) {
            $this->toast('error', __('ops.receipts.no_data'));

            return null;
        }
        $title = (rescue(fn () => app(BusinessSettings::class)->receipt_title, null, false) ?: __('ops.receipts.title')).' - بتاريخ '.now('Asia/Qatar')->format('d/m/Y').' (POS)';

        return Excel::download(new ReceiptsExport($rows, $this->prices(), $title), 'pos_receipts_'.QatarClock::today().'.xlsx');
    }

    protected function toast(string $type, string $message): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }

    public function render()
    {
        $rows = $this->query()->limit(500)->get();
        $prices = $this->prices();
        $sum = ['normal' => (int) $rows->sum('normal_tickets'), 'vip' => (int) $rows->sum('vip_tickets'), 'parking' => (int) $rows->sum('parking_tickets')];
        $amounts = array_map(fn ($t) => Money::of($prices[$t])->multiply($sum[$t]), array_combine(array_keys($sum), array_keys($sum)));
        $expected = array_reduce($amounts, fn ($c, $m) => $c->add($m), Money::zero());
        $ocr = $rows->reduce(fn ($c, $r) => $c->add(Money::tryOf($r->amount_qar) ?? Money::zero()), Money::zero());

        return view('livewire.staff.receipts', [
            'rows' => $rows,
            'canManage' => $this->canManage(),
            'prices' => $prices,
            'sum' => $sum,
            'amounts' => $amounts,
            'expected' => $expected,
            'ocr' => $ocr,
            'events' => Event::query()->currentSeason()->orderBy('event_date')->get(['id', 'title', 'event_date']),
        ])->title(__('ops.receipts.title'));
    }
}
