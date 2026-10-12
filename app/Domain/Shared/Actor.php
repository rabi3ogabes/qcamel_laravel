<?php

namespace App\Domain\Shared;

use App\Enums\ActorType;
use App\Enums\Role;
use App\Models\KioskDevice;
use App\Models\User;

/**
 * Who performs a domain action: an account user, a passcode kiosk device,
 * the system (jobs/scheduler) or an anonymous guest. Request-supplied ids
 * are never trusted (R-131); this object is built from the server session.
 */
final class Actor
{
    /** Permissions a passcode kiosk session holds (target matrix, AUTH). */
    public const KIOSK_PERMISSIONS = ['staff.hub', 'pos.sell', 'scanner.admit', 'gate.view', 'live.view', 'attendance.view', 'checkin.manual'];

    private function __construct(
        public readonly ?User $user,
        public readonly ?KioskDevice $kiosk,
        public readonly bool $system = false,
        public readonly ?string $label = null,
    ) {}

    public static function user(User $user, ?string $label = null): self
    {
        return new self($user, null, false, $label);
    }

    public static function kiosk(KioskDevice $device, ?string $label = null): self
    {
        return new self(null, $device, false, $label);
    }

    public static function system(): self
    {
        return new self(null, null, true);
    }

    public static function guest(): self
    {
        return new self(null, null, false);
    }

    /** Resolve from the current request (session user and/or kiosk cookie). */
    public static function current(): self
    {
        $user = auth()->user();
        if ($user instanceof User && $user->is_active) {
            return self::user($user);
        }
        $kiosk = request()->attributes->get('kiosk');
        if ($kiosk instanceof KioskDevice) {
            return self::kiosk($kiosk);
        }

        return self::guest();
    }

    public function withLabel(?string $label): self
    {
        return new self($this->user, $this->kiosk, $this->system, $label);
    }

    public function isGuest(): bool
    {
        return ! $this->system && $this->user === null && $this->kiosk === null;
    }

    public function isKiosk(): bool
    {
        return $this->kiosk !== null && $this->user === null;
    }

    public function isAccount(): bool
    {
        return $this->user !== null;
    }

    public function isAdmin(): bool
    {
        return $this->system || ($this->user?->isAdmin() ?? false);
    }

    public function isSuperAdmin(): bool
    {
        return $this->system || ($this->user?->isSuperAdmin() ?? false);
    }

    /** Staff = staff-role account OR kiosk (T-087). */
    public function isStaff(): bool
    {
        if ($this->system) {
            return true;
        }
        if ($this->user) {
            return $this->user->isStaff();
        }

        return $this->kiosk !== null;
    }

    public function can(string $permission): bool
    {
        if ($this->system) {
            return true;
        }
        if ($this->user) {
            return $this->user->is_active && ($this->user->isSuperAdmin() || $this->user->hasPermissionTo($permission));
        }
        if ($this->kiosk) {
            return in_array($permission, self::KIOSK_PERMISSIONS, true);
        }

        return false;
    }

    /** User id for confirmed_by / checked_in_by (null for kiosk, R-037). */
    public function userId(): ?string
    {
        return $this->user?->id;
    }

    public function deviceId(): ?string
    {
        return $this->kiosk?->id;
    }

    public function actorType(): ActorType
    {
        return match (true) {
            $this->system => ActorType::System,
            $this->user !== null && $this->user->isAdmin() => ActorType::Admin,
            $this->user !== null || $this->kiosk !== null => ActorType::Staff,
            default => ActorType::Guest,
        };
    }

    public function identifier(): ?string
    {
        return $this->user?->email ?? ($this->kiosk ? 'kiosk:'.$this->kiosk->name : null);
    }

    public function displayName(): ?string
    {
        return $this->label ?? $this->user?->name ?? $this->kiosk?->label();
    }

    /** National IDs visible only to admins / PII permission (R-203). */
    public function canSeePii(): bool
    {
        return $this->system || ($this->user !== null && ($this->user->isAdmin() || $this->user->hasPermissionTo('customers.view_pii')));
    }

    public function hasRole(Role $role): bool
    {
        return $this->user?->hasRole($role->value) ?? false;
    }
}
