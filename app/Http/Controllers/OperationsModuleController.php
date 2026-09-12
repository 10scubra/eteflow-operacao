<?php

namespace App\Http\Controllers;

use App\Models\ActionEvidence;
use App\Models\Aspersion;
use App\Models\EquipmentStatus;
use App\Models\Occurrence;
use App\Models\OperationalAction;
use App\Models\ReadingSection;
use App\Models\ShiftHandover;
use App\Services\DailyOperationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OperationsModuleController extends Controller
{
    public function home(Request $request, DailyOperationService $operations): View
    {
        $shift = $operations->ensure();
        $timeline = $operations->timeline($shift);
        $round = $timeline['display']?->load('sections');

        return view('operation.module', $this->baseData('home', $shift) + [
            'timeline' => $timeline,
            'round' => $round,
        ]);
    }

    public function actions(Request $request, DailyOperationService $operations, ?OperationalAction $action = null): View
    {
        $shift = $operations->ensure();
        if ($action && $action->shift_id !== $shift->id) {
            abort(404);
        }

        return view('operation.module', $this->baseData('actions', $shift) + [
            'actions' => OperationalAction::query()->where('shift_id', $shift->id)
                ->with(['checklistItems', 'evidences.user', 'startedBy', 'completedBy'])
                ->orderByRaw("FIELD(status, 'in_progress', 'pending', 'completed', 'cancelled')")
                ->orderByDesc('priority')->orderBy('due_at')->get(),
            'selectedAction' => $action?->load(['checklistItems', 'evidences.user', 'startedBy', 'completedBy']),
        ]);
    }

    public function createAction(Request $request, DailyOperationService $operations): RedirectResponse
    {
        abort_unless($request->user()->role === 'master', 403);
        $shift = $operations->ensure();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'equipment' => ['nullable', 'string', 'max:150'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'due_at' => ['nullable', 'date'],
        ]);

        $action = OperationalAction::create($data + [
            'shift_id' => $shift->id,
            'status' => 'pending',
            'created_by' => $request->user()->id,
            'requires_before_photo' => $request->boolean('requires_before_photo'),
            'requires_after_photo' => $request->boolean('requires_after_photo'),
        ]);
        $action->checklistItems()->create(['label' => 'Executar a atividade e registrar o resultado.']);

        return redirect()->route('actions.show', $action)->with('success', 'Ação criada para o turno.');
    }

    public function startAction(Request $request, OperationalAction $action): RedirectResponse
    {
        $this->ensureShiftMember($request, $action->shift_id);
        if ($action->status === 'pending') {
            $action->update([
                'status' => 'in_progress',
                'started_by' => $request->user()->id,
                'started_at' => now(),
            ]);
        }

        return back()->with('success', 'Execução iniciada com autoria e horário.');
    }

    public function completeAction(Request $request, OperationalAction $action): RedirectResponse
    {
        $this->ensureShiftMember($request, $action->shift_id);
        $data = $request->validate([
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['integer'],
            'observation' => ['required', 'string', 'max:5000'],
            'measured_value' => ['nullable', 'numeric'],
            'before_photo' => ['nullable', 'image', 'max:8192'],
            'after_photo' => ['nullable', 'image', 'max:8192'],
        ]);

        $checked = collect($data['checklist'] ?? [])->map(fn ($id) => (int) $id);
        $allChecklistIds = $action->checklistItems()->pluck('id');
        if ($allChecklistIds->diff($checked)->isNotEmpty()) {
            return back()->withErrors(['checklist' => 'Conclua todos os itens do checklist.'])->withInput();
        }

        $existingTypes = $action->evidences()->pluck('type');
        if ($action->requires_before_photo && ! $existingTypes->contains('before') && ! $request->hasFile('before_photo')) {
            return back()->withErrors(['before_photo' => 'A foto antes é obrigatória.'])->withInput();
        }
        if ($action->requires_after_photo && ! $existingTypes->contains('after') && ! $request->hasFile('after_photo')) {
            return back()->withErrors(['after_photo' => 'A foto depois é obrigatória.'])->withInput();
        }

        DB::transaction(function () use ($request, $action, $checked, $data) {
            foreach ($action->checklistItems as $item) {
                $item->update([
                    'is_completed' => $checked->contains($item->id),
                    'completed_by' => $request->user()->id,
                    'completed_at' => now(),
                ]);
            }

            foreach (['before', 'after'] as $type) {
                $file = $request->file($type.'_photo');
                if (! $file) {
                    continue;
                }
                $path = $file->store('action-evidences/'.$action->id);
                $action->evidences()->create([
                    'type' => $type,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                    'user_id' => $request->user()->id,
                    'taken_at' => now(),
                ]);
            }

            $action->update([
                'status' => 'completed',
                'observation' => $data['observation'],
                'measured_value' => $data['measured_value'] ?? null,
                'completed_by' => $request->user()->id,
                'completed_at' => now(),
            ]);
        });

        return redirect()->route('actions.show', $action)->with('success', 'Ação concluída com evidências preservadas.');
    }

    public function evidence(Request $request, ActionEvidence $evidence)
    {
        $this->ensureShiftAccess($request, $evidence->action->shift_id);

        return Storage::disk('local')->response($evidence->path, $evidence->original_name, [
            'Content-Type' => $evidence->mime_type,
        ]);
    }

    public function occurrences(Request $request, DailyOperationService $operations): View
    {
        $shift = $operations->ensure();

        return view('operation.module', $this->baseData('occurrences', $shift) + [
            'occurrences' => Occurrence::query()->where('shift_id', $shift->id)
                ->with(['reporter', 'action'])->latest('reported_at')->get(),
        ]);
    }

    public function createOccurrence(Request $request, DailyOperationService $operations): RedirectResponse
    {
        $shift = $operations->ensure();
        $this->ensureShiftMember($request, $shift->id);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'location' => ['required', 'string', 'max:180'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'photo' => ['nullable', 'image', 'max:8192'],
        ]);
        $next = (int) Occurrence::max('id') + 1;
        $path = $request->file('photo')?->store('occurrences');

        Occurrence::create($data + [
            'shift_id' => $shift->id,
            'code' => 'OC-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT),
            'status' => 'open',
            'photo_path' => $path,
            'reported_by' => $request->user()->id,
            'reported_at' => now(),
        ]);

        return back()->with('success', 'Ocorrência registrada e compartilhada com o turno.');
    }

    public function occurrencePhoto(Request $request, Occurrence $occurrence)
    {
        $this->ensureShiftAccess($request, $occurrence->shift_id);
        abort_unless($occurrence->photo_path, 404);

        return Storage::disk('local')->response($occurrence->photo_path);
    }

    public function convertOccurrence(Request $request, Occurrence $occurrence): RedirectResponse
    {
        abort_unless($request->user()->role === 'master', 403);
        if (! $occurrence->operational_action_id) {
            $action = OperationalAction::create([
                'shift_id' => $occurrence->shift_id,
                'title' => 'Inspecionar: '.$occurrence->title,
                'description' => $occurrence->description,
                'priority' => $occurrence->priority,
                'equipment' => $occurrence->location,
                'due_at' => now()->addHours(2),
                'status' => 'pending',
                'created_by' => $request->user()->id,
                'requires_after_photo' => true,
            ]);
            $action->checklistItems()->create(['label' => 'Inspecionar o local e executar a correção necessária.']);
            $occurrence->update(['operational_action_id' => $action->id, 'status' => 'monitoring']);
        }

        return back()->with('success', 'Ocorrência transformada em ação mantendo o vínculo.');
    }

    public function aspersions(Request $request, DailyOperationService $operations): View
    {
        $shift = $operations->ensure();

        return view('operation.module', $this->baseData('aspersion', $shift) + [
            'aspersions' => Aspersion::query()->where('shift_id', $shift->id)
                ->with(['startedBy', 'endedBy'])->latest('started_at')->get(),
        ]);
    }

    public function startAspersion(Request $request, DailyOperationService $operations): RedirectResponse
    {
        $shift = $operations->ensure();
        $this->ensureShiftMember($request, $shift->id);
        $data = $request->validate([
            'area' => ['required', 'string', 'max:120'],
            'line' => ['nullable', 'string', 'max:120'],
            'initial_reading' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        Aspersion::create($data + [
            'shift_id' => $shift->id,
            'status' => 'active',
            'started_by' => $request->user()->id,
            'started_at' => now(),
        ]);

        return back()->with('success', 'Aspersão iniciada e registrada.');
    }

    public function endAspersion(Request $request, Aspersion $aspersion): RedirectResponse
    {
        $this->ensureShiftMember($request, $aspersion->shift_id);
        $data = $request->validate(['final_reading' => ['nullable', 'numeric']]);
        $total = isset($data['final_reading']) && $aspersion->initial_reading !== null
            ? max(0, (float) $data['final_reading'] - (float) $aspersion->initial_reading)
            : null;

        $aspersion->update([
            'status' => 'completed',
            'final_reading' => $data['final_reading'] ?? null,
            'total_consumption' => $total,
            'ended_by' => $request->user()->id,
            'ended_at' => now(),
        ]);

        return back()->with('success', 'Aspersão finalizada com horário e consumo registrados.');
    }

    public function handover(Request $request, DailyOperationService $operations): View
    {
        $shift = $operations->ensure();
        $summary = $this->handoverSummary($shift->id);

        return view('operation.module', $this->baseData('handover', $shift) + [
            'handover' => ShiftHandover::query()->where('shift_id', $shift->id)->with('confirmedBy')->first(),
            'summary' => $summary,
        ]);
    }

    public function closeHandover(Request $request, DailyOperationService $operations): RedirectResponse
    {
        $shift = $operations->ensure();
        $this->ensureShiftMember($request, $shift->id);
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:5000'],
            'confirmed' => ['accepted'],
        ]);

        ShiftHandover::updateOrCreate(
            ['shift_id' => $shift->id],
            [
                'summary' => $this->handoverSummary($shift->id),
                'note' => $data['note'] ?? null,
                'confirmed' => true,
                'confirmed_by' => $request->user()->id,
                'closed_at' => now(),
            ],
        );

        return back()->with('success', 'Passagem de turno registrada.');
    }

    public function more(Request $request, DailyOperationService $operations): View
    {
        return view('operation.module', $this->baseData('more', $operations->ensure()));
    }

    private function baseData(string $module, $shift): array
    {
        return [
            'module' => $module,
            'shift' => $shift,
            'pendingActions' => OperationalAction::where('shift_id', $shift->id)->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'openOccurrences' => Occurrence::where('shift_id', $shift->id)->whereNot('status', 'resolved')->count(),
            'activeAspersions' => Aspersion::where('shift_id', $shift->id)->where('status', 'active')->count(),
            'equipment' => EquipmentStatus::where('shift_id', $shift->id)->latest('reported_at')->get(),
        ];
    }

    private function handoverSummary(int $shiftId): array
    {
        return [
            'pending_actions' => OperationalAction::where('shift_id', $shiftId)->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'open_occurrences' => Occurrence::where('shift_id', $shiftId)->whereNot('status', 'resolved')->count(),
            'active_aspersions' => Aspersion::where('shift_id', $shiftId)->where('status', 'active')->count(),
            'pending_reading_sections' => ReadingSection::whereHas('round', fn ($query) => $query->where('shift_id', $shiftId))->whereNot('status', 'completed')->count(),
        ];
    }

    private function ensureShiftMember(Request $request, int $shiftId): void
    {
        abort_unless($request->user()->shifts()->whereKey($shiftId)->exists(), 403);
    }

    private function ensureShiftAccess(Request $request, int $shiftId): void
    {
        abort_unless($request->user()->role === 'master' || $request->user()->shifts()->whereKey($shiftId)->exists(), 403);
    }
}
