<?php

namespace App\Filament\Resources\PosUsers\Pages;

use App\Filament\Resources\PosUsers\PosUserResource;
use Filament\Resources\Pages\ListRecords;

class ManagePosUsers extends ListRecords
{
    protected static string $resource = PosUserResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.pos.sub');
    }

    protected function getHeaderActions(): array
    {
        return [PosUserResource::createAction()];
    }
}
