<?php

namespace App\Filament\Resources\StaffAccounts\Pages;

use App\Filament\Resources\StaffAccounts\StaffAccountResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

/** F-ADM-SHL-016: hero + team accounts table. */
class ManageStaffAccounts extends ListRecords
{
    protected static string $resource = StaffAccountResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.stf.hero_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.stf.hero_sub');
    }

    protected function getHeaderActions(): array
    {
        return [StaffAccountResource::createAction()];
    }
}
