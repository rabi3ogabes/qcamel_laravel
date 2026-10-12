<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Defaults exactly per the ADM-SET settings-keys table and Part 4.4.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        // branding
        $this->migrator->add('branding.logo_url', null);
        $this->migrator->add('branding.header_bg_color', '#D4B78A');
        $this->migrator->add('branding.header_bg_image_url', null);
        $this->migrator->add('branding.hero_image_url', null);
        $this->migrator->add('branding.hero_text', '');
        $this->migrator->add('branding.before_footer_image_url', null);
        $this->migrator->add('branding.copyright_text', 'جميع الحقوق محفوظة');
        $this->migrator->add('branding.festival_name', 'مهرجان قطر للإبل');
        $this->migrator->add('branding.season_label', 'جزيلات العطا - 2026');
        $this->migrator->add('branding.og_image_url', null);
        $this->migrator->add('branding.site_title_ar', 'تذاكر الفعاليات في قطر - احجز تجربتك');
        $this->migrator->add('branding.site_title_en', 'Qatar Events Tickets — Book your experience');
        $this->migrator->add('branding.site_description_ar', 'احجز تذاكر الفعاليات الرائدة في قطر. حجز آمن عبر الإنترنت مع تأكيد فوري.');
        $this->migrator->add('branding.site_description_en', 'Book tickets for leading events in Qatar. Secure online booking with instant confirmation.');

        // contact
        $this->migrator->add('contact.admin_phone', null);
        $this->migrator->add('contact.admin_emails', []);
        $this->migrator->add('contact.publish_admin_phone', true);
        $this->migrator->add('contact.support_whatsapp', null);

        // events
        $this->migrator->add('events.current_event_id', null);
        $this->migrator->add('events.season_start_date', null);

        // flags (R-195 + parity defaults)
        $this->migrator->add('flags.show_delete_customer_button', false);
        $this->migrator->add('flags.show_delete_order_button', false);
        $this->migrator->add('flags.show_generate_qr_button', false);
        $this->migrator->add('flags.show_delete_event_button', false);
        $this->migrator->add('flags.show_delete_ticket_button', true);
        $this->migrator->add('flags.admit_unpaid_cash_bookings', true);
        $this->migrator->add('flags.cash_hold_policy', 'none');
        $this->migrator->add('flags.public_cash_at_venue_enabled', false);
        $this->migrator->add('flags.passcode_login_enabled', true);
        $this->migrator->add('flags.pos_cashier_required', false);

        // automation (R-152…R-156)
        $this->migrator->addEncrypted('automation.webhook_url', null);
        $this->migrator->addEncrypted('automation.email_webhook_url', null);
        $this->migrator->add('automation.webhook_enabled', true);
        $this->migrator->add('automation.email_webhook_enabled', true);
        $this->migrator->add('automation.payment_failed_email_enabled', true);
        $this->migrator->add('automation.auto_invoice_interval_seconds', 60);
        $this->migrator->add('automation.last_invoice_sent_at', null);
        $this->migrator->add('automation.invoice_batch_min', 1);
        $this->migrator->add('automation.invoice_batch_max', 10);
        $this->migrator->add('automation.invoice_send_delay_min', 300);
        $this->migrator->add('automation.invoice_send_delay_max', 600);
        $this->migrator->add('automation.invoice_excluded_dates', ['2025-11-06', '2025-11-07', '2025-11-08']);
        $this->migrator->add('automation.invoice_auto_send_enabled', false);

        // sadad
        $this->migrator->add('sadad.merchant_id', null);
        $this->migrator->add('sadad.website_domain', null);
        $this->migrator->add('sadad.environment', 'auto');
        $this->migrator->add('sadad.site_url', null);
        $this->migrator->addEncrypted('sadad.checkout_secret', null);
        $this->migrator->addEncrypted('sadad.api_login_secret', null);
        $this->migrator->addEncrypted('sadad.support_pin', null);

        // analytics
        $this->migrator->add('analytics.clarity_enabled', false);
        $this->migrator->add('analytics.clarity_project_id', null);
        $this->migrator->add('analytics.mask_ip', true);
        $this->migrator->add('analytics.require_consent', true);

        // business (NEW)
        $this->migrator->add('business.terms_ar', [
            '1- التذكرة المباعة غير قابلة للتعديل او الإستبدال او إسترجاع قيمتها',
            '2- ممنوع دخول الأطفال دون 10 سنوات',
            '3- يكون إستخدام التذاكر للدخول مرة واحدة فقط',
        ]);
        $this->migrator->add('business.terms_en', [
            '1- Sold tickets cannot be modified, exchanged or refunded',
            '2- Children under 10 years old are not admitted',
            '3- Each ticket admits once only',
        ]);
        $this->migrator->add('business.terms_version', '2026.1');
        $this->migrator->add('business.receipt_price_normal', '200.00');
        $this->migrator->add('business.receipt_price_vip', '300.00');
        $this->migrator->add('business.receipt_price_parking', '500.00');
        $this->migrator->add('business.receipt_title', 'كشف تحميل رسوم تذاكر دخول مهرجان قطر للابل- جزيلا العطا');
        $this->migrator->add('business.low_stock_threshold', 10);
        $this->migrator->add('business.activity_log_retention_days', 365);
        $this->migrator->add('business.invoice_reply_timeout_minutes', 30);
    }
};
