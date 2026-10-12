<?php

namespace App\Filament\Resources\PaymentErrors\Pages;

use App\Filament\Resources\PaymentErrors\PaymentErrorResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentErrors extends ListRecords
{
    protected static string $resource = PaymentErrorResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.perr.subtitle');
    }
}
