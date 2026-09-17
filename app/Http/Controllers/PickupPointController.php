<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\PickupPoint;
use Illuminate\Http\Request;

class PickupPointController extends Controller
{
    public function store(Request $request, Group $group)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $group->pickupPoints()->create($validated);

        return back()->with('success', 'Pick-up point added.');
    }

    public function destroy(Group $group, PickupPoint $pickupPoint)
    {
        $pickupPoint->delete();

        return back()->with('success', 'Pick-up point removed.');
    }
}
