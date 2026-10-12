<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * NEW business settings (U-ADM-SET-04, R-200, F-PUB-050 terms, R-171 retention,
 * F-RCP-021 receipt title, R-055 low stock).
 */
class BusinessSettings extends Settings
{
    /** @var list<string> */
    public array $terms_ar;

    /** @var list<string> */
    public array $terms_en;

    public string $terms_version;

    public string $receipt_price_normal;

    public string $receipt_price_vip;

    public string $receipt_price_parking;

    public ?string $receipt_title;

    public int $low_stock_threshold;

    public int $activity_log_retention_days;

    public int $invoice_reply_timeout_minutes;

    public static function group(): string
    {
        return 'business';
    }
}
