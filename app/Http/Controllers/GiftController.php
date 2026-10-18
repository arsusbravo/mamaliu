<?php

namespace App\Http\Controllers;

use App\Models\Gift;
use App\Models\Menu;
use Illuminate\Http\Request;

class GiftController extends Controller
{
    public function index(Request $request)
    {
        $gifts = Gift::with('rewardMenu')
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return inertia('gifts/Index', [
            'gifts' => $gifts,
            'menus' => Menu::orderBy('label')->get(),
        ]);
    }

    private function rules(): array
    {
        return [
            'threshold_amount' => ['required', 'numeric', 'min:0.01'],
            'reward_menu_id' => ['required', 'exists:menu,id'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        Gift::create($validated);

        return redirect()->route('admin.gifts_index')
            ->with('success', 'Gift created successfully.');
    }

    public function update(Request $request, Gift $gift)
    {
        $validated = $request->validate($this->rules());

        $gift->update($validated);

        return redirect()->route('admin.gifts_index')
            ->with('success', 'Gift updated successfully.');
    }

    public function destroy(Gift $gift)
    {
        $gift->delete();

        return redirect()->route('admin.gifts_index')
            ->with('success', 'Gift deleted successfully.');
    }
}
