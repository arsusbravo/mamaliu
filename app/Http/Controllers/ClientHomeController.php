<?php

namespace App\Http\Controllers;

use App\Models\Weekmenu;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ClientHomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $nowWeek = Carbon::now()->week;
        $nowYear = Carbon::now()->year;

        // Allow viewing specific week/year via URL params (for pre-orders)
        $currentWeek = $request->get('week', $nowWeek);
        $currentYear = $request->get('year', $nowYear);
        $isPreOrder = ($currentYear > $nowYear) || ($currentYear == $nowYear && $currentWeek > $nowWeek);

        // Try to get weekmenus for requested week
        $query = Weekmenu::with(['menu', 'group.pickupPoints'])
            ->where('week', $currentWeek)
            ->where('year', $currentYear)
            ->where('quantity', '>', 0);

        // Filter by user's group if they have one
        if ($user->group_id) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('group_id')
                  ->orWhere('group_id', $user->group_id);
            });
        }

        $weekmenus = $query->orderBy('ordering')->get();

        // Check for future weekmenus
        $futureQuery = Weekmenu::with(['menu', 'group.pickupPoints'])
            ->where(function ($q) use ($currentWeek, $currentYear) {
                $q->where('year', '>', $currentYear)
                  ->orWhere(function ($q2) use ($currentWeek, $currentYear) {
                      $q2->where('year', $currentYear)
                         ->where('week', '>', $currentWeek);
                  });
            })
            ->where('quantity', '>', 0);

        if ($user->group_id) {
            $futureQuery->where(function ($q) use ($user) {
                $q->whereNull('group_id')
                  ->orWhere('group_id', $user->group_id);
            });
        }

        $futureWeekmenus = $futureQuery->orderBy('year')
            ->orderBy('week')
            ->orderBy('ordering')
            ->get();

        // Get unique future weeks for pre-order navigation
        $futureWeeks = $futureWeekmenus->groupBy(function ($wm) {
            return $wm->year . '-' . $wm->week;
        })->map(function ($group) {
            return [
                'week' => $group->first()->week,
                'year' => $group->first()->year,
            ];
        })->values();

        return inertia('Home', [
            'weekmenus' => $weekmenus->map(function ($wm) {
                return [
                    'id' => $wm->id,
                    'week' => $wm->week,
                    'year' => $wm->year,
                    'quantity' => $wm->quantity,
                    'menu' => [
                        'id' => $wm->menu->id,
                        'label' => $wm->menu->label,
                        'description' => $wm->menu->description,
                        'price' => $wm->menu->price,
                        'image_url' => $wm->menu->image_url,
                        'has_image' => $wm->menu->has_image,
                    ],
                    'group' => $wm->group ? [
                        'id' => $wm->group->id,
                        'name' => $wm->group->name,
                        'pickup_points' => $wm->group->pickupPoints->map(fn ($p) => [
                            'id' => $p->id,
                            'name' => $p->name,
                        ])->values(),
                    ] : null,
                ];
            }),
            'currentWeek' => $currentWeek,
            'currentYear' => $currentYear,
            'userName' => $user->name,
            'welcome' => $request->has('welcome'),
            'futureWeeks' => $futureWeeks,
            'isPreOrder' => $isPreOrder,
            'userPickupPoints' => $user->group
                ? $user->group->load('pickupPoints')->pickupPoints->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()
                : [],
        ]);
    }

    public function placeOrder(Request $request)
    {
        $validated = $request->validate([
            'pickup_point_id' => 'nullable|exists:pickup_points,id',
            'orders' => 'required|array|min:1',
            'orders.*.weekmenu_id' => 'required|exists:weekmenu,id',
            'orders.*.quantity' => 'required|integer|min:1',
            'orders.*.notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $createdOrders = [];

        DB::transaction(function () use ($validated, $user, &$createdOrders) {
            foreach ($validated['orders'] as $orderData) {
                // Lock the row so concurrent requests cannot both see the same quantity
                $weekmenu = Weekmenu::lockForUpdate()->findOrFail($orderData['weekmenu_id']);

                if ($weekmenu->quantity <= 0) {
                    continue;
                }

                // Cap at whatever is still available
                $addedQty = min($orderData['quantity'], $weekmenu->quantity);

                $existingOrder = Order::where('user_id', $user->id)
                    ->where('weekmenu_id', $weekmenu->id)
                    ->where('week', $weekmenu->week)
                    ->where('year', $weekmenu->year)
                    ->first();

                if ($existingOrder) {
                    $existingOrder->quantity += $addedQty;
                    if (!empty($orderData['notes'])) {
                        $existingOrder->notes = $orderData['notes'];
                    }
                    if (!empty($validated['pickup_point_id'])) {
                        $existingOrder->pickup_point_id = $validated['pickup_point_id'];
                    }
                    $existingOrder->save();
                    $createdOrders[] = $existingOrder;
                } else {
                    $createdOrders[] = Order::create([
                        'user_id' => $user->id,
                        'weekmenu_id' => $weekmenu->id,
                        'group_id' => $user->group_id ?? $weekmenu->group_id,
                        'pickup_point_id' => $validated['pickup_point_id'] ?? null,
                        'quantity' => $addedQty,
                        'notes' => $orderData['notes'] ?? null,
                        'week' => $weekmenu->week,
                        'year' => $weekmenu->year,
                    ]);
                }

                // Subtract only the newly added quantity, not the running order total
                $weekmenu->decrement('quantity', $addedQty);
            }
        });

        if (empty($createdOrders)) {
            return back()->with('error', 'None of the selected items were available.');
        }

        $firstOrder = $createdOrders[0];

        $allOrdersForWeek = Order::with(['weekmenu.menu', 'weekmenu.group'])
            ->where('user_id', $user->id)
            ->where('week', $firstOrder->week)
            ->where('year', $firstOrder->year)
            ->get();

        try {
            Mail::to($user->email)->send(
                new OrderConfirmation($user, $allOrdersForWeek, $firstOrder->week, $firstOrder->year)
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email: ' . $e->getMessage());
        }

        return redirect('/orders')->with([
            'success' => true,
            'orderWeek' => $firstOrder->week,
            'orderYear' => $firstOrder->year,
            'orderCount' => count($createdOrders),
        ]);
    }
}