<?php

namespace App\Http\Controllers;

use App\Services\ChemicalStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChemicalStockCorrectionController extends Controller
{
    public function store(Request $request, ChemicalStockService $stock): RedirectResponse
    {
        Gate::authorize('chemical_stock.correct');
        $data = $request->validate(['record_type' => ['required', Rule::in(['COUNT_ITEM', 'MOVEMENT'])], 'record_id' => ['required', 'integer'], 'corrected_quantity' => ['required', 'numeric', 'min:0'], 'reason' => ['required', 'string', 'min:5', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $stock->correct($data['record_type'], $data['record_id'], (float) $data['corrected_quantity'], $data['reason'], $data['idempotency_key'], $request->user());

        return back()->with('success', 'Correção registrada sem apagar o original.');
    }
}
