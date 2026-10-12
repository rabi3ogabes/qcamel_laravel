<?php

namespace App\Filament\Resources\KioskDevices\Pages;

use App\Filament\Resources\KioskDevices\KioskDeviceResource;
use Filament\Resources\Pages\ListRecords;

class ListKioskDevices extends ListRecords
{
    protected static string $resource = KioskDeviceResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.sec.devices_sub', ['days' => config('qcamelmc.auth.passcode_session_days', 10)]);
    }

    protected function getHeaderActions(): array
    {
        return [KioskDeviceResource::revokeAllAction()];
    }
}
