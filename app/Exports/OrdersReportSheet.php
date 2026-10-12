<?php

namespace App\Exports;

use App\Enums\TicketKind;
use App\Models\Order;
use App\Support\Formats;
use App\Support\QatarClock;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** One styled RTL sheet of the orders report (F-ADM-RPT-003, 008…010). */
class OrdersReportSheet implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithTitle
{
    private const STATUS_COL = 'E';

    private const AMOUNT_COL = 'F';

    /** @param Collection<int, Order> $orders */
    public function __construct(private readonly bool $pii, private readonly string $title, private readonly Collection $orders) {}

    private function id(?string $v): string
    {
        if (! $v) {
            return '-';
        }

        return $this->pii ? $v : str_repeat('•', max(0, mb_strlen($v) - 4)).mb_substr($v, -4);
    }

    public function title(): string
    {
        return mb_substr(str_replace(['*', ':', '/', '\\', '?', '[', ']'], '-', $this->title), 0, 31);
    }

    public function headings(): array
    {
        return array_map(fn ($k) => __('admin.rpt.cols.'.$k), [
            'event', 'payment_id', 'method', 'tickets', 'status', 'amount', 'reference', 'customer', 'email', 'phone',
            'nationality', 'id_number', 'event_date', 'location', 'booked_at', 'holders', 'holder_phones', 'holder_ids', 'present', 'qr_count',
        ]);
    }

    public function array(): array
    {
        return $this->orders->map(function (Order $o) {
            $holders = $o->holders;
            $breakdown = $holders->groupBy(fn ($h) => is_object($h->ticket_type) ? $h->ticket_type->value : $h->ticket_type)
                ->map(fn ($g, $t) => $g->count().' '.(TicketKind::tryFrom((string) $t)?->label() ?? $t))->implode(' + ');
            $c = $o->customer;

            return [
                $o->event?->title ?? '-',
                $o->payment_id ?: '-',
                $o->payment_method?->label() ?? '-',
                $breakdown ?: (string) $o->quantity,
                $o->payment_status?->label() ?? '-',
                (float) $o->total_amount,
                $o->booking_reference,
                $c?->name ?? '-',
                $c?->email ?: '-',
                $c ? trim(($c->country_code ? '+'.ltrim($c->country_code, '+').' ' : '').$c->phone) : '-',
                $c?->nationality ?: '-',
                $this->id($c?->id_number),
                $o->event ? QatarClock::local($o->event->event_date)->format('d/m/Y H:i') : '-',
                $o->event?->location ?? '-',
                QatarClock::local($o->created_at)->format('d/m/Y H:i'),
                $holders->pluck('name')->filter()->implode('، ') ?: '-',
                $holders->pluck('phone')->filter()->unique()->implode('، ') ?: '-',
                $holders->pluck('id_number')->filter()->map(fn ($v) => $this->id($v))->implode('، ') ?: '-',
                $holders->where('is_present', true)->count().' / '.$holders->count(),
                $holders->count(),
            ];
        })->all();
    }

    public function columnWidths(): array
    {
        $w = [25, 20, 15, 22, 15, 15, 30, 22, 28, 18, 14, 16, 18, 22, 18, 40, 30, 30, 10, 10];
        $out = [];
        foreach ($w as $i => $v) {
            $out[chr(65 + $i)] = $v;
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $e) {
            $s = $e->sheet->getDelegate();
            $s->setRightToLeft(app()->getLocale() === 'ar');
            $last = 'T';
            $n = $this->orders->count() + 1;

            $s->getStyle("A1:{$last}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
            $s->getRowDimension(1)->setRowHeight(25);
            $s->freezePane('A2');

            if ($n > 1) {
                $s->getStyle("A2:{$last}{$n}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']]],
                ]);
                $s->getStyle(self::AMOUNT_COL."2:".self::AMOUNT_COL.$n)->getNumberFormat()->setFormatCode('#,##0.00');
                $s->getStyle(self::AMOUNT_COL."2:".self::AMOUNT_COL.$n)->getFont()->setBold(true);
                $tones = ['confirmed' => ['D4EDDA', '155724'], 'pending' => ['FFF3CD', '856404'], 'cancelled' => ['F8D7DA', '721C24']];
                foreach ($this->orders->values() as $i => $o) {
                    if ($t = $tones[$o->payment_status?->value] ?? null) {
                        $cell = self::STATUS_COL.($i + 2);
                        $s->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($t[0]);
                        $s->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB($t[1]);
                    }
                }
            }

            // F-ADM-RPT-010 rebuild: totals per status, confirmed highlighted.
            $row = $n + 1;
            foreach (['confirmed' => '2E7D32', 'pending' => 'B7791F', 'cancelled' => '9B2C2C'] as $status => $rgb) {
                $sum = $this->orders->filter(fn (Order $o) => $o->payment_status?->value === $status)->sum(fn (Order $o) => (float) $o->total_amount);
                $s->setCellValue(self::STATUS_COL.$row, __('admin.rpt.total_'.$status));
                $s->setCellValue(self::AMOUNT_COL.$row, $sum);
                $s->getStyle(self::STATUS_COL.$row.':'.self::AMOUNT_COL.$row)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM], 'bottom' => ['borderStyle' => Border::BORDER_MEDIUM]],
                ]);
                $s->getStyle(self::AMOUNT_COL.$row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }
        }];
    }
}
