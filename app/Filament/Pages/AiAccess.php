<?php

namespace App\Filament\Pages;

use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Filament\Concerns\InNavigationGroup;
use App\Support\Audit;
use App\Support\Formats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Locked;

/**
 * F-API-001 — connect an AI assistant to the read-only MCP server with a
 * personal token (`mcp:read`) that carries this account's permissions.
 * The plain token is shown exactly once; only its hash is stored.
 */
class AiAccess extends Page
{
    use InNavigationGroup;

    protected string $view = 'filament.pages.ai-access';

    protected static string $navGroup = 'system';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'ai-access';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    /** Shown once right after creation, then cleared (locked: never accepted from the client). */
    #[Locked]
    public ?string $plainToken = null;

    public static function getNavigationLabel(): string
    {
        return __('admin.ai.title');
    }

    public function getTitle(): string
    {
        return __('admin.ai.title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.ai.sub');
    }

    public static function canAccess(): bool
    {
        return Actor::current()->can('api.mcp') && (bool) config('qcamelmc.mcp.enabled', true);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createToken')->label(__('admin.ai.create'))->icon(Heroicon::OutlinedKey)
                ->modalHeading(__('admin.ai.create'))->modalDescription(__('admin.ai.create_desc'))->modalWidth('md')
                ->fillForm(['days' => (string) config('qcamelmc.mcp.token_days', 90)])
                ->schema([
                    TextInput::make('name')->label(__('admin.ai.token_name'))->placeholder(__('admin.ai.token_name_ph'))->required()->maxLength(60),
                    Select::make('days')->label(__('admin.ai.expires_in'))->required()->native(false)
                        ->options(['7' => __('admin.ai.days', ['n' => 7]), '30' => __('admin.ai.days', ['n' => 30]), '90' => __('admin.ai.days', ['n' => 90]), '365' => __('admin.ai.days', ['n' => 365])]),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    $token = $user->createToken('mcp:'.trim($data['name']), ['mcp:read'], now()->addDays((int) $data['days']));
                    $this->plainToken = $token->plainTextToken;
                    Audit::log(ActivityType::Auth, __('admin.ai.audit_created', ['name' => trim($data['name'])]), null, ['token_id' => $token->accessToken->id, 'days' => (int) $data['days']]);
                    Notification::make()->success()->title(__('admin.ai.created'))->send();
                }),
        ];
    }

    public function revoke(int $id): void
    {
        $token = auth()->user()->tokens()->whereKey($id)->first();
        if ($token) {
            $name = $token->name;
            $token->delete();
            Audit::log(ActivityType::Auth, __('admin.ai.audit_revoked', ['name' => substr($name, 4)]), null, ['token_id' => $id]);
            Notification::make()->success()->title(__('admin.ai.revoked'))->send();
        }
    }

    public function dismissToken(): void
    {
        $this->plainToken = null;
    }

    protected function getViewData(): array
    {
        return [
            'tokens' => auth()->user()->tokens()->where('name', 'like', 'mcp:%')->latest()->get()
                ->map(fn (PersonalAccessToken $t) => [
                    'id' => $t->id,
                    'name' => substr($t->name, 4),
                    'created' => Formats::dateTime($t->created_at),
                    'used' => $t->last_used_at ? Formats::ago($t->last_used_at) : null,
                    'expires' => $t->expires_at ? Formats::longDate($t->expires_at, false) : null,
                    'expired' => $t->expires_at?->isPast() ?? false,
                ]),
            'endpoint' => url('/mcp'),
        ];
    }
}
