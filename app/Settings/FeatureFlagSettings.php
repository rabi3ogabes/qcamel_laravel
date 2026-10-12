<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Part 4.4 FeatureFlagSettings (R-195) + parity-default business flags
 * (Part 0.4: cash_hold_policy=none, admit_unpaid_cash_bookings=true).
 */
class FeatureFlagSettings extends Settings
{
    public bool $show_delete_customer_button;

    public bool $show_delete_order_button;

    public bool $show_generate_qr_button;

    public bool $show_delete_event_button;

    public bool $show_delete_ticket_button;

    public bool $admit_unpaid_cash_bookings;

    /** none | expire_at_event_cutoff (R-042) */
    public string $cash_hold_policy;

    /** F-PUB-051 rebuild: show "pay at venue" on the public checkout. */
    public bool $public_cash_at_venue_enabled;

    /** U-AUTH-02 passcode login on/off switch. */
    public bool $passcode_login_enabled;

    /** F-POS-003 rebuild: force a cashier on every POS sale. */
    public bool $pos_cashier_required;

    public static function group(): string
    {
        return 'flags';
    }
}
