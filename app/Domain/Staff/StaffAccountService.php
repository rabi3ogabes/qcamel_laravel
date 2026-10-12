<?php

namespace App\Domain\Staff;

use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\ActivityType;
use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Staff accounts (F-AUTH-026…031, R-150, D-066, T-127): create / set role /
 * reset password / revoke, atomic, validated, audited; cannot demote or
 * revoke yourself or the last admin; admin-level roles granted only by
 * super_admin.
 */
class StaffAccountService
{
    private function assertManager(Actor $actor): void
    {
        if (! $actor->can('staff.manage')) {
            throw DomainError::forbidden();
        }
    }

    private function assertRoleGrantable(Role $role, Actor $actor): void
    {
        if (in_array($role, [Role::SuperAdmin, Role::Admin], true) && ! $actor->isSuperAdmin()) {
            throw new DomainError('forbidden', 403, __('staff.only_owner_grants_admin'));
        }
    }

    public static function adminCount(?string $excludeUserId = null): int
    {
        return User::query()
            ->where('is_active', true)
            ->when($excludeUserId, fn ($q) => $q->whereKeyNot($excludeUserId))
            ->whereHas('roles', fn ($q) => $q->whereIn('name', Role::adminRoles()))
            ->count();
    }

    public function create(string $name, string $email, string $password, Role $role, Actor $actor, bool $sendInvite = false): User
    {
        $this->assertManager($actor);
        $this->assertRoleGrantable($role, $actor);
        $email = mb_strtolower(trim($email));
        if ($email === '' || (! $sendInvite && mb_strlen($password) < 8)) {
            throw DomainError::validation([__('staff.email_password_required')]);
        }
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw DomainError::validation([__('staff.email_taken')]);
        }

        $user = DB::transaction(function () use ($name, $email, $password, $role, $sendInvite) {
            $user = User::create([
                'name' => trim($name) ?: strstr($email, '@', true),
                'email' => $email,
                'password' => $sendInvite ? Hash::make(bin2hex(random_bytes(24))) : $password,
                'is_active' => true,
                'must_reset_password' => $sendInvite,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$role->value]);

            return $user;
        });
        if ($sendInvite) {
            Password::sendResetLink(['email' => $email]);
        }
        Audit::log(ActivityType::StaffRoleChanged, __('staff.audit_created', ['email' => $email, 'role' => $role->label()]), $user, ['role' => $role->value], $actor);

        return $user;
    }

    public function setRole(User $user, Role $role, Actor $actor): User
    {
        $this->assertManager($actor);
        $this->assertRoleGrantable($role, $actor);
        if ($user->id === $actor->userId() && $role->rank() < ($user->topRole()?->rank() ?? 0)) {
            throw new DomainError('forbidden', 403, __('staff.cannot_demote_self'));
        }
        if ($user->isAdmin() && ! in_array($role->value, Role::adminRoles(), true) && self::adminCount($user->id) === 0) {
            throw new DomainError('forbidden', 403, __('staff.last_admin'));
        }
        if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw DomainError::forbidden();
        }
        $old = $user->getRoleNames()->implode(',');
        DB::transaction(function () use ($user, $role) {
            $user->syncRoles([$role->value]);
            if (! $user->is_active) {
                $user->forceFill(['is_active' => true])->save(); // re-granting a role restores a revoked account
            }
        });
        Audit::log(ActivityType::StaffRoleChanged, __('staff.audit_role', ['email' => $user->email, 'role' => $role->label()]), $user, ['from' => $old, 'to' => $role->value], $actor);

        return $user;
    }

    public function resetPassword(User $user, string $password, Actor $actor): void
    {
        $this->assertManager($actor);
        if (mb_strlen($password) < 8) {
            throw DomainError::validation([__('staff.password_required')]);
        }
        if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw DomainError::forbidden();
        }
        $user->forceFill(['password' => $password, 'remember_token' => null, 'must_reset_password' => false])->save();
        $this->killSessions($user, exceptCurrent: $user->id === $actor->userId());
        Audit::log(ActivityType::StaffRoleChanged, __('staff.audit_password', ['email' => $user->email]), $user, [], $actor);
    }

    /** F-AUTH-030 rebuild: deactivate, remove roles, end sessions now. */
    public function revoke(User $user, Actor $actor): void
    {
        $this->assertManager($actor);
        if ($user->id === $actor->userId()) {
            throw new DomainError('forbidden', 403, __('staff.cannot_revoke_self'));
        }
        if ($user->isAdmin() && self::adminCount($user->id) === 0) {
            throw new DomainError('forbidden', 403, __('staff.last_admin'));
        }
        if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw DomainError::forbidden();
        }
        DB::transaction(function () use ($user) {
            $user->syncRoles([]);
            $user->forceFill(['is_active' => false, 'remember_token' => null])->save();
            $user->tokens()->delete();
        });
        $this->killSessions($user);
        Audit::log(ActivityType::StaffRoleChanged, __('staff.audit_revoked', ['email' => $user->email]), $user, [], $actor);
    }

    /** U-AUTH-05 sign out all devices. */
    public function killSessions(User $user, bool $exceptCurrent = false): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::table('sessions')->where('user_id', $user->id)
            ->when($exceptCurrent && session()->getId(), fn ($q) => $q->where('id', '!=', session()->getId()))
            ->delete();
    }
}
