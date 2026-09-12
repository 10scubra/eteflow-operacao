<?php

namespace App\Http\Controllers;

use App\Actions\UpdateShiftTeam;
use App\Http\Requests\UpdateShiftTeamRequest;
use App\Models\User;
use App\Services\DailyOperationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftTeamController extends Controller
{
    public function index(Request $request, DailyOperationService $operations): View
    {
        abort_unless($request->user()->role === 'master', 403);

        $shift = $operations->ensure()->load('allMembers');

        return view('operation.team', [
            'shift' => $shift,
            'operators' => User::query()
                ->where('role', 'operator')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(
        UpdateShiftTeamRequest $request,
        DailyOperationService $operations,
        UpdateShiftTeam $updateShiftTeam,
    ): RedirectResponse {
        $shift = $operations->ensure();
        $operators = User::query()
            ->whereKey($request->validated('operator_ids'))
            ->where('role', 'operator')
            ->where('is_active', true)
            ->get();

        $updateShiftTeam->handle($shift, $operators, $request->user());

        return redirect()->route('team.index')->with('success', 'Equipe do turno atualizada com histórico preservado.');
    }
}
