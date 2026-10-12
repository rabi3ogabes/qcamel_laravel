<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Roles/permissions always; the owner account from SEED_ADMIN_* env;
     * demo data only outside production.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $email = env('SEED_ADMIN_EMAIL');
        $password = env('SEED_ADMIN_PASSWORD');
        if ($email && $password) {
            $owner = User::query()->firstOrCreate(['email' => strtolower($email)], [
                'name' => env('SEED_ADMIN_NAME', 'Owner'),
                'password' => $password,
                'is_active' => true,
                'must_reset_password' => app()->isProduction(),
            ]);
            $owner->syncRoles([Role::SuperAdmin->value]);
        }

        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
