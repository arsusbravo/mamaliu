<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Weekmenu;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $currentWeek = Carbon::now()->week;
        $currentYear = Carbon::now()->year;

        // Stats for current week
        $currentWeekOrders = Order::where('week', $currentWeek)
            ->where('year', $currentYear)
            ->with(['user', 'weekmenu.menu'])
            ->get();

        $totalOrdersThisWeek = $currentWeekOrders->count();
        
        // Calculate quarter revenue (current quarter)
        $currentQuarter = Carbon::now()->quarter;
        $quarterStart = Carbon::now()->setQuarter($currentQuarter)->startOfQuarter();
        $quarterEnd = Carbon::now()->setQuarter($currentQuarter)->endOfQuarter();
        
        $quarterOrders = Order::whereBetween('created_at', [$quarterStart, $quarterEnd])
            ->with(['weekmenu.menu'])
            ->get();
        
        $totalRevenueThisQuarter = $quarterOrders->sum(function ($order) {
            return ($order->special_price ?? $order->weekmenu->menu->price) * $order->quantity;
        });

        // Count unique weeks with menus in this quarter
        $totalWeekmenusThisQuarter = Weekmenu::whereBetween('created_at', [$quarterStart, $quarterEnd])
            ->select('week', 'year')
            ->groupBy('week', 'year')
            ->get()
            ->count();

        // Pre-orders grouped by client (future weeks)
        $preOrdersByClient = Order::where(function ($query) use ($currentWeek, $currentYear) {
            $query->where('year', '>', $currentYear)
                ->orWhere(function ($q) use ($currentWeek, $currentYear) {
                    $q->where('year', $currentYear)
                        ->where('week', '>', $currentWeek);
                });
        })
        ->with(['user', 'weekmenu.menu'])
        ->orderBy('year')
        ->orderBy('week')
        ->get()
        ->groupBy('user_id')
        ->take(10)
        ->map(function ($orders) {
            $user = $orders->first()->user;
            $total = $orders->sum(function ($order) {
                return ($order->special_price ?? $order->weekmenu->menu->price) * $order->quantity;
            });
            
            // Get earliest week/year
            $earliestOrder = $orders->sortBy('week')->sortBy('year')->first();
            
            return [
                'user_id' => $user->id,
                'client_name' => $user->name,
                'total' => $total,
                'order_count' => $orders->count(),
                'earliest_week' => $earliestOrder->week,
                'earliest_year' => $earliestOrder->year,
            ];
        })
        ->values();

        $totalPreOrders = Order::where(function ($query) use ($currentWeek, $currentYear) {
            $query->where('year', '>', $currentYear)
                ->orWhere(function ($q) use ($currentWeek, $currentYear) {
                    $q->where('year', $currentYear)
                        ->where('week', '>', $currentWeek);
                });
        })->count();

        // Recent orders grouped by client (last 10 clients with orders this quarter)
        $recentOrdersByClient = Order::whereBetween('created_at', [$quarterStart, $quarterEnd])
            ->with(['user', 'weekmenu.menu'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('user_id')
            ->take(5)
            ->map(function ($orders) {
                $user = $orders->first()->user;
                $total = $orders->sum(function ($order) {
                    return ($order->special_price ?? $order->weekmenu->menu->price) * $order->quantity;
                });
                
                // Get all unique notes
                $notes = $orders->pluck('notes')->filter()->unique()->values();
                
                return [
                    'user_id' => $user->id,
                    'client_name' => $user->name,
                    'total' => $total,
                    'order_count' => $orders->count(),
                    'notes' => $notes,
                    'latest_date' => $orders->first()->created_at->toISOString(),
                    'is_new' => $orders->first()->created_at->diffInHours(now()) < 24,
                ];
            })
            ->values();

        // Recent notes (orders with notes from last 7 days)
        $recentNotes = Order::whereNotNull('notes')
            ->where('notes', '!=', '')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->with(['user', 'weekmenu.menu'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'client_name' => $order->user->name,
                    'notes' => $order->notes,
                    'menu_label' => $order->weekmenu->menu->label,
                    'created_at' => $order->created_at->diffForHumans(),
                ];
            });

        return inertia('Dashboard', [
            'stats' => [
                'total_orders_this_week' => $totalOrdersThisWeek,
                'total_revenue_this_quarter' => $totalRevenueThisQuarter,
                'total_weekmenus_this_quarter' => $totalWeekmenusThisQuarter,
                'total_pre_orders' => $totalPreOrders,
                'current_quarter' => $currentQuarter,
            ],
            'recentOrders' => $recentOrdersByClient,
            'preOrders' => $preOrdersByClient,
            'recentNotes' => $recentNotes,
            'currentWeek' => $currentWeek,
            'currentYear' => $currentYear,
        ]);
    }
}