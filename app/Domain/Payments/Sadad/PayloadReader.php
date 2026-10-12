<?php

namespace App\Domain\Payments\Sadad;

use Illuminate\Http\Request;
use Throwable;

/**
 * F-PAY-021 readPaymentPayload: GET → query; POST multipart → form fields;
 * otherwise body text (≤ 64 000 chars) as JSON object or urlencoded.
 * Query string is ignored for POST. Garbage never throws.
 */
final class PayloadReader
{
    /** @return array<string, mixed> */
    public static function read(Request $request, int $maxChars = 64000): array
    {
        try {
            if ($request->isMethod('GET')) {
                return $request->query->all();
            }
            if (! $request->isMethod('POST')) {
                return [];
            }
            $type = strtolower((string) $request->header('Content-Type'));
            if (str_contains($type, 'multipart/form-data')) {
                return array_map(fn ($v) => is_string($v) ? $v : '', $request->request->all());
            }
            $text = (string) $request->getContent();
            if (strlen($text) > $maxChars) {
                return [];
            }
            if (str_contains($type, 'json') || str_starts_with(ltrim($text), '{')) {
                $parsed = json_decode($text, true);

                return is_array($parsed) && ! array_is_list($parsed) ? $parsed : [];
            }
            parse_str($text, $out);

            return is_array($out) ? $out : [];
        } catch (Throwable) {
            return [];
        }
    }
}
