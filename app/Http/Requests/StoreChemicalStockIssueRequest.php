<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class StoreChemicalStockIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(PermissionService::class)->allows($this->user(), 'chemical_stock.issue');
    }

    public function rules(): array
    {
        return ['chemical_storage_location_id' => ['required', 'integer', 'exists:chemical_storage_locations,id'], 'quantity' => ['required', 'numeric', 'gt:0'], 'usage_location' => ['nullable', 'string', 'max:150'], 'reason' => ['nullable', 'string', 'max:500'], 'observation' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['required', 'uuid']];
    }
}
