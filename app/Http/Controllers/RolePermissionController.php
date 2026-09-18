<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\UserAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('administration.manage_permissions');
        $roles = Role::with('permissions')->where('is_active', true)->orderBy('name')->get();
        $selectedRole = $roles->firstWhere('id', $request->integer('role')) ?? $roles->first();

        return view('admin.roles.index', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'permissions' => Permission::where('is_active', true)->orderBy('module')->orderBy('name')->get()->groupBy('module'),
        ]);
    }

    public function update(Request $request, Role $role, UserAdministrationService $service): RedirectResponse
    {
        Gate::authorize('administration.manage_permissions');
        $data = $request->validate(['permission_ids' => ['nullable', 'array'], 'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id']]);
        $service->updateRolePermissions($role, $data['permission_ids'] ?? [], $request->user());

        return redirect()->route('admin.roles.index', ['role' => $role->id])->with('success', 'Permissões do perfil atualizadas.');
    }
}
