<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** stock:verify drift alert → Filament database notifications. */
class StockDriftDetected extends Notification
{
    use Queueable;

    /** @param list<array{id:string,event_id:string,type:string,old:int,new:int}> $drift */
    public function __construct(public array $drift) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return \Filament\Notifications\Notification::make()
            ->title(__('admin.notify.stock_drift_title'))
            ->body(__('admin.notify.stock_drift_body', ['n' => count($this->drift)]))
            ->warning()
            ->getDatabaseMessage();
    }
}
