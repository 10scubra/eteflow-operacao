<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChemicalInventoryRequest;
use App\Http\Requests\StoreChemicalOpenPackageWeighingRequest;
use App\Http\Requests\StoreChemicalStockCountRequest;
use App\Http\Requests\StoreChemicalStockIssueRequest;
use App\Http\Requests\StoreChemicalStockReceiptRequest;
use App\Http\Requests\StoreChemicalStockTransferRequest;
use App\Models\ChemicalInventoryCheck;
use App\Models\ChemicalOpenPackage;
use App\Models\ChemicalProduct;
use App\Models\ChemicalStockAlert;
use App\Models\ChemicalStockCount;
use App\Models\ChemicalStockMovement;
use App\Models\ChemicalStorageLocation;
use App\Services\ChemicalStockService;
use App\Services\DailyOperationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChemicalStockController extends Controller
{
    public function dashboard(Request $request, ChemicalStockService $stock): View
    {
        Gate::authorize('chemical_stock.view_balance');
        $days = in_array($request->integer('period'), [7, 14, 30, 90], true) ? $request->integer('period') : 14;
        $productId = ChemicalProduct::query()->where('is_active', true)->whereKey($request->integer('product'))->value('id');
        $locationId = ChemicalStorageLocation::query()->where('is_active', true)
            ->when($productId, fn ($query) => $query->where('chemical_product_id', $productId))
            ->whereKey($request->integer('location'))->value('id');
        $status = in_array($request->string('status')->toString(), ['UNKNOWN', 'NORMAL', 'LOW', 'CRITICAL', 'EMPTY'], true)
            ? $request->string('status')->toString() : null;
        $products = $stock->dashboard($productId, $locationId, $status);
        $locationIds = $products->flatMap(fn ($product) => $product['locations']->pluck('location.id'))->all();
        $severityOrder = ['EMPTY' => 0, 'CRITICAL' => 1, 'LOW' => 2];
        $alerts = empty($locationIds) ? collect() : ChemicalStockAlert::query()->where('status', 'OPEN')
            ->whereIn('chemical_storage_location_id', $locationIds)->with(['product', 'location'])->get()
            ->sortBy(fn ($alert) => [$severityOrder[$alert->severity] ?? 9, -$alert->opened_at->timestamp])->values();

        return view('chemical-stock.dashboard', [
            'products' => $products,
            'alerts' => $alerts,
            'analytics' => $stock->analytics($days, $products),
            'period' => $days,
            'filters' => ['product' => $productId, 'location' => $locationId, 'status' => $status],
            'productOptions' => ChemicalProduct::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'locationOptions' => ChemicalStorageLocation::query()->where('is_active', true)
                ->when($productId, fn ($query) => $query->where('chemical_product_id', $productId))
                ->with('product')->orderBy('sort_order')->get(),
        ]);
    }

    public function countForm(Request $request, DailyOperationService $operations, ChemicalStockService $stock): View
    {
        Gate::authorize('chemical_stock.count');
        $shift = $operations->ensure();
        $this->ensureMember($request, $shift->id);
        $count = ChemicalStockCount::where('shift_id', $shift->id)->first();

        return view('chemical-stock.count', compact('shift', 'count') + ['locations' => $count ? collect() : $stock->activeLocations()]);
    }

    public function count(StoreChemicalStockCountRequest $request, DailyOperationService $operations, ChemicalStockService $stock): RedirectResponse
    {
        $shift = $operations->ensure();
        $this->ensureMember($request, $shift->id);
        $count = $stock->count($shift, $request->user(), $request->validated('quantities'), $request->validated('observation'));

        return redirect()->route('chemical-stock.count.form')->with('success', 'Conferência registrada às '.$count->counted_at->format('H:i').'.');
    }

    public function receiptForm(Request $request, DailyOperationService $operations): View
    {
        Gate::authorize('chemical_stock.receive');
        $shift = $operations->ensure();
        $this->ensureMember($request, $shift->id);
        $locations = ChemicalStorageLocation::where('is_active', true)->whereHas('product', fn ($q) => $q->where('is_active', true))->with(['product.unit', 'unit'])->orderBy('sort_order')->get();

        return view('chemical-stock.receipt', compact('shift', 'locations') + ['idempotencyKey' => (string) Str::uuid()]);
    }

    public function receipt(StoreChemicalStockReceiptRequest $request, DailyOperationService $operations, ChemicalStockService $stock): RedirectResponse
    {
        $shift = $operations->ensure();
        $this->ensureMember($request, $shift->id);
        $movement = $stock->receipt($shift, $request->user(), ChemicalStorageLocation::findOrFail($request->integer('chemical_storage_location_id')), $request->validated());

        return redirect()->route('chemical-stock.receipt.form')->with('receipt', ['product' => $movement->product->name, 'location' => $movement->destination->name, 'quantity' => $movement->quantity, 'unit' => $movement->product->unit->symbol, 'time' => $movement->occurred_at->format('H:i')]);
    }

    public function operations(Request $request, DailyOperationService $operations): View
    {
        abort_unless(Gate::allows('chemical_stock.issue') || Gate::allows('chemical_stock.transfer') || Gate::allows('chemical_stock.inventory'), 403);
        $shift = $operations->ensure();
        $this->ensureMember($request, $shift->id);
        $locations = ChemicalStorageLocation::query()->where('is_active', true)->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->with(['product.unit', 'product.packageContentUnit', 'unit'])->orderBy('sort_order')->get();
        $openPackages = ChemicalOpenPackage::query()->where('status', 'OPEN')->with(['product', 'sourceLocation', 'contentUnit', 'opener', 'weighings.weigher'])->latest('opened_at')->get();

        return view('chemical-stock.operations', compact('shift', 'locations', 'openPackages') + ['idempotencyKey' => (string) Str::uuid()]);
    }

    public function issue(StoreChemicalStockIssueRequest $request, DailyOperationService $operations, ChemicalStockService $stock): RedirectResponse
    {
        $shift = $operations->ensure();
        $this->ensureMember($request, $shift->id);
        $movement = $stock->issue($shift, $request->user(), ChemicalStorageLocation::findOrFail($request->integer('chemical_storage_location_id')), $request->validated());

        return redirect()->route('chemical-stock.operations')->with('success', 'Retirada registrada: '.$movement->quantity.' '.$movement->product->unit->symbol.' de '.$movement->product->name.'.');
    }

    public function transfer(StoreChemicalStockTransferRequest $request, DailyOperationService $operations, ChemicalStockService $stock): RedirectResponse
    {
        $shift = $operations->ensure();
        $this->ensureMember($request, $shift->id);
        $stock->transfer($shift, $request->user(), ChemicalStorageLocation::findOrFail($request->integer('source_location_id')), ChemicalStorageLocation::findOrFail($request->integer('destination_location_id')), $request->validated());

        return redirect()->route('chemical-stock.operations')->with('success', 'Transferência registrada com rastreabilidade.');
    }

    public function inventory(StoreChemicalInventoryRequest $request, DailyOperationService $operations, ChemicalStockService $stock): RedirectResponse
    {
        $shift = $operations->ensure();
        $this->ensureMember($request, $shift->id);
        $check = $stock->inventory($shift, $request->user(), ChemicalStorageLocation::findOrFail($request->integer('chemical_storage_location_id')), $request->validated());

        return redirect()->route('chemical-stock.operations')->with('success', 'Inventário registrado. Divergência: '.number_format((float) $check->difference, 3, ',', '.').'.');
    }

    public function weigh(StoreChemicalOpenPackageWeighingRequest $request, ChemicalOpenPackage $openPackage, ChemicalStockService $stock): RedirectResponse
    {
        $weighing = $stock->weighOpenPackage($openPackage, $request->float('remaining'), $request->validated('observation'), $request->user());

        return redirect()->route('chemical-stock.operations')->with('success', 'Pesagem registrada. Consumo desde a última pesagem: '.number_format((float) $weighing->consumed, 3, ',', '.').' kg.');
    }

    public function history(): View
    {
        Gate::authorize('chemical_stock.view_analytics');

        return view('chemical-stock.history', ['counts' => ChemicalStockCount::with(['user', 'shift', 'items.product.unit', 'items.location'])->latest('counted_at')->limit(100)->get(), 'movements' => ChemicalStockMovement::with(['product.unit', 'source', 'destination', 'creator'])->latest('occurred_at')->limit(100)->get(), 'inventories' => ChemicalInventoryCheck::with(['product.unit', 'location', 'checker'])->latest('checked_at')->limit(100)->get(), 'openPackages' => ChemicalOpenPackage::with(['product', 'contentUnit', 'sourceLocation', 'opener', 'lastWeigher', 'weighings.weigher'])->latest('opened_at')->limit(100)->get()]);
    }

    private function ensureMember(Request $request, int $shiftId): void
    {
        abort_unless($request->user()->shifts()->whereKey($shiftId)->exists(), 403);
    }
}
