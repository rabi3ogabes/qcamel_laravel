<?php

namespace App\Filament\Pages;

use App\Domain\Payments\Sadad\SadadDiagnose;
use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Exports\SimpleTableExport;
use App\Filament\Concerns\InNavigationGroup;
use App\Imports\EventsImport;
use App\Models\Customer;
use App\Models\Event;
use App\Models\TicketType;
use App\Settings\AnalyticsSettings;
use App\Settings\AutomationSettings;
use App\Settings\BrandingSettings;
use App\Settings\BusinessSettings;
use App\Settings\ContactSettings;
use App\Settings\FeatureFlagSettings;
use App\Settings\SadadSettings;
use App\Support\Audit;
use App\Support\PublicSettings;
use App\Support\QatarClock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * ADM-SET — settings in tabs, one Save per section (no coupled saves),
 * secrets write-only (shown as set / not set), uploads replace old files,
 * every change audited with secret values redacted.
 */
class Settings extends Page
{
    use InNavigationGroup;

    protected string $view = 'filament.pages.settings';

    protected static string $navGroup = 'system';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    /** @var array<string,mixed> */
    public ?array $data = [];

    public ?array $diagnose = null;

    public static function getNavigationLabel(): string
    {
        return __('admin.set.title');
    }

    public function getTitle(): string
    {
        return __('admin.set.title');
    }

    public static function canAccess(): bool
    {
        return Actor::current()->can('settings.view');
    }

    private function canManage(): bool
    {
        return Actor::current()->can('settings.manage');
    }

