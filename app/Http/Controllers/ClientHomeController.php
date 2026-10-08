<?php

namespace App\Http\Controllers;

use App\Models\Weekmenu;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Mail\OrderConfirmation;
use App\Exceptions\DiscountIneligibleException;
use App\Exceptions\DiscountNotFoundException;
use App\Services\DiscountService;
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

    /**
     * Resolve each requested cart line to its menu and current price, for
     * discount eligibility checks. Read-only; does not lock or reserve stock.
     *
     * @return array<int, array{weekmenu_id: int, menu_id: int|null, quantity: int, unit_price: float}>
     */
    private function buildCartLines(array $orders): array
    {
        $weekmenuIds = collect($orders)->pluck('weekmenu_id')->unique()->all();
        $weekmenus = Weekmenu::with('menu')->whereIn('id', $weekmenuIds)->get()->keyBy('id');

        return collect($orders)->map(function ($orderData) use ($weekmenus) {
            $weekmenu = $weekmenus->get($orderData['weekmenu_id']);

            return [
                'weekmenu_id' => (int) $orderData['weekmenu_id'],
                'menu_id' => $weekmenu?->menu_id,
                'quantity' => (int) $orderData['quantity'],
                'unit_price' => $weekmenu ? (float) $weekmenu->menu->price : 0.0,
            ];
        })->all();
    }

    public function validateDiscount(Request $request)
    {
        $validated = $request->validate([
            'discount_code' => 'required|string|max:50',
            'orders' => 'required|array|min:1',
            'orders.*.weekmenu_id' => 'required|exists:weekmenu,id',
            'orders.*.quantity' => 'required|integer|min:1',
        ]);

        $discountService = app(DiscountService::class);

        try {
            $discount = $discountService->findValidCode($validated['discount_code']);
            $cartLines = $this->buildCartLines($validated['orders']);
            $discountService->checkEligibility($discount, $request->user(), $cartLines);
            $result = $discountService->calculateDiscount($discount, $cartLines);

            return response()->json([
                'valid' => true,
                'discount_amount' => $result['total_discount'],
            ]);
        } catch (DiscountNotFoundException|DiscountIneligibleException $e) {
            return response()->json([
                'valid' => false,
                'message' => $e->userMessage,
            ]);
        }
    }

    public function placeOrder(Request $request)
    {
        $validated = $request->validate([
            'pickup_point_id' => 'nullable|exists:pickup_points,id',
            'discount_code' => 'nullable|string|max:50',
            'orders' => 'required|array|min:1',
            'orders.*.weekmenu_id' => 'required|exists:weekmenu,id',
            'orders.*.quantity' => 'required|integer|min:1',
            'orders.*.notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $createdOrders = [];
        $discountService = app(DiscountService::class);

        try {
            DB::transaction(function () use ($validated, $user, &$createdOrders, $discountService) {
                $discount = null;

                if (!empty($validated['discount_code'])) {
                    // Lock the discount row too, so concurrent checkouts can't both
                    // slip past a usage_limit check before either one is recorded.
                    $discount = $discountService->findValidCode($validated['discount_code'], lockForUpdate: true);
                    $eligibilityCartLines = $this->buildCartLines($validated['orders']);
                    $discountService->checkEligibility($discount, $user, $eligibilityCartLines);
                }

                $finalCartLines = [];

                foreach ($validated['orders'] as $orderData) {
                    // Lock the row so concurrent requests cannot both see the same quantity
                    $weekmenu = Weekmenu::with('menu')->lockForUpdate()->findOrFail($orderData['weekmenu_id']);

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

                    // Track what was actually added in THIS checkout (post stock-cap) for discount distribution.
                    $finalCartLines[] = [
                        'weekmenu_id' => $weekmenu->id,
                        'menu_id' => $weekmenu->menu_id,
                        'quantity' => $addedQty,
                        'unit_price' => (float) $weekmenu->menu->price,
                    ];
                }

                if ($discount && !empty($finalCartLines)) {
                    $result = $discountService->calculateDiscount($discount, $finalCartLines);

                    foreach ($createdOrders as $order) {
                        if (isset($result['per_line'][$order->weekmenu_id])) {
                            $order->update([
                                'discount_id' => $discount->id,
                                'discount_amount' => $result['per_line'][$order->weekmenu_id],
                            ]);
                        }
                    }

                    $orderTotalBefore = collect($finalCartLines)->sum(fn ($line) => $line['unit_price'] * $line['quantity']);
                    $firstCreated = $createdOrders[0];
                    $discountService->redeem($discount, $user, $firstCreated->week, $firstCreated->year, $orderTotalBefore, $result['total_discount']);
                }
            });
        } catch (DiscountNotFoundException|DiscountIneligibleException $e) {
            return back()->withErrors(['discount_code' => $e->userMessage])->withInput();
        }

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