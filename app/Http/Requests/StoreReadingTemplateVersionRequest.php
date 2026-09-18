<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReadingTemplateVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('readings.configure') ?? false;
    }

    public function rules(): array
    {
        return ['notes' => ['nullable', 'string', 'max:2000']];
    }
}
