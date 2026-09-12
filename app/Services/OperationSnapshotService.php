<?php

namespace App\Services;

use App\Models\Aspersion;
use App\Models\AuditLog;
use App\Models\EquipmentStatus;
use App\Models\Occurrence;
use App\Models\OperationalAction;
use App\Models\ReadingValue;
use Carbon\CarbonInterface;

class OperationSnapshotService
{
    public function __construct(private DailyOperationService $operations) {}

    public function build(?CarbonInterface $moment = null): array
    {
        $shift = $this->operations->ensure($moment);
        $timeline = $this->operations->timeline($shift, $moment);
        $now = $timeline['now'];
        $rounds = $timeline['rounds'];

        $roundPayload = $rounds->map(function ($round) use ($now) {
            $total = $round->sections->count();
            $completed = $round->sections->where('status', 'completed')->count();
            $status = $completed === $total && $total > 0
                ? 'completed'
                : ($round->sections->whereIn('status', ['in_progress', 'reopened'])->isNotEmpty() ? 'in_progress' : 'pending');

            return [
                'id' => $round->id,
                'scheduled_at' => $round->scheduled_at->toIso8601String(),
                'time' => $round->scheduled_at->format('H:i'),
                'status' => $status,
                'completed' => $completed,
                'total' => $total,
                'progress' => $total ? (int) round(($completed / $total) * 100) : 0,
                'is_overdue' => $round->scheduled_at->lt($now) && $status !== 'completed',
                'seconds_from_now' => $round->scheduled_at->diffInSeconds($now, false),
                'sections' => $round->sections->map(fn ($section) => [
                    'id' => $section->id,
                    'key' => $section->section_key,
                    'label' => $section->label,
                    'status' => $section->status,
                    'lock_version' => $section->lock_version,
                    'last_editor' => $section->lastEditedBy?->name,
                    'updated_at' => $section->updated_at?->toIso8601String(),
                    'completed_at' => $section->completed_at?->toIso8601String(),
                ])->values(),
            ];
        })->values();

        $alerts = ReadingValue::query()
            ->whereHas('section.round', fn ($query) => $query->where('shift_id', $shift->id))
            ->where('is_out_of_range', true)
            ->with(['section', 'recordedBy'])
            ->latest('updated_at')->limit(10)->get()
            ->map(fn ($value) => [
                'field_key' => $value->field_key,
                'section' => $value->section->label,
                'value' => $value->value_numeric,
                'unit' => $value->unit,
                'minimum' => $value->minimum_at_time,
                'maximum' => $value->maximum_at_time,
                'operator' => $value->recordedBy?->name,
                'recorded_at' => $value->updated_at?->toIso8601String(),
            ])->values();

        $activities = AuditLog::query()
            ->where('auditable_type', 'App\\Models\\ReadingSection')
            ->whereHasMorph('auditable', ['App\\Models\\ReadingSection'], function ($query) use ($shift) {
                $query->whereHas('round', fn ($round) => $round->where('shift_id', $shift->id));
            })
            ->with('user')->latest('created_at')->limit(12)->get()
            ->map(fn ($log) => [
                'type' => 'reading',
                'title' => 'Bloco de leitura salvo',
                'operator' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        $actionActivities = OperationalAction::query()->where('shift_id', $shift->id)
            ->with(['startedBy', 'completedBy'])->whereIn('status', ['in_progress', 'completed'])->latest('updated_at')->limit(8)->get()
            ->map(fn ($action) => [
                'type' => 'action',
                'title' => $action->status === 'completed' ? 'Ação concluída: '.$action->title : 'Ação iniciada: '.$action->title,
                'operator' => $action->completedBy?->name ?? $action->startedBy?->name,
                'created_at' => ($action->completed_at ?? $action->started_at)?->toIso8601String(),
            ]);

        $occurrenceActivities = Occurrence::query()->where('shift_id', $shift->id)->with('reporter')->latest('reported_at')->limit(8)->get()
            ->map(fn ($occurrence) => [
                'type' => 'occurrence',
                'title' => $occurrence->code.' • '.$occurrence->title,
                'operator' => $occurrence->reporter?->name,
                'created_at' => $occurrence->reported_at?->toIso8601String(),
            ]);

        $aspersionActivities = Aspersion::query()->where('shift_id', $shift->id)->with(['startedBy', 'endedBy'])->latest('updated_at')->limit(8)->get()
            ->map(fn ($aspersion) => [
                'type' => 'aspersion',
                'title' => ($aspersion->status === 'active' ? 'Aspersão iniciada: ' : 'Aspersão finalizada: ').$aspersion->area,
                'operator' => $aspersion->endedBy?->name ?? $aspersion->startedBy?->name,
                'created_at' => ($aspersion->ended_at ?? $aspersion->started_at)?->toIso8601String(),
            ]);

        $activities = $activities->concat($actionActivities)->concat($occurrenceActivities)->concat($aspersionActivities)
            ->filter(fn ($activity) => $activity['created_at'])
            ->sortByDesc('created_at')->take(12)->values();

        $actions = OperationalAction::query()->where('shift_id', $shift->id);
        $occurrences = Occurrence::query()->where('shift_id', $shift->id);
        $aspersions = Aspersion::query()->where('shift_id', $shift->id);
        $equipment = EquipmentStatus::query()->where('shift_id', $shift->id)->latest('reported_at')->get();

        return [
            'server_time' => $now->toIso8601String(),
            'timezone' => config('app.timezone'),
            'shift' => [
                'id' => $shift->id,
                'date' => $shift->shift_date->format('Y-m-d'),
                'type' => $this->operations->type($shift),
                'name' => $this->operations->label($shift),
                'starts_at' => substr($shift->starts_at, 0, 5),
                'ends_at' => substr($shift->ends_at, 0, 5),
                'status' => $shift->status,
                'members' => $shift->members->map(fn ($member) => ['id' => $member->id, 'name' => $member->name, 'username' => $member->username])->values(),
            ],
            'current_round_id' => $timeline['current']?->id,
            'next_round_id' => $timeline['next']?->id,
            'display_round_id' => $timeline['display']?->id,
            'rounds' => $roundPayload,
            'alerts' => $alerts,
            'activities' => $activities,
            'operations' => [
                'pending_actions' => (clone $actions)->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'completed_actions' => (clone $actions)->where('status', 'completed')->count(),
                'overdue_actions' => (clone $actions)->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', $now)->count(),
                'open_occurrences' => (clone $occurrences)->whereNot('status', 'resolved')->count(),
                'active_aspersions' => (clone $aspersions)->where('status', 'active')->count(),
                'stopped_equipment' => $equipment->where('status', 'stopped')->count(),
                'attention_equipment' => $equipment->where('status', 'attention')->count(),
                'equipment' => $equipment->map(fn ($item) => [
                    'name' => $item->name,
                    'location' => $item->location,
                    'status' => $item->status,
                    'reading' => $item->reading,
                    'unit' => $item->unit,
                    'reported_at' => $item->reported_at?->toIso8601String(),
                ])->values(),
            ],
        ];
    }
}
