<?php

namespace App\Filament\Pages;

use App\Domain\Shared\Actor;
use App\Filament\Concerns\InNavigationGroup;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** F-ADM-STF-013: the shared attendance board embedded in the admin panel. */
class TeamAttendance extends Page
{
    use InNavigationGroup;

    protected string $view = 'filament.pages.team-attendance';

    protected static string $navGroup = 'team';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getNavigationLabel(): string
    {
        return __('admin.stf.attendance');
    }

    public function getTitle(): string
    {
        return __('admin.stf.attendance');
    }

    public static function canAccess(): bool
    {
        return Actor::current()->can('attendance.view');
    }
}
