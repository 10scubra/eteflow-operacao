<?php

namespace App\Services;

use App\Models\DosingAlert;
use App\Models\DosingConsistencyCheck;
use App\Models\DosingCycle;
use App\Models\DosingRule;
use App\Models\Equipment;
use App\Models\ReadingValue;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DosingAssistantService
{
    public const Impediments = [
        'PUMP_FAILURE' => 'Bomba com falha',
        'PUMP_MAINTENANCE' => 'Bomba em manutenção',
        'PRODUCT_UNAVAILABLE' => 'Produto indisponível',
        'CONTAINER_EMPTY' => 'Recipiente/tanque sem produto',
        'AWAITING_GUIDANCE' => 'Aguardando orientação',
        'NOT_NEEDED_NOW' => 'Dosagem não necessária neste momento',
        'OTHER' => 'Outro',
    ];

    public function __construct(private AuditService $audit) {}

    public function evaluate(ReadingValue $value, User $actor): void
    {
        if ($value->semantic_status !== 'MEASURED' || $value->value_numeric === null) {
            return;
        }

        $rules = DosingRule::query()
            ->where('is_active', true)
            ->where('parameter_rule_id', $value->parameter_rule_id)
            ->where('effective_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->get();

        foreach ($rules as $rule) {
            $this->evaluateConsistency($rule, $value, $actor);
            if ($rule->parameter_rule_point_id === $value->parameter_rule_point_id) {
                $this->evaluateRule($rule, $value, $actor);
            }
        }
    }

    public function currentState(): array
    {
        $cycle = DosingCycle::query()->where('status', 'ACTIVE')->with(['rule', 'equipment', 'startedBy', 'events.creator'])->latest('started_at')->first();
        $alert = DosingAlert::query()->whereIn('status', ['OPEN', 'REMINDER'])->with(['rule.equipment', 'triggerValue', 'actionedBy'])->latest('detected_at')->first();
        $consistency = DosingConsistencyCheck::query()->with(['rule', 'controlPoint', 'suspectedPoint'])->latest('evaluated_at')->first();
        if ($alert && $alert->status === 'OPEN' && now()->gte($alert->detected_at->copy()->addMinutes($alert->rule->reminder_after_minutes))) {
            $alert->update(['status' => 'REMINDER', 'last_reminded_at' => now()]);
            $this->audit->record($alert, 'dosing.reminder_due', null, ['status' => 'OPEN'], ['status' => 'REMINDER']);
        } elseif ($alert && $alert->status === 'REMINDER' && $alert->rule->reminder_interval_minutes && now()->gte(($alert->last_reminded_at ?? $alert->detected_at)->copy()->addMinutes($alert->rule->reminder_interval_minutes))) {
            $previousReminder = $alert->last_reminded_at;
            $alert->update(['last_reminded_at' => now()]);
            $this->audit->record($alert, 'dosing.reminder_repeated', null, ['last_reminded_at' => $previousReminder?->toIso8601String()], ['last_reminded_at' => $alert->last_reminded_at?->toIso8601String()]);
        }

        $lastPhAt = $cycle?->events?->whereNotNull('ph_value')->max('event_occurred_at');
        $recheckDue = $cycle && $cycle->rule->recheck_after_minutes
            ? now()->gte(($lastPhAt ?? $cycle->started_at)->copy()->addMinutes($cycle->rule->recheck_after_minutes))
            : false;

        $recurrence = false;
        if ($consistency?->is_divergent && $consistency->suspected_point_id && $consistency->rule->recurrence_window && $consistency->rule->recurrence_count) {
            $recent = DosingConsistencyCheck::query()->where('dosing_rule_id', $consistency->dosing_rule_id)->latest('evaluated_at')->limit($consistency->rule->recurrence_window)->get();
            $recurrence = $recent->where('is_divergent', true)->where('suspected_point_id', $consistency->suspected_point_id)->count() >= $consistency->rule->recurrence_count;
        }

        return ['cycle' => $cycle, 'alert' => $alert?->fresh(['rule.equipment', 'triggerValue', 'actionedBy']), 'recheckDue' => $recheckDue, 'consistency' => $consistency, 'consistencyRecurrence' => $recurrence];
    }

    public function reportImpediment(DosingAlert $alert, User $user, string $code, ?string $justification): DosingAlert
    {
        if (! isset(self::Impediments[$code])) {
            throw ValidationException::withMessages(['impediment_code' => 'Selecione um motivo válido.']);
        }
        if (in_array($code, ['NOT_NEEDED_NOW', 'OTHER'], true) && blank($justification)) {
            throw ValidationException::withMessages(['justification' => 'Informe a justificativa para este motivo.']);
        }

        $before = $alert->only(['status', 'impediment_code', 'justification']);
        $alert->update(['status' => 'IMPEDIMENT_REPORTED', 'actioned_at' => now(), 'actioned_by' => $user->id, 'impediment_code' => $code, 'justification' => $justification]);
        $this->audit->record($alert, 'dosing.impediment_reported', $user, $before, $alert->only(['status', 'impediment_code', 'justification']));

        return $alert;
    }

    public function start(DosingAlert $alert, User $user, float $percentage): DosingCycle
    {
        try {
            return DB::transaction(function () use ($alert, $user, $percentage): DosingCycle {
                $rule = DosingRule::query()->with('equipment')->findOrFail($alert->dosing_rule_id);
                Equipment::query()->lockForUpdate()->findOrFail($rule->equipment_id);
                $existing = DosingCycle::query()->where('equipment_id', $rule->equipment_id)->where('status', 'ACTIVE')->with('startedBy')->first();
                if ($existing) {
                    throw ValidationException::withMessages(['cycle' => "Já existe dosagem ativa desde {$existing->started_at->format('H:i')}, iniciada por {$existing->startedBy?->name}."]);
                }

                $cycle = DosingCycle::query()->create([
                    'dosing_rule_id' => $rule->id, 'dosing_alert_id' => $alert->id, 'equipment_id' => $rule->equipment_id,
                    'active_lock_key' => $rule->equipment_id, 'trigger_reading_value_id' => $alert->trigger_reading_value_id,
                    'product_name' => $rule->product_name, 'process_name' => $rule->parameterRule->section_key,
                    'started_at' => now(), 'started_by' => $user->id, 'initial_value' => $alert->trigger_value,
                    'initial_percentage' => $percentage, 'status' => 'ACTIVE',
                ]);
                $cycle->events()->create(['equipment_id' => $rule->equipment_id, 'type' => 'STARTED', 'percentage' => $percentage, 'ph_value' => $alert->trigger_value, 'event_occurred_at' => now(), 'created_by' => $user->id]);
                $alert->update(['status' => 'DOSING_STARTED', 'actioned_at' => now(), 'actioned_by' => $user->id]);
                $this->audit->record($cycle, 'dosing.started', $user, [], $cycle->only(['equipment_id', 'initial_value', 'initial_percentage', 'status']));

                return $cycle->fresh(['rule', 'equipment', 'startedBy', 'events']);
            }, 3);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'active_lock_key')) {
                throw ValidationException::withMessages(['cycle' => 'Já existe uma dosagem ativa para esta bomba.']);
            }
            throw $exception;
        }
    }

    public function changePercentage(DosingCycle $cycle, User $user, float $percentage): void
    {
        $this->ensureActive($cycle);
        $event = $cycle->events()->create(['equipment_id' => $cycle->equipment_id, 'type' => 'PERCENTAGE_CHANGED', 'percentage' => $percentage, 'event_occurred_at' => now(), 'created_by' => $user->id]);
        $this->audit->record($cycle, 'dosing.percentage_changed', $user, [], $event->only(['percentage', 'event_occurred_at']));
    }

    public function recordPh(DosingCycle $cycle, User $user, float $ph): bool
    {
        $this->ensureActive($cycle);
        $event = $cycle->events()->create(['equipment_id' => $cycle->equipment_id, 'type' => 'PH_MEASURED', 'ph_value' => $ph, 'event_occurred_at' => now(), 'created_by' => $user->id]);
        $this->audit->record($cycle, 'dosing.ph_measured', $user, [], $event->only(['ph_value', 'event_occurred_at']));

        return $this->compare($ph, $cycle->rule->stop_operator, (float) $cycle->rule->stop_value);
    }

    public function continueAfterStop(DosingCycle $cycle, User $user, string $justification): void
    {
        $this->ensureActive($cycle);
        if (blank($justification)) {
            throw ValidationException::withMessages(['justification' => 'Informe por que a dosagem continuará.']);
        }
        $event = $cycle->events()->create(['equipment_id' => $cycle->equipment_id, 'type' => 'CONTINUED_AFTER_STOP_CONDITION', 'justification' => $justification, 'event_occurred_at' => now(), 'created_by' => $user->id]);
        $this->audit->record($cycle, 'dosing.continued_after_stop_condition', $user, [], $event->only(['justification', 'event_occurred_at']));
    }

    public function finish(DosingCycle $cycle, User $user, float $finalPh, ?string $notes): DosingCycle
    {
        return DB::transaction(function () use ($cycle, $user, $finalPh, $notes): DosingCycle {
            $locked = DosingCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $this->ensureActive($locked);
            $before = $locked->only(['status', 'ended_at', 'ended_by', 'final_value']);
            $locked->update(['status' => 'COMPLETED', 'active_lock_key' => null, 'ended_at' => now(), 'ended_by' => $user->id, 'final_value' => $finalPh, 'end_reason' => 'OPERATOR_CONFIRMED', 'notes' => $notes]);
            $locked->events()->create(['equipment_id' => $locked->equipment_id, 'type' => 'ENDED', 'ph_value' => $finalPh, 'event_occurred_at' => now(), 'created_by' => $user->id]);
            $this->audit->record($locked, 'dosing.ended', $user, $before, $locked->only(['status', 'ended_at', 'ended_by', 'final_value']));

            return $locked->fresh(['rule', 'equipment', 'startedBy', 'endedBy', 'events.creator']);
        }, 3);
    }

    private function evaluateRule(DosingRule $rule, ReadingValue $value, User $actor): void
    {
        $open = DosingAlert::query()->where('dosing_rule_id', $rule->id)->whereIn('status', ['OPEN', 'REMINDER'])->latest()->first();
        $matches = $this->compare((float) $value->value_numeric, $rule->start_operator, (float) $rule->start_value);
        if ($matches && ! $open) {
            $alert = DosingAlert::query()->create(['dosing_rule_id' => $rule->id, 'trigger_reading_value_id' => $value->id, 'status' => 'OPEN', 'trigger_value' => $value->value_numeric, 'rule_snapshot' => $rule->only(['stable_key', 'version', 'name', 'product_name', 'equipment_id', 'start_operator', 'start_value', 'stop_operator', 'stop_value', 'reminder_after_minutes', 'recheck_after_minutes']), 'detected_at' => now()]);
            $this->audit->record($alert, 'dosing.condition_detected', $actor, [], $alert->only(['trigger_value', 'status', 'detected_at']));
        } elseif (! $matches && $open) {
            $open->update(['status' => 'RESOLVED_WITHOUT_DOSING', 'resolved_reading_value_id' => $value->id, 'resolved_at' => now()]);
            $this->audit->record($open, 'dosing.resolved_without_cycle', $actor, ['status' => 'OPEN'], ['status' => 'RESOLVED_WITHOUT_DOSING', 'resolved_reading_value_id' => $value->id]);
        }
    }

    private function evaluateConsistency(DosingRule $rule, ReadingValue $value, User $actor): void
    {
        $points = $rule->parameterRule()->with('points')->firstOrFail()->points->where('is_active', true)->sortBy('sort_order');
        if ($points->count() < 2 || ! $rule->parameter_rule_point_id) {
            return;
        }
        $values = ReadingValue::query()->where('reading_section_id', $value->reading_section_id)->where('parameter_rule_id', $rule->parameter_rule_id)->whereIn('parameter_rule_point_id', $points->pluck('id'))->where('semantic_status', 'MEASURED')->whereNotNull('value_numeric')->get()->keyBy('parameter_rule_point_id');
        if ($values->count() !== $points->count()) {
            return;
        }

        $snapshot = $points->map(fn ($point): array => ['point_id' => $point->id, 'stable_key' => $point->stable_key, 'label' => $point->label, 'value' => (float) $values[$point->id]->value_numeric])->values();
        $numbers = $snapshot->pluck('value');
        $minimum = (float) $numbers->min();
        $maximum = (float) $numbers->max();
        $average = (float) $numbers->avg();
        $spread = $maximum - $minimum;
        $suspected = $snapshot->sortByDesc(fn (array $item): float => abs($item['value'] - $average))->first();
        $hash = hash('sha256', $snapshot->toJson());
        $check = DosingConsistencyCheck::query()->firstOrCreate(
            ['dosing_rule_id' => $rule->id, 'reading_section_id' => $value->reading_section_id, 'values_hash' => $hash],
            ['control_point_id' => $rule->parameter_rule_point_id, 'suspected_point_id' => $suspected['point_id'] ?? null, 'minimum_value' => $minimum, 'maximum_value' => $maximum, 'average_value' => $average, 'spread_value' => $spread, 'configured_max_spread' => $rule->consistency_max_spread, 'is_divergent' => $rule->consistency_max_spread !== null && $spread > (float) $rule->consistency_max_spread, 'values_snapshot' => $snapshot->all(), 'evaluated_at' => now(), 'evaluated_by' => $actor->id],
        );
        if ($check->wasRecentlyCreated) {
            $this->audit->record($check, $check->is_divergent ? 'dosing.consistency_divergence_detected' : 'dosing.consistency_evaluated', $actor, [], $check->only(['control_point_id', 'minimum_value', 'maximum_value', 'average_value', 'spread_value', 'configured_max_spread', 'is_divergent', 'values_snapshot']));
        }
    }

    private function compare(float $actual, string $operator, float $configured): bool
    {
        return match ($operator) {
            'LESS_THAN' => $actual < $configured,
            'LESS_THAN_OR_EQUAL' => $actual <= $configured,
            'GREATER_THAN' => $actual > $configured,
            'GREATER_THAN_OR_EQUAL' => $actual >= $configured,
            'EQUAL' => $actual === $configured,
            default => false,
        };
    }

    private function ensureActive(DosingCycle $cycle): void
    {
        if ($cycle->status !== 'ACTIVE') {
            throw ValidationException::withMessages(['cycle' => 'Este ciclo de dosagem não está ativo.']);
        }
    }
}
