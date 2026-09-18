<?php

namespace App\Http\Requests;

use App\Services\ReadingDefinitionValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveReadingSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active && in_array($this->user()?->role, ['master', 'operator'], true);
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['in_progress', 'completed'])],
            'reason' => ['nullable', 'string', 'max:255'],
            'values' => ['required', 'array', 'min:1'],
            'values.*.field_key' => ['required', 'string', 'max:80'],
            'values.*.parameter_rule_id' => ['nullable', 'integer', 'exists:parameter_rules,id'],
            'values.*.parameter_rule_point_id' => ['nullable', 'integer', 'exists:parameter_rule_points,id'],
            'values.*.semantic_status' => ['nullable', Rule::in(ReadingDefinitionValidator::SemanticStatuses)],
            'values.*.value_text' => ['nullable', 'string', 'max:5000'],
            'values.*.value_numeric' => ['nullable', 'numeric'],
            'values.*.value_boolean' => ['nullable', 'boolean'],
            'values.*.value_json' => ['nullable', 'array'],
            'values.*.equipment_state' => ['nullable', Rule::in(ReadingDefinitionValidator::EquipmentStates)],
            'values.*.justification' => ['nullable', 'string', 'max:2000'],
            'values.*.unit' => ['nullable', 'string', 'max:30'],
        ];
    }
}
