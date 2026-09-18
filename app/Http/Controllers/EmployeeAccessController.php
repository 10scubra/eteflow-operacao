<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Services\UserAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class EmployeeAccessController extends Controller
{
    public function show(Employee $employee): View
    {
        Gate::authorize('users.manage_access');
        $employee->load(['user.roleProfile', 'user.permissionOverrides.permission']);

        return view('admin.employees.access', [
            'employee' => $employee,
            'roles' => Role::where('is_active', true)->orderBy('name')->get(),
            'permissions' => Permission::where('is_active', true)->orderBy('module')->orderBy('name')->get()->groupBy('module'),
        ]);
    }

    public function store(Request $request, Employee $employee, UserAdministrationService $service): RedirectResponse
    {
        Gate::authorize('users.manage_access');
        $data = $request->validate([
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'temporary_password' => ['required', Password::min(10)->mixedCase()->numbers()],
        ]);
        $role = Role::where('is_active', true)->findOrFail($data['role_id']);
        $service->createAccess($employee, $data, $role, $data['temporary_password'], $request->user());

        return redirect()->route('admin.employees.access.show', $employee)
            ->with('success', 'Acesso criado. Copie a senha temporária agora.')
            ->with('temporary_password', $data['temporary_password']);
    }

    public function block(Request $request, Employee $employee, UserAdministrationService $service): RedirectResponse
    {
        Gate::authorize('users.block');
        $service->block($employee->user()->firstOrFail(), $request->user());

        return back()->with('success', 'Acesso bloqueado.');
    }

    public function unblock(Request $request, Employee $employee, UserAdministrationService $service): RedirectResponse
    {
        Gate::authorize('users.block');
        $service->unblock($employee->user()->firstOrFail(), $request->user());

        return back()->with('success', 'Acesso desbloqueado.');
    }

    public function deactivate(Request $request, Employee $employee, UserAdministrationService $service): RedirectResponse
    {
        Gate::authorize('users.manage_access');
        $service->deactivate($employee->user()->firstOrFail(), $request->user());

        return back()->with('success', 'Acesso desativado.');
    }

    public function activate(Request $request, Employee $employee, UserAdministrationService $service): RedirectResponse
    {
        Gate::authorize('users.manage_access');
        $service->activate($employee->user()->firstOrFail(), $request->user());

        return back()->with('success', 'Acesso reativado.');
    }

    public function resetPassword(Request $request, Employee $employee, UserAdministrationService $service): RedirectResponse
    {
        Gate::authorize('users.reset_password');
        $password = Str::password(14, true, true, false, false);
        $service->setTemporaryPassword($employee->user()->firstOrFail(), $password, $request->user());

        return back()->with('success', 'Senha redefinida. Copie a senha temporária agora.')->with('temporary_password', $password);
    }

    public function role(Request $request, Employee $employee, UserAdministrationService $service): RedirectResponse
    {
        Gate::authorize('users.change_role');
        $data = $request->validate(['role_id' => ['required', Rule::exists('roles', 'id')->where('is_active', true)]]);
        $role = Role::findOrFail($data['role_id']);
        $service->changeRole($employee->user()->firstOrFail(), $role, $role->code, $request->user());

        return back()->with('success', 'Perfil atualizado.');
    }

    public function override(Request $request, Employee $employee, Permission $permission, UserAdministrationService $service): RedirectResponse
    {
        Gate::authorize('administration.manage_permissions');
        $data = $request->validate(['effect' => ['required', Rule::in(['inherit', 'allow', 'deny'])]]);
        $service->setPermissionOverride($employee->user()->firstOrFail(), $permission, $data['effect'], $request->user());

        return back()->with('success', 'Exceção individual atualizada.');
    }
}
