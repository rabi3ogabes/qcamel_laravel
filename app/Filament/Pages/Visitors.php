<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\VisitorAnalyticsQuery;
use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Filament\Concerns\InNavigationGroup;
use App\Models\ActiveVisitor;
use App\Support\Audit;
use App\Support\QatarClock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * ADM-VIS-001…008 + U-ADM-VIS-01/02 — live visitors (cutoff window, 30 s
 * refresh), breakdowns, IPs masked unless owner, and range analytics:
 * top pages, countries and the visit → paid → scanned funnel.
 */
class Visitors extends Page
{
    use InNavigationGroup;

    protected string $view = 'filament.pages.visitors';

    protected static string $navGroup = 'analytics';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'visitors';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    #[Url]
    public int $days = 7;

    public static function getNavigationLabel(): string
    {
        return __('admin.vis.title');
    }

    public function getTitle(): string
    {
        return __('admin.vis.title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.vis.sub');
    }

    public static function canAccess(): bool
    {
        return Actor::current()->can('visitors.view');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = ActiveVisitor::query()->activeNow()->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear')->label(__('admin.vis.clear'))->icon(Heroicon::OutlinedTrash)->color('danger')->outlined()
                ->visible(fn () => Actor::current()->isSuperAdmin())
                ->requiresConfirmation()->modalDescription(__('admin.vis.clear_desc'))
                ->action(function () {
                    $n = ActiveVisitor::query()->delete();
                    Audit::log(ActivityType::DataManagement, __('admin.vis.audit_clear', ['n' => $n]), null, ['deleted' => $n]);
                    Notification::make()->success()->title(__('admin.vis.cleared'))->send();
                }),
        ];
    }

    public static function maskIp(?string $ip): string
    {
        if (! $ip) {
            return '—';
        }
        if (Actor::current()->isSuperAdmin()) {
            return $ip;
        }
        if (str_contains($ip, ':')) {
            return implode(':', array_slice(explode(':', $ip), 0, 3)).':••••';
        }

        return preg_replace('/\.\d+$/', '.•••', $ip);
    }

    protected function getViewData(): array
    {
        $q = app(VisitorAnalyticsQuery::class);
        $days = in_array($this->days, [1, 7, 30], true) ? $this->days : 7;
        $to = QatarClock::today();
        $from = QatarClock::local($to)->subDays($days - 1)->toDateString();

        return [
            'live' => $q->live(),
            'pages' => $q->topPages($from, $to, 10),
            'countries' => $q->topCountries($from, $to, 10),
            'funnel' => $q->funnel($from, $to),
            'unique' => $q->uniqueInRange($from, $to),
        ];
    }
}
