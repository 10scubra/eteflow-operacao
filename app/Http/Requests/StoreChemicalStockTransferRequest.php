<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class StoreChemicalStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(PermissionService::class)->allows($this->user(), 'chemical_stock.transfer');
    }

    public function rules(): array
    {
        return ['source_location_id' => ['required', 'integer', 'exists:chemical_storage_locations,id', 'different:destination_location_id'], 'destination_location_id' => ['required', 'integer', 'exists:chemical_storage_locations,id'], 'quantity' => ['required', 'numeric', 'gt:0'], 'reason' => ['nullable', 'string', 'max:500'], 'observation' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['required', 'uuid']];
    }
}
