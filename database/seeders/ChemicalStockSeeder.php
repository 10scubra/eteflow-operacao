<?php

namespace Database\Seeders;

use App\Models\ChemicalProduct;
use App\Models\ChemicalStorageLocation;
use App\Models\ChemicalUnit;
use Illuminate\Database\Seeder;

class ChemicalStockSeeder extends Seeder
{
    public function run(): void
    {
        $units = collect([['L', 'Litros', 'L', 3], ['KG', 'Quilogramas', 'kg', 3], ['BAG', 'Sacos', 'sacos', 0], ['UNIT', 'Unidades', 'un.', 0]])->mapWithKeys(function ($u) {
            $m = ChemicalUnit::updateOrCreate(['code' => $u[0]], ['name' => $u[1], 'symbol' => $u[2], 'decimal_places' => $u[3], 'is_active' => true]);

            return [$u[0] => $m];
        });
        $units['BAG']->update(['is_closed_package' => true, 'equivalent_quantity' => 25, 'equivalent_unit_id' => $units['KG']->id]);
        $catalog = [
            ['polymer', 'Polímero', 'BAG', ['main' => 'Estoque principal']],
            ['calcium-hydroxide', 'Hidróxido de Cálcio', 'L', ['tank-01' => 'Tanque 01', 'tank-02' => 'Tanque 02', 'tank-03' => 'Tanque 03']],
            ['ferric-chloride', 'Cloreto Férrico', 'L', ['main' => 'Estoque principal']],
            ['antifoam', 'Antiespumante', 'L', ['main' => 'Estoque principal']],
            ['sodium-aluminate', 'Aluminato de Sódio', 'L', ['main' => 'Estoque principal']],
        ];
        foreach ($catalog as $pi => $entry) {
            [$key,$name,$unitCode,$locations] = $entry;
            $unit = $units[$unitCode];
            $productData = ['name' => $name, 'unit_id' => $unit->id, 'decimal_places' => $unit->decimal_places, 'is_active' => true, 'sort_order' => $pi + 1];
            if ($key === 'polymer') {
                $productData += ['handling_mode' => ChemicalProduct::HANDLING_WEIGHABLE_PACKAGE, 'package_content_quantity' => 25, 'package_content_unit_id' => $units['KG']->id];
            }
            $product = ChemicalProduct::updateOrCreate(['stable_key' => $key], $productData);
            $li = 0;
            foreach ($locations as $locationKey => $locationName) {
                ChemicalStorageLocation::updateOrCreate(['chemical_product_id' => $product->id, 'stable_key' => $locationKey], ['unit_id' => $unit->id, 'name' => $locationName, 'capacity' => null, 'low_threshold_type' => null, 'low_threshold_value' => null, 'critical_threshold_type' => null, 'critical_threshold_value' => null, 'participates_in_shift_count' => true, 'is_active' => true, 'sort_order' => ++$li]);
            }
        }
    }
}
