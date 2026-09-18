<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function __construct(private AuditService $audit) {}

    public function create(array $attributes, User $actor): Employee
    {
        return DB::transaction(function () use ($attributes, $actor) {
            $employee = Employee::query()->create($attributes);
            $this->audit->record($employee, 'employee.created', $actor, [], $this->auditable($employee));

            return $employee;
        });
    }

    public function update(Employee $employee, array $attributes, User $actor): Employee
    {
        return DB::transaction(function () use ($employee, $attributes, $actor) {
            $locked = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $old = $this->auditable($locked);
            $locked->update($attributes);
            $this->audit->record($locked, 'employee.updated', $actor, $old, $this->auditable($locked->fresh()));

            return $locked->fresh();
        });
    }

    public function linkUser(Employee $employee, User $user, User $actor): User
    {
        return DB::transaction(function () use ($employee, $user, $actor) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            if (User::query()->where('employee_id', $employee->id)->whereKeyNot($lockedUser->id)->exists()) {
                throw ValidationException::withMessages(['employee_id' => 'Este colaborador já possui uma credencial associada.']);
            }

            $oldEmployeeId = $lockedUser->employee_id;
            $lockedUser->update(['employee_id' => $employee->id]);
            $this->audit->record($lockedUser, 'user.employee_linked', $actor, ['employee_id' => $oldEmployeeId], ['employee_id' => $employee->id]);

            return $lockedUser->fresh();
        });
    }

    private function auditable(Employee $employee): array
    {
        return Arr::except($employee->attributesToArray(), ['created_at', 'updated_at']);
    }
}
