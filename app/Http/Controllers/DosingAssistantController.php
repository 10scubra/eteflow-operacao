<?php

namespace App\Http\Controllers;

use App\Models\DosingAlert;
use App\Models\DosingCycle;
use App\Models\DosingRule;
use App\Models\Equipment;
use App\Services\AuditService;
use App\Services\DailyOperationService;
use App\Services\DosingAssistantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DosingAssistantController extends Controller
{
    public function index(Request $request, DosingAssistantService $assistant): View
    {
        abort_unless(in_array($request->user()->role, ['operator', 'master'], true), 403);

        return view('operation.dosing', $assistant->currentState() + [
            'impediments' => DosingAssistantService::Impediments,
            'history' => $request->user()->role === 'master'
                ? DosingAlert::query()->with(['rule.equipment', 'triggerValue.recordedBy', 'resolvedValue.recordedBy', 'actionedBy', 'cycles.startedBy', 'cycles.endedBy'])->latest('detected_at')->limit(100)->get()
                : collect(),
        ]);
    }

    public function configure(Request $request): View
    {
        abort_unless($request->user()->role === 'master', 403);
        $rule = DosingRule::query()->with(['parameterRule.points', 'point', 'equipment'])->where('stable_key', 'calcium_hydroxide_aerobic_ph')->latest('version')->firstOrFail();

        return view('admin.dosing.configure', ['rule' => $rule, 'equipment' => Equipment::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function updateConfiguration(Request $request, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->role === 'master', 403);
        $current = DosingRule::query()->with('parameterRule.points')->where('stable_key', 'calcium_hydroxide_aerobic_ph')->latest('version')->firstOrFail();
        $data = $request->validate([
            'parameter_rule_point_id' => ['nullable', 'integer', 'exists:parameter_rule_points,id'],
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'start_operator' => ['required', Rule::in(['LESS_THAN', 'LESS_THAN_OR_EQUAL', 'GREATER_THAN', 'GREATER_THAN_OR_EQUAL', 'EQUAL'])],
            'start_value' => ['required', 'numeric'],
            'stop_operator' => ['required', Rule::in(['LESS_THAN', 'LESS_THAN_OR_EQUAL', 'GREATER_THAN', 'GREATER_THAN_OR_EQUAL', 'EQUAL'])],
            'stop_value' => ['required', 'numeric'],
            'consistency_max_spread' => ['nullable', 'numeric', 'gt:0'],
            'recurrence_window' => ['nullable', 'integer', 'min:2', 'max:100'],
            'recurrence_count' => ['nullable', 'integer', 'min:2', 'max:100', 'lte:recurrence_window'],
            'reminder_after_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'reminder_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'recheck_after_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $pointId = $data['parameter_rule_point_id'] ?? null;
        if ($current->parameterRule->points->isNotEmpty() && ! $pointId && $request->boolean('is_active')) {
            throw ValidationException::withMessages(['parameter_rule_point_id' => 'Selecione explicitamente o ponto de pH antes de ativar a regra.']);
        }
        if ($pointId && ! $current->parameterRule->points->contains('id', $pointId)) {
            throw ValidationException::withMessages(['parameter_rule_point_id' => 'O ponto selecionado não pertence ao parâmetro monitorado.']);
        }

        $current->update(['is_active' => false, 'effective_until' => now()]);
        $next = DosingRule::query()->create($current->only(['stable_key', 'name', 'description', 'parameter_rule_id', 'product_name']) + $data + ['version' => $current->version + 1, 'is_active' => $request->boolean('is_active'), 'effective_from' => now(), 'effective_until' => null, 'created_by' => $request->user()->id]);
        $audit->record($next, 'dosing.rule_version_created', $request->user(), $current->toArray(), $next->toArray());

        return redirect()->route('admin.dosing.configure')->with('success', 'Nova versão da regra salva.');
    }

    public function start(Request $request, DosingAlert $alert, DosingAssistantService $assistant): RedirectResponse
    {
        $this->ensureOperator($request);
        $data = $request->validate(['percentage' => ['required', 'numeric', 'min:0', 'max:100']]);
        $assistant->start($alert, $request->user(), (float) $data['percentage']);

        return back()->with('success', 'Dosagem iniciada com autoria e horário do servidor.');
    }

    public function impediment(Request $request, DosingAlert $alert, DosingAssistantService $assistant): RedirectResponse
    {
        $this->ensureOperator($request);
        $data = $request->validate(['impediment_code' => ['required', 'string'], 'justification' => ['nullable', 'string', 'max:2000']]);
        $assistant->reportImpediment($alert, $request->user(), $data['impediment_code'], $data['justification'] ?? null);

        return back()->with('success', 'Impedimento registrado.');
    }

    public function percentage(Request $request, DosingCycle $cycle, DosingAssistantService $assistant): RedirectResponse
    {
        $this->ensureOperator($request);
        $data = $request->validate(['percentage' => ['required', 'numeric', 'min:0', 'max:100']]);
        $assistant->changePercentage($cycle, $request->user(), (float) $data['percentage']);

        return back()->with('success', 'Novo percentual registrado no histórico.');
    }

    public function ph(Request $request, DosingCycle $cycle, DosingAssistantService $assistant): RedirectResponse
    {
        $this->ensureOperator($request);
        $data = $request->validate(['ph_value' => ['required', 'numeric', 'min:0', 'max:14']]);
        $stop = $assistant->recordPh($cycle, $request->user(), (float) $data['ph_value']);

        return back()->with($stop ? 'stop_condition' : 'success', $stop ? 'Condição configurada para verificação de parada atingida.' : 'Novo pH registrado.');
    }

    public function continue(Request $request, DosingCycle $cycle, DosingAssistantService $assistant): RedirectResponse
    {
        $this->ensureOperator($request);
        $data = $request->validate(['justification' => ['required', 'string', 'max:2000']]);
        $assistant->continueAfterStop($cycle, $request->user(), $data['justification']);

        return back()->with('success', 'Decisão de continuar registrada.');
    }

    public function finish(Request $request, DosingCycle $cycle, DosingAssistantService $assistant): RedirectResponse
    {
        $this->ensureOperator($request);
        $data = $request->validate(['final_ph' => ['required', 'numeric', 'min:0', 'max:14'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $assistant->finish($cycle, $request->user(), (float) $data['final_ph'], $data['notes'] ?? null);

        return back()->with('success', 'Dosagem finalizada e histórico preservado.');
    }

    private function ensureOperator(Request $request): void
    {
        abort_unless($request->user()->role === 'operator', 403);
        $shift = app(DailyOperationService::class)->ensure();
        abort_unless($shift->members->contains($request->user()), 403);
    }
}
