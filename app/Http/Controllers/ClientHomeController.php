<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use App\Models\Gift;
use App\Models\PickupPoint;
use App\Models\User;
use App\Models\Weekmenu;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Mail\OrderConfirmation;
use App\Exceptions\DiscountIneligibleException;
use App\Exceptions\DiscountNotFoundException;
use App\Exceptions\GiftIneligibleException;
use App\Exceptions\GiftNotFoundException;
use App\Services\DiscountService;
use App\Services\GiftService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ClientHomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $nowWeek = Carbon::now()->isoWeek;
        $nowYear = Carbon::now()->isoWeekYear;

        $currentWeek = $request->integer('week', $nowWeek);
        $currentYear = $request->integer('year', $nowYear);

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
            'hasActiveDiscounts' => Discount::currentlyValid()->exists(),
            'giftThreshold' => Gift::currentlyValid()->first()?->threshold_amount,
            'defaultPickupPointId' => $this->lastPickupPointId($user, $currentWeek, $currentYear),
        ]);
    }

    /**
     * The pickup point to preselect for this client.
     */
    private function lastPickupPointId(User $user, int $week, int $year): ?int
    {
        $thisWeek = Order::where('user_id', $user->id)
            ->where('week', $week)
            ->where('year', $year)
            ->whereNotNull('pickup_point_id')
            ->latest('created_at')
            ->value('pickup_point_id');

        if ($thisWeek) {
            return $thisWeek;
        }

        return Order::where('user_id', $user->id)
            ->whereNotNull('pickup_point_id')
            ->latest('created_at')
            ->value('pickup_point_id');
    }

    /**
     * Keep only the cart lines this client may order (same rules as index())
     * and merge lines for the same weekmenu.
     */
    private function orderableOrders(array $orders, User $user): array
    {
        $weekmenus = Weekmenu::whereIn('id', collect($orders)->pluck('weekmenu_id')->unique())->get()->keyBy('id');
        $nowWeek = Carbon::now()->isoWeek;
        $nowYear = Carbon::now()->isoWeekYear;

        $allowed = array_filter($orders, function ($line) use ($weekmenus, $user, $nowWeek, $nowYear) {
            $weekmenu = $weekmenus->get($line['weekmenu_id']);

            if (!$weekmenu) {
                return false;
            }

            if ($weekmenu->year < $nowYear || ($weekmenu->year == $nowYear && $weekmenu->week < $nowWeek)) {
                return false;
            }

            return !$user->group_id || $weekmenu->group_id === null || $weekmenu->group_id == $user->group_id;
        });

        // Merge duplicates: quantities add up, last note wins
        $merged = [];

        foreach ($allowed as $line) {
            $id = (int) $line['weekmenu_id'];

            if (!isset($merged[$id])) {
                $merged[$id] = ['weekmenu_id' => $id, 'quantity' => 0, 'notes' => null];
            }

            $merged[$id]['quantity'] += (int) $line['quantity'];

            if (!empty($line['notes'])) {
                $merged[$id]['notes'] = $line['notes'];
            }
        }

        return array_values($merged);
    }

    /**
     * Resolve each requested cart line to its menu and current price, for
     * discount eligibility checks.
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
            $cart = $this->orderableOrders($validated['orders'], $request->user());

            if (empty($cart)) {
                return response()->json(['valid' => false, 'message' => 'None of the selected items are available.']);
            }

            $cartLines = $this->buildCartLines($cart);
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

    public function validateGift(Request $request)
    {
        $validated = $request->validate([
            'gift_code' => 'required|string|max:50',
            'orders' => 'required|array|min:1',
            'orders.*.weekmenu_id' => 'required|exists:weekmenu,id',
            'orders.*.quantity' => 'required|integer|min:1',
        ]);

        $giftService = app(GiftService::class);

        try {
            $gift = $giftService->findActiveGift();
            $cart = $this->orderableOrders($validated['orders'], $request->user());

            if (empty($cart)) {
                return response()->json(['valid' => false, 'message' => 'None of the selected items are available.']);
            }

            $cartLines = $this->buildCartLines($cart);
            $giftService->checkEligibility($gift, $request->user(), $validated['gift_code'], $cartLines);
            $result = $giftService->calculateGift($gift, $cartLines);

            return response()->json([
                'valid' => true,
                'quantity' => $result['quantity'],
                'reward_menu_label' => $gift->rewardMenu->label,
            ]);
        } catch (GiftNotFoundException|GiftIneligibleException $e) {
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
            'gift_code' => 'nullable|string|max:50',
            // Token that stops a double click or retry from ordering twice
            'idempotency_key' => 'nullable|string|max:64',
            'orders' => 'required|array|min:1',
            'orders.*.weekmenu_id' => 'required|exists:weekmenu,id',
            'orders.*.quantity' => 'required|integer|min:1',
            'orders.*.notes' => 'nullable|string',
        ]);

        $user = $request->user();

        // Pick-up point must belong to the client's group
        if (!empty($validated['pickup_point_id'])) {
            $belongsToGroup = $user->group_id && PickupPoint::where('id', $validated['pickup_point_id'])
                ->where('group_id', $user->group_id)
                ->exists();

            if (!$belongsToGroup) {
                return back()->withErrors(['pickup_point_id' => 'This pick-up point does not belong to your group.'])->withInput();
            }
        }

        // Drop lines the client may not order
        $cart = $this->orderableOrders($validated['orders'], $user);

        if (empty($cart)) {
            return back()->with('error', 'None of the selected items were available.');
        }

        // A repeated token gets the first result instead of a second order
        $checkoutKey = !empty($validated['idempotency_key']) ? "checkout:{$user->id}:{$validated['idempotency_key']}" : null;

        if ($checkoutKey && !Cache::add($checkoutKey, 'pending', now()->addMinutes(10))) {
            $firstResult = Cache::get($checkoutKey);

            return redirect('/orders')->with(is_array($firstResult) ? $firstResult : []);
        }

        $createdOrders = [];
        $discountService = app(DiscountService::class);
        $giftService = app(GiftService::class);

        try {
            // Retry up to 3 times on deadlock
            DB::transaction(function () use ($validated, $cart, $user, &$createdOrders, $discountService, $giftService) {
                $createdOrders = [];
                $discount = null;

                if (!empty($validated['discount_code'])) {
                    // Lock the discount row too.
                    $discount = $discountService->findValidCode($validated['discount_code'], lockForUpdate: true);
                    $eligibilityCartLines = $this->buildCartLines($cart);
                    $discountService->checkEligibility($discount, $user, $eligibilityCartLines);
                }

                $gift = null;

                if (!empty($validated['gift_code'])) {
                    $gift = $giftService->findActiveGift(lockForUpdate: true);
                    $eligibilityCartLines = $this->buildCartLines($cart);
                    $giftService->checkEligibility($gift, $user, $validated['gift_code'], $eligibilityCartLines);
                }

                $finalCartLines = [];

                // Lock the weekmenus in id order to avoid deadlocks
                $weekmenus = Weekmenu::with('menu')
                    ->whereIn('id', collect($cart)->pluck('weekmenu_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($cart as $orderData) {
                    $weekmenu = $weekmenus[$orderData['weekmenu_id']];

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
                            // Add to the discount of an earlier checkout
                            $order->update([
                                'discount_id' => $discount->id,
                                'discount_amount' => round((float) $order->discount_amount + $result['per_line'][$order->weekmenu_id], 2),
                            ]);
                        }
                    }

                    $orderTotalBefore = collect($finalCartLines)->sum(fn ($line) => $line['unit_price'] * $line['quantity']);
                    $firstCreated = $createdOrders[0];
                    $discountService->redeem($discount, $user, $firstCreated->week, $firstCreated->year, $orderTotalBefore, $result['total_discount']);
                }

                if ($gift && !empty($finalCartLines)) {
                    $result = $giftService->calculateGift($gift, $finalCartLines);

                    if ($result['quantity'] > 0) {
                        // week/year: derive from the non-gift lines created in THIS checkout,
                        // since a gift line has no weekmenu of its own to derive them from.
                        $firstNonGiftOrder = $createdOrders[0];

                        $giftRedemption = $giftService->redeem(
                            $gift, $user, $firstNonGiftOrder->week, $firstNonGiftOrder->year,
                            $result['qualifying_total'], $result['quantity']
                        );

                        // Merge into an existing gift line for the same reward item/week if one exists, or create a new one if not.
                        $existingGiftOrder = Order::whereNull('weekmenu_id')
                            ->where('user_id', $user->id)
                            ->where('menu_id', $gift->reward_menu_id)
                            ->where('week', $firstNonGiftOrder->week)
                            ->where('year', $firstNonGiftOrder->year)
                            ->first();

                        if ($existingGiftOrder) {
                            $existingGiftOrder->increment('quantity', $result['quantity']);
                            $createdOrders[] = $existingGiftOrder;
                        } else {
                            $createdOrders[] = Order::create([
                                'user_id' => $user->id,
                                'weekmenu_id' => null,
                                'menu_id' => $gift->reward_menu_id,
                                'group_id' => $user->group_id,
                                'pickup_point_id' => $validated['pickup_point_id'] ?? null,
                                'quantity' => $result['quantity'],
                                'special_price' => 0.00,
                                'week' => $firstNonGiftOrder->week,
                                'year' => $firstNonGiftOrder->year,
                                'gift_redemption_id' => $giftRedemption->id,
                            ]);
                        }
                    }
                }

                // Pickup point is a per-client, per-week choice, not a per-line
                if (!empty($validated['pickup_point_id'])) {
                    collect($createdOrders)
                        ->map(fn ($order) => ['week' => $order->week, 'year' => $order->year])
                        ->unique(fn ($pair) => $pair['week'] . '-' . $pair['year'])
                        ->each(function ($pair) use ($user, $validated) {
                            Order::where('user_id', $user->id)
                                ->where('week', $pair['week'])
                                ->where('year', $pair['year'])
                                ->update(['pickup_point_id' => $validated['pickup_point_id']]);
                        });
                }
            }, 3);
        } catch (DiscountNotFoundException|DiscountIneligibleException $e) {
            $checkoutKey && Cache::forget($checkoutKey);

            return back()->withErrors(['discount_code' => $e->userMessage])->withInput();
        } catch (GiftNotFoundException|GiftIneligibleException $e) {
            $checkoutKey && Cache::forget($checkoutKey);

            return back()->withErrors(['gift_code' => $e->userMessage])->withInput();
        } catch (\Throwable $e) {
            // Release the token so the client can retry
            $checkoutKey && Cache::forget($checkoutKey);

            throw $e;
        }

        if (empty($createdOrders)) {
            $checkoutKey && Cache::forget($checkoutKey);

            return back()->with('error', 'None of the selected items were available.');
        }

        $firstOrder = $createdOrders[0];

        $allOrdersForWeek = Order::with(['weekmenu.menu', 'weekmenu.group', 'menu'])
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

        $flash = [
            'success' => true,
            'orderWeek' => $firstOrder->week,
            'orderYear' => $firstOrder->year,
            'orderCount' => count($createdOrders),
        ];

        // Store the result for repeated requests
        $checkoutKey && Cache::put($checkoutKey, $flash, now()->addMinutes(10));

        return redirect('/orders')->with($flash);
    }
}