<?php

use App\Domain\Shared\Actor;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Part 4.5 channel authorisation — same rules as HTTP:
| private-admin = admin/accountant/viewer; private-event.* / private-staff =
| any staff incl. kiosk (kiosk resolved from its cookie); order.* = admins.
*/

Broadcast::channel('admin', fn (?User $user) => $user?->is_active && $user->canAccessPanel(filament()->getPanel('admin')));

Broadcast::channel('event.{eventId}', function (?User $user) {
    $actor = Actor::current();

    return $actor->isStaff() || ($user?->canAccessPanel(filament()->getPanel('admin')) ?? false);
});

Broadcast::channel('staff', fn (?User $user) => Actor::current()->isStaff());

Broadcast::channel('order.{orderId}', function (?User $user, string $orderId) {
    $actor = Actor::current();

    return ($actor->isStaff() || ($user?->canAccessPanel(filament()->getPanel('admin')) ?? false)) && Order::query()->whereKey($orderId)->exists();
});
