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
        $query = Order::with(['weekmenu.menu', 'user.group', 'pickupPoint'])
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

        $query = Order::with(['weekmenu.menu', 'user.group', 'pickupPoint'])
            ->byWeek($currentWeek, $currentYear);

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

        $menus = $orders->pluck('weekmenu.menu')->unique('id')->sortBy('label')->values();

        $headings = ['Name'];
        foreach ($menus as $menu) {
            $headings[] = $menu->label;
        }
        $headings[] = 'Total Qty';
        $headings[] = 'Total Price';
        $numColumns = count($headings);

        // Group orders by pickup point (sorted by name, null/unknown last)
        $byPickupPoint = $orders->groupBy(fn ($o) => $o->pickup_point_id ?? 0)
            ->sortBy(fn ($group) => optional($group->first()->pickupPoint)->name ?? 'zzz');

        $data = collect();
        $sectionHeaderRows = [];
        $totalRows = [];
        $currentRow = 2; // row 1 = headings (WithHeadings)

        foreach ($byPickupPoint as $pickupOrders) {
            $sectionLabel = optional($pickupOrders->first()->pickupPoint)->name ?? 'No pick-up point';

            // Section header
            $sectionRow = array_fill(0, $numColumns, '');
            $sectionRow[0] = $sectionLabel;
            $data->push($sectionRow);
            $sectionHeaderRows[] = $currentRow++;

            // Per-user rows for this pick-up point
            $userRows = $pickupOrders->groupBy('user_id')->map(function ($userOrderGroup) use ($menus) {
                $user = $userOrderGroup->first()->user;
                $row = ['name' => $user->name];
                $totalQuantity = 0;
                $totalPrice = 0;

                foreach ($menus as $menu) {
                    $order = $userOrderGroup->firstWhere('weekmenu.menu.id', $menu->id);
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

            foreach ($userRows as $row) {
                $rowData = [$row['name']];
                foreach ($menus as $menu) {
                    $rowData[] = $row['menu_' . $menu->id];
                }
                $rowData[] = $row['total_quantity'];
                $rowData[] = $row['total_price'];
                $data->push($rowData);
                $currentRow++;
            }

            // Section total
            $sectionTotals = ['TOTAL'];
            foreach ($menus as $menu) {
                $sectionTotals[] = $userRows->sum('menu_' . $menu->id);
            }
            $sectionTotals[] = $userRows->sum('total_quantity');
            $sectionTotals[] = $userRows->sum('total_price');
            $data->push($sectionTotals);
            $totalRows[] = $currentRow++;

            // Blank separator
            $data->push(array_fill(0, $numColumns, ''));
            $currentRow++;
        }

        // Notes section
        $ordersWithNotes = $orders->filter(fn ($o) => !empty($o->notes))->groupBy('user_id');
        $notesHeaderRow = null;

        if ($ordersWithNotes->isNotEmpty()) {
            $data->push(array_fill(0, $numColumns, ''));
            $currentRow++;
            $data->push(['NOTES']);
            $notesHeaderRow = $currentRow++;
            $data->push(['Client Name', 'Notes']);
            $currentRow++;

            foreach ($ordersWithNotes as $userNotes) {
                $user = $userNotes->first()->user;
                $allNotes = $userNotes->pluck('notes')->filter()->unique()->implode('; ');
                $data->push([$user->name, $allNotes]);
                $currentRow++;
            }
        }

        $export = new class($data, $headings, $sectionHeaderRows, $totalRows, $notesHeaderRow) implements FromCollection, WithHeadings, WithStyles, \Maatwebsite\Excel\Concerns\WithColumnWidths {
            protected $data;
            protected $headings;
            protected $sectionHeaderRows;
            protected $totalRows;
            protected $notesHeaderRow;

            public function __construct($data, $headings, $sectionHeaderRows, $totalRows, $notesHeaderRow) {
                $this->data = $data;
                $this->headings = $headings;
                $this->sectionHeaderRows = $sectionHeaderRows;
                $this->totalRows = $totalRows;
                $this->notesHeaderRow = $notesHeaderRow;
            }

            public function collection() {
                return $this->data;
            }

            public function headings(): array {
                return $this->headings;
            }

            public function columnWidths(): array
            {
                $maxLength = 15;
                foreach ($this->data as $row) {
                    if (isset($row[0]) && is_string($row[0])) {
                        $length = mb_strlen($row[0]);
                        if ($length > $maxLength) {
                            $maxLength = $length;
                        }
                    }
                }
                return ['A' => $maxLength + 3];
            }

            public function styles(Worksheet $sheet) {
                $styles = [];
                $totalColumns = count($this->headings);
                $colLetter = fn($i) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $lastCol = $colLetter($totalColumns);

                // Header row: center + wrapText on B onwards
                if ($totalColumns >= 2) {
                    $sheet->getStyle("B1:{$lastCol}1")->applyFromArray([
                        'alignment' => ['horizontal' => 'center', 'wrapText' => true],
                    ]);
                }

                // Section header rows: bold + light yellow background
                foreach ($this->sectionHeaderRows as $row) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 12],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFF3CD'],
                        ],
                    ]);
                }

                // Total rows: bold
                foreach ($this->totalRows as $row) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'font' => ['bold' => true],
                    ]);
                }

                // Notes header
                if ($this->notesHeaderRow !== null) {
                    $styles[$this->notesHeaderRow] = ['font' => ['bold' => true, 'size' => 14]];
                    $styles[$this->notesHeaderRow + 1] = ['font' => ['bold' => true]];
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
            'weekmenu_id' => 'nullable|exists:weekmenu,id',
            'menu_id' => 'nullable|exists:menu,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'week' => 'required|integer|min:1|max:53',
            'year' => 'required|integer',
        ]);

        // Must have either weekmenu_id or menu_id
        if (empty($validated['weekmenu_id']) && empty($validated['menu_id'])) {
            return back()->withErrors(['weekmenu_id' => 'Please select a menu.']);
        }

        $user = User::findOrFail($validated['user_id']);
        $groupId = $user->group_id;

        // If menu_id is provided, find or create the weekmenu
        if (!empty($validated['menu_id'])) {
            $weekmenu = Weekmenu::firstOrCreate([
                'menu_id' => $validated['menu_id'],
                'week' => $validated['week'],
                'year' => $validated['year'],
                'group_id' => $groupId,
            ], [
                'quantity' => 0,
            ]);
            $validated['weekmenu_id'] = $weekmenu->id;
        } else {
            $weekmenu = Weekmenu::findOrFail($validated['weekmenu_id']);
            $groupId = $user->group_id ?? $weekmenu->group_id;
        }

        // Guard: reject if weekmenu cannot cover the requested quantity.
        // Skip for weekmenus that were just created via menu_id (quantity starts at 0).
        if (!$weekmenu->wasRecentlyCreated && $weekmenu->quantity < $validated['quantity']) {
            return back()->withErrors(['quantity' => 'Only ' . $weekmenu->quantity . ' spots remaining for this menu.']);
        }

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

        $weekmenu->decrement('quantity', $validated['quantity']);

        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'special_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $orderedQuantity = $order->quantity;

        if ($validated['quantity'] == 0) {
            $weekmenuId = $order->weekmenu_id;
            $order->delete();
            $weekmenu = Weekmenu::find($weekmenuId);
            if ($weekmenu) {
                $weekmenu->increment('quantity', $orderedQuantity);
            }
            return redirect()->back();
        }

        if ($validated['quantity'] !== $orderedQuantity) {
            $weekmenu = Weekmenu::find($order->weekmenu_id);
            if ($weekmenu) {
                $available = $weekmenu->quantity + $orderedQuantity;
                if ($validated['quantity'] > $available) {
                    return back()->withErrors(['quantity' => 'Only ' . $available . ' spots available for this menu.']);
                }
                $weekmenu->quantity = $available - $validated['quantity'];
                $weekmenu->save();
            }
        }

        $order->quantity = $validated['quantity'];
        $order->special_price = $validated['special_price'];
        $order->notes = $validated['notes'] ?? '';
        $order->save();

        return redirect()->back();
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $weekmenu = Weekmenu::find($order->weekmenu_id);
        
        if ($weekmenu) {
            $weekmenu->quantity += $order->quantity;
            $weekmenu->save();
        }
        
        $order->delete();
        
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