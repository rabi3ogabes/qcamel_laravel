<?php

namespace App\Exports;

use App\Models\TicketHolder;
use App\Support\Formats;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** ADM-TKT upgrade: booked tickets of a day (name, phone, type, reference, payment, scan). */
class BookedTicketsExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    /** @param list<string> $ids */
    public function __construct(private readonly array $ids) {}

    public function query(): Builder
    {
        return TicketHolder::query()->whereIn('id', $this->ids)->with(['order:id,booking_reference,payment_status,event_id', 'order.event:id,title,event_date'])->latest();
    }

    public function headings(): array
    {
        return [__('admin.booked.cols.holder'), __('public.invoice.phone'), __('public.invoice.nationality'), __('admin.orders.cols.tickets'), __('admin.orders.cols.reference'),
            __('admin.orders.filters.event'), __('admin.booked.cols.payment'), __('admin.booked.cols.scan'), __('admin.booked.scan_time'), __('admin.booked.scanned_by'), 'QR'];
    }

    /** @param TicketHolder $h */
    public function map($h): array
    {
        return [
            $h->name, $h->displayPhone(), $h->nationality, $h->ticket_type->label(), $h->order?->booking_reference,
            $h->order?->event?->title, $h->order ? __('admin.trx.status.'.$h->order->payment_status->value) : '',
            $h->is_present ? __('admin.booked.scanned') : __('admin.booked.not_scanned'),
            $h->checked_in_at ? Formats::dateTime($h->checked_in_at) : '', $h->checked_in_by_name, $h->qr_code,
        ];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $e) {
            $s = $e->sheet->getDelegate();
            $s->setRightToLeft(app()->getLocale() !== 'en');
            $s->getStyle('A1:K1')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9C1638']]]);
            $s->freezePane('A2');
        }];
    }
}
