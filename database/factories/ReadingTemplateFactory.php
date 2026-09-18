<?php

namespace Database\Factories;

use App\Models\ReadingTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingTemplate>
 */
class ReadingTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stable_key' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_active' => true,
            'is_default' => false,
        ];
    }
}
