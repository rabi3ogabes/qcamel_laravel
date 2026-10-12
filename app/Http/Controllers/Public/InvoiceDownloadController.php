<?php

namespace App\Http\Controllers\Public;

use App\Domain\Booking\Queries\PublicOrderQueries;
use App\Domain\Shared\Actor;
use App\Domain\Tickets\CheckInService;
use App\Domain\Tickets\QrService;
use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Settings\BrandingSettings;
use App\Support\Audit;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * F-PUB-081 rebuild: real server PDF (mPDF, Arabic shaping, RTL),
 * brand/season/year from settings (D-064), totals with 2 decimals.
 */
class InvoiceDownloadController extends Controller
{
    public function pdf(string $reference, PublicOrderQueries $q, QrService $qr): Response
    {
        $actor = Actor::current();
        $order = $q->invoice($reference, $actor) ?? abort(404);
        $branding = app(BrandingSettings::class);

        $qrs = [];
        foreach ($order->holders as $h) {
            if ($h->qr_code && ! $h->is_present && $this->admissible($order)) {
                $qrs[$h->id] = $qr->dataUri(CheckInService::signedPayload($h->code()), 360, 'M', 1);
            }
        }
        $html = view('invoice.pdf', [
            'order' => $order, 'qrs' => $qrs, 'logo' => $branding->logo_url,
            'festival' => $branding->festival_name, 'season' => $branding->season_label,
            'pii' => $actor->canSeePii(),
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 10, 'margin_right' => 10, 'margin_top' => 10, 'margin_bottom' => 10,
            'default_font' => 'dejavusans', 'autoScriptToLang' => true, 'autoLangToFont' => true, 'useOTL' => 0xFF,
            'tempDir' => storage_path('app/private/mpdf'),
        ]);
        $mpdf->SetDirectionality(app()->getLocale() === 'en' ? 'ltr' : 'rtl');
        $mpdf->SetTitle(__('public.invoice.title').' '.$order->booking_reference);
        $mpdf->WriteHTML($html);

        Order::query()->whereKey($order->id)->update(['invoice_downloaded_at' => now()]);
        Audit::log(ActivityType::InvoiceDownload, __('audit.invoice_download', ['ref' => $order->booking_reference]), $order, ['action' => 'invoice_download', 'booking_reference' => $order->booking_reference], $actor);

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-'.$order->booking_reference.'.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function admissible(Order $order): bool
    {
        return $order->payment_status->value === 'confirmed' || ($order->isCashAtVenue() && $order->payment_status->value === 'pending');
    }
}
