<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\PickupPoint;
use Illuminate\Database\Seeder;

class PickupPointSeeder extends Seeder
{
    public function run(): void
    {
        Group::each(function (Group $group) {
            // Only seed if the group has no pickup points yet
            if ($group->pickupPoints()->doesntExist()) {
                PickupPoint::create([
                    'group_id' => $group->id,
                    'name' => $group->name,
                ]);
            }
        });
    }
}
