<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePublicAspersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['start', 'end'])],
            'totalizer' => ['required', 'numeric', 'min:0'],
            'flow_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'active_cannons' => ['required', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'totalizer' => 'leitura do totalizador',
            'flow_rate' => 'vazão atual',
            'active_cannons' => 'quantidade de canhões ativos',
            'notes' => 'observação',
        ];
    }
}
