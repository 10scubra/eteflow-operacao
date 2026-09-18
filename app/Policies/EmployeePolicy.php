<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Services\PermissionService;

class EmployeePolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->allows($user, 'employees.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->permissions->allows($user, 'employees.view');
    }

    public function viewSensitive(User $user, Employee $employee): bool
    {
        return $this->permissions->allows($user, 'employees.view_sensitive');
    }

    public function create(User $user): bool
    {
        return $this->permissions->allows($user, 'employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->permissions->allows($user, 'employees.edit');
    }

    public function deactivate(User $user, Employee $employee): bool
    {
        return $this->permissions->allows($user, 'employees.deactivate');
    }
}
