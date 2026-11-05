<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(
        protected Collection $data,
        protected array $headings,
        protected array $sectionHeaderRows,
        protected array $totalRows,
        protected ?int $notesHeaderRow,
    ) {}

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
        $colLetter = fn($i) => Coordinate::stringFromColumnIndex($i);
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
                    'fillType' => Fill::FILL_SOLID,
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
}
