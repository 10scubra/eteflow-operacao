<?php

namespace App\Http\Requests;

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
            'values.*.field_key' => ['required', 'string', 'max:80', 'distinct'],
            'values.*.value_text' => ['nullable', 'string', 'max:5000'],
            'values.*.value_numeric' => ['nullable', 'numeric'],
            'values.*.unit' => ['nullable', 'string', 'max:30'],
        ];
    }
}
