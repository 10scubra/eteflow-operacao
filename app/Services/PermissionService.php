<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;

class PermissionService
{
    public const ADMINISTRATION_PERMISSIONS = [
        'users.manage_access',
        'roles.manage',
        'administration.manage_permissions',
    ];

    private const LEGACY_ROLE_PERMISSIONS = [
        'master' => ['*'],
        'operator' => [
            'readings.view', 'readings.fill', 'readings.complete',
            'actions.view', 'actions.start', 'actions.complete',
            'occurrences.view', 'occurrences.create',
            'aspersions.view', 'aspersions.start', 'aspersions.finish',
            'equipment.view', 'shifts.view',
            'chemical_stock.count', 'chemical_stock.receive',
        ],
        'viewer' => [
            'readings.view', 'actions.view', 'occurrences.view', 'aspersions.view',
            'equipment.view', 'shifts.view',
        ],
    ];

    public function allows(User $user, string $permissionCode, ?CarbonInterface $moment = null): bool
    {
        if (! $user->is_active || $user->blocked_at !== null) {
            return false;
        }

        $now = $moment ?? now();
        $overrides = $user->permissionOverrides()
            ->effectiveAt($now)
            ->whereHas('permission', fn ($query) => $query->where('code', $permissionCode)->where('is_active', true))
            ->latest('id')
            ->get();

        if ($overrides->contains('effect', 'deny')) {
            return false;
        }

        if ($overrides->contains('effect', 'allow')) {
            return true;
        }

        if ($user->role_id && $user->roleProfile?->is_active) {
            if ($user->roleProfile->permissions()->where('code', $permissionCode)->where('is_active', true)->exists()) {
                return true;
            }
        }

        if ($user->role_id !== null) {
            return false;
        }

        $legacyPermissions = self::LEGACY_ROLE_PERMISSIONS[$user->role] ?? [];

        return in_array('*', $legacyPermissions, true) || in_array($permissionCode, $legacyPermissions, true);
    }

    public function isAdministrator(User $user): bool
    {
        foreach (self::ADMINISTRATION_PERMISSIONS as $permission) {
            if (! $this->allows($user, $permission)) {
                return false;
            }
        }

        return true;
    }
}
