<?php

namespace App\Services;

use App\Models\ChemicalInventoryCheck;
use App\Models\ChemicalOpenPackage;
use App\Models\ChemicalOpenPackageWeighing;
use App\Models\ChemicalProduct;
use App\Models\ChemicalStockAlert;
use App\Models\ChemicalStockBalanceSnapshot;
use App\Models\ChemicalStockCorrection;
use App\Models\ChemicalStockCount;
use App\Models\ChemicalStockCountItem;
use App\Models\ChemicalStockMovement;
use App\Models\ChemicalStorageLocation;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChemicalStockService
{
    public function __construct(private AuditService $audit) {}

    public function activeLocations(): Collection
    {
        return ChemicalStorageLocation::query()->where('is_active', true)->where('participates_in_shift_count', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))->with(['product.unit', 'unit'])
            ->orderBy('sort_order')->get()->sortBy(fn ($location) => [$location->product->sort_order, $location->sort_order])->values();
    }

    public function count(Shift $shift, User $user, array $quantities, ?string $observation = null): ChemicalStockCount
    {
        return DB::transaction(function () use ($shift, $user, $quantities, $observation) {
            Shift::query()->lockForUpdate()->findOrFail($shift->id);
            if (ChemicalStockCount::query()->where('shift_id', $shift->id)->exists()) {
                throw ValidationException::withMessages(['count' => 'A conferência deste turno já foi concluída.']);
            }
            $locations = $this->activeLocations();
            if ($locations->isEmpty()) {
                throw ValidationException::withMessages(['count' => 'Nenhum produto está configurado para conferência.']);
            }
            foreach ($locations as $location) {
                if (! array_key_exists((string) $location->id, $quantities) && ! array_key_exists($location->id, $quantities)) {
                    throw ValidationException::withMessages(["quantities.{$location->id}" => 'Informe a quantidade encontrada.']);
                }
                $this->validateQuantity($quantities[$location->id], $location->product->decimal_places, "quantities.{$location->id}");
            }
            $count = ChemicalStockCount::query()->create(['shift_id' => $shift->id, 'counted_by' => $user->id, 'origin_context' => 'SHIFT_START', 'observation' => $observation, 'counted_at' => now()]);
            foreach ($locations as $location) {
                $quantity = round((float) $quantities[$location->id], $location->product->decimal_places);
                $current = $this->balance($location);
                $expected = $current['balance'];
                $item = $count->items()->create([
                    'chemical_product_id' => $location->chemical_product_id, 'chemical_storage_location_id' => $location->id, 'unit_id' => $location->unit_id,
                    'quantity' => $quantity, 'expected_quantity_snapshot' => $expected, 'difference_snapshot' => $expected === null ? null : $quantity - $expected,
                    'configuration_snapshot' => $this->configuration($location),
                ]);
                $this->refresh($location, 'COUNT', $item->id, $user);
            }
            $this->audit->record($count, 'chemical_stock.counted', $user, [], ['shift_id' => $shift->id, 'items' => $count->items()->count(), 'counted_at' => $count->counted_at->toISOString()]);

            return $count->fresh(['items.product', 'items.location']);
        }, 3);
    }

    public function receipt(Shift $shift, User $user, ChemicalStorageLocation $location, array $data): ChemicalStockMovement
    {
        return DB::transaction(function () use ($shift, $user, $location, $data) {
            $location = ChemicalStorageLocation::query()->with(['product', 'unit'])->lockForUpdate()->findOrFail($location->id);
            if (! $location->is_active || ! $location->product->is_active) {
                throw ValidationException::withMessages(['location' => 'Produto ou local inativo.']);
            }
            if ((int) $location->unit_id !== (int) $location->product->unit_id) {
                throw ValidationException::withMessages(['location' => 'A unidade do local é incompatível com o produto.']);
            }
            $this->validateQuantity($data['quantity'], $location->product->decimal_places, 'quantity', false);
            $existing = ChemicalStockMovement::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }
            $movement = ChemicalStockMovement::query()->create([
                'chemical_product_id' => $location->chemical_product_id, 'destination_location_id' => $location->id, 'unit_id' => $location->unit_id, 'shift_id' => $shift->id,
                'type' => 'RECEIPT', 'quantity' => round((float) $data['quantity'], $location->product->decimal_places), 'supplier' => $data['supplier'] ?? null,
                'document' => $data['document'] ?? null, 'observation' => $data['observation'] ?? null, 'status' => 'CONFIRMED', 'idempotency_key' => $data['idempotency_key'],
                'occurred_at' => now(), 'created_by' => $user->id, 'configuration_snapshot' => $this->configuration($location),
            ]);
            $this->refresh($location, 'MOVEMENT', $movement->id, $user);
            $this->audit->record($movement, 'chemical_stock.received', $user, [], $movement->only(['chemical_product_id', 'destination_location_id', 'quantity', 'unit_id', 'shift_id', 'supplier', 'document', 'occurred_at']));

            return $movement->fresh(['product.unit', 'destination']);
        }, 3);
    }

    public function issue(Shift $shift, User $user, ChemicalStorageLocation $location, array $data): ChemicalStockMovement
    {
        return DB::transaction(function () use ($shift, $user, $location, $data) {
            $location = ChemicalStorageLocation::query()->with(['product.packageContentUnit', 'product.unit', 'unit'])->lockForUpdate()->findOrFail($location->id);
            $existing = ChemicalStockMovement::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }
            if (! $location->is_active || ! $location->product->is_active) {
                throw ValidationException::withMessages(['location' => 'Produto ou local inativo.']);
            }
            $places = $location->product->handling_mode === ChemicalProduct::HANDLING_BULK ? $location->product->decimal_places : 0;
            $this->validateQuantity($data['quantity'], $places, 'quantity', false);
            $quantity = round((float) $data['quantity'], $places);
            if ($location->product->handling_mode === ChemicalProduct::HANDLING_WEIGHABLE_PACKAGE && $quantity !== 1.0) {
                throw ValidationException::withMessages(['quantity' => 'Retire um saco por operação para manter a rastreabilidade da pesagem na UAP.']);
            }
            if ($location->product->handling_mode !== ChemicalProduct::HANDLING_BULK && floor($quantity) !== $quantity) {
                throw ValidationException::withMessages(['quantity' => 'Embalagens fechadas devem ser informadas em unidades inteiras.']);
            }
            $balance = $this->balance($location)['balance'];
            if ($balance === null || $quantity > $balance) {
                throw ValidationException::withMessages(['quantity' => 'Saldo insuficiente ou ainda não conferido neste local.']);
            }
            if ($location->product->handling_mode === ChemicalProduct::HANDLING_WEIGHABLE_PACKAGE && (! $location->product->package_content_quantity || ! $location->product->package_content_unit_id)) {
                throw ValidationException::withMessages(['quantity' => 'Configure o conteúdo nominal da embalagem antes da retirada.']);
            }
            $movement = ChemicalStockMovement::query()->create([
                'chemical_product_id' => $location->chemical_product_id, 'source_location_id' => $location->id, 'unit_id' => $location->unit_id, 'shift_id' => $shift->id,
                'type' => 'ISSUE', 'direction' => 'OUT', 'quantity' => $quantity, 'reason' => $data['reason'] ?? 'Uso operacional',
                'observation' => $data['observation'] ?? null, 'status' => 'CONFIRMED', 'idempotency_key' => $data['idempotency_key'],
                'occurred_at' => now(), 'created_by' => $user->id, 'configuration_snapshot' => $this->configuration($location),
            ]);
            if ($location->product->handling_mode === ChemicalProduct::HANDLING_WEIGHABLE_PACKAGE) {
                ChemicalOpenPackage::query()->create([
                    'chemical_product_id' => $location->chemical_product_id, 'source_location_id' => $location->id,
                    'content_unit_id' => $location->product->package_content_unit_id, 'usage_location' => $data['usage_location'] ?? 'UAP',
                    'initial_content' => $location->product->package_content_quantity, 'remaining_content' => $location->product->package_content_quantity,
                    'status' => 'OPEN', 'opened_by' => $user->id, 'opened_at' => now(), 'observation' => $data['observation'] ?? null,
                    'issue_movement_id' => $movement->id, 'configuration_snapshot' => $this->configuration($location),
                ]);
            }
            $this->refresh($location, 'MOVEMENT', $movement->id, $user);
            $this->audit->record($movement, 'chemical_stock.issued', $user, [], $movement->only(['chemical_product_id', 'source_location_id', 'quantity', 'unit_id', 'shift_id', 'reason', 'occurred_at']));

            return $movement->fresh(['product.unit', 'source']);
        }, 3);
    }

    public function weighOpenPackage(ChemicalOpenPackage $package, float $remaining, ?string $observation, User $user): ChemicalOpenPackageWeighing
    {
        return DB::transaction(function () use ($package, $remaining, $observation, $user) {
            $package = ChemicalOpenPackage::query()->with(['product', 'contentUnit'])->lockForUpdate()->findOrFail($package->id);
            if ($package->status !== 'OPEN') {
                throw ValidationException::withMessages(['remaining' => 'Este saco já foi encerrado.']);
            }
            $this->validateQuantity($remaining, $package->contentUnit->decimal_places, 'remaining');
            $previous = (float) $package->remaining_content;
            if ($remaining > $previous) {
                throw ValidationException::withMessages(['remaining' => 'O peso restante não pode ser maior que a pesagem anterior.']);
            }
            $weighing = ChemicalOpenPackageWeighing::query()->create([
                'chemical_open_package_id' => $package->id, 'previous_remaining' => $previous, 'remaining' => $remaining,
                'consumed' => $previous - $remaining, 'weighed_by' => $user->id, 'weighed_at' => now(), 'observation' => $observation,
            ]);
            $package->update(['remaining_content' => $remaining, 'status' => $remaining <= 0 ? 'EMPTY' : 'OPEN', 'last_weighed_by' => $user->id, 'last_weighed_at' => now()]);
            $this->audit->record($weighing, 'chemical_stock.open_package_weighed', $user, ['remaining' => $previous], ['remaining' => $remaining, 'consumed' => $previous - $remaining]);

            return $weighing->fresh(['openPackage.product', 'weigher']);
        }, 3);
    }

    public function transfer(Shift $shift, User $user, ChemicalStorageLocation $source, ChemicalStorageLocation $destination, array $data): ChemicalStockMovement
    {
        return DB::transaction(function () use ($shift, $user, $source, $destination, $data) {
            $ids = collect([$source->id, $destination->id])->sort()->values();
            $locked = ChemicalStorageLocation::query()->with(['product', 'unit'])->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $locked[$source->id] ?? null;
            $destination = $locked[$destination->id] ?? null;
            if (! $source || ! $destination || $source->id === $destination->id || $source->chemical_product_id !== $destination->chemical_product_id || $source->unit_id !== $destination->unit_id) {
                throw ValidationException::withMessages(['destination_location_id' => 'Origem e destino devem ser locais diferentes do mesmo produto e unidade.']);
            }
            $existing = ChemicalStockMovement::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }
            $this->validateQuantity($data['quantity'], $source->product->decimal_places, 'quantity', false);
            $quantity = round((float) $data['quantity'], $source->product->decimal_places);
            $balance = $this->balance($source)['balance'];
            if ($balance === null || $quantity > $balance) {
                throw ValidationException::withMessages(['quantity' => 'Saldo insuficiente ou ainda não conferido na origem.']);
            }
            $movement = ChemicalStockMovement::query()->create([
                'chemical_product_id' => $source->chemical_product_id, 'source_location_id' => $source->id, 'destination_location_id' => $destination->id,
                'unit_id' => $source->unit_id, 'shift_id' => $shift->id, 'type' => 'TRANSFER', 'quantity' => $quantity, 'reason' => $data['reason'] ?? null,
                'observation' => $data['observation'] ?? null, 'status' => 'CONFIRMED', 'idempotency_key' => $data['idempotency_key'],
                'occurred_at' => now(), 'created_by' => $user->id, 'configuration_snapshot' => $this->configuration($source),
            ]);
            $this->refresh($source, 'MOVEMENT', $movement->id, $user);
            $this->refresh($destination, 'MOVEMENT', $movement->id, $user);
            $this->audit->record($movement, 'chemical_stock.transferred', $user, [], $movement->only(['chemical_product_id', 'source_location_id', 'destination_location_id', 'quantity', 'unit_id', 'shift_id', 'occurred_at']));

            return $movement->fresh(['product.unit', 'source', 'destination']);
        }, 3);
    }

    public function inventory(Shift $shift, User $user, ChemicalStorageLocation $location, array $data): ChemicalInventoryCheck
    {
        return DB::transaction(function () use ($shift, $user, $location, $data) {
            $location = ChemicalStorageLocation::query()->with(['product', 'unit'])->lockForUpdate()->findOrFail($location->id);
            $existing = ChemicalInventoryCheck::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }
            $this->validateQuantity($data['physical_quantity'], $location->product->decimal_places, 'physical_quantity');
            $expected = $this->balance($location)['balance'];
            $physical = round((float) $data['physical_quantity'], $location->product->decimal_places);
            $difference = $physical - (float) ($expected ?? 0);
            if (abs($difference) > 0.00005 && blank($data['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'Informe o motivo da divergência encontrada.']);
            }
            $check = ChemicalInventoryCheck::query()->create([
                'chemical_product_id' => $location->chemical_product_id, 'chemical_storage_location_id' => $location->id, 'unit_id' => $location->unit_id,
                'shift_id' => $shift->id, 'expected_quantity' => $expected ?? 0, 'physical_quantity' => $physical, 'difference' => $difference,
                'reason' => $data['reason'] ?? null, 'observation' => $data['observation'] ?? null, 'checked_by' => $user->id, 'checked_at' => now(),
                'idempotency_key' => $data['idempotency_key'], 'configuration_snapshot' => $this->configuration($location),
            ]);
            $this->refresh($location, 'INVENTORY', $check->id, $user);
            $this->audit->record($check, 'chemical_stock.inventory_checked', $user, [], $check->only(['chemical_product_id', 'chemical_storage_location_id', 'expected_quantity', 'physical_quantity', 'difference', 'reason', 'checked_at']));

            return $check->fresh(['product.unit', 'location', 'checker']);
        }, 3);
    }

    public function correct(string $type, int $id, float $quantity, string $reason, string $key, User $user): ChemicalStockCorrection
    {
        return DB::transaction(function () use ($type, $id, $quantity, $reason, $key, $user) {
            $existing = ChemicalStockCorrection::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            $record = $type === 'COUNT_ITEM' ? ChemicalStockCountItem::query()->lockForUpdate()->findOrFail($id) : ChemicalStockMovement::query()->lockForUpdate()->findOrFail($id);
            $location = ChemicalStorageLocation::query()->with('product')->lockForUpdate()->findOrFail($type === 'COUNT_ITEM' ? $record->chemical_storage_location_id : ($record->destination_location_id ?? $record->source_location_id));
            $this->validateQuantity($quantity, $location->product->decimal_places, 'corrected_quantity');
            $correction = ChemicalStockCorrection::query()->create(['record_type' => $type, 'record_id' => $id, 'corrected_quantity' => round($quantity, $location->product->decimal_places), 'reason' => $reason, 'corrected_by' => $user->id, 'corrected_at' => now(), 'idempotency_key' => $key, 'original_snapshot' => $record->attributesToArray(), 'correction_snapshot' => ['quantity' => $quantity, 'reason' => $reason]]);
            $this->refresh($location, 'CORRECTION', $correction->id, $user);
            if ($type === 'MOVEMENT' && $record->source_location_id && $record->source_location_id !== $location->id) {
                $this->refresh(ChemicalStorageLocation::findOrFail($record->source_location_id), 'CORRECTION', $correction->id, $user);
            }
            $this->audit->record($correction, 'chemical_stock.corrected', $user, ['quantity' => $record->quantity], ['record_type' => $type, 'record_id' => $id, 'corrected_quantity' => $quantity, 'reason' => $reason]);

            return $correction;
        }, 3);
    }

    public function balance(ChemicalStorageLocation $location): array
    {
        $countItem = ChemicalStockCountItem::query()->where('chemical_storage_location_id', $location->id)->whereHas('count')
            ->with('count')->get()->sortByDesc(fn ($item) => $item->count->counted_at)->first();
        $inventory = ChemicalInventoryCheck::query()->where('chemical_storage_location_id', $location->id)->latest('checked_at')->first();
        $countAt = $countItem?->count?->counted_at;
        $inventoryAt = $inventory?->checked_at;
        if (! $countItem && ! $inventory) {
            return ['balance' => null, 'percentage' => null, 'status' => 'UNKNOWN', 'reference' => null, 'reference_type' => null, 'reference_at' => null];
        }
        $useInventory = $inventory && (! $countAt || $inventoryAt->greaterThanOrEqualTo($countAt));
        $reference = $useInventory ? $inventory : $countItem;
        $referenceAt = $useInventory ? $inventoryAt : $countAt;
        $balance = $useInventory ? (float) $inventory->physical_quantity : $this->effectiveQuantity('COUNT_ITEM', $countItem->id, (float) $countItem->quantity);
        $movements = ChemicalStockMovement::query()->where('status', 'CONFIRMED')->where('occurred_at', '>=', $referenceAt)
            ->where(fn ($query) => $query->where('source_location_id', $location->id)->orWhere('destination_location_id', $location->id))->get();
        foreach ($movements as $movement) {
            $quantity = $this->effectiveQuantity('MOVEMENT', $movement->id, (float) $movement->quantity);
            if ((int) $movement->destination_location_id === $location->id && in_array($movement->type, ['RECEIPT', 'TRANSFER', 'ADJUSTMENT'], true) && $movement->direction !== 'OUT') {
                $balance += $quantity;
            }
            if ((int) $movement->source_location_id === $location->id && in_array($movement->type, ['ISSUE', 'TRANSFER', 'LOSS', 'ADJUSTMENT'], true) && $movement->direction !== 'IN') {
                $balance -= $quantity;
            }
        }
        $percentage = $location->capacity !== null && (float) $location->capacity > 0 ? ($balance / (float) $location->capacity) * 100 : null;

        return ['balance' => $balance, 'percentage' => $percentage, 'status' => $this->status($location, $balance, $percentage), 'reference' => $reference, 'reference_type' => $useInventory ? 'INVENTORY' : 'COUNT_ITEM', 'reference_at' => $referenceAt];
    }

    public function dashboard(?int $productId = null, ?int $locationId = null, ?string $status = null): Collection
    {
        $locations = ChemicalStorageLocation::query()->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->when($productId, fn ($query) => $query->where('chemical_product_id', $productId))
            ->when($locationId, fn ($query) => $query->whereKey($locationId))
            ->with(['product.unit', 'unit'])->get()
            ->sortBy(fn ($location) => [$location->product->sort_order, $location->sort_order])->values();

        return $locations->map(fn ($location) => ['location' => $location] + $this->balance($location))
            ->when($status, fn ($rows) => $rows->where('status', $status)->values())
            ->groupBy(fn ($row) => $row['location']->chemical_product_id)
            ->map(function ($rows) {
                $product = $rows->first()['location']->product;
                $balances = $rows->pluck('balance');
                $statusOrder = ['UNKNOWN' => 0, 'NORMAL' => 1, 'LOW' => 2, 'CRITICAL' => 3, 'EMPTY' => 4];
                $status = $rows->sortByDesc(fn ($row) => $statusOrder[$row['status']])->first()['status'];
                $allCapacity = $rows->every(fn ($row) => $row['location']->capacity !== null);
                $capacity = $allCapacity ? $rows->sum(fn ($row) => (float) $row['location']->capacity) : null;

                return [
                    'product' => $product,
                    'locations' => $rows->values(),
                    'balance' => $balances->contains(null) ? null : $balances->sum(),
                    'capacity' => $capacity,
                    'percentage' => $capacity && ! $balances->contains(null) ? $balances->sum() / $capacity * 100 : null,
                    'status' => $status,
                ];
            })->values();
    }

    public function analytics(int $days, Collection $dashboard): array
    {
        $days = max(1, min($days, 90));
        $start = now()->startOfDay()->subDays($days - 1);
        $labels = collect(range($days - 1, 0))->map(fn ($offset) => now()->subDays($offset)->format('d/m'));
        $locationRows = $dashboard->flatMap(fn ($product) => $product['locations'])->values();
        $locations = $locationRows->pluck('location');
        $locationIds = $locations->pluck('id')->all();
        $snapshots = empty($locationIds) ? collect() : ChemicalStockBalanceSnapshot::query()
            ->whereIn('chemical_storage_location_id', $locationIds)->where('calculated_at', '>=', $start)
            ->orderBy('calculated_at')->get();

        $series = $dashboard->map(function ($productRow) use ($snapshots, $labels) {
            $ids = $productRow['locations']->pluck('location.id');
            $byDay = $snapshots->whereIn('chemical_storage_location_id', $ids)
                ->groupBy(fn ($snapshot) => $snapshot->calculated_at->format('d/m'))
                ->map(fn ($items) => round((float) $items->groupBy('chemical_storage_location_id')
                    ->map(fn ($locationItems) => (float) $locationItems->last()->percentage)->avg(), 1));

            return ['name' => $productRow['product']->name, 'values' => $labels->map(fn ($label) => $byDay->get($label))->all()];
        })->filter(fn ($row) => collect($row['values'])->contains(fn ($value) => $value !== null))->values();

        $movements = empty($locationIds) ? collect() : ChemicalStockMovement::query()->where('status', 'CONFIRMED')
            ->where('occurred_at', '>=', $start)
            ->where(fn ($query) => $query->whereIn('source_location_id', $locationIds)->orWhereIn('destination_location_id', $locationIds))
            ->get();
        $receiptsByDay = $movements->where('type', 'RECEIPT')->groupBy(fn ($movement) => $movement->occurred_at->format('d/m'))->map->count();

        $table = $locationRows->map(function ($row) use ($start, $snapshots) {
            $location = $row['location'];
            $consumption = $this->consumptionForLocation($location, $start);
            $history = $snapshots->where('chemical_storage_location_id', $location->id)
                ->groupBy(fn ($snapshot) => $snapshot->calculated_at->format('Y-m-d'))
                ->map(fn ($items) => $items->last()->percentage ?? $items->last()->balance)->values()->take(-12)->map(fn ($value) => (float) $value)->all();

            return $row + [
                'consumption' => $consumption['consumption'],
                'divergence_gain' => $consumption['divergence_gain'],
                'consumption_percentage' => $location->capacity && $consumption['consumption'] !== null ? $consumption['consumption'] / (float) $location->capacity * 100 : null,
                'last_count_at' => $row['reference']?->count?->counted_at,
                'trend' => $history,
            ];
        });

        $consumption = $table->groupBy('location.chemical_product_id')->map(function ($rows) {
            $product = $rows->first()['location']->product;
            $amount = $rows->sum(fn ($row) => (float) ($row['consumption'] ?? 0));
            $divergence = $rows->sum(fn ($row) => (float) ($row['divergence_gain'] ?? 0));
            $hasCapacity = $rows->every(fn ($row) => $row['location']->capacity !== null && (float) $row['location']->capacity > 0);
            $capacity = $hasCapacity ? $rows->sum(fn ($row) => (float) $row['location']->capacity) : null;

            return ['product' => $product, 'amount' => $amount, 'divergence_gain' => $divergence, 'percentage' => $capacity ? $amount / $capacity * 100 : null];
        })->values();

        $thresholdValues = $locations->filter(fn ($location) => $location->low_threshold_type === 'PERCENTAGE' && $location->critical_threshold_type === 'PERCENTAGE');
        $lowValues = $thresholdValues->pluck('low_threshold_value')->filter()->unique();
        $criticalValues = $thresholdValues->pluck('critical_threshold_value')->filter()->unique();

        return [
            'labels' => $labels->all(),
            'series' => $series->all(),
            'receipts' => $labels->map(fn ($label) => $receiptsByDay->get($label, 0))->all(),
            'receipt_count' => $movements->where('type', 'RECEIPT')->count(),
            'count_count' => empty($locationIds) ? 0 : ChemicalStockCount::query()->where('counted_at', '>=', $start)
                ->whereHas('items', fn ($query) => $query->whereIn('chemical_storage_location_id', $locationIds))->count(),
            'last_update' => $snapshots->max('calculated_at'),
            'is_demo' => Shift::query()->where('notes', '[DEMO ESTOQUE]')->exists(),
            'table' => $table,
            'consumption' => $consumption,
            'thresholds' => [
                'low' => $lowValues->count() === 1 ? (float) $lowValues->first() : null,
                'critical' => $criticalValues->count() === 1 ? (float) $criticalValues->first() : null,
            ],
        ];
    }

    private function consumptionForLocation(ChemicalStorageLocation $location, $start): array
    {
        $items = ChemicalStockCountItem::query()->where('chemical_storage_location_id', $location->id)
            ->whereHas('count', fn ($query) => $query->where('counted_at', '<=', now()))
            ->with('count')->get()->sortBy(fn ($item) => $item->count->counted_at)->values();
        $firstInside = $items->search(fn ($item) => $item->count->counted_at->greaterThanOrEqualTo($start));
        if ($firstInside === false || $items->count() < 2) {
            return ['consumption' => null, 'divergence_gain' => null];
        }
        $items = $items->slice(max(0, $firstInside - 1))->values();
        $consumption = 0.0;
        $divergence = 0.0;
        $intervals = 0;
        for ($index = 1; $index < $items->count(); $index++) {
            $previous = $items[$index - 1];
            $current = $items[$index];
            if ($current->count->counted_at->lessThan($start)) {
                continue;
            }
            $available = $this->effectiveQuantity('COUNT_ITEM', $previous->id, (float) $previous->quantity);
            $movements = ChemicalStockMovement::query()->where('status', 'CONFIRMED')
                ->where('occurred_at', '>', $previous->count->counted_at)->where('occurred_at', '<=', $current->count->counted_at)
                ->where(fn ($query) => $query->where('source_location_id', $location->id)->orWhere('destination_location_id', $location->id))->get();
            foreach ($movements as $movement) {
                $quantity = $this->effectiveQuantity('MOVEMENT', $movement->id, (float) $movement->quantity);
                if ((int) $movement->destination_location_id === $location->id && in_array($movement->type, ['RECEIPT', 'TRANSFER', 'ADJUSTMENT'], true) && $movement->direction !== 'OUT') {
                    $available += $quantity;
                }
                if ((int) $movement->source_location_id === $location->id && in_array($movement->type, ['ISSUE', 'TRANSFER', 'LOSS', 'ADJUSTMENT'], true) && $movement->direction !== 'IN') {
                    $available -= $quantity;
                }
            }
            $difference = $available - $this->effectiveQuantity('COUNT_ITEM', $current->id, (float) $current->quantity);
            $difference >= 0 ? $consumption += $difference : $divergence += abs($difference);
            $intervals++;
        }

        return ['consumption' => $intervals ? $consumption : null, 'divergence_gain' => $intervals ? $divergence : null];
    }

    public function configurationChanged(ChemicalStorageLocation $location, User $actor): ChemicalStockBalanceSnapshot
    {
        return DB::transaction(fn () => $this->refresh(ChemicalStorageLocation::query()->lockForUpdate()->findOrFail($location->id), 'CONFIGURATION', $location->id, $actor), 3);
    }

    private function refresh(ChemicalStorageLocation $location, string $triggerType, ?int $triggerId, ?User $actor): ChemicalStockBalanceSnapshot
    {
        $location->refresh();
        $result = $this->balance($location);
        $snapshot = ChemicalStockBalanceSnapshot::query()->create(['chemical_product_id' => $location->chemical_product_id, 'chemical_storage_location_id' => $location->id, 'reference_count_item_id' => $result['reference_type'] === 'COUNT_ITEM' ? $result['reference']?->id : null, 'trigger_type' => $triggerType, 'trigger_id' => $triggerId, 'balance' => $result['balance'], 'percentage' => $result['percentage'], 'status' => $result['status'], 'configuration_snapshot' => $this->configuration($location), 'calculated_at' => now()]);
        $open = ChemicalStockAlert::query()->where('chemical_storage_location_id', $location->id)->where('status', 'OPEN')->lockForUpdate()->first();
        if (in_array($result['status'], ['LOW', 'CRITICAL', 'EMPTY'], true)) {
            if ($open && $open->severity !== $result['status']) {
                $open->update(['status' => 'RESOLVED', 'resolved_at' => now(), 'resolved_by' => $actor?->id]);
                $this->audit->record($open, 'chemical_stock.alert_resolved', $actor, [], ['resolved_at' => now()->toISOString(), 'reason' => 'severity_changed']);
                $open = null;
            }
            if (! $open) {
                $open = ChemicalStockAlert::query()->create(['chemical_product_id' => $location->chemical_product_id, 'chemical_storage_location_id' => $location->id, 'chemical_stock_balance_snapshot_id' => $snapshot->id, 'severity' => $result['status'], 'status' => 'OPEN', 'balance_snapshot' => $result['balance'], 'threshold_snapshot' => $this->configuration($location), 'opened_at' => now()]);
                $this->audit->record($open, 'chemical_stock.alert_opened', $actor, [], ['severity' => $result['status'], 'balance' => $result['balance']]);
            }
        } elseif ($open) {
            $open->update(['status' => 'RESOLVED', 'resolved_at' => now(), 'resolved_by' => $actor?->id]);
            $this->audit->record($open, 'chemical_stock.alert_resolved', $actor, [], ['resolved_at' => now()->toISOString()]);
        }

        return $snapshot;
    }

    private function effectiveQuantity(string $type, int $id, float $original): float
    {
        return (float) (ChemicalStockCorrection::query()->where('record_type', $type)->where('record_id', $id)->latest('corrected_at')->value('corrected_quantity') ?? $original);
    }

    private function status(ChemicalStorageLocation $location, float $balance, ?float $percentage): string
    {
        if ($balance <= 0) {
            return 'EMPTY';
        } $critical = $this->thresholdReached($location->critical_threshold_type, $location->critical_threshold_value, $balance, $percentage);
        if ($critical) {
            return 'CRITICAL';
        }

        return $this->thresholdReached($location->low_threshold_type, $location->low_threshold_value, $balance, $percentage) ? 'LOW' : 'NORMAL';
    }

    private function thresholdReached(?string $type, mixed $value, float $balance, ?float $percentage): bool
    {
        if ($type === null || $value === null) {
            return false;
        }

        return $type === 'PERCENTAGE' ? $percentage !== null && $percentage <= (float) $value : $balance <= (float) $value;
    }

    private function configuration(ChemicalStorageLocation $location): array
    {
        return ['product_id' => $location->chemical_product_id, 'location_id' => $location->id, 'unit_id' => $location->unit_id, 'capacity' => $location->capacity, 'low_threshold_type' => $location->low_threshold_type, 'low_threshold_value' => $location->low_threshold_value, 'critical_threshold_type' => $location->critical_threshold_type, 'critical_threshold_value' => $location->critical_threshold_value, 'decimal_places' => $location->product->decimal_places];
    }

    private function validateQuantity(mixed $value, int $places, string $field, bool $zeroAllowed = true): void
    {
        if ($value === null || $value === '' || ! is_numeric($value) || (float) $value < 0 || (! $zeroAllowed && (float) $value <= 0)) {
            throw ValidationException::withMessages([$field => 'Informe uma quantidade válida e não negativa.']);
        } $parts = explode('.', str_replace(',', '.', (string) $value), 2);
        if (isset($parts[1]) && strlen(rtrim($parts[1], '0')) > $places) {
            throw ValidationException::withMessages([$field => "Use no máximo {$places} casas decimais."]);
        }
    }
}
