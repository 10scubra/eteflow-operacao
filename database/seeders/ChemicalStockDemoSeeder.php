<?php

namespace Database\Seeders;

use App\Models\ChemicalStockCount;
use App\Models\ChemicalStockMovement;
use App\Models\ChemicalStorageLocation;
use App\Models\Shift;
use App\Models\User;
use App\Services\ChemicalStockService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ChemicalStockDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ChemicalStockSeeder::class);
        $actor = User::query()->where('role', 'master')->firstOrFail();
        $stock = app(ChemicalStockService::class);
        $locations = ChemicalStorageLocation::query()->with('product')->orderBy('id')->get();
        $configuration = [
            'polymer:main' => [120, 30, 15], 'calcium-hydroxide:tank-01' => [20000, 30, 15],
            'calcium-hydroxide:tank-02' => [20000, 30, 15], 'calcium-hydroxide:tank-03' => [20000, 30, 15],
            'ferric-chloride:main' => [15000, 30, 15], 'antifoam:main' => [5000, 30, 15],
            'sodium-aluminate:main' => [12000, 30, 15],
        ];
        foreach ($locations as $location) {
            [$capacity, $low, $critical] = $configuration[$location->product->stable_key.':'.$location->stable_key];
            $location->update(['capacity' => $capacity, 'low_threshold_type' => 'PERCENTAGE', 'low_threshold_value' => $low, 'critical_threshold_type' => 'PERCENTAGE', 'critical_threshold_value' => $critical]);
        }
        $basePercentages = [78, 75, 72, 68, 64, 61, 57, 72, 69, 65, 61, 58, 54, 50];
        $originalNow = Carbon::now();
        try {
            foreach ($basePercentages as $offset => $basePercentage) {
                $date = $originalNow->copy()->startOfDay()->subDays(count($basePercentages) - 1 - $offset);
                $shift = Shift::query()->firstOrCreate(
                    ['shift_date' => $date->toDateString(), 'starts_at' => '06:00:00', 'notes' => '[DEMO ESTOQUE]'],
                    ['ends_at' => '07:00:00', 'status' => 'closed', 'closed_by' => $actor->id, 'closed_at' => $date->copy()->setTime(7, 0)]
                );
                if (ChemicalStockCount::query()->where('shift_id', $shift->id)->exists()) {
                    continue;
                }
                Carbon::setTestNow($date->copy()->setTime(6, 30));
                $quantities = [];
                foreach ($locations as $index => $location) {
                    $variation = (($index * 7 + $offset * 3) % 13) - 6;
                    $percentage = max(8, min(92, $basePercentage + $variation));
                    $quantities[$location->id] = round((float) $location->capacity * $percentage / 100, $location->product->decimal_places);
                }
                $stock->count($shift, $actor, $quantities, 'DADOS FICTÍCIOS — base demonstrativa do painel');
                if (in_array($offset, [3, 7, 11], true)) {
                    $location = $locations[$offset % $locations->count()];
                    $key = 'demo-receipt-'.$date->format('Ymd').'-'.$location->id;
                    if (! ChemicalStockMovement::query()->where('idempotency_key', $key)->exists()) {
                        Carbon::setTestNow($date->copy()->setTime(15, 20));
                        $stock->receipt($shift, $actor, $location, [
                            'quantity' => $location->product->stable_key === 'polymer' ? 20 : 1800,
                            'supplier' => 'Fornecedor demonstração', 'document' => 'DEMO-'.$date->format('dm'),
                            'observation' => 'DADO FICTÍCIO para validação do dashboard', 'idempotency_key' => $key,
                        ]);
                    }
                }
            }
        } finally {
            Carbon::setTestNow($originalNow);
        }
    }
}
