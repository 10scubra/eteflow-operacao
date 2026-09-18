<?php

namespace Database\Seeders;

use App\Models\Aspersion;
use App\Models\AspersionPoint;
use App\Models\Equipment;
use App\Models\EquipmentStatus;
use App\Models\Occurrence;
use App\Models\OperationalAction;
use App\Models\ParameterRule;
use App\Models\ReadingSectionDefinition;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\DailyOperationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EteFlowSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \LogicException('O seeder de homologação não pode ser executado em produção.');
        }

        $passwords = config('eteflow.uat_passwords');
        if (collect($passwords)->contains(fn (?string $password): bool => blank($password))) {
            throw new \LogicException('Configure todas as senhas UAT antes de executar o seeder.');
        }

        $users = [
            ['name' => 'Master Teste', 'username' => 'master.teste', 'email' => 'master@eteflow.local', 'password' => $passwords['master'], 'role' => 'master'],
            ['name' => 'Operador 1', 'username' => 'operador1', 'email' => 'operador1@eteflow.local', 'password' => $passwords['operator_1'], 'role' => 'operator'],
            ['name' => 'Operador 2', 'username' => 'operador2', 'email' => 'operador2@eteflow.local', 'password' => $passwords['operator_2'], 'role' => 'operator'],
        ];

        foreach ($users as $data) {
            User::query()->updateOrCreate(
                ['username' => $data['username']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'role' => $data['role'],
                    'is_active' => true,
                ],
            );
        }

        $this->call(AccessControlSeeder::class);
        $this->call(ProcessBiologicalReadingSeeder::class);
        $this->call(SecondaryProcessReadingSeeder::class);
        $this->call(DosingAssistantSeeder::class);
        $this->call(ChemicalStockSeeder::class);

        $shiftTemplates = [
            ['code' => 'day', 'name' => 'Diurno', 'starts_at' => '08:00:00', 'ends_at' => '20:00:00'],
            ['code' => 'night', 'name' => 'Noturno', 'starts_at' => '20:00:00', 'ends_at' => '08:00:00'],
        ];
        foreach ($shiftTemplates as $templateData) {
            $template = ShiftTemplate::query()->firstOrCreate(
                ['code' => $templateData['code'], 'version' => 1],
                $templateData + ['is_active' => true],
            );

            foreach ([30, 150, 270, 390, 510, 630] as $index => $offset) {
                $template->rounds()->firstOrCreate(
                    ['offset_minutes' => $offset],
                    ['sort_order' => $index + 1, 'is_active' => true],
                );
            }
        }

        $sectionDefinitions = [
            'process' => 'Processo',
            'flotators' => 'Flotadores',
            'decanter' => 'Decanter',
            'totalizers' => 'Totalizadores',
            'chemicals' => 'Produtos químicos',
            'observations' => 'Observações',
        ];
        foreach ($sectionDefinitions as $index => $label) {
            ReadingSectionDefinition::query()->firstOrCreate(
                ['section_key' => $index, 'version' => 1],
                ['label' => $label, 'sort_order' => array_search($index, array_keys($sectionDefinitions), true) + 1, 'is_active' => true],
            );
        }

        foreach ([
            ['code' => 'flotator-01', 'name' => 'Flotador 01', 'category' => 'Flotador', 'location' => 'Flotação'],
            ['code' => 'flotator-02', 'name' => 'Flotador 02', 'category' => 'Flotador', 'location' => 'Flotação'],
            ['code' => 'decanter', 'name' => 'Decanter', 'category' => 'Decanter', 'location' => 'Processo'],
            ['code' => 'aspersion-pump-01', 'name' => 'Bomba de aspersão 01', 'category' => 'Bomba', 'location' => 'Painel elétrico da aspersão'],
        ] as $equipmentData) {
            Equipment::query()->firstOrCreate(
                ['code' => $equipmentData['code']],
                $equipmentData + ['is_active' => true],
            );
        }
        $rules = [
            ['process', 'flow_in', 'Vazão de entrada', 'm³/h', 0, 500],
            ['process', 'ph', 'pH', null, 6.5, 8.5],
            ['process', 'dissolved_oxygen', 'Oxigênio dissolvido', 'mg/L', 1.5, 6],
            ['process', 'temperature', 'Temperatura', '°C', 10, 40],
            ['flotators', 'f1_flow', 'Vazão Flotador 01', 'm³/h', 0, 250],
            ['flotators', 'f2_flow', 'Vazão Flotador 02', 'm³/h', 0, 250],
            ['decanter', 'rotation', 'Rotação', 'rpm', 0, 6000],
            ['totalizers', 'inlet_total', 'Totalizador de entrada', 'm³', 0, null],
            ['totalizers', 'outlet_total', 'Totalizador de saída', 'm³', 0, null],
            ['chemicals', 'polymer_level', 'Nível de polímero', '%', 0, 100],
            ['chemicals', 'coagulant_level', 'Nível de coagulante', '%', 0, 100],
            ['observations', 'shift_note', 'Observação da rodada', null, null, null],
        ];

        foreach ($rules as [$section, $field, $label, $unit, $minimum, $maximum]) {
            ParameterRule::query()->firstOrCreate(
                ['section_key' => $section, 'field_key' => $field, 'version' => 1],
                ['label' => $label, 'unit' => $unit, 'minimum_value' => $minimum, 'maximum_value' => $maximum, 'is_required' => true, 'is_active' => true, 'effective_from' => now()->startOfDay()],
            );
        }

        $shift = app(DailyOperationService::class)->ensure();
        $master = User::where('username', 'master.teste')->firstOrFail();
        $operator1 = User::where('username', 'operador1')->firstOrFail();

        $action = OperationalAction::query()->firstOrCreate(
            ['shift_id' => $shift->id, 'title' => 'Limpeza do Flotador 02'],
            [
                'description' => 'Realizar a limpeza da região indicada e verificar a tubulação.',
                'priority' => 'high',
                'equipment' => 'Flotador 02',
                'due_at' => now()->setTime(15, 30),
                'status' => 'pending',
                'requires_before_photo' => true,
                'requires_after_photo' => true,
                'created_by' => $master->id,
            ],
        );
        foreach (['Realizar limpeza da área', 'Verificar a tubulação'] as $label) {
            $action->checklistItems()->firstOrCreate(['label' => $label]);
        }

        Occurrence::query()->firstOrCreate(
            ['code' => 'OC-'.now()->format('Ymd').'-001'],
            [
                'shift_id' => $shift->id,
                'title' => 'Aerador 03 parado',
                'description' => 'Equipamento parado; redundância ativa e manutenção comunicada.',
                'location' => 'Tanque biológico',
                'priority' => 'high',
                'status' => 'open',
                'reported_by' => $operator1->id,
                'reported_at' => now()->subMinutes(35),
            ],
        );

        $equipment = [
            ['Bomba de alimentação 01', 'Processo', 'operating', 52.0, 'Hz', null],
            ['Bomba de polímero', 'Casa química', 'attention', 32.8, 'Hz', 'Abaixo da faixa operacional.'],
            ['Aerador 03', 'Tanque biológico', 'stopped', null, null, 'Ocorrência aberta.'],
            ['Flotador 02', 'Flotação', 'operating', 18.4, 'm³/h', null],
        ];
        foreach ($equipment as [$name, $location, $status, $reading, $unit, $notes]) {
            EquipmentStatus::query()->updateOrCreate(
                ['shift_id' => $shift->id, 'name' => $name],
                ['location' => $location, 'status' => $status, 'reading' => $reading, 'unit' => $unit, 'notes' => $notes, 'reported_by' => $operator1->id, 'reported_at' => now()],
            );
        }

        $aspersionPoint = AspersionPoint::query()->firstOrCreate(
            ['name' => 'Bomba de aspersão 01'],
            ['location' => 'Painel elétrico da aspersão', 'public_token' => Str::random(48), 'is_active' => true],
        );
        $demoAspersion = Aspersion::query()->firstOrCreate(
            ['shift_id' => $shift->id, 'area' => 'Setor Norte', 'status' => 'active'],
            ['line' => 'Linha 02', 'initial_reading' => 1250, 'notes' => 'Aspersão demonstrativa do turno.', 'started_by' => $operator1->id, 'started_at' => now()->subMinutes(50)],
        );
        $demoAspersion->update([
            'aspersion_point_id' => $aspersionPoint->id,
            'initial_flow_rate' => 18.4,
            'initial_active_cannons' => 3,
        ]);
    }
}
