<?php

namespace App\Http\Controllers;

use App\Models\Weekmenu;
use App\Models\Menu;
use App\Models\Group;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WeekmenuController extends Controller
{
    public function index(Request $request)
    {
        // Get current week and year as defaults
        $currentWeek = $request->get('week', Carbon::now()->week);
        $currentYear = $request->get('year', Carbon::now()->year);
        $groupId = $request->get('group_id', null);

        $query = Weekmenu::with(['menu', 'group'])
            ->byWeek($currentWeek, $currentYear);

        // Filter by group if provided
        if ($request->filled('group_id')) {
            if ($request->group_id === 'none') {
                $query->whereNull('group_id');
            } else {
                $query->where('group_id', $request->group_id);
            }
        }

        $query->ordered();

        $weekmenus = $query->get();

        // Get all menus and groups for the form
        $menus = Menu::orderBy('label')->get();
        $groups = Group::where('active', true)->orderBy('name')->get();

        return inertia('weekmenus/Index', [
            'weekmenus' => $weekmenus,
            'menus' => $menus,
            'groups' => $groups,
            'currentWeek' => (int)$currentWeek,
            'currentYear' => (int)$currentYear,
            'groupId' => $groupId, // ADD THIS LINE
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'week' => 'required|integer|min:1|max:53',
            'year' => 'required|integer|min:2020|max:2100',
            'group_id' => 'required|exists:groups,id',
            'menu_id' => 'required|exists:menu,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $maxOrdering = Weekmenu::where('week', $validated['week'])
        ->where('year', $validated['year'])
        ->max('ordering') ?? -1;
    
        $validated['ordering'] = $maxOrdering + 1;

        Weekmenu::create($validated);

        return redirect()->route('admin.weekmenus_index', [
            'week' => $validated['week'],
            'year' => $validated['year'],
        ])->with('success', 'Week menu created successfully.');
    }

    public function update(Request $request, Weekmenu $weekmenu)
    {
        $validated = $request->validate([
            'week' => 'required|integer|min:1|max:53',
            'year' => 'required|integer|min:2020|max:2100',
            'group_id' => 'required|exists:groups,id',
            'menu_id' => 'required|exists:menu,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $weekmenu->update($validated);

        return redirect()->route('admin.weekmenus_index', [
            'week' => $validated['week'],
            'year' => $validated['year'],
        ])->with('success', 'Week menu updated successfully.');
    }

    public function destroy(Weekmenu $weekmenu)
    {
        $week = $weekmenu->week;
        $year = $weekmenu->year;
        
        $weekmenu->delete();

        return redirect()->route('admin.weekmenus_index', [
            'week' => $week,
            'year' => $year,
        ])->with('success', 'Week menu deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:weekmenu,id',
            'items.*.ordering' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            Weekmenu::where('id', $item['id'])
                ->update(['ordering' => $item['ordering']]);
        }

        return response()->json(['success' => true]);
    }

    public function updateQuantity(Request $request, Weekmenu $weekmenu)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $weekmenu->update(['quantity' => $validated['quantity']]);

        return response()->json(['success' => true]);
    }

    public function closeOrder(Request $request)
    {
        $validated = $request->validate([
            'week' => 'required|integer|min:1|max:53',
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        Weekmenu::where('week', $validated['week'])
            ->where('year', $validated['year'])
            ->update(['quantity' => 0]);

        return redirect()->route('admin.weekmenus_index', [
            'week' => $validated['week'],
            'year' => $validated['year'],
        ])->with('success', 'All orders closed for week ' . $validated['week'] . ', ' . $validated['year']);
    }

    public function getWeekmenus(Request $request)
    {
        $week = $request->get('week');
        $year = $request->get('year');

        $query = Weekmenu::with(['menu', 'group']);
        
        if ($week && $year) {
            $query->byWeek($week, $year);
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        $weekmenus = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'weekmenus' => $weekmenus,
        ]);
    }

    public function toggleInvitation(Request $request)
    {
        $validated = $request->validate([
            'week' => 'required|integer|min:1|max:53',
            'year' => 'required|integer|min:2020',
        ]);

        // Toggle invitation for all weekmenus in this week/year
        Weekmenu::where('week', $validated['week'])
            ->where('year', $validated['year'])
            ->update(['invitation' => DB::raw('IF(invitation = 1, 0, 1)')]);

        return back()->with('success', 'Pre-order visibility updated successfully!');
    }
}