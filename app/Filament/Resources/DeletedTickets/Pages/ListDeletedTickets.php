<?php

namespace App\Filament\Resources\DeletedTickets\Pages;

use App\Filament\Resources\DeletedTickets\DeletedTicketResource;
use Filament\Resources\Pages\ListRecords;

class ListDeletedTickets extends ListRecords
{
    protected static string $resource = DeletedTicketResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.booked.bin_hint');
    }
}
