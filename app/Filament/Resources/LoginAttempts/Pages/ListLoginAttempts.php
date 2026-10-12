<?php

namespace App\Filament\Resources\LoginAttempts\Pages;

use App\Filament\Resources\LoginAttempts\LoginAttemptResource;
use Filament\Resources\Pages\ListRecords;

class ListLoginAttempts extends ListRecords
{
    protected static string $resource = LoginAttemptResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.sec.attempts_sub', [
            'max' => config('qcamelmc.auth.login_max', 5),
            'window' => config('qcamelmc.auth.login_window', 15),
            'block' => config('qcamelmc.auth.login_block', 30),
        ]);
    }
}
