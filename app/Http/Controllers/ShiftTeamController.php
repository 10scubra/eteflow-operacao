<?php

namespace App\Http\Controllers;

use App\Actions\UpdateShiftTeam;
use App\Http\Requests\UpdateShiftTeamRequest;
use App\Models\Employee;
use App\Models\ShiftMember;
use App\Models\User;
use App\Services\DailyOperationService;
use App\Services\ShiftParticipationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShiftTeamController extends Controller
{
    public function index(Request $request, DailyOperationService $operations): View
    {
        Gate::authorize('shifts.manage_team');
        $shift = $operations->ensure()->load(['allMembers', 'participations.employee.user', 'participations.user']);

        return view('operation.team', [
            'shift' => $shift,
            'operators' => User::query()->where('role', 'operator')->where('is_active', true)->orderBy('name')->get(),
            'employees' => Employee::query()->with('user')->where('status', 'active')->orderBy('display_name')->get(),
        ]);
    }

    public function update(UpdateShiftTeamRequest $request, DailyOperationService $operations, UpdateShiftTeam $updateShiftTeam): RedirectResponse
    {
        $shift = $operations->ensure();
        $operators = User::query()->whereKey($request->validated('operator_ids'))->where('role', 'operator')->where('is_active', true)->get();
        $updateShiftTeam->handle($shift, $operators, $request->user());

        return redirect()->route('team.index')->with('success', 'Equipe do turno atualizada com histórico preservado.');
    }

    public function join(Request $request, DailyOperationService $operations, ShiftParticipationService $service): RedirectResponse
    {
        Gate::authorize('shifts.manage_team');
        $data = $request->validate(['employee_id' => ['required', 'integer', 'exists:employees,id']]);
        $employee = Employee::query()->with('user')->where('status', 'active')->findOrFail($data['employee_id']);
        $service->join($operations->ensure(), $employee, $employee->user, now(), $request->user());

        return back()->with('success', 'Participante incluído no turno.');
    }

    public function leave(Request $request, ShiftMember $shiftMember, DailyOperationService $operations, ShiftParticipationService $service): RedirectResponse
    {
        Gate::authorize('shifts.manage_team');
        abort_unless($shiftMember->shift_id === $operations->ensure()->id, 404);
        $service->leave($shiftMember, now(), $request->user());

        return back()->with('success', 'Saída registrada.');
    }

    public function substitute(Request $request, ShiftMember $shiftMember, DailyOperationService $operations, ShiftParticipationService $service): RedirectResponse
    {
        Gate::authorize('shifts.replace_operator');
        abort_unless($shiftMember->shift_id === $operations->ensure()->id, 404);
        $data = $request->validate(['employee_id' => ['required', 'integer', 'exists:employees,id']]);
        $employee = Employee::query()->with('user')->where('status', 'active')->findOrFail($data['employee_id']);
        $service->substitute($shiftMember, $employee, $employee->user, now(), $request->user());

        return back()->with('success', 'Substituição registrada sem alterar o histórico anterior.');
    }
}
