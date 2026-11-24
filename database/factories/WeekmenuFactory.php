<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Weekmenu>
 */
class WeekmenuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'week' => now()->isoWeek,
            'year' => now()->isoWeekYear,
            'group_id' => null,
            'quantity' => 10,
            'ordering' => 0,
        ];
    }
}
