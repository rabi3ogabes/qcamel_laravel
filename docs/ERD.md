# Data model (ERD)

All business tables use UUID primary keys. Money is `decimal(10,2)` QAR; timestamps are stored in UTC and every
business rule converts to **Asia/Qatar** through `App\Support\QatarClock`.

## Core booking flow

```mermaid
erDiagram
    events ||--o{ ticket_types : "sells"
    events ||--o{ orders : "booked for"
    customers ||--o{ orders : "places"
    orders ||--|{ order_items : "frozen price lines"
    orders ||--|{ ticket_holders : "one per ticket"
    ticket_types ||--o{ order_items : "priced from"
    ticket_types ||--o{ ticket_holders : "type of"
    ticket_holders ||--o{ ticket_checkins : "admissions & resets"
    orders ||--o{ payment_events : "every gateway processing"
    orders ||--o{ payment_attempts : "signed checkout requests"
    orders ||--o| sadad_transactions : "settled transaction"
    orders ||--o{ payment_errors : "failures"
    orders ||--o{ refunds : "refund tasks"
    orders ||--o{ expired_qr_codes : "codes revoked by a move"
    orders ||--o{ order_event_changes : "event moves"
    orders ||--o{ deleted_tickets : "recycle bin"
    orders ||--o{ automation_dispatches : "n8n deliveries"
    orders ||--o{ email_delivery_events : "mail timeline"
    pos_users ||--o{ orders : "cashier attribution"
    kiosk_devices ||--o{ orders : "sold on"
    users ||--o{ orders : "confirmed / verified / deleted by"

    events {
        uuid id PK
        string title
        timestamp event_date "start (UTC)"
        timestamp end_date "multi-day seasons"
        bool is_active
        bool is_archived
        time sales_cutoff_time "default 18:00 Qatar"
        time checkin_cutoff_time
        int max_admission_per_order
        int per_person_cap
        int hold_minutes
    }
    ticket_types {
        uuid id PK
        uuid event_id FK
        string type "vip | normal | parking"
        decimal price
        int available_quantity "capacity"
        int sold_quantity "held = pending + confirmed"
    }
    orders {
        uuid id PK
        string booking_reference UK "QTR-/POS-d-m-yyyy-HEX12"
        uuid event_id FK
        uuid customer_id FK
        string payment_method "sadad | cash_pos"
        string payment_status "pending | confirmed | cancelled"
        string payment_note "expired | payment_failed | ..."
        string payment_id UK "Sadad transaction"
        decimal total_amount
        timestamp payment_expires_at "30-min online hold"
        timestamp review_required_at
        timestamp deleted_at "soft delete"
    }
    ticket_holders {
        uuid id PK
        uuid order_id FK
        string qr_code UK "<ref>-TKTnn"
        string qr_image_url
        string id_number "PII: admins only"
        bool is_present
        timestamp checked_in_at
    }
```

## Staff, access and operations

```mermaid
erDiagram
    users ||--o{ model_has_roles : "spatie roles"
    roles ||--o{ role_has_permissions : "grants"
    users ||--o{ personal_access_tokens : "API / MCP tokens"
    users }o--o| pos_users : "linked POS profile"
    pos_users ||--o{ staff_attendance : "daily register"
    pos_users ||--o{ kiosk_devices : "device owner"
    kiosk_devices ||--o{ scan_queue : "offline scans"
    kiosk_devices ||--o{ pos_receipts : "captured on"
    users ||--o{ activity_log : "causer (audit trail)"
    login_attempts }o--|| users : "identifier = e-mail / IP / passcode key"
```

## Analytics and settings

| Table | Purpose |
|---|---|
| `active_visitors` | One row per live public-site session (heartbeat 60 s, 3-min cutoff, IPs stored masked). |
| `page_views` | Page views with `viewed_on_qatar` for Qatar-day grouping (T-125). |
| `settings` | spatie/laravel-settings groups: branding, contact, sadad (secrets **encrypted**), automation (webhook URLs encrypted), business, events, feature flags, analytics. |
| `activity_log` | Append-only audit (spatie/activitylog + actor type/identifier/IP/UA). Purge by age is owner-only and itself logged. |
| `site_assets`, `popup_banners` | Uploaded brand imagery and homepage pop-ups. |

Constraints worth knowing: `ticket_types.sold_quantity ≤ available_quantity` is enforced by the stock ledger under
`lockForUpdate`; `orders.payment_id` and `ticket_holders.qr_code` are unique; CHECK constraints pin the enum columns.
