<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveReadingSectionRequest;
use App\Models\ReadingSection;
use App\Services\AuditService;
use App\Services\OperationalConfigurationService;
use App\Services\ReadingSectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReadingSectionController extends Controller
{
    public function show(Request $request, ReadingSection $readingSection, OperationalConfigurationService $configuration): JsonResponse
    {
        $readingSection->load([
            'round.shift.members',
            'values.recordedBy',
            'values.point',
            'startedBy',
            'completedBy',
            'lastEditedBy',
            'editingBy',
        ]);
        $this->ensureAccess($request, $readingSection);

        $rules = $configuration->rulesForSection($readingSection)->each->load('points')->values();

        $previousSection = ReadingSection::query()
            ->where('section_key', $readingSection->section_key)
            ->whereKeyNot($readingSection->id)
            ->whereHas('round', fn ($query) => $query->where('scheduled_at', '<', $readingSection->round->scheduled_at))
            ->with(['round', 'values'])
            ->get()
            ->sortByDesc(fn ($item) => $item->round->scheduled_at)
            ->first();

        return response()->json([
            'section' => $readingSection,
            'rules' => $rules,
            'previous_values' => $previousSection?->values?->map(fn ($value) => [
                'field_key' => $value->field_key,
                'parameter_rule_id' => $value->parameter_rule_id,
                'parameter_rule_point_id' => $value->parameter_rule_point_id,
                'semantic_status' => $value->semantic_status,
                'value_text' => $value->value_text,
                'value_numeric' => $value->value_numeric,
                'unit' => $value->unit,
            ])->values() ?? [],
            'previous_round_time' => $previousSection?->round?->scheduled_at?->format('H:i'),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function open(Request $request, ReadingSection $readingSection, AuditService $audit): JsonResponse
    {
        $request->validate(['force' => ['sometimes', 'boolean']]);
        $readingSection->load(['round.shift.members']);
        $this->ensureAccess($request, $readingSection);

        $result = DB::transaction(function () use ($request, $readingSection, $audit) {
            $section = ReadingSection::query()->with('editingBy')->lockForUpdate()->findOrFail($readingSection->id);
            $occupiedByAnother = $section->editing_by
                && $section->editing_by !== $request->user()->id
                && $section->editing_started_at?->gt(now()->subMinutes(15));

            if ($occupiedByAnother && ! $request->boolean('force')) {
                return [
                    'conflict' => true,
                    'editor' => $section->editingBy?->name,
                    'editing_started_at' => $section->editing_started_at?->toIso8601String(),
                ];
            }

            if ($section->editing_by !== $request->user()->id) {
                $previous = $section->only(['editing_by', 'editing_started_at']);
                $section->update([
                    'editing_by' => $request->user()->id,
                    'editing_started_at' => now(),
                ]);

                $audit->record(
                    $section,
                    $occupiedByAnother ? 'reading_section.opened_with_warning' : 'reading_section.opened',
                    $request->user(),
                    $previous,
                    $section->only(['editing_by', 'editing_started_at']),
                );
            }

            return ['conflict' => false, 'section' => $section->fresh('editingBy')];
        }, 3);

        if ($result['conflict']) {
            return response()->json([
                'message' => ($result['editor'] ?: 'Outro operador').' está preenchendo este bloco.',
                'editor' => $result['editor'],
                'editing_started_at' => $result['editing_started_at'],
                'can_override' => true,
                'server_time' => now()->toIso8601String(),
            ], 409);
        }

        return response()->json([
            'message' => 'Bloco aberto para edição.',
            'section' => $result['section'],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function update(SaveReadingSectionRequest $request, ReadingSection $readingSection, ReadingSectionService $service): JsonResponse
    {
        try {
            $saved = $service->save($readingSection, $request->user(), $request->validated());
        } catch (ConflictHttpException $exception) {
            $current = $readingSection->fresh(['lastEditedBy']);

            return response()->json([
                'message' => $exception->getMessage(),
                'current_lock_version' => $current->lock_version,
                'last_editor' => $current->lastEditedBy?->name,
                'updated_at' => $current->updated_at?->toIso8601String(),
            ], 409);
        }

        return response()->json([
            'message' => 'Bloco salvo sem alterar os demais blocos.',
            'section' => $saved,
            'warnings' => $saved->values->where('is_out_of_range', true)->values(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function ensureAccess(Request $request, ReadingSection $readingSection): void
    {
        $allowed = $request->user()->role === 'master'
            || $readingSection->round->shift->members->contains($request->user());

        abort_unless($allowed, 403);
    }
}
