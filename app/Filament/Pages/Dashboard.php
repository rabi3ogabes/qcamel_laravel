<?php

namespace App\Filament\Pages;

use App\Support\QatarClock;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/** Admin home: today's KPIs, sales trend, ticket mix, latest bookings. */
class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    public static function getNavigationLabel(): string
    {
        return __('admin.dash.nav');
    }

    public function getTitle(): string
    {
        $h = (int) QatarClock::now()->format('G');

        return __($h < 12 ? 'admin.dash.morning' : 'admin.dash.evening', ['name' => strtok((string) auth()->user()?->name, ' ')]);
    }

    public function getSubheading(): ?string
    {
        return \App\Support\Formats::longDate(QatarClock::now());
    }

    public function getColumns(): int|array
    {
        return ['md' => 3];
    }
}
