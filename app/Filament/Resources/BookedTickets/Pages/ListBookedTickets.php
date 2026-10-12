<?php

namespace App\Filament\Resources\BookedTickets\Pages;

use App\Exports\BookedTicketsExport;
use App\Filament\Resources\BookedTickets\BookedTicketResource;
use App\Filament\Resources\DeletedTickets\DeletedTicketResource;
use App\Support\QatarClock;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;

class ListBookedTickets extends ListRecords
{
    protected static string $resource = BookedTicketResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.booked.subtitle');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label(__('admin.common.export_xlsx'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn () => Excel::download(new BookedTicketsExport($this->getFilteredTableQuery()->pluck('ticket_holders.id')->all()), 'tickets-'.QatarClock::today().'.xlsx')),
            Action::make('bin')->label(__('admin.booked.bin_title'))->icon(Heroicon::OutlinedTrash)->color('gray')
                ->visible(fn () => DeletedTicketResource::canViewAny())
                ->url(DeletedTicketResource::getUrl()),
        ];
    }
}
