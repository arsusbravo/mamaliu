<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    public function getByUser($userId)
    {
        $invoices = Invoice::where('user_id', $userId)
            ->with('invoiceItems.order.weekmenu.menu')
            ->orderBy('invoice_date', 'desc')
            ->get()
            ->map(function ($invoice) {
                $total = $invoice->invoiceItems->sum(function ($item) {
                    $price = $item->order->special_price ?? $item->order->weekmenu->menu->price;
                    return $item->order->quantity * $price;
                });
                
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date->toISOString(),
                    'total' => $total,
                    'items_count' => $invoice->invoiceItems->count(),
                ];
            });

        return response()->json([
            'invoices' => $invoices,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string',
            'invoice_date' => 'required|date',
            'user_id' => 'required|exists:users,id',
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
        ]);

        // Check if invoice with this number already exists for this user
        $invoice = Invoice::where('invoice_number', $validated['invoice_number'])
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($invoice) {
            // Update existing invoice
            $invoice->update([
                'invoice_date' => $validated['invoice_date'],
            ]);
            
            // Delete old invoice items
            InvoiceItem::where('invoice_id', $invoice->id)->delete();
        } else {
            // Create new invoice
            $invoice = Invoice::create([
                'user_id' => $validated['user_id'],
                'invoice_number' => $validated['invoice_number'],
                'invoice_date' => $validated['invoice_date'],
            ]);
        }

        // Create new invoice items
        foreach ($validated['order_ids'] as $orderId) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'order_id' => $orderId,
            ]);
        }

        return redirect()->back();
    }

    public function pdf($id)
    {
        $invoice = Invoice::with([
            'user',
            'invoiceItems.order.weekmenu.menu'
        ])->findOrFail($id);

        $items = $invoice->invoiceItems->map(function ($item) {
            $price = $item->order->special_price ?? $item->order->weekmenu->menu->price;
            $total = $item->order->quantity * $price;
            
            return [
                'menu_label' => $item->order->weekmenu->menu->label,
                'quantity' => $item->order->quantity,
                'price' => $price,
                'total' => $total,
            ];
        });

        $totalInclTax = $items->sum('total');
        $totalExclTax = $totalInclTax / 1.09;
        $vat = $totalInclTax - $totalExclTax;

        return view('invoices.pdf', [
            'invoice' => $invoice,
            'items' => $items,
            'totalInclTax' => $totalInclTax,
            'totalExclTax' => $totalExclTax,
            'vat' => $vat,
        ]);
    }
}