<?php

/*
|--------------------------------------------------------------------------
| QCAMELMC business configuration (docs/SPEC.md Part 3)
|--------------------------------------------------------------------------
| Tunable rules ("T" in Part 3) live here; runtime-editable ones are mirrored
| in spatie/laravel-settings groups. Fixed rules ("F") are constants in code.
| Read every value through App\Support\Rules so nothing is duplicated.
*/

return [

    'version' => env('APP_VERSION', 'v2.0'),

    // R-001 business time zone (storage stays UTC: config('app.timezone')).
    'timezone' => env('APP_BUSINESS_TIMEZONE', 'Asia/Qatar'),

    'currency' => 'QAR',

    'locales' => ['ar', 'en'],

    // R-003 / R-004 default cut-off (per-event columns override).
    'cutoff_time' => '18:00:00',

    'orders' => [
        'max_admission_per_web_order' => 5,   // R-012
        'max_parking_per_web_order' => 5,     // R-013
        'max_per_type_selection' => 5,        // R-014
        'pos_max_admission' => 5,             // R-015
        'max_items' => 6,                     // R-016
        'max_quantity_per_item' => 20,        // R-016
        'max_tickets_per_order' => 30,        // R-016
        'per_person_cap' => 5,                // R-020
        'max_open_unpaid_orders' => 4,        // R-026
        'min_online_amount' => env('PAYMENT_MIN_ONLINE_AMOUNT', '3.00'), // R-034
        'hold_minutes' => (int) env('PAYMENT_HOLD_MINUTES', 30),          // R-041
        'reference_retries' => 5,             // R-060
        'max_body_chars' => 64000,            // R-083
        'relay_max_body_chars' => 200000,     // R-084
        'low_stock_threshold' => 10,          // R-055
        'deadlock_retries' => 3,              // R-047
    ],

    'payments' => [
        'verify_throttle_seconds' => 10,      // R-117
        'verify_window_days' => 3,            // R-117
        'result_poll_seconds' => 3,           // R-118
        'result_verify_seconds' => 12,        // R-118
        'result_timeout_seconds' => 120,      // R-118
        'payload_cap' => 4000,                // R-116
        'amount_tolerance_cents' => 0,        // R-111 (±0.005 == equal in integer cents)
        'reconcile_batch' => 50,
        'reconcile_min_age_minutes' => 2,
    ],

    'gate' => [
        'history_limit' => 30,                // R-134
        'board_recent' => 25,                 // R-134
        'board_refresh_seconds' => 15,        // R-134
        'camera_fps' => 10,                   // R-135
        'camera_box' => 250,                  // R-135
        'duplicate_alert_ms' => 4000,         // R-135
        'staff_label_max' => 80,              // R-090
        'qr_hmac_enabled' => (bool) env('QR_HMAC_ENABLED', false), // Tier A9
    ],

    'auth' => [
        'passcode_hash' => env('STAFF_PASSCODE_HASH'),            // R-140 (argon2id/bcrypt hash)
        'passcode_plain' => env('STAFF_PASSCODE'),                // R-140 legacy plain source (optional)
        'passcode_min_length' => 8,
        'passcode_revoked' => ['@@@Qatar123'],                    // D-001
        'passcode_session_days' => 10,                            // R-141
        'passcode_ip_max' => 5, 'passcode_ip_window' => 15, 'passcode_ip_block' => 30,       // R-142
        'passcode_global_max' => 60, 'passcode_global_window' => 15, 'passcode_global_block' => 15, // R-143
        'login_max' => 5, 'login_window' => 15, 'login_block' => 30,                         // R-145
        'login_attempt_retention_days' => 7,                     // R-147
        'admin_2fa_required' => (bool) env('ADMIN_2FA_REQUIRED', true), // R-149
    ],

    'notifications' => [
        'interval_min_seconds' => 10,         // R-152
        'webhook_timeout' => (int) env('N8N_HTTP_TIMEOUT', 8),   // R-165
        'relay_timeout' => 10,                // R-159
        'max_attempts' => 2,                  // R-156
        'response_max_chars' => 500,          // R-091
        'n8n_response_secret' => env('N8N_RESPONSE_SECRET'),
        'whatsapp_driver' => env('WHATSAPP_DRIVER', 'n8n'),
        'whatsapp_url' => env('N8N_WEBHOOK_URL'),
        'email_url' => env('N8N_EMAIL_WEBHOOK_URL'),
        'mail_webhook_secret' => env('MAIL_WEBHOOK_SECRET'),
    ],

    'screens' => [
        'orders_page_size' => 30,             // R-168
        'transactions_page_size' => 30,       // R-169
        'errors_page_size' => 50,             // R-170
        'logs_page_size' => 50,               // R-171
        'notifications_poll_seconds' => 30,   // R-172
        'home_page_size' => 9,                // R-175
    ],

    // Sadad .env fallbacks. Read only through config() so they survive `config:cache`
    // (env() returns null outside config files once the configuration is cached).
    'sadad' => [
        'merchant_id' => env('SADAD_MERCHANT_ID'),
        'website_domain' => env('SADAD_WEBSITE_DOMAIN'),
        'checkout_secret' => env('SADAD_CHECKOUT_SECRET'),
        'api_login_secret' => env('SADAD_API_LOGIN_SECRET'),
        'environment' => env('SADAD_ENV', 'auto'),
        'frontend_url' => env('FRONTEND_URL'),
        'api_live_url' => env('SADAD_API_LIVE_URL', 'https://api-s.sadad.qa'),
        'api_sandbox_url' => env('SADAD_API_SANDBOX_URL', 'https://api-sandbox.sadad.qa'),
        'http_timeout' => (int) env('SADAD_HTTP_TIMEOUT', 8),
    ],

    'mcp' => [
        'enabled' => (bool) env('MCP_ENABLED', true),  // F-API-001: read-only AI tools at POST /mcp
        'token_days' => (int) env('MCP_TOKEN_DAYS', 90),
    ],

    'capacity' => [
        'nearing' => 80, 'warning' => 90, 'critical' => 100,     // R-176
        'toast_ms' => 8000, 'pos_banner_ms' => 10000, 'live_banner_ms' => 4000, // R-177
        'cache_seconds' => 30,                // D-068
    ],

    'visitors' => [
        'heartbeat_seconds' => 60,            // R-179
        'active_minutes' => 3,
        'prune_minutes' => 5,
        'excluded_prefixes' => ['/admin', '/staff', '/live-', '/pos', '/scanner', '/livewire', '/api', '/payments', '/webhooks', '/up', '/v', '/broadcasting', '/horizon', '/pulse', '/telescope', '/docs', '/mcp', '/build', '/storage', '/favicon'],
    ],

    'cache' => [
        'public_events_seconds' => 60,        // R-185
        'public_settings_seconds' => 300,     // R-185
    ],

    'uploads' => [
        'logo_kb' => 5 * 1024,                // R-187
        'image_kb' => 10 * 1024,
        'receipt_kb' => 6 * 1024,             // R-188
    ],

    'qr' => [
        'disk' => env('QR_DISK', 'qr'),
        'size' => (int) env('QR_SIZE', 800),  // R-189
        'margin' => (int) env('QR_MARGIN', 2),
        'ecc' => env('QR_ECC', 'M'),
        'backfill_batch' => 100,              // R-191
        'hmac_key' => env('QR_HMAC_KEY'),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'gemini'),
        'model' => env('AI_MODEL', 'gemini-2.5-flash'),
        'timeout' => 30,
    ],

    'geo' => [
        'database' => env('GEOIP_DATABASE', storage_path('app/geoip/GeoLite2-City.mmdb')),
    ],
];
