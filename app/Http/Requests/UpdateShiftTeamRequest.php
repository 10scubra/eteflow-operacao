<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true && $this->user()?->role === 'master';
    }

    public function rules(): array
    {
        return [
            'operator_ids' => ['required', 'array', 'min:1'],
            'operator_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'operator')
                    ->where('is_active', true)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'operator_ids.required' => 'Selecione ao menos um operador para o turno.',
            'operator_ids.min' => 'Selecione ao menos um operador para o turno.',
            'operator_ids.*.exists' => 'Um dos operadores selecionados não está ativo.',
        ];
    }
}
