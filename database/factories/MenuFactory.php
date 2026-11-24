<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Menu>
 */
class MenuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 5, 25),
            'menutype' => 'normal',
        ];
    }
}
