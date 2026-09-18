<?php

namespace Database\Seeders;

use App\Models\DosingRule;
use App\Models\Equipment;
use App\Models\ParameterRule;
use App\Models\ReadingTemplateVersion;
use App\Models\User;
use Illuminate\Database\Seeder;

class DosingAssistantSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->where('username', 'master.teste')->firstOrFail();
        $equipment = Equipment::query()->updateOrCreate(
            ['code' => 'calcium-hydroxide-pump'],
            ['name' => 'Bomba de Hidróxido de Cálcio', 'category' => 'Bomba dosadora', 'location' => 'Lagoa Aeróbia', 'is_active' => true],
        );
        $published = ReadingTemplateVersion::query()->where('status', ReadingTemplateVersion::Published)->latest('version')->firstOrFail();
        $parameter = ParameterRule::query()->whereHas('sectionDefinition', fn ($query) => $query->where('reading_template_version_id', $published->id))->where('field_key', 'aerobic_ph')->firstOrFail();

        DosingRule::query()->firstOrCreate(
            ['stable_key' => 'calcium_hydroxide_aerobic_ph', 'version' => 1],
            [
                'name' => 'Hidróxido de Cálcio — Lagoa Aeróbia',
                'description' => 'Assistência operacional baseada no pH da Lagoa Aeróbia.',
                'is_active' => false,
                'parameter_rule_id' => $parameter->id,
                'parameter_rule_point_id' => null,
                'product_name' => 'Hidróxido de Cálcio',
                'equipment_id' => $equipment->id,
                'start_operator' => 'LESS_THAN_OR_EQUAL',
                'start_value' => 6.50,
                'stop_operator' => 'GREATER_THAN_OR_EQUAL',
                'stop_value' => 6.80,
                'reminder_after_minutes' => 15,
                'reminder_interval_minutes' => 15,
                'recheck_after_minutes' => 20,
                'effective_from' => now(),
                'created_by' => $author->id,
            ],
        );
    }
}
