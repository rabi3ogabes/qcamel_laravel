<?php

namespace App\Models;

use App\Enums\Role;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Part 4.1 users — replaces Supabase auth.users + admin_users + user_roles.
 */
class User extends Authenticatable implements FilamentUser, HasName
{
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fillable = [
        'name', 'email', 'password', 'is_active', 'must_reset_password', 'pos_user_id', 'preferences',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'notifications_seen_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_reset_password' => 'boolean',
            'preferences' => 'array',
        ];
    }

    protected function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value === null ? null : mb_strtolower(trim($value));
    }

    public function posUser(): BelongsTo
    {
        return $this->belongsTo(PosUser::class);
    }

    public function confirmedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'confirmed_by');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(TicketCheckin::class, 'actor_id');
    }

    // ---- Role helpers (R-137 precedence) -------------------------------------

    public function topRole(): ?Role
    {
        $best = null;
        foreach ($this->getRoleNames() as $name) {
            $r = Role::tryFrom($name);
            if ($r && (! $best || $r->rank() > $best->rank())) {
                $best = $r;
            }
        }

        return $best;
    }

    /** "admin" | "supervisor" | … | null — used by the staff hub badge. */
    public function staffKind(): ?string
    {
        return $this->topRole()?->value;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SuperAdmin->value);
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(Role::adminRoles());
    }

    public function isStaff(): bool
    {
        return $this->is_active && $this->hasAnyRole(Role::staffRoles());
    }

    public function requiresTwoFactor(): bool
    {
        return (bool) config('qcamelmc.auth.admin_2fa_required') && $this->isAdmin();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    // ---- Filament -------------------------------------------------------------

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasAnyRole(Role::panelRoles());
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function pref(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences ?? [], $key, $default);
    }

    public function setPref(string $key, mixed $value): void
    {
        $prefs = $this->preferences ?? [];
        data_set($prefs, $key, $value);
        $this->forceFill(['preferences' => $prefs])->saveQuietly();
    }
}
