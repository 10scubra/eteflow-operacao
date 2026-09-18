<?php

namespace App\Services;

use App\Models\ParameterRule;
use App\Models\ReadingSection;
use App\Models\ReadingValue;
use App\Models\ReadingValueRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReadingSectionService
{
    public function __construct(
        private OperationalConfigurationService $configuration,
        private AuditService $audit,
        private ReadingDefinitionValidator $definitionValidator,
        private DosingAssistantService $dosingAssistant,
    ) {}

    public function save(ReadingSection $section, User $user, array $payload): ReadingSection
    {
        return DB::transaction(function () use ($section, $user, $payload) {
            $locked = ReadingSection::query()
                ->with('round.shift')
                ->lockForUpdate()
                ->findOrFail($section->id);

            if (! $locked->round->shift->members()->whereKey($user->id)->exists()) {
                throw ValidationException::withMessages(['user' => 'O operador não pertence a este turno.']);
            }

            if ((int) $payload['lock_version'] !== $locked->lock_version) {
                throw new ConflictHttpException('Este bloco foi alterado por outro operador. Atualize os dados antes de salvar.');
            }

            $beforeSection = $locked->only(['status', 'editing_by', 'editing_started_at', 'started_by', 'completed_by', 'lock_version']);

            $rules = $this->configuration->rulesForSection($locked);
            $entries = collect($payload['values']);
            $rulesById = $rules->keyBy('id');
            $unknownFields = $entries->filter(fn (array $entry) => isset($entry['parameter_rule_id']) ? ! $rulesById->has($entry['parameter_rule_id']) : ! $rules->has($entry['field_key']));

            if ($unknownFields->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'values' => 'A leitura contém parâmetro ou ponto não configurado.',
                ]);
            }

            if ($payload['status'] === 'completed') {
                $missingRequired = $rules
                    ->where('is_required', true)
                    ->filter(function (ParameterRule $rule) use ($entries, $locked, $rules): bool {
                        if (! $this->ruleIsApplicable($rule, $rules, $entries, $locked->values)) {
                            return false;
                        }
                        $pointIds = $rule->points()->where('is_active', true)->pluck('id');
                        $slots = $pointIds->isEmpty() ? collect([null]) : $pointIds;

                        return $slots->contains(function (?int $pointId) use ($rule, $entries, $locked): bool {
                            $entry = $entries->first(fn (array $item) => ((int) ($item['parameter_rule_id'] ?? $rule->id)) === $rule->id && (($item['parameter_rule_point_id'] ?? null) === $pointId) && $item['field_key'] === $rule->field_key);
                            if ($entry) {
                                return ! $this->entryIsResolved($entry, $rule);
                            }
                            $persisted = $locked->values->first(fn (ReadingValue $value) => $value->parameter_rule_id === $rule->id && $value->parameter_rule_point_id === $pointId);

                            return ! $persisted || ($rule->visibility_config && $persisted->semantic_status === 'NOT_APPLICABLE') || ! $this->persistedIsResolved($persisted);
                        });
                    })
                    ->pluck('label');

                if ($missingRequired->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'values' => 'Preencha os parâmetros obrigatórios antes de concluir: '.$missingRequired->join(', ').'.',
                    ]);
                }
            }

            foreach ($payload['values'] as $entry) {
                $rule = isset($entry['parameter_rule_id']) ? $rulesById->get($entry['parameter_rule_id']) : $rules->get($entry['field_key']);
                $pointId = $entry['parameter_rule_point_id'] ?? null;
                if ($pointId !== null && ! $rule->points()->whereKey($pointId)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['values' => 'O ponto informado não pertence ao parâmetro configurado.']);
                }
                $semanticStatus = $entry['semantic_status'] ?? ($this->payloadHasValue($entry) ? 'MEASURED' : null);
                $this->validateSemanticEntry($entry, $rule, $semanticStatus, $payload['status']);
                $numeric = array_key_exists('value_numeric', $entry) ? $entry['value_numeric'] : null;
                $outOfRange = $semanticStatus === 'MEASURED' && $this->definitionValidator->isOutOfCondition($numeric, $rule->condition_operator ?? 'BETWEEN', $rule->minimum_value, $rule->maximum_value, $rule->reference_value);

                $identity = ['reading_section_id' => $locked->id, 'parameter_rule_id' => $rule->id, 'parameter_point_slot' => $pointId ?? 0];
                $value = ReadingValue::query()->firstOrNew($identity);
                if (! $value->exists && $pointId === null) {
                    $value = ReadingValue::query()->where('reading_section_id', $locked->id)->where('field_key', $entry['field_key'])->first() ?? $value;
                }
                $previous = $value->exists ? $value->only(['semantic_status', 'value_text', 'value_numeric', 'value_boolean', 'value_json', 'equipment_state', 'justification', 'unit', 'is_out_of_range']) : null;

                $value->fill([
                    'parameter_rule_id' => $rule->id,
                    'parameter_rule_point_id' => $pointId,
                    'parameter_point_slot' => $pointId ?? 0,
                    'field_key' => $rule->field_key,
                    'semantic_status' => $semanticStatus,
                    'value_text' => $entry['value_text'] ?? null,
                    'value_numeric' => $numeric,
                    'value_boolean' => $entry['value_boolean'] ?? null,
                    'value_json' => $entry['value_json'] ?? null,
                    'equipment_state' => $entry['equipment_state'] ?? null,
                    'justification' => $entry['justification'] ?? null,
                    'unit' => $entry['unit'] ?? $rule->unit,
                    'minimum_at_time' => $rule->minimum_value,
                    'maximum_at_time' => $rule->maximum_value,
                    'definition_snapshot' => $this->snapshot($rule, $pointId),
                    'is_out_of_range' => $outOfRange,
                    'recorded_by' => $user->id,
                ])->save();

                $this->dosingAssistant->evaluate($value, $user);

                $current = $value->only(['semantic_status', 'value_text', 'value_numeric', 'value_boolean', 'value_json', 'equipment_state', 'justification', 'unit', 'is_out_of_range']);
                if ($previous !== $current) {
                    ReadingValueRevision::create([
                        'reading_value_id' => $value->id,
                        'previous_value' => $previous,
                        'new_value' => $current,
                        'changed_by' => $user->id,
                        'changed_at' => now(),
                        'reason' => $payload['reason'] ?? null,
                    ]);
                }
            }

            foreach ($rules as $rule) {
                if ($this->ruleIsApplicable($rule, $rules, $entries, $locked->values)) {
                    continue;
                }
                foreach ($locked->values->where('parameter_rule_id', $rule->id) as $value) {
                    if ($value->semantic_status === 'NOT_APPLICABLE') {
                        continue;
                    }
                    $previous = $value->only(['semantic_status', 'value_text', 'value_numeric', 'value_boolean', 'value_json', 'equipment_state', 'justification', 'unit', 'is_out_of_range']);
                    $value->forceFill([
                        'semantic_status' => 'NOT_APPLICABLE',
                        'is_out_of_range' => false,
                        'recorded_by' => $user->id,
                    ])->save();
                    $current = $value->only(['semantic_status', 'value_text', 'value_numeric', 'value_boolean', 'value_json', 'equipment_state', 'justification', 'unit', 'is_out_of_range']);
                    ReadingValueRevision::create([
                        'reading_value_id' => $value->id,
                        'previous_value' => $previous,
                        'new_value' => $current,
                        'changed_by' => $user->id,
                        'changed_at' => now(),
                        'reason' => 'Parâmetro tornou-se não aplicável pela condição configurada.',
                    ]);
                }
            }

            if ($locked->started_at === null) {
                $locked->started_at = now();
                $locked->started_by = $user->id;
            }

            $locked->status = $payload['status'];
            $locked->last_edited_by = $user->id;
            $locked->lock_version++;

            if ($payload['status'] === 'completed') {
                $locked->completed_at = now();
                $locked->completed_by = $user->id;
                $locked->editing_by = null;
                $locked->editing_started_at = null;
            } else {
                $locked->editing_by = $user->id;
                $locked->editing_started_at ??= now();
            }

            $locked->save();

            $sections = $locked->round->sections()->get();
            $roundStatus = $sections->every(fn ($item) => $item->status === 'completed')
                ? 'completed'
                : ($sections->contains(fn ($item) => in_array($item->status, ['in_progress', 'completed', 'reopened'], true)) ? 'in_progress' : 'pending');
            $locked->round->update(['status' => $roundStatus]);

            $this->audit->record(
                $locked,
                'reading_section.saved',
                $user,
                $beforeSection,
                $locked->only(['status', 'editing_by', 'editing_started_at', 'started_by', 'completed_by', 'lock_version']),
            );

            return $locked->load(['values.recordedBy', 'startedBy', 'completedBy', 'lastEditedBy']);
        }, 3);
    }

    private function payloadHasValue(array $entry): bool
    {
        return (array_key_exists('value_numeric', $entry) && $entry['value_numeric'] !== null && $entry['value_numeric'] !== '')
            || (array_key_exists('value_text', $entry) && filled($entry['value_text']));
    }

    private function entryIsResolved(array $entry, ParameterRule $rule): bool
    {
        $status = $entry['semantic_status'] ?? ($this->payloadHasValue($entry) ? 'MEASURED' : null);
        if ($status === 'NOT_MEASURED') {
            return filled($entry['justification'] ?? null);
        }
        if ($status === 'MEASURED') {
            return $this->hasTypedValue($entry, $rule);
        }

        return in_array($status, ['NO_FLOW', 'METER_FAULT', 'EQUIPMENT_STOPPED', 'NOT_APPLICABLE'], true);
    }

    private function persistedIsResolved(ReadingValue $value): bool
    {
        if ($value->semantic_status === 'NOT_MEASURED') {
            return filled($value->justification);
        }
        if ($value->semantic_status === 'MEASURED' || $value->semantic_status === null) {
            return $value->value_numeric !== null || filled($value->value_text) || $value->value_boolean !== null || filled($value->equipment_state);
        }

        return in_array($value->semantic_status, ['NO_FLOW', 'METER_FAULT', 'EQUIPMENT_STOPPED', 'NOT_APPLICABLE'], true);
    }

    private function validateSemanticEntry(array $entry, ParameterRule $rule, ?string $status, string $sectionStatus): void
    {
        if ($status === 'NOT_MEASURED' && blank($entry['justification'] ?? null)) {
            throw ValidationException::withMessages(['values' => $rule->label.': informe a justificativa para não medido.']);
        }
        if ($sectionStatus === 'completed' && $status === 'MEASURED' && ! $this->hasTypedValue($entry, $rule)) {
            throw ValidationException::withMessages(['values' => $rule->label.': informe o valor medido.']);
        }
        if (($rule->data_type === 'equipment_status') && $status === 'MEASURED' && ! in_array($entry['equipment_state'] ?? null, ReadingDefinitionValidator::EquipmentStates, true)) {
            throw ValidationException::withMessages(['values' => $rule->label.': informe o estado do equipamento.']);
        }
    }

    private function hasTypedValue(array $entry, ParameterRule $rule): bool
    {
        return match ($rule->data_type) {
            null => $this->payloadHasValue($entry),
            'boolean' => array_key_exists('value_boolean', $entry) && $entry['value_boolean'] !== null,
            'equipment_status' => filled($entry['equipment_state'] ?? null),
            'text', 'textarea', 'single_select', 'time' => filled($entry['value_text'] ?? null),
            default => array_key_exists('value_numeric', $entry) && $entry['value_numeric'] !== null && $entry['value_numeric'] !== '',
        };
    }

    private function ruleIsApplicable(ParameterRule $rule, $rules, $entries, $persistedValues): bool
    {
        $condition = $rule->visibility_config;
        if (! is_array($condition) || blank($condition['field_key'] ?? null)) {
            return true;
        }
        if (($condition['operator'] ?? null) !== 'EQUALS') {
            return false;
        }
        $controller = $rules->get($condition['field_key']);
        if (! $controller) {
            return false;
        }
        $entry = $entries->last(fn (array $item) => $item['field_key'] === $controller->field_key);
        $actual = $entry
            ? $this->typedValueFromEntry($entry, $controller)
            : $this->typedValueFromPersisted($persistedValues->where('parameter_rule_id', $controller->id)->last(), $controller);

        return $actual === $condition['value'];
    }

    private function typedValueFromEntry(array $entry, ParameterRule $rule): mixed
    {
        return match ($rule->data_type) {
            'boolean' => $entry['value_boolean'] ?? null,
            'integer', 'decimal', 'percentage', 'totalizer' => isset($entry['value_numeric']) ? (float) $entry['value_numeric'] : null,
            default => $entry['value_text'] ?? null,
        };
    }

    private function typedValueFromPersisted(?ReadingValue $value, ParameterRule $rule): mixed
    {
        if (! $value) {
            return null;
        }

        return match ($rule->data_type) {
            'boolean' => $value->value_boolean,
            'integer', 'decimal', 'percentage', 'totalizer' => $value->value_numeric === null ? null : (float) $value->value_numeric,
            default => $value->value_text,
        };
    }

    private function snapshot(ParameterRule $rule, ?int $pointId): array
    {
        $point = $pointId ? $rule->points()->find($pointId) : null;

        return ['parameter_rule_id' => $rule->id, 'equipment_id' => $rule->equipment_id, 'field_key' => $rule->field_key, 'label' => $rule->label, 'data_type' => $rule->data_type, 'unit' => $rule->unit, 'decimal_places' => $rule->decimal_places, 'is_required' => $rule->is_required, 'condition_operator' => $rule->condition_operator, 'minimum' => $rule->minimum_value, 'maximum' => $rule->maximum_value, 'reference' => $rule->reference_value, 'frequency_type' => $rule->frequency_type, 'frequency_config' => $rule->frequency_config, 'options' => $rule->options, 'operational_rule' => $rule->operational_rule, 'visibility_config' => $rule->visibility_config, 'point' => $point?->only(['id', 'stable_key', 'label', 'instruction'])];
    }
}
