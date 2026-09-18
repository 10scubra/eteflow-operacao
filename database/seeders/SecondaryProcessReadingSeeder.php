<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\ReadingTemplate;
use App\Models\ReadingTemplateVersion;
use App\Models\User;
use App\Services\ReadingTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SecondaryProcessReadingSeeder extends Seeder
{
    private const BootstrapKey = 'ETAPA_3C_SECONDARY_PROCESS_V1';

    public function run(): void
    {
        $author = User::where('username', 'master.teste')->firstOrFail();
        $equipment = collect([
            ['code' => 'flotator-01', 'name' => 'Flotador 01', 'category' => 'Flotador', 'location' => 'Flotação'],
            ['code' => 'flotator-02', 'name' => 'Flotador 02', 'category' => 'Flotador', 'location' => 'Flotação'],
            ['code' => 'polymer-pump-uap-01', 'name' => 'Bomba dosadora de polímero UAP 1', 'category' => 'Bomba dosadora', 'location' => 'Tubulação do Flotador 01'],
            ['code' => 'polymer-pump-uap-02', 'name' => 'Bomba dosadora de polímero UAP 2', 'category' => 'Bomba dosadora', 'location' => 'Tubulação do Flotador 02'],
            ['code' => 'decanter', 'name' => 'Decanter', 'category' => 'Decanter', 'location' => 'Processo'],
        ])->mapWithKeys(function (array $attributes): array {
            $model = Equipment::query()->updateOrCreate(
                ['code' => $attributes['code']],
                [...$attributes, 'is_active' => true],
            );

            return [$attributes['code'] => $model];
        });

        DB::transaction(function () use ($author, $equipment): void {
            $template = ReadingTemplate::where('stable_key', 'daily_field_monitoring_ete')->lockForUpdate()->firstOrFail();
            if ($template->versions()->where('notes', self::BootstrapKey)->exists()) {
                return;
            }
            $source = $template->versions()->where('status', ReadingTemplateVersion::Published)->latest('version')->firstOrFail();
            $version = app(ReadingTemplateService::class)->duplicate($source, $author);
            $version->update(['notes' => self::BootstrapKey]);

            foreach ($this->sections($equipment) as $sectionData) {
                $parameters = $sectionData['parameters'];
                unset($sectionData['parameters']);
                $section = $version->sections()->create([
                    ...$sectionData,
                    'version' => $version->version,
                    'is_active' => true,
                    'effective_from' => now(),
                ]);
                foreach ($parameters as $parameter) {
                    $section->parameterRules()->create([
                        ...$parameter,
                        'section_key' => $section->section_key,
                        'version' => $version->version,
                        'is_active' => true,
                        'effective_from' => now(),
                    ]);
                }
            }

            app(ReadingTemplateService::class)->publish($version, $author);
        }, 3);
    }

    private function sections($equipment): array
    {
        $specificTimes = ['times' => ['09:00', '12:00', '21:00', '00:00']];

        return [
            ['section_key' => 'flotator_01', 'label' => 'Flotador 01', 'description' => 'Controle operacional do Flotador 01.', 'sort_order' => 3, 'parameters' => [
                $this->parameter('flotator_01_flow', 'Vazão', 'decimal', 1, 'm³/h', reference: 15, equipmentId: $equipment['flotator-01']->id),
                $this->parameter('flotator_01_feed_pump_command', 'Comando da bomba de alimentação', 'percentage', 2, '%', 0, 100, 'BETWEEN', equipmentId: $equipment['flotator-01']->id),
                $this->parameter('flotator_01_sludge_pump_01_command', 'Comando da bomba de lodo 01', 'percentage', 3, '%', 0, 100, 'BETWEEN', equipmentId: $equipment['flotator-01']->id),
                $this->parameter('flotator_01_uap_01_command', 'Comando da bomba dosadora de polímero UAP 1', 'percentage', 4, '%', 0, 100, 'BETWEEN', equipmentId: $equipment['polymer-pump-uap-01']->id),
                $this->parameter('flotator_01_ph', 'pH', 'decimal', 5, minimum: 6.6, maximum: 7.0, condition: 'BETWEEN', equipmentId: $equipment['flotator-01']->id),
                $this->parameter('flotator_01_sd30_outlet', 'SD30 saída', 'decimal', 6, 'mL/L', reference: 0, equipmentId: $equipment['flotator-01']->id),
            ]],
            ['section_key' => 'flotator_02', 'label' => 'Flotador 02', 'description' => 'Controle operacional do Flotador 02.', 'sort_order' => 4, 'parameters' => [
                $this->parameter('flotator_02_flow', 'Vazão', 'decimal', 1, 'm³/h', reference: 15, equipmentId: $equipment['flotator-02']->id),
                $this->parameter('flotator_02_feed_pump_command', 'Comando da bomba de alimentação', 'percentage', 2, '%', 0, 100, 'BETWEEN', equipmentId: $equipment['flotator-02']->id),
                $this->parameter('flotator_02_return_sludge_pump_command', 'Comando da bomba de retorno de lodo', 'percentage', 3, '%', 0, 100, 'BETWEEN', equipmentId: $equipment['flotator-02']->id),
                $this->parameter('flotator_02_sludge_pump_02_command', 'Comando da bomba de lodo 02', 'percentage', 4, '%', 0, 100, 'BETWEEN', equipmentId: $equipment['flotator-02']->id),
                $this->parameter('flotator_02_uap_02_command', 'Comando da bomba dosadora de polímero UAP 2', 'percentage', 5, '%', 0, 100, 'BETWEEN', equipmentId: $equipment['polymer-pump-uap-02']->id),
                $this->parameter('flotator_02_outlet_ph', 'pH de saída', 'decimal', 6, minimum: 6.5, maximum: 7.0, condition: 'BETWEEN', equipmentId: $equipment['flotator-02']->id),
                $this->parameter('flotator_02_sd30_outlet', 'SD30 saída', 'decimal', 7, 'mL/L', reference: 0, equipmentId: $equipment['flotator-02']->id),
            ]],
            ['section_key' => 'decanter_operation', 'label' => 'Decanter', 'description' => 'Controle operacional confirmado do Decanter.', 'sort_order' => 5, 'parameters' => [
                $this->parameter('decanter_started_at', 'Hora ligado', 'time', 1, equipmentId: $equipment['decanter']->id),
                $this->parameter('decanter_rotation', 'Rotação', 'integer', 2, 'rpm', reference: 1800, equipmentId: $equipment['decanter']->id),
                $this->parameter('decanter_sludge_pump_command', 'Comando da bomba de lodo', 'percentage', 3, '%', 0, 100, 'BETWEEN', equipmentId: $equipment['decanter']->id),
            ]],
            ['section_key' => 'operational_totalizers', 'label' => 'Totalizadores', 'description' => 'Valores brutos acumulados dos totalizadores confirmados.', 'sort_order' => 6, 'parameters' => [
                $this->parameter('totalizer_nf_inlet', 'Entrada NF', 'totalizer', 1, 'm³', frequency: 'SPECIFIC_TIMES', frequencyConfig: $specificTimes),
                $this->parameter('totalizer_flotator_01_feed', 'Alimentação Flotador 01', 'totalizer', 2, 'm³', frequency: 'SPECIFIC_TIMES', frequencyConfig: $specificTimes),
                $this->parameter('totalizer_flotator_02_feed', 'Alimentação Flotador 02', 'totalizer', 3, 'm³', frequency: 'SPECIFIC_TIMES', frequencyConfig: $specificTimes),
                $this->parameter('totalizer_decanter_inlet', 'Entrada Decanter', 'totalizer', 4, 'm³', frequency: 'SPECIFIC_TIMES', frequencyConfig: $specificTimes),
                $this->parameter('totalizer_uap_01_water', 'Água UAP 1', 'totalizer', 5, 'm³', frequency: 'SPECIFIC_TIMES', frequencyConfig: $specificTimes),
                $this->parameter('totalizer_uap_02_water', 'Água UAP 2', 'totalizer', 6, 'm³', frequency: 'SPECIFIC_TIMES', frequencyConfig: $specificTimes),
            ]],
        ];
    }

    private function parameter(string $key, string $label, string $type, int $order, ?string $unit = null, ?float $minimum = null, ?float $maximum = null, string $condition = 'REFERENCE', ?float $reference = null, ?int $equipmentId = null, string $frequency = 'EVERY_ROUND', array $frequencyConfig = []): array
    {
        return ['equipment_id' => $equipmentId, 'field_key' => $key, 'label' => $label, 'data_type' => $type, 'unit' => $unit, 'decimal_places' => $type === 'integer' ? 0 : 2, 'minimum_value' => $minimum, 'maximum_value' => $maximum, 'reference_value' => $reference, 'sort_order' => $order, 'condition_operator' => $condition, 'options' => [], 'frequency_type' => $frequency, 'frequency_config' => $frequencyConfig, 'operational_rule' => null, 'visibility_config' => null, 'is_required' => true];
    }
}
