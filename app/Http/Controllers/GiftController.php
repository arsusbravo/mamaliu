<?php

namespace App\Http\Controllers;

use App\Models\Gift;
use App\Models\Group;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GiftController extends Controller
{
    public function index(Request $request)
    {
        $query = Gift::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $gifts = $query->with(['user', 'group', 'rewardMenu', 'qualifyingItems'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return inertia('gifts/Index', [
            'gifts' => $gifts,
            'menus' => Menu::orderBy('label')->get(),
            'groups' => Group::active()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    private function rules(?Gift $gift = null): array
    {
        $codeUnique = Rule::unique('gifts', 'code');
        if ($gift) {
            $codeUnique->ignore($gift->id);
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', $codeUnique],
            'description' => ['nullable', 'string', 'max:255'],
            'target_type' => ['required', Rule::in(['general', 'user', 'group', 'items'])],
            'user_id' => ['required_if:target_type,user', 'nullable', 'exists:users,id'],
            'group_id' => ['required_if:target_type,group', 'nullable', 'exists:groups,id'],
            'qualifying_menu_ids' => ['required_if:target_type,items', 'array'],
            'qualifying_menu_ids.*' => ['integer', 'exists:menu,id'],
            'threshold_amount' => ['required', 'numeric', 'min:0.01'],
            'reward_menu_id' => ['required', 'exists:menu,id'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'active' => ['required', 'boolean'],
        ];
    }

    private function clearIrrelevantTargets(array $validated): array
    {
        if ($validated['target_type'] !== 'user') {
            $validated['user_id'] = null;
        }
        if ($validated['target_type'] !== 'group') {
            $validated['group_id'] = null;
        }

        return $validated;
    }

    public function store(Request $request)
    {
        $validated = $this->clearIrrelevantTargets($request->validate($this->rules()));
        $qualifyingIds = $validated['qualifying_menu_ids'] ?? [];
        unset($validated['qualifying_menu_ids']);

        $gift = Gift::create($validated);
        $gift->qualifyingItems()->sync($validated['target_type'] === 'items' ? $qualifyingIds : []);

        return redirect()->route('admin.gifts_index')
            ->with('success', 'Gift rule created successfully.');
    }

    public function update(Request $request, Gift $gift)
    {
        $validated = $this->clearIrrelevantTargets($request->validate($this->rules($gift)));
        $qualifyingIds = $validated['qualifying_menu_ids'] ?? [];
        unset($validated['qualifying_menu_ids']);

        $gift->update($validated);
        $gift->qualifyingItems()->sync($validated['target_type'] === 'items' ? $qualifyingIds : []);

        return redirect()->route('admin.gifts_index')
            ->with('success', 'Gift rule updated successfully.');
    }

    public function destroy(Gift $gift)
    {
        $gift->delete();

        return redirect()->route('admin.gifts_index')
            ->with('success', 'Gift rule deleted successfully.');
    }
}
