<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserAdministrationService
{
    public function __construct(private PermissionService $permissions, private AuditService $audit) {}

    public function createAccess(Employee $employee, array $attributes, Role $role, string $temporaryPassword, User $actor): User
    {
        return DB::transaction(function () use ($employee, $attributes, $role, $temporaryPassword, $actor) {
            $lockedEmployee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            if ($lockedEmployee->user()->exists()) {
                throw ValidationException::withMessages(['username' => 'Este colaborador já possui acesso.']);
            }
            $user = User::query()->create([
                'employee_id' => $lockedEmployee->id,
                'role_id' => $role->id,
                'name' => $lockedEmployee->display_name,
                'username' => $attributes['username'],
                'email' => $attributes['email'],
                'password' => Hash::make($temporaryPassword),
                'role' => $role->code,
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $this->audit->record($user, 'user.access_created', $actor, [], [
                'employee_id' => $lockedEmployee->id, 'username' => $user->username,
                'email' => $user->email, 'role_id' => $role->id, 'must_change_password' => true,
            ]);

            return $user;
        });
    }

    public function block(User $user, User $actor): User
    {
        return $this->mutateWithAdministratorProtection($user, $actor, 'user.blocked', fn (User $locked) => $locked->update(['blocked_at' => now()]));
    }

    public function unblock(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $old = ['blocked_at' => $locked->blocked_at?->toISOString()];
            $locked->update(['blocked_at' => null]);
            $this->audit->record($locked, 'user.unblocked', $actor, $old, ['blocked_at' => null]);

            return $locked->fresh();
        });
    }

    public function deactivate(User $user, User $actor): User
    {
        return $this->mutateWithAdministratorProtection($user, $actor, 'user.deactivated', fn (User $locked) => $locked->update(['is_active' => false]));
    }

    public function activate(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $locked->update(['is_active' => true]);
            $this->audit->record($locked, 'user.activated', $actor, ['is_active' => false], ['is_active' => true]);

            return $locked->fresh();
        });
    }

    public function changeRole(User $user, ?Role $role, string $legacyRole, User $actor): User
    {
        return $this->mutateWithAdministratorProtection($user, $actor, 'user.role_changed', fn (User $locked) => $locked->update(['role_id' => $role?->id, 'role' => $legacyRole]));
    }

    public function setPermissionOverride(User $user, Permission $permission, string $effect, User $actor, array $vigency = []): ?UserPermissionOverride
    {
        if (! in_array($effect, ['allow', 'deny', 'inherit'], true)) {
            throw ValidationException::withMessages(['effect' => 'O efeito deve ser allow, deny ou inherit.']);
        }

        return DB::transaction(function () use ($user, $permission, $effect, $actor, $vigency) {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $existing = UserPermissionOverride::query()->where('user_id', $user->id)->where('permission_id', $permission->id)
                ->whereNull('effective_until')->lockForUpdate()->get();
            foreach ($existing as $override) {
                $override->update(['effective_until' => now()]);
            }
            $override = $effect === 'inherit' ? null : UserPermissionOverride::query()->create([
                'user_id' => $user->id, 'permission_id' => $permission->id, 'effect' => $effect,
                'effective_from' => $vigency['effective_from'] ?? now(), 'effective_until' => $vigency['effective_until'] ?? null,
                'created_by' => $actor->id,
            ]);
            $this->assertAdministratorRemains();
            $auditable = $override ?? $user;
            $this->audit->record($auditable, 'user.permission_overridden', $actor,
                ['previous_effects' => $existing->pluck('effect')->all()],
                ['user_id' => $user->id, 'permission_id' => $permission->id, 'effect' => $effect]);

            return $override;
        });
    }

    public function updateRolePermissions(Role $role, array $permissionIds, User $actor): Role
    {
        return DB::transaction(function () use ($role, $permissionIds, $actor) {
            $locked = Role::query()->lockForUpdate()->findOrFail($role->id);
            $old = $locked->permissions()->pluck('permissions.id')->all();
            $locked->permissions()->sync($permissionIds);
            $this->assertAdministratorRemains();
            $this->audit->record($locked, 'role.permissions_updated', $actor, ['permission_ids' => $old], ['permission_ids' => $permissionIds]);

            return $locked->fresh('permissions');
        });
    }

    public function setTemporaryPassword(User $user, string $temporaryPassword, User $actor): User
    {
        return DB::transaction(function () use ($user, $temporaryPassword, $actor) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $locked->update(['password' => Hash::make($temporaryPassword), 'must_change_password' => true]);
            $this->audit->record($locked, 'user.temporary_password_set', $actor, [], ['must_change_password' => true]);

            return $locked->fresh();
        });
    }

    private function mutateWithAdministratorProtection(User $user, User $actor, string $action, callable $mutation): User
    {
        return DB::transaction(function () use ($user, $actor, $action, $mutation) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $old = ['is_active' => $locked->is_active, 'blocked_at' => $locked->blocked_at?->toISOString(), 'role_id' => $locked->role_id, 'role' => $locked->role];
            $mutation($locked);
            $this->assertAdministratorRemains();
            $fresh = $locked->fresh();
            $new = ['is_active' => $fresh->is_active, 'blocked_at' => $fresh->blocked_at?->toISOString(), 'role_id' => $fresh->role_id, 'role' => $fresh->role];
            $this->audit->record($fresh, $action, $actor, $old, $new);

            return $fresh;
        });
    }

    private function assertAdministratorRemains(): void
    {
        $exists = User::query()->where('is_active', true)->whereNull('blocked_at')->get()
            ->contains(fn (User $candidate): bool => $this->permissions->isAdministrator($candidate));
        if (! $exists) {
            throw ValidationException::withMessages(['administrator' => 'A operação deixaria o sistema sem um administrador ativo.']);
        }
    }
}
