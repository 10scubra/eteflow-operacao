<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\ReadingTemplateBootstrapService;
use Illuminate\Database\Seeder;

class ProcessBiologicalReadingSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->where('username', 'master.teste')->firstOrFail();
        app(ReadingTemplateBootstrapService::class)->install($this->definition(), $author);
    }

    /** @return array<string, mixed> */
    private function definition(): array
    {
        return [
            'stable_key' => 'daily_field_monitoring_ete',
            'name' => 'Monitoramento Diário de Campo — ETE',
            'description' => 'Ficha operacional real do processo biológico.',
            'bootstrap_key' => 'ETAPA_3B_PROCESSO_BIOLOGICO_V1',
            'sections' => [
                [
                    'section_key' => 'biological_anoxic_lagoon', 'label' => 'Processo Biológico — Lagoa Anóxica', 'description' => 'Leituras de campo da Lagoa Anóxica.', 'sort_order' => 1,
                    'parameters' => [
                        $this->parameter('weather_condition', 'Condição climática', 'single_select', 1, options: ['TEMPO LIMPO', 'CHUVA'], required: false, frequency: 'ONCE_PER_SHIFT', rule: 'Registrar a condição climática observada no turno.'),
                        $this->parameter('anoxic_inlet_flow', 'Vazão Entrada do Anóxico', 'decimal', 2, unit: 'm³/h', reference: 15, rule: 'Referência operacional: 15 m³/h.'),
                        $this->parameter('feed_pump_command', 'Frequência/Comando da bomba de alimentação', 'percentage', 3, unit: '%', minimum: 0, maximum: 100, condition: 'BETWEEN', rule: 'Faixa configurada: 0–100%. Não corresponde a Hz.'),
                        $this->parameter('chemical_feed_flow', 'Vazão de alimentação de químicos', 'decimal', 4, unit: 'm³/h', rule: 'Preservada a nomenclatura da ficha física.'),
                        $this->parameter('anoxic_ph', 'pH', 'decimal', 5, minimum: 6.6, maximum: 7.2, condition: 'BETWEEN', rule: 'Faixa operacional: 6,6–7,2.'),
                        $this->parameter('anoxic_dissolved_oxygen', 'Oxigênio Dissolvido (OD)', 'decimal', 6, unit: 'mg/L', maximum: 1, condition: 'LESS_THAN', rule: 'Condição operacional: menor que 1 mg/L.'),
                        $this->parameter('anoxic_sd30', 'SD30', 'decimal', 7, unit: 'mL/L', reference: 400, rule: 'Referência operacional: 400 mL/L.'),
                        $this->parameter('anoxic_mixers_running', 'Misturadores funcionando', 'integer', 8, reference: 2, decimals: 0, rule: 'Quantidade de misturadores efetivamente funcionando. Referência: 2.'),
                        $this->parameter('anoxic_observations', 'Observação operacional', 'textarea', 9, required: false, rule: 'Registro opcional com autoria e horário.'),
                    ],
                ],
                [
                    'section_key' => 'biological_aerobic_lagoon', 'label' => 'Processo Biológico — Lagoa Aeróbia', 'description' => 'Leituras de campo da Lagoa Aeróbia.', 'sort_order' => 2,
                    'parameters' => [
                        $this->parameter('aerobic_ph', 'pH — Lagoa Aeróbia', 'decimal', 1, minimum: 6.6, maximum: 7.2, condition: 'BETWEEN', rule: 'Faixa operacional: 6,6–7,2.', points: $this->points(3)),
                        $this->parameter('aerobic_dissolved_oxygen', 'Oxigênio Dissolvido (OD)', 'decimal', 2, unit: 'mg/L', minimum: 1, condition: 'GREATER_THAN', rule: 'Condição operacional: maior que 1 mg/L.', points: $this->points(4)),
                        $this->parameter('aerobic_sd30', 'SD30', 'decimal', 3, unit: 'mL/L', reference: 400, rule: 'Referência operacional: 400 mL/L.', points: $this->points(3)),
                        $this->parameter('alkalizer_dosage', 'Dosagem de Alcalinizante?', 'boolean', 4, rule: 'Registro operacional simples; não calcula consumo.'),
                        $this->parameter('alkalizer_product', 'Qual alcalinizante?', 'single_select', 5, options: [], required: false, rule: 'Opções devem ser cadastradas pelo Master após confirmação dos produtos.', visibility: ['field_key' => 'alkalizer_dosage', 'operator' => 'EQUALS', 'value' => true]),
                        $this->parameter('aerobic_level', 'Nível', 'decimal', 6, unit: 'm'),
                        $this->parameter('aerobic_temperature', 'Temperatura', 'decimal', 7, unit: '°C', reference: 25, rule: 'Referência operacional: 25 °C.'),
                        $this->parameter('aerators_running', 'Aeradores funcionando', 'integer', 8, reference: 8, decimals: 0, rule: 'Quantidade efetivamente funcionando. Referência: 8.'),
                        $this->parameter('antifoam_dosage', 'Dosagem de Antiespumante?', 'boolean', 9, rule: 'Registro SIM/NÃO; sem cálculo de consumo.'),
                        $this->parameter('aerobic_observations', 'Observação operacional', 'textarea', 10, required: false, rule: 'Registro opcional com autoria e horário.'),
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function parameter(string $key, string $label, string $type, int $order, ?string $unit = null, ?float $minimum = null, ?float $maximum = null, ?float $reference = null, string $condition = 'REFERENCE', int $decimals = 2, array $options = [], bool $required = true, string $frequency = 'EVERY_ROUND', ?string $rule = null, array $points = [], ?array $visibility = null): array
    {
        return ['field_key' => $key, 'label' => $label, 'data_type' => $type, 'unit' => $unit, 'decimal_places' => $decimals, 'minimum_value' => $minimum, 'maximum_value' => $maximum, 'reference_value' => $reference, 'sort_order' => $order, 'condition_operator' => $condition, 'options' => $options, 'frequency_type' => $frequency, 'frequency_config' => [], 'operational_rule' => $rule, 'visibility_config' => $visibility, 'is_required' => $required, 'points' => $points];
    }

    /** @return array<int, array<string, mixed>> */
    private function points(int $count): array
    {
        return collect(range(1, $count))->map(fn (int $number): array => ['stable_key' => sprintf('point_%02d', $number), 'label' => sprintf('Ponto %02d', $number), 'sort_order' => $number])->all();
    }
}
