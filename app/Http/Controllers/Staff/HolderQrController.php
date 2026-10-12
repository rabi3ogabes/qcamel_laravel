<?php

namespace App\Http\Controllers\Staff;

use App\Domain\Tickets\CheckInService;
use App\Domain\Tickets\QrService;
use App\Http\Controllers\Controller;
use App\Models\TicketHolder;
use Illuminate\Http\Response;

/** F-ADM-ORD-023 rebuild: holder QR PNG served server-side (800 px, margin 2, ECC H). */
class HolderQrController extends Controller
{
    public function __invoke(string $holder, QrService $qr): Response
    {
        $h = TicketHolder::query()->findOrFail($holder);
        $size = min(800, max(128, (int) request('s', 800)));

        return response($qr->png(CheckInService::signedPayload($h->code()), $size, 'H', 2), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
