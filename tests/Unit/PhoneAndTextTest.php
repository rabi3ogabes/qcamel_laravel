<?php

use App\Support\PhoneNumber;
use App\Support\Text;

/*
| Phone, WhatsApp and scanned-code normalisation — T-082, T-112, T-117, T-118.
*/

it('T-082 normalises the Sadad mobile number', function (?string $phone, ?string $cc, string $expected) {
    expect(PhoneNumber::international($phone, $cc))->toBe($expected);
})->with([
    ['55512345', '+974', '97455512345'],
    ['+974 5551 2345', '+974', '97455512345'],
    ['0097455512345', '+974', '97455512345'],
    ['0555 1234', '+966', '9665551234'],
    ['55512345', '', '97455512345'],
    ['+966 5551 0001', '+974', '96655510001'],
    ['+20 100 123 4567', '+974', '201001234567'],
]);

it('T-117 builds WhatsApp numbers without forcing the Qatar prefix', function () {
    expect(PhoneNumber::toWhatsApp('0555 1234', '+966'))->toBe('9665551234')
        ->and(PhoneNumber::toWhatsApp('+966 5551 0001', '+974'))->toBe('96655510001')
        ->and(PhoneNumber::toWhatsApp('', '+974'))->toBeNull();
});

it('T-112 accepts holder country codes only in the +1…+9999 shape', function (string $cc, bool $ok) {
    expect(PhoneNumber::isValidCountryCode($cc))->toBe($ok);
})->with([['+974', true], ['+1', true], ['+9999', true], ['974', false], ['+12345', false], ['+97a', false], ['', false]]);

it('T-118 normalises scanned codes, including image URLs', function (mixed $raw, string $expected) {
    expect(Text::normalizeScannedCode($raw))->toBe($expected);
})->with([
    ['QTR-5-11-2026-ABC123DEF456-TKT01', 'QTR-5-11-2026-ABC123DEF456-TKT01'],
    ["  QTR-ABC-TKT02\n", 'QTR-ABC-TKT02'],
    ['https://cdn.example/qr/QTR-ABC-TKT03.png?v=2#x', 'QTR-ABC-TKT03'],
    ['https://cdn.example/qr/QTR-ABC-TKT04.PNG', 'QTR-ABC-TKT04'],
    ['', ''],
    [null, ''],
    [12345, '12345'],
]);

it('T-104 accepts only safe QR object names', function (string $name, bool $ok) {
    expect(Text::isSafeQrName($name))->toBe($ok);
})->with([
    ['QTR-ABC123DEF456-TKT01', true],
    ['../secret', false], ['a/b', false], ['a b', false], ['', false],
    [str_repeat('a', 200), false], ['.hidden', false], ['a.png', false], ['ünï', false],
]);

it('T-083 keeps only safe https return origins', function (?string $origin, ?string $expected) {
    expect(Text::safeOrigin($origin))->toBe($expected);
})->with([
    ['https://qcamelmc.org', 'https://qcamelmc.org'],
    ['http://localhost:8000', 'http://localhost:8000'],
    ['javascript:alert(1)', null],
    ['http://evil.example', null],
    ['https://qcamelmc.org/path?x=1', null],
    ['data:text/html,hi', null],
    ['//evil.example', null],
    ['junk', null],
]);

it('masks phones to the last three digits', function () {
    expect(Text::maskPhone('+974 5598 7654'))->toBe('••••••••654')->and(Text::maskPhone(null))->toBeNull();
});
