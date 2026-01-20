<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Menu;
use App\Models\Group;
use App\Models\Weekmenu;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Mpdf\Mpdf;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        // Get current week and year as defaults
        $currentWeek = $request->get('week', Carbon::now()->week);
        $currentYear = $request->get('year', Carbon::now()->year);

        // Get orders with relationships
        $query = Order::with(['weekmenu.menu', 'user.group'])
            ->byWeek($currentWeek, $currentYear);

        // Filter by client's group
        if ($request->filled('group_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('group_id', $request->group_id);
            });
        }

        // Search by client name or menu name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%");
                })->orWhereHas('weekmenu.menu', function ($menuQuery) use ($search) {
                    $menuQuery->where('label', 'like', "%{$search}%");
                });
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        // Group orders by user
        $groupedOrders = $orders->groupBy('user_id')->map(function ($userOrders) {
            $user = $userOrders->first()->user;
            $userGroup = $user->group;

            // Calculate total
            $total = $userOrders->sum(function ($order) {
                $price = $order->special_price ?? $order->weekmenu->menu->price;
                return $order->quantity * $price;
            });

            return [
                'user' => $user,
                'group' => $userGroup,
                'orders' => $userOrders,
                'total' => $total,
                'order_date' => $userOrders->first()->created_at,
            ];
        })->values();

        $menus = Menu::orderBy('label')->get();
        $groups = Group::where('active', true)->orderBy('name')->get();

        return inertia('orders/Index', [
            'groupedOrders' => $groupedOrders,
            'menus' => $menus,
            'groups' => $groups,
            'currentWeek' => (int)$currentWeek,
            'currentYear' => (int)$currentYear,
            'filters' => [
                'search' => $request->search,
                'group_id' => $request->group_id,
            ],
        ]);
    }
    
    public function export(Request $request)
    {
        $currentWeek = $request->get('week', Carbon::now()->week);
        $currentYear = $request->get('year', Carbon::now()->year);

        // Get orders with filters
        $query = Order::with(['weekmenu.menu', 'user.group'])
            ->byWeek($currentWeek, $currentYear);

        // Filter by client's group
        if ($request->filled('group_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('group_id', $request->group_id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%");
                })->orWhereHas('weekmenu.menu', function ($menuQuery) use ($search) {
                    $menuQuery->where('label', 'like', "%{$search}%");
                });
            });
        }

        $orders = $query->get();

        // Get all unique menus for this week
        $menus = $orders->pluck('weekmenu.menu')->unique('id')->sortBy('label')->values();

        // Group orders by user
        $userOrders = $orders->groupBy('user_id')->map(function ($userOrders) use ($menus) {
            $user = $userOrders->first()->user;
            $row = ['name' => $user->name];
            
            $totalQuantity = 0;
            $totalPrice = 0;
            
            // For each menu, get quantity and calculate price
            foreach ($menus as $menu) {
                $order = $userOrders->firstWhere('weekmenu.menu.id', $menu->id);
                $quantity = $order ? $order->quantity : 0;
                $row['menu_' . $menu->id] = $quantity;
                $totalQuantity += $quantity;
                
                if ($order) {
                    $price = $order->special_price ?? $order->weekmenu->menu->price;
                    $totalPrice += $quantity * $price;
                }
            }
            
            $row['total_quantity'] = $totalQuantity;
            $row['total_price'] = $totalPrice;
            
            return $row;
        })->values();

        // Calculate column totals
        $columnTotals = ['TOTAL'];
        $grandTotalQuantity = 0;
        $grandTotalPrice = 0;
        
        foreach ($menus as $menu) {
            $columnTotal = $userOrders->sum('menu_' . $menu->id);
            $columnTotals[] = $columnTotal;
            $grandTotalQuantity += $columnTotal;
        }
        
        $columnTotals[] = $grandTotalQuantity;
        $grandTotalPrice = $userOrders->sum('total_price');
        $columnTotals[] = $grandTotalPrice;

        // Prepare headings
        $headings = ['Name'];
        foreach ($menus as $menu) {
            $headings[] = $menu->label;
        }
        $headings[] = 'Total Qty';
        $headings[] = 'Total Price';

        // Prepare data rows
        $data = $userOrders->map(function ($row) use ($menus) {
            $rowData = [$row['name']];
            foreach ($menus as $menu) {
                $rowData[] = $row['menu_' . $menu->id];
            }
            $rowData[] = $row['total_quantity'];
            $rowData[] = $row['total_price'];
            return $rowData;
        });

        // Add totals row
        $data->push($columnTotals);

        // Get orders with notes grouped by user
        $ordersWithNotes = $orders->filter(function ($order) {
            return !empty($order->notes);
        })->groupBy('user_id');

        // Add notes section if there are any notes
        if ($ordersWithNotes->isNotEmpty()) {
            // Add spacing before notes section
            $numColumns = count($headings);
            $data->push(array_fill(0, $numColumns, ''));
            $data->push(array_fill(0, $numColumns, ''));
            
            // Add notes header
            $data->push(['NOTES']);
            $data->push(['Client Name', 'Notes']);
            
            // Add notes rows
            foreach ($ordersWithNotes as $userNotes) {
                $user = $userNotes->first()->user;
                $allNotes = $userNotes->pluck('notes')->filter()->unique()->implode('; ');
                $data->push([$user->name, $allNotes]);
            }
        }

        // Calculate last row positions
        $totalRowPosition = $userOrders->count() + 1; // +1 for header
        $notesHeaderPosition = $ordersWithNotes->isNotEmpty() ? $totalRowPosition + 4 : null;

        // Create export class
        $export = new class($data, $headings, $totalRowPosition, $notesHeaderPosition) implements FromCollection, WithHeadings, WithStyles, \Maatwebsite\Excel\Concerns\WithColumnWidths {
            protected $data;
            protected $headings;
            protected $totalRowPosition;
            protected $notesHeaderPosition;
            
            public function __construct($data, $headings, $totalRowPosition, $notesHeaderPosition) {
                $this->data = $data;
                $this->headings = $headings;
                $this->totalRowPosition = $totalRowPosition;
                $this->notesHeaderPosition = $notesHeaderPosition;
            }
            
            public function collection() {
                return $this->data;
            }
            
            public function headings(): array {
                return $this->headings;
            }
            
            public function columnWidths(): array
            {
                // Find the longest name
                $maxLength = 15;
                foreach ($this->data as $row) {
                    if (isset($row[0]) && is_string($row[0])) {
                        $length = mb_strlen($row[0]);
                        if ($length > $maxLength) {
                            $maxLength = $length;
                        }
                    }
                }
                
                return [
                    'A' => $maxLength + 3,
                ];
            }
            
            public function styles(Worksheet $sheet) {
                $styles = [
                    1 => ['font' => ['bold' => true]],
                    $this->totalRowPosition => ['font' => ['bold' => true]],
                ];
                
                if ($this->notesHeaderPosition !== null) {
                    $styles[$this->notesHeaderPosition] = ['font' => ['bold' => true, 'size' => 14]];
                    $styles[$this->notesHeaderPosition + 1] = ['font' => ['bold' => true]];
                }
                
                return $styles;
            }
        };

        return Excel::download($export, "orders-week{$currentWeek}-{$currentYear}.xlsx");
    }

    public function pdf(Request $request)
    {
        $currentWeek = $request->get('week', Carbon::now()->week);
        $currentYear = $request->get('year', Carbon::now()->year);

        // Get orders with same filters
        $query = Order::with(['weekmenu.menu', 'user.group'])
            ->byWeek($currentWeek, $currentYear);

        // Filter by client's group
        if ($request->filled('group_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('group_id', $request->group_id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%");
                })->orWhereHas('weekmenu.menu', function ($menuQuery) use ($search) {
                    $menuQuery->where('label', 'like', "%{$search}%");
                });
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        // Group orders by user
        $groupedOrders = $orders->groupBy('user_id')->map(function ($userOrders) {
            $user = $userOrders->first()->user;
            $userGroup = $user->group;

            $total = $userOrders->sum(function ($order) {
                $price = $order->special_price ?? $order->weekmenu->menu->price;
                return $order->quantity * $price;
            });

            return [
                'user' => $user,
                'group' => $userGroup,
                'orders' => $userOrders,
                'total' => $total,
                'order_date' => $userOrders->first()->created_at,
            ];
        })->values();

        $html = view('orders.pdf', [
            'groupedOrders' => $groupedOrders,
            'week' => $currentWeek,
            'year' => $currentYear,
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        
        // Add Chinese font support
        $mpdf->SetDefaultFont('DejaVuSansCondensed');
        
        $mpdf->WriteHTML($html);
        
        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="orders-week' . $currentWeek . '-' . $currentYear . '.pdf"',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'weekmenu_id' => 'required|exists:weekmenu,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'week' => 'required|integer|min:1|max:53',
            'year' => 'required|integer',
        ]);

        $weekmenu = Weekmenu::findOrFail($validated['weekmenu_id']);
        $user = User::findOrFail($validated['user_id']);
        $groupId = $user->group_id ?? $weekmenu->group_id;

        $existedOrder = Order::where('weekmenu_id', $validated['weekmenu_id'])
            ->where('user_id', $validated['user_id'])
            ->where('week', $validated['week'])
            ->where('year', $validated['year'])
            ->first();

        if (!$existedOrder) {
            Order::create([
                'weekmenu_id' => $validated['weekmenu_id'],
                'user_id' => $validated['user_id'],
                'group_id' => $groupId,
                'quantity' => $validated['quantity'],
                'notes' => $validated['notes'],
                'week' => $validated['week'],
                'year' => $validated['year'],
            ]);
        } else {
            // If an order already exists for this user and weekmenu, update the quantity
            $existedOrder->quantity += $validated['quantity'];
            if (!empty($validated['notes'])) {
                $existedOrder->notes = $validated['notes'];
            }
            $existedOrder->save();
        }

        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'special_price' => 'nullable|numeric|min:0',
        ]);

        $orderedQuantity = $order->quantity;

        if ($validated['quantity'] == 0) {
            $order->delete();
            return redirect()->back();
        }
        $order->quantity = $validated['quantity'];
        $order->special_price = $validated['special_price'];
        $order->save();

        if ($orderedQuantity !== $validated['quantity']) {
            $weekmenu = Weekmenu::find($order->weekmenu_id);
            if ($weekmenu) {
                $weekmenu->quantity += ($orderedQuantity - $validated['quantity']);
                $weekmenu->save(); 
            }
        }

        return redirect()->back();
    }

    public function changeClient(Request $request, $userId)
    {
        $validated = $request->validate([
            'new_user_id' => 'required|exists:users,id|different:' . $userId,
            'action' => 'required|in:move,copy',
            'week' => 'required|integer',
            'year' => 'required|integer',
        ]);

        $orders = Order::where('user_id', $userId)
            ->where('week', $validated['week'])
            ->where('year', $validated['year'])
            ->get();

        foreach ($orders as $order) {
            if ($validated['action'] === 'copy') {
                // Copy order
                Order::create([
                    'user_id' => $validated['new_user_id'],
                    'weekmenu_id' => $order->weekmenu_id,
                    'group_id' => $order->group_id,
                    'quantity' => $order->quantity,
                    'special_price' => $order->special_price,
                    'notes' => $order->notes,
                    'week' => $order->week,
                    'year' => $order->year,
                ]);
            } else {
                // Move order
                $order->user_id = $validated['new_user_id'];
                $order->save();
            }
        }

        return redirect()->back();
    }
}