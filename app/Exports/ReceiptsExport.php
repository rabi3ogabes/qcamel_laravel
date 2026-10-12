<?php

namespace App\Exports;

use App\Support\Formats;
use App\Support\Money;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * F-RCP-019…021: styled card-receipt sheet — title row, headers, zebra
 * data rows and a summary block priced from settings (never hard-coded).
 */
class ReceiptsExport implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    /** @param array{normal:string,vip:string,parking:string} $prices */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $prices,
        private readonly string $title,
    ) {}

    public function title(): string
    {
        return 'POS Receipts';
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 15, 'C' => 15, 'D' => 10, 'E' => 10, 'F' => 10, 'G' => 22, 'H' => 12, 'I' => 15, 'J' => 20];
    }

    public function array(): array
    {
        $out = [[$this->title], [], ['#', 'Amount (QAR)', 'Seq Number', 'Normal', 'VIP', 'Parking', 'Card Number', 'Time', 'Auth Number', 'Date Added']];
        foreach ($this->rows->values() as $i => $r) {
            $out[] = [$i + 1, $r->amount_qar === null ? '' : (float) $r->amount_qar, $r->seq_number, $r->normal_tickets, $r->vip_tickets, $r->parking_tickets, $r->card_number_masked, $r->time, $r->auth_number, Formats::dateTime($r->created_at)];
        }
        $out[] = [];
        $sum = fn (string $t) => (int) $this->rows->sum($t.'_tickets');
        $amount = fn (string $t) => (float) Money::of($this->prices[$t])->multiply($sum($t))->toDecimal();
        $out[] = ['', 'pos', '', ''];
        foreach (['normal' => 'normal', 'vip' => 'VIP', 'parking' => 'parking'] as $k => $label) {
            $out[] = ['', $label, $sum($k), $amount($k)];
        }
        $out[] = ['', 'المجموع', $sum('normal') + $sum('vip') + $sum('parking'), $amount('normal') + $amount('vip') + $amount('parking')];

        return $out;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $e) {
            $s = $e->sheet->getDelegate();
            $s->setRightToLeft(false);
            $s->mergeCells('A1:J1');
            $s->getRowDimension(1)->setRowHeight(25);
            $s->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $s->getRowDimension(3)->setRowHeight(22);
            $s->getStyle('A3:J3')->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $n = $this->rows->count();
            for ($i = 0; $i < $n; $i++) {
                $row = 4 + $i;
                $s->getStyle("A$row:J$row")->applyFromArray([
                    'font' => ['size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $i % 2 === 0 ? 'F3F4F6' : 'FFFFFF']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }
            $start = 4 + $n + 1;
            $fills = ['D4E6F1', 'D5F5E3', 'FCF3CF', 'D6EAF8', 'F5CBA7'];
            foreach ($fills as $k => $rgb) {
                $row = $start + $k;
                $s->getStyle("B$row:D$row")->applyFromArray([
                    'font' => ['bold' => in_array($k, [0, 4], true)],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
            }
        }];
    }
}
