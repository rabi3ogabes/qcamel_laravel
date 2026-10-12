<?php

namespace App\Livewire\Staff;

use App\Domain\Tickets\GateBoardQuery;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * F-STF-012…017: big-screen gate board — issued vs entered for the current
 * event (issued = admissible tickets, so entered never exceeds issued),
 * per-type tiles, last 25 scans, per-staff counts; silent 15 s refresh.
 */
#[Layout('components.layouts.staff', ['theme' => 'dark'])]
class GateBoard extends Component
{
    public bool $failed = false;

    public function render()
    {
        try {
            $data = app(GateBoardQuery::class)->forEvent();
            $this->failed = false;
        } catch (Throwable $e) {
            report($e);
            $data = null;
            $this->failed = true;
        }

        return view('livewire.staff.gate-board', ['d' => $data])->title(__('ops.gate.eyebrow'));
    }
}
