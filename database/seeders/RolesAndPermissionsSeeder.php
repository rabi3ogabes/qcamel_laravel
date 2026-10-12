<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Target roles matrix (AUTH, Part 2.4). super_admin passes every Gate via
 * Gate::before; the kiosk passcode session has a fixed permission list
 * (App\Domain\Shared\Actor::KIOSK_PERMISSIONS).
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'orders.view', 'orders.cancel', 'orders.confirm', 'orders.delete', 'orders.change_event', 'orders.verify', 'orders.export',
        'transactions.view', 'payment_errors.view', 'payment_errors.manage', 'invoices.manage',
        'stock.recount', 'tickets.view', 'tickets.edit', 'tickets.bin', 'tickets.restore',
        'customers.view', 'customers.view_pii', 'customers.edit', 'customers.delete',
        'events.view', 'events.manage', 'events.delete',
        'settings.view', 'settings.manage',
        'staff.manage', 'pos_users.manage',
        'staff.hub', 'pos.sell', 'scanner.admit', 'checkin.manual', 'checkin.reset', 'gate.view', 'live.view',
        'attendance.view', 'attendance.edit', 'receipts.capture', 'receipts.manage',
        'visitors.view', 'reports.export', 'logs.view', 'logs.purge', 'notifications.view', 'api.mcp',
    ];

    public const MATRIX = [
        'admin' => '*except:logs.purge',
        'supervisor' => ['staff.hub', 'pos.sell', 'scanner.admit', 'checkin.manual', 'gate.view', 'live.view', 'attendance.view', 'attendance.edit', 'receipts.capture'],
        'cashier' => ['staff.hub', 'pos.sell', 'receipts.capture'],
        'gatekeeper' => ['staff.hub', 'scanner.admit', 'checkin.manual', 'gate.view'],
        'accountant' => ['orders.view', 'orders.export', 'transactions.view', 'payment_errors.view', 'tickets.view', 'customers.view', 'customers.view_pii',
            'events.view', 'reports.export', 'logs.view', 'visitors.view', 'notifications.view', 'receipts.capture', 'receipts.manage', 'api.mcp'],
        'viewer' => ['orders.view', 'transactions.view', 'payment_errors.view', 'tickets.view', 'customers.view', 'events.view', 'visitors.view', 'notifications.view', 'attendance.view'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $p) {
            Permission::findOrCreate($p, 'web');
        }
        foreach (RoleEnum::cases() as $r) {
            Role::findOrCreate($r->value, 'web');
        }
        Role::findByName('super_admin', 'web')->syncPermissions(self::PERMISSIONS);
        foreach (self::MATRIX as $role => $perms) {
            if ($perms === '*except:logs.purge') {
                $perms = array_values(array_diff(self::PERMISSIONS, ['logs.purge']));
            }
            Role::findByName($role, 'web')->syncPermissions($perms);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
