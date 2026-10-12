<?php

namespace App\Domain\Tickets;

use App\Domain\Shared\DomainError;
use App\Models\Order;
use App\Models\TicketHolder;
use App\Support\Text;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Server-side QR rendering only (D-023, R-065, R-066, R-189…R-191,
 * F-QR-003…011, T-044, T-101…T-105). No third-party fallback.
 */
class QrService
{
    /** Margin ≈ 2 modules for typical ticket codes (version 3–4). */
    public static function marginFor(int $size, int $modules = 2): int
    {
        return (int) round($size / 37 * $modules);
    }

    public function png(string $text, ?int $size = null, string $ecc = 'M', ?int $marginModules = null): string
    {
        $size ??= (int) config('qcamelmc.qr.size', 800);
        $marginModules ??= (int) config('qcamelmc.qr.margin', 2);

        return (new Builder(
            writer: new PngWriter,
            data: $text,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: self::ecc($ecc),
            size: $size,
            margin: self::marginFor($size, $marginModules),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build()->getString();
    }

    /** Inline SVG for on-screen presets (F-QR-007 rebuild). */
    public function svg(string $text, int $size = 192, string $ecc = 'H', int $marginModules = 1): string
    {
        $svg = (new Builder(
            writer: new SvgWriter,
            writerOptions: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
            data: $text,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: self::ecc($ecc),
            size: $size,
            margin: self::marginFor($size, $marginModules),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build()->getString();

        return $svg;
    }

    public function dataUri(string $text, int $size = 512, string $ecc = 'M', int $marginModules = 1): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($text, $size, $ecc, $marginModules));
    }

    private static function ecc(string $ecc): ErrorCorrectionLevel
    {
        return match (strtoupper($ecc)) {
            'L' => ErrorCorrectionLevel::Low,
            'Q' => ErrorCorrectionLevel::Quartile,
            'H' => ErrorCorrectionLevel::High,
            default => ErrorCorrectionLevel::Medium,
        };
    }

    /** R-066 / T-104: "<code>.png" only for safe names. */
    public static function objectName(string $code): string
    {
        if (! Text::isSafeQrName($code)) {
            throw new DomainError('invalid_request', 400, 'A QR text and a safe file name are required');
        }

        return $code.'.png';
    }

    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk((string) config('qcamelmc.qr.disk', 'qr'));
    }

    /** Render + store a holder's picture; the code itself never changes (R-065). */
    public function renderHolder(TicketHolder $holder): bool
    {
        $code = (string) $holder->qr_code;
        try {
            $name = self::objectName($code);
        } catch (DomainError) {
            $holder->forceFill(['qr_failed' => true])->saveQuietly(); // F-QR-009 rebuild: not re-listed forever
            Log::warning('QR: unsafe ticket code', ['holder' => $holder->id]);

            return false;
        }
        try {
            $this->disk()->put($name, $this->png($code));
            $holder->forceFill(['qr_image_url' => $this->disk()->url($name), 'qr_failed' => false])->saveQuietly();

            return true;
        } catch (Throwable $e) {
            Log::warning('QR render failed', ['holder' => $holder->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * F-QR-011 / T-101 admin tool: arbitrary text → {url}; refuses unsafe names
     * and never overwrites a picture that belongs to an existing ticket code.
     */
    public function generate(string $text, string $filename): string
    {
        $text = trim($text);
        $filename = trim($filename);
        if ($text === '' || mb_strlen($text) > 500 || ! Text::isSafeQrName($filename)) {
            throw new DomainError('invalid_request', 400, 'A QR text and a safe file name are required');
        }
        if (TicketHolder::query()->where('qr_code', $filename)->exists() && $text !== $filename) {
            throw new DomainError('invalid_request', 400, 'This file name belongs to an existing ticket');
        }
        $name = $filename.'.png';
        $this->disk()->put($name, $this->png($text));

        return $this->disk()->url($name);
    }

    /**
     * R-191 / T-102 backfill: ≤ 100 holders missing a picture per run,
     * continues past failures, never changes codes.
     *
     * @return array{success:bool, updated:int, skipped:int, errors:list<string>, remaining:bool}
     */
    public function backfill(int $batch = 100): array
    {
        $holders = TicketHolder::query()
            ->whereNull('qr_image_url')->whereNotNull('qr_code')->where('qr_failed', false)
            ->orderBy('id')->limit($batch)->get();
        $updated = 0;
        $errors = [];
        foreach ($holders as $h) {
            if ($this->renderHolder($h)) {
                $updated++;
            } else {
                $errors[] = $h->qr_code.': render failed';
            }
        }

        return ['success' => true, 'updated' => $updated, 'skipped' => 0, 'errors' => $errors, 'remaining' => $holders->count() === $batch];
    }

    /**
     * F-QR-010 / T-103: re-render every holder of one booking.
     *
     * @return array{success:bool, updated:int, total:int, errors:list<string>}
     */
    public function regenerateBooking(string $reference): array
    {
        if (Text::reference($reference) === null) {
            throw new DomainError('invalid_reference', 400, 'Invalid booking reference');
        }
        $order = Order::query()->where('booking_reference', $reference)->first();
        if (! $order) {
            throw DomainError::notFound();
        }
        $holders = $order->holders()->reorder('qr_code')->get();
        $updated = 0;
        $errors = [];
        foreach ($holders as $h) {
            $this->renderHolder($h) ? $updated++ : $errors[] = $h->qr_code.': render failed';
        }

        return ['success' => true, 'updated' => $updated, 'total' => $holders->count(), 'errors' => $errors];
    }
}
