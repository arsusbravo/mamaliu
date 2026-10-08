<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use App\Models\Group;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        $query = Discount::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $discounts = $query->with(['user', 'group', 'menu'])
            ->withCount('redemptions')
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return inertia('discounts/Index', [
            'discounts' => $discounts,
            'menus' => Menu::orderBy('label')->get(),
            'groups' => Group::active()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    private function rules(?Discount $discount = null): array
    {
        $codeUnique = Rule::unique('discounts', 'code');
        if ($discount) {
            $codeUnique->ignore($discount->id);
        }

        return [
            'code' => ['required', 'string', 'max:50', $codeUnique],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0'],
            'target_type' => ['required', Rule::in(['general', 'user', 'group', 'menu'])],
            'user_id' => ['required_if:target_type,user', 'nullable', 'exists:users,id'],
            'group_id' => ['required_if:target_type,group', 'nullable', 'exists:groups,id'],
            'menu_id' => ['required_if:target_type,menu', 'nullable', 'exists:menu,id'],
            'min_order_total' => ['nullable', 'numeric', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'active' => ['required', 'boolean'],
        ];
    }

    /**
     * Null out target FKs that don't match the chosen target_type, so stale
     * values from a previous edit never linger.
     */
    private function clearIrrelevantTargets(array $validated): array
    {
        foreach (['user_id', 'group_id', 'menu_id'] as $field) {
            $matchingType = str_replace('_id', '', $field);
            if ($validated['target_type'] !== $matchingType) {
                $validated[$field] = null;
            }
        }

        return $validated;
    }

    public function store(Request $request)
    {
        $validated = $this->clearIrrelevantTargets($request->validate($this->rules()));

        Discount::create($validated);

        return redirect()->route('admin.discounts_index')
            ->with('success', 'Discount created successfully.');
    }

    public function update(Request $request, Discount $discount)
    {
        $validated = $this->clearIrrelevantTargets($request->validate($this->rules($discount)));

        $discount->update($validated);

        return redirect()->route('admin.discounts_index')
            ->with('success', 'Discount updated successfully.');
    }

    public function destroy(Discount $discount)
    {
        $discount->delete();

        return redirect()->route('admin.discounts_index')
            ->with('success', 'Discount deleted successfully.');
    }
}
