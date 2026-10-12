<?php

namespace App\Filament\Pages;

use App\Domain\Shared\Actor;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\QatarClock;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * ADM-NTF — live feed of incoming bookings, POS cash vs online (Sadad),
 * Qatar-day filter, server-side per-admin read state (R-172), confirmed
 * totals shown apart from pending/cancelled, "load more" instead of a
 * silent 200-row cap.
 */
class NotificationsFeed extends Page
{
    protected string $view = 'filament.pages.notifications-feed';

    protected static ?int $navigationSort = -1;

    protected static ?string $slug = 'notifications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    private const PAGE = 40;

    #[Url]
    public string $range = 'today';

    #[Url]
    public ?string $day = null;

    public int $limit = self::PAGE;

    public ?string $openId = null;

    public static function getNavigationLabel(): string
    {
        return __('admin.ntf.title');
    }

    public function getTitle(): string
    {
        return __('admin.ntf.title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.ntf.sub');
    }

    public static function canAccess(): bool
    {
        return Actor::current()->can('notifications.view');
    }

    /** F-ADM-SHL-011 rebuild: new bookings today since this admin last marked them read. */
    public static function unreadCount(): int
    {
        $user = auth()->user();
        if (! $user) {
            return 0;
        }
        [$from] = QatarClock::dayRange(QatarClock::today());
        $since = $user->notifications_seen_at && $user->notifications_seen_at->greaterThan($from) ? $user->notifications_seen_at : $from;

        return Order::query()->where('created_at', '>', $since)->count();
    }

    public static function getNavigationBadge(): ?string
    {
        if (request()->routeIs(static::getRouteName())) {
            return null;
        }
        $n = static::unreadCount();

        return $n > 0 ? ($n > 99 ? '+99' : (string) $n) : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function updatedRange(): void
    {
        $this->limit = self::PAGE;
        if ($this->range === 'day' && ! $this->day) {
            $this->day = QatarClock::today();
        }
    }

    public function updatedDay(): void
    {
        $this->limit = self::PAGE;
    }

    public function loadMore(): void
    {
        $this->limit = min($this->limit + self::PAGE, 1000);
    }

    public function markAllRead(): void
    {
        auth()->user()?->forceFill(['notifications_seen_at' => now()])->save();
    }

    public function open(string $id): void
    {
        $this->openId = $id;
        $this->dispatch('open-modal', id: 'ntf-details');
    }

    private function scoped(): Builder
    {
        $date = match ($this->range) {
            'today' => QatarClock::today(),
            'yesterday' => QatarClock::local(QatarClock::today())->subDay()->toDateString(),
            'day' => $this->day && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->day) ? $this->day : QatarClock::today(),
            default => null,
        };

        return Order::query()->when($date, function (Builder $q) use ($date) {
            [$from, $to] = QatarClock::dayRange($date);
            $q->where('created_at', '>=', $from)->where('created_at', '<', $to);
        });
    }

    /** @return array{rows:Collection, total:int, confirmed:string, pending:string, cancelled:string, nConfirmed:int, nPending:int} */
    private function column(bool $pos): array
    {
        $base = $this->scoped()->where('payment_method', $pos ? 'cash_pos' : 'sadad');
        $sums = (clone $base)->selectRaw('payment_status as s, COUNT(*) as n, COALESCE(SUM(total_amount),0) as a')->groupBy('payment_status')->get()->keyBy('s');

        return [
            'rows' => (clone $base)->with(['customer:id,name,phone', 'event:id,title,event_date'])->latest()->limit($this->limit)->get(),
            'total' => (int) $sums->sum('n'),
            'confirmed' => (string) ($sums['confirmed']->a ?? 0),
            'pending' => (string) ($sums['pending']->a ?? 0),
            'cancelled' => (string) ($sums['cancelled']->a ?? 0),
            'nConfirmed' => (int) ($sums['confirmed']->n ?? 0),
            'nPending' => (int) ($sums['pending']->n ?? 0),
        ];
    }

    protected function getViewData(): array
    {
        $seen = auth()->user()?->notifications_seen_at;
        $pos = $this->column(true);
        $online = $this->column(false);
        $open = $this->openId ? Order::query()->with(['customer', 'event', 'holders'])->find($this->openId) : null;

        return [
            'columns' => ['pos' => $pos, 'online' => $online],
            'seen' => $seen,
            'unread' => static::unreadCount(),
            'activeDay' => $this->range === 'all' ? null : match ($this->range) {
                'today' => QatarClock::today(),
                'yesterday' => QatarClock::local(QatarClock::today())->subDay()->toDateString(),
                default => $this->day ?: QatarClock::today(),
            },
            'open' => $open,
            'orderUrl' => fn (Order $o) => OrderResource::getUrl('view', ['record' => $o]),
            'statusColor' => fn (?PaymentStatus $s) => $s?->color() ?? 'gray',
        ];
    }
}
