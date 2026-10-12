<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PosReceipt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** D-006 / T-123: private receipt image behind auth + a 5-minute signed URL. */
class ReceiptImageController extends Controller
{
    public function __invoke(PosReceipt $receipt): StreamedResponse
    {
        abort_unless($receipt->image_path && Storage::disk('receipts')->exists($receipt->image_path), 404);

        return Storage::disk('receipts')->response($receipt->image_path, null, ['Cache-Control' => 'private, no-store']);
    }
}
