<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Role;
use App\Services\DailyOperationService;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('employees.view');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(Employee::STATUSES)],
            'access' => ['nullable', Rule::in(['with', 'without'])],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);
        $employees = Employee::query()->with('user.roleProfile')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('display_name', 'like', "%{$search}%")
                ->orWhere('registration_number', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['access'] ?? null) === 'with', fn ($query) => $query->whereHas('user'))
            ->when(($filters['access'] ?? null) === 'without', fn ($query) => $query->whereDoesntHave('user'))
            ->when($filters['role_id'] ?? null, fn ($query, $roleId) => $query->whereHas('user', fn ($query) => $query->where('role_id', $roleId)))
            ->orderBy('display_name')->paginate(20)->withQueryString();

        return view('admin.employees.index', compact('employees', 'filters') + ['roles' => Role::where('is_active', true)->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('employees.create');

        return view('admin.employees.form', ['employee' => new Employee, 'canSensitive' => Gate::allows('employees.view_sensitive')]);
    }

    public function store(Request $request, EmployeeService $service): RedirectResponse
    {
        Gate::authorize('employees.create');
        $employee = $service->create($this->validated($request), $request->user());

        return redirect()->route('admin.employees.show', $employee)->with('success', 'Colaborador cadastrado.');
    }

    public function show(Employee $employee, DailyOperationService $operations): View
    {
        Gate::authorize('view', $employee);
        $employee->load(['user.roleProfile', 'shiftMemberships.shift']);
        $shift = $operations->ensure();
        $currentMembership = $employee->shiftMemberships()->where('shift_id', $shift->id)->whereNull('left_at')->first();

        return view('admin.employees.show', compact('employee', 'shift', 'currentMembership') + ['canSensitive' => Gate::allows('viewSensitive', $employee)]);
    }

    public function edit(Employee $employee): View
    {
        Gate::authorize('update', $employee);

        return view('admin.employees.form', compact('employee') + ['canSensitive' => Gate::allows('viewSensitive', $employee)]);
    }

    public function update(Request $request, Employee $employee, EmployeeService $service): RedirectResponse
    {
        Gate::authorize('update', $employee);
        $service->update($employee, $this->validated($request, $employee), $request->user());

        return redirect()->route('admin.employees.show', $employee)->with('success', 'Colaborador atualizado.');
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $rules = [
            'full_name' => ['required', 'string', 'max:180'], 'display_name' => ['required', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:60', Rule::unique('employees')->ignore($employee)],
            'job_title' => ['nullable', 'string', 'max:120'], 'operational_function' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'], 'unit' => ['nullable', 'string', 'max:120'],
            'corporate_email' => ['nullable', 'email', 'max:255'], 'hired_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(Employee::STATUSES)], 'photo' => ['nullable', 'image', 'max:3072'],
            'terminated_at' => ['nullable', 'date'],
        ];
        if (Gate::allows('employees.view_sensitive')) {
            $rules += ['personal_email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'],
                'administrative_notes' => ['nullable', 'string', 'max:5000'], 'termination_reason' => ['nullable', 'string', 'max:3000']];
        }
        $data = $request->validate($rules);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('employees', 'public');
        }
        unset($data['photo']);

        return $data;
    }
}
