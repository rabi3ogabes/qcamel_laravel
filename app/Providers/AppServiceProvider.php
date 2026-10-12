<?php

namespace App\Providers;

use App\Domain\Messaging\Drivers\N8nWebhookDriver;
use App\Domain\Messaging\Drivers\WhatsAppChannel;
use App\Domain\Messaging\Drivers\WhatsAppCloudDriver;
use App\Domain\Payments\Sadad\SadadConfig;
use App\Listeners\DomainEventSubscriber;
use App\Models\User;
use App\View\Composers\BrandComposer;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SadadConfig::class, fn () => new SadadConfig);
        $this->app->bind(WhatsAppChannel::class, fn () => config('qcamelmc.notifications.whatsapp_driver') === 'cloud_api'
            ? new WhatsAppCloudDriver
            : new N8nWebhookDriver);
    }

    public function boot(): void
    {
        // Part 6.6 rule 7: no N+1 outside production.
        Model::preventLazyLoading(! $this->app->isProduction() && ! $this->app->runningUnitTests());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // super_admin passes every ability (spatie Gate::before pattern).
        Gate::before(fn (User $user) => $user->is_active && $user->hasRole('super_admin') ? true : null);

        // Spec 2.2: OpenAPI docs at /docs/api for admins only (session); bearer scheme documented.
        Gate::define('viewApiDocs', fn (?User $user) => $user !== null && $user->is_active && $user->can('settings.view'));
        Scramble::configure()->withDocumentTransformers(
            fn (OpenApi $openApi) => $openApi->secure(SecurityScheme::http('bearer'))
        );

        Event::subscribe(DomainEventSubscriber::class);

        // FOR UPDATE SKIP LOCKED where supported (MySQL 8 / PostgreSQL), plain lock elsewhere.
        QueryBuilder::macro('skipLockedIfSupported', function () {
            /** @var QueryBuilder $this */
            return in_array($this->getConnection()->getDriverName(), ['mysql', 'pgsql'], true)
                ? $this->lock('for update skip locked')
                : $this->lockForUpdate();
        });

        View::composer(['layouts.*', 'components.*', 'public.*', 'staff.*', 'errors::*', 'errors.*', 'auth.*', 'invoice.*', 'livewire.*'], BrandComposer::class);

        $this->rateLimits();
    }

    /** Part 3.8 / R-061 / F-ORD-001 / F-API-012 named limiters. */
    private function rateLimits(): void
    {
        $ip = fn (Request $r) => $r->ip() ?? 'unknown';

        RateLimiter::for('orders', fn (Request $r) => [Limit::perMinute(10)->by('o:'.$ip($r)), Limit::perHour(60)->by('oh:'.$ip($r))]);
        RateLimiter::for('availability', fn (Request $r) => Limit::perMinute(60)->by($ip($r)));
        RateLimiter::for('person-count', fn (Request $r) => Limit::perMinute(20)->by($ip($r)));
        RateLimiter::for('status', fn (Request $r) => Limit::perMinute(60)->by($ip($r)));
        RateLimiter::for('verify', fn (Request $r) => Limit::perMinute(30)->by($ip($r)));
        RateLimiter::for('pay-again', fn (Request $r) => Limit::perMinute(10)->by($ip($r).'|'.(string) $r->input('booking_reference')));
        RateLimiter::for('invoice', fn (Request $r) => Limit::perMinute(30)->by($ip($r)));
        RateLimiter::for('booking-lookup', fn (Request $r) => Limit::perMinute(10)->by($ip($r)));
        RateLimiter::for('payment-errors', fn (Request $r) => Limit::perMinute(10)->by($ip($r)));
        RateLimiter::for('presence', fn (Request $r) => Limit::perMinute(4)->by(session()->getId() ?: $ip($r)));
        RateLimiter::for('sadad', fn (Request $r) => Limit::perMinute(120)->by($ip($r)));
        RateLimiter::for('n8n', fn (Request $r) => Limit::perMinute(120)->by($ip($r)));
        RateLimiter::for('mail-webhook', fn (Request $r) => Limit::perMinute(300)->by($ip($r)));
        RateLimiter::for('staff-lookup', fn (Request $r) => Limit::perMinute(30)->by(($r->user()?->id ?? $ip($r))));
        RateLimiter::for('receipts', fn (Request $r) => Limit::perMinute(20)->by(($r->user()?->id ?? $ip($r))));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $ip($r)));
        RateLimiter::for('sadad-sync', fn () => Limit::perMinute(30));
        RateLimiter::for('mcp', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $ip($r)));
    }
}