    public function mount(): void
    {
        $b = app(BrandingSettings::class);
        $a = app(AutomationSettings::class);
        $s = app(SadadSettings::class);
        $c = app(ContactSettings::class);
        $an = app(AnalyticsSettings::class);
        $f = app(FeatureFlagSettings::class);
        $bz = app(BusinessSettings::class);

        $this->form->fill([
            'branding' => [
                'logo_url' => $b->logo_url, 'hero_image_url' => $b->hero_image_url, 'header_bg_color' => $b->header_bg_color ?: '#D4B78A',
                'header_bg_image_url' => $b->header_bg_image_url, 'before_footer_image_url' => $b->before_footer_image_url,
                'hero_text' => $b->hero_text, 'copyright_text' => $b->copyright_text, 'festival_name' => $b->festival_name, 'season_label' => $b->season_label,
                'og_image_url' => $b->og_image_url, 'site_title_ar' => $b->site_title_ar, 'site_title_en' => $b->site_title_en,
                'site_description_ar' => $b->site_description_ar, 'site_description_en' => $b->site_description_en,
            ],
            'flags' => [
                'show_delete_customer_button' => $f->show_delete_customer_button, 'show_delete_order_button' => $f->show_delete_order_button,
                'show_generate_qr_button' => $f->show_generate_qr_button, 'show_delete_event_button' => $f->show_delete_event_button,
                'show_delete_ticket_button' => $f->show_delete_ticket_button, 'admit_unpaid_cash_bookings' => $f->admit_unpaid_cash_bookings,
                'public_cash_at_venue_enabled' => $f->public_cash_at_venue_enabled, 'passcode_login_enabled' => $f->passcode_login_enabled,
                'pos_cashier_required' => $f->pos_cashier_required,
            ],
            'automation' => [
                'webhook_enabled' => $a->webhook_enabled, 'webhook_url_new' => null, 'email_webhook_enabled' => $a->email_webhook_enabled, 'email_webhook_url_new' => null,
                'auto_invoice_interval_seconds' => $a->auto_invoice_interval_seconds, 'invoice_batch_min' => $a->invoice_batch_min, 'invoice_batch_max' => $a->invoice_batch_max,
                'invoice_send_delay_min' => $a->invoice_send_delay_min, 'invoice_send_delay_max' => $a->invoice_send_delay_max,
                'invoice_excluded_dates' => $a->invoice_excluded_dates ?? [], 'payment_failed_email_enabled' => $a->payment_failed_email_enabled,
            ],
            'sadad' => [
                'merchant_id' => $s->merchant_id, 'website_domain' => $s->website_domain, 'environment' => $s->environment ?: 'auto', 'site_url' => $s->site_url,
                'checkout_secret_new' => null, 'api_login_secret_new' => null, 'support_pin_new' => null,
            ],
            'contact' => [
                'admin_phone' => $c->admin_phone, 'publish_admin_phone' => $c->publish_admin_phone, 'support_whatsapp' => $c->support_whatsapp,
                'admin_emails' => $c->admin_emails ?? [],
                'clarity_enabled' => $an->clarity_enabled, 'clarity_project_id' => $an->clarity_project_id, 'require_consent' => $an->require_consent, 'mask_ip' => $an->mask_ip,
            ],
            'business' => [
                'terms_ar' => collect($bz->terms_ar)->map(fn ($l) => ['line' => $l])->all(), 'terms_en' => collect($bz->terms_en)->map(fn ($l) => ['line' => $l])->all(),
                'receipt_price_normal' => $bz->receipt_price_normal, 'receipt_price_vip' => $bz->receipt_price_vip, 'receipt_price_parking' => $bz->receipt_price_parking,
                'receipt_title' => $bz->receipt_title, 'low_stock_threshold' => $bz->low_stock_threshold, 'activity_log_retention_days' => $bz->activity_log_retention_days,
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $readOnly = ! $this->canManage();
        $secretState = fn (?string $v) => filled($v) ? '✓ '.__('admin.set.secret_set') : '— '.__('admin.set.secret_unset');
        $s = app(SadadSettings::class);
        $a = app(AutomationSettings::class);
        $upload = fn (string $name, string $label, int $kb, array $types = ['image/png', 'image/jpeg', 'image/webp']) => FileUpload::make($name)->label($label)
            ->disk('branding')->directory($name)->image()->maxSize($kb)->acceptedFileTypes($types)->imagePreviewHeight('120')
            ->getUploadedFileNameForStorageUsing(fn ($file) => $name.'-'.now()->format('YmdHis').'.'.$file->getClientOriginalExtension())
            ->dehydrated(true)->disabled($readOnly);
        $save = fn (string $section) => Actions::make([
            Action::make('save_'.$section)->label(__('common.save'))->icon(Heroicon::OutlinedCheck)->visible(! $readOnly)
                ->action(fn () => $this->saveSection($section)),
        ])->alignEnd();

        return $schema->statePath('data')->components([
            Tabs::make('settings')->persistTabInQueryString()->tabs([
                Tab::make(__('admin.set.tabs.design'))->icon(Heroicon::OutlinedPaintBrush)->schema([
                    Section::make(__('admin.set.brand'))->columns(2)->schema([
                        TextInput::make('branding.festival_name')->label(__('admin.set.festival_name'))->maxLength(120)->disabled($readOnly),
                        TextInput::make('branding.season_label')->label(__('admin.set.season_label'))->maxLength(80)->disabled($readOnly),
                        $upload('logo_upload', __('admin.set.logo'), 5120)->helperText(__('admin.set.logo_hint')),
                        TextInput::make('branding.logo_url')->label(__('admin.set.logo_url'))->url()->maxLength(2048)->disabled($readOnly)
                            ->helperText(__('admin.set.url_or_upload')),
                        ColorPicker::make('branding.header_bg_color')->label(__('admin.set.header_color'))->hex()->regex('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i')->disabled($readOnly),
                        TextInput::make('branding.copyright_text')->label(__('admin.set.copyright'))->maxLength(200)->placeholder('جميع الحقوق محفوظة')->disabled($readOnly),
                        Textarea::make('branding.hero_text')->label(__('admin.set.hero_text'))->rows(3)->maxLength(500)->columnSpanFull()->helperText(__('admin.set.hero_text_hint'))->disabled($readOnly),
                        $save('branding'),
                    ]),
                    Section::make(__('admin.set.images'))->columns(3)->collapsible()->schema([
                        $upload('hero_upload', __('admin.set.hero_image'), 10240)->helperText(__('admin.set.hero_hint')),
                        $upload('header_bg_upload', __('admin.set.header_image'), 10240)->helperText(__('admin.set.header_image_hint')),
                        $upload('before_footer_upload', __('admin.set.before_footer'), 10240, ['image/png', 'image/webp'])->helperText(__('admin.set.before_footer_hint')),
                        Grid::make(3)->columnSpanFull()->schema([
                            Toggle::make('branding.remove_hero')->label(__('admin.set.remove_image'))->visible(fn () => filled(app(BrandingSettings::class)->hero_image_url))->disabled($readOnly),
                            Toggle::make('branding.remove_header_bg')->label(__('admin.set.remove_image'))->visible(fn () => filled(app(BrandingSettings::class)->header_bg_image_url))->disabled($readOnly),
                            Toggle::make('branding.remove_before_footer')->label(__('admin.set.remove_image'))->visible(fn () => filled(app(BrandingSettings::class)->before_footer_image_url))->disabled($readOnly),
                        ]),
                        $save('branding'),
                    ]),
                    Section::make(__('admin.set.seo'))->columns(2)->collapsed()->schema([
                        TextInput::make('branding.site_title_ar')->label(__('admin.set.site_title_ar'))->maxLength(120)->disabled($readOnly),
                        TextInput::make('branding.site_title_en')->label(__('admin.set.site_title_en'))->maxLength(120)->disabled($readOnly),
                        Textarea::make('branding.site_description_ar')->label(__('admin.set.site_desc_ar'))->rows(2)->maxLength(300)->disabled($readOnly),
                        Textarea::make('branding.site_description_en')->label(__('admin.set.site_desc_en'))->rows(2)->maxLength(300)->disabled($readOnly),
                        TextInput::make('branding.og_image_url')->label(__('admin.set.og_image'))->url()->columnSpanFull()->disabled($readOnly),
                        $save('branding'),
                    ]),
                    Section::make(__('admin.set.buttons'))->columns(2)->schema([
                        Toggle::make('flags.show_delete_customer_button')->label(__('admin.set.flag_delete_customer'))->disabled($readOnly),
                        Toggle::make('flags.show_delete_order_button')->label(__('admin.set.flag_delete_order'))->disabled($readOnly),
                        Toggle::make('flags.show_generate_qr_button')->label(__('admin.set.flag_generate_qr'))->disabled($readOnly),
                        Toggle::make('flags.show_delete_event_button')->label(__('admin.set.flag_delete_event'))->disabled($readOnly),
                        Toggle::make('flags.show_delete_ticket_button')->label(__('admin.set.flag_delete_ticket'))->disabled($readOnly),
                        Text::make(__('admin.set.flags_note'))->columnSpanFull(),
                        $save('flags'),
                    ]),
                ]),

                Tab::make(__('admin.set.tabs.messaging'))->icon(Heroicon::OutlinedChatBubbleLeftRight)->schema([
                    Section::make(__('admin.set.whatsapp'))->columns(2)->schema([
                        Toggle::make('automation.webhook_enabled')->label(__('admin.set.enabled'))->disabled($readOnly),
                        Text::make(fn () => __('admin.set.current_url').': '.$secretState($a->webhook_url)),
                        TextInput::make('automation.webhook_url_new')->label(__('admin.set.replace_url'))->url()->rule('starts_with:https://')->maxLength(2048)
                            ->placeholder('https://your-n8n-instance.com/webhook/...')->columnSpanFull()->helperText(__('admin.set.whatsapp_hint'))->disabled($readOnly),
                        Toggle::make('automation.email_webhook_enabled')->label(__('admin.set.email_webhook_enabled'))->disabled($readOnly),
                        Text::make(fn () => __('admin.set.current_url').': '.$secretState($a->email_webhook_url)),
                        TextInput::make('automation.email_webhook_url_new')->label(__('admin.set.replace_email_url'))->url()->rule('starts_with:https://')->maxLength(2048)->columnSpanFull()->disabled($readOnly),
                        Actions::make([
                            Action::make('test_whatsapp')->label(__('admin.set.send_test'))->icon(Heroicon::OutlinedBeaker)->color('gray')->visible(! $readOnly)
                                ->action(fn () => $this->testWebhook()),
                            Action::make('save_automation_hooks')->label(__('common.save'))->icon(Heroicon::OutlinedCheck)->visible(! $readOnly)->action(fn () => $this->saveSection('automation')),
                        ])->alignEnd()->columnSpanFull(),
                    ]),
                    Section::make(__('admin.set.auto_invoice'))->columns(3)->schema([
                        TextInput::make('automation.auto_invoice_interval_seconds')->label(__('admin.set.interval'))->integer()->minValue(30)->required()->suffix(__('admin.set.seconds'))->disabled($readOnly),
                        TextInput::make('automation.invoice_batch_min')->label(__('admin.set.batch_min'))->integer()->minValue(1)->maxValue(50)->required()->disabled($readOnly),
                        TextInput::make('automation.invoice_batch_max')->label(__('admin.set.batch_max'))->integer()->minValue(1)->maxValue(50)->gte('automation.invoice_batch_min')->required()->disabled($readOnly),
                        TextInput::make('automation.invoice_send_delay_min')->label(__('admin.set.delay_min'))->integer()->minValue(0)->maxValue(3600)->required()->suffix(__('admin.set.seconds'))->disabled($readOnly),
                        TextInput::make('automation.invoice_send_delay_max')->label(__('admin.set.delay_max'))->integer()->minValue(0)->maxValue(3600)->gte('automation.invoice_send_delay_min')->required()->suffix(__('admin.set.seconds'))->disabled($readOnly),
                        TagsInput::make('automation.invoice_excluded_dates')->label(__('admin.set.excluded_dates'))->placeholder('2025-11-06')->helperText(__('admin.set.excluded_dates_hint'))->disabled($readOnly),
                        Text::make(__('admin.set.auto_invoice_note'))->columnSpanFull(),
                        $save('automation'),
                    ]),
                    Section::make(__('admin.set.mail'))->columns(2)->schema([
                        Toggle::make('automation.payment_failed_email_enabled')->label(__('admin.set.payment_failed_mail'))->helperText(__('admin.set.payment_failed_hint'))->disabled($readOnly),
                        TagsInput::make('contact.admin_emails')->label(__('admin.set.admin_emails'))->placeholder('admin@example.com')
                            ->nestedRecursiveRules(['email:rfc'])->helperText(__('admin.set.admin_emails_hint'))->disabled($readOnly),
                        Actions::make([
                            Action::make('test_mail')->label(__('admin.set.send_test_mail'))->icon(Heroicon::OutlinedEnvelope)->color('gray')->visible(! $readOnly)->action(fn () => $this->testMail()),
                            Action::make('save_mail')->label(__('common.save'))->icon(Heroicon::OutlinedCheck)->visible(! $readOnly)->action(fn () => $this->saveSection('mail')),
                        ])->alignEnd()->columnSpanFull(),
                    ]),
                ]),

                Tab::make(__('admin.set.tabs.payments'))->icon(Heroicon::OutlinedCreditCard)->schema([
                    Section::make(__('admin.set.sadad'))->columns(2)->schema([
                        TextInput::make('sadad.merchant_id')->label(__('admin.set.merchant_id'))->regex('/^\d{4,20}$/')->disabled($readOnly),
                        TextInput::make('sadad.website_domain')->label(__('admin.set.website_domain'))->regex('/^[a-z0-9.-]+\.[a-z]{2,}$/i')
                            ->placeholder('qcamelmc.org')->helperText(__('admin.set.website_domain_hint'))->disabled($readOnly),
                        TextInput::make('sadad.checkout_secret_new')->label(__('admin.set.checkout_secret'))->password()->revealable(false)->autocomplete('new-password')
                            ->helperText(fn () => $secretState($s->checkout_secret).' · '.__('admin.set.replace_only'))->disabled($readOnly),
                        TextInput::make('sadad.api_login_secret_new')->label(__('admin.set.api_login_secret'))->password()->revealable(false)->autocomplete('new-password')
                            ->helperText(fn () => $secretState($s->api_login_secret).' · '.__('admin.set.api_login_hint'))->disabled($readOnly),
                        TextInput::make('sadad.support_pin_new')->label(__('admin.set.support_pin'))->password()->revealable(false)
                            ->helperText(fn () => $secretState($s->support_pin))->disabled($readOnly),
                        Select::make('sadad.environment')->label(__('admin.set.environment'))->native(false)->required()
                            ->options(['auto' => __('admin.set.env_auto'), 'sandbox' => __('admin.set.env_sandbox'), 'live' => __('admin.set.env_live')])
                            ->helperText(__('admin.set.env_hint'))->disabled($readOnly),
                        TextInput::make('sadad.site_url')->label(__('admin.set.site_url'))->url()->rule('regex:#^https://[a-z0-9.-]+\.[a-z]{2,}(:\d+)?/?$#i')
                            ->placeholder('https://qcamelmc.org')->helperText(__('admin.set.site_url_hint'))->columnSpanFull()->disabled($readOnly),
                        $save('sadad'),
                    ]),
                    Section::make(__('admin.set.diagnose'))->description(__('admin.set.diagnose_intro'))->schema([
                        Actions::make([Action::make('run_diagnose')->label(__('admin.set.run_diagnose'))->icon(Heroicon::OutlinedSignal)->action(fn () => $this->runDiagnose())]),
                        Text::make(fn () => $this->diagnoseHtml()),
                    ]),
                    Section::make(__('admin.set.guide'))->collapsed()->schema([Text::make(new HtmlString(view('filament.partials.sadad-guide', ['merchant' => $s->merchant_id, 'domain' => $s->website_domain])->render()))]),
                    Section::make(__('admin.set.cash'))->columns(2)->schema([
                        Toggle::make('flags.public_cash_at_venue_enabled')->label(__('admin.set.public_cash'))->helperText(__('admin.set.public_cash_hint'))->disabled($readOnly),
                        Toggle::make('flags.admit_unpaid_cash_bookings')->label(__('admin.set.admit_cash'))->helperText(__('admin.set.admit_cash_hint'))->disabled($readOnly),
                        Toggle::make('flags.pos_cashier_required')->label(__('admin.set.cashier_required'))->disabled($readOnly),
                        Toggle::make('flags.passcode_login_enabled')->label(__('admin.set.passcode_login'))->helperText(__('admin.set.passcode_hint'))->disabled($readOnly),
                        $save('flags'),
                    ]),
                ]),

                Tab::make(__('admin.set.tabs.contact'))->icon(Heroicon::OutlinedPhone)->schema([
                    Section::make(__('admin.set.contact'))->columns(2)->schema([
                        TextInput::make('contact.admin_phone')->label(__('admin.set.admin_phone'))->tel()->regex('/^\+?[\d\s\-()]{8,20}$/')->placeholder('+974 XXXX XXXX')->disabled($readOnly),
                        Toggle::make('contact.publish_admin_phone')->label(__('admin.set.publish_phone'))->helperText(__('admin.set.publish_phone_hint'))->disabled($readOnly),
                        TextInput::make('contact.support_whatsapp')->label(__('admin.set.support_whatsapp'))->tel()->regex('/^\+?[\d\s\-()]{8,20}$/')->helperText(__('admin.set.support_whatsapp_hint'))->disabled($readOnly),
                        $save('contact'),
                    ]),
                    Section::make(__('admin.set.clarity'))->description(__('admin.set.clarity_sub'))->columns(2)->schema([
                        TextInput::make('contact.clarity_project_id')->label(__('admin.set.clarity_id'))->regex('/^[a-z0-9]{4,20}$/i')->placeholder('abcd1234ef')
                            ->helperText(__('admin.set.clarity_id_hint'))->requiredIf('contact.clarity_enabled', true)->disabled($readOnly),
                        Toggle::make('contact.clarity_enabled')->label(__('admin.set.clarity_enabled'))->disabled($readOnly),
                        Toggle::make('contact.require_consent')->label(__('admin.set.require_consent'))->disabled($readOnly),
                        Toggle::make('contact.mask_ip')->label(__('admin.set.mask_ip'))->disabled($readOnly),
                        $save('contact'),
                    ]),
                ]),

                Tab::make(__('admin.set.tabs.business'))->icon(Heroicon::OutlinedAdjustmentsHorizontal)->schema([
                    Section::make(__('admin.set.terms'))->description(__('admin.set.terms_hint'))->columns(2)->schema([
                        Repeater::make('business.terms_ar')->label(__('admin.set.terms_ar'))->simple(TextInput::make('line')->required()->maxLength(200))->reorderable()->minItems(1)->maxItems(10)->disabled($readOnly),
                        Repeater::make('business.terms_en')->label(__('admin.set.terms_en'))->simple(TextInput::make('line')->required()->maxLength(200))->reorderable()->minItems(1)->maxItems(10)->disabled($readOnly),
                        $save('business'),
                    ]),
                    Section::make(__('admin.set.receipts'))->columns(4)->schema([
                        TextInput::make('business.receipt_price_normal')->label(__('enums.ticket_kind.normal'))->numeric()->minValue(0)->required()->disabled($readOnly),
                        TextInput::make('business.receipt_price_vip')->label(__('enums.ticket_kind.vip'))->numeric()->minValue(0)->required()->disabled($readOnly),
                        TextInput::make('business.receipt_price_parking')->label(__('enums.ticket_kind.parking'))->numeric()->minValue(0)->required()->disabled($readOnly),
                        TextInput::make('business.low_stock_threshold')->label(__('admin.set.low_stock'))->integer()->minValue(1)->maxValue(500)->required()->disabled($readOnly),
                        TextInput::make('business.receipt_title')->label(__('admin.set.receipt_title'))->maxLength(200)->columnSpan(3)->disabled($readOnly),
                        TextInput::make('business.activity_log_retention_days')->label(__('admin.set.log_retention'))->integer()->minValue(30)->maxValue(3650)->suffix(__('admin.set.days'))->required()->disabled($readOnly),
                        $save('business'),
                    ]),
                ]),

                Tab::make(__('admin.set.tabs.data'))->icon(Heroicon::OutlinedCircleStack)->visible(fn () => Actor::current()->isSuperAdmin() || Actor::current()->can('reports.export'))->schema([
                    Section::make(__('admin.set.data_danger'))->description(__('admin.set.data_danger_hint'))->icon(Heroicon::OutlinedExclamationTriangle)->iconColor('danger')->schema([
                        Actions::make([
                            Action::make('events_template')->label(__('admin.set.events_template'))->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
                                ->action(fn () => Excel::download(new SimpleTableExport(EventsImport::templateRows(), EventsImport::HEADINGS, 'Events Template'), 'events_template.xlsx')),
                            Action::make('events_import')->label(__('admin.set.events_import'))->icon(Heroicon::OutlinedArrowUpTray)->visible(Actor::current()->can('events.manage'))
                                ->schema([FileUpload::make('file')->label(__('admin.set.excel_file'))->disk('local')->directory('imports')->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])->maxSize(10240)->required()])
                                ->action(fn (array $data) => $this->importEvents($data['file'])),
                            Action::make('export_events')->label(__('admin.set.export_events'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                                ->action(fn () => Excel::download(new SimpleTableExport(Event::query()->withTrashed()->latest()->get()->map->getAttributes()->all()), 'events_backup_'.QatarClock::today().'.xlsx')),
                            Action::make('export_ticket_types')->label(__('admin.set.export_ticket_types'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                                ->action(fn () => Excel::download(new SimpleTableExport(TicketType::query()->with('event:id,title,event_date')->latest()->get()
                                    ->map(fn ($t) => collect($t->getAttributes())->except('event')->merge(['event_title' => $t->event?->title, 'event_date' => (string) $t->event?->event_date])->all())->all()), 'tickets_backup_'.QatarClock::today().'.xlsx')),
                            Action::make('export_customers')->label(__('admin.set.export_customers'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                                ->visible(Actor::current()->can('customers.view_pii'))
                                ->action(fn () => Excel::download(new SimpleTableExport(Customer::query()->latest()->get()->map(fn ($c) => $c->makeVisible('id_number')->only(['id', 'name', 'email', 'phone', 'country_code', 'nationality', 'id_number', 'created_at']))->all()), 'customers_backup_'.QatarClock::today().'.xlsx')),
                        ]),
                        Text::make(__('admin.set.bulk_delete_note')),
                    ]),
                ]),
            ]),
        ]);
    }

    // ---- Saving -------------------------------------------------------------------------

    public function saveSection(string $section): void
    {
        if (! $this->canManage()) {
            Notification::make()->danger()->title(__('errors.forbidden'))->send();

            return;
        }
        $state = $this->form->getState();
        $d = $state;
        $changed = [];
        try {
            match ($section) {
                'branding' => $this->saveBranding($d['branding'] ?? [], $state, $changed),
                'flags' => $this->apply(app(FeatureFlagSettings::class), $d['flags'] ?? [], $changed),
                'automation' => $this->saveAutomation($d['automation'] ?? [], $changed),
                'mail' => $this->saveMail($d, $changed),
                'sadad' => $this->saveSadad($d['sadad'] ?? [], $changed),
                'contact' => $this->saveContact($d['contact'] ?? [], $changed),
                'business' => $this->saveBusiness($d['business'] ?? [], $changed),
            };
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title(__('admin.set.save_failed'))->send();

            return;
        }
        PublicSettings::flush();
        if ($changed !== []) {
            Audit::log(ActivityType::SettingsChanged, __('admin.set.audit', ['section' => $section]), null, ['changed' => array_values(array_unique($changed))], Actor::current());
        }
        Notification::make()->success()->title(__('admin.common.saved'))->send();
        $this->mount();
    }

    /** Assign only known properties; returns changed keys (values never logged). */
    private function apply(object $settings, array $values, array &$changed): void
    {
        foreach ($values as $k => $v) {
            if (property_exists($settings, $k) && $settings->{$k} !== $v) {
                $settings->{$k} = $v;
                $changed[] = $k;
            }
        }
        $settings->save();
    }

    private function storedUrl(?string $path): ?string
    {
        return $path ? Storage::disk('branding')->url($path) : null;
    }

    private function saveBranding(array $b, array $state, array &$changed): void
    {
        $s = app(BrandingSettings::class);
        $replace = function (string $field, ?string $upload, bool $remove) use ($s, &$changed) {
            if ($upload) {
                $this->deleteOld($s->{$field});
                $s->{$field} = $this->storedUrl($upload);
                $changed[] = $field;
            } elseif ($remove) {
                $this->deleteOld($s->{$field});
                $s->{$field} = null;
                $changed[] = $field;
            }
        };
        $replace('logo_url', $state['logo_upload'] ?? null, false);
        if (! ($state['logo_upload'] ?? null) && ($b['logo_url'] ?? null) !== $s->logo_url) {
            $s->logo_url = $b['logo_url'] ?: null;
            $changed[] = 'logo_url';
        }
        $replace('hero_image_url', $state['hero_upload'] ?? null, (bool) ($b['remove_hero'] ?? false));
        $replace('header_bg_image_url', $state['header_bg_upload'] ?? null, (bool) ($b['remove_header_bg'] ?? false));
        $replace('before_footer_image_url', $state['before_footer_upload'] ?? null, (bool) ($b['remove_before_footer'] ?? false));
        foreach (['header_bg_color', 'hero_text', 'copyright_text', 'festival_name', 'season_label', 'og_image_url', 'site_title_ar', 'site_title_en', 'site_description_ar', 'site_description_en'] as $k) {
            if (array_key_exists($k, $b) && $s->{$k} !== $b[$k]) {
                $s->{$k} = $b[$k];
                $changed[] = $k;
            }
        }
        $s->save();
        $this->data['logo_upload'] = $this->data['hero_upload'] = $this->data['header_bg_upload'] = $this->data['before_footer_upload'] = null;
    }

    private function deleteOld(?string $url): void
    {
        $base = rtrim((string) Storage::disk('branding')->url(''), '/').'/';
        if ($url && str_starts_with($url, $base)) {
            Storage::disk('branding')->delete(substr($url, strlen($base)));
        }
    }

    private function saveAutomation(array $a, array &$changed): void
    {
        $s = app(AutomationSettings::class);
        foreach (['webhook_url_new' => 'webhook_url', 'email_webhook_url_new' => 'email_webhook_url'] as $in => $prop) {
            if (filled($a[$in] ?? null)) {
                $s->{$prop} = trim($a[$in]);
                $changed[] = $prop;
            }
            unset($a[$in]);
        }
        $a['invoice_excluded_dates'] = array_values(array_filter($a['invoice_excluded_dates'] ?? [], fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d)));
        $this->apply($s, $a, $changed);
    }

    private function saveMail(array $d, array &$changed): void
    {
        $a = app(AutomationSettings::class);
        $this->apply($a, ['payment_failed_email_enabled' => (bool) ($d['automation']['payment_failed_email_enabled'] ?? true)], $changed);
        $emails = array_values(array_unique(array_filter(array_map(fn ($e) => strtolower(trim((string) $e)), $d['contact']['admin_emails'] ?? []), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
        $this->apply(app(ContactSettings::class), ['admin_emails' => $emails], $changed);
    }

    private function saveSadad(array $d, array &$changed): void
    {
        $s = app(SadadSettings::class);
        foreach (['checkout_secret_new' => 'checkout_secret', 'api_login_secret_new' => 'api_login_secret', 'support_pin_new' => 'support_pin'] as $in => $prop) {
            if (filled($d[$in] ?? null)) {
                $s->{$prop} = trim($d[$in]);
                $changed[] = $prop;
            }
            unset($d[$in]);
        }
        $d['site_url'] = filled($d['site_url'] ?? null) ? rtrim(trim($d['site_url']), '/') : null;
        $d['website_domain'] = filled($d['website_domain'] ?? null) ? strtolower(trim($d['website_domain'])) : null;
        $this->apply($s, $d, $changed);
        $this->diagnose = null;
    }

    private function saveContact(array $c, array &$changed): void
    {
        $this->apply(app(ContactSettings::class), array_intersect_key($c, array_flip(['admin_phone', 'publish_admin_phone', 'support_whatsapp'])), $changed);
        $clarity = array_intersect_key($c, array_flip(['clarity_enabled', 'clarity_project_id', 'require_consent', 'mask_ip']));
        if (($clarity['clarity_enabled'] ?? false) && ! preg_match('/^[a-z0-9]{4,20}$/i', (string) ($clarity['clarity_project_id'] ?? ''))) {
            $clarity['clarity_enabled'] = false;
        }
        $this->apply(app(AnalyticsSettings::class), $clarity, $changed);
    }

    private function saveBusiness(array $b, array &$changed): void
    {
        $s = app(BusinessSettings::class);
        $ar = array_values(array_filter(array_map(fn ($r) => trim(is_array($r) ? (string) ($r['line'] ?? '') : (string) $r), $b['terms_ar'] ?? [])));
        $en = array_values(array_filter(array_map(fn ($r) => trim(is_array($r) ? (string) ($r['line'] ?? '') : (string) $r), $b['terms_en'] ?? [])));
        if ($ar !== $s->terms_ar || $en !== $s->terms_en) {
            $s->terms_version = now('Asia/Qatar')->format('Ymd-His');
            $changed[] = 'terms_version';
        }
        unset($b['terms_ar'], $b['terms_en']);
        $b['terms_ar'] = $ar;
        $b['terms_en'] = $en;
        $this->apply($s, $b, $changed);
    }

    // ---- Tools ---------------------------------------------------------------------------

    public function runDiagnose(): void
    {
        try {
            $this->diagnose = app(SadadDiagnose::class)->run();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title(__('admin.set.diagnose_failed'))->body($e->getMessage())->send();
        }
    }

    private function diagnoseHtml(): HtmlString
    {
        return new HtmlString($this->diagnose ? view('filament.partials.sadad-diagnose', ['r' => $this->diagnose])->render() : '');
    }

    private function testWebhook(): void
    {
        $url = app(AutomationSettings::class)->whatsappUrl();
        if (! $url) {
            Notification::make()->warning()->title(__('admin.inv.webhook_missing'))->send();

            return;
        }
        try {
            $res = \Illuminate\Support\Facades\Http::timeout(10)->acceptJson()->post($url, ['action' => 'test', 'message' => 'QCAMELMC webhook test', 'timestamp' => now()->toIso8601String()]);
            Notification::make()->{$res->successful() ? 'success' : 'warning'}()->title(__('admin.set.test_result', ['status' => $res->status()]))->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title(__('admin.set.test_failed'))->body($e->getMessage())->send();
        }
    }

    private function testMail(): void
    {
        $to = app(ContactSettings::class)->primaryAdminEmail();
        if (! $to) {
            Notification::make()->warning()->title(__('admin.set.no_admin_email'))->send();

            return;
        }
        try {
            \Illuminate\Support\Facades\Mail::raw(__('admin.set.test_mail_body'), fn ($m) => $m->to($to)->subject(__('admin.set.test_mail_subject')));
            Notification::make()->success()->title(__('admin.set.test_mail_sent', ['to' => $to]))->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title(__('admin.set.test_failed'))->body($e->getMessage())->send();
        }
    }

    private function importEvents(string $path): void
    {
        try {
            $import = new EventsImport;
            Excel::import($import, Storage::disk('local')->path($path));
            Storage::disk('local')->delete($path);
            Audit::log(ActivityType::EventUpdated, __('admin.set.audit_import', ['n' => $import->created]), null, ['created' => $import->created, 'skipped' => $import->errors], Actor::current());
            $n = Notification::make()->title(__('admin.set.import_done', ['n' => $import->created]));
            $import->errors ? $n->warning()->body(implode("\n", array_slice($import->errors, 0, 8)))->persistent() : $n->success();
            $n->send();
            PublicSettings::flush();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title(__('admin.set.import_failed'))->body($e->getMessage())->send();
        }
    }
}
