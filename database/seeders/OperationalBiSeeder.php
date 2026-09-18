<?php

namespace Database\Seeders;

use App\Models\DashboardPage;
use App\Models\Indicator;
use App\Models\LaboratoryParameter;
use App\Models\OperationalUnit;
use App\Models\ParameterRule;
use App\Models\SamplingPoint;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OperationalBiSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \LogicException('A associação inicial de unidade exige migração operacional explícita em produção.');
        }

        $unit = OperationalUnit::updateOrCreate(['stable_key' => 'santa_maria'], ['name' => 'Santa Maria', 'timezone' => 'America/Sao_Paulo', 'is_active' => true]);
        foreach (User::query()->get() as $user) {
            $user->operationalUnits()->syncWithoutDetaching([$unit->id => ['is_default' => true]]);
        }
        Shift::query()->whereNull('operational_unit_id')->update(['operational_unit_id' => $unit->id]);

        foreach ([
            ['geral', 'Geral'], ['biologico', 'Biológico'], ['solidos', 'Sólidos'], ['fisico_quimico', 'Físico-Químico'],
            ['nanofiltracao', 'Nanofiltração'], ['consumos', 'Consumos'], ['custos', 'Custos'], ['laboratorio', 'Laboratório'], ['gestao', 'Gestão'],
        ] as $order => [$key, $name]) {
            DashboardPage::updateOrCreate(['operational_unit_id' => $unit->id, 'stable_key' => $key], ['name' => $name, 'slug' => Str::slug($key), 'sort_order' => $order + 1, 'is_active' => true, 'is_shareable' => true]);
        }

        foreach ([['dqo', 'DQO', 'mg/L'], ['fosforo', 'Fósforo', 'mg/L'], ['nitrogenio_amoniacal', 'Nitrogênio Amoniacal', 'mg/L'], ['nitrato', 'Nitrato', 'mg/L']] as $order => [$key, $name, $measure]) {
            LaboratoryParameter::updateOrCreate(['operational_unit_id' => $unit->id, 'stable_key' => $key], ['name' => $name, 'default_unit' => $measure, 'decimal_places' => 2, 'sort_order' => $order + 1, 'is_active' => true]);
        }
        foreach ([['bruto', 'Bruto'], ['anoxico', 'Anóxico'], ['aerobia', 'Aeróbia'], ['entrada_flotador_1', 'Entrada Flotador 1'], ['saida_flotador_1', 'Saída Flotador 1'], ['entrada_nf', 'Entrada NF'], ['saida_nf', 'Saída NF']] as $order => [$key, $name]) {
            SamplingPoint::updateOrCreate(['operational_unit_id' => $unit->id, 'stable_key' => $key], ['name' => $name, 'sort_order' => $order + 1, 'is_active' => true]);
        }

        $biological = DashboardPage::where('operational_unit_id', $unit->id)->where('stable_key', 'biologico')->firstOrFail();
        foreach (ParameterRule::whereIn('field_key', ['aerobic_ph', 'aerobic_od', 'anoxic_ph', 'anoxic_od'])->get() as $order => $rule) {
            Indicator::updateOrCreate(['operational_unit_id' => $unit->id, 'stable_key' => 'operational_'.$rule->field_key], [
                'dashboard_page_id' => $biological->id, 'name' => $rule->label, 'source_type' => 'OPERATIONAL_READING',
                'parameter_rule_id' => $rule->id, 'aggregation' => 'RAW', 'visualization' => 'LINE', 'unit' => $rule->unit,
                'sort_order' => $order + 1, 'is_active' => true, 'is_shareable' => true,
            ]);
        }
        $laboratory = DashboardPage::where('operational_unit_id', $unit->id)->where('stable_key', 'laboratorio')->firstOrFail();
        foreach (LaboratoryParameter::where('operational_unit_id', $unit->id)->get() as $order => $parameter) {
            Indicator::updateOrCreate(['operational_unit_id' => $unit->id, 'stable_key' => 'lab_'.$parameter->stable_key], [
                'dashboard_page_id' => $laboratory->id, 'name' => $parameter->name, 'source_type' => 'LAB_ANALYSIS',
                'laboratory_parameter_id' => $parameter->id, 'aggregation' => 'LATEST', 'visualization' => 'KPI', 'unit' => $parameter->default_unit,
                'sort_order' => $order + 1, 'is_active' => true, 'is_shareable' => true,
            ]);
        }
    }
}
