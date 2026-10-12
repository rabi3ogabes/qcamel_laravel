<?php

namespace App\Domain\Receipts;

use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Events\PosReceiptChanged;
use App\Models\Event;
use App\Models\PosReceipt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * POS card-slip OCR (F-RCP-005…011, R-188, D-006, D-077, T-123): vision LLM
 * extraction FIRST, then the image is stored on the PRIVATE receipts disk
 * (no orphans), values validated, duplicates (SEQ+AUTH+amount) refused,
 * created_by recorded. Provider is configurable (Gemini default; OpenAI-compatible).
 */
class ParseReceiptAction
{
    public const PROMPT = 'You are an AI specialized in reading POS receipt images. Extract ONLY these fields and return ONLY a JSON object: '
        .'amount_qar (number), seq_number (string), card_number_masked (string), time (HH:MM:SS), auth_number (string). Rules: amount_qar is a number '
        .'without currency symbols (e.g. 1000 not "QAR 1000.00"); seq_number must match SEQ NO / SEQ NUMBER; card_number_masked keeps the masking '
        .'exactly (**** allowed); time is 24-hour HH:MM:SS; auth_number must match AUTH NO / AUTH NUMBER; missing or unreadable → null; do NOT guess; '
        .'no explanations; return ONLY the JSON object.';

    /** @return array{amount_qar:?string, seq_number:?string, card_number_masked:?string, time:?string, auth_number:?string} */
    public function extract(string $binary, string $mime): array
    {
        $key = (string) config('services.ai.key');
        if ($key === '') {
            throw new DomainError('ai_not_configured', 503, __('receipts.ai_not_configured'));
        }
        $provider = (string) config('qcamelmc.ai.provider', 'gemini');
        $model = (string) config('qcamelmc.ai.model', 'gemini-2.5-flash');
        $b64 = base64_encode($binary);

        try {
            if ($provider === 'gemini') {
                $res = Http::timeout((int) config('qcamelmc.ai.timeout', 30))->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}", [
                    'contents' => [['parts' => [
                        ['text' => self::PROMPT."\nExtract the receipt data from this POS receipt image and return it as JSON."],
                        ['inline_data' => ['mime_type' => $mime, 'data' => $b64]],
                    ]]],
                    'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0],
                ]);
                $text = (string) data_get($res->json(), 'candidates.0.content.parts.0.text', '');
            } else {
                $res = Http::timeout((int) config('qcamelmc.ai.timeout', 30))->withToken($key)->post((string) config('services.ai.base_url', 'https://api.openai.com/v1').'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => self::PROMPT],
                        ['role' => 'user', 'content' => [
                            ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$b64}"]],
                            ['type' => 'text', 'text' => 'Extract the receipt data from this POS receipt image and return it as JSON.'],
                        ]],
                    ],
                ]);
                $text = (string) data_get($res->json(), 'choices.0.message.content', '');
            }
        } catch (Throwable) {
            throw new DomainError('ai_gateway_error', 502, __('receipts.ai_error'));
        }

        if ($res->status() === 429) {
            throw new DomainError('rate_limited', 429, __('receipts.rate_limited'));
        }
        if ($res->status() === 402) {
            throw new DomainError('payment_required', 402, __('receipts.credits'));
        }
        if (! $res->successful()) {
            throw new DomainError('ai_gateway_error', 502, __('receipts.ai_error'));
        }
        $text = preg_replace('/^```(?:json)?|```$/m', '', trim($text)) ?? $text;
        $data = json_decode(trim($text), true);
        if (! is_array($data)) {
            throw new DomainError('parse_failed', 422, __('receipts.parse_failed'));
        }

        return self::sanitize($data);
    }

    /** F-RCP-009 rebuild validation; never store a full PAN. */
    public static function sanitize(array $d): array
    {
        $amount = is_numeric($d['amount_qar'] ?? null) && (float) $d['amount_qar'] >= 0 ? number_format((float) $d['amount_qar'], 2, '.', '') : null;
        $alnum = fn ($v) => is_scalar($v) && preg_match('/^[A-Za-z0-9]{1,20}$/', trim((string) $v)) ? trim((string) $v) : null;
        $time = is_string($d['time'] ?? null) && preg_match('/^\d{2}:\d{2}:\d{2}$/', trim($d['time'])) ? trim($d['time']) : null;
        $card = is_string($d['card_number_masked'] ?? null) ? trim($d['card_number_masked']) : null;
        if ($card !== null) {
            if (preg_match('/\d{13,19}/', preg_replace('/\s+/', '', $card) ?? '')) {
                $digits = preg_replace('/\D/', '', $card) ?? '';
                $card = '**** **** **** '.substr($digits, -4);
            }
            if (! preg_match('/^[\d*X ]{4,19}$/i', $card)) {
                $card = null;
            }
        }

        return ['amount_qar' => $amount, 'seq_number' => $alnum($d['seq_number'] ?? null), 'card_number_masked' => $card, 'time' => $time, 'auth_number' => $alnum($d['auth_number'] ?? null)];
    }

    public function handle(UploadedFile $file, Actor $actor, ?string $eventId = null): PosReceipt
    {
        $binary = (string) file_get_contents($file->getRealPath());
        $mime = $file->getMimeType() ?: 'image/jpeg';
        $data = $this->extract($binary, $mime); // OCR first (D-077: no orphan files)

        if ($data['seq_number'] && $data['auth_number'] && $data['amount_qar']
            && PosReceipt::query()->where('seq_number', $data['seq_number'])->where('auth_number', $data['auth_number'])->where('amount_qar', $data['amount_qar'])->exists()) {
            throw new DomainError('duplicate', 409, __('receipts.duplicate'));
        }

        $path = 'receipts/'.now()->format('Y/m').'/'.Str::uuid().'.'.($file->guessExtension() ?: 'jpg');
        Storage::disk('receipts')->put($path, $binary);
        try {
            $receipt = PosReceipt::create($data + [
                'image_path' => $path,
                'ocr_raw' => $data,
                'created_by' => $actor->userId(),
                'kiosk_device_id' => $actor->deviceId(),
                'event_id' => $eventId ?: Event::query()->public()->orderBy('event_date')->value('id'),
            ]);
        } catch (Throwable $e) {
            Storage::disk('receipts')->delete($path);
            throw $e;
        }
        event(new PosReceiptChanged($receipt->id, 'saved'));

        return $receipt;
    }
}
