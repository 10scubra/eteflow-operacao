<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class StoreChemicalStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(PermissionService::class)->allows($this->user(), 'chemical_stock.count');
    }

    public function rules(): array
    {
        return ['quantities' => ['required', 'array'], 'quantities.*' => ['required', 'numeric', 'min:0'], 'observation' => ['nullable', 'string', 'max:2000']];
    }
}
