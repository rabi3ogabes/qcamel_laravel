<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** NEW admin alert for paid_after_expiry_no_stock / paid_after_staff_cancel / review decisions (F-PAY upgrades). */
class PaymentNeedsReview extends Notification
{
    use Queueable;

    public function __construct(public Order $order, public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return \Filament\Notifications\Notification::make()
            ->title(__('admin.notify.review_title', ['ref' => $this->order->booking_reference]))
            ->body(__('enums.payment_outcome.'.$this->reason) !== 'enums.payment_outcome.'.$this->reason ? __('enums.payment_outcome.'.$this->reason) : $this->reason)
            ->danger()
            ->getDatabaseMessage();
    }
}
