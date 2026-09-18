<?php

namespace App\Http\Requests;

use App\Services\ReadingDefinitionValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParameterRuleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $points = collect($this->input('points', []))->filter(fn ($point) => filled($point['stable_key'] ?? null) || filled($point['label'] ?? null))->values()->all();
        $options = collect(preg_split('/[\r\n,;]+/', (string) $this->input('options_text', ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $option): string => trim($option))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $visibility = $this->input('visibility_config');
        if (! is_array($visibility) || blank($visibility['field_key'] ?? null)) {
            $visibility = null;
        } elseif (($visibility['operator'] ?? null) === 'EQUALS') {
            $visibility['value'] = filter_var($visibility['value'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        $frequencyConfig = match ($this->input('frequency_type')) {
            'SPECIFIC_TIMES' => ['times' => collect(preg_split('/[\s,;]+/', (string) $this->input('frequency_times_text', ''), -1, PREG_SPLIT_NO_EMPTY))->map(fn (string $time): string => trim($time))->unique()->values()->all()],
            'EVERY_N_HOURS' => ['hours' => (int) $this->input('frequency_interval_hours')],
            'SPECIFIC_DAYS' => ['days' => collect($this->input('frequency_days', []))->map(fn ($day): int => (int) $day)->values()->all()],
            default => [],
        };
        $this->merge(['points' => $points, 'options' => $options, 'visibility_config' => $visibility, 'frequency_config' => $frequencyConfig]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('readings.configure') ?? false;
    }

    public function rules(): array
    {
        return [
            'field_key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'], 'label' => ['required', 'string', 'max:140'],
            'equipment_id' => ['nullable', 'integer', 'exists:equipment,id'],
            'data_type' => ['required', Rule::in(ReadingDefinitionValidator::DataTypes)], 'description' => ['nullable', 'string', 'max:2000'], 'unit' => ['nullable', 'string', 'max:30'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'], 'minimum_value' => ['nullable', 'numeric'], 'maximum_value' => ['nullable', 'numeric', 'gte:minimum_value'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'], 'condition_operator' => ['required', Rule::in(ReadingDefinitionValidator::ConditionOperators)], 'reference_value' => ['nullable', 'numeric'],
            'options' => ['nullable', 'array'], 'options.*' => ['string', 'max:100'], 'frequency_type' => ['required', Rule::in(ReadingDefinitionValidator::FrequencyTypes)], 'frequency_config' => ['nullable', 'array'],
            'frequency_times_text' => ['nullable', 'string', 'max:1000'], 'frequency_interval_hours' => ['nullable', 'integer', 'min:1', 'max:24'], 'frequency_days' => ['nullable', 'array'], 'frequency_days.*' => ['integer', 'between:1,7'],
            'options_text' => ['nullable', 'string', 'max:5000'],
            'visibility_config' => ['nullable', 'array'], 'visibility_config.field_key' => ['required_with:visibility_config', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'visibility_config.operator' => ['required_with:visibility_config', Rule::in(['EQUALS'])], 'visibility_config.value' => ['required_with:visibility_config'],
            'operational_rule' => ['nullable', 'string', 'max:3000'], 'is_required' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'],
            'points' => ['nullable', 'array'], 'points.*.stable_key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', 'distinct'], 'points.*.label' => ['required', 'string', 'max:140'],
            'points.*.sort_order' => ['required', 'integer', 'min:0', 'max:65535'], 'points.*.instruction' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
