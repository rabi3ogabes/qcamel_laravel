<?php

namespace App\Domain\Booking\Data;

use App\Domain\Shared\DomainError;
use App\Enums\OrderSource;
use App\Enums\PaymentMethod;
use App\Support\PhoneNumber;
use App\Support\Text;

/**
 * Port of the create-order validation (R-072…R-098, F-ORD-003/004, T-034,
 * T-035): every error is collected and returned at once as
 * 400 {code:'validation_failed', errors:[…]}.
 */
class OrderRequestValidator
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    private const ID_NUMBER = '/^[\p{L}\p{N}\-_\/ ]{3,30}$/u';

    private const EMAIL = '/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/';

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param  mixed  $body  decoded JSON (must be an object/array map)
     * @param  bool  $strictPhone  apply per-country rules (R-086) — web forms
     */
    public function validate(mixed $body, bool $strictPhone = false): OrderRequestData
    {
        $this->errors = [];
        if (! is_array($body) || array_is_list($body) && $body !== []) {
            throw DomainError::validation([__('orders.v.body_object')]);
        }
        if ($body === []) {
            throw DomainError::validation([__('orders.v.customer_required'), __('orders.v.items_required')]);
        }

        // --- customer -------------------------------------------------------
        $c = $body['customer'] ?? null;
        $customer = ['name' => '', 'email' => '', 'phone' => '', 'country_code' => '+974', 'nationality' => null, 'id_number' => null];
        if (! is_array($c) || array_is_list($c) && $c !== []) {
            $this->errors[] = __('orders.v.customer_required');
        } else {
            $label = __('orders.v.customer');
            $customer['name'] = $this->name($c['name'] ?? null, $label);
            $customer['country_code'] = '+974';
            if (array_key_exists('country_code', $c) && $c['country_code'] !== null && $c['country_code'] !== '') {
                if (! is_string($c['country_code']) || ! PhoneNumber::isValidCountryCode(trim($c['country_code']))) {
                    $this->errors[] = __('orders.v.customer_cc_invalid');
                } else {
                    $customer['country_code'] = trim($c['country_code']);
                }
            }
            $customer['phone'] = $this->phone($c['phone'] ?? null, $label, $strictPhone ? $customer['country_code'] : null);
            $customer['email'] = $this->email($c['email'] ?? null);
            $customer['nationality'] = $this->nationality($c['nationality'] ?? null, $label, required: true);
            $customer['id_number'] = $this->idNumber($c['id_number'] ?? null, $label);
        }

        // --- items ------------------------------------------------------------
        $items = [];
        $rawItems = $body['items'] ?? null;
        $itemsValid = true;
        if (! is_array($rawItems) || ! array_is_list($rawItems) || $rawItems === []) {
            $this->errors[] = __('orders.v.items_required');
            $itemsValid = false;
        } else {
            if (count($rawItems) > (int) config('qcamelmc.orders.max_items')) {
                $this->errors[] = __('orders.v.too_many_items');
                $itemsValid = false;
            }
            $seen = [];
            $sum = 0;
            foreach (array_slice($rawItems, 0, (int) config('qcamelmc.orders.max_items')) as $it) {
                if (! is_array($it)) {
                    $this->errors[] = __('orders.v.ticket_id_invalid');
                    $itemsValid = false;

                    continue;
                }
                $tid = $it['ticket_id'] ?? null;
                if (! is_string($tid) || preg_match(self::UUID, $tid) !== 1) {
                    $this->errors[] = __('orders.v.ticket_id_invalid');
                    $itemsValid = false;

                    continue;
                }
                $tid = strtolower($tid);
                if (isset($seen[$tid])) {
                    $this->errors[] = __('orders.v.duplicate_ticket');
                    $itemsValid = false;

                    continue;
                }
                $seen[$tid] = true;
                $q = $it['quantity'] ?? null;
                if (! is_int($q) || $q < 1 || $q > (int) config('qcamelmc.orders.max_quantity_per_item')) {
                    $this->errors[] = __('orders.v.quantity_invalid');
                    $itemsValid = false;

                    continue;
                }
                $sum += $q;
                $items[] = ['ticket_id' => $tid, 'quantity' => $q];
            }
            if ($sum > (int) config('qcamelmc.orders.max_tickets_per_order')) {
                $this->errors[] = __('orders.v.too_many_tickets');
                $itemsValid = false;
            }
        }

        // --- holders ------------------------------------------------------------
        $holders = [];
        $rawHolders = $body['holders'] ?? [];
        if (! is_array($rawHolders) || ! array_is_list($rawHolders)) {
            $this->errors[] = __('orders.v.holders_mismatch');
            $rawHolders = [];
        }
        foreach (array_slice($rawHolders, 0, (int) config('qcamelmc.orders.max_tickets_per_order')) as $i => $h) {
            $n = $i + 1;
            $label = __('orders.v.holder', ['n' => $n]);
            if (! is_array($h)) {
                $this->errors[] = __('orders.v.holder_invalid', ['n' => $n]);

                continue;
            }
            $tid = $h['ticket_id'] ?? null;
            if (! is_string($tid) || preg_match(self::UUID, $tid) !== 1) {
                $this->errors[] = __('orders.v.ticket_id_invalid');

                continue;
            }
            $cc = '';
            if (isset($h['country_code']) && $h['country_code'] !== '' && $h['country_code'] !== null) {
                if (is_string($h['country_code']) && PhoneNumber::isValidCountryCode(trim($h['country_code']))) {
                    $cc = trim($h['country_code']);
                } else {
                    $this->errors[] = __('orders.v.holder_cc_invalid', ['n' => $n]);
                }
            }
            $holders[] = [
                'ticket_id' => strtolower($tid),
                'name' => $this->name($h['name'] ?? null, $label),
                'phone' => $this->phone($h['phone'] ?? null, $label, $strictPhone && $cc !== '' ? $cc : null),
                'country_code' => $cc,
                'nationality' => (string) ($this->nationality($h['nationality'] ?? null, $label, required: false) ?? ''),
                'id_number' => $this->idNumber($h['id_number'] ?? null, $label),
            ];
        }

        // --- cross-check (only when items valid and no errors so far) ----------
        if ($itemsValid && $this->errors === []) {
            if (count($holders) !== array_sum(array_column($items, 'quantity'))) {
                $this->errors[] = __('orders.v.holders_count');
            } else {
                foreach ($items as $it) {
                    $count = count(array_filter($holders, fn ($h) => $h['ticket_id'] === $it['ticket_id']));
                    if ($count !== $it['quantity']) {
                        $this->errors[] = __('orders.v.holders_mismatch');
                        break;
                    }
                }
            }
        }

        // --- method / source ------------------------------------------------------
        $method = PaymentMethod::tryFrom(is_string($body['payment_method'] ?? null) ? $body['payment_method'] : '');
        if (! $method) {
            $this->errors[] = __('orders.v.method_invalid');
        }
        $sourceRaw = $body['source'] ?? 'web';
        $source = is_string($sourceRaw) ? OrderSource::tryFrom($sourceRaw) : null;
        if (! $source) {
            $this->errors[] = __('orders.v.source_invalid');
        }
        $posUserId = $body['pos_user_id'] ?? null;
        if ($posUserId !== null && $posUserId !== '' && (! is_string($posUserId) || preg_match(self::UUID, $posUserId) !== 1)) {
            $this->errors[] = __('orders.v.cashier_invalid');
        }

        if ($this->errors !== []) {
            throw DomainError::validation($this->errors);
        }

        return new OrderRequestData(
            customer: $customer,
            items: $items,
            holders: $holders,
            paymentMethod: $method,
            source: $source,
            posUserId: $posUserId ?: null,
            termsAccepted: filter_var($body['terms_accepted'] ?? false, FILTER_VALIDATE_BOOL),
        );
    }

    private function clean(mixed $value, string $label): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }
        $value = (string) $value;
        if (Text::hasControlChars($value)) {
            $this->errors[] = __('orders.v.invalid_chars', ['label' => $label]);

            return null;
        }

        return Text::clean($value);
    }

    private function name(mixed $value, string $label): string
    {
        $v = $this->clean($value, $label);
        if ($v === null || $v === '') {
            $this->errors[] = __('orders.v.required', ['label' => __('orders.v.name_of', ['label' => $label])]);

            return '';
        }
        $len = mb_strlen($v);
        if ($len < 2) {
            $this->errors[] = __('orders.v.name_short', ['label' => $label]);
        } elseif ($len > 100) {
            $this->errors[] = __('orders.v.too_long', ['label' => __('orders.v.name_of', ['label' => $label])]);
        }

        return $v;
    }

    private function phone(mixed $value, string $label, ?string $strictCc): string
    {
        $v = $this->clean(is_string($value) ? PhoneNumber::normalizeDigits($value) : $value, $label);
        if ($v === null || $v === '') {
            $this->errors[] = __('orders.v.required', ['label' => __('orders.v.phone_of', ['label' => $label])]);

            return '';
        }
        if (! PhoneNumber::isValidGeneric($v)) {
            $this->errors[] = __('orders.v.phone_invalid', ['label' => $label]);

            return $v;
        }
        if ($strictCc !== null && ! str_starts_with(ltrim($v), '+') && ! PhoneNumber::isValidFor(PhoneNumber::normalizeNational($v, $strictCc), $strictCc)) {
            $this->errors[] = __('orders.v.phone_country_invalid', ['label' => $label]);
        }

        return $v;
    }

    private function email(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $v = $this->clean($value, __('orders.v.email'));
        if ($v === null || $v === '') {
            return '';
        }
        if (mb_strlen($v) > 254 || preg_match(self::EMAIL, $v) !== 1) {
            $this->errors[] = __('orders.v.email_invalid');
        }

        return $v;
    }

    private function nationality(mixed $value, string $label, bool $required): ?string
    {
        $v = $this->clean($value, $label);
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[] = __('orders.v.required', ['label' => __('orders.v.nationality_of', ['label' => $label])]);
            }

            return null;
        }
        if (mb_strlen($v) > 60) {
            $this->errors[] = __('orders.v.too_long', ['label' => __('orders.v.nationality_of', ['label' => $label])]);
        }

        return $v;
    }

    private function idNumber(mixed $value, string $label): ?string
    {
        $v = $this->clean(is_string($value) ? PhoneNumber::normalizeDigits($value) : $value, $label);
        if ($v === null || $v === '' || preg_match(self::ID_NUMBER, $v) !== 1) {
            $this->errors[] = __('orders.v.id_invalid', ['label' => $label]);

            return $v ?: null;
        }

        return $v;
    }
}
