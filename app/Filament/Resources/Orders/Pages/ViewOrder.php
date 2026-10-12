<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\OrderActions;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return __('admin.orders.label').' '.$this->record->booking_reference;
    }

    protected function getHeaderActions(): array
    {
        return [
            OrderActions::tickets(),
            OrderActions::verify(),
            OrderActions::confirm(),
            OrderActions::cancel(),
            ActionGroup::make([
                OrderActions::moveEvent(), OrderActions::toggleVerified(), OrderActions::refundHandled(),
                OrderActions::sendWhatsApp(), OrderActions::sendEmail(), OrderActions::regenerateQr(), OrderActions::delete(),
            ])->icon(Heroicon::OutlinedEllipsisHorizontal)->button()->label(__('admin.common.more'))->color('gray'),
        ];
    }
}
