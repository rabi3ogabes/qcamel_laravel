<?php

namespace App\Filament\Pages;

use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exports\OrdersReport;
use App\Filament\Concerns\InNavigationGroup;
use App\Models\Event;
use App\Models\Order;
use App\Support\Audit;
use App\Support\Formats;
use App\Support\QatarClock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** ADM-RPT — filtered multi-sheet Excel report with a live preview of what will be exported. */
class Reports extends Page
{
    use InNavigationGroup;

    protected string $view = 'filament.pages.reports';

    protected static string $navGroup = 'analytics';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('admin.rpt.title');
    }

    public function getTitle(): string
    {
        return __('admin.rpt.title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.rpt.sub');
    }

    public static function canAccess(): bool
    {
        return Actor::current()->can('reports.export');
    }

    public function mount(): void
    {
        $this->form->fill(['variant' => 'full']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make(__('admin.rpt.options'))->icon(Heroicon::OutlinedAdjustmentsHorizontal)->schema([
                ToggleButtons::make('variant')->label(__('admin.rpt.variant'))->inline()->live()
                    ->options(['full' => __('admin.rpt.variant_full'), 'sadad' => __('admin.rpt.variant_sadad')])
                    ->icons(['full' => Heroicon::OutlinedTableCells, 'sadad' => Heroicon::OutlinedCreditCard]),
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])->schema([
                    DatePicker::make('from')->label(__('admin.rpt.from'))->native(false)->displayFormat('d/m/Y')->live()->closeOnDateSelection()->maxDate(QatarClock::today()),
                    DatePicker::make('to')->label(__('admin.rpt.to'))->native(false)->displayFormat('d/m/Y')->live()->closeOnDateSelection()->afterOrEqual('from'),
                    Select::make('event_id')->label(__('admin.rpt.event'))->live()->searchable()->placeholder(__('common.all'))
                        ->options(fn () => Event::query()->orderByDesc('event_date')->get(['id', 'title', 'event_date'])->mapWithKeys(fn ($e) => [$e->id => Formats::dayMonth($e->event_date).' — '.$e->title])->all()),
                    Select::make('status')->label(__('admin.rpt.status'))->live()->placeholder(__('common.all'))->options(PaymentStatus::options()),
                ]),
                Select::make('method')->label(__('admin.rpt.method'))->live()->placeholder(__('common.all'))->options(PaymentMethod::options())
                    ->visible(fn ($get) => $get('variant') !== 'sadad')->columnSpan(1),
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(): array
    {
        $d = $this->data ?? [];

        return [
            'variant' => $d['variant'] ?? 'full',
            'from' => isset($d['from']) && $d['from'] ? substr((string) $d['from'], 0, 10) : null,
            'to' => isset($d['to']) && $d['to'] ? substr((string) $d['to'], 0, 10) : null,
            'event_id' => $d['event_id'] ?? null,
            'status' => $d['status'] ?? null,
            'method' => ($d['variant'] ?? 'full') === 'sadad' ? null : ($d['method'] ?? null),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label(__('admin.rpt.export'))->icon(Heroicon::OutlinedArrowDownTray)->size('lg')
                ->action(fn () => $this->export()),
        ];
    }

    public function export(): ?BinaryFileResponse
    {
        $f = $this->filters();
        if ($this->preview()['n'] === 0) {
            Notification::make()->warning()->title(__('admin.rpt.no_data'))->send();

            return null;
        }
        Audit::log(ActivityType::DataManagement, __('admin.rpt.audit'), null, ['filters' => $f]);
        $name = __($f['variant'] === 'sadad' ? 'admin.rpt.file_sadad' : 'admin.rpt.file_full').'-'.QatarClock::now()->format('Y-m-d-His').'.xlsx';
        Notification::make()->success()->title(__('admin.rpt.exported'))->send();

        return Excel::download(new OrdersReport($f), $name);
    }

    /** Live preview: what the file will contain. */
    public function preview(): array
    {
        $f = $this->filters();
        $q = Order::query()
            ->when($f['variant'] === 'sadad', fn ($q) => $q->where('payment_method', 'sadad'))
            ->when($f['method'], fn ($q, $m) => $q->where('payment_method', $m))
            ->when($f['status'], fn ($q, $s) => $q->where('payment_status', $s))
            ->when($f['event_id'], fn ($q, $e) => $q->where('event_id', $e))
            ->when($f['from'], fn ($q, $d) => $q->where('created_at', '>=', QatarClock::dayRange($d)[0]))
            ->when($f['to'], fn ($q, $d) => $q->where('created_at', '<', QatarClock::dayRange($d)[1]));
        $by = (clone $q)->selectRaw('payment_status as s, COUNT(*) as n, COALESCE(SUM(total_amount),0) as a, COALESCE(SUM(quantity),0) as t')->groupBy('payment_status')->get()->keyBy('s');

        return [
            'n' => (int) $by->sum('n'),
            'tickets' => (int) $by->sum('t'),
            'by' => collect(PaymentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ['n' => (int) ($by[$s->value]->n ?? 0), 'a' => (string) ($by[$s->value]->a ?? 0)]])->all(),
            'days' => (clone $q)->whereNotNull('event_id')->distinct()->count('event_id'),
        ];
    }

    protected function getViewData(): array
    {
        return ['p' => $this->preview(), 'pii' => Actor::current()->can('customers.view_pii')];
    }
}
