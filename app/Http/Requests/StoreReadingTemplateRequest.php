<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReadingTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('readings.configure') ?? false;
    }

    public function rules(): array
    {
        return ['stable_key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('reading_templates')], 'name' => ['required', 'string', 'max:140'], 'description' => ['nullable', 'string', 'max:2000']];
    }
}
