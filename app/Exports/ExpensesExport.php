<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpensesExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    /** @param Collection $expenses expenses with their items loaded */
    public function __construct(protected Collection $expenses) {}

    public function collection()
    {
        return $this->expenses->flatMap(fn ($expense) => $expense->items->map(fn ($item) => [
            $expense->purchased_at->format('Y-m-d'),
            $expense->store,
            $item->name,
            $item->category,
            $item->quantity,
            $item->price,
        ]));
    }

    public function headings(): array
    {
        return ['Date', 'Store', 'Item', 'Category', 'Qty', 'Price'];
    }

    public function columnWidths(): array
    {
        return ['A' => 12, 'B' => 22, 'C' => 40, 'D' => 14, 'E' => 8, 'F' => 10];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
