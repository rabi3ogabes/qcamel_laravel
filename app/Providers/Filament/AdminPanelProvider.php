<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureTwoFactorEnrolled;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * ADM-SHL: the admin dashboard at /admin (Filament v4). Login is the shared
 * Fortify page (/login); admins must have 2FA (EnsureTwoFactorEnrolled).
 * Access is decided by User::canAccessPanel (panel roles).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(fn () => rescue(fn () => app(\App\Settings\BrandingSettings::class)->festival_name, null, false) ?: __('common.app_name'))
            ->brandLogo(fn () => rescue(fn () => app(\App\Settings\BrandingSettings::class)->logo_url, null, false))
            ->brandLogoHeight('2.6rem')
            ->favicon(asset('favicon-32.png'))
            ->colors([
                'primary' => Color::hex('#9C1638'),
                'warning' => Color::hex('#E3950C'),
                'success' => Color::hex('#188B48'),
                'danger' => Color::hex('#C4302B'),
                'info' => Color::hex('#0D80F2'),
                'gray' => Color::Stone,
            ])
            ->font('Tajawal', provider: LocalFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode(true)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->unsavedChangesAlerts()
            ->globalSearchKeyBindings(['mod+k'])
            ->globalSearchFieldSuffix(fn () => '⌘K')
            ->navigationGroups([
                NavigationGroup::make(fn () => __('admin.nav.orders')),
                NavigationGroup::make(fn () => __('admin.nav.finance')),
                NavigationGroup::make(fn () => __('admin.nav.tickets')),
                NavigationGroup::make(fn () => __('admin.nav.customers')),
                NavigationGroup::make(fn () => __('admin.nav.events')),
                NavigationGroup::make(fn () => __('admin.nav.analytics')),
                NavigationGroup::make(fn () => __('admin.nav.team')),
                NavigationGroup::make(fn () => __('admin.nav.system'))->collapsed(),
                NavigationGroup::make(fn () => __('admin.nav.quick')),
            ])
            // F-ADM-SHL-012: quick links (same-tab / new-tab behaviour kept), permission-filtered.
            ->navigationItems([
                NavigationItem::make('quick_pos')->label(fn () => __('staff.link_pos'))->icon(Heroicon::OutlinedBanknotes)->group(fn () => __('admin.nav.quick'))
                    ->url(fn () => route('staff.pos'), shouldOpenInNewTab: true)->visible(fn () => \App\Domain\Shared\Actor::current()->can('pos.sell'))->sort(1),
                NavigationItem::make('quick_scan')->label(fn () => __('staff.link_scanner'))->icon(Heroicon::OutlinedQrCode)->group(fn () => __('admin.nav.quick'))
                    ->url(fn () => route('staff.scanner'))->visible(fn () => \App\Domain\Shared\Actor::current()->can('scanner.admit'))->sort(2),
                NavigationItem::make('quick_receipts')->label(fn () => __('staff.link_receipts'))->icon(Heroicon::OutlinedDocumentText)->group(fn () => __('admin.nav.quick'))
                    ->url(fn () => route('staff.receipts'))->visible(fn () => \App\Domain\Shared\Actor::current()->can('receipts.capture'))->sort(3),
                NavigationItem::make('quick_live')->label(fn () => __('staff.link_live'))->icon(Heroicon::OutlinedSignal)->group(fn () => __('admin.nav.quick'))
                    ->url(fn () => route('staff.live'), shouldOpenInNewTab: true)->visible(fn () => \App\Domain\Shared\Actor::current()->can('live.view'))->sort(4),
                NavigationItem::make('quick_site')->label(fn () => __('admin.nav.website'))->icon(Heroicon::OutlinedGlobeAlt)->group(fn () => __('admin.nav.quick'))
                    ->url(fn () => url('/'), shouldOpenInNewTab: true)->sort(9),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->renderHook(PanelsRenderHook::CONTENT_START, fn () => Blade::render('@includeIf(\'filament.hooks.capacity-alert\')'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => Blade::render('@includeIf(\'filament.hooks.staff-apps\')'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                \App\Http\Middleware\SetLocale::class,
                \App\Http\Middleware\SecurityHeaders::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureTwoFactorEnrolled::class,
            ]);
    }
}
