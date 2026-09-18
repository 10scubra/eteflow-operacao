<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class StoreChemicalInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(PermissionService::class)->allows($this->user(), 'chemical_stock.inventory');
    }

    public function rules(): array
    {
        return ['chemical_storage_location_id' => ['required', 'integer', 'exists:chemical_storage_locations,id'], 'physical_quantity' => ['required', 'numeric', 'min:0'], 'reason' => ['nullable', 'string', 'max:2000'], 'observation' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['required', 'uuid']];
    }
}
