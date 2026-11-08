<?php

namespace App\Http\Controllers;

use App\Exports\ExpensesExport;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Services\ReceiptScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'category' => 'nullable|string|max:100',
        ]);

        $expenses = $this->filtered($filters)
            ->with('items')
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Totals cover the whole filtered range, not just the visible page
        $byCategory = $this->itemsQuery($filters)
            ->selectRaw("coalesce(expense_items.category, 'Uncategorised') as category, sum(expense_items.price) as total")
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['category' => $row->category, 'total' => round((float) $row->total, 2)]);

        return inertia('expenses/Index', [
            'expenses' => $expenses,
            'filters' => [
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'category' => $filters['category'] ?? null,
            ],
            'categories' => config('expenses.categories'),
            'totals' => [
                'overall' => round((float) $byCategory->sum('total'), 2),
                'by_category' => $byCategory,
            ],
            'scannerConfigured' => (bool) config('services.openrouter.key'),
        ]);
    }

    public function scan(Request $request, ReceiptScanner $scanner)
    {
        $request->validate([
            'receipt' => 'required|file|max:15360|mimes:jpeg,jpg,png,gif,webp,heic,heif',
        ]);

        try {
            return response()->json($scanner->scan($request->file('receipt')));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($validated) {
            $expense = Expense::create($this->expenseAttributes($validated) + [
                'receipt_image' => $validated['receipt_image'] ?? null,
            ]);
            $expense->items()->createMany($validated['items']);
        });

        return redirect()->route('admin.expenses_index')->with('success', 'Expense saved.');
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($validated, $expense) {
            $expense->update($this->expenseAttributes($validated));
            $expense->items()->delete();
            $expense->items()->createMany($validated['items']);
        });

        return redirect()->back()->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        // Several receipts can come from one photo: only remove the file once nothing uses it
        if ($expense->receipt_image && ! Expense::where('receipt_image', $expense->receipt_image)->exists()) {
            Storage::disk('local')->delete($expense->receipt_image);
        }

        return redirect()->back()->with('success', 'Expense deleted.');
    }

    public function receipt(Expense $expense)
    {
        abort_unless($expense->receipt_image && Storage::disk('local')->exists($expense->receipt_image), 404);

        return response()->file(Storage::disk('local')->path($expense->receipt_image));
    }

    public function export(Request $request)
    {
        $filters = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'category' => 'nullable|string|max:100',
        ]);

        $expenses = $this->filtered($filters)->with('items')->orderBy('purchased_at')->orderBy('id')->get();

        // With a category filter, only export the matching lines
        if (! empty($filters['category'])) {
            $expenses->each(fn ($e) => $e->setRelation('items', $e->items->where('category', $filters['category'])->values()));
        }

        return Excel::download(new ExpensesExport($expenses), 'expenses.xlsx');
    }

    private function rules(): array
    {
        return [
            'store' => 'nullable|string|max:255',
            'purchased_at' => 'required|date',
            'total' => 'nullable|numeric',
            'notes' => 'nullable|string|max:1000',
            // Only paths produced by the scanner are accepted
            'receipt_image' => ['nullable', 'regex:/^receipts\/[0-9a-f\-]{36}\.jpg$/'],
            'items' => 'required|array|min:1|max:200',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01|max:99999',
            'items.*.price' => 'required|numeric|min:-99999|max:99999',
            'items.*.category' => 'nullable|string|in:' . implode(',', config('expenses.categories')),
        ];
    }

    private function expenseAttributes(array $validated): array
    {
        return [
            'store' => $validated['store'] ?? null,
            'purchased_at' => $validated['purchased_at'],
            'total' => $validated['total'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    /** Expenses inside the date range (and having items of the chosen category). */
    private function filtered(array $filters)
    {
        return Expense::query()
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('purchased_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('purchased_at', '<=', $to))
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->whereHas('items', fn ($i) => $i->where('category', $category)));
    }

    private function itemsQuery(array $filters)
    {
        return ExpenseItem::query()
            ->join('expenses', 'expenses.id', '=', 'expense_items.expense_id')
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('expenses.purchased_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('expenses.purchased_at', '<=', $to))
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('expense_items.category', $category));
    }
}
