<?php

namespace Database\Factories;

use App\Models\ReadingTemplate;
use App\Models\ReadingTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingTemplateVersion>
 */
class ReadingTemplateVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reading_template_id' => ReadingTemplate::factory(),
            'version' => 1,
            'status' => 'DRAFT',
        ];
    }
}
