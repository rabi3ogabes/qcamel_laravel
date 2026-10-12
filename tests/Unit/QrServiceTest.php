<?php

use App\Domain\Shared\DomainError;
use App\Domain\Tickets\QrService;
use chillerlan\QRCode\QRCode;

/*
| QR rendering — T-104, T-105.
*/

it('T-104 maps a safe code to its PNG object name and refuses unsafe ones', function () {
    expect(QrService::objectName('QTR-ABC123DEF456-TKT01'))->toBe('QTR-ABC123DEF456-TKT01.png')
        ->and(fn () => QrService::objectName('../secret'))->toThrow(DomainError::class);
});

it('T-105 renders a real PNG that decodes back to exactly the ticket code', function () {
    $code = 'QTR-5-11-2026-ABC123DEF456-TKT01';
    $png = app(QrService::class)->png($code);

    expect(array_values(unpack('C8', substr($png, 0, 8))))->toBe([137, 80, 78, 71, 13, 10, 26, 10]);

    $decoded = (new QRCode)->readFromBlob($png);
    expect((string) $decoded)->toBe($code);
});
