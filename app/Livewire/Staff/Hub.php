<?php

namespace App\Livewire\Staff;

use App\Domain\Shared\Actor;
use App\Models\Order;
use App\Support\QatarClock;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * F-STF-008…011: team hub — identity badge, permission-filtered quick links
 * (same tab; one session covers every staff page), admin dashboard link.
 */
#[Layout('components.layouts.staff')]
class Hub extends Component
{
    public function render()
    {
        $actor = Actor::current();
        $links = collect([
            ['route' => 'staff.pos', 'perm' => 'pos.sell', 'icon' => 'cash', 'label' => 'link_pos', 'tone' => 'from-maroon-600 to-maroon-700'],
            ['route' => 'staff.scanner', 'perm' => 'scanner.admit', 'icon' => 'scan', 'label' => 'link_scanner', 'tone' => 'from-ink to-[#3a2420]'],
            ['route' => 'staff.live', 'perm' => 'live.view', 'icon' => 'live', 'label' => 'link_live', 'tone' => 'from-terracotta to-[#8a4330]'],
            ['route' => 'staff.receipts', 'perm' => 'receipts.capture', 'icon' => 'receipt', 'label' => 'link_receipts', 'tone' => 'from-gold-500 to-gold-700'],
            ['route' => 'staff.attendance', 'perm' => 'attendance.view', 'icon' => 'user-check', 'label' => 'link_attendance', 'tone' => 'from-emerald-600 to-emerald-800'],
            ['route' => 'staff.gate', 'perm' => 'gate.view', 'icon' => 'gate', 'label' => 'link_gate', 'tone' => 'from-sky-600 to-sky-800'],
        ])->filter(fn ($l) => $actor->can($l['perm']))->values();

        $badge = $actor->isKiosk() ? ['badge_kiosk', 'warning'] : ($actor->isAdmin() ? ['badge_admin', 'maroon'] : ['badge_staff', 'success']);

        $mySales = null;
        if ($actor->isAccount() && $actor->can('pos.sell')) {
            [$from, $to] = QatarClock::dayRange(QatarClock::today());
            $q = Order::query()->where('source', 'pos')->where('payment_status', 'confirmed')
                ->whereBetween('created_at', [$from, $to])->where('confirmed_by', $actor->userId());
            $mySales = ['orders' => (clone $q)->count(), 'tickets' => (int) (clone $q)->sum('quantity'), 'amount' => (string) ((clone $q)->sum('total_amount') ?: '0')];
        }

        return view('livewire.staff.hub', [
            'actor' => $actor,
            'links' => $links,
            'badge' => $badge,
            'mySales' => $mySales,
            'canAdmin' => $actor->isAccount() && auth()->user()?->canAccessPanel(filament()->getPanel('admin')),
        ])->title(__('staff.hub_title'));
    }
}
