<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReadingSectionDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('readings.configure') ?? false;
    }

    public function rules(): array
    {
        return ['section_key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'], 'label' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'sort_order' => ['required', 'integer', 'min:0', 'max:65535'], 'is_active' => ['sometimes', 'boolean']];
    }
}
