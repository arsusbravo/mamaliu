<?php

namespace App\Http\Controllers;

use App\Models\Weekmenu;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ClientHomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $currentWeek = Carbon::now()->week;
        $currentYear = Carbon::now()->year;

        // Try to get weekmenus for current week
        $query = Weekmenu::with(['menu', 'group'])
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
        $isPreOrder = false;

        // If no weekmenus for current week, get future weekmenus
        if ($weekmenus->isEmpty()) {
            $futureQuery = Weekmenu::with(['menu', 'group'])
                ->where(function ($q) use ($currentWeek, $currentYear) {
                    $q->where('year', '>', $currentYear)
                      ->orWhere(function ($q2) use ($currentWeek, $currentYear) {
                          $q2->where('year', $currentYear)
                             ->where('week', '>', $currentWeek);
                      });
                })
                ->where('quantity', '>', 0)
                ->where('invitation', 1);

            if ($user->group_id) {
                $futureQuery->where(function ($q) use ($user) {
                    $q->whereNull('group_id')
                      ->orWhere('group_id', $user->group_id);
                });
            }

            $weekmenus = $futureQuery->orderBy('year')
                ->orderBy('week')
                ->orderBy('ordering')
                ->get();

            if ($weekmenus->isNotEmpty()) {
                $isPreOrder = true;
                $currentWeek = $weekmenus->first()->week;
                $currentYear = $weekmenus->first()->year;
            }
        }

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
                    ] : null,
                ];
            }),
            'isPreOrder' => $isPreOrder,
            'currentWeek' => $currentWeek,
            'currentYear' => $currentYear,
            'userName' => $user->name,
            'welcome' => $request->has('welcome'),
        ]);
    }

    public function placeOrder(Request $request)
    {
        $validated = $request->validate([
            'orders' => 'required|array|min:1',
            'orders.*.weekmenu_id' => 'required|exists:weekmenu,id',
            'orders.*.quantity' => 'required|integer|min:1',
            'orders.*.notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $createdOrders = [];

        foreach ($validated['orders'] as $orderData) {
            $weekmenu = Weekmenu::findOrFail($orderData['weekmenu_id']);
            
            // Validate quantity doesn't exceed weekmenu quantity
            if ($orderData['quantity'] > $weekmenu->quantity) {
                $orderData['quantity'] = $weekmenu->quantity;
            }

            // Check if order exists
            $existingOrder = \App\Models\Order::where('user_id', $user->id)
                ->where('weekmenu_id', $weekmenu->id)
                ->where('week', $weekmenu->week)
                ->where('year', $weekmenu->year)
                ->first();

            if ($existingOrder) {
                // Update existing order
                $existingOrder->quantity += $orderData['quantity'];
                if (!empty($orderData['notes'])) {
                    $existingOrder->notes = $orderData['notes'];
                }
                $existingOrder->save();
                $createdOrders[] = $existingOrder;
            } else {
                // Create new order
                $order = Order::create([
                    'user_id' => $user->id,
                    'weekmenu_id' => $weekmenu->id,
                    'group_id' => $user->group_id ?? $weekmenu->group_id,
                    'quantity' => $orderData['quantity'],
                    'notes' => $orderData['notes'] ?? null,
                    'week' => $weekmenu->week,
                    'year' => $weekmenu->year,
                ]);
                $createdOrders[] = $order;
            }
        }

        $firstOrder = $createdOrders[0];
        
        // GET ALL ORDERS FOR THIS WEEK/YEAR FOR THIS USER
        $allOrdersForWeek = Order::with(['weekmenu.menu', 'weekmenu.group'])
            ->where('user_id', $user->id)
            ->where('week', $firstOrder->week)
            ->where('year', $firstOrder->year)
            ->get();

        // Substract ordered quantities from weekmenus
        foreach ($createdOrders as $order) {
            $weekmenu = Weekmenu::find($order->weekmenu_id);
            if ($weekmenu) {
                $weekmenu->quantity -= $order->quantity;
                $weekmenu->save();
            }
        }
        
        // Send order confirmation email with ALL orders for the week
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