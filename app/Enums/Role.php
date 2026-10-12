<?php

namespace App\Enums;

/** Spatie roles (3.12, R-137). */
enum Role: string
{
    use Concerns;

    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Cashier = 'cashier';
    case Gatekeeper = 'gatekeeper';
    case Accountant = 'accountant';
    case Viewer = 'viewer';

    public static function langKey(): string
    {
        return 'role';
    }

    /** R-137 precedence. */
    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin => 100,
            self::Admin => 90,
            self::Supervisor => 70,
            self::Cashier, self::Gatekeeper => 50,
            self::Accountant => 40,
            self::Viewer => 30,
        };
    }

    /** Roles that may open the Filament admin panel (F-ADM-ORD-001). */
    public static function panelRoles(): array
    {
        return [self::SuperAdmin->value, self::Admin->value, self::Accountant->value, self::Viewer->value];
    }

    /** Account roles that may use the staff apps. */
    public static function staffRoles(): array
    {
        return [self::SuperAdmin->value, self::Admin->value, self::Supervisor->value, self::Cashier->value, self::Gatekeeper->value];
    }

    public static function adminRoles(): array
    {
        return [self::SuperAdmin->value, self::Admin->value];
    }
}
