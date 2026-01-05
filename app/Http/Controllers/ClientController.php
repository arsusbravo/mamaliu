<?php

namespace App\Http\Controllers;

use App\Constants\UserType;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $clients = $query->paginate(10)->withQueryString();

        $groups = Group::where('active', true)->get();

        return inertia('clients/Index', [
            'groups' => $groups,
            'clients' => $clients,
            'filters' => [
                'search' => $request->search,
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    public function getClients(Request $request)
    {
        $query = User::where('usertype', UserType::CLIENT);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $clients = $query->orderBy('name')->get();

        return response()->json([
            'clients' => $clients,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|email|unique:users,username',
            'phone' => 'nullable|string|max:20',
            'group_id' => 'required|exists:groups,id',
            'active' => 'required|boolean',
            'password' => 'required|string|min:8',
        ]);

        $validated['usertype'] = UserType::CLIENT;
        $validated['password'] = bcrypt($validated['password']);

        User::create($validated);

        return redirect()->route('admin.clients_index')
            ->with('success', 'Client created successfully.');
    }

    public function update(Request $request, User $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|email|unique:users,username,' . $client->id,
            'phone' => 'nullable|string|max:20',
            'group_id' => 'required|exists:groups,id',
            'active' => 'required|boolean',
            'password' => 'nullable|string|min:8',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $client->update($validated);

        return redirect()->route('admin.clients_index')
            ->with('success', 'Client updated successfully.');
    }

    public function destroy(User $client)
    {
        $client->delete();

        return redirect()->route('admin.clients_index')
            ->with('success', 'Client deleted successfully.');
    }
}