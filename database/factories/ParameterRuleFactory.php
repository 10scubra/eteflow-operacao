<?php

namespace Database\Factories;

use App\Models\ParameterRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParameterRule>
 */
class ParameterRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_key' => 'process',
            'field_key' => fake()->unique()->slug(2),
            'label' => fake()->words(2, true),
            'data_type' => 'decimal',
            'decimal_places' => 2,
            'sort_order' => 0,
            'condition_operator' => 'BETWEEN',
            'frequency_type' => 'EVERY_ROUND',
            'frequency_config' => [],
            'is_required' => false,
            'is_active' => true,
            'version' => 1,
            'effective_from' => now(),
        ];
    }
}
