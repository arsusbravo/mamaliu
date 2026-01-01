<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;

class ClientOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get all orders grouped by week/year
        $orders = Order::with(['weekmenu.menu', 'group'])
            ->where('user_id', $user->id)
            ->orderBy('year', 'desc')
            ->orderBy('week', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($order) {
                return $order->year . '-' . $order->week;
            })
            ->map(function ($weekOrders) {
                $firstOrder = $weekOrders->first();
                $total = $weekOrders->sum(function ($order) {
                    return ($order->special_price ?? $order->weekmenu->menu->price) * $order->quantity;
                });
                
                return [
                    'week' => $firstOrder->week,
                    'year' => $firstOrder->year,
                    'total' => $total,
                    'items_count' => $weekOrders->count(),
                    'created_at' => $firstOrder->created_at->toISOString(),
                    'orders' => $weekOrders->map(function ($order) {
                        $price = $order->special_price ?? $order->weekmenu->menu->price;
                        return [
                            'id' => $order->id,
                            'menu_label' => $order->weekmenu->menu->label,
                            'quantity' => $order->quantity,
                            'price' => $price,
                            'total' => $price * $order->quantity,
                            'notes' => $order->notes,
                        ];
                    })->values(),
                ];
            })
            ->values();

        return inertia('Orders', [
            'orders' => $orders,
        ]);
    }
}