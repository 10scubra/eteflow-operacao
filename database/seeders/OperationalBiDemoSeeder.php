<?php

namespace Database\Seeders;

use App\Models\DashboardPage;
use App\Models\Indicator;
use App\Models\LaboratoryCollection;
use App\Models\LaboratoryParameter;
use App\Models\LaboratoryResult;
use App\Models\OperationalUnit;
use App\Models\SamplingPoint;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class OperationalBiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \LogicException('Dados demonstrativos não podem ser carregados em produção.');
        }

        if (config('database.connections.'.config('database.default').'.database') !== 'eteflow_test') {
            throw new \LogicException('Esta carga demonstrativa é exclusiva do banco eteflow_test.');
        }

        $unit = OperationalUnit::where('stable_key', 'santa_maria')->firstOrFail();
        $user = User::where('username', 'quimico.teste')->first()
            ?? User::where('username', 'guilhermevargas')->firstOrFail();
        $point = SamplingPoint::where('operational_unit_id', $unit->id)
            ->where('stable_key', 'aerobia')->firstOrFail();
        $parameters = LaboratoryParameter::where('operational_unit_id', $unit->id)
            ->get()->keyBy('stable_key');

        $samples = [
            20 => ['dqo' => 412.0, 'fosforo' => 7.40, 'nitrogenio_amoniacal' => 36.2, 'nitrato' => 3.10],
            17 => ['dqo' => 389.0, 'fosforo' => 6.95, 'nitrogenio_amoniacal' => 33.8, 'nitrato' => 4.25],
            14 => ['dqo' => 365.0, 'fosforo' => 6.20, 'nitrogenio_amoniacal' => 31.1, 'nitrato' => 5.80],
            11 => ['dqo' => 342.0, 'fosforo' => 5.72, 'nitrogenio_amoniacal' => 28.4, 'nitrato' => 7.15],
            8 => ['dqo' => 318.0, 'fosforo' => 5.10, 'nitrogenio_amoniacal' => 26.0, 'nitrato' => 8.75],
            5 => ['dqo' => 296.0, 'fosforo' => 4.65, 'nitrogenio_amoniacal' => 23.7, 'nitrato' => 10.20],
            2 => ['dqo' => 281.0, 'fosforo' => 4.22, 'nitrogenio_amoniacal' => 21.9, 'nitrato' => 11.60],
            0 => ['dqo' => 274.0, 'fosforo' => 4.05, 'nitrogenio_amoniacal' => 20.8, 'nitrato' => 12.10],
        ];

        $now = CarbonImmutable::now($unit->timezone);
        foreach ($samples as $daysAgo => $values) {
            $collectedAt = $now->subDays($daysAgo)->setTime(9, 30);
            $external = in_array($daysAgo, [14, 5], true);
            $reference = 'DEMO-BI-'.$collectedAt->format('Ymd');
            $collection = LaboratoryCollection::updateOrCreate(
                ['operational_unit_id' => $unit->id, 'document_reference' => $reference],
                [
                    'sampling_point_id' => $point->id,
                    'origin_type' => $external ? 'EXTERNAL' : 'INTERNAL',
                    'collected_at' => $collectedAt,
                    'result_received_at' => $external ? $collectedAt->addDay() : $collectedAt,
                    'external_laboratory' => $external ? 'Laboratório Externo — Demonstração' : null,
                    'observation' => '[DEMO BI] Resultado fictício para visualização do painel.',
                    'created_by' => $user->id,
                ]
            );

            foreach ($values as $stableKey => $value) {
                $parameter = $parameters->get($stableKey);
                LaboratoryResult::updateOrCreate(
                    ['laboratory_collection_id' => $collection->id, 'laboratory_parameter_id' => $parameter->id],
                    [
                        'result_value' => $value,
                        'unit' => $parameter->default_unit,
                        'parameter_snapshot' => [
                            'name' => $parameter->name,
                            'unit' => $parameter->default_unit,
                            'decimal_places' => $parameter->decimal_places,
                            'demo' => true,
                        ],
                        'created_by' => $user->id,
                    ]
                );
            }
        }

        $general = DashboardPage::where('operational_unit_id', $unit->id)
            ->where('stable_key', 'geral')->firstOrFail();
        $charts = [
            ['dqo', 'DQO — evolução demonstrativa', 'RAW', 'LINE'],
            ['fosforo', 'Fósforo — média demonstrativa', 'AVERAGE', 'BAR'],
            ['nitrogenio_amoniacal', 'Nitrogênio amoniacal — evolução', 'RAW', 'AREA'],
            ['nitrato', 'Nitrato — resultado mais recente', 'LATEST', 'KPI'],
        ];
        foreach ($charts as $order => [$key, $name, $aggregation, $visualization]) {
            $parameter = $parameters->get($key);
            Indicator::updateOrCreate(
                ['operational_unit_id' => $unit->id, 'stable_key' => 'demo_general_'.$key],
                [
                    'dashboard_page_id' => $general->id,
                    'name' => $name,
                    'description' => 'Indicador alimentado exclusivamente por resultados fictícios identificados como DEMO BI.',
                    'source_type' => 'LAB_ANALYSIS',
                    'laboratory_parameter_id' => $parameter->id,
                    'aggregation' => $aggregation,
                    'visualization' => $visualization,
                    'unit' => $parameter->default_unit,
                    'periodicity' => 'DAILY',
                    'visual_configuration' => ['demo' => true],
                    'sort_order' => $order + 1,
                    'is_active' => true,
                    'is_shareable' => false,
                ]
            );
        }
    }
}
