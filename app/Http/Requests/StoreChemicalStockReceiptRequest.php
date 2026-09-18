<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class StoreChemicalStockReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(PermissionService::class)->allows($this->user(), 'chemical_stock.receive');
    }

    public function rules(): array
    {
        return ['chemical_storage_location_id' => ['required', 'integer', 'exists:chemical_storage_locations,id'], 'quantity' => ['required', 'numeric', 'gt:0'], 'supplier' => ['nullable', 'string', 'max:255'], 'document' => ['nullable', 'string', 'max:255'], 'observation' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['required', 'uuid']];
    }
}
