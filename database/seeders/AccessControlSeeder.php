<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'readings' => ['view', 'fill', 'complete', 'reopen', 'edit_history', 'configure'],
            'actions' => ['view', 'create', 'start', 'complete', 'edit', 'cancel'],
            'occurrences' => ['view', 'create', 'edit', 'close', 'convert_to_action'],
            'aspersions' => ['view', 'start', 'finish', 'view_history', 'manage_points'],
            'equipment' => ['view', 'create', 'edit', 'deactivate'],
            'users' => ['view', 'manage_access', 'block', 'reset_password', 'change_role'],
            'employees' => ['view', 'view_sensitive', 'create', 'edit', 'deactivate'],
            'shifts' => ['view', 'manage_team', 'change_schedule', 'replace_operator'],
            'roles' => ['manage'],
            'administration' => ['manage_permissions'],
            'dosing' => ['view', 'operate', 'configure'],
            'chemical_stock' => ['count', 'receive', 'issue', 'transfer', 'inventory', 'view_balance', 'view_analytics', 'configure', 'correct'],
            'laboratory' => ['view', 'create', 'correct', 'configure'],
            'indicators' => ['view', 'configure'],
            'public_shares' => ['view', 'manage'],
            'costs' => ['view', 'manage'],
        ];

        $permissions = collect();
        foreach ($definitions as $module => $actions) {
            foreach ($actions as $action) {
                $permissions->push(Permission::query()->updateOrCreate(
                    ['code' => "{$module}.{$action}"],
                    ['module' => $module, 'action' => $action, 'name' => ucfirst($module).' · '.str_replace('_', ' ', $action), 'is_active' => true],
                ));
            }
        }

        $roles = collect([
            'master' => 'Master',
            'operator' => 'Operador',
            'viewer' => 'Visualizador',
            'chemist' => 'Químico',
        ])->map(fn (string $name, string $code) => Role::query()->updateOrCreate(
            ['code' => $code],
            ['name' => $name, 'is_active' => true],
        ));

        $operatorCodes = [
            'readings.view', 'readings.fill', 'readings.complete', 'actions.view', 'actions.start',
            'actions.complete', 'occurrences.view', 'occurrences.create', 'aspersions.view',
            'aspersions.start', 'aspersions.finish', 'equipment.view', 'shifts.view',
            'dosing.view', 'dosing.operate',
            'chemical_stock.count', 'chemical_stock.receive', 'chemical_stock.issue',
        ];
        $chemistCodes = ['laboratory.view', 'laboratory.create', 'laboratory.correct', 'indicators.view'];
        $viewerCodes = ['readings.view', 'actions.view', 'occurrences.view', 'aspersions.view', 'equipment.view', 'shifts.view', 'dosing.view'];

        $roles['master']->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
        $roles['operator']->permissions()->syncWithoutDetaching($permissions->whereIn('code', $operatorCodes)->pluck('id')->all());
        $roles['chemist']->permissions()->syncWithoutDetaching($permissions->whereIn('code', $chemistCodes)->pluck('id')->all());
        $roles['viewer']->permissions()->syncWithoutDetaching($permissions->whereIn('code', $viewerCodes)->pluck('id')->all());
    }
}
