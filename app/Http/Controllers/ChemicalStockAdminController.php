<?php

namespace App\Http\Controllers;

use App\Models\ChemicalProduct;
use App\Models\ChemicalStorageLocation;
use App\Models\ChemicalUnit;
use App\Services\AuditService;
use App\Services\ChemicalStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChemicalStockAdminController extends Controller
{
    public function index(): View
    {
        Gate::authorize('chemical_stock.configure');

        return view('admin.chemical-stock.index', ['products' => ChemicalProduct::with(['unit', 'packageContentUnit', 'locations.unit'])->orderBy('sort_order')->get(), 'units' => ChemicalUnit::where('is_active', true)->orderBy('name')->get()]);
    }

    public function unit(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('chemical_stock.configure');
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:20', 'unique:chemical_units,code'], 'name' => ['required', 'string', 'max:80'], 'symbol' => ['required', 'string', 'max:20'], 'decimal_places' => ['required', 'integer', 'between:0,4'], 'is_closed_package' => ['nullable', 'boolean'], 'equivalent_quantity' => ['nullable', 'numeric', 'gt:0'], 'equivalent_unit_id' => ['nullable', 'exists:chemical_units,id']]);
        $data['is_closed_package'] = $request->boolean('is_closed_package');
        $unit = ChemicalUnit::query()->create($data + ['is_active' => true]);
        $audit->record($unit, 'chemical_stock.unit_created', $request->user(), [], $unit->attributesToArray());

        return back()->with('success', 'Unidade cadastrada.');
    }

    public function product(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('chemical_stock.configure');
        $data = $request->validate(['stable_key' => ['required', 'alpha_dash', 'max:100', 'unique:chemical_products,stable_key'], 'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string'], 'unit_id' => ['required', 'exists:chemical_units,id'], 'decimal_places' => ['required', 'integer', 'between:0,4'], 'handling_mode' => ['required', Rule::in(['BULK', 'WEIGHABLE_PACKAGE', 'SEALED_PACKAGE'])], 'package_content_quantity' => ['nullable', 'required_if:handling_mode,WEIGHABLE_PACKAGE', 'numeric', 'gt:0'], 'package_content_unit_id' => ['nullable', 'required_if:handling_mode,WEIGHABLE_PACKAGE', 'exists:chemical_units,id'], 'sort_order' => ['required', 'integer', 'min:0']]);
        $product = ChemicalProduct::create($data + ['is_active' => true]);
        $audit->record($product, 'chemical_stock.product_created', $request->user(), [], $product->attributesToArray());

        return back()->with('success', 'Produto cadastrado.');
    }

    public function updateProduct(Request $request, ChemicalProduct $product, AuditService $audit): RedirectResponse
    {
        Gate::authorize('chemical_stock.configure');
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string'], 'unit_id' => ['required', 'exists:chemical_units,id'], 'decimal_places' => ['required', 'integer', 'between:0,4'], 'handling_mode' => ['required', Rule::in(['BULK', 'WEIGHABLE_PACKAGE', 'SEALED_PACKAGE'])], 'package_content_quantity' => ['nullable', 'required_if:handling_mode,WEIGHABLE_PACKAGE', 'numeric', 'gt:0'], 'package_content_unit_id' => ['nullable', 'required_if:handling_mode,WEIGHABLE_PACKAGE', 'exists:chemical_units,id'], 'sort_order' => ['required', 'integer', 'min:0'], 'is_active' => ['required', 'boolean']]);
        $old = $product->attributesToArray();
        DB::transaction(function () use ($product, $data) {
            $product->update($data);
            $product->locations()->update(['unit_id' => $data['unit_id']]);
        });
        $audit->record($product, 'chemical_stock.product_updated', $request->user(), $old, $product->fresh()->attributesToArray());

        return back()->with('success', 'Produto atualizado.');
    }

    public function location(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('chemical_stock.configure');
        $data = $request->validate(['chemical_product_id' => ['required', 'exists:chemical_products,id'], 'stable_key' => ['required', 'alpha_dash', 'max:100'], 'name' => ['required', 'string', 'max:150'], 'sort_order' => ['required', 'integer', 'min:0']]);
        $product = ChemicalProduct::findOrFail($data['chemical_product_id']);
        $location = ChemicalStorageLocation::create($data + ['unit_id' => $product->unit_id, 'participates_in_shift_count' => true, 'is_active' => true]);
        $audit->record($location, 'chemical_stock.location_created', $request->user(), [], $location->attributesToArray());

        return back()->with('success', 'Local cadastrado.');
    }

    public function updateLocation(Request $request, ChemicalStorageLocation $location, AuditService $audit, ChemicalStockService $stock): RedirectResponse
    {
        Gate::authorize('chemical_stock.configure');
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'capacity' => ['nullable', 'numeric', 'gt:0'], 'low_threshold_type' => ['nullable', Rule::in(['ABSOLUTE', 'PERCENTAGE'])], 'low_threshold_value' => ['nullable', 'numeric', 'min:0'], 'critical_threshold_type' => ['nullable', Rule::in(['ABSOLUTE', 'PERCENTAGE'])], 'critical_threshold_value' => ['nullable', 'numeric', 'min:0'], 'participates_in_shift_count' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0']]);
        foreach (['low', 'critical'] as $level) {
            if (($data[$level.'_threshold_type'] ?? null) && $data[$level.'_threshold_value'] === null) {
                return back()->withErrors([$level.'_threshold_value' => 'Informe o valor do limite.'])->withInput();
            }if (($data[$level.'_threshold_type'] ?? null) === 'PERCENTAGE' && (float) $data[$level.'_threshold_value'] > 100) {
                return back()->withErrors([$level.'_threshold_value' => 'Percentual deve estar entre 0 e 100.'])->withInput();
            }
        }if (($data['low_threshold_type'] ?? null) && $data['low_threshold_type'] === ($data['critical_threshold_type'] ?? null) && (float) $data['critical_threshold_value'] >= (float) $data['low_threshold_value']) {
            return back()->withErrors(['critical_threshold_value' => 'O limite crítico deve ser inferior ao limite baixo.'])->withInput();
        }$old = $location->attributesToArray();
        $location->update($data);
        $audit->record($location, 'chemical_stock.location_updated', $request->user(), $old, $location->fresh()->attributesToArray());
        $stock->configurationChanged($location, $request->user());

        return back()->with('success', 'Local atualizado; o histórico anterior foi preservado.');
    }
}
