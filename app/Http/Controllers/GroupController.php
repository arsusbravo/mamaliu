<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $query = Group::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $groups = $query->paginate(10)->withQueryString();

        return inertia('groups/Index', [
            'groups' => $groups,
            'filters' => [
                'search' => $request->search,
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:groups,slug|max:255',
            'active' => 'required|boolean',
        ]);

        Group::create($validated);

        return redirect()->route('admin.groups_index')
            ->with('success', 'Group created successfully.');
    }

    public function update(Request $request, Group $group)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:groups,slug,' . $group->id . '|max:255',
            'active' => 'required|boolean',
        ]);

        $group->update($validated);

        return redirect()->route('admin.groups_index')
            ->with('success', 'Group updated successfully.');
    }

    public function destroy(Group $group)
    {
        $group->delete();

        return redirect()->route('admin.groups_index')
            ->with('success', 'Group deleted successfully.');
    }

    public function show($id)
    {
        return inertia('groups/Show', ['id' => $id]);
    }
}