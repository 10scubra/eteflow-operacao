<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAspersionPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'master';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('aspersion_points', 'name')],
            'location' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome da bomba ou painel', 'location' => 'localização'];
    }
}
