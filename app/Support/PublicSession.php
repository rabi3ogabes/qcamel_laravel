<?php

namespace App\Support;

/**
 * Server-side replacement for the original browser storage (F-PUB-096/097):
 * the cart (`ticketSelection` + `selectedEventId`) and the visitor's last
 * 10 remembered booking references live in the Laravel session.
 * Prices are never stored — the server always re-prices (F-PUB-031).
 */
final class PublicSession
{
    private const CART = 'cart';

    private const BOOKINGS = 'bookings';

    private const MAX_BOOKINGS = 10;

    /** @return array{event_id:?string, lines:array<string,int>} */
    public static function cart(): array
    {
        $cart = session(self::CART);
        if (! is_array($cart) || ! is_string($cart['event_id'] ?? null) || ! is_array($cart['lines'] ?? null)) {
            return ['event_id' => null, 'lines' => []];
        }
        $lines = [];
        foreach ($cart['lines'] as $id => $qty) {
            if (is_string($id) && (int) $qty > 0) {
                $lines[$id] = (int) $qty;
            }
        }

        return ['event_id' => $cart['event_id'], 'lines' => $lines];
    }

    /** @param array<string,int> $lines ticket_type id => qty */
    public static function putCart(string $eventId, array $lines): void
    {
        $lines = array_filter(array_map('intval', $lines), fn (int $q) => $q > 0);
        session([self::CART => ['event_id' => $eventId, 'lines' => $lines]]);
    }

    public static function clearCart(): void
    {
        session()->forget(self::CART);
    }

    public static function hasCart(): bool
    {
        $c = self::cart();

        return $c['event_id'] !== null && $c['lines'] !== [];
    }

    /** Re-adding moves a reference to the end (F-PUB-097). */
    public static function remember(string $reference): void
    {
        if (Text::reference($reference) === null) {
            return;
        }
        $list = array_values(array_filter(self::bookings(), fn ($r) => $r !== $reference));
        $list[] = $reference;
        session([self::BOOKINGS => array_slice($list, -self::MAX_BOOKINGS)]);
    }

    /** @return list<string> oldest → newest */
    public static function bookings(): array
    {
        $list = session(self::BOOKINGS);

        return is_array($list) ? array_values(array_filter($list, fn ($r) => is_string($r) && Text::reference($r) !== null)) : [];
    }

    public static function latest(): ?string
    {
        $list = self::bookings();

        return $list === [] ? null : end($list);
    }

    public static function owns(string $reference): bool
    {
        return in_array($reference, self::bookings(), true);
    }
}
