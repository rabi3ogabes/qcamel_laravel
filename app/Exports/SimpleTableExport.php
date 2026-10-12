<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Generic backup / template sheet (F-ADM-SET-034/036/038/040). */
class SimpleTableExport implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  list<string>|null  $headings  defaults to the first row's keys
     */
    public function __construct(private readonly array $rows, private readonly ?array $headings = null, private readonly string $title = 'Data') {}

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return $this->headings ?? array_keys($this->rows[0] ?? []);
    }

    public function array(): array
    {
        $keys = $this->headings();

        return array_map(function ($r) use ($keys) {
            $out = [];
            foreach ($keys as $k) {
                $v = $r[$k] ?? null;
                $out[] = is_bool($v) ? ($v ? 'true' : 'false') : (is_scalar($v) || $v === null ? $v : json_encode($v, JSON_UNESCAPED_UNICODE));
            }

            return $out;
        }, $this->rows);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $e) {
            $s = $e->sheet->getDelegate();
            $last = $s->getHighestColumn();
            $s->getStyle("A1:{$last}1")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']]]);
            $s->freezePane('A2');
        }];
    }
}
