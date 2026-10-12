<?php

namespace App\Exports;

use App\Models\Order;
use App\Support\Formats;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** ADM-ORD upgrade: export of the filtered list (card fields, Qatar times). */
class OrdersExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    /** @param list<string> $ids */
    public function __construct(private readonly array $ids) {}

    public function query(): Builder
    {
        return Order::query()->whereIn('id', $this->ids)->with(['customer', 'event:id,title,event_date', 'posUser:id,name'])->latest();
    }

    public function headings(): array
    {
        return [
            __('admin.orders.cols.reference'), __('admin.orders.cols.status'), __('admin.orders.filters.event'), __('public.invoice.date'),
            __('public.invoice.name'), __('public.invoice.phone'), __('public.invoice.nationality'), __('admin.orders.cols.tickets'),
            __('admin.orders.cols.amount'), __('admin.orders.cols.method'), __('ops.shell.cashier'), __('admin.orders.cols.payment_id'),
            __('admin.orders.cols.booked_at'), __('admin.orders.cols.confirmed_at'),
        ];
    }

    /** @param Order $o */
    public function map($o): array
    {
        return [
            $o->booking_reference,
            __('enums.public_payment_status.'.$o->publicStatus()).($o->noteLabel() ? ' — '.$o->noteLabel() : ''),
            $o->event?->title,
            Formats::date($o->event?->event_date),
            $o->customer?->name,
            $o->customer ? PhoneNumber::international($o->customer->phone, $o->customer->country_code) : '',
            $o->customer?->nationality,
            $o->quantity,
            (float) $o->total_amount,
            __('enums.payment_method_short.'.$o->payment_method->value),
            $o->posUser?->name,
            $o->payment_id,
            Formats::dateTime($o->created_at),
            $o->confirmed_at ? Formats::dateTime($o->confirmed_at) : '',
        ];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $e) {
            $s = $e->sheet->getDelegate();
            $s->setRightToLeft(app()->getLocale() !== 'en');
            $s->getStyle('A1:N1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9C1638']],
            ]);
            $s->freezePane('A2');
        }];
    }
}
