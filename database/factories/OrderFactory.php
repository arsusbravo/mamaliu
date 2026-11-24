<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Weekmenu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'weekmenu_id' => Weekmenu::factory(),
            'quantity' => 1,
            'week' => now()->isoWeek,
            'year' => now()->isoWeekYear,
        ];
    }
}
