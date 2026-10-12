<?php

namespace App\Domain\Messaging\Drivers;

/**
 * Pluggable delivery channel for WhatsApp tickets/invoices (F-NTF-024,
 * NEW driver interface). Implementations never throw (T-108).
 */
interface WhatsAppChannel
{
    /**
     * @param  array<string,mixed>  $payload
     * @return array{ok:bool, status?:int, error?:string, body?:string, json?:mixed, duration_ms?:int, timeout?:bool}
     */
    public function post(string $url, array $payload, int $timeoutSeconds = 8): array;
}
