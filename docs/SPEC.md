# QCAMELMC → LARAVEL — MASTER SPECIFICATION & PHASED BUILD PROMPTS

**Source analysed:** `github.com/rabi3ogabes/qcamelmc` (commit `1f76e44`) — React 18 + Vite + TypeScript + Tailwind/shadcn + Supabase (Postgres, RLS, 29 Edge Functions, Storage, Realtime) — built with Lovable.dev. Deployed at `qcamelmc.org`.
**Target:** a new Laravel application with **100 % feature parity**, every original defect fixed, and an upgraded luxury, modern, professional experience.
**Document date:** 11 October 2026 · **Language:** English (an Arabic edition with identical structure and IDs exists).
**Size of the inventory:** 862 features (F-IDs) · 203 business rules (R-IDs) · 30 data tables · 106 original defects (D-IDs) · 128 acceptance tests (T-IDs) · 21 build phases.


**Contents:** Part 0 How to use · Part 1 System overview · Part 2 Complete feature inventory (2.1 Public site, booking engine, QR, design · 2.2 Payments, notifications, APIs · 2.3 Admin finance, tickets, customers · 2.4 Admin shell, auth, settings, events, analytics · 2.5 Staff operations: POS, receipts, gate, live boards) · Part 3 Business rules & constants · Part 4 Data model for Laravel · Part 5 Original defects & acceptance tests · Part 6 Target architecture & Definition of Done · Part 7 Advanced upgrades roadmap · Part 8 Phased build prompts (P0–P20 + QA, coverage, bug-fix, change-request templates) · Part 9 Traceability matrix.

---

## Part 0 — How to use this document

### 0.1 What this file is
1. **Parts 1–5 are the specification**: what the system does, feature by feature, with the exact numbers, labels, rules and data model taken from reading every file of the original repository (426 files: 64 pages/components, 29 edge functions, 116 SQL migrations, 14 test suites, docs, plans).
2. **Part 6 is the target architecture** (stack, structure, standards, Definition of Done).
3. **Part 7 is the upgrade roadmap** (new "advanced / luxury" features that the original did not have, clearly marked NEW).
4. **Part 8 is the list of ready-to-paste prompts**, Phase 0 → Phase 20, to make an AI coding agent (Claude Code, Cursor, Codex…) build the system phase by phase.
5. **Part 9 is the traceability matrix**: every source file of the original → the feature IDs that cover it, so you can prove that nothing was missed.

### 0.2 How to run the build
1. Create an empty Git repository for the new project.
2. Copy this file into it as `docs/SPEC.md` (keep the Arabic edition as `docs/SPEC.ar.md` for the business team).
3. Open your AI coding agent in that repository and paste **Phase 0 (Master context)** first. It sets the rules for the whole project.
4. Paste the phases **in order**. Do not start a phase until the previous one passes its acceptance checklist and CI is green.
5. After each phase, paste the **Phase QA prompt** (8.22). After the last phase, paste the **Coverage audit prompt** (8.23).
6. If a phase is too big for one session, paste it again with "Continue Phase N from where you stopped — read docs/PROGRESS.md first".

### 0.3 ID conventions
| Prefix | Meaning | Where |
|---|---|---|
| `F-<MODULE>-NNN` | A feature that exists in the original and MUST exist in the rebuild | Part 2 |
| `U-<MODULE>-NN` / "NEW" | An upgrade that does not exist in the original | Parts 2 & 7 |
| `R-NNN` | A business rule or constant (single source of truth for numbers) | Part 3 |
| `D-NNN` | A defect of the original that MUST NOT be reproduced | Part 5.a |
| `T-NNN` | An acceptance test the Laravel test suite MUST contain | Part 5.b |
| `P-NN` | A build phase / prompt | Part 8 |

### 0.4 Precedence rules (when two parts seem to disagree)
1. **Part 5.a (defects) wins** over any feature description that reproduces the defect: keep the behaviour, apply the fix.
2. **Part 4 (data model) wins on names**: e.g. table `tickets` is renamed `ticket_types`; holder check-in columns `confirmed_*` become `checked_in_*`; role `moderator` becomes `supervisor`; `activity_logs` becomes spatie `activity_log`; the singleton `settings` row becomes spatie settings groups with secrets in `.env`. Part 2 sometimes uses the original names because it describes the original — map them through Part 4.
3. **Part 3 (rules) wins on numbers.** If a number in Part 2 differs, Part 3 is right.
4. **Parity defaults:** where the fix of a defect would change business behaviour, the rebuild exposes a setting whose default keeps the original behaviour (e.g. `cash_hold_policy=none`, `admit_unpaid_cash_bookings=true`) and documents the safer option.
5. Anything marked **NEW** is optional for parity and is scheduled in Part 7 / Phase 20 unless a phase explicitly includes it.

### 0.5 Glossary
| Term | Meaning |
|---|---|
| Event | A festival day/session customers book (title, date, times, location, image). Seasons group consecutive months of events. |
| Ticket type | Per-event product: `vip`, `normal` (Arabic «عادي»), `parking` («مواقف»), each with price and capacity. |
| Order / booking | One purchase for one event; has a booking reference `QTR-…` (online) or `POS-…` (venue cash desk). |
| Ticket holder | One issued ticket (one person/seat) with its own code `<ref>-TKTnn` and QR image. |
| Sadad | Qatari payment gateway (Web Checkout 2.1 + merchant API). |
| n8n | Automation server the client uses to send WhatsApp tickets/invoices and e-mails via webhooks. |
| POS | Point of sale at the venue (cash), operated by cashiers ("POS users"). |
| Gate / scanner | QR check-in at the entrance. |
| Team passcode | A shared staff password that gives kiosk-level staff access without a personal account. |
| Qatar time | `Asia/Qatar` (UTC+3, no DST). All business dates and cut-offs use it. |
| Cut-off | 18:00 Qatar on the event day: bookings, scanning, capacity alerts and invoice sending stop (R-004…). |

---

## Part 1 — System overview

### 1.1 What the system is
A complete event-ticketing platform for a camel festival in Qatar, Arabic-first (RTL), currency QAR:
- **Customers** browse active events, choose ticket quantities (VIP / normal / parking), fill buyer and per-ticket holder details, and pay online with **Sadad** (or choose cash at the venue). They receive an on-screen confirmation, a downloadable invoice with one QR code per ticket, an e-mail invoice and a WhatsApp message (through n8n).
- **Cashiers** sell tickets at the venue with a fast **POS** (cash), with customer autocomplete, limits and capacity alerts, and digitise card-terminal slips with an **AI receipt reader**.
- **Gate staff** admit visitors with a **QR scanner** (camera or manual search by reference/phone), with strict same-day, cut-off, single-use and payment rules, and see a **gate board** of entries.
- **Supervisors/admins** watch **live bookings**, **live visitors**, notifications and capacity; manage **events/seasons**, **ticket types**, **orders**, **transactions** (live Sadad data), **WhatsApp/e-mail invoice sending**, **customers**, **payment errors**, **reports** (Excel), **activity logs**, **visitor analytics**, **staff accounts**, **POS users** and **team attendance**, and configure branding, automation and Sadad in **settings**.
- **Integrations:** Sadad (checkout, callback, webhook, merchant API, diagnostics), n8n webhooks (WhatsApp + e-mail), transactional e-mail with delivery tracking, Microsoft Clarity, IP geolocation for visitor analytics, a vision LLM for receipt OCR, and a read-only MCP server for AI assistants.

### 1.2 Actors
| Actor | Original identity | Rebuild role(s) |
|---|---|---|
| Visitor / customer | anonymous | public (no account; NEW optional customer lookup by reference + phone) |
| Administrator | Supabase user with `admin` role or row in `admin_users` | `super_admin`, `admin` |
| Supervisor ("مشرف"/moderator) | Supabase user with `moderator` role | `supervisor` |
| Cashier | POS user profile (name + emoji), used under an admin/moderator/passcode session | `cashier` (NEW personal role) + `pos_users` profile |
| Gatekeeper | passcode or moderator session | `gatekeeper` (NEW) |
| Accountant / viewer | — | `accountant`, `viewer` (NEW, read-only finance/reporting) |
| Team kiosk | shared team passcode | passcode kiosk session (10 days, staff apps only, throttled) |
| Systems | Sadad, n8n, mail provider, AI assistant | signed webhooks / API tokens |

### 1.3 End-to-end flows (summary — details in Part 2)
1. **Online booking:** Home `/` → Ticket selection `/tickets/{event}` (live availability, limits) → Checkout `/checkout` (buyer + holders, per-person 5-ticket pre-check) → server `CreateOrder` (prices from DB, stock locked, 30-minute hold, reference `QTR-D-M-YYYY-XXXXXXXXXXXX`, ticket codes, QR PNGs) → signed Sadad form POST → Sadad → callback/webhook → **merchant API verification is the only proof of payment** → confirm (exactly-once side effects: invoice e-mail, admin sale e-mail, n8n `payment_confirmed`) → Payment result `/payment/result?ref=` (polls verify every ≥12 s, "delayed" after 120 s) → Confirmation / Invoice `/invoice/{ref}` (PDF/PNG download).
2. **Cash at venue (web):** same as 1 but `payment_method=cash_pos`, order stays `pending` without expiry (parity) and n8n `order-created` payload is sent; staff confirm/cancel later.
3. **POS sale:** `/admin/pos` → event + cashier → ticket cards & cart (VIP+normal ≤ 5 per sale) → buyer & holders (autocomplete, history chips) → server `CreateOrder(source=pos)` → `confirmed` immediately, `POS-…` reference, QR codes, admin e-mail → success dialog with fireworks.
4. **Gate:** `/admin/qr-scanner` → camera/manual → ticket review → admit (today's event only, before 18:00, single use, paid) → history, gate board, live bookings, WhatsApp marker.
5. **Invoice automation:** scheduled batch sends confirmed Sadad orders to n8n/WhatsApp with interval, batch size and random delays; n8n reports back via `n8n-response`.
6. **Season lifecycle:** "Start new event" dialog creates a date range of events with all ticket types, optionally archives previous ones and sets the current event; archived data stays browsable (current vs archive toggles everywhere).

### 1.4 Module map (feature counts)
| Part | Module code | Module | Features |
|---|---|---|---|
| 2.1 | PUB | Public website | 97 |
| 2.1 | ORD | Booking & order engine | 45 |
| 2.1 | QR | Ticket & QR codes, ticket viewer | 24 |
| 2.1 | UI | Design system & brand | 17 |
| 2.2 | PAY | Sadad payments | 60 |
| 2.2 | NTF | Notifications & automation (e-mail, n8n/WhatsApp, invoice batches) | 45 |
| 2.2 | API | Integrations, MCP, endpoint catalogue | 12 |
| 2.3 | ADM-ORD | Admin — orders | 41 |
| 2.3 | ADM-TRX | Admin — transactions | 18 |
| 2.3 | ADM-INV | Admin — invoice sending console | 24 |
| 2.3 | ADM-PERR | Admin — payment errors | 9 |
| 2.3 | ADM-SDD | Admin — Sadad diagnostic & transaction dialog | 13 |
| 2.3 | ADM-TKT | Admin — ticket types, inventory, booked tickets, recycle bin | 26 |
| 2.3 | ADM-CUS | Admin — customers & customer lookup | 19 |
| 2.4 | AUTH | Authentication, roles, passcode, staff accounts | 31 |
| 2.4 | ADM-SHL | Admin shell (header, sidebar, badges, quick links) | 16 |
| 2.4 | ADM-SET | Settings & data management | 42 |
| 2.4 | ADM-EVT | Events & seasons | 25 |
| 2.4 | ADM-CAP | Capacity alerts | 10 |
| 2.4 | ADM-NTF | Admin notifications page & e-mail timeline | 14 |
| 2.4 | ADM-RPT | Reports (Excel) | 11 |
| 2.4 | ADM-LOG | Activity logs | 12 |
| 2.4 | ADM-VIS | Visitor analytics & visitors vs scans | 17 |
| 2.4 | ADM-STF | POS users, staff roles, team attendance, manual check-in | 28 |
| 2.5 | POS | Point of sale | 46 |
| 2.5 | RCP | POS card-receipt reader (OCR) | 23 |
| 2.5 | GATE | Gate scanner & check-in | 50 |
| 2.5 | LIVE | Live bookings & live visitors | 43 |
| 2.5 | STF | Staff access, hub, gate board, attendance page | 27 |
| 2.5 | SFX | Sound & motion effects | 17 |
| | | **Total** | **862** |

### 1.5 Original route map → new route map
| Original route | Guard (original) | New route (Laravel) | Access (rebuild) |
|---|---|---|---|
| `/` | public | `/` | public |
| `/tickets/:eventId` | public | `/events/{event}/tickets` (keep `/tickets/{event}` as 301 redirect) | public |
| `/checkout` | public | `/checkout` | public (requires a selection in session) |
| `/payment/result`, `/sadad-callback` (page) | public | `/payment/result?ref=` | public (reference = capability; NEW signed token) |
| `/confirmation` | public | `/confirmation/{ref}` | public, signed |
| `/invoice/:bookingReference` | public | `/invoice/{ref}` (+ `.pdf`, `.png`) | public reference capability; national ID hidden except admins |
| `*` | public | Laravel 404 view | public |
| `/.lovable/oauth/consent` | session | `/oauth/authorize` (MCP OAuth consent) | admin |
| `/admin/login` | public | `/login` (unified) and `/admin/login` (Filament) | public |
| `/admin/dashboard/*` (17 pages) | adminOnly | `/admin/*` (Filament panel) | admin, super_admin (+ accountant/viewer read-only pages) |
| `/admin/pos` | staff | `/staff/pos` | cashier, supervisor, admin, kiosk |
| `/admin/pos-receipts` | staff (really admin) | `/staff/receipts` | cashier, supervisor, accountant, admin |
| `/admin/qr-scanner` | staff | `/staff/scanner` (PWA) | gatekeeper, supervisor, admin, kiosk |
| `/admin/tickets?ref=` | staff | `/staff/tickets/{ref}` | staff |
| `/live-bookings` | staff | `/staff/live-bookings` | supervisor, admin, kiosk |
| `/live-visitors` | adminOnly | `/admin/live-visitors` | admin |
| `/staff` | staff | `/staff` | all staff roles + kiosk |
| `/staff/attendance` | staff | `/staff/attendance` | edit: supervisor/admin; read-only: kiosk |
| `/staff/gate` | staff | `/staff/gate-board` | staff |
| Edge functions (29) | various | `/api/v1/*`, `/payments/sadad/*`, `/webhooks/*`, jobs & commands (catalogue in 2.2 API) | per endpoint |

### 1.6 Data at a glance (details in Part 4)
`users` (+ spatie roles/permissions) · `login_attempts` · `kiosk_devices` (NEW) · `events` · `ticket_types` (was `tickets`) · `popup_banners` · `customers` · `orders` · `order_items` (NEW) · `ticket_holders` · `ticket_checkins` (NEW) · `scan_queue` (NEW) · `deleted_tickets` (recycle bin) · `expired_qr_codes` · `refunds` (NEW) · `pos_users` · `pos_receipts` · `payment_events` · `payment_attempts` (NEW) · `sadad_transactions` (NEW) · `payment_errors` · `email_delivery_events` · `automation_dispatches` (NEW) · `activity_log` · `settings` (spatie) · `media` · `staff_attendance` · `active_visitors` · `page_views` · Laravel infrastructure tables.


---

## Part 2 — Complete feature inventory (862 features, one by one)

Every feature of the original application, grouped by module. Each entry gives the exact behaviour to reproduce; *Rebuild:* lines say how to implement it better or which defect to avoid. Modules end with NEW upgrades and the original defects to avoid.

## 2.1 Public website, booking engine, QR & design

This part covers the customer-facing site (`PUB`), the server-side booking and order engine (`ORD`), ticket codes and QR pictures including the staff ticket viewer (`QR`), and the visual identity (`UI`). Payment gateway internals (Sadad signing, callback, webhook, verify) are only referenced here; they are specified in the payments module. Currency is QAR; business time is `Asia/Qatar` (UTC+3, no DST).

---

### PUB — Public website

Arabic-first, RTL booking site used by festival visitors on mobile and desktop. New app: Blade + Livewire 3 + Alpine.js + Tailwind CSS 4 pages under the `web` route group (no auth), with the routes `/`, `/tickets/{event}`, `/checkout`, `/payment/result`, `/sadad-callback`, `/confirmation`, `/invoice/{reference}`, plus a 404 handler.

#### App shell & global widgets

- **F-PUB-001 · Public route map** — Exact public routes and behaviour:
  | Path | Screen | Notes |
  |---|---|---|
  | `/` | Event home | crawlable |
  | `/tickets/{eventId}` | Ticket selection | crawlable |
  | `/checkout` | Checkout | requires a ticket selection, else redirect |
  | `/confirmation` | Confirmation | requires a remembered booking, else redirect to `/` |
  | `/invoice/{bookingReference}` | Public invoice | the reference is the access capability |
  | `/tickets` (no id) | permanent redirect to `/` | |
  | `/payment/result` | Payment result | accepts `ref`, `ORDERID`, `order` query keys |
  | `/sadad-callback` | identical to `/payment/result` | old Sadad return address must keep working |
  | `/sadad-redirect` | redirect to `/checkout` | legacy |
  | anything else | 404 page | |
  - *Rebuild:* the gateway's server-side callback (POST from Sadad) is a separate controller in the payments module; `/sadad-callback` as a browser GET must still render the result page. Keep both paths permanently.
- **F-PUB-002 · Document language, direction and meta** — `<html lang="ar" dir="rtl">` for the whole site. Title `تذاكر الفعاليات في قطر - احجز تجربتك`; meta description `احجز تذاكر الفعاليات الرائدة في قطر. حجز آمن عبر الإنترنت مع تأكيد فوري.`; author `Qatar Ticketing Hub`. OpenGraph `og:type=website`, `og:image=/og-image.jpg`, `og:title`/`og:description` = title/description; Twitter card `summary_large_image` with the same image/title/description. Favicon `/favicon.png` (also `/favicon.ico`). Preconnect/dns-prefetch to the font hosts and the production domain.
  - *Rebuild:* when English is active, switch to `lang="en" dir="ltr"` and English meta strings; make title/description/OG image settings-driven.
- **F-PUB-003 · robots.txt** — Exactly: `User-agent: *`, `Allow: /`, `Disallow:` `/admin/`, `/staff`, `/live-bookings`, `/live-visitors`, `/checkout`, `/confirmation`, `/invoice/`, `/payment/`, `/sadad-callback`. Only `/` and `/tickets/{id}` are crawlable.
  - *Rebuild:* also send `X-Robots-Tag: noindex` on invoice/payment/confirmation responses.
- **F-PUB-004 · Global toast notifications** — All public feedback uses toasts positioned top-right, expanded, up to **6** visible, gap **12 px**; enter animation `slideInRight` 0.4 s ease-out, exit `slideOutRight` 0.3 s ease-in. Variants used: success, error, info. Long error toasts may contain multiple lines and an icon (see F-PUB-054).
  - *Rebuild:* one Alpine toast store fed by Livewire `dispatch('toast', …)`; in RTL place toasts top-left (logical "end") or keep top-right consistently — pick one and apply it to staff apps too.
- **F-PUB-005 · Page-loading fallback** — While a page bundle loads: full-screen centred spinner (48 px circle, bottom border in primary colour, spinning).
  - *Rebuild:* with server rendering this becomes a Livewire `wire:loading` overlay for long actions only.
- **F-PUB-006 · Legacy client cleanup (one-time)** — On first load after deployment, guarded by localStorage key `app-cleanup-popups-v1` = `done`: delete every localStorage/sessionStorage key whose lowercase name contains `popup` or `banner`, delete all CacheStorage caches, unregister all service workers, then set the guard. Wrapped in try/catch (storage may be blocked).
  - *Rebuild:* ship once as a small inline script on the public layout for the first weeks after cut-over (old SPA service workers/caches may still exist on visitors' phones), then remove.
- **F-PUB-007 · Settings-driven branding provider** — Public pages read a public settings projection (never secrets) with keys: `logo_url`, `hero_image_url`, `before_footer_image_url`, `header_bg_color`, `header_bg_image_url`, `hero_text`, `copyright_text`, `admin_phone`, `current_event_id`, `clarity_enabled`, `clarity_project_id`. Defaults when missing: `header_bg_color = #D4B78A`, `copyright_text = جميع الحقوق محفوظة`, everything else null/empty. Client cache key `cached_settings` with TTL **5 min**; pages paint from cache first; on fetch error with no cache → defaults. Changes reach visitors after ≤5 min (cache) plus reload.
  - *Rebuild:* a `PublicBrandingSettings` (spatie/laravel-settings) class cached in Redis and busted on save, shared to all Blade views via a view composer; no client cache needed. Legacy hardcoded invoice-automation numbers that the old provider carried (`60, 1, 10, 300, 600`) are not public settings — drop them here.
- **F-PUB-008 · Header branding** — Every public header uses `header_bg_color` (default `#D4B78A`, sand). Home header additionally: bottom border `#A85740`; if `header_bg_image_url` is set it is drawn as a horizontally repeating texture (`repeat-x`, height 100 %, centred) under the gradient `linear-gradient(to top, rgba(212,183,138,0.8), rgba(212,183,138,0.4))`. Logo `logo_url` (height 53 px on home, `eager`); when no logo, the home/checkout/result headers show the text `فعاليات قطر` in `#6B4E3D`; the ticket-selection header shows nothing. Logo click → `/` (on selection, checkout, result pages). Headers are sticky (`top:0`, z 10) with backdrop blur.
- **F-PUB-009 · Hero section (home)** — Image `hero_image_url` or the bundled fallback `qatar-event-hero.jpg`, full width, natural height (`object-contain`), loaded eagerly. Overlay gradient `linear-gradient(to bottom, rgba(168,87,64,0.6), rgba(168,87,64,0.4), #F5EFE7)` (terracotta fading into page cream). If `hero_text` is non-empty it is shown centred, white, bold, `text-4xl` / `md:text-6xl`, drop shadow, newlines preserved.
- **F-PUB-010 · Before-footer decorative image** — If `before_footer_image_url` is set, a full-width image (lazy) with overlay `linear-gradient(to bottom, rgba(245,239,231,0.8), rgba(245,239,231,0.4))` is rendered directly above the footer on home, ticket selection and checkout.
- **F-PUB-011 · Footer** — On every public page and the staff ticket viewer. Background `#E77E5A` (coral); if `header_bg_image_url` is set it is reused as a repeat-x texture under `linear-gradient(to top, rgba(231,126,90,0.8), rgba(231,126,90,0.4))`. Layout: column on mobile, row with space-between on `md+`, gap 24 px, padding 32 px vertical. Contents: `© {current year} {copyright_text}` (white, small); version label `v1.1` (white 70 %); trust row: shield icon + `دفع آمن ومحمي`, vertical divider, logos Visa, Mastercard, Apple Pay, divider, Sadad (each 32 px tall, lazy).
  - *Rebuild:* version label from `config('app.version')`; trust-row logos as settings-managed media.
- **F-PUB-012 · WhatsApp support button** — Only on the home page and only when `admin_phone` is set. Link `https://wa.me/{admin_phone with spaces, dashes and parentheses removed}` (keeps `+` and digits), new tab, `rel="noopener noreferrer"`, aria-label `الدعم الفني عبر الواتس اب`. Fixed bottom 24 px / right 24 px, z 50, pill shape, background `#25D366` (hover `#20BA5A`), white WhatsApp glyph 24 px, label `الدعم الفني` visible only from `sm` up (icon-only on phones), hover scale 1.05.
  - *Rebuild:* strip the `+` for `wa.me` (wa.me expects digits only), and allow a separate `support_whatsapp` setting instead of reusing the admin phone that is also used for internal alerts.
- **F-PUB-013 · Pop-up banners (retired)** — A `popup_banners` table (title, message, image_url, is_active default false, timestamps) and admin strings exist, but the public site no longer displays any pop-up; the retired feature is actively purged from browsers (F-PUB-006). Do not rebuild the public pop-up unless re-requested (see Upgrades).

#### Home — events listing (`/`)

- **F-PUB-014 · Event visibility rule** — If settings `current_event_id` is set, only that event is queried; otherwise all events with `is_active = true AND is_archived = false`. Order: `event_date ASC, display_order ASC, start_time ASC`. Then filtered by the booking cut-off: an event is listed only while `canPurchaseTickets(event_date)` is true (F-PUB-016).
- **F-PUB-015 · Events cache and fetch retry** — Client cache `cached_events` = `{data, timestamp}` TTL **2 min**; the cached (unfiltered) list is re-filtered by the cut-off on read. Fetch: up to **3** attempts, each with a **10 s** timeout; waits 2 s then 4 s between failed attempts. Errors are logged only (no toast); after 3 failures whatever state exists is shown.
  - *Rebuild:* server-rendered list with a 60 s Redis cache tagged `events`, busted on event/ticket save; no client cache.
- **F-PUB-016 · Booking cut-off (event expiry)** — Comparison in Asia/Qatar calendar dates: event date in the future → open; in the past → closed; **same day → closed from 18:00 Qatar time**. `canPurchaseTickets = !isEventExpired`. The same 18:00 rule is used by the gate scanner.
  - *Rebuild:* one `EventClock` service (`isExpired(Event)`, `isToday(Event)`), cut-off hour as a setting (default 18), and enforce it server-side in order creation (the original enforces it only in the browser for web orders).
- **F-PUB-017 · Home loading and empty states** — Loading: a 70 vh skeleton block plus 3 card skeletons (image 256 px, title 3/4 width, line, button 48 px). Empty: full-height centred text `لا توجد فعاليات نشطة` (colour `#6B4E3D`, large).
- **F-PUB-018 · Events grid** — Container max-width 7xl, cards overlap the bottom of the hero by 6 rem (8 rem on `md+`), centred flex-wrap with 32 px gaps. Card widths: 100 % mobile, `calc(50% - 1rem)` on `md`, `calc(33.333% - 1.334rem)` on `lg`. Page background `#F5EFE7`.
- **F-PUB-019 · Event card** — White card, border `#D4B78A`, `hover:shadow-2xl`. Top accent bar 8 px in `header_bg_color` (fallback `#A85740`). Optional image 256 px high, cover, lazy. Body (padding 32 px): title centred bold `text-3xl` `#6B4E3D`; description centred bold, max 3 lines (`line-clamp-3`), `#6B4E3D`. Time row (only if start or end time exists): calendar icon `#A85740`, label `التوقيت`, value `#8B6F47`: `وقت البداية: {start}` + ` | ` (only when both) + `وقت النهاية: {end}`. Location row: pin icon `#A85740`, label `الموقع`, value `event.location` `#8B6F47`. (A full-date line `EEEE، d MMMM، yyyy` exists but is hidden — do not render.)
- **F-PUB-020 · 12-hour time format** — `HH:MM[:SS]` → `hh:MM م` (PM) / `hh:MM ص` (AM); hour zero-padded to 2 digits; 00 → 12 ص; 12 → 12 م; seconds ignored.
- **F-PUB-021 · Book button / closed state** — When bookable: full-width large button background `#A85740`, white text, ticket icon, label `احجز التذاكر الآن` → `/tickets/{event.id}`. When the cut-off passed between fetch and render: disabled button background `#6B4E3D`, opacity 0.6, lock icon, label `انتهى وقت الحجز`.
- **F-PUB-022 · Pagination** — **9** events per page; shown only when more than 9 events. Text `صفحة {n} من {total}`; previous button (`السابق`, chevron pointing right in RTL, disabled on page 1); numbered 40×40 buttons (active: `#A85740` background, white; inactive: outline, border `#A85740`, text `#6B4E3D`); next button (`التالي`, chevron pointing left, disabled on last page).
  - *Rebuild:* query-string `?page=`; Livewire pagination with a custom RTL view.

#### Ticket selection (`/tickets/{eventId}`)

- **F-PUB-023 · Event access validation** — In order: (a) the event exists, `is_active = true`, `is_archived = false`, else toast `لا يمكن حجز تذاكر لهذا التاريخ` and go to `/`; (b) if `current_event_id` is set and differs → toast `هذه الفعالية غير متاحة للحجز حالياً` and go to `/`; (c) cut-off passed (F-PUB-016) → toast `لا يمكن حجز تذاكر لهذا التاريخ` and go to `/`; any unexpected error → silently go to `/`.
  - *Rebuild:* do these checks in the controller and redirect with a flashed toast; return 404 for unknown ids.
- **F-PUB-024 · Release expired holds before showing stock** — Before reading availability the page triggers `release_expired_holds(event)` (closes this event's unpaid online orders whose 30-minute hold expired; see F-ORD-034). Result ignored.
- **F-PUB-025 · Ticket rows and display order** — Loads the event (`title`, `event_date`, `location`) and its ticket rows (`id, type, price, available_quantity, sold_quantity, description`). The event must have **exactly 3** ticket rows (one per type); otherwise toast `هذه الفعالية لا تحتوي على جميع أنواع التذاكر المطلوبة` and render whatever exists. Cards are always ordered `normal → vip → parking` regardless of DB order.
- **F-PUB-026 · Live remaining quantity per type** — `remaining = available_quantity − held(type)` where `held` = count of ticket holders of this event in orders that are `confirmed`, or `pending` with an unexpired (or null) `payment_expires_at` (F-ORD-033). `isSoldOut = remaining <= 0`.
  - *Rebuild:* use `tickets.available_quantity − tickets.sold_quantity` (the authoritative counter) — the two are equal after hold expiry runs; document one formula.
- **F-PUB-027 · Realtime availability refresh** — The page recounts held tickets whenever any ticket holder changes (unfiltered subscription) and when an order of this event changes `payment_status`.
  - *Rebuild:* Reverb public channel `event.{id}.availability` broadcasting `{type, remaining}` from the stock service after commit; Livewire `#[On('echo:...')]` refresh. Never subscribe the public to holder rows.
- **F-PUB-028 · Selection header and event header** — Sticky header with `header_bg_color` (before settings load: translucent card colour) and centred logo (40/48/53 px by breakpoint) → `/`. Event block: title `text-2xl`→`text-4xl` bold; location in muted text. (A date line exists but is hidden.) Section title `اختر تذكرتك`, subtitle `اختر الكمية لكل نوع تذكرة`.
- **F-PUB-029 · Ticket type card** — Width 100 % / 90 % (`sm`) / 80 % (`lg`), centred, min height 160/180 px, shadow; opacity 60 % when sold out.
  - Banner strip in `header_bg_color` with white bold title: vip `تذكرة دخول VIP`, normal `تذكرة دخول عادية`, parking `تذكرة موقف السيارات`; sold-out badge `نفذت الكمية` (destructive).
  - Icon tile: vip = crown (gold/secondary), normal = users (maroon/primary), parking = car (gold/accent). Type chip: `VIP` / `عادي` / `موقف`.
  - Description = `tickets.description` if set, else vip `مقاعد مميزة، وصول حصري`, normal `دخول عام لجميع مناطق وأنشطة الفعالية`, parking `موقف سيارات محجوز بالقرب من مدخل المكان`. Sold out line `نفذت الكمية - غير متوفر`.
  - Price `{price with 2 decimals} ريال قطري` (bold, primary colour). If sold out the stepper is replaced by `غير متاح`.
- **F-PUB-030 · Quantity stepper and caps** — Minus/plus icon buttons around the quantity (minus disabled at 0). Per type maximum = `min(remaining, 5)`. Admission (vip + normal) combined maximum **5** per order; parking maximum **5**. Plus is disabled when the type reached its max or (admission type and admission total ≥ 5). Hints under the stepper: parking `الحد الأقصى: 5`; vip/normal `الحد الأقصى: 5 إجمالي لـ VIP + العام`. Toasts on attempted increase: reached availability → `تم الوصول للحد الأقصى من التذاكر المتاحة`; admission total reached → `الحد الأقصى 5 تذاكر مسموح لـ VIP والدخول العام معاً`.
- **F-PUB-031 · Total and continue** — Total card (`bg-primary/5`): `المبلغ الإجمالي:` and `{total.toFixed(2)} ريال قطري` (client display only; the server re-prices). Button `المتابعة إلى الدفع` with arrow icon; disabled when total is 0. On click: if no quantity > 0 → toast `الرجاء اختيار تذكرة واحدة على الأقل`; else store the selection `[{ticketId, eventId, type, quantity, price}]` (key `ticketSelection`) and `selectedEventId`, then go to `/checkout`.
  - *Rebuild:* store the cart server-side in the session (`cart.event_id`, `cart.lines[ticket_id] = qty`); never trust its prices. A free ticket (total 0) still cannot be continued — keep unless free tickets become a requirement.
- **F-PUB-032 · Back to event list** — Yellow button (`bg-yellow-500`, hover `yellow-600`, black text) `← العودة لتفاصيل الفعالية` → `/`, separated from the content by a fixed 250 px top margin.
  - *Rebuild:* restyle in brand colours; normal spacing.
- **F-PUB-033 · Selection error toasts** — Event load failure toast `Failed to load event details`; tickets failure `Failed to load tickets` (English in an Arabic UI). Held-count and settings failures are logged only.
  - *Rebuild:* Arabic messages from lang files: `تعذّر تحميل بيانات الفعالية` / `تعذّر تحميل التذاكر` (NEW wording).

#### Checkout (`/checkout`)

- **F-PUB-034 · Checkout entry guard and initial state** — If there is no stored selection → redirect to `/tickets` (→ `/`). Otherwise build one holder slot per ticket, in selection order, each carrying its ticket type. Defaults: payment method `sadad`; customer `{name:"", email:"", phone:"", nationality:"قطر", countryCode:"+974", idNumber:""}`; each holder `{name:"", phone:"", nationality:"قطر", ticketType, idNumber:""}`. Loads branding and current availability (`remaining = available_quantity − held`, F-PUB-026).
- **F-PUB-035 · Checkout header and title** — Sticky header (`header_bg_color`), logo (32/40/48 px) or `فعاليات قطر` → `/`; ghost button (white text) `العودة للرئيسية` → `/`. Page title `إتمام عملية الدفع` (centred, bold). Page background `#F5EFE7`. Loading/empty: centred pulsing `جاري التحميل...`.
- **F-PUB-036 · Order summary card** — Title `ملخص الطلب`. One row per selection line: type name (vip `تذكرة دخول VIP`, normal `تذكرة دخول عادية`, parking `تذكرة موقف السيارات`), `الكمية: {qty}`, line total `{price×qty .toFixed(2)} ريال قطري`, and an inline −/qty/+ stepper. Availability line under each row: if `qty > remaining` → destructive with warning icon `متاح {remaining} فقط! يرجى تقليل الكمية`; else `متبقي: {remaining} تذكرة`, amber when `remaining ≤ 10`, muted otherwise. Total row `المبلغ الإجمالي` / `{total} ريال قطري`. Two notes prefixed `* `: `ستتلقى رسالة عبر واتساب تحتوي على تذاكرك ورموز QR بمجرد التأكيد.` and `قدّم رمز QR الخاص بك عند مدخل المكان في يوم الفعالية`.
- **F-PUB-037 · Summary quantity increase** — For vip/normal lines: if admission total ≥ 5 → error toast `الحد الأقصى هو 5 تذاكر (عادي + VIP) لكل شخص` and stop. Otherwise quantity +1, persist selection, rebuild holder slots, toast `تم زيادة الكمية`. (Parking has no cap here and no availability check; the server enforces both.)
  - *Rebuild:* cap parking at 5 and at `remaining` here too; keep already-typed holder data (append/remove slots instead of rebuilding).
- **F-PUB-038 · Summary quantity decrease / remove line** — If quantity is 1: confirm dialog `هل تريد حذف {type} من الطلب؟` (the original shows the raw type, e.g. `vip`); on confirm remove the line; if no line remains → clear the selection, info toast `تم حذف جميع التذاكر، سيتم إعادتك إلى صفحة التذاكر`, go to `/tickets`; else toast `تم حذف التذكرة`. If quantity > 1: −1, toast `تم تقليل الكمية`. Selection persisted and holder slots rebuilt each time.
  - *Rebuild:* show the Arabic type name in the confirm dialog; preserve typed holder data.
- **F-PUB-039 · Layout** — Two-column grid on `lg` (form 2/3, side column 1/3 with a sticky back button); single column below. Card 1 is numbered with a primary circle "1".
- **F-PUB-040 · Customer card header** — `معلومات العميل - التذكرة الرئيسية` with a chip showing the type of holder 1; subtitle `هذه المعلومات للتذكرة الرئيسية وستحصل على QR Code خاص بها`. The customer is always holder 1.
- **F-PUB-041 · Field: full name** — Label `الاسم الكامل *`; required; min 3 / max 100 chars; inline error `اكتب اسمك الكامل الحقيقي` when 1–2 characters. The original trims on every keystroke (spaces cannot be typed).
  - *Rebuild:* trim and collapse spaces on submit only; server rule min 2 / max 100, no control characters (F-ORD-003).
- **F-PUB-042 · Field: e-mail (optional)** — Label `البريد الإلكتروني` (no asterisk), type email. On input, the first `\S+@\S+` match is extracted (strips pasted surrounding text), else the trimmed value. Server: ≤254 chars and `^[^\s@]+@[^\s@]+\.[^\s@]{2,}$` when non-empty.
- **F-PUB-043 · Field: nationality (21 Arab countries)** — Label `الجنسية *`; RTL select with flag + name, default `قطر`. Exact order and flags: السعودية 🇸🇦, الإمارات 🇦🇪, قطر 🇶🇦, الكويت 🇰🇼, البحرين 🇧🇭, عمان 🇴🇲, مصر 🇪🇬, الأردن 🇯🇴, لبنان 🇱🇧, العراق 🇮🇶, سوريا 🇸🇾, اليمن 🇾🇪, ليبيا 🇱🇾, السودان 🇸🇩, الجزائر 🇩🇿, المغرب 🇲🇦, تونس 🇹🇳, موريتانيا 🇲🇷, الصومال 🇸🇴, جيبوتي 🇩🇯, فلسطين 🇵🇸. Changing nationality also resets the customer's country code to that country's code (F-PUB-044), fallback `+974`. Stored value is the Arabic country name (server max 60 chars).
  - *Rebuild:* store an ISO 3166 code plus localized names (Arabic/English) so reports and English UI work; keep the same 21 countries and order, Qatar default.
- **F-PUB-044 · Country dialling codes** — السعودية +966, الإمارات +971, قطر +974, الكويت +965, البحرين +973, عمان +968, مصر +20, الأردن +962, لبنان +961, العراق +964, سوريا +963, اليمن +967, ليبيا +218, السودان +249, الجزائر +213, المغرب +212, تونس +216, موريتانيا +222, الصومال +252, جيبوتي +253, فلسطين +970. The customer's code select (flag + code, 90/110/120 px wide by breakpoint) can be changed independently after a nationality change.
- **F-PUB-045 · Field: phone (WhatsApp number)** — Label `رقم الهاتف (عليه واتساب) *`; code select + tel input (RTL). Input normalisation on every change: Eastern-Arabic digits `٠-٩` → `0-9`; remove `+` and spaces; then by code: +974 drop leading `974`; +966 drop leading `966` then leading `0`; +971 drop leading `971` then leading `0`; +965 drop leading `965`; +973 drop leading `973`. Placeholder by code: +974 `8 أرقام`; +966/+971 `يبدأ بـ 5 (9 أرقام)`; +965 `يبدأ بـ 5، 6، أو 9 (8 أرقام)`; +973 `يبدأ بـ 3 (8 أرقام)`; others `رقم الهاتف (عليه واتساب)`. `maxLength`: +974 8, +966 9, +971 9, +965 8, +973 8, others unlimited. Helper text `بدون رمز الدولة`. The original enforces none of the "starts with / exact length" rules.
  - *Rebuild:* also convert Persian digits `۰-۹`; validate server-side per country (Qatar 8 digits starting 3/5/6/7; KSA 9 starting 5; UAE 9 starting 5; Kuwait 8 starting 5/6/9; Bahrain 8 starting 3; others 6–15 digits) using `libphonenumber` (propaganistas/laravel-phone); store E.164 once (F-ORD-018).
- **F-PUB-046 · Field: ID number** — Label `رقم الهوية *`, placeholder `رقم الهوية`, required, raw value. Server: `^[\p{L}\p{N}\-_/ ]{3,30}$`.
  - *Rebuild:* convert Arabic digits; trim; same server regex.
- **F-PUB-047 · Holder 1 auto-fill from customer** — Whenever customer name, phone, nationality, code or ID change (and name, phone, nationality are non-empty): holder 1 = `{name, phone: "{code} {phone}", nationality, idNumber}`. For other holders with an empty phone, phone is pre-filled with the code of their nationality (e.g. `+974`).
  - *Rebuild:* holder 1 is simply "the customer" in the submitted payload; pre-filling other holders' phone with only a prefix must not count as "filled" (see F-PUB-056).
- **F-PUB-048 · Additional ticket holders card** — Shown only when more than one ticket. Title `التذاكر الإضافية`; subtitle `كل تذكرة ستحصل على QR Code فريد خاص بها. يرجى إدخال معلومات كاملة لكل حامل تذكرة.` One box per ticket from #2: numbered circle, `تذكرة #{n} - {type name}`, chip `QR Code مستقل`; two-column grid on `sm+` with: name (`الاسم الكامل *`, required); nationality (same 21-country select); ID (`رقم الهوية *`, required); phone (label intended `رقم الهاتف *` — the original renders the missing key as `phone *`), tel, RTL, required, helper `بدون رمز الدولة`.
- **F-PUB-049 · Holder phone country derivation** — An additional holder's dialling code is derived from the holder's **nationality** (no separate code select). Displayed value = stored phone without its `+code ` prefix; on input apply the same normalisation as F-PUB-045 and store `"{code} {digits}"`; placeholder and maxLength follow the nationality's code. Changing nationality rewrites the stored phone with the new code.
  - *Rebuild:* give each holder an explicit code select defaulting to the nationality's code (a Saudi national with a Qatari number must be possible).
- **F-PUB-050 · Terms / instructions acknowledgement** — Red card (2 px `red-500` border, `red-50` background, warning icon) titled `التعليمات` with three lines verbatim: `1- التذكرة المباعة غير قابلة للتعديل او الإستبدال او إسترجاع قيمتها`, `2- ممنوع دخول الأطفال دون 10 سنوات`, `3- يكون إستخدام التذاكر للدخول مرة واحدة فقط`. Full-width toggle button (tall, bold): not accepted → red, pulsing, `اضغط هنا للموافقة وإتمام عملية الدفع`; accepted → green `✓ تم الموافقة على التعليمات`. Clicking toggles.
  - *Rebuild:* terms lines as an editable setting (Arabic + English); store `terms_accepted_at` and the terms version on the order; enforce acceptance server-side.
- **F-PUB-051 · Payment method choice** — Title `اختر طريقة الدفع`; radio group (RTL). Visible option `sadad`: card icon + `سداد (دفع إلكتروني)` (subtitle repeats the same text). Option `cash_pos` (`الدفع بالبطاقة عند الحضور`) exists but is hidden, so public visitors can only pay online.
  - *Rebuild:* a setting `public_cash_at_venue_enabled` (default off) controls whether the second option appears; remove the duplicated subtitle.
- **F-PUB-052 · Submit button** — Full-width large button `إتمام عملية الدفع`; disabled while submitting or until terms are accepted; while submitting shows spinner + `جاري التحميل...`. Back button in the side column (yellow, `← العودة لاختيار التذاكر`) → `/tickets/{selectedEventId}`. Before-footer image and footer follow.
  - *Rebuild:* the button is inside the form; Enter must not bypass the terms gate (server rejects without acceptance).
- **F-PUB-053 · Submit — basic checks (in order)** — (1) If method is Sadad and total < **3** QAR → `الحد الأدنى للدفع عبر سداد هو 3 ريال قطري`. (2) Customer name, phone, nationality, ID all required → original toast `Please fill in all customer information` (English). (3) No stored event id → `لم يتم العثور على الفعالية المحددة` and go to `/`.
  - *Rebuild:* replace (2) with per-field Arabic validation messages from a FormRequest (`يرجى ملء جميع الحقول المطلوبة` as summary).
- **F-PUB-054 · Submit — availability pre-check** — For each line with qty > 0, sequentially call the availability check (F-ORD-032). On the first failure show a 6-second error toast (RTL): bold line with icon `عدد التذاكر المطلوبة غير متاح`; `{type name}: {server message or 'غير متوفر'}`; `يرجى تعديل الكمية والمحاولة مرة أخرى`. Server messages: `نوع التذكرة غير موجود لهذه الفعالية`, `عذراً، نفدت التذاكر من هذا النوع حالياً`, `عذراً، لا تتوفر تذاكر كافية. المتاح: N تذكرة`; client fallbacks `فشل في التحقق من توفر التذاكر`, `حدث خطأ غير متوقع`. Then log a payment error `TICKETS_UNAVAILABLE` (F-PUB-062), refresh availability, stop.
  - *Rebuild:* advisory only (the order transaction is authoritative); can be one server call returning all shortages.
- **F-PUB-055 · Submit — holders completeness** — Every holder needs name, phone, nationality, ID → original toast `Please fill in information for all ticket holders` (English).
  - *Rebuild:* Arabic per-holder messages (`تذكرة #{n}: …`); a phone that is only a country code is invalid.
- **F-PUB-056 · Submit — per-person limit pre-check** — Group vip/normal holders by person key: `id:{digits}` if the ID has ≥ 6 digits, else `ph:{last 8 digits}` if the phone has ≥ 8 digits; holders without a key are skipped; the group's first holder name represents it; `requested` = group size. For each group call the person-count lookup (F-ORD-035). Violation when `existing + requested > 5`; `remaining = max(0, 5 − existing)`. Each violation → error toast: remaining 0 → `{name} بلغ الحد الأقصى (5 تذاكر) لهذه الفعالية`; else `{name} لديه {existing} تذكرة — يمكنه إضافة {remaining} فقط (طلب {requested})`. Then log `TICKET_LIMIT` with all violations joined by ` | `, stop. Lookup errors are ignored (the DB guard remains authoritative).
- **F-PUB-057 · Submit — price freshness check** — Reload current prices of the selected ticket ids. A missing id → `إحدى التذاكر المختارة لم تعد متاحة. يرجى العودة واختيار التذاكر من جديد.` and stop. Any changed price → update the selection with live prices and info toast `تم تحديث أسعار التذاكر. يرجى مراجعة الإجمالي ثم المتابعة.`; the customer must click again. DB errors → treated as current.
- **F-PUB-058 · Submit — payload construction** — Customer phone: remove spaces and `+`; if it starts with the digits of the selected code, strip them. Holder payload: `ticket_id` = the first selection line with the holder's type; phone parsed with `^(\+?\d{2,4})[\s-]?(.+)$` → `country_code = "+" + group1`, `phone = group2` without spaces/dashes; no match → `+974` and the stripped phone. Request body: `{customer:{name, email, phone, country_code, nationality, id_number}, items:[{ticket_id, quantity}], holders:[{ticket_id, name, phone, country_code, nationality, id_number}], payment_method}`. The public site never sends `source`, `pos_user_id`, `staff_passcode`, prices or totals.
  - *Rebuild:* post the Livewire form to `OrderService::create()` (F-ORD-001) directly; no client-side parsing of phone strings.
- **F-PUB-059 · Submit — success handling** — On success `{order:{id, booking_reference, total_amount, payment_method, payment_status, payment_expires_at}, payment?:{url, fields}}`: remember the reference (F-PUB-097), clear the selection. Sadad: info toast `جاري تحويلك إلى بوابة الدفع الآمنة...`, keep the button disabled, auto-submit the payment form (F-PUB-060); if the redirect is refused, go to `/payment/result?ref={ref}`. Cash: clear `selectedEventId`, success toast `تم إنشاء الحجز بنجاح!`, go to `/confirmation`.
- **F-PUB-060 · Sadad hand-off form** — A hidden `<form method="post" action="{url}" target="_self">` with one hidden input per field (values set safely, no HTML injection), appended to the document and submitted. Allowed targets only `https://` on hosts `sadadqa.com`, `www.sadadqa.com`, `secure.sadadqa.com`; anything else throws (`Refusing to send payment details …`) — look-alikes such as `sadadqa.com.evil.example` are refused. Field set is opaque to the page (server-signed).
  - *Rebuild:* render an auto-submitting Blade view (`<form>` + `<noscript>` button `متابعة إلى سداد`) from the server; host allow-list in config.
- **F-PUB-061 · Submit — error message mapping** — Priority: (1) code `ticket_limit_exceeded` → server Arabic message verbatim; (2) any API code → table below; (3) message contains `TICKET_LIMIT_EXCEEDED` → text after the marker or `الحد الأقصى هو 5 تذاكر لكل شخص`; (4) legacy tags `CAPACITY_EXCEEDED` → `نفدت التذاكر من هذا النوع. يرجى اختيار نوع آخر أو المحاولة لاحقاً.`, `AMOUNT_MISMATCH` → `تغيّر سعر التذاكر. يرجى تحديث الصفحة وإعادة المحاولة.`, `INVALID_QUANTITY` → `عدد التذاكر غير صالح. يرجى تعديل الطلب.`; else `Failed to create booking. Please try again.`.
  | code | HTTP | Arabic message |
  |---|---|---|
  | `insufficient_stock` | 409 | `عذراً، لم تعد هناك تذاكر كافية من هذا النوع. يرجى تقليل الكمية أو اختيار نوع آخر.` + ` (VIP / الدخول العام / المواقف)` |
  | `quantity_limit_exceeded` | 422 | `تجاوزت الحد الأقصى للتذاكر في الطلب الواحد.` |
  | `below_minimum_amount` | 422 | `الحد الأدنى للدفع عبر سداد هو 3 ريال قطري.` |
  | `event_not_available` | 409 | `هذه الفعالية غير متاحة للحجز حالياً.` |
  | `too_many_pending_orders` | 429 | `لديك حجوزات غير مكتملة. يرجى إتمامها أو المحاولة لاحقاً.` |
  | `payment_unavailable` | 503/502 | `الدفع الإلكتروني غير متاح حالياً. يمكنك اختيار الدفع عند الحضور.` |
  | `validation_failed` | 400 | `يرجى التحقق من البيانات المُدخلة والمحاولة مرة أخرى.` |
  | `invalid_tickets` | 422 | `إحدى التذاكر المختارة لم تعد متاحة. يرجى العودة واختيار التذاكر من جديد.` |
  | `holders_mismatch` | 422 | `يرجى إدخال بيانات حامل كل تذكرة.` |
  | `unauthorized` | 401 | `انتهت الجلسة أو كلمة مرور الفريق غير صحيحة. يرجى تسجيل الدخول من جديد.` |
  | `forbidden` | 403 | `ليست لديك صلاحية لتنفيذ هذا الإجراء.` |
  | `network_error` | — | `تعذر الاتصال بالخادم. تحقق من اتصالك بالإنترنت وحاول مرة أخرى.` |
  | `ticket_limit_exceeded` | 422 | server text, e.g. `الحد الأقصى هو 5 تذاكر (عادي + VIP) لكل شخص. {name} لديه {n} تذكرة بالفعل` |
  | other | — | `حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.` |
  - *Rebuild:* `payment_unavailable` text must not suggest pay-at-venue while that option is hidden; use `الدفع الإلكتروني غير متاح حالياً. يرجى المحاولة لاحقاً.` unless F-PUB-051's setting is on. Drop legacy tag mapping (4) once old triggers are gone; final fallback in Arabic.
- **F-PUB-062 · Client-side payment-error logging** — Rows written to `payment_errors` from checkout: `TICKETS_UNAVAILABLE` (source `site`, message `عدد التذاكر المطلوبة غير متاح ({type}): {message}`), `TICKET_LIMIT` (source `site`), `CHECKOUT_FAILED` (source classified, message `{shown message} — {raw error}`, raw `{paymentMethod}`). Columns: `event_id, customer_name, customer_phone (code+phone, no space), quantity (Σ), amount (cart total), error_source, error_code, error_message, raw{…, url, userAgent}`; order/reference/payment id null. Classification `classifyPaymentError(message + code)` lower-cased: `bank` if it contains any of `card, بطاقة, declin, مرفوض, insufficient, رصيد, bank, بنك, expired card, منتهية, cvv, 3d, authorization, تفويض, issuer`; else `sadad` if any of `sadad, سداد, merchant, checksum, gateway, بوابة`; else `site`. Logging never throws.
  - *Rebuild:* log server-side inside the order controller (no anonymous insert endpoint); if a client beacon is kept, rate-limit it (e.g. 10/min/IP) and truncate `raw`.

#### Payment result (`/payment/result`, `/sadad-callback`)

- **F-PUB-063 · Booking reference resolution** — Valid reference regex `^[A-Za-z0-9][A-Za-z0-9_-]{0,59}$`. Query keys tried in order: `ref`, `ORDERID` (Sadad's name), `order`; value trimmed and must match. Fallback: the most recently remembered reference in the browser (F-PUB-097). None → phase `not_found`.
- **F-PUB-064 · Payment status polling and verification** — Phases: `checking | confirmed | failed | delayed | not_found | cash`. Loop: read the public order status (F-ORD-037) every **3 s**; API/network error → silent retry in 3 s; unknown reference → `not_found` (stop). If method is Sadad and the order "may be paid" (`pending`, or `failed` not yet verified in this round) and ≥ **12 s** since the last verify (first verify immediately at t=0), ask the server to verify with Sadad (payments module `verify-payment`), then re-read. Then: `confirmed` → confirmed (stop); `failed`/`cancelled` → failed (stop); method `cash_pos` → cash (stop); elapsed ≥ **120 s** → delayed (stop); else continue. A `failed` order is verified **exactly once** per round (late payment rescue); `cancelled` (staff) is never verified. "Check again" restarts a fresh 120 s round. Timers stop when leaving the page. (These behaviours are unit-test pinned.)
  - *Rebuild:* Livewire component with `wire:poll.3s` (stops by state) or Reverb `order.{ref}.status` private-by-reference channel; server-side verify throttling stays (10 s minimum per order, 3-day window, admin bypass — payments module).
- **F-PUB-065 · Phase headlines** — Icon in an 80 px muted circle, `role="status" aria-live="polite"`:
  | phase | icon / tone | title | description |
  |---|---|---|---|
  | checking | spinner, primary | `جارٍ التحقق من الدفع...` | `لا تُغلق هذه الصفحة. نتأكد من دفعتك مع سداد وسيظهر حجزك خلال لحظات.` |
  | confirmed | check, green-600 | `تم الدفع بنجاح!` | `تم تأكيد حجزك. تجد تذاكرك ورموز QR أدناه، وسنرسلها لك أيضاً عبر واتساب.` (never shown: redirect) |
  | failed | X, destructive | `لم تكتمل عملية الدفع` | `لم يتم تأكيد أي دفعة لهذا الحجز. إذا تم الخصم من حسابك فسيُؤكَّد حجزك تلقائياً، أو تواصل معنا مع الرقم المرجعي.` |
  | delayed | clock, amber-600 | `ما زلنا نتحقق من دفعتك` | `قد يستغرق تأكيد الدفع بضع دقائق. احتفظ بالرقم المرجعي أدناه؛ إذا خُصم المبلغ فسيتأكد حجزك تلقائياً، ويمكنك أيضاً المحاولة مرة أخرى.` |
  | not_found | search-X, muted | `لم نعثر على الحجز` | `تحقق من الرابط أو ابدأ حجزاً جديداً.` |
- **F-PUB-066 · Confirmed / cash redirect** — On `confirmed`: remember the reference, clear any stored selection, then replace-navigate to `/confirmation`. On `cash`: replace-navigate to `/confirmation`.
- **F-PUB-067 · Order summary panel** — Shown when an order is loaded and phase ≠ not_found. Reference box (muted, centred): label `الرقم المرجعي للحجز`, reference in mono, xl, bold, LTR, selectable; copy icon button (aria `نسخ الرقم المرجعي`) → clipboard, toast `تم النسخ`, check icon for 2 s. Grid (2 columns): `عنوان الفعالية`, `تاريخ الفعالية` (format `EEEE، d MMMM yyyy`, Arabic locale), `موقع الفعالية`, `الكمية`, `المبلغ` (`{total} ريال قطري`), `طريقة الدفع` (`سداد (دفع إلكتروني)` / `الدفع عند الحضور`), `الحالة` badge: confirmed `مؤكد` (default), pending `بانتظار الدفع` (secondary), failed `لم يكتمل` (destructive), cancelled `ملغي` (destructive); unknown → pending style.
- **F-PUB-068 · Delayed notice and actions** — Delayed: amber notice box (`amber-300` border, `amber-50` bg, `amber-900` text, warning icon) repeating the delayed description. Action buttons (column on mobile, row on `sm+`): delayed → `تحقق مرة أخرى` (restart round); resume allowed (phase delayed or failed AND method sadad AND status pending) → outline `إعادة فتح صفحة الدفع` (spinner while working) which requests a freshly signed Sadad form for the same booking (payments module `sadad-payment`) and auto-submits it; on error toast the mapped message (F-PUB-061; `not_payable` → generic); failed or not_found → `حجز جديد` → `/tickets/{order.event_id}` or `/`; always → outline `العودة للرئيسية` → `/`.
  - *Rebuild:* in `failed` the status is never `pending`, so the resume button is effectively delayed-only — keep that behaviour explicitly.
- **F-PUB-069 · Result page layout** — White theme background (not cream), header with `header_bg_color` and centred logo (or `فعاليات قطر`) → `/`, main max-width 3xl, footer. Microsoft Clarity is never loaded on this page (URL carries the reference).

#### Confirmation (`/confirmation`)

- **F-PUB-070 · Load the remembered booking** — Uses only the **most recent** remembered reference, plus legacy stored order ids (`orderIds`, deleted after reading). Nothing → go to `/`. Each loaded via the public order lookup (F-ORD-038), de-duplicated by order id. No polling.
  - *Rebuild:* the server session stores `last_booking_reference` after order creation/payment; also allow `?ref=`; show all bookings of this session (up to 10) newest first.
- **F-PUB-071 · Confirmed view** — When any order is `confirmed`: logo (64 px), green check circle, heading intended `تم تأكيد الحجز!` (the original renders the untranslated key text `bookingConfirmed` — bug), subtitle `تم تأكيد دفعتك بنجاح. يمكنك تحميل الفاتورة الآن.`, then one invoice card per confirmed order (F-PUB-076…084), then `العودة للرئيسية` (secondary) → `/`.
- **F-PUB-072 · Pending view** — Otherwise: clock icon (gold), heading `تم استلام الحجز!`, subtitle `حجزك قيد انتظار تأكيد الدفع`. Card: notice (gold left border) `الدفع قيد الانتظار` / `تم إنشاء حجزك بنجاح. سيقوم فريق الإدارة لدينا بتأكيد دفعتك قريباً. بمجرد التأكيد، ستتلقى تذاكرك مع رموز QR عبر البريد الإلكتروني.`; `تفاصيل الحجز` grid per order: `الرقم المرجعي للحجز` (mono), `نوع التذكرة` (raw type, capitalised), `الكمية`, `المبلغ` (`{total.toFixed(2)} ريال قطري`), `طريقة الدفع` (`سداد (دفع إلكتروني)` / `الدفع بالبطاقة عند الحضور`), `الحالة` (raw status, untranslated); next steps `الخطوات التالية:` with 4 bullets: `احفظ الرقم المرجعي للحجز للرجوع إليه مستقبلاً`, `سيقوم فريق الإدارة لدينا بمراجعة وتأكيد دفعتك`, `ستتلقى رسالة عبر واتساب تحتوي على تذاكرك ورموز QR بمجرد التأكيد.`, `قدّم رمز QR الخاص بك عند مدخل المكان في يوم الفعالية`.
  - *Rebuild:* translate type and status; a `cancelled`/`failed` order gets its own "not completed" layout with a link to `/payment/result?ref=`; align channel wording (WhatsApp vs e-mail) with what is actually sent; for cash-at-venue use `cashBookingTitle`/`cashBookingDesc` (`تم استلام حجزك` / `احتفظ بالرقم المرجعي وأحضر رمز QR الخاص بك عند الحضور. سيتم تأكيد الدفع عند المدخل.`) which exist but are unused.
- **F-PUB-073 · Purchase analytics events** — For each loaded order: `confirmed` → Clarity event `ticket_purchase` with tags `booking_reference`, `purchase_amount`, `ticket_quantity`, `payment_method`, and session upgrade `ticket_purchase`; `cancelled` → event `payment_failed`, tag `payment_failure_reason=cancelled`, upgrade `payment_failed`.
  - *Rebuild:* fire once per order (session flag) to avoid duplicates on refresh; do not send the booking reference to a third party (send a hash).

#### Public invoice (`/invoice/{bookingReference}`)

- **F-PUB-074 · Invoice page loading and live refresh** — Loads the public order by reference (F-ORD-038) and maps it to invoice data (only holders that have a ticket code; `customer_phone = country_code + phone`; `paid_at = confirmed_at`; logo from settings). Refreshes every **3 s** only while the tab is visible, plus on window focus and on visibility change, plus a realtime update when any holder of this order changes — so a ticket scanned at the gate flips to "used" without reload. No status gating: pending/cancelled orders also render (no paid badge; PDF says `قيد الانتظار`). This is the link sent by WhatsApp/e-mail automations.
  - *Rebuild:* `GET /invoice/{reference}` → Livewire component with `wire:poll.5s.visible` or Reverb channel `invoice.{signed token}`; rate-limit lookups (e.g. 30/min/IP); NEW option to use a long random `invoice_token` in links instead of the 48-bit reference.
- **F-PUB-075 · Invoice page states** — Wrapper RTL, main max-width 3xl. Loading: 32 px spinner centred. Not found: card with search-X icon, `لم يتم العثور على الفاتورة`, `تأكد من رقم الحجز في الرابط، أو تواصل مع خدمة العملاء.`. Footer below. No header bar (logo is inside the card).
- **F-PUB-076 · Invoice card — header** — Card with gold-tinted border, top 6 px gradient bar `gold → maroon → gold` (right-to-left). Centered header: logo (56 px, CORS-safe for image export, alt `الشعار`), title `فاتورة الحجز`, reference (mono, small, muted), and when `payment_status = confirmed` a pill `تم الدفع بنجاح` with check icon (success colours).
- **F-PUB-077 · Invoice card — details and totals** — Grid 2 columns (3 on `sm+`) of label/value: `الاسم`, `رقم الهاتف` (LTR), `الجنسية` (`-` if empty), `الفعالية`, `التاريخ` (Arabic weekday, day, month name, year with Latin digits, Asia/Qatar), `نوع التذكرة` (order label type: normal `عادي`, vip `VIP`, parking `مواقف`). Totals box (paid: success tint; else gold/muted tint): `عدد التذاكر المدفوعة` (3xl bold quantity) and `المبلغ الإجمالي` `{total.toFixed(2)} ر.ق`.
- **F-PUB-078 · Invoice card — payment rows** — `وسيلة الدفع`: `نقاط البيع` for `cash_pos`, else `سداد (أونلاين)`; `رقم عملية سداد`: `payment_id` or `-` (LTR); `تاريخ الدفع`: date+time medium/short in Arabic with Latin digits, Asia/Qatar, or `-`.
- **F-PUB-079 · Invoice card — QR grid and used state** — Section `رموز التذاكر (QR)`, grid 2 (3 on `sm+`). For each ticket: if the code is a URL it is used as an image, else a QR is rendered client-side (512 px, margin 1). A ticket is "used" when `is_present` and `confirmed_at` are both set: then the QR is hidden and the box (destructive tint) shows a check icon, `تم استخدام هذه التذكرة`, the check-in time and `بواسطة: {confirmed_by_name}` if known. Otherwise a zoomable button (aria `تكبير رمز QR`, cursor zoom-in, hover scale 1.05 with dark overlay and zoom icon) with a 112 px QR. Caption: ticket type label. Footer line `شكراً لثقتكم — مهرجان قطر للإبل`.
  - *Rebuild:* QR rendered server-side (SVG inline, endroid) from the code; footer brand line from settings.
- **F-PUB-080 · QR enlarge dialog** — Same gradient bar; title `{type label} — {booking_reference}`; holder name (or customer name) and phone (`country_code+phone`, mono, LTR); QR 288 px in a white rounded box; hint `اعرض الرمز عند البوابة ليتم مسحه`.
- **F-PUB-081 · Download invoice as PDF** — Button `تحميل الفاتورة (PDF)`. Original: opens a new window with a printable A4 HTML document and calls print after 500 ms (popup blocked → alert `يرجى السماح بالنوافذ المنبثقة لتوليد الفاتورة`). Document (RTL, `@page A4, margin 1cm`, font Tahoma/Cairo 14 px, text `#4a3728`): container border `#e7e2dc` radius 12; header bottom border 2 px `#c27444`, logo 160 px wide, `فاتورة` (`#a4552b`, 22 px), `رقم الفاتورة: {ref}` (`#6b4b2a`); sections (titles `#a4552b`, underline `#d8b59f`): `بيانات العميل` (الاسم / رقم الهاتف / الجنسية), `بيانات الدفع` (وسيلة الدفع / رقم عملية سداد / تاريخ الدفع), `تفاصيل التذاكر` table (bg `#fdfaf8`, header bg `#f4ede6` text `#8b4a2a`) columns `نوع التذكرة | الكمية | اليوم | إجمالي المبلغ | حالة الدفع` → `{type} | {qty} | {event title} | {total} ر.ق | pill` (paid `مدفوع` `#e8f7e6`/`#2c7a3f`; else `قيد الانتظار` `#fff4e5`/`#b47400`); `رموز التذاكر (QR)` grid of 180×180 images with type captions; thanks line `شكراً لثقتكم في مهرجان قطر للإبل - جزيلات العطا - 2026 💛`; footer `جميع الحقوق محفوظة © مهرجان قطر للإبل - جزيلات العطا - 2026` (`#776150`, 11 px).
  - *Rebuild:* real PDF download `GET /invoice/{ref}/pdf` via spatie/laravel-pdf (Browsershot, Arabic shaping; mPDF fallback), file name `invoice-{ref}.pdf`; format the total with 2 decimals (original prints it raw); brand name, season and year from settings.
- **F-PUB-082 · Save invoice as image** — Button `حفظ كصورة (PNG)` (spinner while saving): captures the card at pixel ratio 2, white background, downloads `invoice-{booking_reference}.png`; errors logged only.
  - *Rebuild:* server PNG `GET /invoice/{ref}/png` (Browsershot screenshot of the invoice view) for reliability on iOS, keeping the same file name.
- **F-PUB-083 · Invoice home button** — Full-width secondary `للتحويل للصفحة الرئيسية` → `/`.
- **F-PUB-084 · Staff invoice pop-up** — The same invoice card is reused inside an admin dialog (from notifications) that loads by reference and polls every **15 s** while open (max-height 90 vh, scrollable, `لم يتم العثور على الفاتورة` when missing). Specified with the admin notifications screen; listed here because it shares the invoice component.

#### 404 and other public pages

- **F-PUB-085 · 404 page** — Centred: `404` (4xl/5xl/6xl bold), `عذراً! الصفحة غير موجودة` (muted), link `العودة للرئيسية` → `/` (full reload). Logs the attempted path.
  - *Rebuild:* `resources/views/errors/404.blade.php` in the public layout (header + footer), HTTP 404 status.
- **F-PUB-086 · AI-assistant OAuth consent page** — Route `/.lovable/oauth/consent?authorization_id=…` for connecting an MCP client to a staff account: missing id → `رابط غير مكتمل: authorization_id مفقود`; no session → redirect to `/admin/login?next=…`; already approved → immediate redirect; else card `ربط {client ?? "تطبيق"} بحسابك` / `سيتمكّن {client ?? "التطبيق"} من قراءة بيانات النظام بالصلاحيات نفسها التي يملكها حسابك.` with `موافقة` / `رفض`; errors `تعذّر إتمام الربط`, `لم يتم استلام رابط العودة من خادم التفويض`.
  - *Rebuild:* belongs to the MCP module (laravel/mcp OAuth via Passport/Sanctum); keep the Arabic consent copy.

#### Analytics, resilience, visitor tracking

- **F-PUB-087 · Microsoft Clarity loader** — Config `clarity_enabled`, `clarity_project_id` (valid `^[a-z0-9]{4,20}$`i), client cache `clarity_config_cache` TTL **10 min**. On every path change: if enabled, valid and the path is trackable → inject once `<script id="ms-clarity-script" async src="https://www.clarity.ms/tag/{id}">` with the queue shim, then tags `page_path={path}` and `site_area=public`. Non-trackable prefixes: `/admin`, `/staff`, `/live-bookings`, `/live-visitors`, `/sadad-redirect`, `/payment`, `/sadad-callback` (on those the script element is removed). `/`, `/tickets/*`, `/checkout`, `/confirmation`, `/invoice/*` are tracked. All calls are try/catch ("analytics must never break the app").
  - *Rebuild:* render the tag in the public layout only, server-decided per route; also exclude `/invoice/*` and `/confirmation` (they expose personal data and the reference); add a cookie-consent gate (NEW).
- **F-PUB-088 · Clarity custom events** — `ticket_purchase` and `payment_failed` as in F-PUB-073; helper `clarityTag(key, value)` and `clarityEvent(name)`.
- **F-PUB-089 · Stale-bundle error boundary** — Catches errors whose message contains `dynamically imported module`, `importing a module script failed`, `loading chunk`, `chunkloaderror`, or (`failed to fetch` and `module`); generic network errors do not count. Auto hard-reload once per **5 minutes** (sessionStorage `__lovable_chunk_reload_ts__`) by replacing the URL with `?__cb={timestamp}`. Fallback card (English): `Update available` / `Something went wrong`, explanatory text, `Refresh` button.
  - *Rebuild:* not needed for server-rendered pages; keep the idea for the PWA (service-worker update prompt `يتوفر تحديث جديد — تحديث`).
- **F-PUB-090 · Visitor tracking — identity and classification** — Per-tab `session_id` = `{epoch ms}-{random base36}` (sessionStorage `visitor_session_id`); `is_new_visitor` true only on the first evaluation ever in this browser (localStorage `is_returning_visitor = "true"`). `device_type`: tablet (`tablet|ipad|playbook|silk` or Android without `mobi`), mobile (`Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)`), else desktop. `browser` checked in order Firefox, Samsung Browser, Opera (`Opera`/`OPR`), IE (`Trident`), Edge (`Edge`/`Edg`), Chrome, Safari, Unknown. `os`: Windows, macOS, Linux, Android, iOS, Unknown (order causes Android→Linux and iPhone→macOS mis-detection). `traffic_source` from the referrer host: direct, facebook (`facebook`/`fb.com`), twitter (`twitter`/`x.com`), instagram, linkedin, tiktok, youtube, whatsapp, telegram, snapchat, google, bing, yahoo, duckduckgo, internal (same host), else referral. Geo from `https://ipapi.co/json/` (`ip, country_name, country_code, city`; default `unknown/Unknown/XX/Unknown`), cached in sessionStorage `visitor_geo_cache` for 30 min.
  - *Rebuild:* do all of this server-side in a `TrackVisit` middleware (jenssegers/agent or `whichbrowser`, MaxMind GeoLite2 local DB) — no third-party call from the browser; fix the Android/iOS detection order.
- **F-PUB-091 · Visitor tracking — writes and heartbeat** — Presence row (`active_visitors`): insert `{session_id, ip_address, country, country_code, city, current_page, referrer (null if empty), traffic_source, device_type, browser, os, is_new_visitor, user_agent, last_seen_at}`; on duplicate session update `{current_page, last_seen_at}`. Page view (`page_views`): insert `{session_id, page_path, country, country_code, city, device_type, browser, os, traffic_source, is_new_visitor, ip_address}` once per distinct path. Heartbeat every **5 min** and on back/forward navigation (in-app navigations are only recorded at the next tick — bug). Excluded path prefixes (lower-cased): `/admin`, `/admin-login`, `/qr-scanner`, `/live-visitors`, `/live-bookings`, `/admin-pos`; `/staff*`, `/payment*`, `/invoice/*`, `/checkout`, `/confirmation` ARE tracked. Presence rows older than 5 min are considered gone.
  - *Rebuild:* middleware records a page view per GET of a public page and upserts presence (by session id) with a 60 s throttle per session; a tiny `navigator.sendBeacon('/v', …)` heartbeat every 60 s keeps presence alive; scheduled prune every minute (5 min staleness); exclude `/staff*` and admin areas.
- **F-PUB-092 · Developer error panel (dev builds only)** — Floating panel capturing window errors, unhandled rejections and console errors (latest 25). Not shipped to production.
  - *Rebuild:* not needed (Laravel debug page / Sentry).

#### Internationalisation and browser storage

- **F-PUB-093 · Languages** — Arabic only in the original (`lng: ar`, fallback `ar`, no switcher, no English resource); a missing key renders the key name (bugs: `phone`, `bookingConfirmed`).
  - *Rebuild:* `lang/ar/*.php` (default) and `lang/en/*.php`; `SetLocale` middleware from `?lang=` → session → `Accept-Language`, default `ar`; language toggle `العربية | English` in the public header; `dir` follows the locale; tests assert no missing keys.
- **F-PUB-094 · Translation key groups (carry over verbatim)** — Groups and representative keys: navigation/auth (`logout تسجيل الخروج`, `login تسجيل الدخول`, `backToHome العودة للرئيسية`); orders (`reference الرقم المرجعي`, `amount المبلغ`, `status الحالة`, `paymentMethod طريقة الدفع`, `confirmed مؤكد`, `pending قيد الانتظار`, `failed فشل`); events (`eventTitle عنوان الفعالية`, `eventDate تاريخ الفعالية`, `eventLocation موقع الفعالية`); tickets (`vipAccess تذكرة دخول VIP`, `generalAdmission تذكرة دخول عادية`, `parking تذكرة موقف السيارات`, `price السعر`, `available متاح`, `sold مُباع`, `quantity الكمية`); messages (`loading جاري التحميل...`, `savedSuccessfully تم الحفظ بنجاح`); live bookings (`liveBookings الحجوزات المباشرة`, `present حاضر`, `absent غائب`); common (`search بحث`, `export تصدير`, `print طباعة`, `qar ريال قطري`); home, ticket selection, checkout, confirmation and payment-result groups — every value quoted in F-PUB-014…083. Unused/retired keys: student ticket keys, banner keys, `readyToJoin`, `secureYourSpot`, `selectYourTickets`, `fillAllFields`, `fillTicketHolderInfo`.
  - *Rebuild:* port all live keys; move every hardcoded Arabic literal (home time labels, pagination, ticket chips, countries, terms, holder labels, phone placeholders, all toasts, invoice/viewer/404/footer/WhatsApp labels) into lang files; replace the English toasts listed in F-PUB-033/053/055/061.
- **F-PUB-095 · Currency and number display** — Pages show `{amount with 2 decimals} ريال قطري`; invoice card/PDF use `ر.ق`. Dates in Arabic with Latin digits (`ar-u-nu-latn`) in `Asia/Qatar`.
- **F-PUB-096 · Browser storage keys (original)** —
  | key | store | purpose / TTL |
  |---|---|---|
  | `cached_events` | local | events list, 2 min |
  | `cached_settings` | local | branding, 5 min |
  | `clarity_config_cache` | local | Clarity config, 10 min |
  | `ticketSelection` | local | cart lines `[{ticketId,eventId,type,quantity,price}]`, cleared after order / on confirmed payment |
  | `selectedEventId` | local | event of the cart; cleared after cash order only |
  | `orderRefs` | local | last 10 booking references |
  | `orderIds` | local | legacy order ids, read and removed |
  | `is_returning_visitor` | local | `"true"` |
  | `app-cleanup-popups-v1` | local | one-time cleanup guard |
  | `visitor_session_id` | session | tab id |
  | `visitor_geo_cache` | session | 30 min |
  | `__lovable_chunk_reload_ts__` | session | reload guard 5 min |
  | `sb-*-auth-token` | local | staff login session |
  - *Rebuild:* cart, remembered references and visitor session move to the Laravel session (cookie); browser storage only for UI conveniences.
- **F-PUB-097 · Remembered bookings** — The browser keeps the last **10** booking references (de-duplicated, re-adding moves to the end, corrupt JSON → empty). They are the visitor's capability to reopen results/invoices.
  - *Rebuild:* session array `bookings` (max 10) + "My bookings" link in the header when non-empty (NEW, see upgrades).

**Upgrades (new, not in original) — PUB**
- NEW: English locale with header toggle and LTR layout (F-PUB-093).
- NEW: "My bookings" drawer listing the session's remembered bookings with links to result/invoice pages.
- NEW: Server-generated PDF and PNG invoices; "Add to Apple Wallet / Google Wallet" pass per ticket (optional).
- NEW: Booking lookup page `/booking` (reference + phone last 4 digits) to re-open an invoice from another device, rate-limited.
- NEW: Optional settings-driven announcement banner on the home page (replacing the retired pop-up) with start/end dates.
- NEW: Cookie-consent gate for Clarity; server-side visitor analytics.
- NEW: Show remaining-time countdown for the 30-minute payment hold on the payment result page when status is pending.

**Original defects to avoid — PUB:**
- Name input trims on each keystroke, so multi-word names cannot be typed.
- Missing i18n keys render as `phone *` (extra holder label) and `bookingConfirmed` (confirmation heading); English toasts in an Arabic UI.
- Submit button outside the `<form>`: HTML5 validation never runs and pressing Enter submits while bypassing the terms gate.
- Changing a quantity on checkout wipes all typed holder data; checkout stepper has no parking or availability cap.
- Extra holders' country code is forced by nationality; phone placeholders promise formats that are never validated; Persian digits not converted.
- Holder phones pre-filled with only a country code pass the "required" check.
- Confirmation shows only the latest of up to 10 remembered bookings and shows cancelled orders as "pending" with raw English status/type.
- `paymentPendingDesc` promises e-mail while every other text promises WhatsApp.
- Booking cut-off (18:00 on event day) enforced only in the browser.
- Unfiltered realtime subscriptions on `ticket_holders` from public pages; anonymous inserts into `payment_errors`, `page_views`, `active_visitors`.
- In-app navigations not recorded by visitor tracking (only on a 5-minute tick); browser calls a third-party geo API; Android/iPhone OS mis-detection.
- Clarity loads on `/invoice/*` and `/confirmation` where personal data is visible.
- `payment_unavailable` message suggests pay-at-venue while that option is hidden.
- Hardcoded brand, season and year on the printed invoice; invoice total printed without 2-decimal formatting.
- Dead code to drop: hidden admin/live links on home, hidden date lines, unused selection input handler, unused `eventDate` fetch, `hand-cursor.png`, legacy `orderIds`, `App.css`.

---

### ORD — Booking & order engine (server rules)

The single, transactional path through which every booking is born (public web, cash-at-venue, POS), plus stock accounting, holds, limits and public order lookups. No UI of its own; used by the public checkout, POS and admin. New app: `App\Domain\Orders\OrderService` (+ `StockService`, `TicketLimitGuard`, `ReferenceGenerator`, `HoldExpiry` job) called by `POST /api/v1/orders` (JSON) and directly by Livewire checkout/POS components. All rules below are pinned by the original DB/edge test suites; port those tests to Pest with the same fixtures (Event A: vip 10 @200, normal 10 @100, parking 10 @10; Event B: normal 10 @50; Event X inactive: normal 10 @50).

#### Order creation contract

- **F-ORD-001 · Order creation entry point** — One operation `createOrder(customer, items, holders, payment_method, source='web', pos_user_id?, actor?)`. Original HTTP endpoint: `POST /functions/v1/create-order`, body ≤ **64 000** characters; request `Origin` header captured as `return_origin` only if it is a safe origin (`https://` host with at least one dot and optional port, or `http://localhost[:port]`, no path; `javascript:` etc. → null). Response header `Cache-Control: no-store`.
  - *Rebuild:* `POST /api/v1/orders` (Sanctum optional; throttle `orders` 10/min per IP + 30/hour per phone) and the same service for Livewire.
- **F-ORD-002 · HTTP envelope** — `OPTIONS` → 204 with CORS (`Allow-Origin *`, headers `authorization, x-client-info, apikey, content-type`, methods `POST, GET, OPTIONS`, max-age 86400); non-POST → `405 method_not_allowed`; body too large → `413 payload_too_large`; invalid JSON → `400 invalid_json`; error body always `{success:false, code, error, ...extra}`; unexpected exceptions → `500 internal_error "Something went wrong"` without internals.
  - *Rebuild:* same JSON error shape from a dedicated exception renderer for `/api/*`; restrict CORS to the site origin(s).
- **F-ORD-003 · Input validation rules** — Text cleaning: collapse whitespace runs to one space and trim; reject control characters `[\u0000-\u001f\u007f]` in the raw value.
  - `customer` object required: `name` 2–100 chars; `email` optional, if non-empty ≤ 254 and `^[^\s@]+@[^\s@]+\.[^\s@]{2,}$` (stored `''` when absent); `phone` only `[+\d\s\-()]` with **6–15 digits**; `country_code` optional `^\+\d{1,4}$`, default `+974` (invalid → `+974` plus error); `nationality` 1–60 chars; `id_number` required `^[\p{L}\p{N}\-_/ ]{3,30}$`.
  - `items`: non-empty array, at most **6** entries (only the first 6 processed); `ticket_id` a UUID v1–v8 (lowercased); no duplicate ticket ids; `quantity` integer **1–20** (rejects 0, −1, 1.5, 21, "2", null); Σ quantity ≤ **30**.
  - `holders`: array (first 30 processed); each `ticket_id` UUID; `country_code` optional `^\+\d{1,4}$` (stored `''` when absent/invalid); `name` 2–100; `phone` as customer; `nationality` ≤ 60; `id_number` as customer.
  - Cross-check (only when items valid and no errors so far): holders count = Σ quantities (`Each ticket needs exactly one holder`); per item, holders with that ticket id = its quantity (`Ticket holders do not match the selected tickets`).
  - `payment_method` ∈ {`sadad`, `cash_pos`}; `source` ∈ {`web`, `pos`} (default `web`); `pos_user_id` optional UUID.
  - English messages (original): `Customer details are required`, `<label> is required`, `<label> is too long`, `<label> contains invalid characters`, `<label> phone number is invalid`, `<label> ID number is invalid`, `Customer email is invalid`, `Customer country code is invalid`, `At least one ticket is required`, `Too many ticket types in one order`, `Ticket id is invalid`, `Duplicate ticket type in order`, `Quantity must be a whole number between 1 and 20`, `An order may contain at most 30 tickets`, `Unsupported payment method`, `Unsupported order source`, `Cashier id is invalid`.
  - *Rebuild:* `StoreOrderRequest` with the same limits; Arabic + English messages in lang files; add per-country phone rules (F-PUB-045) and a `terms_accepted` boolean (required for web).
- **F-ORD-004 · Validation error aggregation and ignored fields** — All validation errors are returned at once (de-duplicated): `400 {success:false, code:"validation_failed", error:"The order details are invalid", errors:[…]}`. Client-supplied `total_amount`, `price`, `payment_status` and event id are never read.
- **F-ORD-005 · Order source and POS authorisation** — `source = pos` requires staff identity (admin, moderator, or valid team passcode `staff_passcode`), else `401 unauthorized "Staff only"`; adding `source:"pos"` to a public request gives 401. POS orders must be `cash_pos` (`400 "Point-of-sale orders are cash orders"`). `actor` = staff user id (null for passcode); `pos_user_id` recorded for POS only (web orders never carry a cashier).
  - *Rebuild:* POS uses its own authenticated route (`role:cashier|admin|moderator` or kiosk token); `actor` always recorded (kiosk device id when passcode).
- **F-ORD-006 · Gateway readiness check** — For `sadad` orders, if Sadad merchant id, secret or website domain is missing → `503 payment_unavailable "Online payment is not available right now"` **before reserving anything**. Cash orders need no gateway.
- **F-ORD-007 · Open unpaid orders cap** — Non-POS: count orders whose customer phone equals the submitted phone string exactly, `payment_status = pending` and (`payment_expires_at` null or in the future) — online and cash. If ≥ **4** → `429 too_many_pending_orders "Please complete or cancel your existing bookings first"`. POS exempt.
  - *Rebuild:* match on normalised E.164 phone (the original's exact-string match is bypassed by formatting variations); count inside the order transaction.

#### Transactional creation algorithm

- **F-ORD-008 · Transaction, locking and lazy expiry** — Everything below runs in one DB transaction. First resolve the event, then lock **all ticket rows of that event** `ORDER BY id FOR UPDATE` (serialises sales per event and avoids deadlocks), then expire this event's stale holds (F-ORD-034) — scoped to the event to avoid cross-event lock cycles.
  - *Rebuild:* `DB::transaction(fn…, attempts: 3)` with `Ticket::where('event_id')->orderBy('id')->lockForUpdate()->get()`; InnoDB; retry on deadlock.
- **F-ORD-009 · Ticket resolution** — Join items to `tickets`; any unknown ticket id, or tickets from more than one event → `invalid_tickets` (422). One order = one event.
- **F-ORD-010 · Event open for sale** — Web: event must have `is_active = true` else `event_not_available` (409). POS may sell inactive events.
  - *Rebuild:* web also refuses archived events and events past the 18:00 cut-off (F-PUB-016); POS keeps its exemption but logs it.
- **F-ORD-011 · Stock check per line** — For each item ordered by ticket id: if `available_quantity − sold_quantity < quantity` → `insufficient_stock:<type>` (409, `ticket_type` in the body). Applies to all channels (POS 11 normal when 10 exist → refused).
- **F-ORD-012 · Per-order caps (web)** — Web only: admission (vip + normal) ≤ **5** and parking ≤ **5** per order, else `quantity_limit_exceeded` (422). Test cases: 3 vip + 3 normal refused; 6 parking refused.
- **F-ORD-013 · Online minimum amount** — If `payment_method = sadad` and total < **3 QAR** → `below_minimum_amount` (422). The same 2 QAR order as cash is accepted.
- **F-ORD-014 · Holder ↔ ticket matching** — Holders count must equal Σ quantities and, per item, the number of holders with that `ticket_id` must equal its quantity, else `holders_mismatch` (422). Missing customer name/phone, empty items, null ids/quantities, duplicate ids or blank holder name/phone → `invalid_request` (400). Unknown source → `invalid_source`.
- **F-ORD-015 · Pricing and totals** — Prices always from `tickets.price` (decimal 10,2). `total_amount = Σ price × quantity`; `orders.quantity` = total tickets; `orders.ticket_type` (label type) = type of the **most expensive** ticket in the order (`price DESC, ticket id` tie-break). Test: 1 vip (200) + 2 normal (100) = **400**, label `vip`.
  - *Rebuild:* store money as integer dirhams or DECIMAL(10,2); also persist order lines (`order_items`: ticket_id, type, unit_price, quantity) so re-payment and refunds use frozen prices (the original rebuilds lines from current prices).
- **F-ORD-016 · Status and hold assignment** — POS → `confirmed` with `confirmed_by = actor`, `confirmed_at = paid_at = now()`, `pos_user_id`, `n8n_response_message = 'طلب من نقطة البيع - POS'`, `n8n_responded_at = now()`. Web → `pending`; web + sadad → `payment_expires_at = now() + 30 minutes` (`c_hold_minutes = 30`); web + cash_pos → no expiry.
- **F-ORD-017 · Booking reference generation** — `{PREFIX}-{D}-{M}-{YYYY}-{12 uppercase hex}`: prefix `QTR` (web) or `POS` (POS); D-M-YYYY is the **event date** in Asia/Qatar, **not zero-padded** (`QTR-3-9-2026-…`, `QTR-25-12-2026-1A2B3C4D5E6F`); the 12 hex chars are the first 12 of a random UUID without dashes (48 bits). Test regex `^QTR-[0-9]{1,2}-[0-9]{1,2}-[0-9]{4}-[0-9A-F]{12}$` (POS `^POS-…$`). Unique constraint; on collision retry up to **5** times, then fail.
  - *Rebuild:* `ReferenceGenerator` with `random_bytes(6)` → hex upper; keep format exactly (printed tickets, scanners and n8n flows depend on it).
- **F-ORD-018 · Customer record** — A new `customers` row per order (never de-duplicated): `name` trimmed, `email` trimmed or `''`, `phone` trimmed, `nationality` null if blank, `id_number` null if blank, `country_code` default `+974`.
  - *Rebuild:* store `phone_e164` plus display fields; optional `people` table keyed by (id digits | phone last 8) for limits/history while keeping the per-order customer snapshot.
- **F-ORD-019 · Holder records** — All holders inserted in input order, numbered 1..n: `name`, `phone` trimmed (a typed `+code` prefix stays in the text); `country_code` = first non-empty of holder's code, a `^\s*(\+\d{1,4})\s` capture from the holder phone, the customer's code, `+974`; `nationality` = trimmed or `''`; `ticket_type` taken from the ticket row (never from the client); `qr_code = {reference}-TKT{NN}` (F-QR-001); `id_number` null if blank. Test: explicit `+966` kept; phone `+971 55510002` without code → `+971`.
  - *Rebuild:* store `ticket_id` FK and an enum `ticket_type` on holders; store `phone_e164`.
- **F-ORD-020 · Per-person ticket limit (5)** — Before each holder insert (all channels: web, cash, POS, restore-from-bin): skip parking; identity key = ID digits if ≥ 6 digits, else last 8 phone digits if ≥ 8 digits; no key → skip. Take a per-(person, event) lock, count existing normal+vip holders for that person and event in orders that are `confirmed` or `pending` with unexpired/null expiry (matching by ID when an ID key exists — phone then ignored — else by phone). If existing ≥ **5** → error `TICKET_LIMIT_EXCEEDED: الحد الأقصى هو 5 تذاكر (عادي + VIP) لكل شخص. {name} لديه {n} تذكرة بالفعل` → `422 ticket_limit_exceeded` with that Arabic message. Rows inserted earlier in the same order count (3 + 3 refused, 3 + 2 allowed). The cap is per event ("season"), not lifetime; people already over keep their tickets.
  - *Rebuild:* `TicketLimitGuard` inside the order transaction using `Cache::lock("person:{key}:event:{id}")` or a `SELECT … FOR UPDATE` on a `person_event_counters` row; limit (5) and excluded types configurable per event.
- **F-ORD-021 · Amount guards** — Safety checks run at insert: order `quantity > 0` (`INVALID_QUANTITY: عدد التذاكر غير صالح`); order total ≥ min ticket price of the event × quantity − 0.01 (`AMOUNT_MISMATCH: المبلغ غير صحيح. الحد الأدنى المطلوب % ريال`); at commit, Σ holders' ticket prices ≤ order total + 0.01 (`AMOUNT_MISMATCH: المبلغ غير صحيح. قيمة التذاكر % ريال`).
  - *Rebuild:* assertions inside `OrderService` (totals computed server-side make them invariants) plus a DB CHECK `total_amount >= 0`.
- **F-ORD-022 · Error → HTTP mapping** — `insufficient_stock:<type>` 409 (+`ticket_type`), `quantity_limit_exceeded` 422, `below_minimum_amount` 422, `event_not_available` 409, `invalid_tickets` 422, `holders_mismatch` 422, `invalid_request` 400, `forbidden` 403, `TICKET_LIMIT_EXCEEDED: <text>` → 422 `ticket_limit_exceeded` (message = text), anything else 500 `internal_error`. (Plain `insufficient_stock` without type is unmapped → 500 — fix.)
- **F-ORD-023 · Atomic rollback** — Any error leaves no customer, order, holder or stock change (test-pinned, including the refused per-person order holding no seats).
- **F-ORD-024 · Post-create: QR pictures** — For every created holder, render and store its QR PNG (F-QR-005) before responding; a failure is logged only (the ticket still scans by its code).
  - *Rebuild:* dispatch `GenerateTicketQr` jobs after commit; invoice/QR views render on the fly if the picture is not ready.
- **F-ORD-025 · Post-create: online payment request** — For `sadad`: build a signed Sadad Web Checkout 2.1 request (payments module) with `ORDER_ID = booking_reference`, amount = order total, product lines per item with names `تذكرة VIP` / `تذكرة دخول عامة` / `تصريح مواقف`, mobile = customer phone with country code digits, email = customer e-mail or `noreply@{website domain}`. If building fails: mark the order payment-failed with note `checkout_request_failed` (seats released) and return `502 payment_unavailable`.
- **F-ORD-026 · Post-create: notifications** — Web + `cash_pos` + WhatsApp automation configured → background POST of the "order created" payload to n8n (`{customer{…, phone international}, order{id, booking_reference, total_amount, payment_method, payment_status, event_id}, ticketHolders[{…, phone international, qr_code, qr_code_image}], bookingReference, adminPhone, timestamp}`). Sadad orders notify only when paid; POS orders call the admin sale alert in the background (failure never fails the sale).
  - *Rebuild:* `OrderCreated` event → queued listeners (n8n driver, admin alert mail); idempotent.
- **F-ORD-027 · Response shape** — `200 {success:true, order:{id, booking_reference, total_amount, payment_method, payment_status, payment_expires_at}, payment?:{url:"https://sadadqa.com/webpurchase", fields:{merchant_id, ORDER_ID, TXN_AMOUNT "nnn.nn", WEBSITE, CALLBACK_URL, MOBILE_NO, EMAIL, txnDate "YYYY-MM-DD" (Qatar date), signature, productdetail[i][order_id|itemname|amount|quantity]}}}`. The secret never appears. Internally the creation procedure returns also `event_id`, `items[{ticket_id,type,price,quantity}]` (price desc) and `holders[{id,qr_code,ticket_type,name}]` (by code).

#### Stock engine and availability

- **F-ORD-028 · Stock invariant** — `tickets.available_quantity` is total capacity; `tickets.sold_quantity` = seats held by `pending` + `confirmed` orders (per holder; legacy holder-less orders count `orders.quantity` of `orders.ticket_type`). Remaining = capacity − sold. Seats are taken at holder insert (+1 each; refused with `insufficient_stock:<type>` when none left).
  - *Rebuild:* DB CHECK `sold_quantity >= 0`; application check `sold ≤ capacity`; capacity edits below `sold` refused in admin.
- **F-ORD-029 · Stock follows order status** — On status change: active (pending/confirmed) → inactive (cancelled): release the order's seats (never below 0). Inactive → active: re-reserve all seats or fail with `insufficient_stock` (whole update refused). Pending ↔ confirmed: no change. Test-pinned: admin cancel releases; reinstating to pending re-reserves; reinstating when sold out raises.
- **F-ORD-030 · Stock on holder delete and order delete** — Deleting a holder of an active order releases 1 seat; deleting an active order releases its seats once; deleting an already-cancelled order releases nothing (no double release).
  - *Rebuild:* no hard deletes of orders (soft delete + cancel); holder removal only through the recycle bin (F-ORD-042).
- **F-ORD-031 · Stock follows event move** — Changing an order's event (admin "change event"): lock both events' ticket rows in a fixed order (event id, ticket id), release seats on the old event, reserve on the new one or refuse the whole move with `insufficient_stock` (`لا توجد تذاكر كافية في الفعالية الجديدة`). Moving back restores counts.
- **F-ORD-032 · Availability check (advisory)** — Public read-only check per (event, type, quantity), misleadingly named `reserve_tickets` (reserves nothing): no ticket row → `{success:false, error:"TICKET_NOT_FOUND", message:"نوع التذكرة غير موجود لهذه الفعالية"}`; remaining < quantity → `{success:false, error:"INSUFFICIENT_CAPACITY", message: remaining ≤ 0 ? "عذراً، نفدت التذاكر من هذا النوع حالياً" : "عذراً، لا تتوفر تذاكر كافية. المتاح: {n} تذكرة", available, requested}`; else `{success:true, available, after_reservation}`.
  - *Rebuild:* name it `AvailabilityService::check()`; `GET /api/v1/events/{id}/availability`.
- **F-ORD-033 · Held counts per type** — Public count of holders per type for an event in orders that are `confirmed` or `pending` with null or future `payment_expires_at` (an expired-but-not-yet-closed hold stops counting immediately). Holder-less legacy orders not counted.
- **F-ORD-034 · 30-minute payment hold and expiry** — Pending **sadad** orders whose `payment_expires_at < now()` become `cancelled` with `payment_note = 'expired'` (seats released), processed with skip-locked row locks, idempotent; returns the count. Cash and confirmed orders never expire. Triggered lazily: at the start of every order creation for the same event (an expired order of event A is not closed by a sale of event B), and by public pages before showing availability (`release_expired_holds(event)`, callable anonymously). An expired order can still be confirmed later by a verified late payment (re-reserve; else `paid_after_expiry_no_stock` — payments module).
  - *Rebuild:* scheduled command `orders:expire-holds` every minute (all events) plus the lazy per-event call; hold minutes as a setting (default 30).
- **F-ORD-035 · Person ticket count lookup** — Public read `(id_number, phone, event) → integer` with the same keying and counting as F-ORD-020 (both keys missing → 0). Used by checkout and POS pre-checks.
  - *Rebuild:* restrict to authenticated checkout sessions/staff and rate-limit; return only the number.
- **F-ORD-036 · Cash-at-venue orders** — Web `cash_pos` orders are `pending`, have **no expiry**, hold seats until staff confirm or cancel, count toward the per-person cap and the 4-open-orders cap, trigger the n8n "order created" notification, and are admitted at the gate as if paid (scanner rule).
  - *Rebuild:* configurable expiry for unpaid cash bookings (default: event start, or N hours) and an explicit "collect payment" step at the gate.

#### Order lifecycle and lookups

- **F-ORD-037 · Public order status lookup** — Input: up to **10** references (others ignored), each matching `^[A-Za-z0-9][A-Za-z0-9_-]{0,59}$`. Output array ordered by `created_at`: `{booking_reference, payment_status, payment_method, total_amount, quantity, ticket_type, created_at, payment_expires_at, event_id, event:{title, event_date, location}, tickets:[{ticket_type, name, qr_code, qr_image_url}] (by code)}`. Status mapping: `cancelled` with note in (`payment_failed`, `expired`, `paid_after_expiry_no_stock`) → `failed`; other cancellations stay `cancelled`. Never returns customer id, ID numbers or `payment_note`. Unknown → `[]`.
  - *Rebuild:* `GET /api/v1/orders/status?refs[]=` throttled 60/min/IP.
- **F-ORD-038 · Public order (invoice) lookup** — By booking reference (and, in the original, also by order UUID) → `{id, booking_reference, ticket_type, quantity, total_amount, payment_method, payment_status (raw), payment_id, payment_error_reason, confirmed_at, created_at, customers{name, email, phone, country_code, nationality}, events{title, event_date, location}, ticket_holders[{id, name, phone, country_code, nationality, ticket_type, qr_code, qr_image_url, is_present, confirmed_at, confirmed_by_name}] (by created_at)}`; holder `id_number` included **only for admins**. Unknown → null.
  - *Rebuild:* drop lookup by UUID; mask e-mail/phones on the public invoice (e.g. `+974 •••• 2345`) and show full values only to staff; rate-limit.
- **F-ORD-039 · Order states and notes** — Stored `payment_status` ∈ `pending | confirmed | cancelled`; `payment_note` explains a cancellation: `payment_failed`, `expired`, `paid_after_expiry_no_stock`, `admin_cancelled`, `checkout_request_failed`, or a free staff note. Transitions: create (web) → pending; pending → confirmed (verified payment); pending → cancelled/expired; pending → cancelled/payment_failed; cancelled(expired|failed) → confirmed (late payment, seats re-reserved) or stays cancelled with `paid_after_expiry_no_stock` (refund needed); pending|confirmed → cancelled/admin note. Timestamps: `paid_at`, `confirmed_at`, `payment_expires_at`, `last_payment_check_at`.
  - *Rebuild:* `OrderStatus` enum + `PaymentNote` enum + spatie/laravel-model-states (or a small state class) with transitions logged to activity log; NOT NULL `payment_status`.
- **F-ORD-040 · Admin cancel ("return to sale")** — Admin-only: lock; not found → `order_not_found`; status not pending/confirmed → `already_inactive`; else `cancelled` with note = given note or `admin_cancelled` → `cancelled`; seats released. UI label `إلغاء وإعادة للبيع`.
- **F-ORD-041 · Recount stock** — Admin-only repair: recompute every `sold_quantity` from live orders (holders of pending+confirmed orders, legacy fallback), update only differing rows, return the number changed. UI `إعادة حساب المخزون`.
  - *Rebuild:* also a nightly `stock:verify` command that reports drift to admins.
- **F-ORD-042 · Ticket recycle bin (engine)** — Admin "bin" copies a holder with context `{booking_reference, payment_status, payment_method, event_title, event_date, customer_name}`, who/when (`deleted_by`, admin e-mail), then deletes the holder (seat released) → `تم نقل التذكرة إلى سلة المحذوفات`. Restore re-inserts with the **same id** (limit and stock re-checked; sold out → `insufficient_stock`) → `تم استرجاع التذكرة`; errors `هذه العملية متاحة للأدمن فقط`, `التذكرة غير موجودة`, `العنصر غير موجود في السلة`, `تعذر الاسترجاع — الحجز الأصلي لم يعد موجوداً`.
  - *Rebuild:* soft-delete holders (`deleted_at`, `deleted_by`) instead of copying; keep `qr_image_url` (original loses it).
- **F-ORD-043 · Person event history** — `(id_number, phone) → [{event_id, title, event_date, ticket_count}]`: confirmed holders matching ID digits (≥ 6) or phone last 8 (≥ 8), grouped by event, newest first, max 50. Used by POS customer lookup ("previous attendance").
  - *Rebuild:* staff-only (original is callable anonymously).
- **F-ORD-044 · Lifetime ticket totals** — Admin-only: confirmed holders of all events (incl. archived) counted per key `id:{digits}` and `ph:{last 8}`; a person's total = max over their keys. Used for the lifetime badge on live bookings.
- **F-ORD-045 · Legacy data and references** — Lookups must accept any reference matching `^[A-Za-z0-9][A-Za-z0-9_-]{0,59}$` (legacy `QTR-XXXXXXXX` 8 hex, client-generated references, POS references). Migration rules: close sadad pending orders older than 30 min as `cancelled/expired`, set `payment_expires_at = created_at + 30 min` on fresh ones, recount stock, move URLs found in holder codes to `qr_image_url` (F-QR-006).

**Upgrades (new, not in original) — ORD**
- NEW: `order_items` table with frozen unit prices; reuse for re-payment and refunds.
- NEW: Idempotency key header on `POST /api/v1/orders` (double-click / retry safe).
- NEW: Scheduled hold expiry and configurable expiry for cash-at-venue bookings.
- NEW: Per-event configurable limits (per order, per person, minimum online amount, hold minutes).
- NEW: Server-side cut-off enforcement for web sales.

**Original defects to avoid — ORD:**
- Open-orders cap matches the raw phone string; formatting variations bypass it.
- Web cash-at-venue orders never expire, block stock and count toward caps forever.
- QR rendering runs synchronously before the response (latency); a new customer row per order with no person identity.
- `reserve_tickets` is a read-only check despite its name.
- Plain `insufficient_stock` (from status re-activation) maps to 500.
- Holder `ticket_type` is free text without FK to the ticket row; no CHECK `sold ≤ capacity`; nullable `payment_status`.
- Destructive cascades (event delete removes orders; customer delete removes orders).
- Public order lookup by UUID and anonymous person-history lookup leak personal data; 48-bit references are not rate-limited.
- Re-payment rebuilds product lines from current prices instead of frozen order lines.
- POS orders by passcode staff have no actor.

---

### QR — Ticket & QR codes

Every ticket (holder) has a unique text code that is encoded in its QR picture and scanned at the gate. Covers the code format, picture generation and storage, maintenance tools and the staff ticket viewer/printer. New app: `App\Domain\Tickets\TicketCode`, `QrRenderer` (endroid/qr-code), queued jobs, and a Filament custom page "Ticket viewer" reachable at the legacy URL `/admin/tickets?ref=`.

- **F-QR-001 · Ticket code format** — `{booking_reference}-TKT{NN}` where NN is the holder's 1-based position in the order, zero-padded to 2 digits (`-TKT01`, `-TKT02`, …), e.g. `QTR-25-12-2026-ABCDEF123456-TKT01`, `POS-0123456789AB-TKT12`. Stored in `ticket_holders.qr_code`, **unique**. The QR picture encodes exactly this text (round-trip test). Legacy codes after an event change look like `QR-{epoch ms}-{16 hex}` (F-QR-012).
- **F-QR-002 · Numbering beyond 99** — The original pads with `lpad(n, 2)`, which truncates at 100+ (`100` → `10`) and would collide; safe only because of the 30-ticket request cap.
  - *Rebuild:* `str_pad($n, 2, '0', STR_PAD_LEFT)` without truncation (TKT100 allowed).
- **F-QR-003 · QR picture specification** — PNG, **800 px**, margin (quiet zone) **2** modules, error correction **M**, black on white. Fallback when the local library fails: `https://api.qrserver.com/v1/create-qr-code/?size=800x800&data={urlencoded}&format=png`; both fail → error "QR generation failed".
  - *Rebuild:* endroid/qr-code only (no third-party fallback; codes are capabilities); SVG for on-screen use, PNG for files/WhatsApp.
- **F-QR-004 · Storage and safe object names** — Public bucket `qr-codes`, object `{code}.png`, `image/png`, upsert; public URL saved in `ticket_holders.qr_image_url`. Object name accepted only if the code matches `^[A-Za-z0-9][A-Za-z0-9_-]{0,79}$` (rejects `../secret`, `a/b`, `a b`, empty, 200 chars, `.hidden`, `a.png`, non-ASCII). Writes by the server (and admins).
  - *Rebuild:* `Storage::disk('qr')` (public, prefix `qr/`), path `qr/{event_id}/{code}.png`; optionally signed URLs; same name validation.
- **F-QR-005 · Generation at order creation** — For each new holder: render, upload, set `qr_image_url`. Failure never fails the order; the scannable code is never modified.
- **F-QR-006 · Code vs picture separation (legacy repair)** — Since the 2026-10-09 migration `qr_code` always holds the text code; `qr_image_url` holds the picture URL. Older rows that stored a URL in `qr_code` were repaired: code = file name without path, `.png` and query string; URL moved to `qr_image_url`; after migration no `qr_code` matches `^https?://`. Even older holders without a code received `{reference}-TKT{NN}` numbered by `created_at`.
  - *Rebuild:* run the same repair in the data import and assert it in a test.
- **F-QR-007 · On-screen QR rendering** — Invoice card renders codes client-side at 512 px, margin 1 (a value starting with `http` is shown as an image); PDF print uses the same; the staff viewer renders 400 px, margin 2, error correction **H**.
  - *Rebuild:* one server renderer (SVG inline) with size presets: card 112 px, enlarged 288 px, print 300 px, viewer 192 px / 500 px.
- **F-QR-008 · Scanned code normalisation** — Scanner input is trimmed; if it is an `http(s)://` URL, strip query/hash, keep the last path segment and drop a `.png` extension → ticket code. (Old printed tickets may carry the picture URL.)
- **F-QR-009 · Backfill missing pictures (admin)** — Lists up to **100** holders with a code but no `qr_image_url`, renders them sequentially, collects per-ticket errors `"{code}: {message}"`; response `{success, updated, skipped:0, errors?, message:"Created {n} QR images"}` + ` — run it again to continue` when exactly 100 were listed.
  - *Rebuild:* queued batch job with progress in Filament; mark permanent failures (invalid names) so they are not re-listed forever.
- **F-QR-010 · Regenerate pictures of one booking (admin)** — Input booking reference (regex-validated, else `400 invalid_reference`); unknown → `404 order_not_found`; re-renders every holder's picture (by code order) → `{success, updated, total, errors?, message:"Regenerated {n} QR images for booking {ref}"}`. Codes do not change.
- **F-QR-011 · Generate an arbitrary QR picture (admin tool)** — `{text (1–500 chars), filename (safe name, ".png" appended)}` → `{url}`; overwrites if present; invalid → `400 invalid_request "A QR text and a safe file name are required"`.
  - *Rebuild:* keep admin-only; forbid overwriting objects that belong to existing ticket codes.
- **F-QR-012 · Codes after moving a booking to another event** — Admin event change records every old holder code (and the legacy order-level code) in `expired_qr_codes {qr_code, order_id, reason:'event_date_changed'}`, issues new codes `QR-{epoch ms}-{first 16 hex of a UUID}` uppercased, clears and re-renders pictures, logs activity `event_change`.
  - *Rebuild:* new codes keep the `{reference}-TKT{NN}` scheme with a new reference suffix or a version suffix (`-TKT01-V2`); the scanner must consult the expired registry and show "expired code — moved to {event}" (the original never checks it); do it in one transaction and auto-resend tickets (NEW).

#### Staff ticket viewer (`/admin/tickets?ref={reference}`)

- **F-QR-013 · Viewer access** — Requires staff access (account or team passcode). Missing `ref` → toast `رقم الحجز مفقود` and go to `/admin/dashboard`. Admin status (admin_users row or `admin` role) unlocks the check-in reset button.
  - *Rebuild:* Filament page `TicketViewer` with policy `viewTickets` (admin, moderator, cashier, gatekeeper); `resetCheckin` ability admin only; legacy URL redirects to the page.
- **F-QR-014 · Viewer data** — Public order lookup by reference (F-ORD-038) with admin-only `id_number`; branding (logo, header colour). QR images rendered for each holder. If no order → toast `فشل تحميل التذاكر`.
- **F-QR-015 · Viewer header actions** — Sticky header (`header_bg_color`): logo → `/admin/dashboard` (or title `عرض وطباعة التذاكر`); yellow button `رجوع` (arrow) → `/admin/dashboard`; outline button `إعادة إنشاء جميع رموز QR` → F-QR-010 → toast `تم إعادة إنشاء {n} رمز QR بنجاح` then reload, error `فشل إعادة إنشاء رموز QR`.
- **F-QR-016 · Legacy-ticket notice** — Amber card: `⚠️ تنبيه هام` and `إذا كانت التذاكر المطبوعة القديمة تحتوي فقط على الرقم المرجعي للحجز (مثل: QTR-AVB5MGKO)، يجب **إعادة تحميل وطباعة التذاكر الجديدة** التي تحتوي على رموز QR فردية لكل تذكرة (مثل: QTR-AVB5MGKO-TKT01).` + `كل تذكرة يجب أن يكون لها رمز QR فريد خاص بها لتسجيل الحضور بشكل صحيح.`
  - *Rebuild:* show only when the booking reference is a legacy format.
- **F-QR-017 · Booking summary and print all** — Card `تفاصيل الحجز`: `الرقم المرجعي` (mono, bold, primary), `اسم العميل`, `رقم الهاتف` (whitespace removed), `اسم الفعالية`, `عدد التذاكر: {n} تذكرة`; full-width button `طباعة جميع التذاكر ({n})` opens one print window per holder at 500 ms intervals.
  - *Rebuild:* one multi-page print/PDF (one ticket per A5/A6 page) instead of n pop-ups.
- **F-QR-018 · Ticket cards grid** — Grid 1/2/3 columns (`md`/`lg`). Per holder: header with the code in mono (if the stored code contains `http`, show the last path segment without `.png`/`.jpg`) and, when checked in, badge `حاضر ✓` (green); QR 192 px with primary border (click → enlarge) or a spinner; rows `الاسم:`, `الجنسية:`, `النوع:` (type uppercase); buttons `عرض وطباعة` (loading `جاري الإنشاء...`) and `إرسال للواتساب` (loading `جاري الإرسال...`).
- **F-QR-019 · Undo check-in (admin only)** — Small destructive outline button `إلغاء` (title `إلغاء المسح (للأدمن فقط)`) on checked-in tickets: confirm `إلغاء مسح تذكرة {name}؟ ستعود التذكرة صالحة من جديد.` → server reset: only an admin account (`401/403 إلغاء المسح متاح للأدمن فقط`); missing id `معرّف التذكرة مطلوب`; conditional update `is_present=false, confirmed_at/by/by_name = null` where currently present; none updated `التذكرة غير ممسوحة أصلاً`; error `تعذر إلغاء المسح`; success `تم إلغاء المسح، التذكرة صالحة من جديد` + activity log `ticket_checkin_reset {holder_id, qr_code, holder_name}`. Client fallbacks `تعذر إلغاء المسح — هذه العملية للأدمن فقط` / `تم إلغاء المسح`.
- **F-QR-020 · Enlarge dialog** — Title = code; QR 500×500 with 4 px primary border on white; holder name (xl bold), phone (mono, LTR), type.
- **F-QR-021 · Printable ticket** — New window, RTL, title `تذكرة {code}`: card max 800 px, 3 px border, radius 16, padding 40, subtle grey-to-white gradient; header `🎫 تذكرة دخول` + event title; QR 300×300 with 4 px border and the code in mono 24 px; detail rows (4 px side border): `اسم حامل التذكرة:`, `رقم الهاتف:`, `الجنسية:`, `رقم الهوية:`, `نوع التذكرة:` (uppercase), `موقع الفعالية:`, `تاريخ الفعالية:` (`ar-QA` date); status box: present → green (`#dcfce7`/`#166534`/`#22c55e`) `✅ تم تسجيل الحضور في: {datetime}`, else amber (`#fef3c7`/`#92400e`/`#fbbf24`) `⏳ لم يتم تسجيل الحضور بعد`; dashed footer `يرجى إحضار هذه التذكرة (مطبوعة أو رقمية) لتسجيل الدخول` and `الرقم المرجعي للحجز: {ref}`; button `طباعة التذكرة 🖨️` hidden when printing. Toasts: popup blocked `يرجى السماح بالنوافذ المنبثقة`; missing QR `فشل إنشاء رمز QR`; ok `تم فتح التذكرة في نافذة جديدة`; error `فشل إنشاء التذكرة`. The accent colour is violet `#8B5CF6` (off-brand) and moderators see `undefined` for the hidden ID.
  - *Rebuild:* Blade print view in brand maroon/gold, Tajawal font, ID row shown only when permitted (never "undefined"), server PDF option.
- **F-QR-022 · Send one ticket to WhatsApp** — If the holder has no picture URL, upload one first (failure → `فشل رفع رمز QR`, abort). Then relay to the n8n WhatsApp automation (server relay; admin) the payload `{booking_reference, event_title, event_date, event_location, holder:{name, phone (prefix "+code " removed), country_code (digits from the phone prefix, else holder code without "+", else "974"), nationality, id_number, ticket_type, ticket_price, qr_code, qr_code_image, is_present}, timestamp}`. Results: success → `تم إرسال التذكرة إلى {phone}`; upstream "not registered" → `الويب هوك غير مفعل في n8n. يرجى تفعيل الـ workflow أولاً`; other error → message or `فشل إرسال البيانات إلى الويب هوك`; transport error `فشل الاتصال بالويب هوك`; unexpected `استجابة غير متوقعة من الويب هوك`; generic `فشل إرسال التذكرة`. The ticket price is looked up by type across **all events** (wrong when several events exist).
  - *Rebuild:* `SendTicketWhatsApp` job through the messaging driver; price from the order line; record the send on the order's automation status.
- **F-QR-023 · Live check-in status** — The viewer subscribes to all holder updates (unfiltered) and merges changes by id so a gate scan flips `حاضر ✓` live.
  - *Rebuild:* private channel `order.{id}.holders`.
- **F-QR-024 · Viewer loading and not-found states** — Loading: 48 px spinner + `جاري تحميل التذاكر...`. Not found (no order or no holders): card `لم يتم العثور على تذاكر` (red), `لا توجد تذاكر للرقم المرجعي: {ref}`, button `العودة للوحة التحكم`. Footer at the bottom.

**Upgrades (new, not in original) — QR**
- NEW: Optional HMAC suffix on QR content (`{code}.{8-char HMAC}`) so codes cannot be forged from a guessed reference; scanner verifies it while still accepting legacy plain codes.
- NEW: Apple/Google Wallet passes generated from the same code.
- NEW: Bulk "print all tickets of an event" PDF for VIP envelopes.

**Original defects to avoid — QR:**
- `lpad(n,2)` truncation above 99 tickets.
- Third-party QR fallback service receives ticket codes.
- Expired-code registry written on event change but never checked by the scanner; event-change codes break the `-TKTnn` scheme; change not transactional.
- Backfill re-lists permanently failing holders forever.
- Ticket viewer: price lookup ignores the event; unfiltered realtime; violet off-brand print ticket; `undefined` ID for moderators; n pop-up windows for "print all"; regenerate hides the whole page while loading.
- Public bucket with predictable names derived from the reference.

---

### UI — Design system & brand

Visual identity shared by the public site, staff apps (POS, scanner, boards) and the Filament admin theme. New app: Tailwind CSS 4 `@theme` tokens in `resources/css/theme.css`, one token source consumed by Blade/Livewire views and the custom Filament theme; RTL-first.

- **F-UI-001 · Typography** — Brand font **Lusail**, substituted by Google **Tajawal** weights 300/400/500/700/800/900 (`display=swap`, preloaded). `font-lusail` = `font-sans` = `['Tajawal', 'sans-serif']`. Monospace (system stack) for booking references, ticket codes and phone numbers (LTR). Print documents: invoice `Tahoma, Cairo, sans-serif` 14 px; ticket `Arial`.
  - *Rebuild:* self-host Lusail (if licensed) with Tajawal fallback via `@font-face` + `font-display: swap`; Latin pairing for English (e.g. Inter); tabular numerals for amounts.
- **F-UI-002 · Theme colour tokens (light)** —
  | token | HSL | ≈ hex | role |
  |---|---|---|---|
  | background | 0 0% 100% | #FFFFFF | theme pages |
  | foreground | 345 100% 15% | #4D0013 | deep maroon text |
  | card / popover | 0 0% 100% | #FFFFFF | |
  | primary | 345 75% 35% | #9C1638 | maroon: buttons, prices, rings |
  | primary-foreground | 0 0% 100% | #FFFFFF | |
  | secondary / accent | 40 95% 55% | #F9B11F | gold: VIP, pending, highlights |
  | secondary/accent-foreground | 345 100% 15% | #4D0013 | |
  | muted | 0 0% 96% | #F5F5F5 | |
  | muted-foreground | 0 0% 45% | #737373 | |
  | destructive | 0 84.2% 60.2% | #EF4444 | errors, sold out, used tickets |
  | success | 145 70% 32% | #188B48 | paid badge |
  | border / input | 0 0% 90% | #E5E5E5 | |
  | ring | 345 75% 35% | #9C1638 | |
  | sidebar | bg 0 0% 98%, primary 345 75% 35%, accent 0 0% 95%, border 90 % | | admin sidebar |
- **F-UI-003 · Dark tokens** — Class-based dark mode (no public toggle): background `345 100% 8%` (#29000A), foreground `0 0% 95%`, card/popover `345 50% 12%`, primary `345 75% 45%` (#C91D48), secondary/accent unchanged gold, muted `345 30% 20%`, destructive `0 62.8% 50%`, success `145 60% 42%`, border/input `345 30% 20%`, ticket-parking `210 90% 60%`, ticket-normal `345 75% 45%`, scanner-maroon `343 82% 40%`, sidebar `345 50% 10%`.
- **F-UI-004 · Gradients and shadows** — `gradient-primary` `linear-gradient(135deg, #9C1638, #D41142)`; `gradient-gold` `linear-gradient(135deg, #F9B11F, #FFD24D)`; `shadow-elegant` `0 10px 40px -10px hsl(345 75% 35% / 0.3)` (maroon glow); `shadow-card` `0 4px 20px hsl(0 0% 0% / 0.08)`. Invoice top bar gradient gold → maroon → gold.
- **F-UI-005 · Ticket-type colours and icons** — vip gold `#F9B11F` + crown; normal maroon `#9C1638` + users; parking blue `#0D80F2` (token) + car (public card uses gold accent for the car icon).
- **F-UI-006 · Scanner dark palette** — `scanner-background #171717`, `surface #212121`, `elevated #2E2E2E`, `foreground #F5F5F5`, `muted #A6A6A6`, `gold #F3B21B`, `maroon #A7113B` — dark maroon/gold gate UI.
- **F-UI-007 · Warm desert palette (public inline colours)** — page `#F5EFE7` (cream); header default `#D4B78A` (sand gold) also event-card border; terracotta `#A85740` (header border, CTA, icons, active pagination, hero overlay rgba(168,87,64,.6/.4)); dark brown `#6B4E3D` (headings, disabled CTA); mid brown `#8B6F47` (secondary values); footer coral `#E77E5A`; cards `#FFFFFF`. Overlays: header rgba(212,183,138,.8→.4), before-footer rgba(245,239,231,.8→.4), footer rgba(231,126,90,.8→.4).
  - *Rebuild:* promote these to named tokens (`sand`, `cream`, `terracotta`, `bark`, `clay`, `coral`) instead of inline hex.
- **F-UI-008 · Status and utility colours** — Terms card red `red-500/600/50`, accepted green `green-600/700`; low stock and delayed notices amber `amber-50/300/600/700/800/900`; back buttons Tailwind `yellow-500/600` with black (public) or white (viewer) text; WhatsApp `#25D366`/`#20BA5A`.
  - *Rebuild:* replace yellow back buttons with brand ghost/outline buttons.
- **F-UI-009 · Print palettes** — Invoice warm browns `#4a3728 #c27444 #a4552b #6b4b2a #d8b59f #e7e2dc #fdfaf8 #f4ede6 #8b4a2a`, paid `#e8f7e6/#2c7a3f`, pending `#fff4e5/#b47400`, footer `#776150`. Printable ticket violet `#8B5CF6` (off-brand; replace).
- **F-UI-010 · Celebration palette (POS fireworks)** — gold rgb(250,204,21), light gold rgb(253,224,71), rose rgb(190,24,93), emerald rgb(16,185,129), white; 6 s canvas effect, disabled under reduced motion (specified in the POS module).
- **F-UI-011 · Radius and layout container** — `--radius 0.75rem` (lg 12 px, md 10 px, sm 8 px); container centred, padding 2 rem, max 1400 px at `2xl`; content pages max-width 3xl (result, confirmation, invoice) or 7xl (home grid).
- **F-UI-012 · RTL rules** — Document `dir="rtl"`; dialogs, selects, radio groups, footer, invoice and viewer restate `dir="rtl"`. LTR islands: references, ticket codes, phone numbers, stack traces. Pagination chevrons mirrored manually (previous = right chevron). Fixed widgets use physical sides (WhatsApp bottom-right, toasts top-right).
  - *Rebuild:* logical properties only (`ms-*`, `me-*`, `start-*`, `end-*`, `text-start`), `rtl:`/`ltr:` variants for icons, `<bdi>`/`dir="ltr"` on codes and phones, so the same views work in English.
- **F-UI-013 · Animations** — Keyframes: `scanner-line` 2.8 s ease-in-out infinite (translateY 0→15 rem, opacity .25→.9; off under reduced motion); `new-booking-glow` 1.5 s × 3 (green `hsl(142 76% 36%)` glow + border); `recent-pulse` 2 s infinite (scale 1→1.02 with `--pulse-color` ring, "5-minute highlight"); toast slide-in 0.4 s / slide-out 0.3 s; accordion 0.2 s ease-out. Utilities: pulse (loading text, terms CTA), spin (loaders), `hover:shadow-2xl`, `hover:scale-105`, backdrop blur, drop shadow, `line-clamp-3`.
- **F-UI-014 · Sounds** — Web Audio tones (scanner/capacity alerts, not public): alert = 880 Hz 0.22 s then 1320 Hz at +0.18 s for 0.3 s (sine, volume 0.35); error = square 320 Hz 0.25 s (volume 0.4) then 200 Hz at +0.22 s for 0.35 s.
- **F-UI-015 · Imagery** — Bundled: fallback hero `qatar-event-hero.jpg`, payment logos Visa/Mastercard/Apple Pay/Sadad, `og-image.jpg`, favicons. Admin-configurable: logo, hero image, header texture (repeat-x under a gradient), before-footer image.
  - *Rebuild:* medialibrary conversions (WebP/AVIF, responsive `srcset`), explicit width/height to avoid layout shift.
- **F-UI-016 · Responsive rules** — Breakpoints Tailwind default (`sm 640`, `md 768`, `lg 1024`, `xl 1280`, `2xl 1400` container); mobile hook threshold < 768 px. Event cards 1/2/3 per row; ticket cards 100/90/80 % width; checkout two-column only on `lg`; logo heights 32–53 px by breakpoint; WhatsApp label hidden below `sm`; action rows stack vertically below `sm`.
- **F-UI-017 · Formatting conventions** — Amounts with 2 decimals + `ريال قطري` (pages) or `ر.ق` (invoice); dates Arabic month/weekday names with Latin digits (`ar-u-nu-latn`) in Asia/Qatar; times 12-hour with `ص`/`م`.

**Luxury modern upgrade direction (NEW — keeps the identity)**
- NEW: Keep the two-family identity — maroon `#9C1638` + gold `#F9B11F` for actions and status, desert neutrals (cream `#F5EFE7`, sand `#D4B78A`, terracotta `#A85740`, bark `#6B4E3D`) for surfaces — but unify them in one token set and remove off-brand colours (violet print ticket, Tailwind yellow buttons, coral footer becomes a deep maroon-to-bark footer with a thin gold rule).
- NEW: Typography hierarchy: display headings Tajawal 800 with slight negative tracking, body 400/500 at 16 px min on mobile, gold hairline dividers, generous whitespace (8-pt spacing scale).
- NEW: Surfaces: ivory cards with 1 px sand borders, soft `shadow-elegant`, 16 px radius on primary cards, subtle Sadu-pattern (Qatari weaving) SVG texture at 4–6 % opacity in headers and ticket banners instead of raster textures.
- NEW: Motion: 150–250 ms ease-out transitions, gentle card lift on hover, skeleton shimmer, success check animation on payment confirmation; all disabled under `prefers-reduced-motion`.
- NEW: Ticket cards redesigned as "tickets" (perforated edge, gold foil gradient for VIP, maroon for normal, sand for parking) and the same visual used on the invoice, printable ticket and wallet pass.
- NEW: Dark luxury theme (maroon-black `#1A0008` background, gold accents) for scanner/POS/boards, matching F-UI-006.
- NEW: Accessibility: WCAG AA contrast checked for gold-on-white text (use bark text on gold backgrounds), focus rings in gold, 44 px minimum touch targets.

**Original defects to avoid — UI:**
- Two unrelated colour systems (theme tokens vs inline desert hex) mixed per page; result/confirmation pages use white while booking pages use cream.
- Off-brand violet printable ticket and yellow back buttons; coral footer unrelated to the header.
- Physical left/right utilities and manually mirrored icons (breaks an English/LTR mode).
- Dark-mode tokens defined but unreachable; scanner palette separate from the main tokens.
- Hardcoded brand name, season and year in printed documents.


---

## 2.2 Payments, notifications & integrations

This part covers everything that runs server-side between the customer, the Sadad payment gateway, the e-mail provider, the n8n/WhatsApp automation and external AI tooling. In the original these were Supabase Edge Functions (Deno) plus PostgreSQL RPCs. In the rebuild they become one `App\Payments\Sadad` module behind a `PaymentGateway` interface, queued jobs (Horizon), scheduled commands, Laravel Mailables and a small set of HTTP controllers. All amounts are QAR with 2 decimals. Every business rule uses the `Asia/Qatar` time zone (UTC+3, no DST).

Conventions used below:
- "Booking reference" (`ref`) always means `orders.booking_reference`, e.g. `QTR-3-10-2026-1A2B3C4D5E6F` (web) or `POS-21-09-2026-…` (POS). It must match `^[A-Za-z0-9][A-Za-z0-9_-]{0,59}$` wherever it arrives from outside.
- "Failed" (derived status) = `payment_status = cancelled` AND `payment_note ∈ {payment_failed, expired, paid_after_expiry_no_stock}`. Any other cancelled note (for example `admin_cancelled` or a free staff note) is a final staff cancellation.
- "Background" means "after the HTTP response has been sent". The original used `EdgeRuntime.waitUntil`; the rebuild uses queued jobs (`dispatch()`, or `->afterResponse()` for trivial work).

---

### PAY — Sadad payments

Online card payment through Sadad (Qatar) Web Checkout 2.1, with server-to-server verification through the Sadad merchant API. Actors: customers (checkout, result page), Sadad (browser callback, webhook), admins (verify, diagnose, transaction details). New app: `App\Payments\Sadad\*` (config, `CheckoutRequestBuilder`, `Signature`, `PayloadNormalizer`, `MerchantApiClient`, `PaymentDecider`, `PaymentProcessor`), routes under `/payments/sadad/*`, Livewire page `/payment/result`, and a Filament settings page plus a diagnostics widget.

#### Configuration

- **F-PAY-001 · Payment gateway module and configuration source** — All Sadad settings are read by a single `SadadConfig` value object. Fields: `merchantId`, `checkoutSecret`, `apiLoginSecret`, `websiteDomain`, `environment`, `siteUrl`. Each value is trimmed, and an empty string becomes null. In the original these came from the single `settings` row (`sadad_merchant_id`, `sadad_secret`, `sadad_api_key`, `sadad_website_domain`, `sadad_environment`, `site_url`), plus an env secret `SADAD_API_SECRET_KEY` that only the transaction-details function used. A missing settings row gives all nulls with environment `auto`.
  - *Rebuild:* store secrets in `.env` (or in spatie/laravel-settings with `encrypted` casts). Never send them to the browser: the Filament form shows "set / not set" and a replace-only password field. Bind `PaymentGateway` → `SadadGateway` in a service provider so another gateway can be added later.

- **F-PAY-002 · Merchant ID (Sadad ID)** — numeric string. It is sent as form field `merchant_id` at checkout, and as a JSON **number** `sadadId` (`Number(merchantId)`) at API login. Admin label `معرف التاجر (Merchant ID)`, placeholder `أدخل معرف التاجر`.

- **F-PAY-003 · Checkout secret key** — used to compute the request `signature` and to verify the callback/webhook `checksumhash`. In the original, `_shared/sadad-api.ts` also used it as the API login `secretKey`. Admin label `المفتاح السري` (password input, placeholder `أدخل المفتاح السري`). It must never appear in any response (test-pinned).

- **F-PAY-004 · API login secret** — the original had two competing credentials. The verification path logged in with `settings.sadad_secret`. The transaction-details path logged in with env `SADAD_API_SECRET_KEY`, and its code comment said "API Login has a dedicated credential … Sadad rejects [checksum keys]". `settings.sadad_api_key` (admin label `رقم الدعم (Support Pin Number)`, help `رقم الدعم الخاص بحساب سداد - Support Pin Number`) was loaded but never used.
  - *Rebuild:* one config key `SADAD_API_LOGIN_SECRET`, used by **every** merchant-API call. If it is empty, fall back to the checkout secret and record a warning in diagnostics. Drop the unused `sadad_api_key`, or keep it as a display-only "support PIN".

- **F-PAY-005 · Website domain** — the bare domain registered with Sadad when the secret was generated (e.g. `qcamelmc.org`, with no scheme and no path). It is used for form field `WEBSITE`, API login `domain`, the HTTP header `Origin: https://<domain>` on API calls, and the fallback customer e-mail domain. Admin label `النطاق المسجل (Website Domain)`, placeholder `مثال: qcamelmc.org أو www.qcamelmc.org`, help `يجب أن يطابق النطاق المسجل في لوحة سداد تماماً عند إنشاء المفتاح السري`.

- **F-PAY-006 · Environment `auto | sandbox | live`** — default `auto`; any other value is treated as `auto` (the DB has a CHECK constraint). The setting only chooses which **API base** is used for verification. The checkout URL is the same in every environment (`https://sadadqa.com/webpurchase`), because sandbox vs live is decided by the merchant credentials. API bases: live `https://api-s.sadad.qa`, sandbox `https://api-sandbox.sadad.qa`. With `auto`, the client tries live first, then sandbox, and the first successful login wins. An explicit environment tries only that base. Admin select `بيئة سداد` with options `تلقائي (يُكتشف من مفتاحك)` / `تجريبي (Sandbox)` / `فعلي (Live)`, help `يُستخدم للتحقق من الدفع مع سداد. اتركه «تلقائي» إلا إذا طلب منك الدعم غير ذلك.`

- **F-PAY-007 · Site URL and safe return base** — optional `site_url` is the base the customer is sent back to after payment. Admin label `عنوان الموقع (اختياري)`, placeholder `https://qcamelmc.org`, help `إليه يعود العميل بعد الدفع. اتركه فارغاً ليعود إلى الموقع الذي حجز منه.` It is stored trimmed, and empty becomes null. Return-base resolution is `resolveReturnBase(siteUrl, order.return_origin)`: take the first candidate that, after trimming and stripping trailing slashes, matches
  `SAFE_ORIGIN = ^(https://[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+(:\d+)?|http://localhost(:\d+)?)$` (case-insensitive). That means a bare https origin with at least one dot and an optional port, or `http://localhost[:port]`. No path or query is allowed. `javascript:`, `data:`, protocol-relative URLs, plain http (except localhost), paths and junk are refused (test-pinned). If no candidate matches, the result is null.

- **F-PAY-008 · Return-origin capture at order creation** — when an order is created, the request `Origin` header goes through the same `SAFE_ORIGIN` filter (`safeOrigin(origin)`) and is stored in `orders.return_origin`. An unsafe value is stored as null.

- **F-PAY-009 · "Sadad configured" rule** — Sadad counts as configured only when merchant ID, checkout secret **and** website domain are all non-blank. The API key is not required. If an online order is requested while Sadad is not configured, the response is `503 payment_unavailable "Online payment is not available right now"`, **before any seat is reserved** (test-pinned). Cash orders need no gateway.

- **F-PAY-010 · Callback and webhook URLs** — derived, never typed in. Original: `<SUPABASE_URL>/functions/v1/sadad-callback` and `/functions/v1/sadad-webhook`, with any trailing slash of the base stripped. The callback URL must start with `https://`, otherwise checkout building throws `Callback URL must use https`. The webhook URL is registered by hand in the Sadad merchant panel (Payment Gateway → Webhook).
  - *Rebuild:* `route('payments.sadad.callback')` = `https://<app>/payments/sadad/callback` and `route('payments.sadad.webhook')` = `https://<app>/payments/sadad/webhook`. Both are CSRF-exempt and accept `GET|POST`. Keep a permanent alias at the old public path `/sadad-callback`, because the return URL configured at Sadad may still point there (see F-PAY-027).

#### Web Checkout 2.1 (signed form)

- **F-PAY-011 · Checkout request builder** — `buildCheckoutRequest({merchantId, secret, orderId, amount, website, callbackUrl, mobile, email, now, items})` returns `{url: "https://sadadqa.com/webpurchase", fields: {...}}`. Preconditions, each of which throws (an internal error, never shown to the customer):
  - `Sadad secret key is not configured`
  - `Sadad merchant id is not configured`
  - `Sadad website is not configured`
  - `Callback URL must use https`
  - `Invalid amount` (amount not finite or ≤ 0)

  Spec reference: `https://developer.sadad.qa/web-checkout_2.1/`.

- **F-PAY-012 · Signed fields (exact names and case)** —

  | Field | Value |
  |---|---|
  | `merchant_id` | Sadad ID (string) |
  | `ORDER_ID` | booking reference (≤ 60 chars, `[A-Za-z0-9_-]`) |
  | `TXN_AMOUNT` | order total, `"<int>.<2 digits>"`, dot separator, no thousands separator (e.g. `150.00`, `3.50`) |
  | `WEBSITE` | website domain |
  | `CALLBACK_URL` | https callback URL |
  | `MOBILE_NO` | Sadad mobile (digits incl. country code, F-PAY-015) |
  | `EMAIL` | customer e-mail or fallback (F-PAY-016) |
  | `txnDate` | **Qatar** calendar date `YYYY-MM-DD` = (now + 3 h) as ISO date. Example: 21:30Z on 2026-10-09 → `2026-10-10` |
  | `signature` | F-PAY-013 |

- **F-PAY-013 · Request signature algorithm** — `signature = UPPERCASE( hex( SHA-256( secret + concat(values) ) ) )`. Rules:
  - The values are concatenated with **no separator**.
  - They are taken in the order of their **keys sorted by byte order**: uppercase letters sort before lowercase, as with PHP `ksort($p, SORT_STRING)` or JavaScript's default sort.
  - Keys `signature` and `checksumhash`, and every key that starts with `productdetail`, are excluded.
  - Values are never URL-encoded. Booleans are stringified the PHP way: null/false → `""`, true → `"1"`, anything else `(string)`.
  - The resulting order for the 8 signed fields is `CALLBACK_URL, EMAIL, MOBILE_NO, ORDER_ID, TXN_AMOUNT, WEBSITE, merchant_id, txnDate`, so the hash input is `secret‖CALLBACK_URL‖EMAIL‖MOBILE_NO‖ORDER_ID‖TXN_AMOUNT‖WEBSITE‖merchant_id‖txnDate`.

  PHP: `$p = $signed; ksort($p, SORT_STRING); $sig = strtoupper(hash('sha256', $secret . implode('', array_values($p))));`.

  **Reference test vector (MUST pass):** fields `{merchant_id: 1664851, ORDER_ID: QTR-ABC123DEF456, TXN_AMOUNT: 150.00, WEBSITE: example.qa, CALLBACK_URL: https://x.supabase.co/functions/v1/sadad-callback, MOBILE_NO: 97455512345, EMAIL: a@b.qa, txnDate: 2026-10-09}` with secret `S3CR3T` → `6A6D258727B321A8EC7C02CAFD96B2F14D754BA1627029F2920D27E11F4D0B49`.

- **F-PAY-014 · Amount handling in cents** — `toCents(a) = round((a + ε) × 100)`. `formatAmount` formats integer cents, so `0.1 + 0.2` → `"0.30"`. It rejects 0, negative values, NaN and Infinity.
  - *Rebuild:* keep money as integer dirhams (cents) or `DECIMAL(10,2)` with brick/money; never use floats.

- **F-PAY-015 · Sadad mobile normalisation (`toSadadMobile(phone, countryCode)`)** — steps:
  1. `cc` = digits of `countryCode`, or `974` when empty.
  2. `digits` = digits of `phone`, with a leading `00` removed.
  3. If the trimmed phone starts with `+`, return `digits` (the number's own country code wins).
  4. Else, if `digits` already starts with `cc` and the remainder has ≥ 7 digits, return `digits`.
  5. Else return `cc + digits-without-leading-zeros`.

  Pinned examples:
  - `('55512345', '+974')` → `97455512345`
  - `('+974 5551 2345', '+974')` → `97455512345`
  - `('0097455512345', '+974')` → `97455512345`
  - `('0555 1234', '+966')` → `9665551234`
  - `('+966 5551 0001', '+974')` → `96655510001`
  - `('+20 100 123 4567', '+974')` → `201001234567`

  The same function also produces every "international phone" sent to n8n (NTF).

- **F-PAY-016 · Fallback e-mail** — Sadad requires `EMAIL`. If the customer gave none, use `fallbackEmail(websiteDomain)`: strip the scheme and path and lowercase the result; empty → `noreply@example.invalid`; a host that contains a dot → `noreply@<host>`; otherwise `noreply@<host>.invalid`. Pinned: domain `MYSHOP` → `noreply@myshop.invalid`.

- **F-PAY-017 · Product detail lines (unsigned)** — these are appended **after** signing. For each line `i` (0-based):
  - `productdetail[i][order_id]` = booking reference
  - `productdetail[i][itemname]` = the label from `TICKET_LABELS`: `vip` → `تذكرة VIP`, `normal` → `تذكرة دخول عامة`, `parking` → `تصريح مواقف` (an unknown type uses the raw type)
  - `productdetail[i][amount]` = `formatAmount(unit price)`
  - `productdetail[i][quantity]` = `String(qty)`

  At order creation the lines come from the order's items (price descending).

- **F-PAY-018 · Checkout response and browser hand-off** — order creation (`create-order`, see the booking module) returns `200` with header `Cache-Control: no-store`. The body is `{success:true, order:{id, booking_reference, total_amount, payment_method, payment_status, payment_expires_at}, payment:{url, fields}}`; `payment` is present only for `sadad`.

  The browser builds a hidden `<form method="POST" action="https://sadadqa.com/webpurchase" target="_self">`. It contains one `<input type="hidden">` per field, with values set as DOM properties (no HTML injection), and the form is attached to `body` and submitted. The client refuses any URL that is not `https:` on host `sadadqa.com`, `www.sadadqa.com` or `secure.sadadqa.com`; the messages are `Refusing to send payment details: invalid Sadad address` and `Refusing to send payment details to an address that is not Sadad`. If the redirect throws, the client navigates to `/payment/result?ref=<ref>`. Toast shown before leaving: `جاري تحويلك إلى بوابة الدفع الآمنة...`.
  - *Rebuild:* a Blade partial `<x-sadad-redirect-form :payment="$payment"/>` that auto-submits, with a `<noscript>` submit button.

- **F-PAY-019 · Checkout-build failure releases the seats** — if building the request throws after the order was created, call `mark_order_payment_failed(ref, "checkout_request_failed")` (errors ignored). The order becomes `cancelled`, note `payment_failed`, `payment_error_reason = checkout_request_failed`, and its seats are released. Respond `502 payment_unavailable "Online payment is not available right now"`. The client message for `payment_unavailable` is `الدفع الإلكتروني غير متاح حالياً. يمكنك اختيار الدفع عند الحضور.`

- **F-PAY-020 · Pay again (re-issue checkout)** — original endpoint `POST sadad-payment {booking_reference}`. It is used by the result page button `إعادة فتح صفحة الدفع`, and needs no login (the reference is the capability).
  - Input: the reference, trimmed. If it fails the regex → `400 invalid_reference "Invalid booking reference"`. Methods other than POST → `405`.
  - Unknown reference → `404 order_not_found "Booking not found"`.
  - The order must be `payment_status = pending` **and** `payment_method = sadad`, otherwise `409 not_payable "This booking is not waiting for an online payment"`. Confirmed, failed and cash orders all give 409 (test-pinned).
  - Sadad not configured → `503 payment_unavailable`.
  - Rebuild: the amount is the **stored** `orders.total_amount`. Lines are rebuilt from the holders: count per `ticket_type` → `{name: TICKET_LABELS[type], price: current tickets.price for that type (0 if missing), quantity}`, dropping lines with price ≤ 0. Mobile = `toSadadMobile(customer.phone, customer.country_code || "+974")`; e-mail = the customer's or the fallback.
  - Output: `200 {success:true, payment:{url, fields}}`, `Cache-Control: no-store`. Exceptions → `500 internal_error`.
  - The original does **not** check `payment_expires_at`. An expired order that the lazy expiry has not yet closed can still be paid again; a later confirmation either re-reserves the seats or ends in `paid_after_expiry_no_stock`.
  - *Rebuild:* store order line items (`order_items`: ticket_id, type, unit_price, qty) at creation and reuse them, so the product lines always add up to `TXN_AMOUNT` even after a price change. Refuse when `payment_expires_at < now()` (run the expiry first, then return 409). Rate-limit this endpoint to 10 per minute per reference + IP.

#### Return (callback) and webhook

- **F-PAY-021 · Payment payload reader** — the same reader handles the callback and the webhook (`readPaymentPayload`):
  - `GET` → URL query parameters.
  - Any method other than GET or POST → `{}`.
  - `multipart/form-data` → form fields (file parts become `""`).
  - Otherwise read the body text; if it is longer than 64 000 chars → `{}`.
  - If the content type contains `json`, or the text starts with `{` → parse a JSON object (arrays or invalid JSON → `{}`).
  - Otherwise parse `application/x-www-form-urlencoded`.

  For POST the query string is ignored. Garbage input must never throw (test-pinned).

- **F-PAY-022 · Payload normalisation** — `normalizePaymentPayload(payload)` → `{orderRef, transactionNumber|null, status, amount|null, sandbox|null}`, or null when no valid reference is found:
  - `orderRef` = the first non-blank of `ORDERID`, `ORDER_ID`, `website_ref_no`, `websiteRefNo`. It must match the reference regex; otherwise the whole payload is ignored (returns null).
  - `status`: if `transaction_status` or `transactionStatus` is present, map the code: `"3"` → `success`; `"2"`, `"7"` → `failed`; `"1"`, `"5"`, `"6"` → `in_progress`; anything else → `unknown`. If neither is present, use `STATUS`: `TXN_SUCCESS` → success, `TXN_FAILURE` → failed, anything else → unknown. (Code `4` is unknown here but means "failed" in the API, F-PAY-030.)
  - `transactionNumber` = `transaction_number` or `transactionNumber`, only if it matches `^[A-Za-z0-9_-]{1,64}$`; else null.
  - `amount` = `TXNAMOUNT` or `txnAmount` if numeric; else null.
  - `sandbox` = `issandboxmode` or `isTestMode`: `""` → null, `"1"` → true, anything else → false.

- **F-PAY-023 · Checksum verification (informational only)** — computes `expected = lower(hex(SHA-256(secret + concat(values of ALL fields except checksumhash, sorted by key))))` and compares it constant-time with `lower(trim(received checksumhash))`. Returns false if the payload, the secret or the received value is missing. JSON webhook values are stringified the PHP way (numbers as-is, null → `""`).

  PHP: `unset($p['checksumhash']); ksort($p, SORT_STRING); hash_equals(hash('sha256', $secret.implode('', $p)), strtolower(trim($received)))`.

  Pinned test-vector hash: `6082cc9b43e1c98b95f4911c9b696810cfa912f4a88e306217500a144bf26b8a`.

  The result is only **recorded** in `payment_events.checksum_valid` and is **never trusted**. Reason (keep this in code comments): Sadad hashes `secret + values` with no separator, and the server signs requests that contain customer-chosen text (e-mail, mobile). A customer can therefore re-split a legitimate request signature into a forged "paid" callback that verifies.

- **F-PAY-024 · Callback endpoint** — `GET|POST /payments/sadad/callback` (original `sadad-callback`, no auth, no CORS handling because it is a top-level navigation). Fields used:
  - reference: `ORDERID | ORDER_ID | website_ref_no | websiteRefNo`
  - status: `transaction_status | transactionStatus`, or `STATUS`
  - `transaction_number | transactionNumber`
  - `TXNAMOUNT | txnAmount`
  - `issandboxmode | isTestMode`
  - `checksumhash` (every posted field takes part in the checksum)

  Algorithm:
  1. Read and normalise the payload.
  2. If a valid reference is found: compute `checksumValid` (false when no secret is set), then run the processor (F-PAY-036) with `source = "callback"`, and remember the order's `return_origin`.
  3. Every exception is caught and logged, because the customer must always land somewhere.
  4. `base = resolveReturnBase(site_url, return_origin)`.

  Response:
  - If `base` is set: `303 See Other`, `Location: <base>/payment/result?ref=<urlencoded ref>` (or `<base>/` when there is no valid reference), `Cache-Control: no-store`, empty body.
  - Otherwise: `200 text/html; charset=utf-8`, no-store, a fallback page: `<html lang="ar" dir="rtl">`, title `Payment`, H1 `تم استلام عملية الدفع`, the line `Payment received. You can close this page.`, the reference in `<p dir="ltr"><strong>REF</strong></p>` (safe because it is regex-validated), and the line `سيصلك التأكيد قريباً. / Your confirmation will follow shortly.`
  - *Rebuild:* dispatch the verification as a job, but run it **synchronously with a short (≤ 5 s) budget** before redirecting, so most customers land on an already-settled page. The result page polls anyway.

- **F-PAY-025 · Webhook endpoint** — `POST /payments/sadad/webhook` (original `sadad-webhook`, no auth). Sadad server-to-server JSON with fields `websiteRefNo`, `transactionStatus` (1/2/3/5/6/7), `transactionNumber`, `txnAmount`, `isTestMode`, `checksumhash`. The payload is read and normalised. If the reference is valid, compute the checksum (informational) and run the processor with `source = "webhook"`.

  The response is **always** `200` `{"status":"success"}` (`Content-Type: application/json`), even for malformed bodies and internal errors. Sadad's guidance: always answer 200 and process idempotently; a non-200 only triggers retries. Payloads with no valid reference are acknowledged and ignored (not logged). Replays neither confirm nor notify twice (test-pinned).
  - *Rebuild:* acknowledge immediately and process in a queued job (`ProcessSadadNotification`) on a dedicated `payments` queue. Log invalid payloads at debug level with a rate limit.

- **F-PAY-026 · Webhook registration** — the merchant must register the webhook URL by hand in the Sadad merchant panel (Payment Gateway → Webhook). The diagnostics screen prints the URL with a copy button (F-PAY-051, F-PAY-052).

- **F-PAY-027 · Legacy return path** — the public routes `/payment/result` and `/sadad-callback` render the **same** result page. The page reads the reference from the query keys `ref`, then `ORDERID`, then `order` (trimmed, regex-validated). If none is present it falls back to the last reference stored in the browser (`orderRefs`, last 10). Neither path loads the analytics script (Clarity), because the reference in the URL is a capability.
  - *Rebuild:* in Laravel `/sadad-callback` must be a route that, for a `POST` from Sadad, runs the callback handler (F-PAY-024), and for a `GET` with `ref` shows the result page.

#### Merchant API client (source of truth)

- **F-PAY-028 · API login** — `POST {base}/api/userbusinesses/login`. Headers: `Content-Type: application/json`, `Accept: application/json`, `Origin: https://<websiteDomain>`. Body `{"sadadId": <merchant id as number>, "secretKey": "<api login secret>", "domain": "<websiteDomain>"}`. Success = a 2xx response whose JSON contains `accessToken`. Error codes:
  - `login_<env>_<httpStatus>`
  - `login_<env>_no_token`
  - `login_<env>_<exception message>`

  Timeout is **8 000 ms** per HTTP call. Docs: `https://developer.sadad.qa/API/Authentication`.
  - *Rebuild:* `Http::timeout(8)->acceptJson()`. Cache the token in Redis per environment for 10 minutes (NEW, not in the original, which logged in on every lookup).

- **F-PAY-029 · Environment detection** — `detectEnvironment(creds, environment)`: with `auto` try `[live, sandbox]`, otherwise only `[environment]`. The first successful login returns `{ok:true, environment}`; if all fail, `{ok:false, errors:[...]}`, e.g. `['login_live_401','login_sandbox_401']` (test-pinned).

- **F-PAY-030 · Transaction lookup by reference** — `lookupTransaction(creds, environment, orderRef, transactionNumber?)`. It never throws.
  1. If `orderRef` fails the safe-reference regex → `unavailable invalid_order_ref`, and no request is made.
  2. For each environment (auto: live, then sandbox): log in. On a login error, record it and try the next environment.
  3. With a token, call `GET {base}/api/transactions/listTransactions?website_ref_no=<urlenc ref>&skip=0&limit=50`. Headers: `Authorization: <accessToken>` (the **raw** token, no `Bearer`), `Accept` and `Content-Type` json. A non-2xx response → `unavailable list_<env>_<status>`; a body that is not an array → `unavailable list_<env>_unexpected_response`. Docs: `/API/List-Transactions`.
  4. `summarise(rows)`: keep rows whose `website_ref_no` is null/blank or equals the reference.
     - Any kept row with status id `3` and a numeric `amount` → `success {transactionNumber: String(invoicenumber), amount, websiteRefNo, sandbox}`. A success is preferred over earlier failed rows; a non-numeric amount is ignored.
     - Otherwise, any in-progress row (`1, 5, 6`) → `in_progress`.
     - Otherwise, the first failed row (`2, 4, 7`) → `failed {transactionNumber: invoicenumber or null}`.
     - Otherwise → `not_found`.

     The status id is read from `transactionstatusId` or `transactionstatus.id`.
  5. Once a login has succeeded in an environment, that environment's verdict is final: a `not_found` there does not fall through to sandbox.
  6. Any exception → `unavailable <env>_<message>`. If every login failed → `unavailable` with the login errors joined by commas (or `no_environment`). Network errors and 5xx → `unavailable`.

- **F-PAY-031 · Single-transaction fallback** — only when the list verdict is `not_found` **and** a valid transaction number is known (from the callback or webhook): `GET {base}/api/transactions/getTransaction?transactionno=<urlenc txn>`. A 404 → `not_found`; any other non-2xx → `unavailable single_<env>_<status>`; otherwise `summarise([row])`. A row that belongs to another reference is ignored, which gives `not_found`. Docs: `/API/Get-Single-Transaction`.

- **F-PAY-032 · API result kinds** — `success {transactionNumber, amount, websiteRefNo|null, sandbox}` | `failed {transactionNumber|null}` | `in_progress` | `not_found` | `unavailable {error}`. If Sadad is not configured, the lookup returns `unavailable sadad_not_configured` without any HTTP call.

#### Decision engine and processor

- **F-PAY-033 · Payment decision rule (`decidePayment`)** — this is the single rule for marking an order paid or failed. **Only Sadad's API can confirm or fail an order**; a callback or webhook, even with a valid checksum, is only a trigger.

  Input: `{orderRef, orderTotal, orderStatus ∈ pending|confirmed|cancelled|failed, payload: NormalizedPayment|null, checksumValid, api: ApiResult|null}`. The rules are evaluated in order:
  1. Order `confirmed` → `noop already_confirmed`.
  2. Define `claimedSuccess` = payload present AND `payload.orderRef == orderRef` AND `payload.status == success`.
  3. API `success`:
     - `websiteRefNo` set and ≠ ref → `pending api_reference_mismatch`.
     - else no transaction number → `review missing_transaction_number`.
     - else amount ≠ total → `review amount_mismatch` (even when the signed callback states the correct amount).
     - else → `confirm` with the transaction number.
  4. API `failed` → `fail` with reason `sadad_reported_failure`.
  5. `claimedSuccess` and the API is null or `unavailable` → `review unverified_success_api_unavailable`.
  6. `claimedSuccess` and the API is `in_progress` or `not_found` → `pending unverified_success`.
  7. Otherwise → `pending <api.kind or "no_evidence">`.

  Actions: `noop | confirm | fail | pending | review`. Only `confirm` and `fail` change the database. `review` is logged for an admin to examine. A signed failure alone gives `pending` (the order simply expires); an unsigned failure is ignored; an expired or failed order **may** still be confirmed by an API success (test-pinned).

- **F-PAY-034 · Amount match tolerance** — `amountsMatch(a, b)` is false if either value is null, undefined, a blank string or non-finite. Otherwise it compares integer cents, which means equal within ±0.005. Pinned: `150.004 ≈ 150` (match); `149.99 ≠ 150`; non-numeric → no match.

- **F-PAY-035 · Reference match** — a transaction counts only when Sadad's `website_ref_no` equals the order's booking reference, or is empty (list filter in F-PAY-030 plus the decision check in F-PAY-033 rule 3). A transaction attributed to another order → `pending`.

- **F-PAY-036 · Payment processor (`processPayment`)** — input `{orderRef, payload, rawPayload, checksumValid, source ∈ callback|webhook|poll|admin}`.
  1. Load the order `{id, booking_reference, total_amount, payment_status (with failed mapping), return_origin}`. If it is not found, log the event `{order_id:null, api_result:null, decision:"none", outcome:"order_not_found"}` and stop. **Sadad is not called.**
  2. If the order is confirmed, log `{decision:"noop", outcome:"noop"}` and stop. Sadad is not called.
  3. `api = lookupTransaction(ref, payload.transactionNumber ?? null)`.
  4. `decision = decidePayment(...)`.
  5. On `confirm`: call `confirm_order_payment(ref, txn)`; the outcome is the result string. Only when the result is `confirmed` → run the `onConfirmed` side effects (F-PAY-038).
  6. On `fail`: call `mark_order_payment_failed(ref, reason)`; the outcome is `failed`, `not_pending` or `order_not_found`. Only when it is `failed` → run `onFailed` (F-PAY-039).
  7. On `pending` or `review`: the outcome is the action name; nothing is written to the order.
  8. **Always** insert exactly one `payment_events` row (F-PAY-040). A logging failure and any side-effect failure must never change the outcome.
  - *Rebuild:* `PaymentProcessor::process(PaymentTrigger $t): PaymentOutcome`, wrapped in a Redis lock `sadad:ref:<ref>` (10 s) to reduce duplicated API calls when the callback, webhook and poller arrive together. Correctness must still rely on the row lock (F-PAY-041).

- **F-PAY-037 · Stored payload cap** — the raw payload stored in `payment_events.payload` is JSON. If its serialised length is over **4 000** characters, store `{truncated:true, preview:<first 4000 chars>}`. An unserialisable payload is stored as `{unserialisable:true}`, and null as null. Test bound: the row is smaller than 8 100 chars.

- **F-PAY-038 · Exactly-once confirmation side effects (`onConfirmed`)** — these run only for the processor call whose `confirm_order_payment` returned `confirmed`, in the background, each independently (`allSettled` semantics; errors are logged and never propagate):
  1. Send the booking invoice e-mail (F-NTF-003).
  2. Send the admin new-sale e-mail (F-NTF-014).
  3. If the WhatsApp webhook is enabled and set: build the `payment_confirmed` payload from the database (F-NTF-026) and POST it once (8 s timeout).

  Pending and review outcomes trigger no follow-ups.
  - *Rebuild:* fire a `PaymentConfirmed` domain event; queued listeners `SendBookingInvoiceMail`, `SendAdminSaleAlert`, `PostPaymentConfirmedToN8n` (each `ShouldQueue`, `tries=3`, backoff `[10,60,300]`, and uniquely keyed by order id with `ShouldBeUnique`).

- **F-PAY-039 · Failure side effects (`onFailed`)** — these run only when `mark_order_payment_failed` returned `failed`, in the background:
  1. Insert a `payment_errors` row (F-PAY-055): `order_id`, `booking_reference`, `amount` (order total), `error_source` = `failureSource(reason)` (F-PAY-053), `error_code = "PAYMENT_FAILED"`, `error_message = reason`, `raw = {source:"payment-processor", reason}`. `event_id`, `quantity`, `payment_id`, `customer_name` and `customer_phone` are copied from the order.
  2. Send the payment-failed e-mail (F-NTF-009..012).

  Errors are swallowed.
  - *Rebuild:* a `PaymentFailed` event with queued listeners.

- **F-PAY-040 · Payment audit log (`payment_events`)** — one row per callback, webhook, poll or admin evaluation. Columns:

  | Column | Content |
  |---|---|
  | `id` | bigint identity |
  | `booking_reference` | text |
  | `order_id` | FK → orders, ON DELETE SET NULL |
  | `source` | NOT NULL, CHECK ∈ `callback, webhook, poll, admin` |
  | `payload` | json, capped per F-PAY-037 |
  | `checksum_valid` | bool |
  | `api_result` | `success / failed / in_progress / not_found / unavailable`, or null |
  | `decision` | `confirm / fail / pending / review / noop / none` |
  | `outcome` | `confirmed / already_confirmed / transaction_already_used / paid_after_expiry_no_stock / failed / not_pending / pending / review / noop / order_not_found` |
  | `created_at` | timestamp |

  Index `(booking_reference, created_at DESC)`. Only the server writes; admins read; anonymous users get nothing. The admin order screen surfaces these rows (owned by the admin finance section).
  - *Rebuild:* model `PaymentEvent` (no updates or deletes; append-only policy). Add an `api_error` text column and `decision_reason` (e.g. `amount_mismatch`) — NEW, so admins can see *why* a review happened. The original only stored the action.

#### Confirm / fail semantics (DB-level, transactional)

- **F-PAY-041 · Confirm payment (`confirm_order_payment(ref, txn)`)** — runs in one DB transaction with the order row locked (`SELECT … FOR UPDATE`):
  1. Not found → `{result:"order_not_found"}`.
  2. Already `confirmed` → `{result:"already_confirmed", order_id}`. This is idempotent: a second, different transaction number does **not** overwrite `payment_id`.
  3. Blank `txn` → raise `invalid_request`.
  4. Another order already has `payment_id = txn` → `{result:"transaction_already_used", order_id}`, and this order is left unchanged (pending stays pending).
  5. Update: `payment_status='confirmed'`, `payment_id=txn`, `paid_at=confirmed_at=now()`, `payment_note=NULL`, `payment_error_reason=NULL`. If the order was cancelled (expired or failed), its seats are **re-reserved** by the stock service.
     - Re-reservation fails because of stock → update only `payment_id=txn`, `paid_at=now()`, `payment_note='paid_after_expiry_no_stock'`; the status stays `cancelled`. Result `{result:"paid_after_expiry_no_stock", order_id}`. This must never oversell. Staff must refund in the Sadad panel; the admin label for this state is `دُفع بعد انتهاء الحجز ولا توجد تذاكر`.
     - A unique violation on `payment_id` → `transaction_already_used`.
  6. Result `{result:"confirmed", order_id}`. Confirming a pending order does not change the stock count, because the seats are already held.

  Unique partial index: `orders.payment_id` where not null (one Sadad transaction settles at most one order).
  - *Rebuild:* `OrderPaymentService::confirm(string $ref, string $txn): ConfirmResult` (enum). Use `DB::transaction` + `lockForUpdate()`, call `StockService::reserveForOrder()`, which locks the event's ticket rows ordered by id. Add a unique index on `payment_id` (MySQL allows multiple NULLs). Before migrating, deduplicate legacy duplicates. **Decision for original defect #11:** if the order was cancelled with a staff note (not in the failed set), still record `payment_id`/`paid_at`, but do NOT auto-confirm. Set `payment_note='paid_after_staff_cancel'` and raise an admin alert (NEW).

- **F-PAY-042 · Mark payment failed (`mark_order_payment_failed(ref, note)`)** — locks the row. Not found → `order_not_found`. Status is not `pending` → `{result:"not_pending"}`: a confirmed order can never be failed by a gateway message, and a repeat call is a no-op with no double release. Otherwise set `payment_status='cancelled'`, `payment_note='payment_failed'`, `payment_error_reason = trimmed note or 'payment_failed'` → `{result:"failed"}`, and release the seats.

- **F-PAY-043 · Derived status mapping** — wherever a status leaves the server for customers or the poller (`get_order_status`, the processor, verify-payment), `cancelled` + note ∈ {`payment_failed`, `expired`, `paid_after_expiry_no_stock`} is shown as `failed`. Other cancelled orders stay `cancelled`. `payment_note` itself is never exposed publicly.

#### Verification endpoints

- **F-PAY-044 · Verify-payment endpoint** — `POST /api/payments/verify` (original `verify-payment`), body `{booking_reference}`.
  - The reference fails the regex → `400 invalid_reference`. Methods other than POST → 405.
  - Unknown reference → `404 order_not_found "Booking not found"`.
  - Already confirmed → `200 {success:true, checked:false, payment_status:"confirmed"}`. **Sadad is not called.**
  - `adminId` = the signed-in admin, if any. `allowed = adminId != null || claimPaymentCheck(ref) != null` (F-PAY-045).
  - If allowed, run the processor with `payload = null`, `checksumValid = false`, and `source = "admin"` when an admin is signed in, else `"poll"`.
  - Re-read the order → `200 {success:true, checked: allowed, payment_status: pending|confirmed|cancelled|failed}`. When throttled the response is `checked:false` and no Sadad call is made.

- **F-PAY-045 · Public verification throttle (`claim_payment_check`)** — one atomic UPDATE sets `last_payment_check_at = now()` only when **all** of these hold:
  - `payment_method = sadad`
  - status is `pending`, OR `cancelled` with a note in the failed set
  - `created_at > now() − 3 days`
  - `last_payment_check_at` is null or older than **10 s**

  It returns `{order_id, booking_reference, total_amount, payment_status}`, or null when not allowed: throttled, confirmed, staff-cancelled, cash, older than 3 days, or unknown. Pinned: the first call is allowed; a second call within 10 s → null; confirmed and unknown → null; a failed online payment is re-checkable; a staff-cancelled order is not.
  - *Rebuild:* the same single conditional `UPDATE … WHERE …`, checking affected rows = 1. Add a Laravel `RateLimiter` of 30 per minute per IP on the route (NEW).

- **F-PAY-046 · Admin verification (bypass)** — an admin (button `تحقق من سداد` on the admin order screens) calls the same endpoint without the throttle, the 3-day window or the status restriction (only `confirmed` short-circuits). Events are recorded with `source = admin`. It can rescue a paid-but-expired order. In the original it could also confirm an admin-cancelled order (see F-PAY-041 *Rebuild*).

- **F-PAY-047 · Customer result-page polling contract** — `usePaymentStatus(ref)`, with constants `pollMs = 3000`, `verifyMs = 12000`, `timeoutMs = 120000`. Phases: `checking | confirmed | failed | delayed | not_found | cash`.
  - A null reference → `not_found` immediately.
  - Each tick reads the order status (F-PAY-049). An API error → retry silently after 3 s. An unknown reference → `not_found` (stop).
  - If `payment_method = sadad` and the order may be paid (`pending`, or `failed` and not yet verified while failed) and at least 12 s have passed since the last verify → call verify-payment. The **first verify happens at t = 0**. Then re-read the order.
  - Outcomes: `confirmed` → `confirmed` (stop). `failed`/`cancelled` → `failed` (stop). `cash_pos` → `cash` (stop). 120 s elapsed → `delayed` (stop). Otherwise schedule the next tick.
  - Pinned: confirmed at the first read never verifies; pending verifies at 0 s, ~12–13 s, ~24 s…; a failed order is verified exactly **once** (the customer may have paid late); a cancelled order is never verified; `checkAgain` restarts a fresh 120 s window; unmounting stops the timers.
  - *Rebuild:* a Livewire component with `wire:poll.3s`. Server-side verify is called from the component, respecting the 12 s spacing per session. The page layout, texts and buttons are specified in the public booking flow section.

- **F-PAY-048 · Result-page actions tied to payments** — in `delayed`, button `تحقق مرة أخرى` restarts polling. `إعادة فتح صفحة الدفع` (pay again, F-PAY-020) is shown when phase is `delayed`/`failed` AND method is `sadad` AND status is `pending` (in practice only in `delayed`). In `confirmed` and `cash` the page redirects to `/confirmation` (invoice). On `confirmed` it remembers the reference and clears the stored cart.

- **F-PAY-049 · Order status lookup (≤ 10 references)** — original `get_order_status(p_refs text[])`, callable anonymously.
  - Only the first **10** references are considered; each must match the reference regex, and others are silently ignored. Unknown references are simply absent.
  - Returns an array ordered by `created_at` of `{booking_reference, payment_status (failed-mapped), payment_method, total_amount, quantity, ticket_type, created_at, payment_expires_at, event_id, event:{title, event_date, location}, tickets:[{ticket_type, name, qr_code, qr_image_url}] (ordered by qr_code)}`.
  - It never returns `customer_id`, `id_number` or other personal data. Pinned: `[real, 'QTR-NOPE', 'bad ref!', real, real]` → exactly the 3 real ones.
  - *Rebuild:* `GET /api/orders/status?refs[]=…`, JSON, rate-limited to 60 per minute per IP, `Cache-Control: no-store`.

- **F-PAY-050 · Hold expiry for online orders** — web Sadad orders are created `pending` with `payment_expires_at = created_at + 30 min`. `expire_stale_orders(event?)` sets every pending **sadad** order past its expiry to `cancelled`, note `expired` (rows are taken with `FOR UPDATE SKIP LOCKED`, ordered by event_id, id), releases the seats and returns the count. It is idempotent (a second run returns 0). Cash and confirmed orders never expire. The original ran it **lazily** only: inside order creation for that event, and via `release_expired_holds(event)` when public pages load availability. There was no scheduler. Expired orders can still be confirmed later by an API success (F-PAY-041).
  - *Rebuild:* keep the lazy calls, and add the scheduled command `orders:expire-holds` every minute (`withoutOverlapping()`, `onOneServer()`).

#### Diagnostics and admin tools

- **F-PAY-051 · Sadad readiness diagnostics endpoint** — admin only (original `sadad-diagnose`; 401 without a session, 403 for non-admins). It takes no input and **never returns the secret**. Output: `200 {success:true, ready: (no check has status error), environment: "live"|"sandbox"|null, callbackUrl, webhookUrl, checks:[{id, status: ok|warn|error, message}]}`. Checks, in order:
  1. `merchant_id` — ok `Merchant ID: <id>` / error `Merchant (Sadad) ID is not set`.
  2. `secret` — ok `Secret key is set (<n> characters)` / error `Secret key is not set`.
  3. `website` — error `Website domain is not set`. Warn when the value contains `/`, `:` or whitespace: `"<domain>" should be just the domain registered with Sadad (no https:// or path)`. Else ok `Website: <domain>`.
  4. `api_login` — only when checks 1–3 are all set; runs `detectEnvironment`. Ok `Sadad API login works (TEST/sandbox credentials)` or `Sadad API login works (LIVE credentials)`. Error `Sadad API login failed (<errors joined ", ">). Payments cannot be verified automatically: check the merchant ID, the secret/API key and that the website domain matches the one the key was generated for.` If settings are missing: error `Skipped: complete the settings above first`.
  5. `api_lookup` — only after a successful login: `lookupTransaction(orderRef:"DIAGNOSTIC-PING")` in the detected environment. `unavailable` → error `Sadad transaction lookup failed (<error>)`; else ok `Payment verification (transaction lookup) works`.
  6. `callback_url` — ok `Customers return from Sadad to: <callbackUrl>`.
  7. `webhook_registration` — always warn: `Register this webhook URL in the Sadad merchant panel (Payment Gateway → Webhook): <webhookUrl>`.
  8. `automation` — ok `Automation (n8n) webhook is configured` / warn `No automation (n8n) webhook is configured: tickets will not be sent automatically`.
  9. `site_url` — ok `Customers are returned to <site_url>` / warn `Site URL is not set: customers return to the address they ordered from (fine for a single site)`.

- **F-PAY-052 · Diagnostics UI (Filament widget on the Integrations settings page)** — card title `فحص جاهزية الدفع`.
  - Intro before running: `احفظ إعدادات سداد ثم شغّل الفحص: يتأكد من الإعدادات ويجرّب الاتصال بسداد فعلياً (اختبار أو إنتاج) ويعرض الروابط التي يجب تسجيلها في لوحة سداد.`
  - Button `تشغيل الفحص` / `جارٍ الفحص...`.
  - One row per check, with the icon by status (ok green check, warn amber triangle, error red alert). Titles: `merchant_id` `معرف التاجر`, `secret` `المفتاح السري`, `website` `النطاق المسجل`, `api_login` `الاتصال بواجهة سداد`, `api_lookup` `التحقق من المدفوعات`, `callback_url` `رابط العودة بعد الدفع`, `webhook_registration` `تسجيل الـ Webhook في سداد`, `automation` `الأتمتة (n8n)`, `site_url` `عنوان الموقع`.
  - Copy fields (toast `تم النسخ`): `سجّل هذا الرابط في لوحة سداد (Payment Gateway ← Webhook):` → Webhook URL; `Callback URL (يُرسل مع كل طلب تلقائياً)` → Callback URL.
  - *Rebuild:* translate the English check messages to Arabic (keep the English in the `en` locale).

- **F-PAY-053 · Server-side failure classification** — `failureSource(reason)` returns `"bank"` if the lowercased reason contains any of `card, بطاقة, declin, مرفوض, insufficient, رصيد, bank, بنك, issuer, cvv, authorization`. Otherwise it returns `"sadad"`. With the current decision rules the reason is always `sadad_reported_failure`, so the result is always `sadad`.
  - *Rebuild:* one `PaymentErrorClassifier` shared by server and client logging, using the union of all keyword lists (F-PAY-054, F-NTF-012). Classify Sadad's real failure text when the API returns one (store it from the transaction row if present).

- **F-PAY-054 · Client/site failure classification and logging** — `classifyPaymentError(message, code)` works on the lowercased `message + " " + code`:
  - `bank` if it contains any of `card, بطاقة, declin, مرفوض, insufficient, رصيد, bank, بنك, expired card, منتهية, cvv, 3d, authorization, تفويض, issuer`;
  - else `sadad` if it contains any of `sadad, سداد, merchant, checksum, gateway, بوابة`;
  - else `site`.

  Checkout writes `payment_errors` rows with these codes:

  | error_code | error_source | When |
  |---|---|---|
  | `TICKETS_UNAVAILABLE` | `site` | availability check reported a shortage |
  | `TICKET_LIMIT` | `site` | per-person limit hit on the client |
  | `CHECKOUT_FAILED` | classified | any order-creation failure; `error_message` = `"<shown message> — <raw message>"` |

  Fields written: `event_id`, `customer_name`, `customer_phone`, `quantity`, `amount` (from the cart), `raw{paymentMethod, url, userAgent}`; `order_id`, `booking_reference` and `payment_id` are null. Logging never breaks the flow.

  Source labels for admins: `sadad` → `سداد`, `bank` → `البنك / البطاقة`, anything else → `الموقع`.
  - *Rebuild:* the browser must not insert directly. It calls `POST /api/payment-errors` (rate limit 5 per minute per IP, validated fields, `raw` ≤ 2 KB).

- **F-PAY-055 · Payment errors store (`payment_errors`)** — columns:

  | Column | Notes |
  |---|---|
  | `id` | |
  | `created_at` | |
  | `order_id` | FK SET NULL |
  | `booking_reference` | |
  | `event_id` | FK SET NULL |
  | `customer_name`, `customer_phone` | |
  | `quantity`, `amount` | |
  | `payment_id` | |
  | `error_source` | NOT NULL, default `site`; values `sadad`, `bank`, `site` |
  | `error_code` | |
  | `error_message` | |
  | `raw` | json NOT NULL, default `{}` |

  Indexes `created_at DESC`, `event_id`. Admins read and delete. The admin screen `أخطاء الدفع` is specified in the admin finance section.
  - *Rebuild:* add an enum CHECK on `error_source`, and broadcast inserts via Reverb (the original UI subscribed to realtime, but the table was not published).

- **F-PAY-056 · Sadad transaction details (admin)** — original `sadad-transaction`; admin only.
  - Auth: user JWT + `is_admin`. No auth header → `401 {error:"Unauthorized"}`; not an admin → `403 {error:"Forbidden"}`.
  - Input: `{orderId?: uuid, transactionno?: string trimmed 1..100}`, at least one required. Missing both → `400` `يجب تحديد الطلب أو رقم عملية سداد`. Other validation failures → the first validation message, or `بيانات الطلب غير صالحة`.
  - If there is no `transactionno`: load the order (`payment_id`, `payment_method`). None → `404 {error:"الطلب غير موجود"}`. Method ≠ sadad → `400 {error:"هذا الطلب ليس عبر سداد"}`. Otherwise use `payment_id` as the transaction number.
  - Still no transaction number → `400 {error:"لا يوجد رقم عملية سداد لهذا الطلب"}`.

- **F-PAY-057 · Transaction details — Sadad call** — the domain comes from settings (default `qcamelmc.org`); the login secret is the dedicated API login secret.
  - Merchant ID or secret missing → `200 {success:false, code:"SADAD_API_LOGIN_NOT_CONFIGURED", error:"تفاصيل العملية غير متاحة حالياً: مفتاح API Login الخاص بسداد غير مضبوط."}`.
  - Login (original: LIVE base only, no Origin header) gives no token → `200 {success:false, code:"SADAD_API_LOGIN_REJECTED", error:"تعذر التحقق من بيانات API Login لدى سداد. تحقق من المفتاح المخصص للـ API والنطاق <domain>."}`.
  - Then `POST {base}/api/transactions/getTransaction` with JSON body `{"transactionno":"<txn>"}`. Headers: `Authorization: <accessToken>`, `Origin: https://<domain>`, json content type and accept.
  - A non-2xx response or `tx.error` → status 404 if Sadad answered 404, else 502, with `{error: tx.error.message || "تعذر جلب تفاصيل العملية من سداد"}`. Unhandled → `500 {error: message || "خطأ غير متوقع"}`.
  - *Rebuild:* use the shared `MerchantApiClient` (respecting the environment setting and the 8 s timeout), and try `GET …getTransaction?transactionno=` first with a POST fallback (the two original clients disagreed). Cache results for 60 s per transaction.

- **F-PAY-058 · Transaction details — output** — `200 {success:true, transaction:{...}}`:

  | Field | Value |
  |---|---|
  | `transactionno` | `tx.invoicenumber ?? txn` |
  | `status` | `upper(tx.transactionstatus.name)`, or `UNKNOWN` |
  | `statusAr` | `STATUS_AR[status] ?? status` |
  | `isRefund` | bool |
  | `amount` | `Number(tx.amount \|\| 0)` |
  | `commission` | `Number(tx.servicecharge \|\| 0)` |
  | `refundCharge` | `Number(tx.refundcharge \|\| 0)` |
  | `netAmount` | `round3(amount − commission − refundCharge)` |
  | `mode` | `tx.transactionmode.name \| null` |
  | `entity` | `tx.transactionentity.name \| null` |
  | `transactiondate` | or null |
  | `websiteRefNo` | `tx.website_ref_no \| null` |

  `STATUS_AR`: `INPROGRESS` / `IN PROGRESS` → `قيد المعالجة`; `FAILED` → `فشلت`; `SUCCESS` → `ناجحة`; `REFUND` / `REFUNDED` → `مستردة`; `PENDING` → `معلقة`; `ONHOLD` / `ON HOLD` → `موقوفة`; `REJECTED` → `مرفوضة`. The admin UI shows an unknown empty name as `غير معروف`.
  - *Rebuild:* use `brick/money` for the net amount (2 decimals; the original rounded to 3).

- **F-PAY-059 · Recover-pending (legacy)** — original `recover-pending-orders`: an unauthenticated **no-op stub**. It logs `=== RECOVER PENDING ORDERS ===` and `Auto-processing disabled. Pending/failed orders must be handled manually by admin.`, then returns `200 {success:true, message:"Auto-processing disabled. Use admin dashboard to manually review pending orders."}`. OPTIONS → 200.
  - *Rebuild:* do not port the stub. Replace it with the scheduled reconciliation `payments:reconcile` (every 5 minutes, `withoutOverlapping`). It runs the processor (`source = poll`, NEW source value `reconcile` is acceptable) for sadad orders that are `pending`, or failed-cancelled, created within the last 3 days, whose `last_payment_check_at` is older than 2 minutes, in batches of 50. It then runs `orders:expire-holds`. This closes the gap where a paid order with no callback relies on the customer's tab or an admin click.

- **F-PAY-060 · Concurrency and idempotency guarantees** — preserve all of these:
  1. Confirm and fail lock the order row and are state-guarded. Only the call that actually flips the state returns `confirmed`/`failed`, and therefore fires side effects exactly once.
  2. Unique `payment_id`, plus the explicit `transaction_already_used` check.
  3. Stock changes are serialised by locking all ticket rows of the event in id order (two events in a fixed order when an order moves).
  4. E-mail idempotency keys (F-NTF-001) plus DB "already sent" checks.
  5. The webhook is always acknowledged; duplicates are harmless because of points 1–2.
  6. Callback, webhook, poll and admin may run simultaneously.
  - *Rebuild:* on MySQL, use InnoDB with consistent lock ordering and retry on deadlock (`DB::transaction($fn, 3)`). Write a Pest test that fires 20 parallel confirms and asserts exactly one `PaymentConfirmed` event.

#### PAYMENT STATE MACHINE

Stored state: `orders.payment_status ∈ {pending, confirmed, cancelled}` + `payment_note` + `payment_error_reason` + `payment_id` + `paid_at` + `confirmed_at` + `payment_expires_at` + `last_payment_check_at`. In the table, "failed" is the derived state (cancelled + failed-set note). "Seats held" means pending and confirmed orders count in `tickets.sold_quantity`.

| # | Current state | Trigger | Actor | Guard | Next state | Side effects |
|---|---|---|---|---|---|---|
| 1 | — | Order create, web, `sadad` | Customer | Validation; stock; web limits (5 admission + 5 parking per order); 5 vip+normal per person per event; ≤ 4 open orders per phone; total ≥ 3 QAR; event active; Sadad configured | `pending`, `payment_expires_at = now + 30 min` | Seats held; QR PNGs rendered; signed Sadad form returned; no n8n, no e-mail |
| 2 | — | Order create, web, `cash_pos` (pay at venue) | Customer | Same as #1 minus Sadad and the minimum amount | `pending`, no expiry | Seats held; QR PNGs; n8n `order-created` payload (background) |
| 3 | — | Order create, `pos` | Staff (admin / moderator / passcode) | Staff auth; `cash_pos` only | `confirmed` (`paid_at = confirmed_at = now`, `confirmed_by = actor`, `pos_user_id`, `n8n_response_message = 'طلب من نقطة البيع - POS'`, `n8n_responded_at = now`) | Seats held; QR PNGs; admin new-sale e-mail (background) |
| 4 | `pending` (sadad) | Checkout request build throws | System (order creation) | — | `cancelled`, note `payment_failed`, reason `checkout_request_failed` (= failed) | Seats released; 502 to client |
| 5 | `pending` / failed | Sadad callback (browser) | Sadad → customer browser | Normalised payload with a valid reference | Per decision (#8–#12) | `payment_events` row (`callback`); 303 to `/payment/result?ref=` or fallback page |
| 6 | `pending` / failed | Sadad webhook | Sadad server | Valid reference | Per decision | `payment_events` row (`webhook`); always 200 `{"status":"success"}` |
| 7a | `pending` / failed | Verify (public) | Result-page poller | `claim_payment_check`: sadad, ≤ 3 days, ≥ 10 s since last check, pending or failed | Per decision | `payment_events` (`poll`) |
| 7b | Any non-confirmed | Verify (admin) | Admin | Admin session | Per decision | `payment_events` (`admin`) |
| 7c | `pending` / failed (NEW) | `payments:reconcile` schedule | Scheduler | sadad, ≤ 3 days | Per decision | `payment_events` (`poll`/`reconcile`) |
| 8 | `pending` | Decision `confirm` (API status 3, reference match, txn present, amount equal to the cent) | Processor | Row lock; txn unused | `confirmed` (`payment_id`, `paid_at`, `confirmed_at`; note and reason cleared) | **Once:** invoice e-mail, admin sale e-mail, n8n `payment_confirmed` |
| 9 | failed (`expired` / `payment_failed` / `paid_after_expiry_no_stock`) | Decision `confirm` | Processor | Seats can be re-reserved | `confirmed` | Seats re-reserved; same as #8 |
| 10 | failed | Decision `confirm` | Processor | Seats **cannot** be re-reserved | stays `cancelled`, note `paid_after_expiry_no_stock`, `payment_id` + `paid_at` stored | Outcome logged; admin must refund (`دُفع بعد انتهاء الحجز ولا توجد تذاكر`). NEW: admin alert |
| 11 | `pending` / failed | Decision `confirm`, txn already on another order | Processor | — | unchanged | Outcome `transaction_already_used` logged |
| 12 | `cancelled` with a staff note | Decision `confirm` (admin verify / webhook / callback) | Processor | Original: same as #9/#10. **Rebuild: record `payment_id`/`paid_at`, note `paid_after_staff_cancel`, do not confirm** | Original: `confirmed`; rebuild: `cancelled` | Rebuild: admin alert for review/refund |
| 13 | `pending` | Decision `fail` (API status 2/4/7) | Processor | Row lock; status pending | `cancelled`, note `payment_failed`, reason `sadad_reported_failure` (= failed) | Seats released; `payment_errors` row (`PAYMENT_FAILED`, source bank/sadad); payment-failed e-mail (customer if enabled + admin copy) |
| 14 | failed / `confirmed` | Decision `fail` | Processor | Not pending | unchanged (`not_pending`) | None |
| 15 | Any non-confirmed | Decision `pending` / `review` (in_progress, not_found, unavailable, reference mismatch, amount mismatch, missing txn, unverified success) | Processor | — | unchanged | `payment_events` only. Review reasons: `missing_transaction_number`, `amount_mismatch`, `unverified_success_api_unavailable` |
| 16 | `confirmed` | Any trigger | Any | — | unchanged | `payment_events` `noop`; Sadad not called |
| 17 | `pending` (sadad) | `payment_expires_at < now` | `expire_stale_orders` (lazy at order creation / public availability; rebuild: every minute) | `FOR UPDATE SKIP LOCKED` | `cancelled`, note `expired` (= failed) | Seats released |
| 18 | `pending` / `confirmed` | Admin cancel (`إلغاء وإعادة للبيع`) | Admin | Admin | `cancelled`, note = given note or `admin_cancelled` | Seats released; a second cancel → `already_inactive` |
| 19 | any | Admin manual status edit | Admin | Admin (stock guard: reinstating while sold out raises `insufficient_stock`) | Chosen status | Seats follow the status; on → confirmed the admin UI sends n8n `payment_confirmed` (notify mode) |
| 20 | `confirmed` | Auto/batch invoice send | Scheduler / admin | sadad, confirmed, no automation message, event not finished, not on hold | unchanged (`n8n_response_message` set) | WhatsApp invoice via n8n |
| 21 | any | n8n-response callback | n8n | Shared secret | unchanged (`n8n_response_message`, `n8n_responded_at` set) | — |
| 22 | `confirmed`, or pending `cash_pos`, or `POS-` reference | Gate admit | Gate staff | Ticket valid today, not used | unchanged; if `n8n_responded_at` is null → message `تم التسجيل يدوياً ✓` | Holder `is_present` |

Customer-facing phases derived from this (result page): `checking` (pending), `confirmed`, `failed` (failed or cancelled), `delayed` (still pending after 120 s), `not_found`, `cash`.

#### Upgrades (new, not in original)
- NEW: scheduled `orders:expire-holds` (every minute) and `payments:reconcile` (every 5 min) (F-PAY-050, F-PAY-059).
- NEW: `order_items` snapshot so "pay again" re-signs identical lines (F-PAY-020).
- NEW: `payment_events.decision_reason` and `api_error` columns; a Filament "Payments needing review" list filtered on `decision = review` or outcome `paid_after_expiry_no_stock` / `transaction_already_used`.
- NEW: admin alert (database notification + e-mail) for `paid_after_expiry_no_stock` and for payments that arrive on staff-cancelled orders.
- NEW: Sadad API token cached 10 min; rate limits on verify, pay-again and status lookup.
- NEW: `PaymentGateway` interface (`createCheckout`, `handleReturn`, `handleWebhook`, `lookup`, `diagnose`) so a second gateway can be added.
- NEW: optional cash-at-venue expiry (e.g. at event start) configurable in settings (fixes defect 6 below).

**Original defects to avoid:**
- Two API-login credentials (`settings.sadad_secret` vs env `SADAD_API_SECRET_KEY`) and an unused `sadad_api_key`; one of them was wrong for production.
- Transaction details always hit the LIVE API with `POST getTransaction` and had no timeout, while verification used `GET` and supported sandbox.
- `confirm_order_payment` confirmed **admin-cancelled** orders when Sadad reported success (webhook, callback or admin verify).
- No scheduled expiry or reconciliation: expiry was lazy only, and a paid order with no callback relied on the customer's open tab or an admin click.
- "Pay again" did not check `payment_expires_at` and rebuilt product lines from current prices, so the lines could disagree with `TXN_AMOUNT`.
- Web `cash_pos` orders never expire: they hold seats and count toward the 4-open-orders and 5-per-person limits forever.
- The open-orders count matched the raw phone string, so formatting variations bypassed it.
- Code `4` was treated as failed by the API client but as unknown by the callback normaliser.
- Server and client used two different bank-keyword lists (11 vs 15 entries), and the e-mail function had a third (13).
- Sadad secrets were stored in plain text in `settings`, echoed to the admin browser, and were publicly readable in earlier migrations (rotate them).
- `payment_errors` accepted anonymous inserts with no limits (log flooding).
- `payment_errors` was not in the realtime publication although the UI subscribed to it.
- The `payment_id` unique index could silently degrade to a plain index if legacy duplicates existed.
- QR rendering ran synchronously before the order-creation response (added latency to every checkout).

---

### NTF — Notifications & automation

Transactional e-mails to customers and the admin, delivery tracking, and the n8n automation contract that sends tickets and invoices over WhatsApp (and e-mail) on the club's behalf. Actors: the system (after payment, after a POS sale, on login lockout), admins (resend, relay actions, batch invoice console), n8n (delivery callbacks) and the e-mail provider (bounce, complaint and unsubscribe webhooks). New app: Laravel Mailables (Blade, RTL), the `email_delivery_events` model, a `WhatsAppChannel` driver interface (default driver `N8nWebhookDriver`; a WhatsApp Cloud API driver can be plugged in), queued jobs, and the scheduled command `invoices:auto-send`.

#### E-mail transport and common rules

- **F-NTF-001 · Transactional e-mail sender** — every e-mail goes through one sender service.
  - Original: Lovable managed e-mail API (`@lovable.dev/email-js`, key `LOVABLE_API_KEY`, optional `LOVABLE_SEND_URL`).
  - Constants: site name `qcamelmc`, verified sending subdomain `notify.qcamelmc.org`, From header `qcamelmc <noreply@qcamelmc.org>`, `purpose: "transactional"`, `label = <template name>`.
  - Each message is rendered to HTML **and** plain text. The subject is a function of the template data.
  - Every send carries an **idempotency key**. If the caller gives none, a random UUID is used. Keys in use:
    - `booking-invoice-<orderId>-<attempt>`
    - `payment-failed-<orderId>`
    - `payment-failed-admin-<orderId>`
    - `admin-sale-<orderId>`
    - `login-block-<identifier>-<floor(nowMs / 1 800 000)>` (one per 30-minute bucket)
  - An unknown template throws `Template '<name>' not found. Available: …`. An empty recipient throws.
  - Results: `{sent:true}`. A provider error `recipient_suppressed` → `{sent:false, reason:'recipient_suppressed'}`. Any other error is rethrown.
  - *Rebuild:* Laravel Mail (SMTP/SES/Postmark). Each Mailable implements `ShouldQueue` and adds a header `X-Idempotency-Key`. Dedupe in the DB with a `mail_sends(idempotency_key unique, provider_message_id, …)` table (NEW). Check the suppression list (from F-NTF-020 events) before sending. Configure the `From` name and address through `MAIL_FROM_*`.

- **F-NTF-002 · Common e-mail layout** — every template:
  - `<html lang="ar" dir="rtl">`, width up to 600 px, white background, Arabic copy.
  - Dates are formatted with locale `ar-u-nu-latn` (Arabic month names, Latin digits) in `Asia/Qatar`.
  - Detail rows are two columns: label (muted `#6B6257`, 13 px) and value (ink `#14110E`, 14 px, semibold).
  - Line colour `#EDE6DA`. Currency suffix `ر.ق`. Optional logo from `settings.logo_url`.
  - *Rebuild:* one Blade mail layout `mail.layouts.rtl` with these tokens and the same fonts (`'Segoe UI', Tahoma, Arial, sans-serif` for the invoice; Tahoma for admin and security mails; Arial for payment-failed). Each mail gets an `en` locale variant chosen by the customer's booking locale (NEW).

#### Booking invoice e-mail (customer)

- **F-NTF-003 · Send booking invoice — trigger and access** — original `send-invoice-email`. It is called by:
  - the payment processor after a confirmation (internal call);
  - an admin resend (admin session), with `force` to resend.

  Input `{order_id: string (required), force?: boolean}`. A body parse error is treated as `{}`. Access: internal call or admin; anyone else gets `401 {error:'unauthorized'}`. Missing `order_id` → `400 {error:'order_id is required'}`. Unknown order → `404 {error:'order_not_found'}`.
  - *Rebuild:* the job `SendBookingInvoiceMail(orderId, force)` plus the Filament action `إعادة إرسال الفاتورة` on the order.

- **F-NTF-004 · Invoice send guards** — checked in order:
  1. `payment_status != confirmed` → `{sent:false, reason:'not_confirmed'}` (nothing logged).
  2. The customer e-mail (trimmed) does not match `^[^@\s]+@[^@\s]+\.[^@\s]+$` → log `failed` with detail `لا يوجد بريد إلكتروني صالح للعميل` → `{sent:false, reason:'no_email'}`.
  3. `orders.invoice_email_sent_at` is set and `force` is not given → `{sent:false, reason:'already_sent'}`.

- **F-NTF-005 · Invoice attempt counting and logging** — `attempt = count(email_delivery_events where order_id and status ∈ {queued, retried}) + 1`. Before sending, log `queued` with detail `تمت جدولة الإرسال` (attempt 1), or `retried` with detail `إعادة إرسال — المحاولة رقم <n>`. Send with idempotency key `booking-invoice-<order.id>-<attempt>`.
  - Send throws → log `failed` with the message, return `500 {error}`.
  - Sent → log `sent` with detail `تم الإرسال إلى <email>`, and set `orders.invoice_email_sent_at = now()`.
  - Not sent → log `suppressed` (recipient suppressed) or `failed` with the reason.
  - Response `200 {sent, reason?, invoice_url, attempt}`.

- **F-NTF-006 · Invoice template data** — the template receives:

  | Key | Value |
  |---|---|
  | `booking_reference` | |
  | `customer_name` | or `-` |
  | `customer_phone` | `<country_code><phone>` |
  | `event_title` | or `-` |
  | `event_date` | |
  | `ticket_type` | |
  | `quantity` | |
  | `total_amount` | |
  | `payment_id` | |
  | `payment_method` | |
  | `paid_at` | `confirmed_at` |
  | `logo_url` | |
  | `invoice_url` | `https://qcamelmc.org/invoice/<urlenc booking_reference>` |

  The original hard-coded the site URL.
  - *Rebuild:* `route('invoice.show', $ref)` built from `APP_URL`. Use a signed, long random token instead of the bare reference (NEW; see API defects).

- **F-NTF-007 · Invoice template — subject and preview** — display name `فاتورة الحجز`. Subject `فاتورة حجزك <booking_reference>` (trimmed). Preview text `فاتورة حجزك <booking_reference> — تم تأكيد الدفع`.

- **F-NTF-008 · Invoice template — layout (top to bottom)** —
  1. Optional logo, 48 px high, centred.
  2. Small gold uppercase label `فاتورة الحجز` (gold `#C9A227`, 12 px, letter-spacing 3 px).
  3. H1 with the event title (24 px), then the event date (year, long month, day).
  4. Amount box (1 px line border, **3 px gold top border**, radius 14, background `#FCFAF6`): label `المبلغ المدفوع`, value `<total.toFixed(2)> ر.ق` (30 px bold), and a green pill `تم تأكيد الدفع` (`#1F7A44` on `#E8F5ED`).
  5. Detail card (border, radius 14), rows separated by thin rules:
     - `رقم الحجز`
     - `الاسم`
     - `رقم الهاتف`
     - `نوع التذكرة` (raw value, e.g. `vip`)
     - `عدد التذاكر`
     - `طريقة الدفع` (`cash_pos` → `نقداً / نقطة بيع`, else `سداد`)
     - optional payment-id row, labelled `رقم العملية` (cash_pos) or `رقم عملية سداد`
     - optional `تاريخ الدفع`
  6. If `invoice_url` is set: a gold pill button `عرض الفاتورة أونلاين`, with the hint `اضغط لعرض فاتورتك كاملة أونلاين مع كل التفاصيل ورموز الدخول`.
  7. Note `يرجى الاحتفاظ بهذه الفاتورة. تذاكرك ورمز الدخول تصلك عبر واتساب على الرقم المسجل.`
  8. Footer `qcamelmc — شكراً لثقتكم`.

  Preview data: ref `QC-2026-00123`, `محمد الأنصاري`, `+97455512345`, `سباق الهجن`, 2026-02-14, VIP × 3, 450, `SDD-889231`, sadad, paid 2026-02-01, invoice URL `https://qcamelmc.org/invoice/QC-2026-00123`.
  - *Rebuild:* show the localised ticket-type label (`VIP` / `عادي` / `مواقف`) instead of the raw enum.

#### Payment-failed e-mail (customer + admin copy)

- **F-NTF-009 · Send payment-failed notice — trigger and access** — original `send-payment-failed-email`. It is called by the processor after a failure (F-PAY-039), or by an admin.
  - Input `{order_id?, booking_reference?, error_message?, force?}`. One of id or reference is required, else `400 {error:'order_id or booking_reference is required'}`.
  - Access: internal or admin (`401 {error:'unauthorized'}`). Unknown order → `404 order_not_found`.
  - Order already `confirmed` → `{sent:false, reason:'order_confirmed'}`.
  - Response `200 {sent, reason?, admin_sent: bool}`; uncaught errors → `500 {error}`. Every log row uses `attempt: 1` and template `payment-failed`.

- **F-NTF-010 · Customer part with toggle** — the customer notice is on when `settings.payment_failed_email_enabled !== false` (default true).
  - Disabled → log `skipped` with detail `إشعار فشل الدفع للعميل معطّل من الإعدادات` → `{sent:false, reason:'disabled'}`.
  - Invalid e-mail → log `failed` `لا يوجد بريد إلكتروني صالح للعميل` → `no_email`.
  - A `sent` row already exists for template `payment-failed` on this order and `force` is not set → `already_sent`.
  - Otherwise log `queued` with detail `تمت جدولة إشعار فشل الدفع`, then send with key `payment-failed-<order.id>` (a throw logs `failed` and returns 500), then log `sent` (`تم الإرسال إلى <email>`), `suppressed` or `failed`.

  Admin settings switch (`مفعّل`/`معطّل`), with toasts `تم تفعيل إشعار فشل الدفع للعميل` / `تم إيقاف إشعار فشل الدفع للعميل`. Explanatory text: `عند التفعيل يصل العميل بريد بالعربية يوضح المبلغ ورقم الحجز وسبب الرفض (البنك/البطاقة أو بوابة سداد) مع رابط لإعادة المحاولة ورابط متابعة الحجز، ولا يتكرر الإرسال لنفس الطلب.`

- **F-NTF-011 · Admin copy (always)** — if `settings.admin_email` is a valid address, the same template and data go to the admin with key `payment-failed-admin-<order.id>`. Log `sent` with detail `نسخة للأدمن <email>`, or `failed` with detail `نسخة الأدمن: <reason>`. Errors are swallowed. The copy is attempted on **every** call, and only the provider idempotency key prevents duplicates. Settings field `بريد الإدارة (يستلم نسخة دائماً)` (placeholder `admin@example.com`, regex-validated), help `تصل نسخة من كل إشعار فشل دفع إلى هذا البريد حتى لو كان الإشعار للعميل معطّلاً.`
  - *Rebuild:* dedupe the admin copy in the DB as well (unique idempotency key in `mail_sends`).

- **F-NTF-012 · Payment-failed template data and source label** — data:

  | Key | Value |
  |---|---|
  | `booking_reference` | |
  | `customer_name` | or `-` |
  | `customer_phone` | `<cc><phone>` |
  | `event_title` | or `-` |
  | `event_date` | |
  | `quantity` | |
  | `total_amount` | |
  | `payment_id` | |
  | `error_message` | `reason` or null, where `reason = order.payment_error_reason \|\| body.error_message \|\| ''` |
  | `error_source_label` | `البنك / البطاقة` if the lowercased reason contains any of `card, بطاقة, declin, مرفوض, insufficient, رصيد, bank, بنك, issuer, cvv, authorization, تفويض, منتهية` (13 hints), else `بوابة الدفع سداد` |
  | `logo_url` | |
  | `retry_url` | `https://qcamelmc.org` |
  | `invoice_url` | `https://qcamelmc.org/invoice/<ref>` |

- **F-NTF-013 · Payment-failed template — subject and layout** — display name `فشل الدفع`. Subject `لم تتم عملية الدفع — <booking_reference>`. Preview `لم تتم عملية الدفع للحجز <ref>`. Font Arial. Layout:
  1. Optional logo.
  2. Muted label `حالة الدفع` (letter-spacing 2 px).
  3. H1 with the event title (22 px), then the date.
  4. Red alert box (border and title `#B3261E`, background `#FDF3F2`): title `لم تكتمل عملية الدفع`, the amount `<x.toFixed(2)> ر.ق`, optional `مصدر الرفض: <error_source_label>`, optional error message.
  5. Detail card: `رقم الحجز`, `الاسم`, `رقم الهاتف`, `عدد التذاكر`, optional `رقم عملية سداد`.
  6. If `retry_url`: dark button `إعادة محاولة الحجز` with the hint `لم يتم خصم أي مبلغ. يمكنك المحاولة مرة أخرى ببطاقة أخرى أو التواصل مع البنك المصدر.`
  7. If `invoice_url`: `لمتابعة حالة حجزك في أي وقت: <invoice_url>`.
  8. Footer `qcamelmc — نحن هنا لمساعدتك`.

  Preview data: ref `QTR-3-9-2026-004521`, 2 tickets, 100, error `تم رفض البطاقة من البنك المصدر`, source `البنك / البطاقة`, retry `https://qcamelmc.org`.
  - *Rebuild:* point `retry_url` to the event's ticket page (`/tickets/<event_id>`) instead of the home page.

#### Admin new-sale alert e-mail

- **F-NTF-014 · Admin sale alert — trigger and guards** — original `notify-admin-sale`. It fires for every confirmed sale: online after confirmation (F-PAY-038), and POS right after creation (in the background; an alert failure never fails the sale).
  - Input `{order_id?, booking_reference?, force?}`; one of the two identifiers is required (400). Access: internal or admin (401). Unknown → 404.
  - Guards: not confirmed → `{sent:false, reason:'not_confirmed'}`. `settings.admin_email` invalid → `{sent:false, reason:'no_admin_email'}`. A `sent` row for template `admin-sale-alert` already exists and `force` is not set → `{sent:false, reason:'already_sent'}`.
  - Logging: `queued` with detail `تمت جدولة إشعار البيع للأدمن`; send with key `admin-sale-<order.id>` (a throw → `failed` + 500); then `sent` (`تم الإرسال إلى <admin>`), `suppressed` or `failed`. Recipient = the admin e-mail, attempt 1.
  - Response `200 {sent:true}` or `{sent:false, reason}`.

- **F-NTF-015 · Admin sale alert — data** —

  | Key | Value |
  |---|---|
  | `booking_reference` | |
  | `customer_name` | |
  | `customer_phone` | `<cc><phone>` |
  | `customer_email` | or null |
  | `event_title` | |
  | `event_date` | |
  | `quantity` | |
  | `total_amount` | |
  | `payment_id` | |
  | `payment_method` | |
  | `source_label` | `بيع عبر نقاط البيع` for `cash_pos`, else `دفع إلكتروني (سداد)` |
  | `sold_at` | `confirmed_at \|\| created_at` |
  | `logo_url` | |
  | `invoice_url` | |
  | `holders_summary` | `"<name> (<VIP\|مواقف\|عادي>)"` joined with `، `, or null |

- **F-NTF-016 · Admin sale alert — template** — display name `إشعار الإدارة ببيع جديد`. Subject `بيع جديد — <booking_reference> (<total_amount> ر.ق)`. Preview `عملية بيع جديدة — <ref> بمبلغ <total|-> ر.ق`. Font Tahoma. Layout:
  1. Optional logo (width 80).
  2. H1 `تم تأكيد عملية بيع جديدة` (22 px).
  3. Subtitle `<source_label or 'حجز جديد'> — <qty> تذكرة بمبلغ <total> ر.ق`.
  4. Rows:
     - `رقم الحجز`
     - `اسم العميل`
     - `هاتف العميل`
     - `بريد العميل`
     - `الفعالية`
     - `تاريخ الفعالية` (date + time)
     - `عدد التذاكر`
     - `المبلغ` (`<x> ر.ق`)
     - `طريقة الدفع` (`cash_pos` → `نقدي / نقاط البيع`, else `سداد`)
     - `رقم العملية`
     - `وقت البيع` (date + time, 2-digit hour and minute)
     - optional `حاملو التذاكر`
  5. Optional gold button (`#B08B4F`) `عرض الفاتورة`.
  6. Footer `رسالة تلقائية من نظام إدارة التذاكر.`

#### Login-block security alert e-mail

- **F-NTF-017 · Login-block alert — trigger** — sent when an identifier (an admin e-mail, or the team-passcode login) becomes blocked after **5 failures in 15 minutes** (a 30-minute block). Recipient: `settings.admin_email` (regex-validated; skipped if invalid). Idempotency key `login-block-<identifier>-<30-min bucket>`, so at most one e-mail per identifier per 30 minutes. At the same moment an activity-log entry is written (`activity_type 'login_blocked'`, `user_type` `staff` for passcode or `admin` for an account, `user_identifier`, `action_data {attempts, block_minutes:30}`, IP, user agent). Errors are logged only. The throttling rules themselves are specified in the authentication section.
  - *Rebuild:* raise it from the Laravel login pipeline (Fortify `RateLimiter` lockout event `Illuminate\Auth\Events\Lockout`) and from the staff passcode controller. It must never be triggerable by an unauthenticated "record" endpoint (original defect).

- **F-NTF-018 · Login-block alert — template** — display name `تنبيه محاولات الدخول الفاشلة`. Subject `تنبيه أمني: محاولات دخول فاشلة (<identifier>)`. Preview `محاولات دخول متكررة فاشلة — <identifier>`. Font Tahoma.

  Data:
  - `identifier`
  - `kind_label`: `كلمة مرور الفريق` for passcode, `لوحة التحكم` for an account
  - `attempts`
  - `blocked_minutes` (30)
  - `ip_address`
  - `user_agent`
  - `occurred_at`

  Layout:
  1. H1 (danger `#8E2C2C`) `تنبيه أمني: محاولات دخول متكررة`.
  2. Subtitle `تم إيقاف الدخول مؤقتًا لمدة <blocked_minutes|30> دقيقة بعد <attempts|5> محاولات فاشلة.`
  3. Rows `المُعرّف المستخدم`, `نوع الدخول`, `عدد المحاولات`, `وقت الحظر` (date + time), `عنوان الشبكة`, `الجهاز / المتصفح`.
  4. Footer `رسالة تلقائية من نظام إدارة التذاكر.`

#### Delivery tracking

- **F-NTF-019 · E-mail delivery event log (`email_delivery_events`)** — columns:

  | Column | Notes |
  |---|---|
  | `id` | |
  | `order_id` | FK, cascade |
  | `booking_reference` | |
  | `recipient` | |
  | `template` | default `booking-invoice` |
  | `status` | |
  | `attempt` | default 1 |
  | `detail` | |
  | `created_at` | |

  Indexes `(order_id, created_at DESC)` and `lower(recipient)`. Admins read; the server writes. Log failures are swallowed.

  Status vocabulary and admin labels (timeline badge):

  | Status | Label | Icon | Colour |
  |---|---|---|---|
  | `queued` | `في قائمة الإرسال` | clock | amber |
  | `retried` | `إعادة محاولة` | rotate | blue |
  | `sent` | `تم الإرسال` | mail-check | emerald |
  | `failed` | `فشل الإرسال` | mail-x | red |
  | `suppressed` | `محظور من الاستقبال` | shield-alert | orange |
  | `skipped` | raw | clock | muted |
  | `bounced` | `ارتد البريد` | mail-x | red |
  | `complained` | `بلاغ إزعاج` | shield-alert | red |
  | `unsubscribed` | `إلغاء الاشتراك` | undo | muted |
  | anything else | raw status | clock | muted |

  The admin timeline dialog (in the invoices console) has the title `سجل إرسال الفاتورة بالبريد` and the description `حجز <ref> — <email|بدون بريد>`. Items are newest first, with the time (`ar-u-nu-latn`, Qatar, medium date + short time), `• المحاولة N` when the attempt is above 1, the detail and the recipient (LTR). Empty state: `لم يتم إرسال أي فاتورة بالبريد لهذا الحجز بعد.`
  - *Rebuild:* add `provider_message_id` and `event_id` (provider) columns, cast `status` to a PHP enum, broadcast inserts on the private channel `orders.{id}` (the original UI subscribed, but the table was not in the realtime publication), and give `skipped` a proper label `تم التخطي`.

- **F-NTF-020 · Provider delivery webhook (bounces, complaints, unsubscribes)** — original `handle-email-events`, verified with the provider's webhook signature. Handled events:

  | Provider event | Stored status | Detail |
  |---|---|---|
  | `email.bounced` | `bounced` | `ارتد البريد ولم يصل للمستلم` |
  | `email.complaint` | `complained` | `صنّف المستلم الرسالة كإزعاج` |
  | `email.unsubscribed` | `unsubscribed` | `ألغى المستلم الاشتراك` |

  Recording:
  1. `recipient` = `event.data.recipient`, trimmed.
  2. Dedupe: skip if a row with `detail = "<detail> [<event.event_id>]"` already exists.
  3. Link to an order through the latest row whose recipient matches case-insensitively (copy its `order_id` and `booking_reference`).
  4. Insert `{order_id, booking_reference, recipient, template:'booking-invoice', status, attempt:1, detail:"<detail> [<event_id>]"}`.
  5. An insert error throws, so the provider retries.
  - *Rebuild:* `POST /webhooks/mail/{provider}` (SES SNS / Postmark / Mailgun signature verification). Match on `provider_message_id` to get the real order **and template**. Dedupe on a unique `(provider, provider_event_id)`. Add hard bounces and complaints to a `mail_suppressions` table checked before every send.

- **F-NTF-021 · E-mail template preview** — original `preview-transactional-email`, protected by `Authorization: Bearer <LOVABLE_API_KEY>` (plain comparison). A missing key gives `500 {error:'Server configuration error'}`; a mismatch gives `401 {error:'Unauthorized'}`. It renders every template with its preview data → `200 {templates:[{templateName, displayName, subject, html, status}]}`, where `status` is `ready`, `preview_data_required` (template without preview data; subject and html empty) or `render_failed` (+ `errorMessage`).
  - *Rebuild:* admin-only Filament page `معاينة رسائل البريد` listing the 4 Mailables rendered with fixture data (`Mailable::render()`), and a "send test to me" button (NEW).

#### n8n / WhatsApp automation

- **F-NTF-022 · Automation endpoints configuration** — settings:
  - `webhook_url` + `webhook_enabled` (default true). This is the WhatsApp automation. Card `رابط Webhook واتساب (n8n)`, switch `مفعّل`/`معطّل` (toasts `تم تفعيل ويب هوك واتساب` / `تم تعطيل ويب هوك واتساب`), input placeholder `https://your-n8n-instance.com/webhook/...`, help `سيتم استدعاء هذا الرابط بعد كل حجز ناجح لإرسال الفاتورة عبر واتساب`.
  - `email_webhook_url` + `email_webhook_enabled` (default true). This is the e-mail automation. Card `رابط Webhook البريد الإلكتروني (n8n)` (toasts `تم تفعيل ويب هوك البريد` / `تم تعطيل ويب هوك البريد`), placeholder `https://your-n8n-instance.com/webhook/email...`, help `سيتم استدعاء هذا الرابط لإرسال الفاتورة عبر البريد الإلكتروني (مختلف عن واتساب)`.
  - A disabled flag makes the URL count as **not set**. The e-mail URL falls back to the WhatsApp URL when unset.
  - `admin_phone`: the admin WhatsApp number put into payloads (formatted, default code +974).
  - *Rebuild:* validate the URLs (`url`, `https` required except in local env). Store them encrypted. Never send them to the browser: every outbound call is server-side.

- **F-NTF-023 · Phone and QR conventions for n8n** — n8n expects phone numbers as **digits with the country code and no `+`** (e.g. `97455512345`).
  - The modern builders use `formatPhone(phone, cc = '+974')` = `toSadadMobile` (F-PAY-015); an empty phone → null.
  - The older payloads (batch invoices, admin relay shapes) simply concatenate `country_code` without `+` (default `974`) and the stored phone, with no normalisation.
  - QR picture URL convention: `<public storage base>/qr-codes/<ticket code>.png` (PNG 800 × 800).
  - *Rebuild:* keep a public disk path `storage/qr-codes/<code>.png` so existing n8n templates keep working. Use the `formatPhone` normalisation everywhere (fixes double country codes).

- **F-NTF-024 · Outbound POST helper** — `postWebhook(url, body, timeout = 8 000 ms)`: the URL must match `^https?://`, else `{ok:false, error:"invalid_webhook_url"}`. It POSTs JSON and ignores the response body. Results: `{ok:true, status}`, `{ok:false, status}` or `{ok:false, error}`. It never throws.
  - *Rebuild:* `N8nWebhookDriver::post()` using `Http::timeout(8)->retry(2, 500)`. Log every call (status, duration) to an `automation_calls` table (NEW) for support.

- **F-NTF-025 · Payload: order created (web cash-at-venue only)** — sent in the background right after a **web** order with `payment_method = cash_pos` is created and the WhatsApp webhook is enabled. It is not sent for Sadad orders (they are sent on confirmation) nor for POS orders. Exact shape:
  ```json
  {
    "customer": { "name", "email", "phone": "<formatPhone(customer.phone, customer.country_code)>", "country_code", "nationality", "id_number" },
    "order": { "id", "booking_reference", "total_amount", "payment_method", "payment_status", "event_id" },
    "ticketHolders": [ { "order_id", "name", "phone": "<formatPhone(holder.phone, holder.country_code)>", "country_code", "nationality", "ticket_type", "qr_code": "<ticket code>", "qr_image_url", "qr_code_image": "<public png url|null>", "id_number" } ],
    "bookingReference": "<ref>",
    "adminPhone": "<formatPhone(settings.admin_phone, '+974') | null>",
    "timestamp": "<ISO 8601>"
  }
  ```
  `customer` holds the validated input. Holders are rebuilt from the input by index. A holder's `country_code` = the holder's own code, else a `+code` prefix parsed from the phone (`^\s*(\+\d{1,4})\s`), else the customer's code. Pinned example: `adminPhone '97455500000'`.

- **F-NTF-026 · Payload: payment confirmed (`action: "payment_confirmed"`)** — sent once per confirmation (F-PAY-038), and by the admin relay in notify mode (F-NTF-029). Built entirely from the database:
  ```json
  {
    "...every column of the orders row...": "",
    "customers": { "<customers row>" },
    "events": { "<events row>" },
    "ticket_holders": [ { "...<ticket_holders row>...",
        "ticket_price": "<current tickets.price of that type for the event, or 0>",
        "qr_code": "<qr_image_url if present, else the ticket code>",
        "qr_code_text": "<ticket code>",
        "qr_code_image": "<qr_image_url | null>",
        "phone_international": "<formatPhone(holder.phone, holder.country_code)>" } ],
    "customer": { "<same as customers>" },
    "event": { "<same as events>" },
    "sadad_response": { "transaction_number": "<orders.payment_id>" },
    "action": "payment_confirmed",
    "timestamp": "<ISO 8601>"
  }
  ```
  Holders are sorted by `qr_code`. `sadad_response` is null when there is no `payment_id`. n8n reads `ticket_holders[].qr_code` as the **picture URL** (legacy convention); keep it.
  - *Rebuild:* a `PaymentConfirmedPayload` resource class with a snapshot test that pins every key. Exclude secrets and internal columns (`return_origin`, `last_payment_check_at`) only after confirming that n8n does not read them.

- **F-NTF-027 · Payment-confirmed delivery on confirmation** — the POST is skipped when the WhatsApp webhook is off. Timeout 8 s; the response is ignored. It never runs for `already_confirmed`, `pending` or `review` outcomes.

- **F-NTF-028 · Admin automation relay — access, input and routing** — original `send-to-webhook`. Admin only (401 without a session, 403 for non-admins, 204 for OPTIONS).
  - Input: any JSON **object** up to 200 000 chars. Not an object → `400 invalid_payload "Payload must be a JSON object"`.
  - Routing: `isEmail = payload.action === "send_email"`. The target is the e-mail URL (falling back to the WhatsApp URL) when `isEmail`, else the WhatsApp URL. `send_ticket` goes to the WhatsApp URL.
  - No target → `400 webhook_not_configured` with message `Email webhook URL not configured` / `Webhook URL not configured` and hint `Please configure the email webhook URL in admin settings` / `Please configure the webhook URL in admin settings`.
  - The call is a POST of JSON with a **10 000 ms** timeout, reading the response text.

- **F-NTF-029 · Relay notify mode** — body `{"notify":"payment_confirmed","booking_reference":"<ref>"}`. Reference fails the regex → `400 invalid_reference`. Unknown → `404 order_not_found "Booking not found"`. The outgoing body is the server-built `payment_confirmed` payload (F-NTF-026); data from the browser is ignored.

- **F-NTF-030 · Relay passthrough shape 1 — admin confirmed an order** (orders screen, after the admin sets status confirmed): the full order row with `customers(*)`, `events(*)`, `ticket_holders(*)`, plus `action:"payment_confirmed"` and `timestamp`.
  - *Rebuild:* replace it with notify mode (server-built).

- **F-NTF-031 · Relay passthrough shape 2 — WhatsApp invoice** (invoices console): `{order_id, booking_reference, customer_name, customer_phone, customer_phone_whatsapp, customer_email, nationality, ticket_type, quantity, total_quantity, total_amount, payment_status, qr_codes[], ticket_types[], event_title, event_date, event_location, created_at, customers{...}}`.
  - `total_quantity` = the sum of quantities in the current batch (or `order.quantity` for a single send).
  - `qr_codes[]`: per holder, the `qr_code` if it starts with `http`, else `<storage>/qr-codes/<qr_code>.png`.
  - `customer_phone_whatsapp` = `country_code` without `+` (default `974`) + phone.
  - A missing customer is replaced with `{name:'غير معروف', email:'', phone:'', country_code:'+974', nationality:null}`.

- **F-NTF-032 · Relay passthrough shape 3 — e-mail invoice** (invoices console, mail icon `إرسال الفاتورة بالبريد`): the same as shape 2 **minus** `total_quantity`, **plus** `action:"send_email"` as the first key. It is routed to the e-mail webhook.

- **F-NTF-033 · Relay passthrough shape 4 — send all tickets of a booking** (customers screen): `{booking_reference, event_title, event_location, event_date, customer_name, customer_phone (without +code), customer_phone_whatsapp, nationality, quantity, qr_codes[], ticket_types[], total_amount, payment_status, timestamp}`.

- **F-NTF-034 · Relay passthrough shape 5 — send one ticket** (customers screen / ticket viewer): `{booking_reference, event_title, event_location?, event_date, ticket_count?: 1, holder:{name, phone, country_code (digits), nationality, id_number, ticket_type, ticket_price, qr_code, qr_code_image, is_present}, timestamp}`.

- **F-NTF-035 · Shape 6 — `send_ticket` (direct from the browser in the original)** — `{customer, order:{id, booking_reference, ticket_type, quantity, total_amount, payment_status}, ticketHolders:[...], bookingReference, adminPhone, timestamp, action:"send_ticket"}`. The original POSTed this **directly from the admin browser** to `webhook_url`, bypassing the relay and exposing the URL.
  - *Rebuild:* route it through the server relay.
  - *Rebuild (all shapes):* the browser sends only `{action, order_id, holder_id?}`; the server builds each shape from the database with dedicated payload classes that keep **exactly** the keys above, so n8n workflows keep working.

- **F-NTF-036 · Relay response handling and "sending…" marker** —
  - Upstream 2xx: `pendingMessage` = `جاري إرسال البريد الإلكتروني...` (e-mail) or `جاري الإرسال إلى واتساب...` (WhatsApp). If the payload carries a string `order_id` (preferred) or `booking_reference`, mark the order: `n8n_response_message = pendingMessage`, `n8n_responded_at = NULL` (errors only logged). Return `200 {success:true, message: pendingMessage, response: <upstream text>}`. If the automation refused, nothing is marked.
  - Upstream 404 → `200 {error:"Webhook not found or inactive", details:<parsed JSON or text>, status:404, solution:"Please ACTIVATE the workflow in n8n (not just test mode)."}`.
  - Other non-2xx → `200 {error:"Webhook returned error", details, status}`.
  - Timeout → `200 {error:"Webhook request timed out", details:"The webhook did not respond in time"}`.
  - Network error → `200 {error:"Webhook request failed", details:"Could not reach the webhook"}`.

  Upstream problems are HTTP 200 on purpose, so the admin screen can show them. Admin toast mapping: status 404 → `Webhook غير موجود أو غير مفعل في n8n. فعّل الـ workflow (Activate) أو اضغط Execute workflow إذا كنت تستخدم Test Webhook.`; otherwise `خطأ من n8n: <error — hint|solution>`.

- **F-NTF-037 · n8n delivery callback (`n8n-response`)** — `POST /webhooks/n8n/response`. n8n reports the result of a WhatsApp or e-mail send.
  - Auth: header `x-webhook-secret: <N8N_RESPONSE_SECRET>` or `Authorization: Bearer <secret>`, compared constant-time (lengths must match), else `401 {success:false, error:'unauthorized'}`. In the original an **unset** secret meant the endpoint was open.
  - Input `{booking_reference: string (required), message: string (required, truncated to 500 chars), status?: 'success'|'failed'}` (status ignored in the original). Missing fields → `400 {success:false, error:'Missing required fields: booking_reference and message'}`.
  - Effect: `orders.n8n_response_message = message`, `n8n_responded_at = now()` by booking reference. Success → `200 {success:true, message:'Order updated successfully', order_id, booking_reference}`. DB error → `500 {success:false, error:'Failed to update order', details}`. The intended `404 {success:false, error:'Order not found with booking reference: <ref>'}` was unreachable in the original (it returned 500).
  - *Rebuild:* the secret is **mandatory** (the app refuses to boot the route without it). Return a real 404. Store `status` in a new `n8n_response_status` column (`success|failed`). Rate-limit to 120 per minute. Broadcast `OrderAutomationUpdated` on Reverb.

- **F-NTF-038 · Automation status markers on orders** — `orders.n8n_response_message` / `n8n_responded_at` / `send_attempt_count` carry the automation state shown in the admin screens. Values written by the system:

  | Message | `n8n_responded_at` | Written by |
  |---|---|---|
  | `طلب من نقطة البيع - POS` | now | POS order creation |
  | `جاري الإرسال إلى واتساب...` | NULL | relay, WhatsApp, upstream OK |
  | `جاري إرسال البريد الإلكتروني...` | NULL | relay, e-mail, upstream OK |
  | n8n's message (≤ 500 chars) | now | n8n-response |
  | `response.message`, or `تم الإرسال تلقائياً` | now | server batch sender, on success |
  | `تم التسجيل يدوياً ✓` | now | gate admit, only when `n8n_responded_at` was null |
  | `تم التحديد كمرسل يدويًا` | now | admin "mark all as sent" |
  | NULL | NULL | admin "reset status" (`إعادة`) |

  A "pending message" = NULL, or exactly one of the two "sending…" strings. Channel detection for the admin badge: the message contains `اميل`, `إيميل`, `بريد`, `email`, `Email` or `جاري إرسال البريد` → e-mail (`📧 تم الإرسال بالإيميل`); it contains `واتساب`, `whatsapp` or `WhatsApp` → WhatsApp (`💬 تم الإرسال بالواتساب`); otherwise `تم الإرسال`.
  - *Rebuild:* add an `automation_status` enum column (`none, sending_whatsapp, sending_email, delivered, failed, manual, pos, checked_in`) and derive the badge from it. Keep the message text for display.

#### Automatic invoice sending (batch engine)

- **F-NTF-039 · Invoice batch engine — settings** —

  | Setting | Default | Meaning |
  |---|---|---|
  | `auto_invoice_interval_seconds` | 60 (settings UI minimum 10) | Seconds between batches |
  | `invoice_batch_min` | 1 (≥ 1) | Minimum batch size |
  | `invoice_batch_max` | 10 (≥ min) | Maximum batch size |
  | `invoice_send_delay_min` | 300 s (≥ 1) | Lower bound of the random delay **between consecutive sends inside a batch** |
  | `invoice_send_delay_max` | 600 s (≥ min) | Upper bound of that delay |
  | `last_invoice_sent_at` | — | Written after each batch |

  Validation messages in settings: delay min < 1 → `يجب أن يكون الحد الأدنى ثانية واحدة على الأقل`; max < min → the same message as for the batch range. Defaults are applied with `||`, so 0 falls back to the default.

- **F-NTF-040 · Batch engine — candidate selection** — orders with `payment_method = sadad` AND `payment_status = confirmed` AND a **pending message** (F-NTF-038).
  - The server job used `n8n_response_message IS NULL`, oldest first, limit `batchMax × 2`.
  - The admin console used newest first, and excluded orders already in its in-memory "sent this session" map.
  - Excluded:
    - **finished events**: the event date (Qatar) is before Qatar-today, or it is the same day and the Qatar hour ≥ **18**;
    - **on-hold** orders (F-NTF-042);
    - (original only) orders created on 2025-11-06/07/08 (a hard-coded legacy exclusion).
  - POS orders never appear.

- **F-NTF-041 · Batch engine — cadence and size** —
  - Server job: if `last_invoice_sent_at` is set and less than `interval` seconds have passed → `200 {message:"Interval not reached yet", seconds_remaining: round(interval − elapsed)}`. No webhook URL → `{message:"No webhook URL configured"}`. No candidates → `{message:"No orders to send"}`.
  - Batch size = `min(batchMax, max(batchMin, candidates))`, taking the first N.
  - Sends are **sequential**. Between consecutive orders (not after the last) the console waited a random `floor(random × (maxMs − minMs + 1)) + minMs` ms, with the toast `انتظار {s} ثانية قبل إرسال الفاتورة التالية...`. The server job had no delay.
  - After the batch, `settings.last_invoice_sent_at = now()`, even if every send failed.
  - Server response `200 {success:true, message:"Sent X/N invoices successfully", batch_size, total_quantity, results:[{order_id, booking_reference, success}], next_send_in_seconds: interval}`.

- **F-NTF-042 · Batch engine — attempts and hold after 2** — `orders.send_attempt_count` (default 0) counts send attempts. Batch mode increments it **before** each send; a single manual send increments it only **on failure**. **On hold** = `send_attempt_count ≥ 2` AND (pending message OR `n8n_responded_at` NULL). On-hold orders are excluded from batches and single sends until an admin clicks `إعادة المحاولة` (sets the count to 0, toast `تم إعادة تعيين عدد المحاولات - يمكن إعادة الإرسال`).

  Toasts:
  - attempt ≥ 2 on success: `الفاتورة لـ {name} - المحاولة {n} (ستوضع في الانتظار إذا لم تنجح)`
  - failure: `فشل إرسال الفاتورة لـ {name} - تم وضعها في الانتظار (محاولة {n})` (≥ 2) or `فشل إرسال الفاتورة لـ {name} (محاولة {n} من 2)`

  In the original, "actual success" (reset the count to 0) required the returned message to contain `تم إرسال` or `بنجاح` and not `جاري الإرسال`. That never happened, so every order went on hold after its 2nd batch send unless n8n answered.
  - *Rebuild:* reset the count to 0 when n8n-response reports success, or when the upstream returns 2xx and the order receives a non-pending message.

- **F-NTF-043 · Batch engine — per-order send and success rule (server)** — the payload per order is shape 2 (F-NTF-031) **without** `order_id` and `customers`; `total_quantity` is the batch total. It is POSTed as JSON to the WhatsApp webhook. The response JSON is parsed (fallback `{message:"No JSON response"}`). Success = HTTP 2xx AND `response.success !== false`. On success: `n8n_response_message = response.message || 'تم الإرسال تلقائياً'`, `n8n_responded_at = now()`.

- **F-NTF-044 · Batch console scheduler (admin UI)** — page `إرسال الفواتير (يدوي)`, subtitle `إرسال يدوي للفواتير - قم بتفعيل العد التنازلي للإرسال التلقائي`.
  - Countdown box: `العد التنازلي نشط ⏱️` / `العد التنازلي متوقف ⏸️`.
  - Buttons: `تفعيل العد التنازلي` (toast `بدأ العد التنازلي - {interval} ثانية`) / `إيقاف العد التنازلي` (toast `تم إيقاف العد التنازلي`); `إرسال (<pending>)`, which shows `جاري الإرسال...` while running.
  - Batch toasts: `سيتم إرسال {n} فاتورة من أصل {total}`, `إرسال فاتورة {i+1} من {n}...`, `تم الانتهاء من إرسال جميع الفواتير!`. Empty → `جميع الطلبات تم إرسالها بالفعل`. No URL → `لم يتم تكوين رابط الويب هوك. يرجى تحديثه في الإعدادات`.
  - The countdown ran **in the admin browser**: ticking every 1 s while active, not sending and pending > 0; at 0 it ran a batch and restarted. Its active flag was persisted in `localStorage invoiceCountdownActive`. (The full console UI — tabs, columns, badges, reset and mark-all actions — is specified in the admin finance section.)
  - *Rebuild:* the console only toggles a server-side setting `invoice_auto_send_enabled` (NEW) and shows the next run time; the browser never runs the loop.

- **F-NTF-045 · Server scheduler for the batch engine** — original: `auto-send-invoices`, triggered externally (dashboard cron or n8n), with **no authentication** and no lock.
  - *Rebuild:* scheduled command `invoices:auto-send`, run every minute with `withoutOverlapping()->onOneServer()`. It honours `invoice_auto_send_enabled` and `webhook_enabled` and the interval (`last_invoice_sent_at`). It selects candidates with `lockForUpdate()->skipLocked()`, marks each order "sending" (and increments its attempts) **before** dispatching, and dispatches one `SendWhatsAppInvoice` job per order using `->delay(now()->addSeconds(random_int(delayMin, delayMax) × i))` to reproduce the spacing. It skips finished events (18:00 Qatar rule, computed with Carbon in `Asia/Qatar`) and on-hold orders. The legacy November-2025 exclusion becomes an optional "exclude orders created on these dates" list in settings (default empty). The admin "send now" button dispatches the same command.

#### Upgrades (new, not in original)
- NEW: `mail_sends` table (idempotency key, provider message id, template, order, status) and `mail_suppressions`; check suppression before every send.
- NEW: English variants of all four Mailables, chosen by the booking locale.
- NEW: "send test e-mail" on the preview page.
- NEW: `automation_calls` log of every outbound n8n POST (URL host, status, ms, payload hash).
- NEW: `WhatsAppChannel` interface with drivers `n8n` (default, identical payloads) and `cloud_api` (Meta WhatsApp Cloud API templates).
- NEW: `n8n_response_status` and an `automation_status` enum on orders.
- NEW: server-side toggle `invoice_auto_send_enabled` replacing the browser countdown.

**Original defects to avoid:**
- `auto-send-invoices` had no authentication and no lock (anyone could trigger sends; concurrent runs double-sent). It ignored `webhook_enabled`, had no fetch timeout, and did its "Qatar" date math with UTC Date getters.
- The batch scheduler with its 5–10-minute sleeps ran in the admin's browser tab: closing the tab stopped it and two tabs double-sent.
- The attempt-reset heuristic never matched, so delivered orders went on hold after 2 sends.
- Hard-coded exclusions: orders created on 6/7/8 November 2025, and the 18:00 event-finished rule (make the cutoff configurable, default 18:00).
- `customer_phone_whatsapp` simply concatenated the country code and the phone, so numbers already prefixed got a double code.
- QR URLs in batch payloads were rebuilt from the code and ignored the stored `qr_image_url`.
- `send_ticket` was posted directly from the browser, and the webhook URL (and Sadad secrets) were readable by the admin browser.
- `n8n-response` was open when `N8N_RESPONSE_SECRET` was unset; an unknown reference returned 500 instead of 404; `status` was ignored.
- `handle-email-events` stored every bounce/complaint with `template='booking-invoice'`, linked to orders through a case-insensitive pattern match on the raw recipient (`%`/`_` act as wildcards), and deduplicated through a text match on `detail`.
- The payment-failed admin copy was re-attempted on every call (only provider idempotency prevented duplicates).
- Three different "bank" keyword lists existed (11, 13, 15 entries).
- Invoice and retry URLs hard-coded `https://qcamelmc.org`.
- `email_delivery_events` and `payment_errors` were not in the realtime publication although the admin UI subscribed to them.
- The login-alert e-mail could be triggered by anyone through the unauthenticated `login-guard` "record" mode (alert spam and admin lockout).

---

### API — Integrations & APIs

This module covers the machine-facing surface of the app: the read-only MCP server for AI assistants, the HTTP conventions shared by all endpoints, the mapping of every original edge function to its Laravel equivalent, and the environment variables. New app: `laravel/mcp` server at `/mcp`, JSON controllers under `routes/api.php` (some public, some session- or token-protected), webhooks under `/webhooks/*` and `/payments/sadad/*`, plus queued jobs and scheduled commands.

#### MCP server (read-only AI tools)

- **F-API-001 · MCP server identity and access** — Model Context Protocol server (Streamable HTTP).
  - Identity: name and title `qcamelmc`, version `0.1.0`.
  - Instructions text (verbatim): "Read-only tools for the qcamelmc ticketing system. Use `list_events` to find an event id, `event_sales_summary` for sales, revenue and gate attendance of that event, `find_booking` to look up a booking by its reference, and `search_customers` to find a customer by name, phone or email. All data is scoped to the signed-in admin account."
  - Original auth: OAuth 2 bearer issued by the platform auth server (issuer `https://<project>.supabase.co/auth/v1`, audience `authenticated`). Every tool ran its queries **as the user**, so row-level security decided visibility (admin-only policies). Every tool first checks authentication and throws `Not authenticated` otherwise. DB errors become tool errors carrying the message.
  - All tools are annotated `readOnlyHint: true`, `idempotentHint: true`, `openWorldHint: false`, with `additionalProperties: false` and no output schema.
  - *Rebuild:* `laravel/mcp` web server at `POST /mcp`, protected by Passport OAuth (or Sanctum tokens with ability `mcp:read`) plus `can:viewReports` (roles super_admin, admin, accountant). Each tool authorises through policies, never through raw queries. Throttle at 60 calls per minute per token.

- **F-API-002 · Tool `list_events`** — title `List events`, description "List the ticketing events (title, date, location, capacity status), newest first."
  - Input (both required): `include_archived: boolean` ("Include archived (past season) events as well."), `limit: integer 1..50`.
  - Query: events `id, title, event_date, end_date, location, is_active, is_archived` ordered by `event_date DESC`, limited to N, filtered on `is_archived = false` unless `include_archived`.
  - Text output: lines `<title> — <event_date|?> (<id>)`, or `No events found.`
  - Structured output: `{events:[{id, title, event_date|null, end_date|null, location|null, is_active, is_archived}]}`.

- **F-API-003 · Tool `event_sales_summary`** — title `Event sales summary`, description "Ticket sales and gate attendance for one event: tickets sold per type, revenue, capacity left and how many holders have been scanned in."
  - Input: `event_id: uuid` ("Event id, as returned by list_events. Pass the current event when unsure."). Unknown → `Event not found`.
  - Data: tickets `type, price, available_quantity`; **confirmed** orders `id, payment_method`; their holders (in chunks of 200 order ids) `order_id, ticket_type, is_present`.
  - Per type (`ticket_type || "normal"`): `{type, sold, scanned, price, revenue = sold × price, capacity = available_quantity, remaining = max(0, capacity − sold)}`.
  - Summary: `{event:{id, title, event_date}, orders_confirmed, tickets_sold, tickets_scanned, tickets_sold_online (orders with method sadad), tickets_sold_at_counter (all others), revenue_qar = Σ revenue, ticket_types:[...]}`.
  - Text: `<title>: <sold> tickets sold, <scanned> scanned in, <revenue> QAR.`
  - *Rebuild:* compute revenue from stored order totals (or `order_items` unit prices), not current ticket prices, and report `remaining` from `available_quantity − sold_quantity` (which includes pending holds). Add the field names `revenue_qar_at_current_prices` only if needed for backward compatibility.

- **F-API-004 · Tool `find_booking`** — title `Find a booking`, description "Look up one booking by its reference (for example POS-21-09-2026-UY956SR9R) and return the customer, payment status and every ticket holder with their check-in state."
  - Input: `booking_reference: string`, trimmed, minimum 4 characters. Unknown → `No booking found with that reference`.
  - Structured output: `{booking:{booking_reference, payment_status, payment_method, total_amount, created_at, customer:{name, phone, email}, event:{title, event_date}, holders:[{name, phone, nationality, ticket_type, checked_in, checked_in_at, checked_in_by}]}}`. The holder fields come from `is_present`, `confirmed_at` and `confirmed_by_name`.
  - Text: `<ref> — <customer|?> — <n> ticket(s), payment <status>.`

- **F-API-005 · Tool `search_customers`** — title `Search customers`, description "Search customers by part of their name, phone number or email address, and see how many bookings each one has."
  - Input (both required): `query: string`, trimmed, minimum 2; `limit: integer 1..25`.
  - Search: replace `%` and `,` in the query with spaces, then match `name|phone|email` case-insensitively against `%term%`, ordered by `created_at DESC`, limited to N. For each match, count its orders.
  - Structured output: `{customers:[{id, name, phone, email, nationality|null, bookings}]}`.
  - Text: lines `<name> — <phone> (<n> bookings)`, or `No matching customers.`
  - *Rebuild:* escape `_` as well as `%` in the LIKE pattern, and group results by normalised phone (customers were one row per order in the original).

#### HTTP conventions

- **F-API-006 · CORS policy** — the original was open on purpose because auth was by bearer tokens and never by cookies. Headers: `Access-Control-Allow-Origin: *`, `Access-Control-Allow-Headers: authorization, x-client-info, apikey, content-type`, `Access-Control-Allow-Methods: POST, GET, OPTIONS`, `Access-Control-Max-Age: 86400`. `OPTIONS` → `204` with an empty body (older functions answered `200 "ok"`).
  - *Rebuild:* the web app is same-origin (session + CSRF), so no CORS is needed for it. `config/cors.php` allows `*` only on `api/v1/*` (Sanctum bearer, no credentials) and `/mcp`. Webhook routes need none.

- **F-API-007 · Error envelope** — the standard JSON error is `{ "success": false, "code": "<machine_code>", "error": "<human message>", ...extra }` with the matching HTTP status. Unhandled exceptions → `500 {success:false, code:"internal_error", error:"Something went wrong"}`; the stack is logged and never returned. Codes in use:
  - `invalid_json` (400)
  - `payload_too_large` (413)
  - `validation_failed` (400, + `errors: string[]` listing **all** failures at once)
  - `invalid_reference` (400)
  - `invalid_payload` (400)
  - `invalid_request` (400)
  - `unauthorized` (401)
  - `forbidden` (403)
  - `method_not_allowed` (405 `Use POST`)
  - `order_not_found` (404)
  - `not_payable` (409)
  - `event_not_available` (409)
  - `insufficient_stock` (409, + `ticket_type`)
  - `quantity_limit_exceeded`, `below_minimum_amount`, `invalid_tickets`, `holders_mismatch`, `ticket_limit_exceeded` (422; the last carries the Arabic DB message)
  - `too_many_pending_orders` (429)
  - `payment_unavailable` (502/503)
  - `webhook_not_configured` (400, + `hint`)

  The staff "not authorised" shape was `401 {"success":false,"error":"unauthorized","message":"غير مصرح"}`.
  - *Rebuild:* a single `ApiException` renderer in `bootstrap/app.php` (`->withExceptions`) that maps `ValidationException` → `validation_failed` with a flat `errors[]` and keeps every code above. The frontend maps codes to the Arabic messages specified in the public booking section.

- **F-API-008 · Request body limits** — JSON bodies are capped at **64 000** characters (`413 payload_too_large "Request body is too large"`). Invalid JSON → `400 invalid_json "Request body must be valid JSON"`. Exceptions: the admin relay allows 200 000 chars; receipt OCR images allow 8 000 000 base64 chars (`413 {error:"Image is too large"}`).
  - *Rebuild:* middleware `LimitJsonBody:64000`, with per-route overrides.

- **F-API-009 · Cache headers on payment responses** — order creation, pay-again and the callback redirect all send `Cache-Control: no-store`. Keep this, and add it to the status lookup and verify endpoints.

- **F-API-010 · Caller identity model for endpoints** — four kinds of caller:
  - **admin**: a signed-in account with the admin role (the original accepted either an `admin_users` row or the role `admin`).
  - **moderator**: a staff account.
  - **passcode staff**: the team passcode, sent in the body; it must be at least 8 chars, is never the revoked `@@@Qatar123`, and is compared constant-time.
  - **internal**: function-to-function calls with the service key.

  "Staff" = admin, moderator or passcode. An auth lookup failure never grants access.
  - *Rebuild:* session guard + spatie roles. The passcode exchange issues a short-lived signed staff token (Sanctum ability `staff:passcode`, 12 h) instead of resending the secret with every call. Internal calls become direct service or job invocations (no HTTP).

- **F-API-011 · Background execution** — the original answered first and continued afterwards (`waitUntil`) for QR rendering of POS orders, n8n posts, e-mails and payment side effects. Internal function calls were HTTP POSTs to `/functions/v1/<name>` with the service key, a 15 s timeout, and a throw on non-2xx.
  - *Rebuild:* Horizon queues `payments` (high priority), `notifications`, `automation`, `default`. Every job is idempotent and `ShouldBeUnique` on its business key.

- **F-API-012 · Inbound webhook security** —
  - Sadad callback and webhook: no signature is trusted; verification goes through the merchant API (F-PAY-033).
  - n8n-response: shared secret in a header (mandatory in the rebuild).
  - E-mail provider: provider signature.
  - Template preview: bearer key → admin session in the rebuild.

  All webhook routes are CSRF-exempt, rate-limited (Sadad 120 per minute, n8n 120 per minute, mail 300 per minute) and logged.

#### Internal endpoints catalogue (original edge function → Laravel equivalent)

| # | Original function | Original access | Laravel equivalent |
|---|---|---|---|
| 1 | `create-order` | public (web) / staff (pos) | `POST /api/orders` → `OrderController@store` → `OrderService::create()` (transaction + locks); POS via `POST /staff/pos/orders` (Livewire action, staff session); jobs `RenderTicketQr`, `PostOrderCreatedToN8n`, `SendAdminSaleAlert` |
| 2 | `sadad-payment` | public (reference) | `POST /api/payments/sadad/restart` → `SadadCheckoutController@restart` |
| 3 | `sadad-callback` | none (browser) | `GET\|POST /payments/sadad/callback` (+ alias `/sadad-callback`) → `SadadCallbackController` |
| 4 | `sadad-webhook` | none | `POST /payments/sadad/webhook` → `SadadWebhookController` → job `ProcessSadadNotification` |
| 5 | `verify-payment` | public (throttled) / admin | `POST /api/payments/verify` → `PaymentVerificationController`; Filament action `تحقق من سداد` |
| 6 | `sadad-diagnose` | admin | Filament action on the Integrations settings page → `SadadGateway::diagnose()`; also `php artisan sadad:diagnose` |
| 7 | `sadad-transaction` | admin | Filament action/modal `تفاصيل عملية سداد` → `MerchantApiClient::transactionDetails()` |
| 8 | `recover-pending-orders` | none (stub) | dropped → scheduled `payments:reconcile` (every 5 min) |
| 9 | `auto-send-invoices` | none (!) | scheduled `invoices:auto-send` (every minute) → job `SendWhatsAppInvoice` |
| 10 | `send-invoice-email` | internal / admin | job `SendBookingInvoiceMail` + Filament action `إعادة إرسال الفاتورة بالبريد` |
| 11 | `send-payment-failed-email` | internal / admin | job `SendPaymentFailedMail` (listener of `PaymentFailed`) |
| 12 | `notify-admin-sale` | internal / admin | job `SendAdminSaleAlert` (listener of `PaymentConfirmed` / `PosSaleCompleted`) |
| 13 | `handle-email-events` | provider signature | `POST /webhooks/mail/{provider}` → `MailEventWebhookController` |
| 14 | `preview-transactional-email` | bearer key | Filament page `معاينة رسائل البريد` (admin) |
| 15 | `send-to-webhook` | admin | Filament actions → `AutomationRelay::send(action, order, holder?)` (server-built payloads) |
| 16 | `n8n-response` | optional secret | `POST /webhooks/n8n/response` (mandatory secret) → `N8nResponseController` |
| 17 | `generate-qr-code` | admin | Filament action "generate QR image" → `QrImageService::render(text, filename)` |
| 18 | `backfill-qr-codes` | admin | `php artisan tickets:backfill-qr --batch=100` + Filament action → job `BackfillQrImages` |
| 19 | `regenerate-booking-qr-codes` | admin | Filament order action → job `RegenerateBookingQr(ref)` |
| 20 | `change-order-event` | admin | Filament order action `تغيير الفعالية` → `OrderService::moveToEvent()` (single transaction) |
| 21 | `staff-auth` | none (it is the login) | `POST /staff/passcode` → `StaffPasscodeController` (RateLimiter per IP 5/15 min → 30 min; global 60/15 min → 15 min) issuing a staff token |
| 22 | `login-guard` | none (!) | removed as an endpoint → Fortify login pipeline `RateLimiter` (5/15 min → 30 min) + `Lockout` listener (alert mail + activity log) |
| 23 | `manage-staff-users` | admin | Filament `UserResource` (create, role, revoke, reset password) |
| 24 | `staff-customer-lookup` | staff | `GET /staff/customers/lookup?term=` (Livewire, ≥ 3 chars, 8 results; national ID hidden for passcode staff) |
| 25 | `staff-attendance` | staff read / account write | Livewire `StaffAttendanceBoard` + `AttendanceService` |
| 26 | `gate-stats` | staff | Livewire `GateBoard` (`wire:poll.15s`) + Reverb broadcasts → `GateStatsQuery` |
| 27 | `ticket-checkin` | staff (reset: admin) | `POST /staff/scanner/{checkin,lookup,search,manual,history}`, `POST /admin/checkins/{holder}/reset` → `CheckInService` |
| 28 | `parse-pos-receipt` | staff | `POST /staff/pos/receipts/parse` → `ReceiptOcrService` (Prism vision LLM, timeout 30 s, 10/min per staff) |
| 29 | `mcp` | OAuth | `POST /mcp` (`laravel/mcp`, F-API-001..005) |

Database RPCs with HTTP-visible behaviour (implemented as services): `get_order_status` → `GET /api/orders/status`; `get_public_order` → invoice page controller `GET /invoice/{token}`; `release_expired_holds` → called by the availability query + scheduled `orders:expire-holds`; `reserve_tickets` (read-only availability) → `GET /api/events/{event}/availability`; `cancel_order` / `recount_ticket_stock` → Filament actions; `cleanup_login_attempts` → scheduled `model:prune` daily (rows > 7 days).

#### Environment variables (Laravel names)

| Variable | Purpose | Original source |
|---|---|---|
| `APP_URL` | public base URL; builds the callback, webhook, invoice and QR URLs | `SUPABASE_URL` + hard-coded `https://qcamelmc.org` |
| `APP_TIMEZONE` | `UTC` (storage); business TZ via `APP_BUSINESS_TIMEZONE=Asia/Qatar` | runtime UTC + hard-coded `Asia/Qatar` |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `ar` / `en` | i18n default |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL 8 | `SUPABASE_SERVICE_ROLE_KEY` (service DB client) |
| `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` | cache, locks, rate limits, queues | — |
| `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` | Horizon | `EdgeRuntime.waitUntil` |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT` | realtime | Supabase realtime |
| `SADAD_MERCHANT_ID` | Sadad ID | `settings.sadad_merchant_id` |
| `SADAD_CHECKOUT_SECRET` | request signature / checksum | `settings.sadad_secret` |
| `SADAD_API_LOGIN_SECRET` | merchant API login secret (falls back to the checkout secret) | env `SADAD_API_SECRET_KEY` / `settings.sadad_secret` |
| `SADAD_WEBSITE_DOMAIN` | registered domain | `settings.sadad_website_domain` |
| `SADAD_ENV` | `auto\|sandbox\|live` | `settings.sadad_environment` |
| `SADAD_CHECKOUT_URL` | default `https://sadadqa.com/webpurchase` | hard-coded |
| `SADAD_API_LIVE_URL` / `SADAD_API_SANDBOX_URL` | `https://api-s.sadad.qa` / `https://api-sandbox.sadad.qa` | hard-coded |
| `SADAD_HTTP_TIMEOUT` | 8 (seconds) | hard-coded 8 000 ms |
| `PAYMENT_HOLD_MINUTES` | 30 | DB constant |
| `PAYMENT_MIN_ONLINE_AMOUNT` | 3.00 | DB constant |
| `FRONTEND_URL` (optional) | customer return base after payment | `settings.site_url` |
| `N8N_WEBHOOK_URL` / `N8N_EMAIL_WEBHOOK_URL` | defaults for the automation endpoints (settings override) | `settings.webhook_url` / `email_webhook_url` |
| `N8N_RESPONSE_SECRET` | **mandatory** shared secret for n8n callbacks | env `N8N_RESPONSE_SECRET` (optional) |
| `N8N_HTTP_TIMEOUT` | 8 (system posts) / relay 10 | hard-coded |
| `WHATSAPP_DRIVER` | `n8n` (default) or `cloud_api` | — |
| `WHATSAPP_CLOUD_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID` | only for the `cloud_api` driver | — |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS=noreply@qcamelmc.org`, `MAIL_FROM_NAME=qcamelmc` | transactional e-mail | `LOVABLE_API_KEY`, `LOVABLE_SEND_URL`, sender `notify.qcamelmc.org` |
| `MAIL_WEBHOOK_SECRET` (or `POSTMARK_WEBHOOK_TOKEN` / SES SNS topic ARN) | provider delivery webhook verification | `LOVABLE_API_KEY` |
| `STAFF_PASSCODE_HASH` | team passcode (bcrypt hash; ≥ 8 chars; deny-list `@@@Qatar123`) | env `STAFF_PASSCODE` (plain) |
| `AI_PROVIDER`, `AI_MODEL`, `AI_API_KEY` | receipt OCR via Prism (e.g. `gemini` / `gemini-2.5-flash`) | `LOVABLE_API_KEY`, gateway `https://ai.gateway.lovable.dev/v1/chat/completions`, model `google/gemini-2.5-flash` |
| `QR_DISK` (`public`), `QR_SIZE=800`, `QR_MARGIN=2`, `QR_ECC=M` | ticket QR images | bucket `qr-codes`, `qrcode@1.5.4`, fallback `https://api.qrserver.com/v1/create-qr-code/` |
| `MCP_ENABLED`, `PASSPORT_*` | MCP OAuth | OAuth issuer `https://<project>.supabase.co/auth/v1` |
| `CLARITY_PROJECT_ID` | analytics (settings override) | `settings.clarity_project_id` |
| `SENTRY_LARAVEL_DSN` | error tracking | — |

Settings that stay **in the database** (admin-editable via spatie/laravel-settings): webhook URLs and enabled flags, `admin_phone`, `admin_email`, `logo_url`, `payment_failed_email_enabled`, `current_event_id`, invoice batch interval/min/max/delay min/max, `last_invoice_sent_at`, `site_url`, Sadad environment (if the admin may switch it). Secrets set from the admin UI must use `encrypted` casts and never be re-displayed.

#### Upgrades (new, not in original)
- NEW: REST API v1 (`/api/v1`, Sanctum personal access tokens) for a future mobile app: events and availability, create order, order status, invoice, and staff endpoints (scan, search). It uses the same services and error envelope.
- NEW: OpenAPI document generated from the routes (`dedoc/scramble`), served at `/docs/api` to admins.
- NEW: MCP tools stay read-only; adding `list_payment_reviews` (payments needing review) is optional.

**Original defects to avoid:**
- `verify_jwt = true` was treated as "authenticated", but the public anon key is itself a valid JWT, so `auto-send-invoices` and `login-guard` were effectively public.
- Internal function-to-function calls went over HTTP with the service-role key as the bearer (key sprawl). Use direct calls or jobs.
- Inconsistent error shapes: newer functions used the envelope; older ones returned ad-hoc `{error}`, `{ok:false}` or `{success:false, message}` and sometimes HTTP 200 for errors.
- The `mcp` function was auto-generated from client code ("do not edit"). Its revenue used current ticket prices × sold holders, not stored totals, and "remaining" ignored pending holds.
- The `search_customers` LIKE pattern kept `_` as a wildcard. The gate and staff lookups had the same problem and also returned national IDs to passcode staff.
- Secrets were spread across env vars and the plain-text `settings` row, and `.env.example` referenced a non-existent `private_settings` table.
- No IP-level rate limits on public endpoints (order creation, status lookup, pay again, invoice lookup by a 48-bit reference).


---

## 2.3 Admin — orders, finance, tickets & customers

This section covers the finance-side back-office screens that the original mounts under `/admin/dashboard/*` (orders, transactions, invoices, payment errors, tickets, customers), the Sadad diagnostic card embedded in Settings, the Sadad transaction dialog, and two admin components reused by the POS (`TicketAddItem`, `CustomerLookup`). The original UI is Arabic-only, RTL, currency QAR (shown as `ر.ق`, `ريال قطري`, `QAR` or `ريال` depending on the screen; keep each screen's wording), all business times in `Asia/Qatar` (UTC+3, no DST).

Shared enums used throughout this section (the rebuild must keep the same values):

- `orders.payment_status`: `pending` | `confirmed` | `cancelled` (the API status `failed` is only *reported* by the public status endpoint for a closed online payment; it is never stored).
- `orders.payment_method`: `sadad` (online via Sadad) | `cash_pos` (cash / point of sale).
- `ticket_type`: `vip` | `normal` | `parking`.
- `orders.payment_note` reason codes: `expired` (30-minute payment window ran out), `payment_failed` (Sadad reported failure), `paid_after_expiry_no_stock` (paid after expiry with no seats left → refund needed), `admin_cancelled` (staff cancellation via toggle, or `cancel_order` with an empty note), plus free notes `released by admin` (Orders → release) and `returned by admin` (Customers → return). `NULL` once confirmed.
- Sadad live status (upper-cased `transactionstatus.name`) → Arabic label: `INPROGRESS`/`IN PROGRESS` `قيد المعالجة`; `FAILED` `فشلت`; `SUCCESS` `ناجحة`; `REFUND`/`REFUNDED` `مستردة`; `PENDING` `معلقة`; `ONHOLD`/`ON HOLD` `موقوفة`; `REJECTED` `مرفوضة`; unknown → raw name, or `غير معروف` / `UNKNOWN` if empty.
- Ticket-type labels differ per screen; long form `تذكرة دخول VIP` / `تذكرة دخول عادية` / `تذكرة موقف السيارات`; short `VIP` / `عادي` / `مواقف`; API-client labels `VIP` / `الدخول العام` / `المواقف`.

Laravel placement: all screens are Filament v4 resources/custom pages in the `admin` panel, restricted to roles `super_admin`/`admin` (`accountant` read-only on Transactions, Payment errors and Tickets statistics; `viewer` read-only everywhere). The team passcode never opens these screens. Live refresh uses Reverb broadcasting (`private-admin.orders`, `private-admin.tickets`, `private-admin.payment-errors`) with Livewire `#[On('echo-private:…')]` listeners; Filament tables otherwise refresh with `->poll()` only where noted.

---

### ADM-ORD — Orders management

Main booking-management screen and default landing page of the dashboard (original `/admin/dashboard/orders`, index `/admin/dashboard` redirects here). Used by admins to find bookings, check and reconcile payments, confirm/cancel manually, move bookings to another event date and repair QR pictures. New app: Filament custom page `Admin\Pages\Orders` at `/admin/orders` backed by a single server query service (`OrderListQuery`).

- **F-ADM-ORD-001 · Admin-only access to the dashboard** — every `/admin/dashboard/*` route requires an admin: a user with role `admin` or a row in `admin_users` (server function `is_admin(uid)`). A user who is only `moderator` is redirected to `/staff` on admin-only routes; no session → unified login card; a spinner shows while the check runs; the check re-runs on every route change and auth-state change. In the original, a valid team staff-passcode (localStorage/sessionStorage `staff_passcode_ok` / `staff_passcode_value` / `staff_passcode_exp` + cookie `staff_passcode_ok=1`) or a one-time staff hand-off token also opened the whole admin UI.
  - *Rebuild:* Filament panel `admin` with `canAccessPanel()` = roles `super_admin|admin|accountant|viewer`; moderators/cashiers/gatekeepers are redirected to the staff hub. Passcode/kiosk sessions are a separate guard limited to POS/scanner/live boards and can never reach finance screens. All mutations additionally authorise with policies (`OrderPolicy`), not only by hiding buttons.

- **F-ADM-ORD-002 · Two status tabs `نجح` / `فشل`** — tab `نجح (N)` (value `success`) lists orders with `payment_status = 'confirmed'`; tab `فشل (N)` (value `failed`) lists orders with `payment_status IN ('cancelled','pending')` — pending orders appear under "failed". N = the stat counts of F-ADM-ORD-003. Empty states: success tab `لا توجد حجوزات ناجحة`; failed tab `لا توجد حجوزات ملغية`.
  - *Rebuild:* keep the two tabs for familiarity but add a third tab `قيد الانتظار` (pending) and relabel the failed empty state; or keep two tabs and show pending with a distinct badge. Counts come from the same server query.

- **F-ADM-ORD-003 · Payment stat cards** — two cards (1 column on mobile, 2 on md+): green card titled `الدفعات الناجحة` with big number `stats.success` (green-600); red card titled `الدفعات الفاشلة` with `stats.failed` (red-600). Counts respect the current method filter, event filter and upcoming-only switch, but ignore the search term. Counted with head-only count queries joined to `events` (orders without an event are never counted).

- **F-ADM-ORD-004 · Payment-method filter with counts** — segmented control of 3 buttons: `الكل (N)`, `سداد (N)`, `نقدي / POS (N)`. Filters `payment_method` (`all` / `sadad` / `cash_pos`). The counts are always the **confirmed** order counts per method (methodAll, methodSadad, methodCashPos) with the current event + upcoming filters, regardless of the selected tab. A small `Loader2` spinner shows next to this control while the list is loading (the only loading indicator; the previous rows stay visible until replaced).
  - *Rebuild:* show counts for the active tab's statuses (fix: original always shows confirmed counts even on the failed tab); add a table skeleton.

- **F-ADM-ORD-005 · Event filter** — Select (width 200 px): first item `جميع الفعاليات` (value `all`), then one item per event from `events` (ALL events including archived/past, ordered by `event_date desc`), each item showing only the event date as `dd/MM/yyyy` (no title). Filters `orders.event_id`.
  - *Rebuild:* show `dd/MM/yyyy — <title>` and group archived seasons under a separator.

- **F-ADM-ORD-006 · Search by reference, name or phone** — input with search icon on the right, placeholder `ابحث بالرقم المرجعي، الاسم أو رقم الهاتف...`; debounce **400 ms**. When the trimmed term is non-empty the list switches to search mode: two parallel queries with the current tab/method/event/upcoming filters — (a) `booking_reference ILIKE %term%`, (b) customer `name ILIKE %term%` OR customer `phone ILIKE %term%` — each ordered `created_at desc` limited to **100**; results are merged, de-duplicated by id and sorted by `created_at desc`; total = merged length; search mode is not paginated (pagination bar hidden).
  - *Rebuild:* one server query with `where(fn ($q) => $q->where('booking_reference','like',…)->orWhereHas('customer', …))`, LIKE wildcards escaped (original did not escape the PostgREST `or()` syntax, so commas/parentheses broke the query), normal pagination while searching, and phone search on normalised digits.

- **F-ADM-ORD-007 · Upcoming-only switch** — Switch `القادمة فقط` (id `upcoming-only`), default **ON**: filters `events.event_date >= today` where today is the Qatar date `YYYY-MM-DD`. When ON a secondary badge shows the current `totalCount` next to the switch.

- **F-ADM-ORD-008 · List / grid view toggle** — two icon buttons (List, Grid3x3); default `list`; persisted per browser in localStorage key `ordersViewMode` (`grid` | `list`). Grid = 1 / 2 / 3 columns (base / md / lg) of order cards; list = stacked full-width order cards (same card component).
  - *Rebuild:* persist the choice in the user's UI preferences (DB) or localStorage wrapped in try/catch.

- **F-ADM-ORD-009 · Pagination (30 per page)** — page size `30`, ordered `created_at desc`, exact total count. Bar shown only when not searching and `totalCount > 30`: text `عرض {from}–{to} من {total} حجز` (from = `page*30+1`, to = `min((page+1)*30, total)`), buttons `السابق` (disabled on first page or while loading) and `التالي` (disabled on last page or while loading), middle text `صفحة {page+1} من {totalPages}`. Page resets to the first page whenever tab, method, event, debounced search or upcoming toggle changes. A stale-response guard drops out-of-date responses (request id counter).

- **F-ADM-ORD-010 · Live auto-refresh** — any insert/update/delete on `orders` or `ticket_holders` triggers a refresh of the page and stats, debounced **1500 ms** (channel `orders-page-realtime`). A second channel `ticket-holders-changes` (UPDATE on `ticket_holders`) patches the holder row inside the open details dialog in place and also refreshes the page.
  - *Rebuild:* one broadcast event `OrderChanged`/`TicketHolderChanged` on `private-admin.orders`; the Livewire page debounces 1.5 s and refreshes once (original refreshed twice per change and re-subscribed whenever the selected order changed).

- **F-ADM-ORD-011 · Order card layout and status badge** — RTL card with an accent rail on the right; ring-green when `sadad_manually_verified` is true. Header left: status badge — `confirmed` → `مؤكد` (default variant), `cancelled` → `فشل` (destructive), otherwise (pending) → `قيد الانتظار` (secondary).
  - *Rebuild:* cancelled badge text `ملغي` with the reason-code sub-label (`انتهت مهلة الدفع`, `فشل الدفع`, `ألغاه المشرف`) instead of the misleading `فشل`.

- **F-ADM-ORD-012 · Cashier / payment-method line** — under the status badge: icon `Banknote` (green) for `cash_pos`, `CreditCard` (blue) otherwise, followed by the cashier name `pos_users.name` when the order was sold at a POS, else the raw method value (`sadad` / `cash_pos`).
  - *Rebuild:* show `سداد` / `نقدي / POS` labels instead of raw enum values.

- **F-ADM-ORD-013 · Copy booking reference** — header right: copy icon button (title = the reference) that copies `booking_reference` to the clipboard and toasts `تم نسخ رقم الحجز`; next to it the reference in bold monospace primary colour, truncated at 130 px with a tooltip showing the full value.

- **F-ADM-ORD-014 · Event-shift badge and change history** — per card, if the order has rows in `expired_qr_codes` with `reason='event_date_changed'`, a badge `تم النقل` (title `تم تغيير تاريخ الفعالية`) is shown. Clicking opens dialog `سجل تغيير الفعالية` listing each change as `تغيير #N` with `من` (old event title + date) and `إلى` (new event title + date), read from `activity_logs` where `activity_type='event_change'` and `action_data.order_id` = this order; fallback when no logs: group the `expired_qr_codes` rows by second and show titles `غير معروف`; nothing at all → `لا توجد سجلات`.
  - *Rebuild:* store moves in a dedicated `order_event_changes` table (order_id, old_event_id, new_event_id, changed_by, changed_at, expired_codes_count) and eager-load a count to avoid one query per card.

- **F-ADM-ORD-015 · Event date and booked time on card** — header shows the event date `dd/MM/yyyy` (Arabic date-fns locale) with a calendar icon, or `غير متوفر` if missing/invalid. Footer right shows the booking time `dd/MM/yyyy - HH:mm` (or `—`).
  - *Rebuild:* format both in Asia/Qatar (original used the browser time zone for the booking time).

- **F-ADM-ORD-016 · Paid-after-expiry refund warning** — when `payment_note = 'paid_after_expiry_no_stock'` the card shows a red bold line `⚠️ دُفع بعد انتهاء الحجز ولا توجد تذاكر — يلزم استرداد المبلغ` (customer paid after the 30-minute window and no seats were left; the money must be refunded in Sadad).
  - *Rebuild:* also list these orders in a "refunds needed" filter and on the dashboard alerts.

- **F-ADM-ORD-017 · Expired-payment note** — when `payment_note = 'expired'` the card shows a muted line `انتهت مهلة الدفع`.

- **F-ADM-ORD-018 · Payment error reason box** — when status ≠ confirmed and `payment_error_reason` is set: red alert box with `AlertCircle` icon and the reason text.

- **F-ADM-ORD-019 · Amount and customer block** — amount block: caption `ريال قطري`, value `total_amount` with 2 decimals (bold, primary, tabular digits). Customer block: caption `العميل`; customer name + nationality flag emoji; phone (LTR); e-mail (LTR, only if present). Missing customer → name `عميل غير معروف`, phone `غير متوفر`.

- **F-ADM-ORD-020 · Ticket icon strip and type summary** — up to **8** icons (`min(quantity, 8)`): Crown (VIP, colour `ticket-vip`), Car (parking), Ticket (normal). Types are taken from the order's `ticket_holders[].ticket_type` (fallback for legacy orders without holders: `quantity` × `orders.ticket_type`), truncated to `quantity`. Summary text: one type → `<label> × <quantity>`; several types → `<label> × <count>` joined by ` • `. Labels: vip → `تذكرة دخول VIP`, normal → `تذكرة دخول عادية`, anything else → `تذكرة موقف السيارات`.

- **F-ADM-ORD-021 · Action `عرض التذاكر` (view tickets)** — small button with Printer icon, always shown; navigates to the admin ticket viewer `/admin/tickets?ref=<booking_reference>` (printable tickets of the booking).

- **F-ADM-ORD-022 · Ticket holders dialog** — button `عرض التفاصيل` (Eye), always shown. Opens a dialog (max-w-4xl, max-height 80vh, scroll) titled `معلومات حاملي التذاكر` with a right-aligned table: `#` (1-based index), `اسم حامل التذكرة`, `رقم الهاتف`, `الجنسية`, `نوع التذكرة` (outline badge, raw value `vip`/`normal`/`parking` capitalised), `السعر` (`<price 2 dp> ريال قطري`, or `-` when 0), `QR Code` (ghost QR button titled `View QR Code` when the holder has a `qr_code`, else text `No QR`; plus presence: green `حاضر` with CheckCircle if `is_present`, else muted `غائب` with XCircle). Empty → `لا توجد معلومات حاملي التذاكر`. Load error toast `فشل التحميل`. Price per holder = current price of the holder's type for the order's event (0 if missing). Holder rows update live (F-ADM-ORD-010).
  - *Rebuild:* show a loading state (original showed the previous order's holders while loading); show the price actually paid (store `unit_price` on each holder at order creation) instead of the current ticket price; translate `View QR Code` / `No QR`.

- **F-ADM-ORD-023 · Enlarged holder QR dialog** — from the holders table. Title `QR Code`; image source = holder `qr_image_url`, else a legacy `qr_code` value that starts with `http`, else a QR generated from the code text (width **800**, margin **2**, error-correction **H**); displayed 800×800 with the holder name (2xl bold), phone and ticket type below. Spinner (800×800 box) while generating; error toast `Failed to generate QR code`.
  - *Rebuild:* serve the PNG from `GET /admin/holders/{holder}/qr.png` (endroid, same size/margin/ECC), Arabic texts (`رمز QR`, `تعذر إنشاء رمز QR`).

- **F-ADM-ORD-024 · Action `تفاصيل سداد` (Sadad details)** — primary-outline button with ReceiptText icon, shown only when `payment_method = 'sadad'`; opens the Sadad transaction dialog (F-ADM-SDD-010…015) for this order.

- **F-ADM-ORD-025 · Action `تحقق من سداد` (verify with Sadad)** — ShieldCheck button, shown only when method is `sadad` AND status ≠ `confirmed` AND `payment_note ≠ 'admin_cancelled'`; spinner and disabled while this order is being verified. Calls server `verify-payment {booking_reference}`: reference must match `/^[A-Za-z0-9][A-Za-z0-9_-]{0,59}$/` (else 400 `invalid_reference`), unknown → 404 `order_not_found`, already confirmed → `{success:true, checked:false, payment_status:'confirmed'}`. Admin callers are not throttled (the public 10-second `claim_payment_check` throttle is skipped). The server looks up the order in the Sadad API; it confirms only when Sadad says `success` with matching `website_ref_no`, a transaction number and a matching amount (`confirm_order_payment`: sets `confirmed`, `payment_id`, `paid_at`, `confirmed_at`, clears `payment_note`/`payment_error_reason`; if no seats are left it keeps the status and sets `payment_note='paid_after_expiry_no_stock'`; a transaction number already used on another order → `transaction_already_used`); Sadad `failed` → `mark_order_payment_failed` (pending → cancelled, `payment_note='payment_failed'`); otherwise unchanged. Every attempt is logged to `payment_events` (payload truncated to 4000 chars); on confirmation the invoice e-mail and admin sale alert are triggered. Client toasts: confirmed → `سداد أكّد الدفع — تم تأكيد الحجز ✓`; not confirmed → info `لم يُسجّل سداد دفعاً لهذا الحجز بعد`; error → mapped Arabic API message (F-ADM-ORD-040). Then refresh.
  - *Rebuild:* `VerifySadadPayment` action calling `PaymentService::verify($order, source: 'admin')`. Make "staff-cancelled is final" a server rule: refuse verification/confirmation of orders cancelled with any admin note (`admin_cancelled`, `released by admin`, `returned by admin`) unless the admin explicitly chooses "re-open"; the original hid the button only for `admin_cancelled`, so released/returned orders could be re-confirmed by a late Sadad success.

- **F-ADM-ORD-026 · Action `إلغاء وإعادة للبيع` (cancel & resell)** — Undo2 button, only for `pending` orders. Browser confirm `إلغاء الحجز {ref} وإعادة تذاكره للبيع؟`; calls RPC `cancel_order(order_id, 'released by admin')`. Server contract: admin only (else `forbidden`); locks the order `FOR UPDATE`; acts only if status is `pending` or `confirmed`; sets `payment_status='cancelled'`, `payment_note = trimmed note or 'admin_cancelled'`; seats released by the stock rule (F-ADM-ORD-041); returns `{result:'cancelled'|'already_inactive'|'order_not_found', order_id}` (idempotent). Client treats every result as success: toast `تم إلغاء الحجز وإعادة التذاكر للبيع` + refresh; error → mapped API message.
  - *Rebuild:* Filament action with `requiresConfirmation()` and the same Arabic text; report `already_inactive` as info.

- **F-ADM-ORD-027 · Action `تأكيد الدفع` (manual confirm of a pending order)** — green CheckCircle button, only for `pending`. No confirmation dialog. Algorithm (togglePaymentStatus → confirmed): load order with customer, event and holders; update order `{payment_status:'confirmed', confirmed_at: now, confirmed_by: current user id, paid_at: now, payment_note: null}`; update all holders of the order `confirmed_at`, `confirmed_by`; run the QR backfill (F-ADM-ORD-035) — on error toast `تم التأكيد لكن فشل توليد رموز QR`, else wait 1000 ms; reload the order and send it to the WhatsApp automation with `action:'payment_confirmed'` + `timestamp` (n8n result handling F-ADM-ORD-036); success toast `تم تأكيد الحجز وإرساله للنظام بنجاح ✓`; refresh. Outer failure toast `فشل في تغيير حالة الحجز: <message|خطأ غير معروف>`.
  - *Rebuild:* single server action `ConfirmOrderManually` in one DB transaction (order status + stock check + audit) then queued jobs: `GenerateOrderQrImages` (this order only, not the whole backlog), `SendWhatsAppTickets` (server-built `payment_confirmed` payload, never the full row), `SendInvoiceEmail`. Require a confirmation modal with an optional "reference / reason" field; for Sadad orders warn that no Sadad transaction number is recorded and ask for it (stored as `manual_payment_reference`). Do NOT write holder `confirmed_at/confirmed_by` (those are gate-scan fields); store `orders.confirmed_at/confirmed_by` only.

- **F-ADM-ORD-028 · Action `إلغاء التأكيد` (cancel a confirmed order)** — destructive XCircle button, only for `confirmed`. No confirmation dialog. Updates order `{payment_status:'cancelled', confirmed_at:null, confirmed_by:null, payment_note:'admin_cancelled'}` (a staff cancellation is final: the verify button is hidden afterwards), clears holders' `confirmed_at`/`confirmed_by`; seats are released by the stock rule. Toast `تم إلغاء تأكيد الحجز`; refresh.
  - *Rebuild:* confirmation modal; reason field; audit log; do not touch scan fields; offer "refund needed" flag for Sadad orders.

- **F-ADM-ORD-029 · Action `تغيير إلى نجح` (re-confirm a cancelled order)** — green CheckCircle button, only for `cancelled`. Same algorithm as F-ADM-ORD-027 (status → confirmed, holders, QR backfill, n8n notify). Re-activation re-reserves seats; see F-ADM-ORD-030.

- **F-ADM-ORD-030 · Insufficient-stock message on re-activation** — if moving an order back to an active status fails because the stock rule raises `insufficient_stock`, the client shows toast `لا توجد تذاكر كافية لإعادة تفعيل هذا الطلب` and stops (status unchanged).

- **F-ADM-ORD-031 · Sadad manually-verified flag** — clickable badge in the card footer, shown for every method (including cash). Verified → green `✓ تم تأكيد سداد` (CheckCircle), title `اضغط لإلغاء التأكد`; not verified → muted `✗ تأكيد سداد` (XCircle), title `اضغط للتأكد من الدفع`. Click toggles `orders.sadad_manually_verified`; toasts `تم التأكد من الدفع في سداد` (set) / `تم إلغاء التأكد من الدفع` (unset); error `فشل في تحديث حالة التأكد`. Card gets a green ring when set. Purely an internal reconciliation marker (admin checked the payment in Sadad's portal); no other effect.
  - *Rebuild:* also store `sadad_verified_by`/`sadad_verified_at`; hide for `cash_pos` or relabel `تمت المطابقة`.

- **F-ADM-ORD-032 · Change event date** — row shown when the order has an event: label `تغيير التاريخ:` + Select whose value is the order's `event_id`, items `dd/MM/yyyy - <title>` for every event (including archived). Selecting an item immediately (no confirmation) calls server `change-order-event {order_id, new_event_id}`. Server contract: admin only (team passcode not sufficient → 401); 400 `Missing order_id or new_event_id`; 404 `Order not found`; inserts into `expired_qr_codes` a row `{qr_code, order_id, reason:'event_date_changed'}` for the order's own code and for every holder code (abort with 500 before any change if this fails); loads old and new event; sets a new order code `QR-<epoch ms>-<16 hex>` (upper-case) and `orders.event_id` (stock moves from old to new event by the stock rule; no room → 409 `{error:'insufficient_stock', message:'لا توجد تذاكر كافية في الفعالية الجديدة'}`; other → 500 `Error updating order`); gives every holder a new code (same format) and `qr_image_url = null`, then re-renders the PNG pictures best-effort in parallel; writes `activity_logs {activity_type:'event_change', user_type:'admin', action_data:{order_id, booking_reference, old_event_id, old_event_title|'Unknown', old_event_date|'', new_event_id, new_event_title|'Unknown', new_event_date|''}}`; returns `{success, message, expired_qr_codes:<count>, new_order_qr, updated_ticket_holders}`. Old codes are rejected at the gate because they are listed in `expired_qr_codes` and no longer on any holder. Client: success toast `تم تغيير تاريخ الفعالية بنجاح وإنشاء {expired_qr_codes} رموز QR جديدة` + refresh; any error → `فشل في تغيير تاريخ الفعالية`.
  - *Rebuild:* `MoveOrderToEvent` action with confirmation modal listing old → new event, only non-archived future events with the same ticket types, validation that the new event exists and differs, one transaction (expire codes, move stock with `SELECT … FOR UPDATE` on both events' ticket rows in id order, new codes, audit), then a queued job regenerates pictures and optionally re-sends tickets via WhatsApp. Show the server's message (e.g. no stock) and the count of *new* codes (original showed the expired count and swallowed the 409 message).

- **F-ADM-ORD-033 · Delete order** — trash icon button (destructive, title `حذف الطلب`) shown only when setting `show_delete_customer_button` is true (default false; the same setting gates customer deletion). Opens AlertDialog `تأكيد الحذف` / `هل أنت متأكد من حذف هذا الطلب؟ سيتم حذف جميع التذاكر المرتبطة به. هذا الإجراء لا يمكن التراجع عنه.` with `إلغاء` / `حذف` (destructive). Deletes all holders of the order (seat released per holder if the order is active) then the order. Toast `تم حذف الطلب والتذاكر بنجاح`; error `فشل حذف الطلب`. Hard delete, no audit.
  - *Rebuild:* dedicated setting `show_delete_order_button`; soft delete (`deleted_at`, `deleted_by`) with stock release in the same transaction and an activity-log entry; super_admin only.

- **F-ADM-ORD-034 · Card footer** — clickable verified badge (F-ADM-ORD-031) on the left; booked date-time on the right (F-ADM-ORD-015).

- **F-ADM-ORD-035 · Generate missing QR pictures (backfill)** — button `Generate Missing QR Codes` (QrCode icon; while running spinner + `Generating QR Codes...`) at the top of the page, shown only when setting `show_generate_qr_button` is true (default false). Calls server `backfill-qr-codes` (admin): takes up to **100** holders whose `qr_image_url` is empty, renders a PNG of the holder's existing `qr_code` text (fallback to a public QR service), uploads it to storage `qr-codes/<qr_code>.png` (upsert; object name must match `/^[A-Za-z0-9][A-Za-z0-9_-]{0,79}$/`), saves the public URL in `ticket_holders.qr_image_url`; never changes the code. Returns `{success, updated, skipped:0, errors?, message:'Created N QR images[ — run it again to continue]'}`. Toasts `${updated} QR codes generated successfully!` / `Failed to generate QR codes`; refresh. (A sibling server function `regenerate-booking-qr-codes {booking_reference}` re-renders the pictures of one booking; not wired to this screen.)
  - *Rebuild:* Arabic labels (`توليد رموز QR الناقصة`, `تم توليد {n} صورة QR`); queued job `BackfillQrImages` processing all missing pictures in chunks of 100 with progress broadcast; pictures normally generated on confirmation so the button is a repair tool; per-order "regenerate QR pictures" action wired to the per-booking routine.

- **F-ADM-ORD-036 · n8n notification result handling** — after a manual confirm (F-ADM-ORD-027/029) the client invokes the server relay `send-to-webhook` with the order payload. Relay contract: admin only; body ≤ 200 000 bytes; payload with `notify:'payment_confirmed'` → server builds the message from the DB for that `booking_reference`; otherwise relays the client payload verbatim; target URL = `settings.webhook_url` (null if `webhook_enabled=false`) or, when `action='send_email'`, `settings.email_webhook_url` (falls back to `webhook_url`; null if `email_webhook_enabled=false`); missing → 400 `webhook_not_configured` with hint `Please configure the (email )webhook URL in admin settings`; POST JSON with 10 000 ms timeout; upstream OK → if payload has `order_id` or `booking_reference` sets `orders.n8n_response_message` = `جاري الإرسال إلى واتساب...` (or `جاري إرسال البريد الإلكتروني...` for e-mail) and `n8n_responded_at = NULL`, returns `{success:true, message, response}`; upstream 404 → 200 `{error:'Webhook not found or inactive', details, status:404, solution:'Please ACTIVATE the workflow in n8n (not just test mode).'}`; other upstream error → 200 `{error:'Webhook returned error', details, status}`; timeout → `{error:'Webhook request timed out', details:'The webhook did not respond in time'}`; network → `{error:'Webhook request failed', details:'Could not reach the webhook'}`. Client toasts: invoke error → `تم تحديث الحالة لكن فشل الإرسال للنظام: <message>`; `error` with status 404 → (8 s) `⚠️ تم تحديث الحالة لكن n8n webhook غير نشط!\n\nالحل: قم بتفعيل الـ workflow في n8n (اضغط على Toggle في أعلى الصفحة)`; with `solution` → (8 s) `تم تحديث الحالة لكن: <solution>`; other error → `تم تحديث الحالة لكن فشل الإرسال للنظام`; exception → `تم تحديث الحالة لكن حدث خطأ في الإرسال للنظام`; success → `تم تأكيد الحجز وإرساله للنظام بنجاح ✓`. n8n later reports back through `n8n-response {booking_reference, message ≤500, status?}` (optional shared secret header `x-webhook-secret` or Bearer), which sets `n8n_response_message` and `n8n_responded_at = now()` (400 missing fields, 404 unknown order).
  - *Rebuild:* `WhatsAppChannel` driver interface (n8n implementation keeps this exact contract); job result stored in `notification_attempts`; failure surfaces as a persistent notification, not only a toast. Inbound `POST /api/n8n/response` requires the shared secret (mandatory, constant-time compare).

- **F-ADM-ORD-037 · Order list data contract** — each row exposes: id, booking_reference, payment_status, payment_method, ticket_type, quantity, total_amount, created_at, event_id, payment_id, sadad_manually_verified, payment_error_reason, payment_note, customer (name, email, phone, nationality), event (title, event_date, location; inner join — orders without an event are never listed), POS user (name, icon), holders' ticket types. Fetch errors → empty list silently (no toast).
  - *Rebuild:* `GET /admin/orders?status=&method=&event=&q=&upcoming=&page=` (Filament table query) returning page + counts in one round trip; error toast on failure.

- **F-ADM-ORD-038 · Settings-driven button visibility** — the page reads `settings.show_delete_customer_button` (delete order icon) and `settings.show_generate_qr_button` (backfill button), both default false; load errors only logged.

- **F-ADM-ORD-039 · Dead "confirmPayment" helper (not a feature)** — the original contains an unused `confirmPayment` that would set status `confirmed` and toast `تم تأكيد الدفع!`. Do not port.

- **F-ADM-ORD-040 · Arabic API error messages** — every admin API call resolves `{ok, data}` or `{ok:false, error:{code, message, status, details}}` and never throws; errors map to Arabic: `ticket_limit_exceeded` with message → server message verbatim; `insufficient_stock` → `عذراً، لم تعد هناك تذاكر كافية من هذا النوع. يرجى تقليل الكمية أو اختيار نوع آخر.` (+ ` (VIP|الدخول العام|المواقف)` when `details.ticket_type`); `quantity_limit_exceeded` → `تجاوزت الحد الأقصى للتذاكر في الطلب الواحد.`; `below_minimum_amount` → `الحد الأدنى للدفع عبر سداد هو 3 ريال قطري.`; `event_not_available` → `هذه الفعالية غير متاحة للحجز حالياً.`; `too_many_pending_orders` → `لديك حجوزات غير مكتملة. يرجى إتمامها أو المحاولة لاحقاً.`; `payment_unavailable` → `الدفع الإلكتروني غير متاح حالياً. يمكنك اختيار الدفع عند الحضور.`; `validation_failed` → `يرجى التحقق من البيانات المُدخلة والمحاولة مرة أخرى.`; `invalid_tickets` → `إحدى التذاكر المختارة لم تعد متاحة. يرجى العودة واختيار التذاكر من جديد.`; `holders_mismatch` → `يرجى إدخال بيانات حامل كل تذكرة.`; `unauthorized` → `انتهت الجلسة أو كلمة مرور الفريق غير صحيحة. يرجى تسجيل الدخول من جديد.`; `forbidden` (RPC message exactly `forbidden`) → `ليست لديك صلاحية لتنفيذ هذا الإجراء.`; `network_error` → `تعذر الاتصال بالخادم. تحقق من اتصالك بالإنترنت وحاول مرة أخرى.`; anything else (incl. `rpc_failed`) → `حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.`
  - *Rebuild:* a `DomainException` hierarchy with `code()`; translations in `lang/ar/errors.php` keyed by the same codes; Filament actions show `Notification::make()->danger()->body(__("errors.$code"))`.

- **F-ADM-ORD-041 · Stock invariant (server rule used by every order action)** — `tickets.sold_quantity` must always equal the seats held by orders whose status is `pending` or `confirmed`. Seats of an order = count of its holders per `ticket_type` (legacy orders without holders: `orders.ticket_type × orders.quantity`). Rules: status change active → inactive releases seats (`GREATEST(0, sold − seats)`); inactive → active reserves seats and fails the whole change with `insufficient_stock` if `available_quantity − sold_quantity < seats` for any type or the type row is missing; adding a holder to an active order reserves +1 (fails `insufficient_stock:<type>`; unknown type ignored); deleting a holder of an active order releases −1; moving an active order to another event releases on the old and reserves on the new (fails `insufficient_stock`); deleting an active order releases. Ticket rows are locked `FOR UPDATE ORDER BY id` (both events in a fixed order) to avoid deadlocks. Online orders get `payment_expires_at = created_at + 30 minutes`; expired pending Sadad orders become `cancelled` with `payment_note='expired'`. `recount_ticket_stock()` (admin only, else `forbidden`) recomputes every `sold_quantity` from live orders and returns the number of rows changed (used by F-ADM-TKT-005).
  - *Rebuild:* `App\Domain\Stock\StockLedger` service invoked from `OrderStatusService`, `HolderService`, `MoveOrderToEvent` and order deletion inside `DB::transaction()` with `lockForUpdate()` on ticket rows ordered by id; no DB triggers (portable MySQL/PostgreSQL). Pest tests for every transition and for concurrent reservations; nightly scheduled `stock:recount --report` that alerts if any counter drifted.

**Upgrades (new, not in original)**
- NEW: Export current filtered list to XLSX (maatwebsite/excel) with the card fields.
- NEW: Bulk actions on selected rows (verify with Sadad, mark verified) — server-side, queued.
- NEW: "Refunds needed" quick filter (`payment_note = paid_after_expiry_no_stock`) and a "refund handled" flag with who/when.
- NEW: Order timeline drawer (payment_events, status changes, notifications, scans) on the order page.

**Original defects to avoid:**
- Pending orders shown under the `فشل` tab while its empty state says `ملغية`; method counts always confirmed even on the failed tab.
- Manual confirm/cancel executed from the browser in 5+ non-atomic steps (order update, holder update, whole-backlog QR backfill, webhook); partial failures leave inconsistent state.
- Manual confirm writes holder `confirmed_at/confirmed_by`, which other screens use as the gate scan time/scanner — payment confirmation and scanning must be separate fields.
- The full order row (all columns, customer, holders) is sent to n8n instead of the server-built `payment_confirmed` payload — leaks internal fields.
- Sadad orders can be confirmed manually with no transaction number; `cancelled → confirmed` can silently fail on stock.
- Verify button hidden only for `admin_cancelled`; released/returned orders can be re-confirmed by the admin verify path, which skips the throttle/finality checks.
- Change-date select fires instantly without confirmation, lists archived events, doesn't validate the target event, reports the expired-codes count and swallows the 409 "no stock" message.
- Two realtime channels both refresh the page (double fetch) and the holders channel re-subscribes on every dialog change.
- Holders dialog shows stale data while loading and prices from the current ticket price, not the price paid.
- Delete-order button gated by the customer-delete setting; hard delete without audit.
- Booking time shown in the browser time zone; English strings (`Generate Missing QR Codes`, `View QR Code`, `No QR`, `Failed to generate QR code`) in an Arabic UI.
- Search term not escaped (commas/parentheses break the query) and search results capped at 100+100 and unpaginated.
- Dead code (`confirmPayment`) and unused imports.

---

### ADM-TRX — Transactions

Financial ledger of orders per payment channel (Sadad online vs POS cash), with Qatar-day filtering, confirmed totals, live Sadad fee/net data from Sadad's Get-Single-Transaction API and a detail dialog. Used by admins/accountants for reconciliation. Original `/admin/dashboard/transactions`; new app: Filament page `Admin\Pages\Transactions` at `/admin/transactions` (read-only; accountant role allowed).

- **F-ADM-TRX-001 · Header** — icon ArrowLeftRight, title `المعاملات`; subtitle depends on channel: `معاملات الدفع الإلكتروني عبر سداد` (sadad) or `معاملات نقاط البيع (نقدي / POS)` (cash_pos).

- **F-ADM-TRX-002 · Channel switch Sadad / POS** — two buttons: `سداد` (CreditCard, primary) and `نقدي / POS` (Banknote, amber). Default `sadad`. Always filters `payment_method = <channel>` (there is no "all" channel). Leaving the Sadad channel resets the Sadad-status filter (F-ADM-TRX-008) to `all`. Page resets to 0 on any filter change.

- **F-ADM-TRX-003 · Current / archived season toggle** — header buttons `الحالية` / `الأرشيف` (Archive icon); default `current`. Filters on the order's event `is_archived` (`false` for current, `true` for archive); orders without an event are excluded (inner join).

- **F-ADM-TRX-004 · Refresh button** — `تحديث` (RefreshCw, spinning while loading) reloads list and totals. No realtime on this screen.

- **F-ADM-TRX-005 · Summary cards** — three cards for the current scope (channel, season, day, search; status filter applies to the count card only): `إجمالي المبلغ المؤكد` (Wallet icon) = sum of `total_amount` of **confirmed** orders in scope formatted `amount.toLocaleString('en-US') ر.ق`; `معاملات ناجحة` = number of confirmed orders in scope; `إجمالي المعاملات` = total count of the main list query (all statuses in scope, i.e. with the status filter). Original computes the sum by paging 1000 rows at a time up to 20 000 rows; errors silently give 0.
  - *Rebuild:* single aggregate query `SUM(total_amount)`, `COUNT(*)` with the same filters (money in integer dirhams / DECIMAL).

- **F-ADM-TRX-006 · Qatar-day filter (transaction date)** — popover calendar (single date). Button label `كل الأيام` when none, else the day formatted `EEEE d MMMM yyyy` in Arabic. Inside the popover, when a day is set, a button `عرض كل الأيام` clears it. Filters `orders.created_at` between Qatar `00:00:00` and `23:59:59.999` of that day converted to UTC (creation date, not event date).
  - *Rebuild:* compute the range server-side with `CarbonImmutable::parse($day, 'Asia/Qatar')->startOfDay()->utc()`; optional second filter "event date".

- **F-ADM-TRX-007 · Status filter** — Select (width 160) placeholder `الحالة`: `كل الحالات` (all), `ناجحة` (confirmed), `قيد الانتظار` (pending), `فاشلة / ملغاة` (cancelled). Filters `payment_status`.

- **F-ADM-TRX-008 · Live Sadad-status filter (Sadad channel only)** — Select (width 190) placeholder `حالة سداد`: `كل حالات سداد` (all), `سداد: ناجحة` (SUCCESS), `سداد: مرفوضة / فاشلة` (matches REJECTED or FAILED), `سداد: قيد المعالجة` (matches INPROGRESS, IN PROGRESS, PENDING). Applied client-side to the **current page only**; rows without fetched Sadad data are hidden while the filter is active; selecting a filter automatically triggers the page Sadad fetch (F-ADM-TRX-012) if any eligible row lacks data. Empty state while active: `لا توجد معاملات بهذه الحالة في سداد ضمن الصفحة الحالية`.
  - *Rebuild:* store `sadad_status`, `sadad_fee`, `sadad_refund_charge`, `sadad_net`, `sadad_synced_at` on `payments`/`orders` (filled by a queued `SyncSadadTransaction` job and by webhook/verify), so the filter is server-side and paginates correctly.

- **F-ADM-TRX-009 · Search by booking reference or Sadad transaction number** — input (min width 200) placeholder `بحث برقم الحجز أو رقم المعاملة…`; debounce **300 ms**; filters `booking_reference ILIKE %t%` OR `payment_id ILIKE %t%`.
  - *Rebuild:* Livewire `wire:model.live.debounce.300ms` (original bound the input to the debounced value, causing typing glitches); escape LIKE wildcards.

- **F-ADM-TRX-010 · Transactions table** — card title `كل المعاملات` or `معاملات يوم <day label>` followed by `(<totalCount>)`. Loading: centered spinner. Empty: `لا توجد معاملات مطابقة للفلاتر الحالية` (or the Sadad-filter message). Columns: `رقم المعاملة` (`payment_id` monospace LTR or `—`), `رقم الحجز` (monospace LTR), `العميل` (name or `عميل غير معروف` + phone LTR), `الفعالية` (title truncated at 180 px or `—`), `التذاكر` (secondary badge `<quantity> × VIP|مواقف|عادي` from `orders.ticket_type`), `المبلغ` (`<total en-US> ر.ق`), `الطريقة` (`سداد` with CreditCard or `نقدي / POS` with Banknote), `الحالة` (status badge + `payment_error_reason` in red truncated beneath), [Sadad only: `حالة سداد`, `عمولة سداد`, `الصافي بعد الخصم` (cell background primary/5)], `التاريخ (قطر)` (`dd/MM/yyyy hh:mm a` Qatar), `تفاصيل` (ghost button `عرض` with Eye). On the Sadad channel an extra header row spans the three Sadad columns with the pill `بيانات مباشرة من منصة سداد (Get Single Transaction API)`. The whole row is clickable and opens the details dialog. Ordered `created_at desc`.
  - *Rebuild:* the tickets column must summarise holders by type (original shows only the main `orders.ticket_type`, wrong for mixed orders).

- **F-ADM-TRX-011 · Status badge mapping** — `confirmed` → `ناجحة` (emerald, CheckCircle2); `pending` → `قيد الانتظار` (amber, Clock); `cancelled` → `فاشلة / ملغاة` (destructive, XCircle); unknown → pending style.

- **F-ADM-TRX-012 · Per-row live Sadad cells with fetch / retry** — for Sadad rows: no `payment_id` → three `—`; loading → spinner spanning 3 cells; error → red underlined button `<error> — إعادة المحاولة` (refetch); not fetched → ghost button `جلب من سداد` (RefreshCw); fetched → status badge with Arabic label coloured by status (SUCCESS emerald; FAILED/REJECTED destructive; REFUND/REFUNDED sky; PENDING/INPROGRESS/IN PROGRESS amber; else muted), fee = `commission + refundCharge` (amber), net = `netAmount` (emerald bold); amounts formatted `<n 2 dp> ر.ق` (0 if not finite). Fetch calls server `sadad-transaction {orderId}` (contract F-ADM-SDD-011); error text = server `error` or `تعذر جلب بيانات سداد` (catch → message or `خطأ`). Results cached in memory for the session.
  - *Rebuild:* read the stored columns; the "fetch" button queues a sync job and updates via broadcast; parse non-2xx error bodies correctly (original lost the server message and showed the generic text); cache keyed by order with `sadad_synced_at` and a "refresh" action.

- **F-ADM-TRX-013 · Bulk Sadad fetch for the page** — Sadad channel only: header button `جلب حالة سداد للصفحة` (CreditCard; pulses and reads `جارٍ الجلب من سداد…` while syncing; disabled while syncing or loading). Fetches Sadad details for every row on the current page with `payment_method='sadad'` and a `payment_id`, **3 at a time** (batches of 3).
  - *Rebuild:* dispatch a `Bus::batch()` of `SyncSadadTransaction` jobs throttled by a Redis rate limiter (≤ 3 concurrent), with cached Sadad access token (original logged in to Sadad on every call → up to 30 logins per page).

- **F-ADM-TRX-014 · Pagination (30 per page)** — shown when totalPages > 1: text `صفحة {page+1} من {totalPages}`, buttons `السابق` (ChevronRight) / `التالي` (ChevronLeft) disabled at bounds or while loading.

- **F-ADM-TRX-015 · Transaction details dialog** — opened by row click or `عرض`. Dialog (max-w-2xl, 85vh scroll, RTL) title `تفاصيل المعاملة` (ReceiptText), description `كل بيانات الدفع والحجز المرتبطة بهذه المعاملة`. Badges: status badge + method badge (`سداد` / `نقدي / POS`). Ten info tiles (2 columns): `رقم المعاملة` (payment_id or `—`, LTR), `رقم الحجز` (LTR), `العميل` (or `عميل غير معروف`), `الهاتف` (LTR or `—`), `البريد الإلكتروني` (LTR or `—`), `الفعالية` (or `—`), `التذاكر` (`qty × VIP|مواقف|عادي`), `المبلغ` (`x ر.ق`), `تاريخ الإنشاء (قطر)` (LTR), `تاريخ التأكيد (قطر)` (LTR or `—`). If `payment_error_reason`: red box `سبب الفشل (من نظامنا)` + reason.

- **F-ADM-TRX-016 · Live Sadad block in details dialog** — for Sadad orders with a `payment_id`: block `بيانات سداد المباشرة (Get Single Transaction)` with button `تحديث من سداد` (spinner, disabled while loading). Opening the dialog auto-fetches. States: loading `جارٍ الاتصال بمنصة سداد…`; error box `سبب فشل جلب البيانات من سداد` + message; nothing yet `اضغط «تحديث من سداد» لجلب كل تفاصيل العملية`; data: status badge (Arabic) + raw status (LTR) + sky badge `تم استردادها` when `isRefund`; if status FAILED/REJECTED a red box `سبب الفشل: <payment_error_reason>` or, when none, `العملية غير ناجحة لدى سداد — لم يُرجع سداد سبباً تفصيلياً لهذه العملية.`; nine tiles: `مرجع العملية (رقم الفاتورة)` (transactionno), `المرجع في الموقع (website_ref_no)` (or `—`), `المبلغ الإجمالي`, `عمولة سداد (servicecharge)`, `رسوم الاسترداد (refundcharge)`, `الصافي بعد الخصم`, `وسيلة الدفع (transactionmode)` (or `—`), `جهة العملية (transactionentity)` (or `—`), `تاريخ العملية في سداد` (raw string or `—`).

- **F-ADM-TRX-017 · Open invoice page** — button `فتح صفحة الفاتورة` (ExternalLink) opens the public invoice `/invoice/<booking_reference>` in a new tab (`noopener`).

- **F-ADM-TRX-018 · Customer's other orders** — section `طلبات أخرى لنفس العميل` in the details dialog: up to **10** other orders of the same `customer_id` (excluding this one), newest first, regardless of season or channel. Spinner while loading; empty `لا توجد طلبات أخرى مرتبطة`; each row: reference (monospace LTR), Qatar date-time, amount `x ر.ق`, status badge, ExternalLink button → that order's invoice page. Errors only logged.

**Upgrades (new, not in original)**
- NEW: XLSX export of the filtered ledger including stored Sadad fee/net, and a daily settlement summary (gross, fees, net) for reconciliation with Sadad payouts.
- NEW: Cash-POS breakdown by cashier (`pos_users`) and by shift.

**Original defects to avoid:**
- Totals computed in the browser by sequential 1000-row pages capped at 20 000 rows; sum query errors silently show 0.
- Sadad-status filter only filters the current page; pagination and counts ignore it.
- Tickets column uses `orders.ticket_type` only (wrong for mixed orders).
- Search input bound to the debounced state (typing glitch); LIKE input not escaped.
- Day filter is on creation date though the label reads `معاملات يوم`; day string built in browser time zone.
- Non-2xx Sadad error bodies lost (generic `تعذر جلب بيانات سداد`); in-memory Sadad cache never invalidated.
- Sadad API login on every lookup, no token cache; up to 30 logins per page view.
- Fee column silently sums commission and refund charge without saying so (label the column `عمولة + رسوم استرداد` or split).

---

### ADM-INV — Invoice sending console (WhatsApp / e-mail)

"Manual invoice sending" console for **confirmed Sadad orders**: sends each order's invoice and ticket QR pictures to the customer's WhatsApp through the n8n automation, in randomised-delay batches either on click or on an auto-repeat countdown; tracks n8n replies, retry counts and on-hold orders; per-order e-mail invoice; resets; mark-all-as-sent; e-mail delivery timeline. Original `/admin/dashboard/invoices` (scheduler ran in the admin's browser tab). New app: Filament page `Admin\Pages\InvoiceDispatch` at `/admin/invoices` that *monitors and controls* a server-side scheduler (`invoices:dispatch` job), keeping the same rules and labels.

- **F-ADM-INV-001 · Eligible orders** — only orders with `payment_method = 'sadad'` AND `payment_status = 'confirmed'` (POS orders never appear), with customer (name, email, phone, country_code, nationality), event (title, event_date, location, is_archived) and holders (qr_code, ticket_type); ordered `created_at desc`; loaded unpaginated. Missing customer → placeholder `{name:'غير معروف', email:'', phone:'', country_code:'+974', nationality:null}`. Loading: full-panel centered spinner; load failure toast `فشل في تحميل الطلبات`.
  - *Rebuild:* server-paginated Filament table (original hit the 1000-row cap so older orders silently disappeared).

- **F-ADM-INV-002 · Header and season toggle** — header card title `إرسال الفواتير (يدوي)`, subtitle `إرسال يدوي للفواتير - قم بتفعيل العد التنازلي للإرسال التلقائي`; toggle `الحالية` / `الأرشيف` (Archive icon) scoping the list to orders whose event `is_archived` is false / true.

- **F-ADM-INV-003 · Configuration settings** — read from `settings` (default applied when value is null **or 0**): `auto_invoice_interval_seconds` (default **60**, countdown length), `invoice_batch_min` (default **1**), `invoice_batch_max` (default **10**), `invoice_send_delay_min` (default **300** s), `invoice_send_delay_max` (default **600** s), `webhook_url` + `webhook_enabled` (WhatsApp), `email_webhook_url` + `email_webhook_enabled`. Edited in the Settings screen (section 2.x Settings).
  - *Rebuild:* `InvoiceDispatchSettings` (spatie/laravel-settings) with validation `1 ≤ min ≤ max ≤ 50`, `0 ≤ delay_min ≤ delay_max ≤ 3600`, interval ≥ 30; zero is a valid value only where meaningful (delay 0 allowed).

- **F-ADM-INV-004 · "Pending message" definition** — an order's n8n message is "pending" when `n8n_response_message` is NULL or equals exactly `جاري الإرسال إلى واتساب...` or `جاري إرسال البريد الإلكتروني...` (the placeholders written by the relay when it hands the order to n8n).

- **F-ADM-INV-005 · Event-done rule** — an order is "event done" when its event date (Qatar) is before today (Qatar), or it is the same day and the current Qatar time is **18:00** or later. Event-done orders are never sent.
  - *Rebuild:* configurable cut-off hour setting `invoice_event_cutoff_hour` (default 18), computed in Asia/Qatar on the server.

- **F-ADM-INV-006 · Attempt counting and on-hold rule** — `orders.send_attempt_count` counts send attempts. On hold = `send_attempt_count >= 2` AND (pending message OR `n8n_responded_at` is NULL). On-hold orders are excluded from automatic/batch sending until their counter is reset (F-ADM-INV-016). Batch send increments the counter **before** each send; single send increments it **only on failure**. In batch mode the counter is reset to 0 only on "actual success" = the returned message does not contain `جاري الإرسال` and contains `تم إرسال` or `بنجاح` — since the relay always returns `جاري الإرسال إلى واتساب...`, this never matches, so every order goes on hold after its second batch send unless n8n answers through `n8n-response`.
  - *Rebuild:* count only failed deliveries (HTTP error/timeout/n8n `success:false`) plus "no n8n reply within N minutes" (setting, default 30); reset on n8n success reply. Keep the threshold 2 as a setting `invoice_max_attempts`.

- **F-ADM-INV-007 · Filter tabs with counts** — five tabs (mobile short labels in brackets): `الكل (n)` [`الكل`], `قيد الإرسال (n)` [`قيد`], `تم الإرسال (n)` [`مرسل`], `موقوف (n)` (red text) [`موقوف`], `انتهت الفعالية (n)` (orange) [`انتهت`]. Formulas on the season-scoped orders: pending = (pending message OR no responded_at) AND NOT event done AND NOT on hold; sent = NOT pending message AND responded_at set; event done = event done AND (pending message OR no responded_at); on hold = on hold AND NOT event done; all = everything (original counts `الكل` over all loaded orders, not season-scoped). Empty rows (colSpan 14): pending `لا توجد طلبات قيد الإرسال`; sent `لا توجد طلبات تم إرسالها`; event done `لا توجد طلبات انتهت فعاليتها`; on hold `لا توجد طلبات موقوفة`; all `لا توجد طلبات مدفوعة عبر سداد`.
  - *Rebuild:* store a computed `invoice_dispatch_status` enum (`pending|sending|awaiting_reply|sent|on_hold|event_done`) updated by the job so tabs are SQL filters; scope `الكل` to the season.

- **F-ADM-INV-008 · Auto-send countdown** — box showing `العد التنازلي نشط ⏱️` / `العد التنازلي متوقف ⏸️` with the big remaining-seconds number. Buttons `تفعيل العد التنازلي` (green, Clock; disabled while sending; resets to the interval, toast `بدأ العد التنازلي - {interval} ثانية`) / `إيقاف العد التنازلي` (destructive; toast `تم إيقاف العد التنازلي`). Ticks every 1000 ms only while active, not sending and pending > 0; at 0 it runs a batch (F-ADM-INV-009, restart mode) and resets to the interval. Active state persisted in localStorage `invoiceCountdownActive` (`'true'|'false'`); on page load, if it was active and pending > 0 the countdown restarts automatically.
  - *Rebuild:* the switch toggles a server setting `invoice_auto_send_enabled`; the Laravel Scheduler runs `invoices:dispatch` every minute and the job self-throttles on `last_invoice_sent_at` + interval, using `Cache::lock('invoice-dispatch')` so two admins/tabs can never double-send; the page shows "next run in N s" computed from `last_invoice_sent_at` and broadcasts progress. Closing the browser no longer stops sending.

- **F-ADM-INV-009 · Send pending batch** — button `إرسال (<pendingCount>)` (Send icon); while sending `جاري الإرسال...` with spinner; disabled when sending, pending = 0 or no WhatsApp URL. Algorithm: no URL → toast `لم يتم تكوين رابط الويب هوك. يرجى تحديثه في الإعدادات`; to-send list = orders not already sent this session AND pending message AND NOT event done AND NOT on hold (original used all loaded orders, not the season scope); empty → info `جميع الطلبات تم إرسالها بالفعل` (and reset countdown when active in restart mode); batch size = `min(batchMax, max(batchMin, toSendCount))`, take the first N (newest first); toast `سيتم إرسال {n} فاتورة من أصل {total}`; for each order i: mark as sending, toast `إرسال فاتورة {i+1} من {n}...`, set `send_attempt_count = old + 1`, send (F-ADM-INV-011); success → (reset counter on "actual success"), mark sent, toast warning `الفاتورة لـ {name} - المحاولة {n} (ستوضع في الانتظار إذا لم تنجح)` when attempts ≥ 2 else `تم إرسال الفاتورة لـ {name}`; failure → mark as handled this session, toast `فشل إرسال الفاتورة لـ {name} - تم وضعها في الانتظار (محاولة {n})` (≥2) or `فشل إرسال الفاتورة لـ {name} (محاولة {n} من 2)`; if more remain: toast `انتظار {s} ثانية قبل إرسال الفاتورة التالية...` and wait a random delay `floor(random × (max − min + 1)) + min` (in ms from the seconds settings) — no wait after the last. End: clear flags, restart countdown if active (restart mode), toast `تم الانتهاء من إرسال جميع الفواتير!`.
  - *Rebuild:* the button dispatches the same job immediately ("send now"); per-order sends are chained jobs with `->delay(random delay)`; oldest-first order (as the server job does) and season-scoped; select candidates with `lockForUpdate()->skip locked` and mark `sending` before posting.

- **F-ADM-INV-010 · WhatsApp invoice payload** — keys exactly: `{order_id, booking_reference, customer_name, customer_phone, customer_phone_whatsapp, customer_email, nationality, ticket_type, quantity, total_quantity, total_amount, payment_status, qr_codes:[urls], ticket_types:[holder types], event_title, event_date, event_location, created_at, customers:<customer object>}`. `total_quantity` = sum of quantities in the current batch (single send: the order's quantity). `customer_phone_whatsapp` = `country_code` without `+` (default `974`) concatenated with `customers.phone`. QR URL per holder = `qr_code` if it starts with `http`, else `<storage public URL>/qr-codes/<qr_code>.png`.
  - *Rebuild:* server-built payload (same keys, for n8n compatibility), WhatsApp number via `PhoneNumber::toWhatsApp($phone, $cc)` (strip non-digits, `00` prefix, keep `+` numbers, avoid double country code), QR URLs from stored `qr_image_url` (signed, long-lived) and generated on demand if missing.

- **F-ADM-INV-011 · Relay call and n8n error messages** — each send invokes the server relay `send-to-webhook` (contract F-ADM-ORD-036). Client results: invoke error → toast `خطأ في الاتصال: <message>`; `data.error` → status 404 → `Webhook غير موجود أو غير مفعل في n8n. فعّل الـ workflow (Activate) أو اضغط Execute workflow إذا كنت تستخدم Test Webhook.`, else `<error> — <details.hint>` or `<error> — <solution>` or `<error>` (default `فشل الإرسال`), shown as toast `خطأ من n8n: <msg>`; success → message `data.message` or `تم الإرسال بنجاح`; exception → toast `خطأ في الاتصال بـ n8n: <msg>`. On upstream success the server marks the order "sending" (`n8n_response_message` = `جاري الإرسال إلى واتساب...`, `n8n_responded_at` NULL); n8n later sets the final message/time.

- **F-ADM-INV-012 · Per-order send** — actions-cell button `إرسال` (Send; spinner `جاري...`), shown only if the order has no message, wasn't sent this session and isn't on hold; disabled while a batch runs, this row is sending, or no WhatsApp URL. Requires URL (same toast as batch); toast `جاري إرسال فاتورة {ref}...`; success → toast `تم إرسال الفاتورة بنجاح لـ {name}`, refetch; failure → `send_attempt_count + 1`, toast `فشل إرسال الفاتورة - تم وضعها في الانتظار (محاولة {n})` (≥2) or `فشل إرسال الفاتورة: {msg} (محاولة {n} من 2)`, refetch; exception `حدث خطأ أثناء إرسال الفاتورة`.

- **F-ADM-INV-013 · Per-order e-mail invoice** — in the `البريد` column: e-mail (truncated 120 px) + mail icon button (title `إرسال الفاتورة بالبريد`; spinner while sending; disabled while this e-mail or a batch is sending); `-` if no e-mail. Requires e-mail webhook URL else toast `لم يتم تكوين رابط الويب هوك للبريد الإلكتروني. يرجى إضافته في الإعدادات`; requires customer e-mail else `لا يوجد بريد إلكتروني لهذا العميل`. Payload = WhatsApp payload minus `total_quantity`, with `action:'send_email'` first. Results: invoke error `خطأ في إرسال البريد: <msg>`; `data.error` → `خطأ: <error>`; success `تم إرسال الفاتورة بالبريد لـ {name}` + refetch; exception `حدث خطأ أثناء إرسال البريد`.
  - *Rebuild:* send via Laravel Mail (`BookingInvoiceMail`, RTL Blade, PDF attachment) as a queued job with idempotency key, logging every outcome to `email_delivery_events`; the n8n e-mail webhook kept only as an optional driver.

- **F-ADM-INV-014 · Missing/disabled WhatsApp webhook warning** — when no usable WhatsApp URL: if a URL exists but `webhook_enabled` is false → `⚠️ رابط الويب هوك (واتساب) معطّل حاليًا. فعّله من: الإعدادات ← رابط Webhook واتساب (n8n) ← بدّل المفتاح إلى «مفعّل».`; else `⚠️ لم يتم تكوين رابط الويب هوك. أضِفه من: الإعدادات ← رابط Webhook واتساب (n8n).`

- **F-ADM-INV-015 · Reset n8n status (`إعادة`)** — RotateCcw outline button, shown when the order has a message or was sent this session: sets `n8n_response_message = NULL`, `n8n_responded_at = NULL` (order returns to pending), clears session state; toast `تم إعادة تعيين حالة الطلب`; error `فشل في إعادة تعيين حالة الطلب`; refetch. No confirmation.
  - *Rebuild:* confirmation modal ("the customer may receive the invoice twice").

- **F-ADM-INV-016 · Reset attempts (`إعادة المحاولة`)** — red-outline RotateCcw button, only on on-hold rows: sets `send_attempt_count = 0`; toast `تم إعادة تعيين عدد المحاولات - يمكن إعادة الإرسال`; error `فشل في إعادة تعيين عدد المحاولات`; refetch.

- **F-ADM-INV-017 · Mark all as sent (`تحديد الكل كمرسل`)** — on the pending tab when pending > 0: button `تحديد الكل كمرسل (n)` [mobile `تحديد الكل (n)`] (CheckCircle; disabled while sending). Candidates = all loaded orders with empty `n8n_response_message` (not season/tab scoped; includes on-hold/event-done orders with NULL message, excludes "sending" placeholders); none → info `لا توجد طلبات قيد الإرسال`; toast `جاري تحديد {n} طلب كمرسل...`; bulk update `n8n_response_message = 'تم التحديد كمرسل يدويًا'`, `n8n_responded_at = now`; toast `تم تحديد {n} طلب كمرسل بنجاح`; error `فشل في تحديد الطلبات كمرسلة`. No confirmation.
  - *Rebuild:* scope to exactly the rows of the pending tab in the current season; confirmation modal with the count; audit log.

- **F-ADM-INV-018 · Table columns** — horizontal-scroll table (min width 1200), 13 columns: `الحالة`, `رقم الحجز` (mono), `اسم العميل`, `الدولة` (flag emoji from nationality, default `قطر`, unknown → 🇶🇦; mapped names: السعودية, الإمارات, قطر, الكويت, البحرين, عمان, مصر, الأردن, لبنان, العراق, سوريا, اليمن, ليبيا, السودان, الجزائر, المغرب, تونس, موريتانيا, الصومال, جيبوتي, فلسطين), `الهاتف` (LTR), `البريد` (F-ADM-INV-013), `نوع التذكرة` (raw `orders.ticket_type`), `الكمية`, `المبلغ` (`<total_amount> QAR`), `الفعالية` (title or `غير متوفر`), `تاريخ الطلب` (`dd/MM/yyyy HH:mm`), `رد n8n`, `إجراءات`.
  - *Rebuild:* order date in Asia/Qatar; amount formatted 2 dp `ر.ق`; ticket summary from holders; shared nationality → flag helper (same list as Customers).

- **F-ADM-INV-019 · Status cell states** — decision order: (1) message present and not a pending message → green badge (blue if e-mail): `📧 تم الإرسال بالإيميل` if message contains any of `اميل`, `إيميل`, `بريد`, `email`, `Email`, `جاري إرسال البريد`; `💬 تم الإرسال بالواتساب` if it contains `واتساب`, `whatsapp`, `WhatsApp`; else `تم الإرسال`; plus `n8n_responded_at` `dd/MM/yyyy HH:mm:ss` and the message in italics; (2) sent this session → `تم الإرسال` + local send time + message; (3) e-mail being sent for this row → blue spinner badge `📧 جاري الإرسال بالإيميل...`; (4) row being sent in a batch → spinner badge `💬 جاري الإرسال...`; (5) pending placeholder message present → spinner badge `📧 بانتظار رد الإيميل...` (e-mail placeholder) / `💬 بانتظار رد الواتساب...`; (6) a batch is running → Clock badge `قيد الانتظار`; (7) event done → orange CalendarX badge `انتهت الفعالية`; (8) on hold → red PauseCircle badge `موقوف` + `محاولات: <count>`; (9) else outline `لم يتم الإرسال`.
  - *Rebuild:* derive from the stored `invoice_dispatch_status` + `last_channel` instead of keyword matching on free text.

- **F-ADM-INV-020 · n8n reply column (`رد n8n`)** — if any message: badge `تم الاستلام` (CheckCircle) + `n8n_responded_at` + message text; else `-`.
  - *Rebuild:* show `تم الاستلام` only for a real n8n reply (`n8n_responded_at` set); show `بانتظار الرد` for placeholders (original showed "received" for the "sending..." placeholder).

- **F-ADM-INV-021 · E-mail log button and delivery timeline dialog** — actions-cell button `سجل البريد` (History, ghost, title `سجل حالة إرسال البريد`), always shown. Dialog (max-w-lg, RTL) title `سجل إرسال الفاتورة بالبريد` (Mail icon), description `حجز <ref> — <email|بدون بريد>`. Body lists `email_delivery_events` of the order (id, status, attempt, detail, recipient, created_at) newest first as a vertical timeline: spinner while loading; empty `لم يتم إرسال أي فاتورة بالبريد لهذا الحجز بعد.`; each entry: status badge (`queued` `في قائمة الإرسال` amber; `retried` `إعادة محاولة` blue; `sent` `تم الإرسال` emerald; `failed` `فشل الإرسال` red; `suppressed` `محظور من الاستقبال` orange; `bounced` `ارتد البريد` red; `complained` `بلاغ إزعاج` red; `unsubscribed` `إلغاء الاشتراك` muted; unknown (e.g. `skipped`) → raw status muted), time in Qatar (`ar-u-nu-latn`, dateStyle medium, timeStyle short), `• المحاولة N` when attempt > 1, detail text, recipient (LTR). Live: new events for that order are appended in real time. Server side, the invoice mailer logs `queued` (`تمت جدولة الإرسال`) for attempt 1 or `retried` (`إعادة إرسال — المحاولة رقم <n>`), then `sent`/`failed`/`suppressed`/`skipped`; provider webhooks add `bounced`/`complained`/`unsubscribed`.
  - *Rebuild:* `email_delivery_events` written by the mail job and by provider webhooks (SES/Postmark) with the real template name (original stamped `booking-invoice` on every provider event); broadcast `EmailDeliveryEventRecorded` on `private-admin.orders.{id}`; add a "resend invoice e-mail" action (server `force` flag) in the dialog.

- **F-ADM-INV-022 · Live refresh on Sadad order updates** — any UPDATE of an order with `payment_method = 'sadad'` triggers a full reload of the list (channel `orders-n8n-updates`).
  - *Rebuild:* broadcast only the changed row and patch it in the table.

- **F-ADM-INV-023 · Legacy exclusion of 6–8 Nov 2025 orders** — orders whose `created_at` (browser local date) falls on **6, 7 or 8 November 2025** are hidden from this console and never sent (hard-coded legacy data rule, also in the server auto-send job).
  - *Rebuild:* replace with a configurable list of excluded date ranges (`invoice_excluded_ranges` setting, default empty) or a per-order `invoice_suppressed` flag set by a one-off migration for those orders.

- **F-ADM-INV-024 · Server-side auto-send job (existing server contract)** — the original also has an unauthenticated server function `auto-send-invoices` meant to be triggered by an external scheduler: reads `webhook_url`, interval, `last_invoice_sent_at`, batch min/max; returns `Interval not reached yet` with `seconds_remaining` if called early; selects confirmed Sadad orders with `n8n_response_message IS NULL` oldest first (limit `batchMax × 2`), drops the Nov-2025 dates and event-done orders, sends up to `batchMax` sequentially with the same payload (without `order_id`/`customers`), success = HTTP OK and `response.success !== false` → `n8n_response_message = response.message || 'تم الإرسال تلقائياً'`, `n8n_responded_at = now`; always updates `last_invoice_sent_at`; returns `{success, message:'Sent X/N invoices successfully', batch_size, total_quantity, results, next_send_in_seconds}`.
  - *Rebuild:* this becomes the single implementation (`DispatchInvoicesJob`) driving F-ADM-INV-008/009: authenticated (scheduler only), locked, respects `webhook_enabled`, timeouts (10 s), Qatar-time math, the delay/attempt/hold rules above.

**Upgrades (new, not in original)**
- NEW: Per-order "send log" listing every WhatsApp attempt (time, result, n8n reply) from a `notification_attempts` table.
- NEW: Rate-limit guard and daily cap per WhatsApp number.

**Original defects to avoid:**
- Scheduler (countdown + 5–10 min sleeps) runs in the admin's browser tab: closing the tab stops it, two tabs double-send; the server job has no auth and no lock.
- Attempt-reset heuristic never matches → delivered orders go on hold after two sends.
- `الكل` count, send list and mark-all are not scoped to the selected season/tab.
- Unpaginated load (1000-row cap) and full reload on every Sadad order update.
- Hard-coded 6–8 Nov 2025 exclusion, evaluated in browser/runtime-local time.
- QR URLs built from the code text instead of the stored `qr_image_url`; phone number concatenation ignores existing `+`/leading zeros.
- Client builds the n8n payload (relay mode) instead of server "notify" mode.
- `رد n8n` shows `تم الاستلام` for the pending "sending..." placeholder; status derived by keyword matching on free text.
- `webhook_enabled` ignored by the server job; settings value 0 silently replaced by defaults.
- Dead imports (`generateInvoicePdf`, `FileDown`, `useSettings`).

---

### ADM-PERR — Payment errors log

Log viewer of failed payment attempts (table `payment_errors`): who, when, how many tickets, amount, source (Sadad / bank-card / site) and reason; filter, search, delete, live updates. Original `/admin/dashboard/payment-errors`; new app: Filament resource `PaymentErrorResource` (list only) at `/admin/payment-errors`.

- **F-ADM-PERR-001 · Error log list (latest 500)** — loads the latest **500** rows ordered `created_at desc` (id, created_at, order_id, booking_reference, event_id, customer_name, customer_phone, quantity, amount, payment_id, error_source, error_code, error_message, event title/date/is_archived); all filtering happens client-side on those 500. Loading: centered spinner; load error toast `فشل في تحميل أخطاء الدفع`.
  - *Rebuild:* server-side pagination (25/50/100) and filtering; no cap.

- **F-ADM-PERR-002 · Header** — title `أخطاء الدفع` with red AlertTriangle; subtitle `كل محاولة دفع فاشلة مع الاسم والوقت وعدد التذاكر والمبلغ وسبب الرفض`.

- **F-ADM-PERR-003 · Current / archived toggle** — segmented `الحالية` / `الأرشيف` (Archive icon). A row is archived when its event `is_archived = true`; rows without an event count as **current**.

- **F-ADM-PERR-004 · Source filter with counts** — buttons `الكل (n)`, `سداد (n)`, `البنك / البطاقة (n)`, `الموقع (n)` filtering `error_source` (`sadad` / `bank` / `site`); counts computed within the current season view, ignoring the search.

- **F-ADM-PERR-005 · Search** — input (min 200 px) placeholder `بحث بالاسم أو الهاتف أو رقم الحجز`; case-insensitive substring over `customer_name`, `customer_phone`, `booking_reference`, `payment_id`.

- **F-ADM-PERR-006 · Errors table** — min width 980; columns: `الوقت` (Qatar, `ar-u-nu-latn`, numeric year, 2-digit month/day/hour/minute, LTR), `العميل` (or `—`), `الهاتف` (LTR or `—`), `التذاكر` (quantity or `—`), `المبلغ` (`x.toFixed(2) ر.ق` or `—`), `المصدر` (outline badge: sadad amber `سداد`, bank destructive `البنك / البطاقة`, other muted `الموقع`), `سبب الخطأ` (`error_message || error_code || —`, clamped to 3 lines, max 320 px, with the event title beneath in small muted text), `رقم الحجز` (LTR, `payment_id` beneath in small text), `إجراءات`. Empty (colSpan 9): `لا توجد أخطاء دفع مسجّلة`.
  - *Rebuild:* make the booking reference a link to the order (original had no link).

- **F-ADM-PERR-007 · Delete a log row** — ghost trash button per row (spinner while deleting); no confirmation; deletes the row, removes it locally; toast `تم حذف السجل`; error `فشل في حذف السجل`.
  - *Rebuild:* replace hard delete with "mark resolved" (`resolved_at`, `resolved_by`, note) + a "show resolved" filter; hard delete only for super_admin with confirmation.

- **F-ADM-PERR-008 · Refresh and live updates** — `تحديث` button (RefreshCw) reloads; any insert/update/delete on `payment_errors` triggers a full refetch (channel `payment_errors_changes`).
  - *Rebuild:* broadcast `PaymentErrorRecorded` and prepend the row.

- **F-ADM-PERR-009 · Automatic source classification of logged errors** — when an error is recorded, `classifyPaymentError(message, code)` on lower-cased `message + code`: **bank** if it contains any of `card`, `بطاقة`, `declin`, `مرفوض`, `insufficient`, `رصيد`, `bank`, `بنك`, `expired card`, `منتهية`, `cvv`, `3d`, `authorization`, `تفويض`, `issuer`; else **sadad** if it contains any of `sadad`, `سداد`, `merchant`, `checksum`, `gateway`, `بوابة`; else **site**. Writers: the public browser (`logPaymentError`, stores `raw` with `url` and `userAgent`, never throws) and the server payment wiring (`recordPaymentError`, fills customer/event details from the order). Table: `id uuid`, `created_at` default now, `order_id` → orders ON DELETE SET NULL, `booking_reference`, `event_id` → events ON DELETE SET NULL, `customer_name`, `customer_phone`, `quantity int`, `amount numeric`, `payment_id`, `error_source text not null default 'site'`, `error_code`, `error_message`, `raw json not null default '{}'`; indexes on `created_at desc` and `event_id`.
  - *Rebuild:* `PaymentErrorClassifier` service with the same keyword lists (unit-tested); only the server writes rows — the public site reports client-side failures to a validated, rate-limited endpoint (`POST /api/payment-errors`, throttle 10/min per IP, size-limited `raw`).

**Original defects to avoid:**
- Anonymous INSERT allowed with no validation or rate limit (table can be spammed from the public site).
- 500-row cap with client-side filtering.
- Delete without confirmation and no "resolved" concept.
- No link from a row to its order.

---

### ADM-SDD — Sadad diagnostic panel & Sadad transaction dialog

Two Sadad tools: the "is online payment ready?" diagnostic card embedded in Settings (original `SadadDiagnostic` inside `/admin/dashboard/settings`), and the modal showing the live Sadad record of one order (original `SadadTransactionDialog`, opened from Orders `تفاصيل سداد`). New app: a Livewire component on the Filament Settings → Payments page, backed by `POST /admin/sadad/diagnose`, and a Filament modal action `ShowSadadTransaction` backed by `App\Payments\Sadad\SadadClient::getTransaction()`.

- **F-ADM-SDD-001 · Run readiness check** — card titled `فحص جاهزية الدفع` (ShieldCheck) with button `تشغيل الفحص` (while running `جارٍ الفحص...` + spinner, disabled). Before the first run: help text `احفظ إعدادات سداد ثم شغّل الفحص: يتأكد من الإعدادات ويجرّب الاتصال بسداد فعلياً (اختبار أو إنتاج) ويعرض الروابط التي يجب تسجيلها في لوحة سداد.` Calls server `sadad-diagnose` (POST only, else 405; admin only). Failure → report cleared + mapped Arabic API error toast.

- **F-ADM-SDD-002 · Verdict and environment badges** — after a run: badge `جاهز للدفع` (default) when no check has status `error`, else `يحتاج إلى إصلاح` (destructive); environment badge `بيئة الاختبار (Sandbox)` or `بيئة الإنتاج (Live)` (only when detected). Response `{success:true, ready, environment:'live'|'sandbox'|null, callbackUrl, webhookUrl, checks:[{id, status, message}]}`; the secret is never returned.

- **F-ADM-SDD-003 · Check: merchant ID** — id `merchant_id`, title `معرف التاجر`: ok `Merchant ID: <id>` / error `Merchant (Sadad) ID is not set` (from private setting `sadad_merchant_id`).

- **F-ADM-SDD-004 · Check: secret key** — id `secret`, title `المفتاح السري`: ok `Secret key is set (<n> characters)` / error `Secret key is not set` (`sadad_secret` / `sadad_api_key`).

- **F-ADM-SDD-005 · Check: registered website domain** — id `website`, title `النطاق المسجل`: error `Website domain is not set`; warn if the value contains `/`, `:` or whitespace: `"<domain>" should be just the domain registered with Sadad (no https:// or path)`; ok `Website: <domain>` (`sadad_website_domain`).

- **F-ADM-SDD-006 · Check: API login and transaction lookup** — only when checks 003–005 are set; otherwise `api_login` error `Skipped: complete the settings above first`. Environment detection per `sadad_environment` (`auto` | `sandbox` | `live`, default `auto`): `auto` tries Sadad login on live (`https://api-s.sadad.qa`) then sandbox (`https://api-sandbox.sadad.qa`); otherwise only the configured one. `api_login` (title `الاتصال بواجهة سداد`): ok `Sadad API login works (TEST/sandbox|LIVE credentials)`; failure → error `Sadad API login failed (<errors>). Payments cannot be verified automatically: check the merchant ID, the secret/API key and that the website domain matches the one the key was generated for.` After a successful login, `api_lookup` (title `التحقق من المدفوعات`) looks up the fake reference `DIAGNOSTIC-PING`: error `Sadad transaction lookup failed (<err>)` when the API is unavailable, else ok `Payment verification (transaction lookup) works`.

- **F-ADM-SDD-007 · Checks: callback, webhook registration, automation, site URL** — `callback_url` (title `رابط العودة بعد الدفع`): ok `Customers return from Sadad to: <base>/functions/v1/sadad-callback`; `webhook_registration` (title `تسجيل الـ Webhook في سداد`): warn `Register this webhook URL in the Sadad merchant panel (Payment Gateway → Webhook): <base>/functions/v1/sadad-webhook`; `automation` (title `الأتمتة (n8n)`): ok `Automation (n8n) webhook is configured` / warn `No automation (n8n) webhook is configured: tickets will not be sent automatically` (`webhook_url` + `webhook_enabled`); `site_url` (title `عنوان الموقع`): ok `Customers are returned to <site_url>` / warn `Site URL is not set: customers return to the address they ordered from (fine for a single site)`.
  - *Rebuild:* URLs become `https://<app>/api/sadad/callback` and `https://<app>/api/sadad/webhook`; all check messages translated to Arabic (`lang/ar/sadad.php`), English kept in `lang/en`.

- **F-ADM-SDD-008 · Check list rendering** — each check row: icon by status (ok green CheckCircle2, warn amber TriangleAlert, error red AlertCircle), Arabic title by id (unknown id → raw id), message text below.

- **F-ADM-SDD-009 · Copyable Webhook and Callback URLs** — box headed `سجّل هذا الرابط في لوحة سداد (Payment Gateway ← Webhook):` with two copy fields: `Webhook URL` and `Callback URL (يُرسل مع كل طلب تلقائياً)`. Each = label + selectable `<code dir=ltr>` + copy icon button (aria-label `copy`) → toast `تم النسخ`; clipboard failure ignored.

- **F-ADM-SDD-010 · Sadad transaction dialog shell** — opened with an order id (open while non-null) and optional booking reference. Dialog (max-w-lg, RTL) title `تفاصيل عملية سداد` (ReceiptText), screen-reader description `تفاصيل عملية الدفع عبر سداد`, booking reference in monospace under the title. Fetches on open and on `تحديث من سداد` (RefreshCw, shown when not loading). Loading: spinner + `جارٍ الاتصال بسداد…`.

- **F-ADM-SDD-011 · Sadad transaction server contract** — `sadad-transaction` (POST `{orderId?: uuid, transactionno?: string 1..100}`): requires an authenticated admin (401 `Unauthorized`, 403 `Forbidden`); at least one field → else 400 `يجب تحديد الطلب أو رقم عملية سداد`; other invalid body → `بيانات الطلب غير صالحة`; with orderId: order missing → 404 `الطلب غير موجود`; method ≠ sadad → 400 `هذا الطلب ليس عبر سداد`; transaction number = `payment_id`; none → 400 `لا يوجد رقم عملية سداد لهذا الطلب`. Uses `sadad_merchant_id`, `sadad_website_domain` (default `qcamelmc.org`) and the dedicated API-login secret (`SADAD_API_SECRET_KEY`; checksum keys intentionally not used); missing → 200 `{success:false, code:'SADAD_API_LOGIN_NOT_CONFIGURED', error:'تفاصيل العملية غير متاحة حالياً: مفتاح API Login الخاص بسداد غير مضبوط.'}`. Login POST `/api/userbusinesses/login` `{sadadId:Number(merchantId), secretKey, domain}` → `accessToken`; failure → 200 `{success:false, code:'SADAD_API_LOGIN_REJECTED', error:'تعذر التحقق من بيانات API Login لدى سداد. تحقق من المفتاح المخصص للـ API والنطاق <domain>.'}`. Lookup POST `/api/transactions/getTransaction` (headers `Authorization: <token>`, `Origin: https://<domain>`, body `{transactionno}`); non-OK or `tx.error` → 404 (Sadad 404) else 502 with `tx.error.message || 'تعذر جلب تفاصيل العملية من سداد'`. Success → `{success:true, transaction:{transactionno: invoicenumber ?? transactionNo, status: UPPER(transactionstatus.name) || 'UNKNOWN', statusAr, isRefund, amount, commission: servicecharge, refundCharge: refundcharge, netAmount: round3(amount − commission − refundCharge), mode: transactionmode.name|null, entity: transactionentity.name|null, transactiondate|null, websiteRefNo: website_ref_no|null}}`; unhandled → 500 `{error: message || 'خطأ غير متوقع'}`.
  - *Rebuild:* host chosen by the configured environment (original hard-coded the live host here), domain from settings (no hard-coded default), access token cached in Redis until expiry, response mapped to a `SadadTransactionData` DTO and persisted to the stored Sadad columns (see F-ADM-TRX-008).

- **F-ADM-SDD-012 · Transaction data view** — header strip: `حالة العملية` + Arabic status badge (SUCCESS emerald-600 solid; FAILED/REJECTED destructive; REFUND/REFUNDED sky-600; PENDING/INPROGRESS/IN PROGRESS amber-500; ONHOLD/ON HOLD slate-500; else muted) and `المبلغ` big `x.xx ر.ق`. Rows: `رقم العملية` (transactionno); `عمولة سداد` `- x.xx ر.ق` (amber); `رسوم الاسترداد` `- x.xx ر.ق` (sky, only when > 0); `الصافي بعد العمولة` (emerald); `وسيلة الدفع` (when mode present); `تاريخ العملية` (raw, when present); refund note `تمت عملية استرداد على هذه المعاملة.` when `isRefund`.
  - *Rebuild:* also show `entity` and `website_ref_no` (shown only in Transactions originally) and flag a mismatch between `website_ref_no` and the booking reference.

- **F-ADM-SDD-013 · Readable error messages** — error box (AlertCircle): server `error` parsed from the response body even on non-2xx (so "not configured" / "login rejected" texts appear), else `تعذر الاتصال بسداد. حاول مرة أخرى لاحقاً.` (invoke failure without body), `لم تُرجع سداد تفاصيل لهذه العملية` (success=false without message), fallback `تعذر جلب تفاصيل العملية من سداد`.

**Original defects to avoid:**
- Diagnostic messages in English inside an Arabic UI.
- Sadad login on every call (no token cache); live host and default domain `qcamelmc.org` hard-coded in the transaction lookup while diagnose/verify support sandbox.
- Dialog refetches only when the order id changes (re-opening the same order relies on the parent resetting it).
- Entity and website reference not shown in the dialog.

---

### ADM-TKT — Ticket types, inventory & booked tickets

Ticket inventory and sales screen per event date: ticket types (VIP / normal / parking) with price, capacity, remaining, sold %, payment-status breakdown, edit dialog, daily sales statistics, stock recount, current vs archived; plus the embedded "booked tickets by date" panel (individual holders with scan status, QR, details, recycle bin). Also documents the POS ticket tile component (`TicketAddItem`) that reads the same inventory. Original `/admin/dashboard/tickets`; new app: Filament page `Admin\Pages\TicketInventory` at `/admin/tickets` with a Livewire sub-component `BookedTicketsPanel`, and `EditTicketType` modal action. Ticket-type creation/deletion lives in the Events module (not here).

- **F-ADM-TKT-001 · Header and loading** — title `إدارة التذاكر`; loading text `جاري التحميل...` centered; load error toast `فشل التحميل`. Empty (no ticket types in the selected season): `لا توجد تذاكر`.

- **F-ADM-TKT-002 · Current / archived toggle** — `الحالية` / `الأرشيف` buttons filtering ticket types by their event's `is_archived`; also filters the daily statistics. Does not affect the booked-tickets panel.

- **F-ADM-TKT-003 · Grouping by event-date tabs and by type** — ticket types (`tickets` with event title, event_date, location, is_archived; ordered `price desc`) are grouped per event **date string**; one tab per date, sorted ascending, trigger label = date in `en-US` long format with weekday; default tab = earliest date (set only at mount). Each tab: event card (title, location); then three sections, each with a heading badge + long label: `VIP` + `تذكرة دخول VIP`; `عادي` + `تذكرة دخول عادية`; `مواقف` + `تذكرة موقف السيارات`; each section a grid (1/2/3 columns) of ticket cards.
  - *Rebuild:* group by `event_id` (two events with the same timestamp merged originally); Arabic date labels (`ar` locale, Latin digits) in Asia/Qatar.

- **F-ADM-TKT-004 · Ticket card (stock)** — Ticket icon; type name; price `x.xx ريال قطري`; edit icon button (opens F-ADM-TKT-011); `متاح` = remaining = `available_quantity − sold` coloured by sold % (≥ 90 % red-600, ≥ 70 % yellow-600, else green-600; capacity 0 treated as 100 %); `مُباع` = sold; `المباع` + `<pct>%` (`sold / capacity × 100`, 0 decimals) with a progress bar. `available_quantity` is the **total capacity** of the type. The displayed `sold` is recomputed client-side as the number of holders of **confirmed** orders for that event + type (it overrides the DB `tickets.sold_quantity`, which counts pending + confirmed seats).
  - *Rebuild:* show four numbers from one SQL aggregate: capacity, held (pending + confirmed = `sold_quantity`), confirmed, remaining = capacity − held; colour and % on held; guard capacity 0 (no NaN/Infinity).

- **F-ADM-TKT-005 · Payment breakdown per ticket type** — section `حالة الدفع` with three tiles counting holders of that event + type by their order status: `مدفوعة` (confirmed, emerald), `قيد الانتظار` (pending, amber), `فاشلة` (cancelled/other, destructive).
  - *Rebuild:* `GROUP BY event_id, ticket_type, payment_status` query (original scanned all holders in the browser twice per load, up to 60 000 rows).

- **F-ADM-TKT-006 · Low-stock and sold-out badges** — remaining ≤ **10** and > 0 → destructive badge `تذاكر محدودة متبقية!`; remaining = 0 → secondary badge `نفذت الكمية`; negative remaining shows nothing.
  - *Rebuild:* negative remaining (over-sold) shows a red `تجاوز السعة` badge; low-stock threshold as a setting (default 10).

- **F-ADM-TKT-007 · Recount stock** — header button `إعادة حساب المخزون` (RefreshCw spinning while running; title `يعيد حساب المباع من الحجوزات الفعلية`) → server `recount_ticket_stock()` (admin only, else `forbidden`) recomputing every `sold_quantity` from live orders (F-ADM-ORD-041), returning the number of rows corrected. Toast `تم تصحيح عدّاد {n} نوع تذكرة` when n > 0, else `عدّادات التذاكر صحيحة بالفعل`; error → mapped API message; then refetch tickets.
  - *Rebuild:* `RecountStock` action wrapping the `StockLedger::recount()` service (also an artisan command and nightly scheduled check); audit log of corrected rows (old → new).

- **F-ADM-TKT-008 · Daily sales statistics table** — card `إحصائيات المبيعات اليومية` with button `إخفاء` (EyeOff); when hidden and summaries exist, header button `إظهار الإحصائيات` (Eye). Visibility persisted in localStorage `tickets_show_daily_stats` (default shown). Built from holders of **confirmed** orders, grouped by event date (browser-time-zone `en-CA` date) + archived flag, filtered by the season toggle, sorted by date ascending. Header row 1: `التاريخ`, `VIP` (colspan 3), `عادي` (3), `مواقف` (3), `الإجمالي اليومي`; row 2: `العدد`, `السعر`, `المبلغ` × 3. Body row per date: date (`en-US` weekday long, month long), per type count (or `-`), price `x.xx` (or `-`), amount `x.xx` in destructive colour (or `-` when 0), daily total `x.xx ريال قطري`. Amount = count × **current** ticket price of that event + type; per-type price = last non-zero price seen. Final row `الإجمالي الكلي` with summed counts/amounts and grand total `x.xx ريال قطري` (its price cells show the first row's prices).
  - *Rebuild:* SQL aggregation on the stored price paid per holder (`ticket_holders.unit_price`) grouped by Qatar event date; Arabic dates; grand-total price cells left blank; XLSX export (NEW).

- **F-ADM-TKT-009 · Per-event sales tiles** — inside each event tab, when that date has a summary: card `إحصائيات المبيعات` with 4 tiles: `VIP` (count + amount `x.xx ريال قطري`), `عادي`, `مواقف`, `الإجمالي اليومي` (big total + `ريال قطري`).

- **F-ADM-TKT-010 · Embedded booked-tickets panel** — the panel F-ADM-TKT-014…029 is always rendered at the top of the page, independent of the season toggle.

- **F-ADM-TKT-011 · Edit ticket type dialog** — title `تعديل التذكرة`; fields: `نوع التذكرة` (disabled input showing `تذكرة دخول VIP` / `تذكرة دخول عادية` / `تذكرة موقف السيارات` / raw); `السعر (ريال قطري)` (number, step 0.01, required); `الكمية المتاحة` (number, required; helper `تم بيع {sold} تذكرة حتى الآن` with the confirmed count from the card); `الوصف` (textarea 3 rows, placeholder `أدخل وصف التذكرة (اختياري)`, helper `إذا لم يتم إدخال وصف، سيتم استخدام الوصف الافتراضي` — public site falls back to the i18n defaults such as `مقاعد مميزة، وصول حصري` for VIP and `دخول عام لجميع مناطق وأنشطة الفعالية` for normal). Footer `إلغاء` (outline) / `حفظ` (`جاري التحميل...` while saving; double-submit guarded). Form re-initialised whenever the ticket changes.

- **F-ADM-TKT-012 · Edit ticket validation and save** — price must parse to a finite number ≥ 0 else toast `يرجى إدخال سعر صحيح (رقم أكبر من أو يساوي صفر)`; quantity integer ≥ 0 else `يرجى إدخال عدد تذاكر صحيح`; quantity ≥ sold else `لا يمكن أن يقل العدد عن التذاكر المباعة ({sold})`. Save updates `tickets {price, available_quantity, description: trimmed or null}`; toast `تم الحفظ بنجاح`, callback refreshes the ticket list (daily statistics not refreshed), close; error `فشل في تحديث التذكرة`.
  - *Rebuild:* Form Request rules `price: decimal(0,2)|min:0`, `available_quantity: integer|min:<held seats>` validated server-side under a row lock against `sold_quantity` (pending + confirmed); price change never alters historical revenue (price snapshot on holders); activity-log entry with old/new values.

- **F-ADM-TKT-013 · POS ticket tile (`TicketAddItem`)** — component used by the POS ticket grid (`/admin/pos`; staff: admin, moderator or passcode). Props: ticket (id, type, price, available_quantity, sold_quantity, event_id), `onAddToCart(ticket, qty)`, type-name resolver, optional actual sold count (the POS passes the real holder count per type, fallback `sold_quantity`). `available = max(0, available_quantity − soldCount)`. Visual per type: vip → Crown, label `VIP`, colour `--ticket-vip`, gradient `--gradient-gold`; normal → Ticket, `عادي`, `--ticket-normal`; parking → Car, `مواقف`, `--ticket-parking`; unknown → Ticket + resolved name. Card with 4 px top border in the type colour; icon tile; title; subtitle `متاح: {available} تذكرة`; price (LTR, type colour) + caption `ريال`. Quantity stepper: starts at 1; `−` (aria `تقليل الكمية`) min 1; number input (min 1, max available; typed values accepted only if integer 1..available); `+` (aria `زيادة الكمية`) max available; button `إضافة` adds `qty` to the cart and resets to 1.
  - *Rebuild:* Livewire/Alpine component in the POS (section on POS); cap by `available − quantity already in cart` (original allowed exceeding availability across clicks; server order creation enforces stock).

- **F-ADM-TKT-014 · POS tile sold-out state** — when available = 0: card at 60 % opacity, destructive border, not-allowed cursor, subtitle `نفذت الكمية` (red), add button `نفذت` disabled.

- **F-ADM-TKT-015 · Booked tickets panel header** — CalendarDays icon, title `التذاكر المحجوزة`, subtitle `حسب تاريخ الفعالية — اضغط على رمز QR لعرضه مكبّراً أو على التفاصيل لعرض بيانات التذكرة`; (admin only) button `سلة المحذوفات` (Trash2); button `تحديث` (RefreshCw spinning while loading). Manual refresh error toast `تعذر تحميل التذاكر المحجوزة`.

- **F-ADM-TKT-016 · Date filter** — segmented `اليوم` (default), `غداً`, `يوم محدد`, `الكل`. `يوم محدد` reveals a date input (LTR, w-44) defaulting to today in Qatar. Day key = Qatar `YYYY-MM-DD`; tomorrow = now + 1 day then Qatar key; range on the order's `events.event_date` = `[key T00:00:00+03:00, +1 day)`. `الكل` = no date filter and no season filter (every season).

- **F-ADM-TKT-017 · Scan filter with counts** — segmented `كل التذاكر`, `ممسوحة (n)` (ScanLine), `غير ممسوحة (n)` (Clock); scanned = `is_present`; counts over the loaded day (not search-filtered).
  - *Rebuild:* add a payment filter defaulting to "paid only" so pending/cancelled tickets are not counted as `غير ممسوحة`.

- **F-ADM-TKT-018 · Search** — input (max-w-xs) placeholder `بحث بالاسم أو الهاتف أو رقم الحجز…`; matches holder name (case-insensitive), holder phone (substring), booking reference (ci), customer name (ci), customer phone. Count badge `{n} تذكرة` shows the filtered count.

- **F-ADM-TKT-019 · Data loading** — holders (id, name, phone, ticket_type, qr_code, is_present, confirmed_at, confirmed_by_name, created_at) with their order (id, booking_reference, payment_status, payment_method, total_amount, created_at, customer name/email/phone, event title/event_date/location/is_archived), ordered by holder `created_at desc`, fetched in 1000-row pages up to **40** pages; holders without an order dropped; ALL payment statuses included. Loading `جاري التحميل…`; empty `لا توجد تذاكر محجوزة لهذا اليوم` (also for `الكل`).
  - *Rebuild:* server pagination (cursor, 60 per page) with infinite scroll; counts from SQL.

- **F-ADM-TKT-020 · Booked ticket card** — grid 1/2/3 columns (md/xl). Content: small grey clock at the top-left when not scanned (title/aria `لم تُمسح بعد`); type icon; holder name; phone (LTR); type badge (`VIP` amber, `مواقف` blue, `عادي` primary); line `<booking_reference> • <event title>`; source badge `نقاط البيع` (cash_pos) / `دفع إلكتروني` (otherwise) + `تاريخ البيع: <Qatar dd/MM/yyyy, HH:mm>` (order created_at, fallback holder created_at; `ar` Latin digits); when scanned a green box `تم المسح` + scan time `dd/MM HH:mm` (Qatar) + `— <confirmed_by_name>`; bottom: payment badge (`مدفوعة` success / `قيد الانتظار` amber / `فاشلة / ملغاة` destructive) and buttons `QR`, `التفاصيل` (Info), `حذف` (F-ADM-TKT-024).
  - *Rebuild:* scan time/scanner from dedicated `scanned_at`/`scanned_by` fields (original reused `confirmed_at`/`confirmed_by_name`, which manual payment confirmation also overwrote).

- **F-ADM-TKT-021 · Enlarged QR (paid & unscanned only)** — `QR` button only on unscanned tickets; disabled with title `رمز QR متاح للتذاكر المدفوعة فقط` unless the order is `confirmed`, else title `عرض رمز QR`. Dialog title `<type label> — <holder name>`, description = the encoded code (LTR), QR generated from `qr_code` (or the booking reference if the holder has no code) width **512**, margin **1**, displayed 288×288 with border (pulsing placeholder while generating), footer `وجّه الكاميرا نحو الرمز للمسح`. Error toast `تعذر إنشاء رمز QR`.
  - *Rebuild:* never fall back to the booking reference (that string is not a valid gate code); serve the PNG server-side.

- **F-ADM-TKT-022 · Ticket details dialog** — title `تفاصيل التذكرة`, description = booking reference (LTR). 14 tiles: `اسم حامل التذكرة`, `رقم الهاتف` (LTR), `نوع التذكرة`, `الحالة` (`حاضر ✓` / `لم تُمسح بعد`), `وقت المسح` (Qatar full date-time or `—`), `المسحة بواسطة` (scanner name or `—`), `العميل`, `بريد العميل` (LTR), `الفعالية`, `تاريخ الفعالية` (Arabic long with weekday, Qatar), `الموقع`, `طريقة الدفع` (`سداد (إلكتروني)` / `نقاط البيع`), `المبلغ الإجمالي للحجز` (`x.xx ر.ق`), `تاريخ الحجز`; then `حالة الدفع:` + payment badge; when scanned a green note `تم المسح في <time>[ — بواسطة <name>]`.

- **F-ADM-TKT-023 · Live scan updates and silent refresh** — realtime channel `booked-tickets-live`: holder UPDATE patches `is_present`, `confirmed_at`, `confirmed_by_name` of the matching card in place; holder INSERT triggers a silent refetch. Additionally a silent auto-refresh every **10 000 ms** (no spinner).
  - *Rebuild:* broadcast `TicketScanned` / `TicketHolderCreated` on `private-admin.tickets.{date}`; drop the 10 s polling (fallback `wire:poll.30s` only when the socket is disconnected).

- **F-ADM-TKT-024 · Delete ticket to recycle bin** — button `حذف` (destructive ghost, title `حذف التذكرة (تُنقل إلى سلة المحذوفات)`, disabled while busy) visible only when the user is admin (`admin_users` row or `admin` role) AND setting `show_delete_ticket_button` (default **true** when null). Browser confirm `حذف تذكرة {name}؟ ستُنقل إلى سلة المحذوفات ويمكن استرجاعها لاحقاً.` → server `bin_ticket_holder(holder_id)` → `{success, message}`: non-admin → `هذه العملية متاحة للأدمن فقط`; not found → `التذكرة غير موجودة`; otherwise snapshots context `{booking_reference, payment_status, payment_method, event_title, event_date, customer_name}`, records the admin e-mail as `deleted_by_name`, inserts into `deleted_tickets` (holder_id, order_id, name, phone, nationality, ticket_type, qr_code, is_present, confirmed_at, confirmed_by, confirmed_by_name, id_number, country_code, holder_created_at, context, deleted_by, deleted_at now), deletes the holder (seat freed if the order is active), returns `تم نقل التذكرة إلى سلة المحذوفات`. Client: failure toast message or `تعذر حذف التذكرة`; success toast message or `تم نقل التذكرة إلى السلة`; removes the card; refreshes the bin if open. Deleted tickets do not appear in any report.
  - *Rebuild:* soft-delete the holder (`deleted_at`, `deleted_by`, `delete_context` JSON) inside a transaction that releases the seat and recalculates `orders.quantity`/`total_amount` (or records an adjustment); keep `qr_image_url`; add the code to `expired_qr_codes` so the gate rejects it.

- **F-ADM-TKT-025 · Recycle bin dialog** — dialog (sm:max-w-2xl) title `سلة المحذوفات` (red Trash2), description `التذاكر المحذوفة لا تظهر في أي تقرير — يمكن استرجاعها في أي وقت`; button `تحديث`; list (max 55vh scroll) of the latest **500** `deleted_tickets` by `deleted_at desc`: loading `جاري التحميل…`; empty `السلة فارغة`; rows: type icon (red), name + type badge, `<booking_reference|—> • <event_title>`, `تاريخ البيع: <holder_created_at>`, scan line (`تم المسح <time> — <name>` green or `لم تُمسح`), `حُذفت في <deleted_at Qatar>[ — بواسطة <deleted_by_name>]`, button `استرجاع` (Undo2, disabled while busy). Load error toast `تعذر تحميل سلة المحذوفات`.

- **F-ADM-TKT-026 · Restore ticket from recycle bin** — server `restore_ticket_holder(bin_id)`: non-admin → `هذه العملية متاحة للأدمن فقط`; missing → `العنصر غير موجود في السلة`; original order gone → `تعذر الاسترجاع — الحجز الأصلي لم يعد موجوداً`; else re-inserts the holder with the **same id, qr_code and created_at**, deletes the bin row, returns `تم استرجاع التذكرة`; the insert reserves a seat if the order is active and may fail with `insufficient_stock:<type>`. Client: failure toast message or `تعذر استرجاع التذكرة`; success toast message or `تم استرجاع التذكرة`; removes it from the bin list and refetches tickets.
  - *Rebuild:* restore = clear `deleted_at` under the stock lock; show the specific no-stock message (original showed a generic error); remove the code from `expired_qr_codes`; regenerate the picture if missing.

**Upgrades (new, not in original)**
- NEW: XLSX export of the booked-tickets list for a day (name, phone, type, booking ref, payment, scan status).
- NEW: Inventory history (capacity/price changes, recounts) per ticket type.

**Original defects to avoid:**
- Displayed "sold" (confirmed holders) ≠ DB `sold_quantity` (pending + confirmed): remaining shown can exceed what is sellable; capacity edit check uses the confirmed-only count, so capacity can be set below held seats (no DB CHECK).
- Two full client-side scans of all holders per load; date grouping in browser time zone; English date labels in an Arabic UI; grand-total price cells misleading; revenue based on current price, so a price edit rewrites history; no audit of price/capacity changes.
- Grouping by date string merges events on the same timestamp; NaN/Infinity when capacity is 0.
- Booked-tickets panel mixes pending/cancelled tickets with paid ones and counts them as unscanned.
- Deleting a holder leaves `orders.quantity`/`total_amount` inconsistent; restore loses the QR picture.
- 10 s polling + realtime + unpaginated 40 000-row load.
- Scan time/scanner stored in `confirmed_at`/`confirmed_by_name`, also written by manual payment confirmation.
- POS tile does not account for quantities already in the cart.
- Unused state (`dailyBookings`, `DailyBooking`).

---

### ADM-CUS — Customers & customer lookup

Directory of every customer with **confirmed** orders, grouped into collapsible "season folders" by event month, with search, current/archived split, repeat-buyer badge, cards listing every ticket, and per-customer actions (send tickets to WhatsApp, delete, details with orders, edit customer, return ticket, send invoice, edit holder phone, send a single ticket, un-mark attendance). Also documents the shared `CustomerLookup` autocomplete used at the POS. Original `/admin/dashboard/customers`; new app: Filament resource `CustomerResource` (custom list page with folders + view page with relation managers) at `/admin/customers`; lookup as a Livewire component `CustomerLookup` used by the POS.

- **F-ADM-CUS-001 · Customer list (confirmed only)** — loads all customers with their orders (id, booking_reference, ticket_type, quantity, total_amount, payment_status, payment_method, created_at, qr_code, event title/location/event_date/is_archived, holders id/name/phone/country_code/nationality/ticket_type/qr_code/is_present/id_number), ordered by customer `created_at desc`; keeps only orders with `payment_status = 'confirmed'` and drops customers without any confirmed order. Loading `جاري التحميل...`; header title `العملاء والحجوزات`; empty `لا يوجد عملاء مع حجوزات`. Load errors only logged.
  - *Rebuild:* server query `Customer::whereHas('orders', confirmed)` paginated per folder (original capped at 1000 customers).

- **F-ADM-CUS-002 · Current / archived toggle** — `الحالية` / `الأرشيف`: current = orders whose event `is_archived` is not true; archived = `is_archived = true`; customers without orders in scope are hidden.

- **F-ADM-CUS-003 · Season (month) folders** — each order's event month index (`year × 12 + month`); customer's month = latest month of their scoped orders; months sorted ascending, consecutive months merged into one folder; label: single month → `<Arabic month name> <year>` with Latin digits (e.g. `نوفمبر 2025`); run → `<first month name> - <last month name> <year>`; customers with no dated orders → folder `بدون تاريخ`; folders shown newest first. Folder header (collapsible): Folder icon, label, badge `{n} عميل`, chevron rotating when open. Default open: current view → all open; archived view → open only when there is a single folder.
  - *Rebuild:* compute months in Asia/Qatar (original used browser time zone); better: group by the `seasons` entity when available.

- **F-ADM-CUS-004 · Search** — input placeholder `بحث بالاسم، الهاتف، أو رمز التذكرة...`; on scoped customers: name (ci), e-mail (ci), phone (substring, case-sensitive), any order booking reference (ci), any holder `qr_code` (ci) — so scanning/pasting a ticket QR code finds its owner.
  - *Rebuild:* server search incl. normalised phone digits and exact QR-code match (indexed).

- **F-ADM-CUS-005 · Customer card** — grid 1/2/3/4 columns; whole card clickable → details dialog. Event header strip (when the first order has an event title): title + repeat-buyer badge on the left. Row: avatar icon; centre badges: payment method of the customer's **first** order (`POS` secondary for cash_pos / `Online` default) and `{total holders} تذكرة`; caption `رمز التذكرة:` + the booking reference of the first holder's order (mono); right: customer name + phone. Box `التذكرة الرئيسية:` (first holder): type badge (upper-case raw type), holder name + flag, ID number (CreditCard icon) when present, phone. Box `التذاكر الإضافية:` (other holders, scroll max-h 40) with the same layout. Footer buttons: send ticket (F-ADM-CUS-007) and delete (F-ADM-CUS-008).

- **F-ADM-CUS-006 · Repeat-buyer count badge** — small circle with a count on the event header when `phoneTicketCounts[customer.phone] > 1`; counts = number of holders of confirmed orders grouped by the **exact trimmed holder phone string**, looked up by customer phone (format mismatches such as `+974 5555…` vs `5555…` give no badge; count query capped at 1000 rows).
  - *Rebuild:* count by normalised phone digits (last 8) and ID number via SQL, consistent with the lifetime-totals rule used in Live bookings.

- **F-ADM-CUS-007 · Send ticket to WhatsApp from the card** — button `إرسال التذكرة` (Ticket icon, title `إرسال التذكرة عبر n8n`, shows ⏳ while sending). Algorithm: order = `customer.orders[0]` (API order, not sorted, not season-scoped); none → `لا توجد حجوزات لهذا العميل`; read `settings.webhook_url, admin_phone`; no URL → `لم يتم تكوين رابط n8n webhook في الإعدادات`; price map from all ticket types (type → price, last wins across events); for each holder render a QR (500 px, margin 2) to JPEG 0.95, upload `qr-codes/<holderId|now>-<random>.jpg` (no upsert, cache 3600) → public URL; POST **directly from the browser** to the webhook URL: `{customer:{name, email, phone: WhatsApp(phone, cc)}, order:{id, booking_reference, ticket_type, quantity, total_amount, payment_status}, ticketHolders:[{name, phone: WhatsApp(phone), nationality, id_number, ticket_type, ticket_price, qr_code, qr_code_image}], bookingReference, adminPhone: WhatsApp(admin_phone), timestamp, action:'send_ticket'}`; non-OK → error. Toasts `تم إرسال التذكرة إلى واتساب بنجاح` / `فشل إرسال التذكرة. يرجى المحاولة مرة أخرى`. Ignores `webhook_enabled`. WhatsApp number helper `toWhatsAppNumber(phone, cc)`: trim; empty → null; cc digits default `974`; strip non-digits; strip leading `00`; raw starting with `+` → digits as-is; digits starting with cc and national part ≥ 7 digits → as-is; else `cc + digits without leading zeros`.
  - *Rebuild:* action dispatches `SendWhatsAppTickets(order)` (latest confirmed order of the selected season, or a picker when several); same `send_ticket` payload built server-side with stored QR picture URLs and prices paid; respects `webhook_enabled`; webhook URL never reaches the browser.

- **F-ADM-CUS-008 · Delete customer (cascade)** — trash icon (title `حذف العميل وجميع حجوزاته`) shown only when `settings.show_delete_customer_button` (default false). Browser confirm `هل أنت متأكد من حذف هذا العميل وجميع حجوزاته وتذاكره؟ لا يمكن التراجع عن هذا الإجراء.`; deletes all holders of the customer's orders, then the orders, then the customer (seats released for active orders); toast `تم حذف العميل وجميع حجوزاته بنجاح`; closes the details dialog if open; error `فشل حذف العميل. يرجى المحاولة مرة أخرى`.
  - *Rebuild:* one transaction, soft delete + audit, super_admin only; refuse when the customer has confirmed orders on upcoming events unless forced.

- **F-ADM-CUS-009 · Customer details dialog** — dialog (max-w-3xl, 80vh) title `تفاصيل العميل`. Info card: name, e-mail, phone, button `تعديل` (Edit) → F-ADM-CUS-010. Section `الحجوزات`: per order card: Calendar icon + booking reference + status badge (`مؤكد` default / `قيد الانتظار` secondary / `ملغي` destructive); buttons `إرجاع التذكرة` (F-ADM-CUS-012) and WhatsApp invoice (F-ADM-CUS-013); order QR image 200×200 when `orders.qr_code` exists; amount `x.xx ريال قطري` + `{quantity} تذكرة`; order date (`en-US` long with time). Sub-section `حاملو التذاكر:` per holder (view mode): name, green `حاضر` badge when present, type badge (upper-case); `<country_code|+974><phone> • <nationality>` (LTR); ID number; `حالة التأكيد:` + order-status badge `✓ مؤكد` (green) / `⏳ قيد الانتظار` / `✗ ملغي`; buttons edit phone (F-ADM-CUS-014), send single ticket (F-ADM-CUS-015), un-mark attendance (F-ADM-CUS-016). On opening, QR data URLs are generated for every order code (200 px, margin 2) and holder code (500 px, margin 2).
  - *Rebuild:* Arabic dates in Asia/Qatar; QR images from stored pictures.

- **F-ADM-CUS-010 · Edit customer** — dialog `تعديل بيانات العميل`: `الاسم` (placeholder `اسم العميل`), `البريد الإلكتروني` (type email, placeholder `email@example.com`), `رقم الهاتف` (placeholder `+974 XXXX XXXX`), `الجنسية` (Select, placeholder `اختر الجنسية`, default `قطر`); buttons `إلغاء` / `حفظ التعديلات` (`جاري الحفظ...`). Save updates `customers {name, email, phone, nationality}`, then sets **every holder phone** of all the customer's orders to the new phone (as-is if it starts with `+`, else `+974 <phone>`; holder errors only logged); toast `تم تحديث بيانات العميل بنجاح`; refetch and patch the open dialog (name/email/phone); error `فشل تحديث بيانات العميل`. No validation.
  - *Rebuild:* validate (name required ≤ 120, e-mail `email:rfc`, phone E.164 via libphonenumber, nationality in list); do NOT overwrite holders' phones (optionally offer "also update the main holder"); audit log.

- **F-ADM-CUS-011 · Nationality list and flags** — Select groups: Gulf `قطر 🇶🇦`, `السعودية 🇸🇦`, `الإمارات 🇦🇪`, `الكويت 🇰🇼`, `البحرين 🇧🇭`, `عمان 🇴🇲`; Others `مصر`, `الأردن`, `لبنان`, `سوريا`, `العراق`, `اليمن`, `المغرب`, `الجزائر`, `تونس`, `ليبيا`, `السودان`, `فلسطين`, `باكستان`, `الهند`, `بنغلاديش`, `الفلبين`, `إندونيسيا`, `نيبال`, `أمريكا`, `بريطانيا`, `فرنسا`, `ألمانيا`, `إيطاليا`, `أسبانيا`, each item `<flag> <name>`. Flag lookup accepts these Arabic names and English names (lower-cased): qatar, saudi, saudi arabia, uae, united arab emirates, emirates, kuwait, bahrain, oman, egypt, jordan, lebanon, syria, iraq, yemen, palestine, morocco, tunisia, algeria, libya, sudan, india, pakistan, bangladesh, philippines, nepal, sri lanka, usa, uk, united kingdom, canada, australia, france, germany, italy, spain; unknown → 🌐.
  - *Rebuild:* one `Nationality` enum/config (ISO code, Arabic name, English name, flag) shared by public checkout, POS, invoices and customers.

- **F-ADM-CUS-012 · Return ticket (cancel order & restock)** — button `إرجاع التذكرة` (destructive, Trash2, title `إرجاع التذكرة وإعادتها للبيع`). Browser confirm `هل أنت متأكد من إرجاع التذكرة للطلب {ref}؟ سيتم إعادة التذكرة للبيع مرة أخرى.` → `cancel_order(order_id, 'returned by admin')` (contract F-ADM-ORD-026). `already_inactive` → info `هذا الطلب ملغي مسبقاً` + refetch; success → `تم إرجاع التذكرة وإعادتها للبيع بنجاح`, refetch, local status patched to cancelled; error → `فشل إرجاع التذكرة. يرجى المحاولة مرة أخرى`. Cancels the **whole order** (all tickets); no Sadad refund handling (refunds done manually in Sadad's portal).
  - *Rebuild:* modal with reason + "refund required" checkbox for Sadad orders (creates a refund task); optional per-holder return (removes one holder, releases one seat, adjusts totals); audit log; order becomes "final" (cannot be re-confirmed by a late Sadad verify).

- **F-ADM-CUS-013 · Send invoice to WhatsApp per order** — icon button (MessageCircle green, title `إرسال الفاتورة عبر واتساب`, ⏳ while sending). No holders → `لم يتم العثور على التذاكر`. For each holder: take the generated QR data URL, upload to `qr-codes/<code>.png` (upsert) and collect the public URL ('' on failure). Payload `{booking_reference, event_title, event_location, event_date, customer_name, customer_phone (leading "+<digits> " stripped), customer_phone_whatsapp (<first holder cc without + | 974><stripped phone>), nationality (customer || first holder || ''), quantity (order.quantity || holders.length), qr_codes:[urls], ticket_types:[types], total_amount, payment_status, timestamp}` → relay `send-to-webhook` (server marks the order "sending"). Invoke error → `فشل إرسال التذاكر`; otherwise success toast `تم إرسال {n} تذكرة إلى واتساب بنجاح` (relay `error` responses are not checked, so n8n 404/timeouts show as success); exception `فشل إرسال التذاكر. يرجى المحاولة مرة أخرى`.
  - *Rebuild:* same `SendWhatsAppTickets` job as F-ADM-CUS-007 / F-ADM-INV-009 with the invoice payload; surface relay errors (404 inactive workflow, timeout) using the messages of F-ADM-ORD-036.

- **F-ADM-CUS-014 · Edit ticket holder phone** — edit icon (title `تعديل رقم الهاتف`) switches the holder row to edit mode: inputs `كود الدولة` (placeholder `+974`, prefilled `country_code || '+974'`) and `رقم الهاتف` (placeholder `XXXX XXXX`, prefilled with any leading `+<digits> ` stripped); buttons `إلغاء` / `حفظ` (`جاري الحفظ...`). Save updates `ticket_holders {phone, country_code}`; toast `تم تحديث بيانات حامل التذكرة بنجاح`; error `فشل تحديث بيانات حامل التذكرة`; refetch + local patch. No validation.
  - *Rebuild:* validate with libphonenumber; store E.164 + display format; audit.

- **F-ADM-CUS-015 · Send single ticket to WhatsApp** — Send button (title `إرسال التذكرة للواتساب`) per holder, only when the order status ≠ pending (⏳ while sending). Checks `settings.webhook_url` presence → `لم يتم تكوين رابط الويب هوك`; uploads the QR PNG `<qr_code>.png` (upsert) → on failure toast `فشل رفع رمز QR` and stop; price by type; order found by reference in the selected customer else `لم يتم العثور على الطلب`; payload `{booking_reference, event_title, event_location, event_date, ticket_count:1, holder:{name, phone (stripped), country_code (from "+NNN " prefix of phone, else holder cc without +, else 974), nationality, id_number, ticket_type, ticket_price, qr_code, qr_code_image, is_present}, timestamp}` → relay. Invoke error → message or `فشل الاتصال بالويب هوك`; `data.error` → if details mention `not registered` → `الويب هوك غير مفعل في n8n. يرجى تفعيل الـ workflow أولاً`, else `data.error` or `فشل إرسال البيانات إلى الويب هوك`; `data.success` → toast `تم إرسال التذكرة إلى {holder.phone}`; otherwise `استجابة غير متوقعة من الويب هوك`; errors default `فشل إرسال التذكرة`.
  - *Rebuild:* `SendSingleTicket(holder)` job; only for confirmed orders (original allowed cancelled orders too since it only excluded pending).

- **F-ADM-CUS-016 · Un-mark attendance** — UserX destructive button (title `إلغاء تحديد الحضور`) only when the holder `is_present`. Sets `is_present = !current`; toast `تم تحديد الحضور` / `تم إلغاء تحديد الحضور`; error `فشل تحديث حالة الحضور`; refetch + local patch. Does not clear scan time/scanner and writes no scan log.
  - *Rebuild:* `UnmarkAttendance` action with reason; clears `scanned_at/scanned_by`, writes a `ticket_scan_logs` entry (`action='unmark'`), broadcasts to live boards/gate counters.

- **F-ADM-CUS-017 · Customer lookup at POS (`CustomerLookup`)** — autocomplete in the POS customer step (staff: admin, moderator or passcode). Input placeholder `ابحث برقم الهاتف`; clear button `مسح` (X icon, spinner while searching, disabled when empty). Debounce **250 ms**; minimum **3** characters (else results cleared). Search: with a user session → customers where `phone ILIKE %term%` (`%` and `,` removed from term), newest first, limit **8**; with team-passcode access only → server `staff-customer-lookup {term, passcode}` (admin, moderator or passcode else 401 `unauthorized`; term < 3 → `{customers:[]}`; same query, limit 8; errors 500); no access → empty. Results de-duplicated by `phone|id_number` (most recent wins). No match → `لا يوجد عميل مطابق — تابع بإدخال البيانات يدوياً`. Error toast `تعذر البحث عن العميل`. An `eventId` prop exists but is unused (per-event remaining allowance never implemented).
  - *Rebuild:* `GET /staff/customers/lookup?q=` (staff guard, throttle 30/min), search by phone digits, name and ID number; returns customers with history in one query (no N+1); implement the per-event remaining allowance using the event's per-person ticket limit (NEW).

- **F-ADM-CUS-018 · Previous-event history chips** — for each result, server `get_person_event_history(id_number digits, country_code+phone digits)` (skipped when ID < 6 digits and phone < 8 digits): confirmed holders matched by equal ID digits OR same last 8 phone digits, grouped by event → `{event_id, title, event_date, ticket_count}`, event date desc, limit **50**. Rendered as chips: date (`ar-u-nu-latn` `dd MMM yyyy`, Qatar; or title when no date) + `<count> تذكرة`, title attribute = event title.
  - *Rebuild:* staff-only (original granted the function to anonymous users — anyone could query anyone's attendance history).

- **F-ADM-CUS-019 · Select customer to auto-fill POS form** — result cards (max-h 64 scroll; role button; Enter/Space selects): name (bold); `<country_code|+974><phone>[ · <id_number>]` (LTR); history chips; right `اختيار` with UserCheck icon. Selecting calls `onSelect({id, name, email|null, phone, country_code|null, nationality|null, id_number|null})`, clears query and results, toast `تم تعبئة بيانات {name}`.

**Upgrades (new, not in original)**
- NEW: Customer merge (duplicate phones/IDs) with order re-assignment.
- NEW: XLSX export of the customer list per season folder.

**Original defects to avoid:**
- Browser posts directly to the n8n webhook URL (secret URL exposed, `webhook_enabled` bypassed).
- Browser uploads to the public `qr-codes` bucket with upsert (clients can overwrite pictures); pictures must be generated server-side.
- Edit customer overwrites the phone of every ticket holder across all the customer's orders.
- Unpaginated customers and holder-phone counts (1000-row caps); repeat-buyer counts by exact phone string.
- Invoice send ignores relay errors (false success); prices looked up by type across all events.
- Card "send ticket" uses an unsorted, unscoped `orders[0]`; method badge from the first order only.
- Dead QR-viewer dialog (`QR Code - <name>`, `QR Code Value:` and English note) never opened.
- Return cancels the whole order with no refund workflow.
- Dialog dates in English (`en-US`); folder months in browser time zone.
- Customer lookup: unused `eventId`, N+1 history calls (up to 8), history RPC exposed to anon, phone-only search.



---

## 2.4 Admin — shell, auth, settings, events & analytics

This section covers identity and access (account login, team passcode, guards, brute-force protection, staff accounts, roles) and the core of the admin back-office: the dashboard shell, global settings, events and seasons, capacity alerts, the notifications feed, Excel reports, activity logs, visitor analytics, and POS users with team attendance. Finance screens (orders, transactions, invoices, payment errors, tickets, customers) are in section 2.3. Staff operations screens (POS, scanner, live boards, staff hub, gate board, live visitors page) are in the staff-ops section. Edge-function and database contracts referenced here are detailed in the backend and database sections.

Conventions used below:
- "Qatar time" = Asia/Qatar, UTC+3, no DST. All business-day logic (today, yesterday, 18:00 cut-off, day buckets) uses Qatar time; storage is UTC.
- Arabic UI strings are quoted verbatim. Where the original used an i18n key, the key's Arabic value is given (Arabic is the default and fallback locale).
- Digits in Arabic-locale formatting are Latin (`ar-u-nu-latn`).
- "Original" = the React/Supabase app being replaced. "Rebuild" = the Laravel 12 target (Filament v4 admin, Livewire 3 staff apps, Fortify, spatie/laravel-permission, spatie/laravel-settings, spatie/laravel-activitylog, Reverb/Echo, Horizon, maatwebsite/excel).

---

### AUTH — Authentication, roles & access

One login screen serves admins, moderators (supervisors) and passcode-only team members. The screen has two tabs: account (e-mail + password) and team passcode. Route guards then send each identity to the screens it may use. Original routes: `/admin/login` (page), plus the same card rendered inline by the route guard on any protected route. Rebuild: `GET /admin/login` (Blade/Livewire page shared by Filament and the staff apps), `POST /login` (Fortify), `POST /staff/passcode` (passcode exchange), middleware `role:*` / `staff`.

#### Identity kinds (original)

| Kind | How established | Where stored |
|---|---|---|
| **admin** | Auth session AND (`user_roles.role='admin'` OR row in `admin_users` with `id = user.id`) | Supabase session (browser localStorage) |
| **moderator** (`مشرف مساعد`) | Auth session AND `user_roles.role='moderator'` AND not admin | Supabase session |
| **passcode (team)** | Shared team passcode verified server-side against secret `STAFF_PASSCODE` | Browser storage keys `staff_passcode_ok="1"`, `staff_passcode_value=<plaintext passcode>`, `staff_passcode_exp=<ms epoch>` + cookie `staff_passcode_ok=1` |
| anonymous / public | none | — |

- **F-AUTH-001 · Login page (`/admin/login`)** — Public page. Sticky header (z-index above content) with a centred logo button that navigates to `/`. The logo comes from `public_settings.logo_url` (rendered at 53 px height); when no logo is set, the header shows H1 `تسجيل الدخول` ("Sign in"). Header background = `public_settings.header_bg_color`, default `hsl(var(--card) / 0.5)`. Body: vertically padded (`py-14`), centred unified login card (F-AUTH-002) opened on the **passcode** tab by default, with the "back to home" link enabled (F-AUTH-012).
  - *Rebuild:* one Blade view reused by the Filament login route (custom `Filament\Pages\Auth\Login`) and the staff login; default tab configurable per entry point.

- **F-AUTH-002 · Unified login card** — Card `max-w-md`. Gradient header with a lock icon in a circle, H1 `تسجيل الدخول`, subtitle `دخول موحّد للأدمن والمشرفين وفريق العمل` ("Unified sign-in for admins, supervisors and the team"). Two tabs: `حساب` ("Account", ShieldCheck icon) and `كلمة مرور الفريق` ("Team passcode", KeyRound icon). Props in the original: `intendedPath`, `onPasscodeSuccess`, `showHomeLink` (default false), `defaultTab` (`account` default; `passcode` on `/admin/login`).

- **F-AUTH-003 · Account tab form** — Fields: `البريد الإلكتروني` (e-mail, input `type=email`, LTR, required, placeholder `admin@example.com`); `كلمة المرور` (password, LTR, required, placeholder `••••••••`). Submit button (large, full width) label by state: locked → `موقوف مؤقتاً ({m:ss})` ("Temporarily locked"); submitting → `جاري الدخول...` ("Signing in..."); idle → `دخول` ("Sign in"). Helper text under the form: `يتم توجيهك تلقائياً حسب صلاحيتك: الأدمن للوحة التحكم، والمشرف للروابط السريعة.` ("You are routed automatically by your permission: admins to the dashboard, supervisors to quick links.").

- **F-AUTH-004 · Account sign-in algorithm** — In order:
  1. If the card is locked, do nothing. Set loading.
  2. Pre-check the lockout (F-AUTH-014) for identifier = e-mail (trimmed, lower-cased, max 160 chars), kind `account`. If blocked: start the lock countdown with `retry_after_seconds` (fallback **1800 s**) and show error toast `تم إيقاف المحاولات مؤقتاً بسبب تكرار الأخطاء` ("Attempts temporarily stopped due to repeated errors"); stop.
  3. Attempt password sign-in. On failure, record a failed attempt, then show the provider's error message as a toast.
  4. Load the user's roles. If moderator and not admin: record success, toast `تم تسجيل الدخول` ("Signed in"), navigate (replace) to `/staff`.
  5. If neither admin role nor `admin_users` row: sign the user out, record a failure, error `غير مصرح: الدخول للأدمن فقط` ("Unauthorised: admins only").
  6. Admin: record success, toast `تم تسجيل الدخول`, navigate (replace) to the intended path if it is a safe relative path (starts with `/` and not `//`), else `/admin/dashboard`.
  - Any other exception: toast `error.message` or `تعذر تسجيل الدخول` ("Could not sign in").
  - After recording a failure, the card reads the response: `blocked` → start the lock; otherwise, if `attempts_left` is a number, store it for the warning (F-AUTH-016). Success clears both.
  - *Rebuild:* steps 2–5 run inside the Fortify login pipeline on the server (custom `AuthenticateUsing` + `RateLimiter`), never in the browser. Role precedence is fixed: admin (role) > moderator. The `admin_users` table is migrated into the `admin` role and dropped.

- **F-AUTH-005 · Post-login role routing** — admin → intended path or `/admin/dashboard`; moderator-only → `/staff` (quick links hub); authenticated user with no staff role → signed out with `غير مصرح: الدخول للأدمن فقط`; passcode success → the guarded page that rendered the card inline (callback) else `/staff`.
  - *Rebuild:* map to target roles: `super_admin`/`admin` → Filament panel; `moderator`/`supervisor`, `cashier`, `gatekeeper` → `/staff`; `accountant`, `viewer` → Filament panel with read-only resources (NEW roles, see roles matrix).

- **F-AUTH-006 · Intended-path resolution** — On `/admin/login` the intended path is the query parameter `next` if it starts with `/` and not `//` (used by the OAuth consent flow `/.lovable/oauth/consent`), else the router state `from` (never set in practice — the guard renders the card inline). Unsafe values are ignored (open-redirect protection).
  - *Rebuild:* Laravel `redirect()->intended()` with the same relative-path validation.

- **F-AUTH-007 · Team passcode tab form** — One field `كلمة مرور الفريق` (password input, LTR, not marked required). Submit label: locked → `موقوف مؤقتاً ({m:ss})`; verifying → `جاري التحقق...` ("Verifying..."); idle → `فتح الصفحة` ("Open the page"). Helper: `تبقى الصلاحية فعّالة لمدة ١٠ أيام في هذا المتصفح، ثم تُطلب كلمة المرور من جديد.` ("Access stays valid for 10 days in this browser, then the passcode is asked again.").

- **F-AUTH-008 · Team passcode verification flow (client)** — If locked, do nothing. Call the passcode verifier (F-AUTH-009) with `{passcode}`. Outcomes:
  - `ok` → clear attempts and lock, persist passcode access (F-AUTH-018), toast `تم فتح الصفحة` ("Page unlocked"), then run the guard callback or navigate to `/staff`.
  - `unavailable` → toast `الدخول بكلمة مرور الفريق غير مفعّل حالياً — استخدم حسابك أو تواصل مع الإدارة` ("Team passcode sign-in is currently disabled — use your account or contact management").
  - `blocked` → lock for `retry_after_seconds` (fallback 1800 s), toast `تم إيقاف المحاولات مؤقتاً بسبب تكرار الأخطاء`.
  - otherwise → store `attempts_left` if numeric, toast `كلمة المرور غير صحيحة` ("Incorrect passcode").
  - network/exception → toast `تعذر التحقق من كلمة المرور` ("Could not verify the passcode").
  - The passcode tab does not call the account lockout endpoint; the verifier throttles on its own.

- **F-AUTH-009 · Team passcode verifier (server)** — POST only (other methods → 405 `{ok:false, error:"method_not_allowed"}`). Body `{passcode}`; invalid JSON → `{ok:false}`. Always HTTP 200 so the UI can show the reason. Steps:
  1. `passcode === "__warmup__"` → `{ok:false, warmup:true}`, nothing recorded (F-AUTH-011).
  2. Configured passcode unusable → `{ok:false, unavailable: "not_configured" | "revoked" | "too_short"}` (rules in F-AUTH-010).
  3. Throttle check (F-AUTH-015) for both the caller key and the global key; either blocked → `{ok:false, blocked:true, retry_after_seconds: max(both)}`.
  4. Compare `supplied.trim()` with the configured value in constant time (byte-wise XOR over the longer length, length difference folded in).
  5. Record a `login_attempts` row `{identifier: "passcode:<ip or unknown>", kind:"passcode", success, ip_address, user_agent (≤300 chars)}`; on a wrong passcode also record a failure under identifier `team-passcode` (global counter; successes never reset it). Recording errors are logged only.
  6. Correct → `{ok:true}`. Wrong → `{ok:false, attempts_left: max(0, callerAttemptsLeft − 1)}` and, if that was the last allowed attempt, also `blocked:true, retry_after_seconds:1800`.
  - In the original no token is issued; the browser keeps the plaintext passcode and re-sends it in the `passcode` body field of every staff call.
  - *Rebuild:* `POST /staff/passcode` validates, then logs the browser into a dedicated `staff_passcode` guard session (or issues a Sanctum token with ability `staff:passcode`) valid 10 days; the passcode itself is never stored client-side or re-sent. Store the configured passcode as a hash (`STAFF_PASSCODE_HASH` env or encrypted setting) and compare with `Hash::check`.

- **F-AUTH-010 · Passcode validity rules** — Source: secret `STAFF_PASSCODE` only (not editable in the UI). Value is trimmed; it is unusable (passcode login OFF) when empty (`not_configured`), shorter than **8** characters (`too_short`), or equal to the permanently revoked value `@@@Qatar123` (`revoked`; it was once published in source and on the login page). Revoked list is a set, so further leaked values can be added.
  - *Rebuild:* keep the min length 8 and the deny list (config array); add an admin-only "rotate team passcode" action that writes the new hash and invalidates all passcode sessions (NEW, see upgrades).

- **F-AUTH-011 · Warm-up ping** — When the login card mounts it fires (and ignores) a verifier call with passcode `"__warmup__"` to wake the serverless function. The server answers `{ok:false, warmup:true}` and does not count it.
  - *Rebuild:* not needed on Laravel (no cold starts); drop it. Keep the server rule that a literal `__warmup__` is never counted if the endpoint is reused by old clients.

- **F-AUTH-012 · Back-to-home link** — When enabled, a ghost full-width button `← العودة للرئيسية` ("Back to home") under the card navigates to `/`.

- **F-AUTH-013 · Lock banner and countdown** — While locked, a destructive banner shows `تم إيقاف المحاولات مؤقتاً` ("Attempts temporarily stopped") and `حاول مجدداً بعد {m:ss} — تم إشعار الإدارة.` ("Try again after {m:ss} — management has been notified."). The countdown ticks every 1000 ms; the `m:ss` span is LTR. Both submit buttons show `موقوف مؤقتاً ({m:ss})` and are disabled. Lock duration = server `retry_after_seconds`, fallback 1800 s. The countdown is client-side only and resets on reload (server still enforces).

- **F-AUTH-014 · Account brute-force protection (lockout service)** — Original endpoint `login-guard`, body `{mode: "check"|"record" (anything else = check), identifier, kind: "account"|"passcode" (default account), success?: true}`. Constants: **MAX_ATTEMPTS = 5**, **WINDOW = 15 min**, **BLOCK = 30 min**. Identifier normalised `trim().toLowerCase().slice(0,160)`; empty → for `passcode` use `team-passcode`, for `account` return `{allowed:true}`.
  - "Recent failures" = `login_attempts` rows for the identifier with `attempted_at ≥ now − 15 min`, newest first, max 50, counted as the streak of failures until the first success.
  - `check` → blocked when streak ≥ 5 and newest failure + 30 min > now: `{allowed:false, blocked:true, retry_after_seconds, attempts}`; else `{allowed:true, attempts_left: max(0, 5 − failures)}`.
  - `record` → insert `login_attempts {identifier, kind, ip_address (first X-Forwarded-For entry), user_agent ≤300, success}`. Success → `{ok:true, blocked:false}`. Failure → recompute; if now blocked → send the alert e-mail (F-AUTH-017) and write activity log `login_blocked` (F-AUTH-017). Always fire-and-forget cleanup of rows older than 7 days (F-AUTH-019). Returns `{ok:true, blocked, retry_after_seconds, attempts_left}`.
  - Errors → `{allowed:true, error}` (fail-open by design).
  - *Rebuild:* enforce inside the login controller (Fortify `RateLimiter::for('login')` plus a `login_attempts` table for audit), keyed by **both** e-mail and IP; 5 failures in 15 min → lock 30 min measured from the newest failure (same semantics as F-AUTH-015's `evaluateThrottle`). The endpoint must not be callable anonymously to record failures for arbitrary identifiers. Decide fail-closed vs fail-open explicitly (recommended: fail-open only for Redis outage, logged to Sentry).

- **F-AUTH-015 · Throttle evaluation rule (shared)** — `evaluateThrottle(attempts, {max, windowMs, blockMs}, now)`: sort attempts newest first; streak = consecutive failures from the newest back to the first success; `attemptsLeft = max(0, max − count of streak entries within window)`; if streak length ≥ max and the max-th newest failure is within the window and `newest + blockMs > now` → blocked, `retryAfterSeconds = ceil((newest + blockMs − now)/1000)`, attemptsLeft 0. Consequence: further wrong attempts during a block extend it. Passcode rules: **per caller** key `passcode:<ip>` (IP from `CF-Connecting-IP`, else first `X-Forwarded-For` entry, max 64 chars, else `unknown`) = `{max 5, window 15 min, block 30 min}`; **global** key `team-passcode` = `{max 60, window 15 min, block 15 min}` (failures only). Attempts loaded since now − 30 min, max 200 rows per key.

- **F-AUTH-016 · Remaining-attempts warning** — When `attempts_left ≤ 2` and not locked, the card shows `تبقّت {n} محاولة قبل الإيقاف المؤقت` ("{n} attempts left before temporary lock").

- **F-AUTH-017 · Login-block alert e-mail and audit entry** — When an identifier becomes blocked (account login path), the system e-mails `settings.admin_email` (only if it matches the e-mail regex) using template `login-alert`: display name `تنبيه محاولات الدخول الفاشلة`; subject `تنبيه أمني: محاولات دخول فاشلة ({identifier})`; preview `محاولات دخول متكررة فاشلة — {identifier}`; H1 (danger colour `#8E2C2C`) `تنبيه أمني: محاولات دخول متكررة`; subtitle `تم إيقاف الدخول مؤقتًا لمدة {blocked_minutes|30} دقيقة بعد {attempts|5} محاولات فاشلة.`; rows `المُعرّف المستخدم` (identifier), `نوع الدخول` (kind label: `كلمة مرور الفريق` for passcode, `لوحة التحكم` for account), `عدد المحاولات`, `وقت الحظر` (date+time), `عنوان الشبكة` (IP), `الجهاز / المتصفح` (user agent); footer `رسالة تلقائية من نظام إدارة التذاكر.` Idempotency key `login-block-{identifier}-{floor(now / 30 min)}` → at most one mail per identifier per 30-minute bucket. Also writes activity log `{activity_type:"login_blocked", user_type: "staff" (passcode) | "admin" (account), user_identifier: identifier, action_data:{attempts, block_minutes:30}, ip_address, user_agent}`.
  - *Rebuild:* queued Mailable `LoginBlockedAlert` (Horizon), idempotency via cache lock on the same key; activity log via spatie/activitylog with log name `auth`. Extend to passcode lockouts too (original passcode verifier never e-mails — see defects).

- **F-AUTH-018 · Passcode access persistence (10 days)** — On success the browser stores the access flag, the passcode and an expiry = now + **10 days** (864 000 s) in both localStorage and sessionStorage, and sets cookie `staff_passcode_ok=1; path=/; max-age=864000; SameSite=Lax`. `hasStaffAccess()` = flag present in either storage or the cookie, and not expired (expired → revoke everything). `getStaffPasscode()` returns the stored value unless expired. `staffAccessRemainingMs()` = max(0, exp − now). Revoke removes all three keys from both storages and clears the cookie. Opening a staff link from the hub re-grants (sliding renewal of 10 days).
  - *Rebuild:* server session in guard `staff_passcode` with absolute lifetime 10 days and optional sliding renewal on hub use; cookie `HttpOnly; Secure; SameSite=Lax`. Logging out of the hub ends the session.

- **F-AUTH-019 · Login attempts store and retention** — Table `login_attempts`: `id`, `identifier` (account e-mail, `passcode:<ip>`, or `team-passcode`), `kind` (`account` default | `passcode`), `ip_address`, `user_agent`, `success` (default false), `attempted_at` (default now), `created_at`. Indexes `(identifier, attempted_at desc)` and `(attempted_at desc)`. Readable by admins only; written by the server only. Retention **7 days** (`cleanup_login_attempts()` deletes `attempted_at < now − 7 days`, called opportunistically after each record).
  - *Rebuild:* migration with the same columns (`ip_address` VARCHAR(64), `user_agent` VARCHAR(300)); scheduled `model:prune` daily (Prunable trait, 7 days) instead of opportunistic cleanup. Optional read-only Filament page "محاولات الدخول" (NEW).

- **F-AUTH-020 · Cross-tab / cross-origin access handoff** — Links opened from the staff hub carry the passcode in the URL hash `#sk=<urlencoded passcode>` (hash is not sent to servers). On a guarded page, the guard consumes it: if the hash starts with `#sk=`, decode it, always strip the hash via `history.replaceState`, verify it with the passcode verifier; `ok` → persist access (F-AUTH-018) and allow. Any error → not allowed.
  - *Rebuild:* not needed with a same-origin session cookie. If the staff apps live on another sub-domain, use a signed one-time URL (`URL::temporarySignedRoute`, 60 s, single-use nonce in Redis) that establishes the session; never put the passcode in a URL.

- **F-AUTH-021 · Route guard (`RequireAdmin`)** — Runs on every navigation and every auth-state change. States: `checking` (full-screen centred spinner), `allowed` (render page), `denied` (render the unified login card inline, `min-h-screen`, RTL, default tab `account`, intended path = current path, passcode success → re-check and allow). Algorithm:
  1. Passcode access present → allowed (ignores `adminOnly` — defect).
  2. Valid `#sk=` handoff → allowed (ignores `adminOnly` — defect).
  3. No session → denied.
  4. Load roles. Moderator and not admin: on `adminOnly` routes → `navigate("/staff", replace)`; else allowed.
  5. Admin role → allowed.
  6. Else row in `admin_users` → allowed; otherwise denied.
  - *Rebuild:* server middleware. `/admin/*` (Filament) → `role:super_admin|admin|accountant|viewer` with per-resource policies; staff routes (`/staff`, `/staff/attendance`, `/staff/gate`, `/admin/pos`, `/admin/qr-scanner`, `/admin/pos-receipts`, `/admin/tickets`, `/live-bookings`) → `auth:web,staff_passcode` + permission per page; `/live-visitors` → admin only. Unauthenticated requests redirect to `/admin/login?next=<path>`. A signed-in user without a staff role sees a "not authorised" page with a sign-out button (the original kept them stuck on the login card).

- **F-AUTH-022 · Admin-only vs staff routes** — `adminOnly`: `/admin/dashboard` and all its children, `/live-visitors`. Staff (any of admin, moderator, passcode): `/staff`, `/staff/attendance`, `/staff/gate`, `/admin/qr-scanner`, `/admin/pos`, `/admin/pos-receipts`, `/admin/tickets`, `/live-bookings`. Public: `/`, `/tickets/:eventId`, `/checkout`, `/confirmation`, `/invoice/:bookingReference`, `/payment/result`, `/sadad-callback`, `/admin/login`, `/.lovable/oauth/consent`; `/tickets` → redirect `/`, `/sadad-redirect` → redirect `/checkout`.

- **F-AUTH-023 · Server-side authorisation tiers** — Every server endpoint uses one of: **admin** (`requireAdmin` / `isAdminAuthorized`: signed-in user who is admin; passcode never enough → 401 `Sign in as an administrator` / 403 `Administrators only`); **staff** (`staffIdentity` / `isStaffAuthorized`: admin, moderator, or valid passcode in body → else 401 `{"success":false,"error":"unauthorized","message":"غير مصرح"}`); **service** (internal calls with the service key, constant-time compare); **public**. Identity precedence in `optionalStaff`: user token → admin check → moderator check (DB errors swallowed) → passcode. The public anon key never resolves to a user.
  - *Rebuild:* Gates `admin`, `staff`, `account-staff`; Policies per model; internal jobs run in-process (no service-key HTTP calls).

- **F-AUTH-024 · Staff role resolution** — Original hook returns `{role: "admin"|"moderator"|null, loading, isAdmin, isModerator}` from `user_roles` (admin > moderator) and re-resolves on auth changes; it ignores `admin_users` (so legacy table-only admins show as "passcode" in the hub). Used by the staff hub badge and the "full dashboard" link.
  - *Rebuild:* `auth()->user()->getRoleNames()` via spatie/permission; one source of truth.

- **F-AUTH-025 · Logout** — Dashboard logout signs out the account session and navigates to `/admin/login` (does not revoke passcode access). Staff hub logout revokes passcode access AND signs out, then `/admin/login`. Scanner logout signs out only.
  - *Rebuild:* one `POST /logout` that ends both the account session and the passcode session.

- **F-AUTH-026 · Staff accounts list** — Admin-only card `صلاحيات الفريق` ("Team permissions", Shield icon), subtitle `المشرف المساعد يرى فقط الروابط السريعة بعد تسجيل الدخول` ("The assistant supervisor only sees quick links after signing in"). Rendered at the top of `/admin/dashboard/pos-users` and on `/admin/dashboard/staff-accounts` (F-ADM-STF-012). Loads on mount via `manage-staff-users {action:"list"}`: all `user_roles (user_id, role)` joined to e-mails from the auth user list (first page, **200 users max**; unknown → `—`). One row per role row: ShieldCheck icon (primary colour for admin), e-mail, badge `مدير كامل` ("Full manager", default style) for admin or `مشرف مساعد` ("Assistant supervisor", secondary) for moderator. States: `جارٍ التحميل…` ("Loading…"); empty `لا توجد صلاحيات بعد` ("No permissions yet"). Errors → toast with the server message or `فشل الإجراء` ("Action failed").
  - *Rebuild:* Filament resource `StaffUserResource` on `users` with roles relation; paginated, searchable by e-mail/name; shows last login and 2FA status.

- **F-AUTH-027 · Create staff account** — Inline form (grid `1fr 1fr auto`): `البريد الإلكتروني` (e-mail, placeholder `staff@example.com`, required), `كلمة المرور` (password, placeholder `8 أحرف على الأقل` "at least 8 characters", minLength 8, required), button `إضافة مشرف مساعد` ("Add assistant supervisor", UserPlus). Server `create`: e-mail and password ≥ 8 required else 400 `البريد وكلمة مرور (8 أحرف على الأقل) مطلوبة`; creates the auth user with e-mail pre-confirmed; inserts role `moderator` (the UI never creates admins directly; promote afterwards). Provider errors → 400 with the provider message. Success toast `تم إنشاء حساب المشرف المساعد` ("Assistant supervisor account created"); form clears; list reloads.
  - *Rebuild:* form with name, e-mail, role select (all target roles, admin-only roles restricted to super_admin), temporary password or e-mailed invitation link; unique e-mail validation; activity log.

- **F-AUTH-028 · Promote / demote role** — Per row button: `تحويل إلى مساعد` ("Convert to assistant") when admin, `ترقية إلى مدير` ("Promote to manager") when moderator. Server `set_role {userId, role}`: missing data → 400 `بيانات ناقصة` ("Missing data"); deletes all role rows of the user then inserts the new one (non-atomic, role not validated). No confirmation. Toast `تم تحديث الصلاحية` ("Permission updated").
  - *Rebuild:* `syncRoles()` in a transaction; confirmation modal; cannot demote self or the last admin; activity log.

- **F-AUTH-029 · Reset staff password** — Per row button `كلمة المرور` (KeyRound) opens a prompt `كلمة مرور جديدة لـ {email} (8 أحرف على الأقل)` ("New password for {email} (at least 8 characters)"). Client check < 8 → toast `كلمة المرور قصيرة جداً` ("Password too short"). Server `reset_password {userId, password}`: < 8 → 400 `كلمة مرور (8 أحرف على الأقل) مطلوبة`. Success toast `تم تغيير كلمة المرور` ("Password changed").
  - *Rebuild:* modal with password + confirmation (Laravel `Password::defaults()`), option "send reset link instead"; invalidates the user's other sessions; activity log.

- **F-AUTH-030 · Revoke staff access** — Per row trash icon (ghost, destructive). Server `revoke {userId}`: deletes all role rows; the auth user remains and can still sign in but is denied everywhere (unless still in `admin_users`). Existing sessions keep working until their tokens expire. **No confirmation.** Toast `تم سحب الصلاحية` ("Access withdrawn").
  - *Rebuild:* confirmation; deactivate the user (`is_active=false`), remove roles, delete sessions/tokens immediately; cannot revoke self/last admin; activity log.

- **F-AUTH-031 · Staff-account endpoint contract** — Original `manage-staff-users` (POST): requires a Bearer user token of an admin (in `admin_users` or role `admin`) else 401 `{success:false, message:"غير مصرح"}`. Body `{action: list|create|set_role|revoke|reset_password, email?, password?, userId?, role?}`. Unknown action → 400 `إجراء غير معروف` ("Unknown action"); exceptions → 500 `{success:false, message}`.
  - *Rebuild:* Filament actions calling `App\Actions\Staff\*` classes; same validation messages in Arabic (`lang/ar/staff.php`).

#### Roles matrix (original behaviour, all screens)

Legend: ✔ allowed; ✖ denied (login card or 401/403); → redirect; RO read-only; "UI only" = screen renders but data calls fail by RLS.

| Screen / capability | admin | moderator (account) | passcode staff | public |
|---|---|---|---|---|
| Public site `/`, `/tickets/:eventId`, `/checkout`, `/confirmation`, `/payment/result`, `/sadad-callback` | ✔ | ✔ | ✔ | ✔ |
| Invoice page `/invoice/:ref` (reference = capability) | ✔ (+ `id_number` shown) | ✔ | ✔ | ✔ |
| `/admin/login` | ✔ | ✔ | ✔ | ✔ |
| `/admin/dashboard/*` (all sidebar pages) | ✔ | → `/staff` | **✔ (guard defect)** then bounced to `/admin/login` by the shell's session check; data UI only | ✖ login card |
| `/live-visitors` | ✔ | → `/staff` | **✔ (guard defect)**, no data (RLS) | ✖ |
| `/staff` hub | ✔ (+ link to full dashboard) | ✔ | ✔ (badge `دخول بكلمة مرور الفريق — الحضور للعرض فقط`) | ✖ |
| `/admin/pos` (sell, cash POS orders) | ✔ | ✔ | ✔ (passcode sent with create-order) | ✖ |
| POS lifetime totals badge (`get_lifetime_ticket_totals`) | ✔ | ✖ (silently hidden) | ✖ | ✖ |
| Manual check-in dialog (search + mark present) | ✔ | ✔ | ✔ | ✖ |
| `/admin/qr-scanner` (scan, confirm presence) | ✔ | ✔ | ✔ (phone search empty; lookup fallback only) | ✖ |
| Check-in reset (`ticket-checkin mode:"reset"`) | ✔ | ✖ | ✖ | ✖ |
| `/admin/pos-receipts` | ✔ | UI only (RLS admin) | UI only | ✖ |
| `/admin/tickets` (ticket viewer) | ✔ | ✔ route; data per RLS | ✔ route; data per RLS | ✖ |
| `/live-bookings` | ✔ | ✔ | ✔ | ✖ |
| `/staff/attendance` | ✔ edit | ✔ edit | RO (server `account_required` on writes) | ✖ |
| `/staff/gate` gate board | ✔ | ✔ | ✔ | ✖ |
| Customer phone lookup in POS (`staff-customer-lookup`, returns `id_number`) | ✔ | ✔ | ✔ (PII defect) | ✖ |
| Settings, events, reports, logs, visitors analytics, notifications, staff accounts, POS users | ✔ | ✖ | ✖ | ✖ |
| Change order event, generate/regenerate QR, Sadad diagnose/transaction lookup, send-to-webhook | ✔ | ✖ | ✖ | ✖ |
| Read `login_attempts` | ✔ | ✖ | ✖ | ✖ |

Server precedence anomaly: the client guard treats a user with role `moderator` who is also in `admin_users` as moderator, while the server treats them as admin. The rebuild uses one precedence: admin > moderator.

#### Target roles matrix (rebuild)

| Capability | super_admin | admin | moderator / supervisor | cashier | gatekeeper | accountant | viewer | passcode kiosk | public |
|---|---|---|---|---|---|---|---|---|---|
| Filament panel access | ✔ | ✔ | ✖ | ✖ | ✖ | ✔ (finance + reports) | ✔ (read-only) | ✖ | ✖ |
| Settings (incl. Sadad secrets) | ✔ | ✔ (secrets write-only) | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ |
| Staff accounts & roles | ✔ | ✔ (cannot grant super_admin) | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ |
| Events, ticket types, current event | ✔ | ✔ | ✖ | ✖ | ✖ | RO | RO | ✖ | ✖ |
| Notifications, reports, Excel exports | ✔ | ✔ | ✖ | ✖ | ✖ | ✔ | RO (view) | ✖ | ✖ |
| Activity logs | ✔ (+ purge) | ✔ (view) | ✖ | ✖ | ✖ | ✔ (view) | ✖ | ✖ | ✖ |
| Visitor analytics, `/live-visitors` | ✔ | ✔ | ✖ | ✖ | ✖ | ✔ | ✔ | ✖ | ✖ |
| POS users & attendance admin | ✔ | ✔ | edit attendance | ✖ | ✖ | ✖ | RO | RO attendance | ✖ |
| `/staff` hub | ✔ | ✔ | ✔ | ✔ | ✔ | ✖ | ✖ | ✔ | ✖ |
| POS sell | ✔ | ✔ | ✔ | ✔ | ✖ | ✖ | ✖ | ✔ (if enabled) | ✖ |
| Scanner, manual check-in, gate board | ✔ | ✔ | ✔ | ✖ | ✔ | ✖ | ✖ | ✔ | ✖ |
| Check-in reset | ✔ | ✔ | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ |
| POS receipts OCR | ✔ | ✔ | ✔ | ✔ | ✖ | ✔ | ✖ | ✖ | ✖ |
| Customer PII (national ID) in staff apps | ✔ | ✔ | ✔ | masked | masked | ✔ | masked | masked | ✖ |

The target matrix extends the original; columns `cashier`, `gatekeeper`, `accountant`, `viewer`, `super_admin` are NEW. Each row is a permission (e.g. `settings.manage`, `staff.manage`, `pos.sell`, `checkin.reset`) seeded by a `RolesAndPermissionsSeeder`.

**Upgrades (new, not in original) — AUTH**
- **U-AUTH-01 (NEW)** · TOTP 2FA (Fortify) mandatory for `super_admin`/`admin`, optional for others.
- **U-AUTH-02 (NEW)** · Admin action "rotate team passcode" (hash stored, all passcode sessions invalidated, activity log), plus on/off switch for passcode login.
- **U-AUTH-03 (NEW)** · Read-only Filament page of `login_attempts` with filters (identifier, kind, success, date) and "unblock" action.
- **U-AUTH-04 (NEW)** · Login-block alert also for passcode lockouts (per-IP and global), same template with kind label `كلمة مرور الفريق`.
- **U-AUTH-05 (NEW)** · Session list per staff user with "sign out all devices".

**Original defects to avoid:**
- Passcode and handoff holders pass `adminOnly` guards (dashboard, `/live-visitors`); only a racy client redirect in the dashboard shell keeps them out. Enforce on the server.
- Team passcode stored in plaintext in localStorage/sessionStorage, re-sent on every staff call, and placed in URL hashes for handoff (leaks via history/screenshots).
- A missing expiry key silently stamps a fresh 10-day expiry (auto-extends legacy access).
- Account lockout is advisory (browser calls it; anyone can skip it and call the auth API directly) and fail-open on errors.
- Lockout endpoint is unauthenticated: anyone can record failures for a known admin e-mail → 30-min lockout DoS and alert spam.
- Block computed over only the 15-minute history window, so a "30-minute" account block effectively ends ~15 min after the 5th failure.
- `login-guard` and the passcode verifier share identifier `team-passcode` with different rules (5 vs 60), so 5 wrong passcode guesses make the guard report passcode login "blocked" for everyone.
- `login_blocked` activity rows (and `user_type:"staff"`) violate the `activity_logs` CHECK constraints → silently lost.
- Passcode lockouts never e-mail the admin (only account lockouts do).
- `useStaffRole` ignores `admin_users`; guard and server disagree for users with both moderator role and `admin_users` row.
- Staff list capped at 200 auth users; admins existing only in `admin_users` are not listed; users with two role rows listed twice.
- Role switch non-atomic and unvalidated; create ignores the role-insert error; no protection against removing self or the last admin; revoke has no confirmation; no audit log for any staff-account action; revoked users keep working sessions.
- Dashboard logout does not end passcode access; a signed-in non-staff user is stuck on the login card without a sign-out option.

---
### ADM-SHL — Admin dashboard shell

The layout that wraps every `/admin/dashboard/*` page: sticky header, grouped collapsible sidebar (desktop) or drawer (mobile), quick links, unread-notifications badge and the global capacity alert. Admin-only. Rebuild: Filament v4 panel `admin` at `/admin/dashboard` with a custom luxury theme, navigation groups, render hooks for the capacity alert, and a Livewire badge component.

- **F-ADM-SHL-001 · Route and index redirect** — Layout route `/admin/dashboard` guarded admin-only. The index `/admin/dashboard` redirects (replace) to `/admin/dashboard/orders`. Child routes: `notifications`, `orders`, `customers`, `transactions`, `events`, `tickets`, `invoices`, `settings`, `pos-users`, `visitors`, `reports`, `activity-logs`, `payment-errors`, `attendance`, `visitors-vs-scans`, `staff-accounts`. Sub-pages are lazy-loaded; while a page chunk loads, a centred spinner (48 px, primary border, spinning) is shown. Page direction `rtl`.
  - *Rebuild:* Filament panel `->path('admin/dashboard')`, `->homeUrl()` = Orders resource index; each child route becomes a Filament Resource or custom Page with the same slug so bookmarks keep working.

- **F-ADM-SHL-002 · Session check on mount** — The shell checks for an account session; none → navigate to `/admin/login`. It checks existence only, not role (role is the guard's job). This incidentally bounces passcode holders that the guard wrongly let in.
  - *Rebuild:* handled by panel middleware (`Authenticate` + role middleware); no client check.

- **F-ADM-SHL-003 · Sticky header** — Sticky, z-30, backdrop blur; background = `public_settings.header_bg_color` (default `hsl(var(--card) / 0.5)`). Contents (RTL):
  - Mobile menu button (ghost icon, hidden from `lg` up, aria-label `القائمة` "Menu") toggling the drawer.
  - Logo button → navigates to `/` (public site). Shows `<img src=logo_url>` at heights 32/40/48 px (base/sm/md); when no logo, H1 `لوحة تحكم المسؤول` ("Administrator dashboard").
  - Logout button (outline, LogOut icon): label `تسجيل الخروج` ("Log out") on ≥ sm, `خروج` ("Exit") on mobile. Action: sign out → `/admin/login` (see F-AUTH-025).
  - Header settings fetch errors are logged to the console only.
  - *Rebuild:* Filament `brandLogo()` from settings (spatie/laravel-settings `BrandingSettings`), topbar colour via theme CSS variable bound to `header_bg_color`; user menu with logout.

- **F-ADM-SHL-004 · Sidebar layout (desktop)** — Width 18 rem (`w-72`), sticky below the header (`top: 73px`, height `calc(100vh − 73px)`), visible at ≥ 1024 px only. Five groups, each a rounded bordered container; the group containing the active route gets `border-primary/30 bg-muted/40`. Group header button: group icon (primary colour when active), right-aligned label, chevron rotating 180° when open. Body animates open/close with CSS grid rows `0fr → 1fr` and opacity, 300 ms ease-out. Active link: solid primary background, primary-foreground text, elegant shadow; inactive: muted text, hover muted background.

- **F-ADM-SHL-005 · Sidebar group `الرئيسية` ("Home", LayoutDashboard icon)** — Items in order:
  1. `الإشعارات` ("Notifications", Bell) → `/admin/dashboard/notifications`, with unread badge (F-ADM-SHL-011).
  2. `الطلبات` ("Orders", ShoppingCart; i18n `orders`) → `/admin/dashboard/orders`.
  3. `العملاء` ("Customers", Users) → `/admin/dashboard/customers`.
  4. `المعاملات` ("Transactions", ArrowLeftRight) → `/admin/dashboard/transactions`.

- **F-ADM-SHL-006 · Sidebar group `الفعاليات والتذاكر` ("Events & tickets", Calendar)** — Items:
  1. `الفعاليات` ("Events", Calendar; i18n `events`) → `/admin/dashboard/events`.
  2. `التذاكر` ("Tickets", Ticket; i18n `tickets`) → `/admin/dashboard/tickets`.
  3. `إرسال الفواتير` ("Send invoices", FileText) → `/admin/dashboard/invoices`.

- **F-ADM-SHL-007 · Sidebar group `الفريق` ("Team", UserCog)** — Items:
  1. `مستخدمي POS` ("POS users", UserCog) → `/admin/dashboard/pos-users`.
  2. `حضور الفريق` ("Team attendance", ClipboardCheck) → `/admin/dashboard/attendance`.
  3. `حسابات الفريق` ("Team accounts", UserCog) → `/admin/dashboard/staff-accounts`.

- **F-ADM-SHL-008 · Sidebar group `التحليلات والتقارير` ("Analytics & reports", BarChart3)** — Items:
  1. `الزوار النشطون` ("Active visitors", Eye) → `/admin/dashboard/visitors`.
  2. `الزوار مقابل الحضور` ("Visitors vs attendance", BarChart3) → `/admin/dashboard/visitors-vs-scans`.
  3. `التقارير` ("Reports", FileText) → `/admin/dashboard/reports`.
  4. `سجلات النشاط` ("Activity logs", FileText) → `/admin/dashboard/activity-logs`.
  5. `أخطاء الدفع` ("Payment errors", AlertTriangle) → `/admin/dashboard/payment-errors`.

- **F-ADM-SHL-009 · Sidebar group `الإعدادات` ("Settings", Settings; i18n `settings`)** — Item: `الإعدادات العامة` ("General settings", Settings) → `/admin/dashboard/settings`.

- **F-ADM-SHL-010 · Group open state, auto-open and persistence** — Each group's open state = stored value if any, else open when it contains the active route (`openGroups[id] ?? hasActive`). Toggling flips and persists the whole map to localStorage key `admin_nav_groups` (JSON `{groupId: boolean}`), per browser. Quirk: a group the user explicitly closed stays closed even when navigating into it.
  - *Rebuild:* Filament collapsible navigation groups (`->collapsible()`), state persisted per user (user preferences JSON column) with the active group always forced open.

- **F-ADM-SHL-011 · Unread notifications badge** — Red pill (`bg-destructive`, rounded-full, min 20×20 px, 11 px bold, fade/zoom animation) on the `الإشعارات` item. Value = count of `orders` created **today (Qatar day)** with `created_at > lastSeen`, where `lastSeen` = localStorage `admin:notifications:lastSeenAt` (ISO; default epoch). All statuses are counted (pending, confirmed, cancelled). Displays `+99` when > 99, else the number formatted `ar-u-nu-latn`. Forced to 0 while the current page is the notifications page. Recomputed: every **30 000 ms**, on every realtime INSERT on `orders` (channel `admin-nav-notifications`), and on window event `admin:notifications:seen` (fired by mark-all-read). Errors swallowed.
  - *Rebuild:* `last_seen_notifications_at` column per admin user; badge via Filament `->badge()` computed by a cached query; live update through Reverb private channel `admin.orders` (`OrderCreated` broadcast) plus 30 s polling fallback (`wire:poll.30s`).

- **F-ADM-SHL-012 · Quick links section** — Caption `روابط سريعة` ("Quick links", 11 px uppercase, letter-spacing 0.18em), below the groups. Items in order:
  1. `نقاط البيع` ("Points of sale", CreditCard) → opens `/admin/pos` in a **new tab**.
  2. `مسح التذكرة` ("Scan ticket", ScanLine; i18n `scanTicket`) → `/admin/qr-scanner` in the **same tab**.
  3. `قراءة إيصال POS` ("Read POS receipt", Receipt) → `/admin/pos-receipts` same tab.
  4. `الحجوزات المباشرة` ("Live bookings", ExternalLink; i18n `liveBookings`) → `/live-bookings` new tab.
  5. `الزوار المباشرون` ("Live visitors", Eye) → `/live-visitors` new tab (page specified in the staff-ops section).
  6. `الموقع الرئيسي` ("Main website", ExternalLink; i18n `mainWebsite`) → `/` new tab.
  - *Rebuild:* Filament navigation group "روابط سريعة" with `NavigationItem::make()->url(..., shouldOpenInNewTab: true)` preserving same-tab/new-tab behaviour.

- **F-ADM-SHL-013 · Mobile drawer** — Below 1024 px the sidebar becomes a drawer sliding in from the right, width 18 rem (max 85vw), over an overlay `bg-foreground/40` with backdrop blur. Same groups, items and quick links. Closes on overlay click and on any route change. Defect: the toggle button shows the Menu icon in both states (never an X).

- **F-ADM-SHL-014 · Main content area and global capacity alert** — Container `max-w-7xl`. The capacity alert (F-ADM-CAP-001) is always rendered at the top of every dashboard page, then the page. While header settings load, a centred `جاري التحميل...` ("Loading...") text (py-12) is shown instead of the page.
  - *Rebuild:* Filament render hook `PanelsRenderHook::CONTENT_START` mounting the Livewire `CapacityAlert` component.

- **F-ADM-SHL-015 · Global app wrappers** — Whole app: chunk-load error boundary (reload prompt when a lazy chunk fails), visitor tracker on every route (F-ADM-VIS-016), Clarity tracker (public routes only), toast host at top-right, expanded, up to 6 visible toasts, 12 px gap.
  - *Rebuild:* Filament notifications (top-right, max 6 stacked) for admin; Livewire toasts in staff apps. Visitor tracker only on public layout.

- **F-ADM-SHL-016 · Staff accounts page wrapper** — `/admin/dashboard/staff-accounts` renders a hero card (gradient from primary/10 via card to card, rounded-2xl, `dir=rtl`) with eyebrow `حسابات الفريق` (11 px, uppercase, tracking 0.25em), H2 `إدارة الدخول والصلاحيات` ("Sign-in and permissions management"), subtitle `أنشئ حسابات للموظفين وحدّد من يملك صلاحية لوحة التحكم الكاملة.` ("Create staff accounts and decide who has full dashboard access."), then the staff accounts card (F-AUTH-026…030). `/admin/dashboard/pos-users` stacks the same staff accounts card above the POS users card (`space-y-6`).

**Upgrades (new, not in original) — ADM-SHL**
- **U-ADM-SHL-01 (NEW)** · Global search (Filament global search) over orders by booking reference, customers by phone/name.
- **U-ADM-SHL-02 (NEW)** · Luxury active-item highlight specified in the 2026-09-15 plan (gradient + thin side bar) — the original only implemented a solid primary background with shadow.
- **U-ADM-SHL-03 (NEW)** · Server-side per-admin "last seen" so the badge is consistent across devices.

**Original defects to avoid:**
- Mobile menu icon never switches to X.
- Read state (`lastSeen`) per browser; two admins/devices disagree.
- Badge counts all statuses (pending/failed included); define explicitly (keep "all new orders" but label it).
- Shell session check is the only thing stopping passcode holders from viewing dashboard UI (racy redirect).
- Explicitly closed group stays closed even when it contains the active page.
- Logout leaves passcode access active.

---

### ADM-SET — Settings

Global configuration stored in a single settings row, edited at `/admin/dashboard/settings` (sidebar `الإعدادات العامة`). Admin-only. Three tabs: design & display, integrations (n8n, invoice automation, Sadad, admin contact, payment-failed mail, Clarity), and data management (Excel templates/import/export, bulk delete). Rebuild: spatie/laravel-settings classes (`BrandingSettings`, `FeatureFlagSettings`, `IntegrationSettings`, `InvoiceAutomationSettings`, `SadadSettings`, `NotificationSettings`, `AnalyticsSettings`, `EventSettings`) edited by a custom Filament `SettingsPage` with the same three tabs; uploads via spatie/laravel-medialibrary.

#### Settings keys

Every key of the original `settings` row, with type, default, control and validation. "Public" = exposed to anonymous visitors through the `public_settings` view.

| Key | DB type | Default | Control (tab › card) | Validation / limits | Public | Feature |
|---|---|---|---|---|---|---|
| `id` | uuid PK | generated | — (singleton id) | one row only | yes | F-ADM-SET-001 |
| `logo_url` | text null | null | Design › Logo: URL input + PNG/JPG upload | upload ≤ 5 MB, `image/png|jpeg|jpg`; URL required to press main Save | yes | F-ADM-SET-008 |
| `hero_image_url` | text null | null | Design › Hero background: URL input + PNG/JPG upload | upload ≤ 10 MB | yes | F-ADM-SET-005 |
| `before_footer_image_url` | text null | null | Design › Before-footer image: PNG upload | PNG only, ≤ 10 MB | yes | F-ADM-SET-006 |
| `header_bg_image_url` | text null | null | Design › Header background image: PNG/JPG upload | ≤ 10 MB | yes | F-ADM-SET-007 |
| `header_bg_color` | text null | `hsl(var(--card) / 0.5)` | Design › Logo card: colour picker + text | none (any CSS string) | yes | F-ADM-SET-009 |
| `hero_text` | text null | null | Design › Logo card: textarea | none | yes | F-ADM-SET-010 |
| `copyright_text` | text null | `جميع الحقوق محفوظة` | Design › Logo card: input | none | yes | F-ADM-SET-011 |
| `webhook_url` | text null | null | Integrations › WhatsApp webhook: URL input | none | no | F-ADM-SET-018 |
| `webhook_enabled` | bool NOT NULL | true | Integrations › WhatsApp webhook: switch | — | no | F-ADM-SET-018 |
| `email_webhook_url` | text null | null | Integrations › E-mail webhook: URL input | none | no | F-ADM-SET-019 |
| `email_webhook_enabled` | bool NOT NULL | true | Integrations › E-mail webhook: switch | — | no | F-ADM-SET-019 |
| `admin_phone` | text null | null | Integrations › Admin phone: tel input | none | **yes** | F-ADM-SET-027 |
| `admin_email` | text null | null | Integrations › Payment-failed mail: e-mail input | regex `^[^@\s]+@[^@\s]+\.[^@\s]+$`; empty → NULL | no | F-ADM-SET-029 |
| `payment_failed_email_enabled` | bool NOT NULL | true | Integrations › Payment-failed mail: switch | — | no | F-ADM-SET-028 |
| `clarity_project_id` | text null | null | Integrations › Clarity: input | regex `^[a-z0-9]{4,20}$` (case-insensitive) | yes | F-ADM-SET-031 |
| `clarity_enabled` | bool NOT NULL | false | Integrations › Clarity: switch | enabling requires valid project id | yes | F-ADM-SET-030 |
| `sadad_merchant_id` | text null | null | Integrations › Sadad: text | none | no | F-ADM-SET-023 |
| `sadad_api_key` | text null | null | Integrations › Sadad: text ("Support Pin Number") | none; unused by payment flow | no | F-ADM-SET-023 |
| `sadad_secret` | text null | null | Integrations › Sadad: password | none | no | F-ADM-SET-023 |
| `sadad_website_domain` | text null | null | Integrations › Sadad: text | none | no | F-ADM-SET-023 |
| `sadad_environment` | text NOT NULL | `auto` | Integrations › Sadad: select | CHECK in (`auto`,`sandbox`,`live`) | no | F-ADM-SET-024 |
| `site_url` | text null | null | Integrations › Sadad: URL | trimmed; empty → NULL | no | F-ADM-SET-025 |
| `show_delete_customer_button` | bool null | false | Design › Buttons: switch | — | yes | F-ADM-SET-013 |
| `show_generate_qr_button` | bool null | false | Design › Buttons: switch | — | yes | F-ADM-SET-014 |
| `show_delete_event_button` | bool null | false | Design › Buttons: switch | — | yes | F-ADM-SET-015 |
| `show_delete_ticket_button` | bool NOT NULL | true | Design › Buttons: switch | — | no | F-ADM-SET-016 |
| `auto_invoice_interval_seconds` | int null | 60 | Integrations › Auto-invoice: number | min 10 | no | F-ADM-SET-020 |
| `invoice_batch_min` | int null | 1 | Integrations › Batch range: number | ≥ 1 | no | F-ADM-SET-021 |
| `invoice_batch_max` | int null | 10 | Integrations › Batch range: number | ≥ min | no | F-ADM-SET-021 |
| `invoice_send_delay_min` | int null | 300 | Integrations › Delay range: number (s) | ≥ 1 | no | F-ADM-SET-022 |
| `invoice_send_delay_max` | int null | 600 | Integrations › Delay range: number (s) | ≥ min | no | F-ADM-SET-022 |
| `current_event_id` | uuid null, FK events ON DELETE SET NULL | null | Events page › Current event selector | must be a non-archived event or null | yes | F-ADM-EVT-022 |
| `last_invoice_sent_at` | timestamptz null | null | not in UI; written by the auto-send job after each batch | — | no | F-ADM-SET-020 |
| `created_at` / `updated_at` | timestamptz | now() | housekeeping; `updated_at` trigger | — | no | F-ADM-SET-001 |

- **F-ADM-SET-001 · Settings singleton and persistence pattern** — One logical row. Every save follows "upsert the singleton": read the row id; if present update the changed columns by id, else insert. Some handlers omit `limit(1)` (would error with >1 row). Generic success toast `تم الحفظ بنجاح` ("Saved successfully"); generic error `فشل في حفظ الإعداد` ("Failed to save the setting") unless stated otherwise. `updated_at` maintained by trigger.
  - *Rebuild:* spatie/laravel-settings (one record per property, typed, cached); every save writes an activity-log entry with changed keys (secret values redacted).

- **F-ADM-SET-002 · Load on open** — Reads 31 columns (`logo_url, hero_image_url, before_footer_image_url, header_bg_color, header_bg_image_url, hero_text, copyright_text, webhook_url, email_webhook_url, webhook_enabled, email_webhook_enabled, admin_phone, admin_email, payment_failed_email_enabled, clarity_project_id, clarity_enabled, sadad_merchant_id, sadad_api_key, sadad_secret, sadad_website_domain, sadad_environment, site_url, show_delete_customer_button, show_generate_qr_button, show_delete_event_button, show_delete_ticket_button, auto_invoice_interval_seconds, invoice_batch_min, invoice_batch_max, invoice_send_delay_min, invoice_send_delay_max`). Errors → console only (form keeps defaults). Empty strings do not overwrite client defaults; `sadad_environment` applied only if `sandbox|live` (else stays `auto`). Client defaults when DB is null: header colour `hsl(var(--card) / 0.5)`, both webhook switches true, payment-failed mail true, Clarity false, Sadad env `auto`, delete-customer false, generate-QR false, delete-event false, delete-ticket true, interval 60, batch 1–10, delay 300–600.
  - *Rebuild:* never send secret values to the browser (see F-ADM-SET-023).

- **F-ADM-SET-003 · Page layout and tabs** — Container `max-w-4xl`, H2 `الإعدادات` ("Settings"). Three equal-width tabs, default `design`: `التصميم والعرض` ("Design & display"), `التكاملات (n8n & Sadad)` ("Integrations"), `إدارة البيانات` ("Data management").

- **F-ADM-SET-004 · Image upload storage** — Uploads go to the public bucket `qr-codes` (public read; write/update/delete admin only) under prefixes `logos/`, `hero-images/`, `before-footer/`, `header-bg/`, cache-control 3600, upsert. File names include `Date.now()`. Old files are never deleted.
  - *Rebuild:* medialibrary collections on the settings owner model (`logo`, `hero`, `before_footer`, `header_bg`), `singleFile()` so replacing deletes the old file, conversions (WebP, responsive), public disk. Add "remove image" actions.

- **F-ADM-SET-005 · Hero background image card** — Card `صورة خلفية الصفحة الرئيسية` ("Homepage background image"). Preview `الصورة الحالية` ("Current image") in a dashed box (`max-h-48 object-cover`; placeholder icon when empty). URL input `رابط صورة الخلفية` (type url, placeholder `https://example.com/hero-image.jpg`) — saved only by the main Save of the logo card (F-ADM-SET-012). File input `أو تحميل صورة (PNG/JPG)` ("Or upload an image"), accept `image/png,image/jpeg,image/jpg`; help `اختر صورة PNG أو JPG (حد أقصى 10 ميغابايت) - يفضل 1920x1080 بكسل` ("PNG or JPG, max 10 MB — 1920×1080 preferred"). Upload: MIME must match `image/(png|jpeg|jpg)` else `يرجى اختيار صورة PNG أو JPG`; size ≤ **10 MB** else `حجم الصورة يجب أن يكون أقل من 10 ميغابايت`; path `hero-images/hero-{ts}.{ext}`; public URL immediately persisted to `hero_image_url`; toast `تم تحميل صورة الخلفية بنجاح`; input cleared; error `فشل في تحميل الصورة`. Consumer: public home hero.

- **F-ADM-SET-006 · Before-footer image card** — Card `صورة قبل الفوتر` ("Image before the footer"). Preview (`max-h-48 object-contain`). File input `تحميل صورة PNG` accept `image/png` only, disabled while loading; help `اختر صورة PNG فقط (حد أقصى 10 ميغابايت) - ستظهر قبل الفوتر مباشرة` ("PNG only, max 10 MB — shown just before the footer"). Validation: MIME `image/png` else `يرجى اختيار صورة PNG فقط`; ≤ 10 MB. Path `before-footer/before-footer-{ts}.png`; persists `before_footer_image_url`; toast `تم تحميل الصورة بنجاح`. No URL input, no remove. Consumers: home, ticket selection, checkout.

- **F-ADM-SET-007 · Header background image card** — Card `صورة خلفية الترويسة (الصفحة الرئيسية)` ("Header background image (homepage)"). Preview `max-h-32` with overlay gradient `linear-gradient(to left, rgba(0,0,0,0.8), rgba(0,0,0,0.4))` and white label `معاينة التدرج` ("Gradient preview"). File `تحميل صورة (PNG/JPG)`, ≤ 10 MB, path `header-bg/header-bg-{ts}.{ext}`, persists `header_bg_image_url`, toast `تم تحميل صورة خلفية الترويسة بنجاح`. Help `اختر صورة PNG أو JPG (حد أقصى 10 ميغابايت) - ستظهر خلف الترويسة مع تدرج شفافية 80% إلى 40%` ("…shown behind the header with an 80%→40% transparency gradient"). Consumers: home header, footer.

- **F-ADM-SET-008 · Logo card (URL + upload)** — Card `رفع الشعار` ("Logo upload"). Preview `الشعار الحالي` ("Current logo", `max-h-32 object-contain`). URL input `رابط الشعار` (placeholder `https://example.com/logo.png`). File `أو تحميل صورة (PNG/JPG)`, ≤ **5 MB** (`حجم الصورة يجب أن يكون أقل من 5 ميغابايت`), path `logos/logo-{ts}.{ext}`, persisted immediately to `logo_url`, toast `تم تحميل الشعار بنجاح`. Help `اختر صورة PNG أو JPG (حد أقصى 5 ميغابايت)`. Consumers: every header (admin, login, POS, scanner, live boards, staff hub, attendance, ticket viewer), public pages, invoice card/page/PDF, all e-mail templates.

- **F-ADM-SET-009 · Header background colour** — Highlighted box `لون خلفية الترويسة` ("Header background colour"): native colour input (value = current if it starts with `#`, else `#ffffff`) and a text input (placeholder `#ffffff`), both bound to the same value; help `أدخل رمز لون hex (مثال: #ffffff) أو استخدم منتقي الألوان` ("Enter a hex code or use the colour picker"). No validation. Saved by the main Save.
  - *Rebuild:* validate `regex:/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i` or the literal default token; Filament `ColorPicker`.

- **F-ADM-SET-010 · Homepage hero text** — Textarea `نص الصفحة الرئيسية` ("Homepage text"), 4 rows, min-height 100 px, placeholder `أدخل النص الذي سيظهر في صورة الخلفية`, help `النص الذي سيظهر في منتصف صورة الخلفية على الصفحة الرئيسية` ("Text shown centred on the homepage background"). Saved by main Save.

- **F-ADM-SET-011 · Footer copyright text** — Input `نص حقوق الطبع` ("Copyright text"), placeholder `جميع الحقوق محفوظة`, help `النص الذي سيظهر في تذييل الصفحة` ("Text shown in the page footer"). DB default `جميع الحقوق محفوظة`. Saved by main Save.

- **F-ADM-SET-012 · Main branding Save** — Button (Upload icon) `حفظ` / `جاري التحميل...`. Validation: logo URL must be non-empty else `الرجاء إدخال رابط الشعار` ("Please enter the logo URL"). Writes six columns together: `logo_url, hero_image_url, header_bg_color, hero_text, copyright_text, webhook_url` (the WhatsApp webhook URL from the Integrations tab is coupled here). Error toast `فشل في حفظ الشعار`.
  - *Rebuild:* one Save per tab section with only that section's fields; logo optional.

- **F-ADM-SET-013 · Toggle: show delete-customer button** — Card `إعدادات الأزرار` ("Button settings"). Switch `إظهار زر حذف العميل` ("Show delete-customer button"), help `عند التفعيل، سيظهر زر حذف العملاء في صفحة العملاء`; column `show_delete_customer_button` (default false); saves immediately (switch disabled while saving); consumers: customers page and orders page. Toasts `تم الحفظ بنجاح` / `فشل في حفظ الإعداد`.

- **F-ADM-SET-014 · Toggle: show generate-QR button** — Switch `إظهار زر إنشاء رموز QR` ("Show generate QR codes button"), help `عند التفعيل، سيظهر زر إنشاء رموز QR في صفحة الطلبات`; column `show_generate_qr_button` (default false); consumer: orders page.

- **F-ADM-SET-015 · Toggle: show delete-event button** — Switch `إظهار زر حذف الفعالية` ("Show delete-event button"), help `عند التفعيل، سيظهر زر حذف الفعالية في صفحة الفعاليات`; column `show_delete_event_button` (default false); consumer: events page (F-ADM-EVT-016).

- **F-ADM-SET-016 · Toggle: show delete-ticket button** — Switch `إظهار زر حذف التذكرة` ("Show delete-ticket button"), help `عند التفعيل، سيظهر زر حذف كل تذكرة في صفحة إدارة التذاكر (تُنقل إلى سلة المحذوفات)` ("…each ticket's delete button appears on the tickets page (moved to the recycle bin)"); column `show_delete_ticket_button` (NOT NULL, default **true**); consumer: booked tickets by date (tickets page).
  - *Rebuild (all four toggles):* these are UI-visibility flags only; the server must still authorise every delete through Policies. Store in `FeatureFlagSettings`.

- **F-ADM-SET-017 · Integrations tab layout** — Cards in order: WhatsApp webhook (F-ADM-SET-018), e-mail webhook (019), auto-invoice interval (020), invoice batch range (021), delay between invoices (022), Sadad credentials/environment/site URL (023–025), Sadad readiness check (026), Sadad troubleshooting guide (032), admin phone (027), payment-failed e-mail and admin e-mail (028–029), Microsoft Clarity (030–031).

- **F-ADM-SET-018 · WhatsApp n8n webhook** — Card `رابط Webhook واتساب (n8n)`. Header status text `مفعّل` ("Enabled", primary) / `معطّل` ("Disabled", muted) + switch → `webhook_enabled`, saved immediately, toasts `تم تفعيل ويب هوك واتساب` / `تم تعطيل ويب هوك واتساب`. Body dims (`opacity-60`) when disabled but stays editable. Input `رابط Webhook واتساب` (type url, placeholder `https://your-n8n-instance.com/webhook/...`), help `سيتم استدعاء هذا الرابط بعد كل حجز ناجح لإرسال الفاتورة عبر واتساب` ("Called after every successful booking to send the invoice via WhatsApp"). Save button calls the main branding save (F-ADM-SET-012) — coupling defect. Server semantics: `webhook_enabled=false` makes the URL treated as null. Consumers: invoices page, customers page, order notifications, auto-send job, send-to-webhook.

- **F-ADM-SET-019 · E-mail n8n webhook** — Card `رابط Webhook البريد الإلكتروني (n8n)`. Switch → `email_webhook_enabled` (toasts `تم تفعيل ويب هوك البريد` / `تم تعطيل ويب هوك البريد`). Input `رابط Webhook البريد` (placeholder `https://your-n8n-instance.com/webhook/email...`), help `سيتم استدعاء هذا الرابط لإرسال الفاتورة عبر البريد الإلكتروني (مختلف عن واتساب)` ("Called to send the invoice by e-mail (different from WhatsApp)"). Own Save button; no URL validation. Server: disabled → null; falls back to `webhook_url` when empty.
  - *Rebuild:* validate `url:https`; "Send test" button (NEW) posting a sample payload and showing the HTTP status.

- **F-ADM-SET-020 · Auto-invoice send interval** — Card `إعدادات الإرسال التلقائي للفواتير` ("Automatic invoice sending"). Number `الفاصل الزمني (بالثواني)` ("Interval (seconds)"), min 10, placeholder 60, parsed `parseInt || 60` → `auto_invoice_interval_seconds`. Help `الفاصل الزمني بين كل إرسال تلقائي للفواتير (الحد الأدنى: 10 ثوانٍ)`; yellow warning `⚠️ تنبيه: سيتم إرسال الفواتير تلقائياً حتى لو كان التطبيق مغلقاً` ("Invoices will be sent automatically even if the app is closed"). Validation `< 10` → `يجب أن يكون الفاصل الزمني 10 ثوانٍ على الأقل`. Server job reads interval (default 60), `last_invoice_sent_at`, batch min/max and writes `last_invoice_sent_at` after each batch (job itself specified in the backend section).
  - *Rebuild:* scheduler runs every minute; job checks `now − last_invoice_sent_at ≥ interval` under a Redis lock.

- **F-ADM-SET-021 · Invoice batch size range** — Card `نطاق عدد الفواتير المرسلة` ("Range of invoices sent"). `الحد الأدنى` (min 1, placeholder 1, `parseInt||1`) and `الحد الأقصى` (min 1, placeholder 10, `parseInt||10`) → `invoice_batch_min/max`. Help `عند الضغط على "بدء العد التنازلي" أو "إرسال الفواتير"، سيتم إرسال عدد الفواتير بين الحد الأدنى والأقصى` ("When pressing Start countdown or Send invoices, a number of invoices between min and max is sent"). Validation: min < 1 → `يجب أن يكون الحد الأدنى 1 على الأقل`; max < min → `يجب أن يكون الحد الأقصى أكبر من أو يساوي الحد الأدنى`.

- **F-ADM-SET-022 · Delay between invoice sends** — Card `التأخير بين إرسال الفواتير (بالثواني)` ("Delay between invoice sends (seconds)"). `الحد الأدنى (ثانية)` (placeholder 300, `||300`) and `الحد الأقصى (ثانية)` (placeholder 600, `||600`) → `invoice_send_delay_min/max`. Help `الوقت العشوائي للانتظار بين إرسال كل فاتورة والتالية (بالثواني)`; yellow example `مثال: إذا كانت القيمة من 300 إلى 600 ثانية، سيتم الانتظار بين 5 إلى 10 دقائق بين كل فاتورة`. Validation: min < 1 → `يجب أن يكون الحد الأدنى ثانية واحدة على الأقل`; max < min → same message as F-ADM-SET-021. Consumer: invoices page random delay between sends. Quirk: `|| default` means typing 0 resets to the default.
  - *Rebuild:* Laravel validation `integer|min:1` and `gte:` rules; no silent default substitution.

- **F-ADM-SET-023 · Sadad credentials** — Card `دفع سداد` ("Sadad payment"), single Save (error `فشل في حفظ إعدادات سداد`). Fields:
  - `معرف التاجر (Merchant ID)` — text, placeholder `أدخل معرف التاجر` → `sadad_merchant_id`.
  - `رقم الدعم (Support Pin Number)` — text, placeholder `أدخل رقم الدعم`, help `رقم الدعم الخاص بحساب سداد - Support Pin Number` → `sadad_api_key` (shown in clear; never used by the payment flow).
  - `المفتاح السري` ("Secret key") — password input, placeholder `أدخل المفتاح السري` → `sadad_secret` (but the value is loaded into the browser).
  - `النطاق المسجل (Website Domain)` — text, placeholder `مثال: qcamelmc.org أو www.qcamelmc.org`, help `يجب أن يطابق النطاق المسجل في لوحة سداد تماماً عند إنشاء المفتاح السري` ("Must exactly match the domain registered in the Sadad panel when the secret was created") → `sadad_website_domain`.
  - No validation on any field. Sadad counts as "configured" when merchant id, secret and domain are all non-blank.
  - *Rebuild:* secrets in `.env` or encrypted settings (`encrypted` cast); UI shows "مُعيّن / غير مُعيّن" (set / not set) and a replace-only field; separate `checkout_secret` and `api_login_secret` (the original confuses them); validate merchant id digits and domain host format (no scheme). Every change logged (values redacted).

- **F-ADM-SET-024 · Sadad environment** — Select `بيئة سداد` ("Sadad environment"): `auto` = `تلقائي (يُكتشف من مفتاحك)` ("Automatic (detected from your key)"), `sandbox` = `تجريبي (Sandbox)`, `live` = `فعلي (Live)`. Help `يُستخدم للتحقق من الدفع مع سداد. اتركه «تلقائي» إلا إذا طلب منك الدعم غير ذلك.` ("Used to verify payments with Sadad. Leave it on Automatic unless support tells you otherwise."). Column NOT NULL default `auto`, CHECK in (auto, sandbox, live); unknown values treated as auto.

- **F-ADM-SET-025 · Site URL (post-payment return)** — `عنوان الموقع (اختياري)` ("Site address (optional)"), type url, LTR, placeholder `https://qcamelmc.org`, help `إليه يعود العميل بعد الدفع. اتركه فارغاً ليعود إلى الموقع الذي حجز منه.` ("The customer returns here after paying. Leave empty to return to the site they booked from."). Trimmed; empty → NULL. Server accepts it as return base only if it matches the safe-origin pattern (bare https origin with a dot, optional port, or `http://localhost[:port]`, no path); otherwise falls back to the stored booking origin (open-redirect protection).

- **F-ADM-SET-026 · Sadad readiness check (diagnostic)** — Card `فحص جاهزية الدفع` ("Payment readiness check"). Intro before running: `احفظ إعدادات سداد ثم شغّل الفحص: يتأكد من الإعدادات ويجرّب الاتصال بسداد فعلياً (اختبار أو إنتاج) ويعرض الروابط التي يجب تسجيلها في لوحة سداد.` Button `تشغيل الفحص` / `جارٍ الفحص...` (spinner) calls the admin-only diagnose endpoint. Report: badge `جاهز للدفع` ("Ready for payment") or `يحتاج إلى إصلاح` ("Needs fixing", destructive); environment badge `بيئة الاختبار (Sandbox)` or `بيئة الإنتاج (Live)`. Check list, each with status icon ok (green check) / warn (amber triangle) / error (red alert) and a message: `merchant_id` `معرف التاجر`, `secret` `المفتاح السري`, `website` `النطاق المسجل`, `api_login` `الاتصال بواجهة سداد`, `api_lookup` `التحقق من المدفوعات`, `callback_url` `رابط العودة بعد الدفع`, `webhook_registration` `تسجيل الـ Webhook في سداد`, `automation` `الأتمتة (n8n)`, `site_url` `عنوان الموقع`. Copy fields (toast `تم النسخ` "Copied"): `سجّل هذا الرابط في لوحة سداد (Payment Gateway ← Webhook):` → Webhook URL; `Callback URL (يُرسل مع كل طلب تلقائياً)` → callback URL. Error → toast with the API error message. (The check logic itself is in the backend section, Sadad diagnose.)
  - *Rebuild:* Filament action invoking `App\Payments\Sadad\Diagnostics`; results rendered as an infolist; URLs = `route('payments.sadad.webhook')` / `route('payments.sadad.callback')` (https).

- **F-ADM-SET-027 · Admin phone** — Card `رقم هاتف الإدارة` ("Management phone"). Input `رقم الهاتف` (tel, placeholder `+974 XXXX XXXX`), help `رقم هاتف المسؤول للتواصل (مثال: +974 12345678)`. No validation. Error `فشل في حفظ رقم الهاتف`. Public (exposed to visitors; used by the public WhatsApp button), also used in customers page and n8n payloads (digits with country code, default +974).
  - *Rebuild:* validate E.164 (`propaganistas/laravel-phone`), store normalised; keep public exposure explicit (it is intentionally the contact number).

- **F-ADM-SET-028 · Payment-failed customer e-mail toggle** — Card `بريد إشعار فشل الدفع` ("Payment-failure notification e-mail"). Switch `مفعّل`/`معطّل` → `payment_failed_email_enabled` (NOT NULL default true); toasts `تم تفعيل إشعار فشل الدفع للعميل` / `تم إيقاف إشعار فشل الدفع للعميل`; error `فشل في تحديث الإعداد`. Explanation: `عند التفعيل يصل العميل بريد بالعربية يوضح المبلغ ورقم الحجز وسبب الرفض (البنك/البطاقة أو بوابة سداد) مع رابط لإعادة المحاولة ورابط متابعة الحجز، ولا يتكرر الإرسال لنفس الطلب.` ("When enabled the customer receives an Arabic e-mail with amount, booking number and rejection reason (bank/card or Sadad gateway), a retry link and a booking-tracking link; never sent twice for the same order.").

- **F-ADM-SET-029 · Admin e-mail** — Input `بريد الإدارة (يستلم نسخة دائماً)` ("Management e-mail (always receives a copy)"), placeholder `admin@example.com`, help `تصل نسخة من كل إشعار فشل دفع إلى هذا البريد حتى لو كان الإشعار للعميل معطّلاً.` Validation: non-empty must match `^[^@\s]+@[^@\s]+\.[^@\s]+$` else `البريد الإلكتروني غير صالح`; empty saves NULL. Error `فشل في حفظ بريد الإدارة`. Recipients: payment-failed admin copy (always), sale alert for every confirmed sale incl. POS, login-block alert (F-AUTH-017).
  - *Rebuild:* allow a list of recipients (NEW), Laravel `email:rfc,dns` validation.

- **F-ADM-SET-030 · Microsoft Clarity enable switch** — Card `تحليلات Microsoft Clarity (خرائط الحرارة)` ("Microsoft Clarity analytics (heatmaps)") with a top gradient accent bar. Subtitle `تتبّع زوّار الموقع العام فقط — لا يعمل في نقاط البيع أو صفحات الفريق أو لوحة التحكم.` ("Tracks public-site visitors only — not POS, team pages or dashboard."). Switch `مفعّل`/`معطّل`: enabling requires the project id to match `^[a-z0-9]{4,20}$` (case-insensitive) else toast `أدخل معرّف مشروع Clarity أولاً ثم فعّل التتبّع`; enabling writes `{clarity_enabled:true, clarity_project_id: trimmed}`; disabling writes `{clarity_enabled:false}`. Toasts `تم تفعيل تتبّع Microsoft Clarity` / `تم إيقاف تتبّع Microsoft Clarity`.

- **F-ADM-SET-031 · Microsoft Clarity project id** — Input `معرّف المشروع (Project ID)` (LTR, monospace, placeholder `abcd1234ef`), help `تجده في clarity.microsoft.com ضمن Settings ← Overview ← Project ID.` Info box `ما الذي يُسجَّل؟` ("What is recorded?"): `جلسات الزوّار وخرائط الحرارة والنقرات على الصفحة الرئيسية واختيار التذاكر والدفع.` / `حدث «ticket_purchase» عند إتمام الشراء مع رقم الحجز والمبلغ وعدد التذاكر.` / `حدث «payment_failed» عند فشل أو إلغاء الدفع.` Save: non-empty must match the regex else `معرّف المشروع غير صالح (أحرف وأرقام فقط)`; writes `{clarity_project_id: value || null, clarity_enabled: value ? current : false}`; error `فشل في حفظ إعدادات Clarity`. Consumer: public Clarity loader (public routes only; events `ticket_purchase`, `payment_failed`).

- **F-ADM-SET-032 · Sadad troubleshooting guide (static)** — Orange card `🔍 دليل استكشاف أخطاء سداد` ("Sadad troubleshooting guide"):
  - Box `❌ خطأ: "Checksumhash did not match"` with intro `هذا الخطأ يحدث عندما لا تتطابق بيانات الطلب مع ما هو مسجل في لوحة سداد. الأسباب الشائعة:`
  - `1️⃣ وضع الاختبار (Test Mode) غير مفعّل` — `يجب تفعيل وضع الاختبار من: لوحة التاجر → API → Test Mode (تبديل الزر)`.
  - `2️⃣ النطاق (Domain) غير متطابق` — `يجب أن يطابق النطاق أعلاه ما هو مسجل في لوحة سداد عند إنشاء المفتاح السري`; monospace box `النطاق الحالي: {sadad_website_domain || "غير محدد"}`; warning `⚠️ انتبه: الفرق بين "qcamelmc.org" و "www.qcamelmc.org" مهم!`.
  - `3️⃣ المفتاح السري غير صحيح` — `جرب إعادة توليد المفتاح السري من: لوحة التاجر → API → Generate New Test Key`.
  - `4️⃣ Web Checkout 2.2 غير مفعّل` — `إذا لم يعمل بعد التحقق من النقاط السابقة، اتصل بدعم سداد لتفعيل Web Checkout 2.2`.
  - `✅ خطوات التحقق السريع:` ordered list: open `لوحة التاجر سداد` (link `https://webpanel.sadad.qa/authentication/login`, new tab); `اذهب إلى قسم "API" من القائمة الجانبية`; `تأكد أن زر "Test Mode" مفعّل (أخضر)`; `تحقق من أن "Sadad ID" = {merchant_id || "؟؟؟"}`; `انسخ المفتاح السري الموجود والصقه أعلاه (بدون مسافات)`; `تحقق من النطاق المسجل عند إنشاء المفتاح السري`; `احفظ الإعدادات وجرب الدفع مرة أخرى`.
  - Tip `💡 نصيحة: تحقق من سجلات Edge Function للحصول على تفاصيل أكثر عن الخطأ`.
  - *Rebuild:* same content as a collapsible Filament section; replace the last tip with "check the payment events log (سجل أحداث الدفع)"; note that the integration targets Web Checkout 2.1.

- **F-ADM-SET-033 · Data management tab (danger zone)** — Red card with AlertTriangle and `تحذير: منطقة خطرة` ("Warning: danger zone"), text `العمليات التالية لا يمكن التراجع عنها. يرجى التأكد من عمل نسخة احتياطية قبل الحذف.` ("The following operations are irreversible. Make a backup before deleting."). Three sections: events, ticket types (`إدارة التذاكر`; here "tickets" = ticket TYPES per event, not issued tickets), customers. All three exports share one busy flag and all deletes share one busy flag (buttons disable together).

- **F-ADM-SET-034 · Download events Excel template** — Button `تحميل القالب` ("Download template", outline, Download icon) → file `events_template.xlsx`, sheet `Events Template`, one sample row: `title:"اسم الفعالية"`, `description:"وصف الفعالية"`, `event_date:"2025-01-15T18:00:00"`, `location:"الموقع"`, `image_url:"https://example.com/image.jpg"`, `video_url:"https://example.com/video.mp4"`, `display_order:1`, `start_time:"18:00"`, `end_time:"22:00"`, `is_active:true`; column widths `[20,30,25,20,40,40,12,10,10,10]`. Toast `تم تحميل القالب بنجاح`.
  - *Rebuild:* maatwebsite/excel `FromArray` export; add `end_date` and the six ticket-type columns (vip/normal/parking qty & price).

- **F-ADM-SET-035 · Import events from Excel** — Button `استيراد فعاليات` / `جاري الاستيراد...` opens a file input (accept `.xlsx,.xls`). Reads the first sheet; empty → `الملف فارغ` ("File is empty"). Per row, sequential insert into events with English or Arabic headers:
  - `title` = `title` or `اسم الفعالية` (trimmed); `description` = `description` or `وصف الفعالية` or null; `event_date` = `event_date` or `تاريخ الفعالية` or now (Excel serial numbers inserted raw — defect); `location` = `location` or `الموقع` (trimmed); `image_url` / `video_url` from `image_url|رابط الصورة`, `video_url|رابط الفيديو` cleaned (`""`, `"#"`, `"-"`, falsy → null); `display_order` = number of `display_order` or `ترتيب العرض` or 0; `start_time`/`end_time` from `start_time|وقت البداية`, `end_time|وقت النهاية` parsed (`"HH:mm"` strings pass through; numeric fraction of a day → `round(v×1440)` minutes → `HH:mm`); `is_active` from `is_active|نشط` (true/"TRUE"/"true"/1/"1" → true; false/"FALSE"/"false"/0/"0" → false; anything else → **true**).
  - Rows without title or location are skipped silently. Toasts `تم استيراد {n} فعالية بنجاح`, `فشل استيراد {n} فعالية`, none valid → `لم يتم العثور على بيانات صالحة للاستيراد`, exception → `فشل في استيراد الفعاليات`. Input reset afterwards.
  - *Rebuild:* queued `EventsImport` (`ToCollection`, `WithHeadingRow`, `WithValidation`) converting Excel serial dates (`PhpSpreadsheet\Shared\Date::excelToDateTimeObject`) in Asia/Qatar, importing `end_date`, `is_archived=false` and ticket types, one transaction per row, and a downloadable error report listing skipped rows with reasons; activity log.

- **F-ADM-SET-036 · Export events** — Button `تصدير الفعاليات` / `جاري التصدير...` → all events ordered by `created_at` desc → `events_backup_{YYYY-MM-DD}.xlsx`, sheet `Data` (all columns raw). Empty → `لا توجد بيانات للتصدير`; toast `تم تصدير {n} فعالية بنجاح`; error `فشل في تصدير البيانات`.

- **F-ADM-SET-037 · Delete all events** — Destructive button `حذف جميع الفعاليات` → confirmation dialog title `تأكيد حذف جميع الفعاليات`, body `هل أنت متأكد من حذف جميع الفعاليات؟ سيتم أيضاً حذف جميع التذاكر المرتبطة بها. هذا الإجراء لا يمكن التراجع عنه.`, buttons `إلغاء` / `نعم، احذف الكل` (`جاري الحذف...`, red). Action: delete all ticket types, then all events (DB cascades also remove orders of those events). Toasts `تم حذف جميع الفعاليات بنجاح` / `فشل في حذف الفعاليات`. Note under the section: `⚠️ حذف الفعاليات سيحذف أيضاً جميع التذاكر المرتبطة بها`.

- **F-ADM-SET-038 · Export ticket types** — Section `إدارة التذاكر`: button `تصدير التذاكر` → all ticket types with their event title and date flattened into `event_title`, `event_date` columns (nested object removed), ordered by `created_at` desc → `tickets_backup_{date}.xlsx`; toast `تم تصدير {n} تذكرة بنجاح`.

- **F-ADM-SET-039 · Delete all ticket types** — Button `حذف جميع التذاكر` → dialog `تأكيد حذف جميع التذاكر` / `هل أنت متأكد من حذف جميع التذاكر؟ هذا الإجراء لا يمكن التراجع عنه.` → delete all ticket types; toasts `تم حذف جميع التذاكر بنجاح` / `فشل في حذف التذاكر`.

- **F-ADM-SET-040 · Export customers** — Section `إدارة العملاء`: button `تصدير العملاء` → all customers ordered by `created_at` desc → `customers_backup_{date}.xlsx`; toast `تم تصدير {n} عميل بنجاح`.

- **F-ADM-SET-041 · Delete all customers** — Button `حذف جميع العملاء` → dialog `تأكيد حذف جميع العملاء` / `هل أنت متأكد من حذف جميع العملاء؟ سيتم أيضاً حذف جميع الطلبات وحاملي التذاكر. هذا الإجراء لا يمكن التراجع عنه.` → delete all ticket holders, then all orders, then all customers (three separate client calls). Toasts `تم حذف جميع العملاء بنجاح` / `فشل في حذف العملاء`. Note `⚠️ حذف العملاء سيحذف أيضاً جميع الطلبات وحاملي التذاكر المرتبطين بهم`.
  - *Rebuild (all bulk deletes):* super_admin only; typed confirmation (type `احذف الكل`); automatic spatie/laravel-backup snapshot first; queued job in one DB transaction; soft deletes for events/orders/customers (financial records RESTRICT); activity log with counts. Exports become queued jobs with a download notification (Filament `ExportAction`).

- **F-ADM-SET-042 · Public settings exposure** — Anonymous visitors read only the public view: `id, logo_url, header_bg_color, header_bg_image_url, hero_image_url, hero_text, before_footer_image_url, copyright_text, admin_phone, current_event_id, show_delete_customer_button, show_generate_qr_button, show_delete_event_button, clarity_project_id, clarity_enabled`. Must never expose `sadad_secret`, `sadad_api_key`, `sadad_merchant_id`, `webhook_url`, `site_url` (test-pinned).
  - *Rebuild:* a `PublicSettingsResource` (or view-composer DTO) with an explicit allow-list; a Pest test asserts the secret keys are absent. Drop the three admin button flags from the public payload (they only matter in the admin).

**Upgrades (new, not in original) — ADM-SET**
- **U-ADM-SET-01 (NEW)** · "Send test" buttons for both n8n webhooks and a "send test e-mail" for `admin_email`.
- **U-ADM-SET-02 (NEW)** · Remove-image actions and image history for the four branding images.
- **U-ADM-SET-03 (NEW)** · Settings change history (who changed which key, when) from the activity log.
- **U-ADM-SET-04 (NEW)** · Configurable business constants currently hard-coded elsewhere: event-day cut-off hour (18:00), auto-invoice excluded dates, POS receipt ticket prices.

**Original defects to avoid:**
- Sadad secret and API key are loaded into the browser and shown in inputs; secrets stored in plain text in the settings row.
- WhatsApp webhook Save is the branding Save: it requires a logo URL and overwrites branding fields with whatever is in the form.
- No URL validation (webhooks, logo, site URL), no hex validation for header colour; `parseInt || default` silently replaces 0 with defaults.
- Uploaded files are never removed; no way to clear hero/before-footer/header images.
- Bulk deletes run from the browser, non-transactional (partial failure leaves orphans), unaudited, with only a click-through confirmation.
- Excel import does not convert Excel serial dates, ignores `end_date`/ticket types, inserts row by row, silently skips invalid rows, no audit.
- Some singleton reads omit `limit(1)` and would fail if a second row existed.

---
### ADM-EVT — Events & seasons

Admin screen `/admin/dashboard/events` (sidebar `الفعاليات`) to manage events ("seasons"): current vs archived lists grouped into month-range folders, per-type capacity bars, edit dialog with ticket types, archive/restore, optional delete, active toggle, the customer-facing "current event", and starting a new season. Also the "moved" badge shown on orders. Rebuild: Filament `EventResource` with a custom list page (cards grid + folders), `EditEvent` modal/page with a ticket-types repeater, header actions `StartNewSeason` and `CurrentEventSelector` widget.

Data model (original): `events` = `id, title, description, event_date` (timestamptz start), `end_date` (timestamptz, null), `location, image_url, video_url, display_order` (int), `start_time`/`end_time` (text `HH:mm`), `is_active` (bool null), `is_archived` (bool NOT NULL), `archived_at, created_at, updated_at`. `tickets` = ticket TYPE rows per event: `id, event_id, type` (`vip|normal|parking`), `price` (numeric), `available_quantity` (int, = max capacity), `sold_quantity` (int, seats held by pending + confirmed orders), `description, created_at`.

- **F-ADM-EVT-001 · Page header** — H2 `إدارة الفعاليات` ("Event management"); date-filter button (F-ADM-EVT-004); segmented toggle `الحالية` ("Current") / `الأرشيف` ("Archive", Archive icon) (F-ADM-EVT-003); `بدء فعالية جديدة` button (F-ADM-EVT-020). Below the header: current-event selector card (F-ADM-EVT-022, re-mounted whenever the number of events changes), then the folders.

- **F-ADM-EVT-002 · Data loading and sold counts** — On mount and on every view change: read `show_delete_event_button` (default false); events where `is_archived = (view == archived)`, ordered by `display_order` asc then `event_date` asc. For each event (N+1, parallel): its ticket types (`type, available_quantity`) and the count of `ticket_holders` whose order belongs to the event and has `payment_status='confirmed'`, grouped by holder `ticket_type` → `tickets_sold = [{type, count, max: available_quantity}]`. "Sold" here = confirmed holders, not `tickets.sold_quantity`. Load error → toast `فشل التحميل` ("Failed to load").
  - *Rebuild:* one query with `withCount`/`GROUP BY event_id, ticket_type` over confirmed holders (no 1000-row cap); expose both "confirmed" and "held (pending+confirmed)" figures explicitly.

- **F-ADM-EVT-003 · Current / archive views** — Toggle switches between non-archived and archived events and reloads. Folders open by default in Current, closed in Archive. After a new season is created the view switches to Current.

- **F-ADM-EVT-004 · Filter by single date** — Popover calendar button showing CalendarIcon + the chosen date (`PPP` format) or `تصفية حسب التاريخ` ("Filter by date"); an X ghost icon clears it. Keeps events whose `event_date` local Y/M/D equals the picked date. Empty with a filter → `لا توجد فعاليات في هذا التاريخ`.
  - *Rebuild:* filter in Asia/Qatar, and match multi-day events where the date lies between `event_date` and `end_date`.

- **F-ADM-EVT-005 · Season folders (month-range grouping)** — Month index = `year×12 + month` of `event_date`. Sort ascending, merge consecutive month indexes into runs; each run is a folder. Label: single month → Arabic month + year (`ar-u-nu-latn`, e.g. `يناير 2026`); multi-month → `{first month name} - {last month name + year}` (e.g. `يناير - فبراير 2026`), first/last from min/max `event_date`. Each folder is collapsible: Folder icon, bold label, badge `{n} فعالية` ("{n} events"), rotating chevron.

- **F-ADM-EVT-006 · Event card — header** — Grid 1/2/3 columns (base/md/lg), gap 24 px. Optional image (`h-48 object-cover`) when `image_url`. Outline badge `#{display_order || 0}`, title (xl bold), status badge `نشط` ("Active", default) / `غير نشط` ("Inactive", secondary).

- **F-ADM-EVT-007 · Event card — details** — Description clamped to 2 lines. Date line: `event_date` formatted `en-US {year numeric, month long, day numeric}` plus ` — {end_date}` when `end_date` is on a different calendar day. Location line with MapPin icon.
  - *Rebuild:* Arabic date format with Latin digits in Asia/Qatar for consistency with the rest of the admin.

- **F-ADM-EVT-008 · Capacity bars per ticket type** — Shown when the event has ticket types. Title `سعة التذاكر:` ("Ticket capacity:"). Per type: name (raw type capitalised `Vip|Normal|Parking`), text `{count} مباع / {max} كحد أقصى` ("{count} sold / {max} max"), percentage badge and bar. `pct = max > 0 ? count/max×100 : 0`, shown `toFixed(0)`. Thresholds: **> 80 %** destructive badge + red bar; **> 50 % and ≤ 80 %** default badge + primary bar; **≤ 50 %** secondary badge + green bar. Bar width `min(pct, 100)%`.
  - *Rebuild:* show Arabic type labels (`VIP`, `عادي`, `مواقف`).

- **F-ADM-EVT-009 · Capacity total row** — `الإجمالي:` ("Total:") `{Σcount} مباع / {Σmax} إجمالي` + badge `Σcount/Σmax×100 %`. Defect: shows `NaN%` when Σmax = 0.

- **F-ADM-EVT-010 · Empty and loading states** — First load: centred `جاري التحميل...` (loading is not shown again on view switch). Empty: card `لا توجد فعاليات` ("No events") or, with a date filter, `لا توجد فعاليات في هذا التاريخ` ("No events on this date").

- **F-ADM-EVT-011 · Edit dialog — open and load** — Card button `تعديل` ("Edit", outline, Edit icon) opens the dialog (`max-w-2xl`, max height 90vh, scrollable), title `تعديل الفعالية` ("Edit event"). On open it loads the event's ticket types and pre-fills VIP/normal/parking quantity (`available_quantity`) and price, plus title, description, location, image URL, display order (string, default "0"), start/end time, and the event date. It does not load or edit `end_date`, `video_url`, `is_active`.

- **F-ADM-EVT-012 · Edit dialog — fields** — `عنوان الفعالية` (input, required); `الوصف` (textarea 3 rows, HTML-required); `تاريخ الفعالية` (popover calendar, button shows `dd/MM/yyyy` or `اختر التاريخ`); `الموقع` (required); `وقت البداية` / `وقت النهاية` (`type=time`, optional); `رابط الصورة` (placeholder `https://example.com/image.jpg`); `ترتيب العرض (الأقل يظهر أولاً)` ("Display order (lowest first)", number, placeholder 0, help `استخدم الأرقام لترتيب الفعاليات (0، 1، 2، الخ...)`). Section `أنواع التذاكر والأسعار` ("Ticket types and prices"): `الحد الأقصى لتذاكر VIP` (min 0, placeholder 100) / `سعر VIP (ريال)` (min 0, step 0.01, placeholder 200); `الحد الأقصى لتذاكر عادية` (placeholder 500) / `سعر عادية (ريال)` (placeholder 150); `الحد الأقصى لتذاكر مواقف` (placeholder 200) / `سعر مواقف (ريال)` (placeholder 1). Buttons `إلغاء` (outline) / `حفظ` (`جاري التحميل...` while saving).
  - *Rebuild:* add `end_date`, `video_url`, `is_active` fields; date-time pickers in Asia/Qatar; prices as decimal(10,2) in QAR.

- **F-ADM-EVT-013 · Edit dialog — validation** — In order: (1) trimmed title and location non-empty else `يرجى إدخال اسم الفعالية والموقع`; (2) when both times are set, `end_time ≤ start_time` (string compare) → `وقت النهاية يجب أن يكون بعد وقت البداية`; (3) for each type with quantity or price filled, quantity must be an integer ≥ 0 and price a finite number ≥ 0 else `قيم غير صحيحة لتذاكر {VIP|عادي|مواقف} — يرجى إدخال عدد وسعر صالحين`.

- **F-ADM-EVT-014 · Edit dialog — save event** — Update `title, description, event_date, location, image_url || null, display_order (parseInt || 0), start_time || null, end_time || null`. `event_date` is the picked day at local midnight converted to UTC (defect: wipes the original time of day). Then ticket types (F-ADM-EVT-015). Success: toast `تم الحفظ بنجاح`, close, reload. Error: if the message contains Arabic letters show it, else `تعذّر حفظ التعديلات، يرجى المحاولة مرة أخرى`. Non-transactional.
  - *Rebuild:* single DB transaction for event + ticket types; preserve time of day; activity log `event.updated` with diff.

- **F-ADM-EVT-015 · Ticket type add / update / remove with sold guard** — After the event update, re-read the event's ticket types; for each of vip/normal/parking:
  - quantity AND price filled: existing type → if `quantity < sold_quantity` reject with `لا يمكن خفض عدد تذاكر {type} إلى {q} — تم بيع {sold} تذكرة بالفعل` ("Cannot reduce {type} to {q} — {sold} already sold"), else update `available_quantity, price`; missing type → insert `{event_id, type, available_quantity, price, sold_quantity:0}`.
  - both empty and the type exists: if `sold_quantity > 0` → error `لا يمكن حذف تذاكر {type} لأنه تم بيع {sold} تذكرة`; else delete the type.
  - only one of quantity/price filled → silently ignored.
  - *Rebuild:* guard against held seats (pending+confirmed) under `lockForUpdate`; DB CHECK `sold_quantity <= available_quantity`; when capacity increases broadcast `TicketCapacityIncreased` (F-ADM-CAP-006).

- **F-ADM-EVT-016 · Delete event (gated)** — Trash button (destructive outline) visible only when `show_delete_event_button` is true. Confirmation `حذف الفعالية` / `هل أنت متأكد من حذف "{title}"؟ لا يمكن التراجع عن هذا الإجراء.` / `إلغاء` / `حذف`. Hard delete; toast `تم الحذف بنجاح`; reload. No check for existing orders (DB cascades remove ticket types and orders).
  - *Rebuild:* policy `delete` allowed only when the event has no orders (else offer archive); soft delete; activity log.

- **F-ADM-EVT-017 · Archive event** — Icon button (title `أرشفة` "Archive") → `is_archived=true, archived_at=now, is_active=false`; toast `تمت أرشفة الفعالية (البيانات محفوظة)` ("Event archived (data kept)"). Archived events are hidden from all public pages and from the current-event selector.

- **F-ADM-EVT-018 · Restore event** — In the archive view the same icon button (title `استعادة` "Restore") → `is_archived=false, archived_at=null`; stays inactive. Toast `تمت استعادة الفعالية`.

- **F-ADM-EVT-019 · Toggle active** — Row with label `نشط` / `غير نشط` and a switch → `is_active = !current`; toast `تم الحفظ بنجاح`. Public listing shows active events only; web orders on inactive events are rejected (`event_not_available`).
  - *Rebuild:* activity log for archive/restore/active toggles (none in original).

- **F-ADM-EVT-020 · Start new season — dialog** — Trigger: gradient button (Sparkles) `بدء فعالية جديدة` ("Start a new event"). Dialog (`max-w-2xl`, RTL) title `بدء فعالية جديدة`, description `تُنشئ فعالية واحدة تمتد من يوم البداية إلى يوم النهاية. تبقى بيانات الفعاليات السابقة محفوظة بالكامل (العملاء والحجوزات) ويمكن الاطلاع عليها في الأرشيف.` ("Creates one event spanning from start day to end day. Previous events' data (customers, bookings) is kept and visible in the archive."). Fields: `اسم الفعالية` (required); `الوصف` (textarea 3 rows, optional); `يوم البداية` / `يوم النهاية` (`type=date`, required); `وقت البداية` / `وقت النهاية` (`type=time`); `المكان` (required); `رابط الصورة` (placeholder `https://...`); section `أنواع التذاكر والأسعار`: `عدد تذاكر VIP`, `سعر VIP (ريال)`, `عدد التذاكر العادية`, `سعر العادية (ريال)`, `عدد تذاكر المواقف`, `سعر المواقف (ريال)` (numbers min 0, prices step 0.01); switch `أرشفة الفعاليات السابقة` ("Archive previous events", help `إخفاؤها من الواجهات مع الاحتفاظ بكل البيانات`, default ON); submit full-width `بدء الفعالية` / `جارٍ البدء...`. Closing is blocked while saving; closing resets the form and the archive switch to ON.

- **F-ADM-EVT-021 · Start new season — validation and creation** — Validation in order: title & location → `يرجى إدخال اسم الفعالية والموقع`; both dates valid → else `يرجى اختيار تاريخ بداية وتاريخ نهاية صالحين`; end day < start day → `تاريخ النهاية يجب أن يكون بعد تاريخ البداية` (same day allowed); both times set and `end_time ≤ start_time` → `وقت النهاية يجب أن يكون بعد وقت البداية`; all 6 quantity/price values required → `يرجى إدخال عدد وسعر جميع أنواع التذاكر`; integer ≥ 0 quantity and finite ≥ 0 price per type → `قيم غير صحيحة لتذاكر {VIP|عادي|مواقف} — يرجى إدخال عدد وسعر صالحين`. Creation:
  1. If archive switch ON: every non-archived event → `is_archived=true, is_active=false, archived_at=now`.
  2. `event_date = {start_day}T{start_time || 00:00}`, `end_date = {end_day}T{end_time || 23:59}` (browser time zone in the original).
  3. Insert the event `{title, description, event_date, end_date, location, image_url || null, start_time || null, end_time || null, display_order:0, is_active:true, is_archived:false}`.
  4. Insert three ticket types vip/normal/parking with price, `available_quantity`, `sold_quantity:0`.
  5. Set `settings.current_event_id` to the new event (errors ignored).
  6. Activity log `{activity_type:"new_event_started", user_type:"admin", action_data:{event_id, title, start_day, end_day, archived_previous}}`.
  7. Toast `تم بدء الفعالية الجديدة بنجاح`; reset; close; switch to Current view and reload. Error → `تعذر بدء الفعالية الجديدة`.
  - *Rebuild:* `StartNewSeason` action in one transaction (create first, then archive others, then set current), dates built in Asia/Qatar and stored UTC; logs the acting admin.

- **F-ADM-EVT-022 · Current event selector** — Card (`border-primary/30 bg-primary/5`) with Star icon, label `الفعالية الحالية` ("Current event"), help `الفعالية التي يراها العملاء ويمكنهم الحجز فيها. الفعاليات المؤرشفة مخفية دائماً.` ("The event customers see and can book. Archived events are always hidden."). Select (placeholder `اختر الفعالية`, disabled while loading/saving) with options `كل الفعاليات النشطة` ("All active events", sentinel → NULL) and every non-archived event as `{title} — {event_date en-US date}` (ordered by `event_date`). Changing saves `settings.current_event_id` immediately (spinner while saving). Toasts: `سيظهر للعملاء جميع الفعاليات النشطة` ("Customers will see all active events") / `تم تحديد الفعالية الحالية للعملاء` ("Current event set for customers"); errors `لا توجد إعدادات محفوظة` (no settings row), `تعذر حفظ الفعالية الحالية`. FK `ON DELETE SET NULL`. Consumers: public home and ticket selection, order creation, gate board default event.
  - *Rebuild:* revert the optimistic selection on error (original does not); store in `EventSettings::$current_event_id`; validate the event is not archived.

- **F-ADM-EVT-023 · Clear current event** — Ghost button `إلغاء التحديد` ("Clear selection"), shown when an event is selected, saves the "all active events" sentinel (NULL).

- **F-ADM-EVT-024 · "Moved" badge on orders** — In each Orders row, an amber chip `تم النقل` ("Moved", ArrowRightLeft icon, title `تم تغيير تاريخ الفعالية` "Event date changed") rendered only when the order has at least one `expired_qr_codes` row with reason `event_date_changed`. Click (does not propagate to the row) opens the history dialog. Server contract: moving an order to another event stores old QR codes in `expired_qr_codes` (reason default `event_date_changed`) and logs `event_change` with `{order_id, booking_reference, old_event_id, old_event_title, old_event_date, new_event_id, new_event_title, new_event_date, expired_qr_codes}` (move action itself specified in section 2.3 Orders).
  - *Rebuild:* `orders.event_moved_count` (or `exists` subquery eager-loaded) instead of one query per row.

- **F-ADM-EVT-025 · Event change history dialog** — Dialog `max-w-md`, title `سجل تغيير الفعالية` ("Event change history", amber icon). Source: activity logs of type `event_change` whose `action_data.order_id` = this order, oldest first (original fetches all and filters client-side). Fallback when none: `expired_qr_codes` rows of the order grouped by timestamp truncated to the second, titles unknown. Each entry card: badge `تغيير #{i+1}`, timestamp `dd/MM/yyyy HH:mm` (Arabic locale), box `من` ("From", red) and box `إلى` ("To", green) each showing the date `dd/MM/yyyy` (or the title if no date) and the title underneath; unknown title → `غير معروف`. States: spinner; empty `لا توجد سجلات` ("No records"); list max height 400 px scroll.
  - *Rebuild:* dedicated `order_event_changes` table (order_id, from/to event ids, titles, dates, actor, expired code count) written in the move transaction.

**Upgrades (new, not in original) — ADM-EVT**
- **U-ADM-EVT-01 (NEW)** · Edit `end_date`, `video_url`, `is_active` in the edit form; duplicate-event action.
- **U-ADM-EVT-02 (NEW)** · Per-event KPIs on the card (confirmed revenue, scanned count) from aggregated queries.
- **U-ADM-EVT-03 (NEW)** · Show both confirmed and held (pending) counts in capacity bars.

**Original defects to avoid:**
- N+1 queries per event and PostgREST 1000-row truncation of holder counts.
- Two sources of truth: cards show confirmed holder counts, the edit guard uses `sold_quantity` (pending + confirmed).
- Edit resets the event time to local midnight; cannot edit `end_date`/`video_url`/`is_active`; non-transactional save; only-one-field-filled ticket rows ignored silently.
- Total percentage `NaN%` when no capacity.
- Hard delete with cascades to orders; no activity log for edit/archive/restore/toggle/delete.
- Start-new-season archives everything before creating (failure leaves all events archived and no current event); browser time zone used for dates.
- Current-event selector optimistic state not reverted on error.
- "Moved" badge does one query per order row and reads `event_change` logs that the CHECK constraint actually rejects (badge falls back to timestamps).

---

### ADM-CAP — Capacity alerts

Live capacity banners shown at the top of every admin page for today's event, and the "new tickets available" pop-up shown on POS and live-bookings screens when an admin increases a ticket type's capacity. Rebuild: Livewire `CapacityAlert` component (Filament render hook) refreshed by Reverb events `TicketHolderChanged` / `OrderStatusChanged` (debounced); `TicketCapacityIncreased` broadcast on private channel `event.{id}.capacity` consumed by POS and live-bookings via Echo.

- **F-ADM-CAP-001 · Global capacity alert — event resolution** — Rendered above every `/admin/dashboard/*` page (F-ADM-SHL-014); optional `eventId` prop. Without a prop: first **active** event whose `event_date` falls within today's day bounds (browser-local bounds in the original). Then loads the event date (for the cut-off rule), the event's ticket types (`type, available_quantity`) and confirmed holder counts per type (holders of confirmed orders of that event).
  - *Rebuild:* "today's event" = active, non-archived event whose Qatar date range `[event_date, end_date]` contains today (multi-day seasons supported); falls back to `current_event_id`.

- **F-ADM-CAP-002 · Levels and thresholds** — Per ticket type `percentage = round(confirmed / available × 100)` (0 when available = 0). Levels: **critical ≥ 100 %**, **warning 90–99 %**, **nearing 80–89 %**, **normal < 80 %**. Type labels: `vip` → `VIP`, `normal` → `عادي`, `parking` → `موقف`.

- **F-ADM-CAP-003 · Banner rendering** — Container `flex flex-wrap gap-3 mb-4`, RTL; nothing while loading or when no capacities. Up to four alerts, one per level that has at least one type:
  - Critical: red (`border-red-500 bg-red-50`), XCircle icon, title `تجاوز السعة!` ("Capacity exceeded!"), destructive badges `{label}: {confirmed}/{available} ({pct}%)`.
  - Warning: orange, AlertTriangle, title `تحذير - السعة تقارب الامتلاء (90%+)` ("Warning — capacity nearly full (90%+)"), badges `{label}: {confirmed}/{available} ({pct}%) - متبقي {available − confirmed}` ("remaining").
  - Nearing: yellow, TrendingUp, title `السعة تقترب (80%+)` ("Capacity approaching (80%+)"), yellow badges + remaining.
  - Normal: green, CheckCircle, title `السعة متاحة` ("Capacity available"), green badges + remaining.

- **F-ADM-CAP-004 · 18:00 hide rule** — Alerts are hidden once the event day is over: hidden if the current Qatar date is after the event date, or it is the event date and the Qatar hour is **≥ 18** (6 PM). Hard-coded; ignores `end_date`.
  - *Rebuild:* cut-off hour configurable (default 18) and applied to the last day of a multi-day event.

- **F-ADM-CAP-005 · Realtime refresh** — Subscribes to all changes on `ticket_holders` and UPDATE on `orders` (channel `capacity-alerts`); any change anywhere re-runs the four queries.
  - *Rebuild:* broadcast only for the relevant event; debounce 2 s; cached aggregate.

- **F-ADM-CAP-006 · Capacity-increase detection (POS & live bookings)** — For the screen's current event, keep a baseline map `type → available_quantity` (loaded on event change; cleared when no event). Subscribe to UPDATE on `tickets` filtered by `event_id` (channel `capacity-notification-{eventId}`). When the new `available_quantity` > baseline (missing baseline = 0): raise a notification `{ticketType, oldCapacity, newCapacity, increase}`, show the banner (F-ADM-CAP-007), play the sound (F-ADM-CAP-008) and show the toast (F-ADM-CAP-009). Always update the baseline with the new value (decreases tracked silently). Used on `/admin/pos`; `/live-bookings` has an equivalent implementation.

- **F-ADM-CAP-007 · Capacity-increase banner** — Fixed centred overlay (z-50, width 90 %, max `xl`), slide-in. Gradient by type: vip `amber-500 → orange-500`, normal `emerald-500 → green-500`, parking `blue-500 → cyan-500` (unknown → normal). Animated blurred sparkles and a bouncing Sparkles icon; title (Ticket icon) `تذاكر جديدة متاحة للبيع!` ("New tickets available for sale!"); text `تم إضافة {increase} تذكرة {label} • الإجمالي: {newCapacity}` with labels `VIP` / `عادي` / `مواقف`. Dismiss X button. Bottom progress bar shrink animation over 4 s (visual only).

- **F-ADM-CAP-008 · Alert sound** — Web Audio two-tone chime: 880 Hz for 0.22 s, then 1320 Hz for 0.30 s starting at +0.18 s.

- **F-ADM-CAP-009 · Toast** — Title `🎉 تذاكر جديدة متاحة!` ("New tickets available!"), description `تم إضافة {increase} تذكرة {VIP|عادي|مواقف} ({new} إجمالي)`, duration **8000 ms**.

- **F-ADM-CAP-010 · Auto-hide** — Banner hides automatically after **10 000 ms** or on dismiss.
  - *Rebuild:* progress bar duration = auto-hide duration (10 s).

**Original defects to avoid:**
- "Today's event" uses browser-local day bounds and `event_date` only → multi-day seasons never alert after their first day.
- 18:00 cut-off hard-coded.
- Every holder change anywhere triggers four queries for every open admin tab; holder counts truncated at 1000 rows.
- Banner progress bar (4 s) does not match auto-dismiss (10 s).

---
### ADM-NTF — Notifications

Live feed of incoming bookings at `/admin/dashboard/notifications` (sidebar `الإشعارات`), split into POS cash sales and online Sadad payments, with Qatar-day filters, unread tracking, a details dialog and the invoice pop-up. Also hosts the shared Qatar-date helpers and (as a shared component) the e-mail delivery timeline used by the invoices screen. Admin-only. Rebuild: custom Filament page `NotificationsFeed` (Livewire), live via Reverb channel `admin.orders`.

- **F-ADM-NTF-001 · Header card** — Gradient card: BellRing icon with a red counter = number of loaded rows with `created_at > lastSeen`; H2 `الإشعارات`; subtitle `الحجوزات الواردة لحظياً — نقاط البيع والدفع الإلكتروني` ("Incoming bookings in real time — POS and online payment"). Buttons `تحديث` ("Refresh", RefreshCw, outline) and `تعليم الكل كمقروء` ("Mark all as read", CheckCheck; disabled when unread = 0).

- **F-ADM-NTF-002 · Data query** — Orders with `id, created_at, booking_reference, payment_method, payment_status, total_amount, quantity, ticket_type`, customer `name, phone`, event `title, event_date`; optional Qatar-day range on `created_at`; newest first; **limit 200** (no pagination). Refetched on filter change. Error toast `تعذر تحميل الإشعارات` ("Could not load notifications").
  - *Rebuild:* cursor pagination / infinite scroll; never silently truncate (show "showing latest 200 of N").

- **F-ADM-NTF-003 · Date filter** — Label `التاريخ` ("Date", CalendarDays). Segmented buttons: `اليوم` ("Today", default), `أمس` ("Yesterday"), `يوم محدد` ("Specific day"), `الكل` ("All"). `يوم محدد` reveals a date input (LTR, default today in Qatar, width 170 px). When a day is active an outline badge (Clock) shows it as long weekday, day, month, year (`ar-u-nu-latn`). Day bounds = `[{day}T00:00:00+03:00, +24 h)`.

- **F-ADM-NTF-004 · Two columns: POS vs online** — Grid of two columns from `xl` up: (1) `نقاط البيع` / `المبيعات النقدية من نقاط البيع` ("Points of sale" / "Cash sales from POS", Store icon amber, `bg-amber-500/5`) = rows with `payment_method == "cash_pos"`; (2) `دفع إلكتروني` / `الحجوزات عبر بوابة سداد` ("Online payment" / "Bookings via Sadad gateway", CreditCard primary, `bg-primary/5`) = all other rows.

- **F-ADM-NTF-005 · Column counts and totals** — Each column header shows the row count (`ar-u-nu-latn`) and `Σ total_amount` formatted `{n} ر.ق` — summing **all** statuses including pending and cancelled.
  - *Rebuild:* show confirmed total prominently and pending/cancelled separately.

- **F-ADM-NTF-006 · Column body and notification card** — Body max height 70vh, scrollable. Loading: 4 skeletons (h-32). Empty: Bell icon + `لا توجد إشعارات` ("No notifications"). Cards in a 1/2-column grid: right accent bar (amber POS / primary online), icon tile (Store/CreditCard), pulsing red `جديد` ("New") badge when unread, customer name or `عميل غير معروف` ("Unknown customer"), status badge, ticket count (Ticket icon + quantity), amount (bold primary) and Qatar time (`ar-u-nu-latn`, day 2-digit, short month, hour, minute), button `عرض الفاتورة` ("View invoice", Hash icon). Status badges: `confirmed` → `مدفوع / مؤكد` ("Paid / confirmed", emerald); `pending` or null → `قيد الانتظار` ("Pending", amber); `cancelled` → `ملغي` ("Cancelled", destructive). Card click / Enter / Space opens the details dialog (F-ADM-NTF-011).

- **F-ADM-NTF-007 · Unread marking** — A row is unread when `created_at > lastSeen` (localStorage `admin:notifications:lastSeenAt`, default epoch). Unread cards get `bg-primary/[0.04]` and a primary ring plus the `جديد` badge. Visiting the page does not mark anything read (the sidebar badge is merely hidden on this page).

- **F-ADM-NTF-008 · Mark all as read** — Sets `lastSeen = now` (ISO) in localStorage, updates state, dispatches window event `admin:notifications:seen` so the sidebar badge recomputes (F-ADM-SHL-011).
  - *Rebuild:* `users.last_seen_notifications_at` updated server-side; broadcast to the admin's other tabs.

- **F-ADM-NTF-009 · Realtime insert** — Channel `admin-notifications-live`, INSERT on `orders`: refetch that order with relations; if a day filter is active and the row is outside it → ignore; else prepend (deduplicated by id) and mark "just arrived" for **1200 ms** (slide-in animation).

- **F-ADM-NTF-010 · Realtime update** — UPDATE on `orders`: refetch the row and replace it in the list and in the open details dialog (e.g. pending → confirmed).

- **F-ADM-NTF-011 · Details dialog** — `max-w-lg`. Title with icon: `حجز نقاط بيع` ("POS booking") or `دفع إلكتروني (سداد)` ("Online payment (Sadad)"). Status badge; booking reference badge (LTR, Hash). Info grid: name (`عميل غير معروف` fallback), phone (LTR, `—`), `{quantity} تذكرة — {ticket_type}` (raw type, untranslated), amount, event title (`—`), full Qatar date-time (long weekday, day, long month, year, hour, minute). Buttons: `عرض الفاتورة` → invoice pop-up; icon button (ExternalLink, title `فتح الفاتورة في تبويب جديد` "Open invoice in a new tab") → `/invoice/{booking_reference}` new tab; `إغلاق` ("Close").
  - *Rebuild:* translate ticket type labels; link to the order record in the Orders resource.

- **F-ADM-NTF-012 · Invoice pop-up** — `عرض الفاتورة` on a card or in the details dialog opens the shared invoice dialog for the booking reference (component specified in section 2.3 Invoices).

- **F-ADM-NTF-013 · Qatar date helpers (shared)** — `qatarDateKey(d)` → `YYYY-MM-DD` in Asia/Qatar; `qatarDayRange(key)` → `{start: key T00:00:00+03:00 as UTC ISO, end: start + 24 h}`; `shiftDay(key, n)` → key ± n days (computed from noon +03:00); `money(v)` → `{v ar-u-nu-latn} ر.ق`. Shared with the sidebar badge.
  - *Rebuild:* `App\Support\QatarClock` (CarbonImmutable in Asia/Qatar) with `todayKey()`, `dayRange()`, `shiftDay()`; `Money::format()`.

- **F-ADM-NTF-014 · E-mail delivery timeline (shared component)** — Used inside the invoices screen per order (section 2.3). Source: `email_delivery_events` for the order (`status, attempt, detail, recipient, created_at`), newest first; realtime INSERT filtered by `order_id` reloads. Status map: `queued` `في قائمة الإرسال` (Clock, amber); `retried` `إعادة محاولة` (RotateCcw, blue); `sent` `تم الإرسال` (MailCheck, emerald); `failed` `فشل الإرسال` (MailX, red); `suppressed` `محظور من الاستقبال` (ShieldAlert, orange); `bounced` `ارتد البريد` (MailX, red); `complained` `بلاغ إزعاج` (ShieldAlert, red); `unsubscribed` `إلغاء الاشتراك` (Undo2, muted); other → raw status (Clock, muted). UI: spinner; empty dashed box `لم يتم إرسال أي فاتورة بالبريد لهذا الحجز بعد.` ("No invoice e-mail sent for this booking yet."); vertical list with right border line, coloured dot, status pill, time (`ar-u-nu-latn`, Asia/Qatar, medium date, short time), `• المحاولة {n}` when attempt > 1, detail text, recipient (LTR). Writers: invoice e-mail, payment-failed e-mail, admin sale alert, provider bounce/complaint webhooks.
  - *Rebuild:* Livewire component listening on `order.{id}.emails`; table `email_delivery_events` keeps `template` per event (original mislabels provider events as `booking-invoice`).

**Original defects to avoid:**
- Read state per browser only.
- 200-row cap silently truncates `الكل`.
- Column totals include pending and cancelled amounts.
- Ticket type shown raw (`vip/normal/parking`).

---

### ADM-RPT — Reports

`/admin/dashboard/reports` (sidebar `التقارير`): styled multi-sheet Excel export of all orders, or Sadad-only orders grouped by purchase date. Admin-only (accountant in the rebuild). Rebuild: maatwebsite/excel multi-sheet export (`WithMultipleSheets`, `WithStyles`, `WithColumnWidths`, `WithEvents`) generated by a queued job; Filament notification with a download link when ready.

- **F-ADM-RPT-001 · Reports page** — Card: H3 `تصدير تقرير الطلبات` ("Export orders report"); text `سيتم تصدير جميع بيانات الطلبات في ملف Excel واحد، حيث يمثل كل ورقة عمل يومًا من أيام الفعاليات` ("All order data is exported to one Excel file, each worksheet representing one event day"); box `البيانات المصدرة تشمل:` ("Exported data includes:") listing `• رمز الحجز`, `• بيانات العميل (الاسم، البريد، الهاتف، الجنسية، رقم الهوية)`, `• نوع التذكرة وعدد التذاكر`, `• حالة الدفع وطريقة الدفع`, `• المبلغ الإجمالي`, `• تفاصيل الفعالية (الاسم، التاريخ، الموقع)`, `• عدد الحضور`, `• أسماء حاملي التذاكر`, `• التواريخ (الإنشاء، التأكيد)`, `• معرف الدفع ورمز الاستجابة السريعة`. Buttons (large): primary `تصدير التقرير الكامل` ("Export full report", FileDown) and secondary `تصدير سداد فقط` ("Export Sadad only"); both disabled while either runs; spinner text `جاري التصدير...`.

- **F-ADM-RPT-002 · Data fetch** — Paginated in pages of **1000** rows until a short page: orders with customer `name, email, phone, nationality, id_number, country_code`, event `title, event_date, location`, holders `name, phone, nationality, id_number, ticket_type, is_present, qr_code, country_code`; ordered `created_at` desc; Sadad-only adds `payment_method = 'sadad'`. Empty → toast `لا توجد بيانات للتصدير`.
  - *Rebuild:* chunked query (`lazyById`) server-side; optional filters (NEW): date range, event, status, method.

- **F-ADM-RPT-003 · Row columns (20, in order, with widths)** — 1 `اسم الفعالية` (event title or `-`, 25); 2 `معرف الدفع` (payment id or `-`, 20); 3 `طريقة الدفع` (raw `sadad`/`cash_pos` or `-`, 15); 4 `عدد التذاكر` (breakdown from holders `"{n} {type}"` joined ` + `, e.g. `2 vip + 1 parking`; fallback `"{holders count || quantity || 0} {order.ticket_type}"`, 15); 5 `حالة الدفع` (raw `confirmed/pending/cancelled` or `-`, 15); 6 `المبلغ الإجمالي` (number, default 0, 15); 7 `رمز الحجز` (15); 8 `اسم العميل` (22); 9 `البريد الإلكتروني` (28); 10 `رقم الهاتف` (18); 11 `الجنسية` (15); 12 `رقم الهوية` (18); 13 `نوع التذكرة` (order ticket type, 15); 14 `تاريخ الفعالية` (`dd/MM/yyyy`, browser time zone, 15); 15 `الموقع` (event location, 20); 16 `عدد الحضور` (count of holders `is_present`, 12); 17 `أسماء حاملي التذاكر` (holder names joined `, ` or `-`, 40); 18 `تاريخ التأكيد` (confirmed_at Qatar `dd/MM/yyyy HH:mm` or `-`, 20); 19 `تاريخ الإنشاء` (created_at Qatar `dd/MM/yyyy HH:mm`, 20); 20 `رمز الاستجابة السريعة` (order QR code or `-`, 35). `country_code` is fetched but unused.
  - *Rebuild:* event date in Asia/Qatar; translated status/method labels optional via a "language" option; phone with country code.

- **F-ADM-RPT-004 · Sheet `جميع السجلات` ("All records")** — Full report only: all orders that have an event date (orders without an event are excluded — defect), concatenated by event date ascending then stable-sorted by status rank (confirmed 1, pending 2, cancelled 3, other 4).

- **F-ADM-RPT-005 · One sheet per event day** — Full report only: grouped by the Qatar `yyyy-MM-dd` of the event start date; sheet name `فعالية {dd-MM-yyyy}` truncated to 31 characters; rows status-sorted. Multi-day events are grouped by their start date only.

- **F-ADM-RPT-006 · Sadad summary sheets** — When any Sadad orders exist: sheet `جميع مشتريات سداد` ("All Sadad purchases") — all Sadad orders by creation date ascending, status-sorted — plus one sheet per Qatar creation date named `سداد {dd-MM-yyyy}`.

- **F-ADM-RPT-007 · Sadad-only report** — Skips the all-records and event-day sheets; produces only `جميع مشتريات سداد` and the `سداد {date}` sheets.

- **F-ADM-RPT-008 · Header styling** — Header row bold white 12 pt on `#1E3A5F`, centred, wrapped, thin black borders; header row height 25 pt.

- **F-ADM-RPT-009 · Data styling** — All cells centred with thin `#D0D0D0` borders. Status column (5th): confirmed fill `#D4EDDA`, font `#155724` bold; pending `#FFF3CD` / `#856404`; cancelled `#F8D7DA` / `#721C24`. Amount column (6th): bold, number format `#,##0.00`.

- **F-ADM-RPT-010 · Total row** — Appended after the last data row of each sheet: label `الإجمالي:` in the status column and Σ amount (numeric) in the amount column; style bold white 12 pt on green `#2E7D32` with medium top/bottom borders. Sums all statuses.
  - *Rebuild:* three total rows (confirmed / pending / cancelled) or confirmed-only total clearly labelled.

- **F-ADM-RPT-011 · File names and toasts** — `تقرير-التذاكر-{yyyy-MM-dd-HHmmss}.xlsx` (full) or `تقرير-سداد-{yyyy-MM-dd-HHmmss}.xlsx` (Sadad); toasts `تم تصدير التقرير بنجاح` / `فشل في تصدير التقرير`. Sheets are right-to-left.

**Original defects to avoid:**
- Orders without an event are excluded from every event sheet including `جميع السجلات`.
- Totals mix confirmed, pending and cancelled amounts.
- Every order is loaded into the browser to build the file; no filters.
- Event date column formatted in the browser's time zone.

---

### ADM-LOG — Activity logs

`/admin/dashboard/activity-logs` (sidebar `سجلات النشاط`): audit viewer with type tabs, JSON detail expanders, single and bulk delete, realtime refresh. Admin-only. Rebuild: spatie/laravel-activitylog (`activity_log` table) with a Filament `ActivityResource` (filters by log name/event/causer/date, JSON viewer); all logging server-side.

- **F-ADM-LOG-001 · Page header** — Card header: H2 `سجلات النشاط` ("Activity logs"), subtitle `تتبع جميع الأنشطة في النظام (عمليات البحث وإدخال البيانات)` ("Track all system activity (searches and data entry)"); destructive button `حذف جميع السجلات ({total})` ("Delete all logs"). Loading: centred spinner.

- **F-ADM-LOG-002 · Data** — Latest **500** rows ordered by `created_at` desc. Load error → `فشل في تحميل السجلات`. No pagination, search or date filter.
  - *Rebuild:* paginated table with filters (type, user type, identifier, date range) and full-text search on JSON.

- **F-ADM-LOG-003 · Tabs with counts** — Tabs (3 columns mobile / 5 from `sm`): `الكل ({n})` ("All"); `فواتير ({n})` ("Invoices", Download icon) = invoice downloads; `POS ({n})` = `pos_form` excluding invoice downloads; `بحث ({n})` ("Search") = `qr_search`; `مسح ({n})` ("Scan") = `ticket_scan`. Other types appear only under `الكل`.

- **F-ADM-LOG-004 · Type classification and labels** — Invoice download = `activity_type == 'pos_form'` AND `action_data.action == 'invoice_download'` → `تحميل فاتورة` (Download icon, blue). `pos_form` → `نموذج POS` (FileText); `qr_search` → `بحث QR` (Search); `ticket_scan` → `فحص تذكرة` (ScanLine); any other → raw type (Filter icon).
  - *Rebuild:* Arabic labels also for `login_blocked` (`حظر دخول`), `new_event_started` (`بدء فعالية جديدة`), `event_change` (`نقل حجز لفعالية أخرى`), `ticket_checkin_reset` (`إلغاء تسجيل حضور`), plus settings/staff/event changes; tabs for each log group.

- **F-ADM-LOG-005 · User type badges** — `admin` → `مدير` (default badge); `guest` → `ضيف` (secondary); `system` → `نظام` (outline); other → raw (outline).

- **F-ADM-LOG-006 · Table columns** — Min width 900 px, horizontal scroll: `النوع` ("Type", icon + label), `المستخدم` ("User", badge), `المعرف` ("Identifier", `user_identifier` or `-`), `البيانات` ("Data", JSON expander), `التاريخ والوقت` ("Date & time", `dd/MM/yyyy HH:mm:ss`, LTR, browser time zone), `إجراءات` ("Actions", trash ghost button / spinner). Empty → `لا توجد سجلات` ("No logs").

- **F-ADM-LOG-007 · JSON detail expander** — `<details>` with summary `عرض البيانات` ("Show data") revealing pretty-printed `action_data`.

- **F-ADM-LOG-008 · Delete one log** — Trash button → delete by id; toasts `تم حذف السجل بنجاح` / `فشل في حذف السجل`.

- **F-ADM-LOG-009 · Delete all logs** — Native confirm `هل أنت متأكد من حذف جميع السجلات؟ لا يمكن التراجع عن هذا الإجراء.` → delete every row; toasts `تم حذف جميع السجلات بنجاح` / `فشل في حذف السجلات`.
  - *Rebuild:* audit logs are append-only for admins; only super_admin may purge, by age (e.g. older than N days), and the purge itself is logged. Default retention via `activitylog:clean` (365 days, configurable).

- **F-ADM-LOG-010 · Realtime refresh** — Channel `activity_logs_changes` (all events) → full refetch.
  - *Rebuild:* Reverb event `ActivityLogged` prepends the new row (no full refetch).

- **F-ADM-LOG-011 · What gets logged, by whom (original)** — Table `activity_logs`: `id, created_at, activity_type, user_type, user_identifier, action_data (jsonb), metadata (jsonb: page URL, browser), ip_address, user_agent`; indexes on `created_at desc`, `activity_type`, `user_identifier`. Writers:
  - `pos_form` — POS screen on order submission (user_type `guest` default); `action_data.action = "invoice_download"` when a POS invoice is downloaded.
  - `qr_search` — QR scanner searches (manual/phone lookups).
  - `ticket_scan` — QR scanner scan results.
  - `login_blocked` — lockout service (F-AUTH-017), user_type `staff` (passcode) or `admin` (account).
  - `new_event_started` — start-new-season dialog (F-ADM-EVT-021), user_type `admin`.
  - `event_change` — move order to another event (section 2.3), user_type `admin`, no identifier.
  - `ticket_checkin_reset` — admin reset of a check-in (staff-ops section).
  - Constraints in the DB allow only `pos_form|qr_search|ticket_scan` and user types `admin|guest|system`, so the last four types (and `staff`) are silently rejected. Anyone (anonymous) can insert; select/delete are allowed only for rows in `admin_users`.
  - *Rebuild:* server-side logging only, causer = authenticated user or passcode session label; log names `auth`, `pos`, `scanner`, `checkin`, `events`, `orders`, `settings`, `staff`; additionally log settings saves, staff-account actions, bulk deletes, event edits/archive/delete, POS user changes, attendance edits, manual check-ins.

- **F-ADM-LOG-012 · Client logging helper (original)** — `logActivity({activityType: 'pos_form'|'qr_search'|'ticket_scan', userType = 'guest', userIdentifier?, actionData, metadata = {}})` inserts a row with `metadata` extended by `url` (current page) and `browser` (user agent) plus `user_agent`; errors only logged to console. Used by the POS and the QR scanner.
  - *Rebuild:* replaced by server-side `activity()->...` calls inside the POS/scanner actions; no client-writable log endpoint.

**Original defects to avoid:**
- CHECK constraints reject most event types in use → audit trail for lockouts, new seasons, event moves and check-in resets is lost.
- Anonymous INSERT allowed → forgeable/spammable logs.
- RLS keyed on `admin_users` only (role-based admins cannot read).
- Admins can delete all logs with one native confirm.
- 500-row cap, no filters, timestamps in browser time zone.

---
### ADM-VIS — Visitor analytics & visitors vs scans

Admin-side analytics: the active-visitors dashboard at `/admin/dashboard/visitors` (sidebar `الزوار النشطون`), the visitors-vs-gate-scans comparison at `/admin/dashboard/visitors-vs-scans` (sidebar `الزوار مقابل الحضور`), and the public-site tracking pipeline that feeds both. The full-screen live visitors board `/live-visitors` is specified in the staff-ops section (it reads the same data). Rebuild: server-side tracking middleware/Livewire beacon on the public layout writing `active_visitors` (Redis or table) and `page_views`; Filament pages with stats widgets and a Chart.js line chart; scheduled prune.

- **F-ADM-VIS-001 · Active visitors page header** — H2 `الزوار النشطون` ("Active visitors"), subtitle `مراقبة الزوار في الوقت الفعلي` ("Real-time visitor monitoring"). Buttons: `تحديث` ("Refresh", RefreshCw spinning while loading) and destructive `مسح الكل` ("Clear all", Trash2) → native confirm `هل أنت متأكد من حذف جميع بيانات الزوار؟` → delete all `active_visitors` rows → reload (no toast).
  - *Rebuild:* clear-all limited to super_admin, logged.

- **F-ADM-VIS-002 · Data load and stale cleanup** — Calls `cleanup_stale_visitors()` first (deletes `active_visitors` with `last_seen_at < now − 5 min`; in the final DB its execute right is revoked from signed-in users, so the call fails silently and stale rows accumulate), then reads all `active_visitors` ordered by `last_seen_at` desc. Stats computed client-side.
  - *Rebuild:* scheduled `PruneStaleVisitors` every minute; the page shows only rows with `last_seen_at ≥ now − cutoff`.

- **F-ADM-VIS-003 · Refresh cadence** — Realtime channel `active_visitors_changes` (all events) → full refetch; plus polling every **30 000 ms**.

- **F-ADM-VIS-004 · KPI cards** — Six cards (2/3/6 columns): `إجمالي الزوار` ("Total visitors", Users, primary) = row count; `زوار جدد` ("New visitors", UserPlus, green) = `is_new_visitor` true; `زوار عائدون` ("Returning visitors", UserCheck, blue) = falsy; `سطح المكتب` ("Desktop", Monitor, purple); `الجوال` ("Mobile", Smartphone, orange); `التابلت` ("Tablet", Tablet icon, cyan) = counts by `device_type`.

- **F-ADM-VIS-005 · Breakdown: countries** — Card `الدول` ("Countries", Globe): country name + count badge, sorted desc, max height 160 px scroll; empty `لا توجد بيانات` ("No data").

- **F-ADM-VIS-006 · Breakdown: traffic sources** — Card `مصادر الزيارة` ("Visit sources", ExternalLink): source badge + count, sorted desc. Badge colours: direct blue, google red, facebook indigo, twitter sky, instagram pink, whatsapp green, telegram cyan, youtube red-600, referral purple, internal gray; others (linkedin, tiktok, snapchat, bing, yahoo, duckduckgo) muted.

- **F-ADM-VIS-007 · Breakdown: active pages** — Card `الصفحات النشطة` ("Active pages", MapPin): `current_page` truncated to 150 px + count, sorted desc.

- **F-ADM-VIS-008 · Visitors detail table** — Title `تفاصيل الزوار` ("Visitor details"). Columns: `الدولة` (flag emoji from `country_code` via regional indicators; `🌍` for null/`XX`; name or `غير معروف`), `المدينة` (`-`), `IP` (monospace), `الجهاز` (icon + capitalised type, default desktop), `المتصفح`, `النظام`, `الصفحة` (truncated 150 px, full path as tooltip), `المصدر` (badge), `النوع` (`جديد` "New" default badge / `عائد` "Returning" secondary), `آخر نشاط` (Clock + relative time in Arabic with suffix). Empty: `لا يوجد زوار نشطون حاليًا` ("No active visitors right now").
  - *Rebuild:* show IPs masked (last octet hidden) unless super_admin; retain raw IPs at most 30 days (privacy).

- **F-ADM-VIS-009 · Visitors vs scans — page header and ranges** — Hero card: eyebrow `الزوار مقابل الحضور` ("Visitors vs attendance"), H2 `من زار الموقع ومن حضر فعلاً` ("Who visited the site and who actually attended"). Range buttons (selected = filled, others outline): `اليوم` (1 day), `آخر 7 أيام` (7, default), `آخر 30 يوم` (30). Secondary `تحديث` button (spinner; silent refresh, no auto-interval).

- **F-ADM-VIS-010 · Visitors vs scans — data** — Day keys = Qatar `YYYY-MM-DD` for each of the last N days (today inclusive, oldest first). Window: start = now − (N−1) days at **UTC** midnight (defect: not Qatar midnight), end = now. Visitors: `get_visitors_per_date(start, end)` → per `DATE(viewed_at)` (UTC date — defect) `total_visitors, unique_visitors (distinct session), new_visitors, returning_visitors`; the page uses `unique_visitors`. Scans: `ticket_holders` with `is_present = true` and `confirmed_at` in the window (`confirmed_at` = check-in time), max **5000** rows, bucketed by Qatar date. Errors → console only.
  - *Rebuild:* both aggregations in SQL grouped by Qatar date (`CONVERT_TZ`/app-side bucketing), no row caps; protect the analytics queries with the admin policy (original RPCs had no admin check).

- **F-ADM-VIS-011 · Visitors vs scans — KPIs** — Three cards: `زوار الموقع` ("Site visitors", Eye) = Σ unique visitors; `تذاكر تم مسحها` ("Tickets scanned", ScanLine, emerald) = Σ scans; `نسبة الوصول للبوابة` ("Gate reach rate", TrendingUp, primary) = `round(Σscans / Σvisitors × 100)%` (0 when no visitors). Values show `—` while loading.

- **F-ADM-VIS-012 · Visitors vs scans — daily chart** — Card `المقارنة اليومية` ("Daily comparison"): line chart 320 px high, LTR; dashed grid `3 3` in border colour; X axis = Arabic short date (`day numeric, month short`, `ar-u-nu-latn`); Y axis integers only; font size 12; tooltip with card background, border, radius 12; legend; line `visitors` named `زوار الموقع` (primary colour, width 2, no dots, monotone); line `scans` named `تذاكر ممسوحة` ("Scanned tickets", `#10b981`, width 2, no dots). No empty state (zeros plotted).

- **F-ADM-VIS-013 · Tracking exclusions** — Routes not tracked (prefix match): `/admin`, `/admin-login`, `/qr-scanner`, `/live-visitors`, `/live-bookings`, `/admin-pos`. `/staff*` pages ARE tracked (defect).
  - *Rebuild:* track only the public layout (home, ticket selection, checkout, confirmation, invoice, payment result).

- **F-ADM-VIS-014 · Session, geo and device detection** — Session id in sessionStorage `visitor_session_id` = `{Date.now()}-{random base36}`; returning flag localStorage `is_returning_visitor="true"` (first visit reports new). Geo via `https://ipapi.co/json/` cached in sessionStorage `visitor_geo_cache` for **30 min**; fallback `{ip:"unknown", country:"Unknown", country_code:"XX", city:"Unknown"}`. Device: tablet regex `/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i`, then mobile regex, else desktop. Browser order: Firefox, Samsung Browser, Opera/OPR, IE (Trident), Edge/Edg, Chrome, Safari, Unknown. OS: Win → Windows, Mac → macOS, Linux, Android, iOS, Unknown (Android user agents contain "Linux" and are misclassified — defect). Traffic source from referrer host: facebook/fb.com, twitter/x.com, instagram, linkedin, tiktok, youtube, whatsapp, telegram, snapchat, google, bing, yahoo, duckduckgo, same host → internal, other → referral, none → direct.
  - *Rebuild:* server-side geo (MaxMind GeoLite2 local DB, no third-party call from the browser), `jenssegers/agent`-style UA parsing with Android checked before Linux.

- **F-ADM-VIS-015 · Presence heartbeat and page views** — On mount: insert `active_visitors {session_id, ip_address, country, country_code, city, current_page, referrer, traffic_source, device_type, browser, os, is_new_visitor, user_agent, last_seen_at}`; on unique violation update `current_page, last_seen_at`. A `page_views` row `{session_id, page_path, country, country_code, city, device_type, browser, os, traffic_source, is_new_visitor, ip_address}` is recorded whenever the path changes. Heartbeat every **300 000 ms** (5 min; plan said 2 min) and on `popstate`. Unmount deletes the visitor's own row. Stale cutoff 5 min = heartbeat → visitors flicker out.
  - *Rebuild:* beacon every 60 s, cutoff 3 min; upsert by session id on the server (no anonymous direct table writes).

- **F-ADM-VIS-016 · Tracking pipeline (summary)** — Mounted on every route of the app (F-ADM-SHL-015): F-ADM-VIS-013 exclusions → F-ADM-VIS-014 detection → F-ADM-VIS-015 writes. Feeds F-ADM-VIS-001…008, F-ADM-VIS-009…012 and the live visitors board (staff-ops section).

- **F-ADM-VIS-017 · Live visitors board (reference)** — `/live-visitors` (admin-only, opened in a new tab from quick links F-ADM-SHL-012) shows the same `active_visitors` data full-screen; specified in the staff-ops section.

**Upgrades (new, not in original) — ADM-VIS**
- **U-ADM-VIS-01 (NEW)** · Page-view stats per path and per country over a range (DB functions `get_page_view_stats`, `get_visitors_per_country` exist in the original but no screen uses them).
- **U-ADM-VIS-02 (NEW)** · Conversion funnel (visits → checkout → paid → scanned).

**Original defects to avoid:**
- Heartbeat (5 min) equals stale cutoff (5 min) → flicker; stale cleanup call fails (execute revoked) → stale rows remain.
- Android detected as Linux; `/staff` pages tracked as public visits.
- Raw IPs stored and shown; third-party geo call from the browser.
- Visitors grouped by UTC date while scans grouped by Qatar date; window starts at UTC midnight; 5000-scan cap.
- Anyone can update/delete any `active_visitors` row; anonymous upsert breaks under admin-only SELECT policy.
- Analytics functions have no admin check (any signed-in user can call them).

---

### ADM-STF — POS users, staff roles & team attendance

POS seller profiles (cashiers / gate team members without logins), the staff accounts card (see AUTH), the team attendance board (admin view and staff view) and the manual check-in dialog opened from the POS. Routes: `/admin/dashboard/pos-users` (sidebar `مستخدمي POS`), `/admin/dashboard/staff-accounts` (`حسابات الفريق`), `/admin/dashboard/attendance` (`حضور الفريق`), `/staff/attendance` (staff view of the same board). Rebuild: Filament `PosUserResource`, custom Filament page + Livewire staff page sharing one `AttendanceBoard` Livewire component, `ManualCheckIn` Livewire modal in the POS.

- **F-ADM-STF-001 · POS users page composition** — `/admin/dashboard/pos-users` stacks the staff accounts card (F-AUTH-026…030) above the POS users card, 24 px apart.

- **F-ADM-STF-002 · POS user model** — `pos_users`: `id`, `name` (required), `is_active` (default true), `icon` (emoji, default `⭐`), `created_at`. Not login accounts: selected on the POS to attribute cash sales (`orders.pos_user_id`), on the scanner as the gate staff name, and used as the attendance roster. Active users are readable by anyone (needed by staff screens); management is admin-only (original RLS checks `admin_users` only). `orders.pos_user_id` has no ON DELETE (delete fails when the user has orders); `staff_attendance.pos_user_id` cascades.

- **F-ADM-STF-003 · POS users list** — Card title (User icon) `مستخدمي نقطة البيع` ("Point-of-sale users"). Loaded newest first; error toast `خطأ` / `فشل تحميل مستخدمي نقطة البيع`. Table columns: `الأيقونة` ("Icon"), `الاسم` ("Name"), `الحالة` ("Status", switch), `تاريخ الإنشاء` ("Created", `en-US` MM/DD/YYYY), `إجراءات` ("Actions": edit/delete, or save/cancel while editing). Loading `جاري التحميل...`; empty `لا يوجد مستخدمين. أضف مستخدم جديد للبدء.` ("No users. Add a new user to start.").
  - *Rebuild:* add columns "sales today / total" and "last attendance" (NEW).

- **F-ADM-STF-004 · Add POS user** — Input placeholder `اسم المستخدم الجديد` ("New user name") + button `إضافة` ("Add", Plus); Enter submits. Name trimmed, required (`يرجى إدخال اسم المستخدم`). Inserts `{name, icon: next available icon}` (F-ADM-STF-005). Toasts `تم` / `تم إضافة المستخدم بنجاح`; error `فشل إضافة المستخدم`. No uniqueness check.
  - *Rebuild:* unique active name (case-insensitive), max 80 chars.

- **F-ADM-STF-005 · Emoji icon set and allocation** — 20 icons in order: `⭐ 🚗 🌙 🐉 🦁 🦅 🐺 🦊 🐻 🦋 🔥 ⚡ 💎 🎯 🌟 🚀 🎪 🎭 🎨 🏆`. Next available = first icon not used by any existing user, else a random one.

- **F-ADM-STF-006 · Toggle active** — Switch updates `is_active`; toasts `تم تفعيل المستخدم` ("User enabled") / `تم تعطيل المستخدم` ("User disabled"); error `فشل تحديث حالة المستخدم`. Inactive users disappear from POS, scanner and attendance rosters.

- **F-ADM-STF-007 · Inline rename** — Pencil → inline input (autofocus; Enter saves, Escape cancels) with Check (green) / X buttons. Empty → `يرجى إدخال اسم المستخدم`. Toasts `تم تحديث اسم المستخدم بنجاح` / `فشل تحديث اسم المستخدم`.

- **F-ADM-STF-008 · Regenerate icon** — Clicking the emoji (title `انقر لتغيير الأيقونة` "Click to change icon", hover scale 125 %) assigns the next icon unused by the other users. Toasts `تم تحديث الأيقونة` / `فشل تحديث الأيقونة`.
  - *Rebuild:* also allow picking a specific icon from the 20 (NEW).

- **F-ADM-STF-009 · Delete POS user** — Native confirm `هل أنت متأكد من حذف هذا المستخدم؟` → hard delete; toasts `تم حذف المستخدم بنجاح` / `فشل حذف المستخدم - قد يكون مرتبطاً بطلبات` ("Failed — may be linked to orders"). Attendance rows cascade.
  - *Rebuild:* delete allowed only when no orders reference the user; otherwise offer "deactivate" (soft delete); attendance history kept.

- **F-ADM-STF-010 · Staff roles card placement** — The staff accounts/roles card (F-AUTH-026) is shared by `/admin/dashboard/pos-users` (top) and `/admin/dashboard/staff-accounts`.

- **F-ADM-STF-011 · Clarify two kinds of "staff"** — Login accounts (admin/moderator, managed in the roles card) vs POS user profiles (names/icons without login). Attendance and sales attribution use POS user profiles; attendance edit permission uses login accounts.
  - *Rebuild:* optional link `pos_users.user_id` → `users.id` so a cashier account maps to its POS profile (enables "my sales today", U-ADM-STF-01).

- **F-ADM-STF-012 · Staff accounts page** — `/admin/dashboard/staff-accounts`: hero header (F-ADM-SHL-016) + roles card (F-AUTH-026…030).

- **F-ADM-STF-013 · Attendance routes and access** — Same board on `/admin/dashboard/attendance` (admin) and `/staff/attendance` (staff page with header: logo (alt `الشعار`) or text `حضور الفريق`, outline button `رجوع` "Back" → `/staff`). Read: admin, moderator, passcode. Write: signed-in admin or moderator only; passcode holders are read-only.

- **F-ADM-STF-014 · Attendance server contract** — One endpoint, body `{mode, passcode?, date?, from?, to?, pos_user_id?, status?}`; dates `YYYY-MM-DD` (Qatar calendar dates chosen by the client). Not staff → 401 `غير مصرح`. Editor = signed-in user in `admin_users` or with role admin/moderator; write modes without editor → 403 `{error:"account_required", message:"سجّل الدخول بحسابك لتعديل الحضور"}`; `marked_by` = editor id (client value ignored).
  - `list {from, to}` (invalid → 400 `bad_range`) → `{users: active pos_users (id, name, icon, is_active) ordered by name, records: staff_attendance (pos_user_id, attendance_date, status) in range, can_edit}`.
  - `set {date, pos_user_id, status}`: invalid → 400 `bad_input`; `status = null` → delete that user/date row; status must be `present|absent` (400 `bad_status`) → upsert `{pos_user_id, attendance_date, status, marked_by, marked_at: now}` on unique `(pos_user_id, attendance_date)`.
  - `bulk_present {date}` → upsert `present` for all active POS users → `{success, ids}`.
  - `clear_day {date}` → delete all rows of the date.
  - other mode → 400 `bad_mode`; DB errors → 500.
  - Table `staff_attendance`: `id, pos_user_id (FK cascade), attendance_date (date), status (present|absent), marked_by (user id), marked_at, created_at, updated_at`; unique `(pos_user_id, attendance_date)`; index on date desc; direct table access admin-only.
  - *Rebuild:* Livewire actions with policy `attendance.edit` (admin, moderator/supervisor); `marked_by` FK to users; reject dates in the future and outside a configurable back-edit window (NEW).

- **F-ADM-STF-015 · Attendance hero and date navigation** — Eyebrow `حضور الفريق`, H2 `سجل الحضور والغياب` ("Attendance and absence record"), subtitle `الأسماء مأخوذة من مستخدمي نقاط البيع النشطين` ("Names are taken from active POS users"). Date navigator: previous-day button (ChevronRight, aria `اليوم السابق`), centred pretty date (long weekday, day, long month, year, `ar-u-nu-latn`) with a small native date input (LTR, max = today), next-day button (ChevronLeft, aria `اليوم التالي`, disabled when the selected date ≥ today). Default = today in Qatar; future dates blocked. Day shifting uses UTC date arithmetic on the key.

- **F-ADM-STF-016 · Attendance counters** — Four stat cards: `إجمالي الفريق` ("Team total", Users), `حاضر` ("Present", UserCheck, emerald), `غائب` ("Absent", UserX, destructive), `بدون تسجيل` ("Not recorded", CalendarDays, muted) = total − present − absent.

- **F-ADM-STF-017 · Read-only mode** — When loaded and `can_edit` is false: amber notice `أنت في وضع العرض فقط — سجّل الدخول بحسابك (بريد وكلمة مرور) لتسجيل الحضور والغياب.` ("View-only mode — sign in with your account (e-mail and password) to record attendance."). All edit controls disabled; clicking a status shows toast `للقراءة فقط` / `سجّل الدخول بحسابك لتعديل الحضور`.

- **F-ADM-STF-018 · Mark all present** — Button `تحديد الكل حاضر` ("Mark everyone present", CheckCheck; disabled when busy, no users or read-only) → `bulk_present` → all users present locally; toast `تم` / `تم تسجيل الجميع كحاضرين`; error `تعذّر التحديث`.

- **F-ADM-STF-019 · Clear day** — Button `مسح اليوم` ("Clear day", RotateCcw, outline; disabled when busy or read-only) → `clear_day` with **no confirmation**; toast `تم مسح سجل هذا اليوم`; error `تعذّر المسح`.
  - *Rebuild:* confirmation modal; activity log.

- **F-ADM-STF-020 · People grid with one-tap toggles** — Grid 1/2/3 columns. Card tinted emerald when present, destructive when absent; emoji tile (`icon` or `👤`), name, status text `حاضر` / `غائب` / `لم يُسجَّل بعد` ("Not recorded yet"). Two square buttons: Check (aria `تسجيل {name} حاضر`, filled emerald when active) and UserX (aria `تسجيل {name} غائب`, filled destructive when active). Tapping the already-active status clears it (`status: null`). Saving is locked per user; local state updated after success; error `لم يتم حفظ الحالة` ("Status not saved").

- **F-ADM-STF-021 · Last-7-days summary** — Card `ملخّص آخر 7 أيام` ("Last 7 days summary"): table (min width 520 px), header `الاسم` + 7 day columns (the 7 days ending at the selected date; short weekday + day, `ar-u-nu-latn`); cells: circle `✓` (emerald) present, `✗` (destructive) absent, `–` (muted) none. Data fetched with `list {from: selected − 6 days, to: selected}`.

- **F-ADM-STF-022 · Attendance refresh** — Polling every **30 000 ms** (no realtime, because realtime is blocked for passcode-only staff). Load error toast `خطأ` / `تعذّر تحميل بيانات الحضور`.
  - *Rebuild:* Reverb private channel `attendance.{date}` (passcode sessions are authenticated server-side now) with 30 s polling fallback — the plan required realtime across devices.

- **F-ADM-STF-023 · Attendance empty and loading states** — Spinner while loading; empty card `لا يوجد مستخدمو نقاط بيع نشطون — أضفهم من صفحة «مستخدمي POS».` ("No active POS users — add them from the POS users page.").
  - *Rebuild:* show "marked by {name} at {time}" on each card (plan required recording who marked; original stores `marked_by` but never displays it).

- **F-ADM-STF-024 · Manual check-in dialog — UI** — Opened from the POS header (staff name = selected POS user). Dialog `max-w-2xl` with gradient header, title (UserCheck) `تسجيل الحضور اليدوي` ("Manual check-in"), subtitle `ابحث برقم الهاتف أو الاسم أو البريد أو رقم الحجز، ثم سجّل الحضور بلمسة واحدة` ("Search by phone, name, e-mail or booking number, then check in with one tap"). Search input (h-12, autofocus, placeholder `مثال: 66793776 أو أحمد أو QTR-...`, Search icon, spinner while searching); counter `{n} تذكرة · {present} حاضر`. Body max height 55vh: idle `اكتب حرفين على الأقل لبدء البحث` ("Type at least two characters"); no results `لا توجد تذاكر مطابقة لهذا البحث`. All state resets on close.

- **F-ADM-STF-025 · Manual check-in — search** — Debounced **300 ms** after typing ≥ 2 trimmed characters. Server `mode:"search"` (staff auth; < 2 chars → empty): term stripped of `%`, `,`, `(`, `)`; only holders of events with `event_date ≥` Qatar today 00:00; three queries merged by holder id (limit 40 each): holder `name|phone|id_number` contains term; order `booking_reference` contains term; holders of orders whose customer `name|phone|email` contains term (orders limit 60). Result fields: `holder_id, name, phone (country_code + phone), id_number, ticket_type, is_present, confirmed_at, confirmed_by_name, booking_reference, payment_status, payment_method, customer_name ('غير معروف' default), customer_phone, customer_email, event_title, event_date, is_today`. Error toast `تعذر البحث، حاول مرة أخرى`.

- **F-ADM-STF-026 · Manual check-in — result card** — Name; type pill (vip `VIP` with Crown, normal `عادي` with Ticket, parking `مواقف` with Car; colours from CSS variables `--ticket-vip/normal/parking`); badge `حاضر` (green) when present or `ليست لليوم` ("Not for today", outline) when not today; phone (LTR), e-mail, booking reference; line `{event_title} · العميل: {customer_name}`; `سُجّل بواسطة {confirmed_by_name}` ("Recorded by") when present. Action: `تم` ("Done", green check) when present, else button `تسجيل الحضور` ("Check in", spinner while pending).

- **F-ADM-STF-027 · Manual check-in — mark present (server rules)** — `mode:"manual", holder_id, staff_name` (label whitespace-collapsed, trimmed, ≤ 80 chars). Checks in order: missing id → `التذكرة غير محددة`; not found → `تذكرة غير موجودة`; event day passed, or same Qatar day and hour ≥ 18 → `⏰ انتهت صلاحية التذكرة - الحدث انتهى في {date}`; not today → `📅 هذه التذكرة ليست لليوم — صالحة في {date} فقط`; already present → `تم تسجيل حضور هذه التذكرة مسبقاً` with `already:true`; payment not confirmed (POS orders — reference prefix `POS-` or method `cash_pos` — count as confirmed) → `⚠️ الدفع غير مؤكد - لا يمكن تسجيل الحضور`. Update holder `is_present=true, confirmed_at=now, confirmed_by=<user id or null for passcode>, confirmed_by_name=<label>` only where `is_present=false` (race-safe; 0 rows → already message). If the order's `n8n_responded_at` is null, set `n8n_response_message='تم التسجيل يدوياً ✓'`, `n8n_responded_at=now`. Success `{success:true, message:"✅ تم تسجيل حضور {name}", confirmed_at}`; all responses HTTP 200 with a success flag. Client: success toast = server message or `تم تسجيل الحضور`, row patched (present, time, staff name); failure toast = server message or `تعذر تسجيل الحضور`, and if `already` the row is patched present.
  - *Rebuild:* shared `CheckInService` with the QR scanner; "today" = within `[event_date, end_date]` in Qatar time (multi-day seasons); cut-off hour configurable; activity log `checkin.manual`.

- **F-ADM-STF-028 · Gate board (reference)** — `/staff/gate` live board (issued vs entered vs remaining per type for the current event, latest 25 scans, 15 000 ms auto-refresh) was delivered by the same plan as attendance; specified in the staff-ops section.

**Planned vs implemented (.lovable plans 2026-09-15 and 2026-09-21)**

| Plan item | Status in original | Rebuild |
|---|---|---|
| Accordion sidebar groups (Home, Events & tickets, Team, Analytics, Settings, Quick links) | Implemented; extended with `حسابات الفريق` and `الزوار مقابل الحضور`; group renamed `التحليلات والتقارير` | F-ADM-SHL-005…012 |
| Group with current page auto-opens and stays open | Implemented, but an explicitly closed group stays closed | force active group open |
| Luxury active highlight (gradient + side bar), smooth transitions | Partial (solid primary + shadow) | U-ADM-SHL-02 |
| Open/closed state persisted | Implemented (browser) | per user |
| Same menu in mobile drawer | Implemented | F-ADM-SHL-013 |
| Attendance page (names from active POS users with icon, Qatar date bar, one-tap present/absent, tap again clears, counters, mark all, clear day, 7-day table, instant save, RTL) | Implemented | F-ADM-STF-013…023 |
| Attendance realtime across devices | NOT implemented (30 s polling) | F-ADM-STF-022 rebuild |
| Record and show who marked attendance | Stored (`marked_by`), not displayed | F-ADM-STF-023 rebuild |
| Attendance edits require account; passcode read-only with message | Implemented (server-enforced) | F-ADM-STF-017 |
| Staff hub two modes with badge | Implemented (staff-ops section) | — |
| "مبيعاتي اليوم" (my sales today) card for account staff | **NOT implemented** | U-ADM-STF-01 |
| `/staff/gate` live board, 15 s refresh | Implemented | F-ADM-STF-028 (reference) |
| Visitors vs scans page (today/7/30, conversion, dual-line chart, refresh) | Implemented | F-ADM-VIS-009…012 |
| Visitor heartbeat every 2 min | Implemented at 5 min | F-ADM-VIS-015 rebuild |
| Staff accounts page (create, role مدير/مشرف, revoke, reset password) | Implemented | F-AUTH-026…031, F-ADM-STF-012 |
| Login protection: log failures, 5/15 min → 30 min block with countdown, admin e-mail, activity log, 7-day cleanup | Implemented with defects (block math, log CHECK rejects `login_blocked`) | F-AUTH-013…019 |
| Passcode login goes through `login-guard` | Not as planned: passcode verifier has its own per-IP + global throttle | F-AUTH-009, F-AUTH-015 |

**Upgrades (new, not in original) — ADM-STF**
- **U-ADM-STF-01 (NEW, planned in 2026-09-21 plan, never built)** · "مبيعاتي اليوم" (my sales today) card on the staff hub for account staff: count and amount of confirmed POS orders created today (Qatar) attributed to the staff member's POS profile, by ticket type.
- **U-ADM-STF-02 (NEW)** · Attendance export (month × person matrix to Excel) and per-person attendance history.
- **U-ADM-STF-03 (NEW)** · Sales count and amount per POS user in the POS users table.

**Original defects to avoid:**
- POS users management RLS uses `admin_users` only (role-based admins blocked); hard delete fails when orders exist.
- No uniqueness check on POS user names.
- Attendance editor resolution errors for a user holding both admin and moderator roles (`maybeSingle` on two rows → treated as non-editor).
- Clear day has no confirmation; attendance writes are not logged; `marked_by` never shown; no realtime.
- Manual check-in "today" check ignores `end_date` → multi-day season tickets fail after the first day; 18:00 cut-off hard-coded.
- Manual check-in admits web `cash_pos` pending orders while the gate board counts only confirmed orders (inconsistent totals).

---
### Traceability — source checklist (B2) → feature IDs

Every item of the admin-core source checklist maps to at least one feature ID above.

| # | Source checklist item | Feature ID(s) |
|---|---|---|
| 1 | Dashboard layout, sticky header with logo or title linking home | F-ADM-SHL-003 |
| 2 | Header background colour from `header_bg_color` | F-ADM-SHL-003, F-ADM-SET-009 |
| 3 | Logout button | F-ADM-SHL-003, F-AUTH-025 |
| 4 | Collapsible grouped sidebar (5 groups), persisted state | F-ADM-SHL-004…010 |
| 5 | Active-route highlight, auto-open active group | F-ADM-SHL-004, F-ADM-SHL-010 |
| 6 | Mobile drawer with overlay, closes on navigation | F-ADM-SHL-013 |
| 7 | Sidebar unread badge (+99, realtime + 30 s) | F-ADM-SHL-011 |
| 8 | Quick links (POS, scanner, receipts, live bookings, live visitors, website) | F-ADM-SHL-012 |
| 9 | Global capacity alert on every page | F-ADM-SHL-014, F-ADM-CAP-001 |
| 10 | Dashboard index → Orders | F-ADM-SHL-001 |
| 11 | Login page (logo, card, home link, safe `next`) | F-AUTH-001, F-AUTH-006, F-AUTH-012 |
| 12 | Unified card: account + passcode tabs | F-AUTH-002, F-AUTH-003, F-AUTH-007 |
| 13 | Role-based post-login routing | F-AUTH-004, F-AUTH-005 |
| 14 | Account brute-force protection with countdown and warning | F-AUTH-013, F-AUTH-014, F-AUTH-016 |
| 15 | Admin e-mail alert on block + activity log | F-AUTH-017 |
| 16 | Server-side passcode verification, min 8, revoked value, per-IP + global throttle | F-AUTH-009, F-AUTH-010, F-AUTH-015 |
| 17 | Passcode "unavailable" message | F-AUTH-008, F-AUTH-010 |
| 18 | Passcode access persisted 10 days | F-AUTH-018 |
| 19 | Cross-tab handoff via URL hash | F-AUTH-020 |
| 20 | Warm-up ping | F-AUTH-011 |
| 21 | Route guard rules | F-AUTH-021, F-AUTH-022 |
| 22 | Login attempts table, 7-day cleanup | F-AUTH-019 |
| 23 | Staff role resolution hook | F-AUTH-024 |
| 24 | Hero background image | F-ADM-SET-005 |
| 25 | Before-footer image | F-ADM-SET-006 |
| 26 | Header background image | F-ADM-SET-007 |
| 27 | Logo URL/upload ≤ 5 MB | F-ADM-SET-008 |
| 28 | Header background colour | F-ADM-SET-009 |
| 29 | Hero text | F-ADM-SET-010 |
| 30 | Copyright text | F-ADM-SET-011 |
| 31 | Toggle delete-customer button | F-ADM-SET-013 |
| 32 | Toggle generate-QR button | F-ADM-SET-014 |
| 33 | Toggle delete-event button | F-ADM-SET-015 |
| 34 | Toggle delete-ticket button | F-ADM-SET-016 |
| 35 | WhatsApp n8n webhook + switch | F-ADM-SET-018 |
| 36 | E-mail n8n webhook + switch | F-ADM-SET-019 |
| 37 | Auto-invoice interval (min 10) | F-ADM-SET-020 |
| 38 | Invoice batch range | F-ADM-SET-021 |
| 39 | Random delay range | F-ADM-SET-022 |
| 40 | Sadad credentials | F-ADM-SET-023 |
| 41 | Sadad environment | F-ADM-SET-024 |
| 42 | Site URL for return | F-ADM-SET-025 |
| 43 | Sadad readiness diagnostic | F-ADM-SET-026 |
| 44 | Sadad troubleshooting guide | F-ADM-SET-032 |
| 45 | Admin phone | F-ADM-SET-027 |
| 46 | Payment-failed customer e-mail toggle | F-ADM-SET-028 |
| 47 | Admin e-mail with validation | F-ADM-SET-029 |
| 48 | Clarity project id + toggle | F-ADM-SET-030, F-ADM-SET-031 |
| 49 | Download events template | F-ADM-SET-034 |
| 50 | Import events from Excel | F-ADM-SET-035 |
| 51 | Export events | F-ADM-SET-036 |
| 52 | Delete all events | F-ADM-SET-037 |
| 53 | Export ticket types | F-ADM-SET-038 |
| 54 | Delete all ticket types | F-ADM-SET-039 |
| 55 | Export customers | F-ADM-SET-040 |
| 56 | Delete all customers (+ orders, holders) | F-ADM-SET-041 |
| 57 | Events current vs archived | F-ADM-EVT-003 |
| 58 | Season folders | F-ADM-EVT-005 |
| 59 | Filter by single date | F-ADM-EVT-004 |
| 60 | Event card: image, order, title, badge, description, dates, location | F-ADM-EVT-006, F-ADM-EVT-007 |
| 61 | Capacity bars 50/80 % and totals | F-ADM-EVT-008, F-ADM-EVT-009 |
| 62 | Event edit dialog | F-ADM-EVT-011…014 |
| 63 | Ticket type add/update/remove with sold guard | F-ADM-EVT-015 |
| 64 | Archive / restore | F-ADM-EVT-017, F-ADM-EVT-018 |
| 65 | Delete event (setting-gated) | F-ADM-EVT-016 |
| 66 | Toggle active | F-ADM-EVT-019 |
| 67 | Start new season dialog | F-ADM-EVT-020, F-ADM-EVT-021 |
| 68 | Current event selector + clear | F-ADM-EVT-022, F-ADM-EVT-023 |
| 69 | "Moved" badge + history dialog | F-ADM-EVT-024, F-ADM-EVT-025 |
| 70 | Capacity alert levels, remaining, 18:00 hide | F-ADM-CAP-002…004 |
| 71 | "New tickets available" banner + sound + toast | F-ADM-CAP-006…010 |
| 72 | Notifications POS vs online columns with counts/totals | F-ADM-NTF-004, F-ADM-NTF-005 |
| 73 | Notifications date filter | F-ADM-NTF-003 |
| 74 | Unread "new" badge and mark-all-read | F-ADM-NTF-007, F-ADM-NTF-008 |
| 75 | Realtime insert (animated) and update | F-ADM-NTF-009, F-ADM-NTF-010 |
| 76 | Notification details dialog | F-ADM-NTF-011 |
| 77 | Invoice popup and new-tab invoice | F-ADM-NTF-011, F-ADM-NTF-012 |
| 78 | E-mail delivery timeline | F-ADM-NTF-014 |
| 79 | Full orders Excel report | F-ADM-RPT-001…006, F-ADM-RPT-008…011 |
| 80 | Sadad-only report | F-ADM-RPT-007 |
| 81 | Activity log tabs with counts | F-ADM-LOG-003, F-ADM-LOG-004 |
| 82 | JSON detail expander | F-ADM-LOG-007 |
| 83 | Delete one / delete all logs | F-ADM-LOG-008, F-ADM-LOG-009 |
| 84 | Realtime log refresh | F-ADM-LOG-010 |
| 85 | Client logging helper | F-ADM-LOG-012 |
| 86 | Active visitors KPIs | F-ADM-VIS-004 |
| 87 | Breakdown by country, source, page | F-ADM-VIS-005…007 |
| 88 | Visitors detail table | F-ADM-VIS-008 |
| 89 | Refresh, 30 s, realtime, clear all | F-ADM-VIS-001, F-ADM-VIS-003 |
| 90 | Public-site visitor tracking | F-ADM-VIS-013…016 |
| 91 | Visitors vs scans page | F-ADM-VIS-009…012 |
| 92 | POS users add/rename/toggle/icon/delete | F-ADM-STF-003…009 |
| 93 | Staff accounts list with role badges | F-AUTH-026 |
| 94 | Create moderator account | F-AUTH-027 |
| 95 | Promote / demote | F-AUTH-028 |
| 96 | Reset password | F-AUTH-029 |
| 97 | Revoke access | F-AUTH-030 |
| 98 | Staff accounts page with hero | F-ADM-SHL-016, F-ADM-STF-012 |
| 99 | Attendance board, Qatar date navigation, no future dates | F-ADM-STF-013, F-ADM-STF-015 |
| 100 | One-tap present/absent, clear on second tap | F-ADM-STF-020 |
| 101 | Attendance counters | F-ADM-STF-016 |
| 102 | Mark all present; clear day | F-ADM-STF-018, F-ADM-STF-019 |
| 103 | Last-7-days summary | F-ADM-STF-021 |
| 104 | Read-only mode for passcode staff | F-ADM-STF-017, F-ADM-STF-014 |
| 105 | 30 s polling | F-ADM-STF-022 |
| 106 | Manual check-in search | F-ADM-STF-024…026 |
| 107 | Manual check-in mark present with server validation | F-ADM-STF-027 |
| 108 | Staff gate board 15 s (reference) | F-ADM-STF-028 |
| 109 | "My sales today" (planned, not implemented) | U-ADM-STF-01 |


---

## 2.5 Staff operations — POS, receipts, gate, live boards

This section inventories every staff-facing operational screen of the original system: the venue cash desk (POS), the card-receipt reader, the gate QR scanner and its check-in server, the live bookings and live visitors boards, the staff hub / gate board / team attendance pages, and the shared sound and motion effects. Sources: `C-staff-ops.md` (all 97 checklist items are mapped at the end of this section), `D-backend.md` (functions `create-order` POS path, `staff-customer-lookup`, `ticket-checkin`, `gate-stats`, `parse-pos-receipt`, `staff-auth`, `staff-attendance`, `notify-admin-sale`) and `E-database-infra.md` (tables, RPCs, triggers, storage, security findings).

Conventions used below:
- "Qatar time" = `Asia/Qatar` (UTC+3, no DST). All date logic in the rebuild runs in Qatar time; storage is UTC.
- "Staff" in the original = signed-in **admin** (row in `admin_users` or `user_roles.role='admin'`), signed-in **moderator** (`user_roles.role='moderator'`), or anyone holding the **team passcode**. In the rebuild these map to roles `super_admin`/`admin`, `supervisor` (ex-moderator), `cashier`, `gatekeeper`, plus a hardened kiosk session `passcode_staff` (see F-STF-003).
- Ticket types: `normal` (Arabic labels `عادي` / `الدخول العام`), `vip` (`VIP`), `parking` (`مواقف` / `موقف سيارات` / `تصريح مواقف`). Type colours are CSS tokens `--ticket-vip`, `--ticket-normal`, `--ticket-parking` (VIP amber/gold, normal emerald/green, parking blue/cyan). Type icons: VIP Crown, normal Ticket, parking Car.
- Booking reference: `<PREFIX>-<D>-<M>-<YYYY>-<12 uppercase hex>`, PREFIX `POS` (point of sale) or `QTR` (web), date = event date in Qatar time without zero padding (e.g. `POS-5-1-2026-A1B2C3D4E5F6`). Ticket (holder) code / QR payload: `<booking_reference>-TKT<NN>` (NN = 2-digit 1-based ordinal).
- Two toast systems exist in the original (shadcn `useToast` for POS/attendance, Sonner for scanner/live bookings/manual check-in/customer lookup; Sonner: `top-right`, `expand`, 6 visible, gap 12). The rebuild uses one toast component (Livewire/Alpine) with the same texts.

---

### POS — Point of sale (venue cash desk)
Cash desk used at the venue by cashiers to sell tickets on the spot: pick event, build a cart, capture buyer and every ticket holder, submit; the server creates an already-confirmed `POS-` order paid in cash. Original route `/admin/pos` (guard: admin, moderator or team passcode). New app: `/staff/pos`, Livewire 3 full-page component, installable PWA, roles `cashier`, `supervisor`, `admin`, `super_admin`, `passcode_staff`.

- **F-POS-001 · Access & page guard** — Page opens for any staff identity (admin, moderator, passcode holder). Unauthenticated visitors see the unified login card (F-STF-002). The server endpoint independently re-checks staff identity (F-POS-029).
  - *Rebuild:* route middleware `auth:web` + `can:pos.sell` (permission granted to cashier/supervisor/admin/passcode_staff). The POS sale endpoint must never trust a passcode sent in the request body.
- **F-POS-002 · Header & branding** — Sticky header whose background colour is `public_settings.header_bg_color` (default `hsl(var(--card) / 0.5)`). Left/right: logo from `public_settings.logo_url` (click → `/`), or text `نقاط البيع` ("Point of sale") when no logo. A yellow label `بيع تذكرة` ("Sell a ticket") is shown in the header. Header also hosts: cashier selector (F-POS-003), event selector (F-POS-004), `تسجيل الحضور` button (F-POS-042), fullscreen toggle (F-POS-041).
- **F-POS-003 · Cashier selector (POS user)** — Select with primary tint listing `pos_users` where `is_active=true` ordered by name; each item `{icon} {name}` (icon = emoji, default `⭐`). Trigger shows `{icon||⭐} {name}` or placeholder `اختر المستخدم` ("Choose user", User icon). On change: toast `تم` / `تم اختيار المستخدم: {name}` ("Done / User selected: {name}"). The selected id is persisted in `localStorage['pos_user_id']`; on load, if the saved id is not in the active list it is removed. Selecting a cashier is **optional**: a sale without one is allowed (`pos_user_id` null). The same `pos_users` list doubles as the gate-staff list and the attendance roster.
  - *Rebuild:* when the cashier signs in with a personal account, preselect the matching cashier profile; keep the selector for shared kiosk sessions. Consider making cashier mandatory (setting) so every cash sale is attributable — in the original a passcode sale without cashier has no attribution at all (`confirmed_by` null).
- **F-POS-004 · Event selector** — Sellable events = `events` where `is_active=true AND is_archived=false`, ordered by `event_date` ascending, then filtered by `canPurchaseTickets(event_date)`: an event is NOT sellable when its Qatar calendar day is in the past, or it is today and the current Qatar hour is ≥ 18 (18:00 cutoff). The first sellable event is auto-selected. If more than one sellable event: Select (Calendar icon) with placeholder `اختر الفعالية` ("Choose the event"), items = title + date (original used date-fns `PPP`, English format — use Arabic Qatar-formatted date in rebuild). If only one/no event: a disabled outline button showing the date or `اختر التاريخ` ("Choose the date"). Changing the event reloads ticket types, availability and capacity and re-subscribes realtime.
  - *Rebuild:* make the 18:00 cutoff a per-event setting (`sales_close_at`), defaulting to 18:00 Qatar; display dates in Arabic with Latin digits (`ar-u-nu-latn`).
- **F-POS-005 · Ticket type cards** — Card `إضافة التذاكر` ("Add tickets", ShoppingCart icon) containing one card per `tickets` row of the event (ordered by type), 3 columns on md+. Each card: 4px top border in the type colour; gradient icon tile (Crown/Ticket/Car); name `VIP` / `عادي` / `موقف سيارات`; sub-line `متاح: {available} تذكرة` ("Available: n tickets") or red `نفذت الكمية` ("Sold out"); price in large LTR digits followed by `ريال`. Sold-out cards render at 60% opacity.
- **F-POS-006 · Quantity stepper & add button** — Per card: `−` button (aria `تقليل الكمية`), numeric input (range 1..available; invalid input is ignored, not clamped), `+` button (aria `زيادة الكمية`), add button `إضافة` ("Add") or `نفذت` ("Sold out", disabled when no stock). After a successful add the card quantity resets to 1.
- **F-POS-007 · Client availability figure** — Displayed availability per type = `tickets.available_quantity − (count of ticket_holders of that type whose order is for this event AND payment_status='confirmed')` ("holderCounts"). Note this differs from the server's stock counter `sold_quantity`, which also includes unexpired pending holds (F-POS-030).
  - *Rebuild:* show `available_quantity − sold_quantity` (the same figure the server enforces) so the UI never offers stock that the server will refuse.
- **F-POS-008 · Add-to-cart rules** — `addToCart(ticket, qty)`: `remaining = available − holderCounts[type]`; if `inCart + qty > remaining` → toast `خطأ` / `لا يمكن إضافة هذا العدد. المتبقي: {max(0, remaining − inCart)} تذاكر فقط` ("Cannot add this amount. Remaining: n tickets only"). VIP+normal cap breach → toast `الحد الأقصى لتذاكر VIP والعادي معاً هو 5` ("Maximum for VIP and normal together is 5"). Otherwise merge into an existing line of the same ticket or append `{ticketId, ticketType, quantity, price, eventId}`; toast `تمت الإضافة` / `تم إضافة {qty} {type} للسلة` ("Added / {qty} {type} added to cart").
- **F-POS-009 · Per-sale cap: VIP + normal ≤ 5** — Client rule: the combined quantity of VIP and normal tickets in one POS cart cannot exceed 5. Parking is unlimited on the client. Counters shown under the type grid `تذاكر VIP والعادي في السلة: {n} / 5` and in the totals box `تذاكر VIP والعادي: {n} / 5`. Server-side POS has no 5/5 per-order cap (web only); global server caps apply: ≤ 6 item lines, ≤ 20 per line, ≤ 30 tickets per order.
  - *Rebuild:* keep 5 as a setting `pos.max_admission_per_sale` and enforce it on the server too for POS.
- **F-POS-010 · Cart sidebar** — Sticky sidebar (top-24) titled `السلة` ("Cart") with badge `{n} تذكرة`. Empty state: `السلة فارغة` / `أضف تذاكر من الأعلى لتظهر هنا` ("Cart is empty / Add tickets above to show here"). Per line: coloured dot + type name, `{qty} × {price} ريال`; one 48px tile per ticket unit (type icon in type colour, small index number, red X badge on hover; title `حذف هذه التذكرة`, aria `حذف تذكرة {type} رقم {i}`) — clicking a tile removes exactly ONE unit; line subtotal `المجموع` `{price × qty} ريال`.
- **F-POS-011 · Cart totals** — Totals box: `الإجمالي الكلي` ("Grand total") `{total} ريال` and the VIP+normal counter (F-POS-009). Prices displayed are the current `tickets.price`; the server recomputes the real total (F-POS-030).
- **F-POS-012 · Change line quantity** — `updateCartItemQuantity(id, n)`: n < 1 removes the line; n > remaining → toast `لا يمكن تجاوز الحد الأقصى. المتبقي: {remaining} تذاكر` ("Cannot exceed the maximum. Remaining: n tickets"); cap-5 check with the same message as F-POS-008. Every cart change triggers holder reconciliation (F-POS-021).
- **F-POS-013 · Buyer card (main ticket)** — Shown only when the cart is non-empty. Title `معلومات العميل (التذكرة الرئيسية)` ("Customer information (main ticket)"), sub `هذه المعلومات ستُستخدم للتذكرة الأولى وللتواصل مع العميل` ("This information is used for the first ticket and to contact the customer"). Fields: `الاسم *` (name, required); an e-mail input `البريد الإلكتروني` exists but is hidden (`className="hidden"`) — POS never collects e-mail, but an e-mail filled by customer lookup is silently sent; `رقم الهاتف *` (F-POS-014); `رقم الهوية *` (F-POS-016); `الجنسية *` (F-POS-015). The buyer is the holder of the first ticket of the first cart line.
  - *Rebuild:* either show the e-mail field (optional) and use it to e-mail the tickets, or drop it completely; never send hidden data the cashier cannot see.
- **F-POS-014 · Phone with 30 country codes** — `رقم الهاتف *` (type tel, placeholder `XXXXXXXX`) plus a country-code Select (width 140) of 30 entries shown as `{flag} {country} {code}`, default `+974`: +974 قطر 🇶🇦, +966 السعودية, +971 الإمارات, +965 الكويت, +973 البحرين, +968 عمان, +20 مصر, +962 الأردن, +961 لبنان, +963 سوريا, +964 العراق, +967 اليمن, +212 المغرب, +213 الجزائر, +216 تونس, +218 ليبيا, +249 السودان, +970 فلسطين, +92 باكستان, +91 الهند, +880 بنغلاديش, +63 الفلبين, +62 إندونيسيا, +977 نيبال, +1 أمريكا, +44 بريطانيا, +33 فرنسا, +49 ألمانيا, +39 إيطاليا, +34 أسبانيا. The country code is NOT reset after a sale (cashier keeps the last one).
  - *Rebuild:* one shared `config/countries.php` list (code, Arabic name, flag, nationality name) used by POS, public checkout and live boards.
- **F-POS-015 · Nationality buttons** — `الجنسية *` as a button grid (2/3/4/6 columns, height 48–56px, flag + name). Default list = the 6 Gulf nationalities (قطر, السعودية, الإمارات, الكويت, البحرين, عمان). Toggle `عرض المزيد من الجنسيات` / `إخفاء الجنسيات الأخرى` ("Show more nationalities / Hide other nationalities") reveals 24 more: مصر, الأردن, لبنان, سوريا, العراق, اليمن, المغرب, الجزائر, تونس, ليبيا, السودان, فلسطين, باكستان, الهند, بنغلاديش, الفلبين, إندونيسيا, نيبال, أمريكا, بريطانيا, فرنسا, ألمانيا, إيطاليا, أسبانيا. Footer `الجنسية المختارة: {x}` ("Selected nationality"). Default nationality `قطر`. After a sale the list collapses back to the Gulf 6.
  - *Rebuild:* add an "other" free-text option (the original cannot record any nationality outside the 30).
- **F-POS-016 · ID number** — `رقم الهوية *` (placeholder `رقم الهوية`), required (HTML `required` on client; server regex `^[\p{L}\p{N}\-_/ ]{3,30}$`, F-POS-028). The ID's digits (≥ 6) are the person key for the 5-per-person rule (F-POS-025/031).
- **F-POS-017 · Previous-customer lookup box** — Dashed box `استدعاء عميل سابق` ("Recall a previous customer") with an input placeholder `ابحث برقم الهاتف` ("Search by phone number") and a `مسح` ("Clear") button (X, or spinner while loading). Search starts at ≥ 3 characters with 250 ms debounce and calls `staff-customer-lookup {term, passcode}`. Server contract: `term` trimmed < 3 chars → `200 {customers:[]}` (returned BEFORE the auth check); then staff check → `401 {error:"unauthorized"}`; pattern `%term%` with `%` and `,` removed; `customers` (`id, name, email, phone, country_code, nationality, id_number`) where `phone ILIKE pattern` order `created_at desc` limit **8**; `500 {error}` on DB error. Client dedupes by `phone|id_number` (the customers table has one row per order). Results list (max-height 16rem, scroll, keyboard Enter/Space selectable): name, `{cc}{phone} · {id}` (LTR), history chips (F-POS-018), action `اختيار` ("Select"). No result: `لا يوجد عميل مطابق — تابع بإدخال البيانات يدوياً` ("No matching customer — continue entering data manually"). On select: fills all buyer fields, toast.success `تم تعبئة بيانات {name}` ("{name}'s data filled"). Error toast `تعذر البحث عن العميل` ("Could not search for the customer").
  - *Rebuild:* Livewire search action `CustomerLookup::search(term)` behind `can:pos.sell`, rate-limited, returning a de-duplicated "person" list; mask the national ID for `passcode_staff` (original returns full IDs to passcode holders — PII leak); escape `_` as well as `%` in LIKE.
- **F-POS-018 · Event-history chips** — For a lookup result or typed buyer with ID digits ≥ 6 or phone digits ≥ 8, RPC `get_person_event_history(p_id_number, p_phone)` returns confirmed tickets grouped by event (match: ID digits exact (≥6) OR last 8 phone digits), newest first, limit 50. Each chip: `{event date dd MMM yyyy, ar-u-nu-latn, Qatar || event title}` + `{n} تذكرة`.
  - *Rebuild:* the RPC is callable by anonymous users in the original (privacy issue E §8.4); restrict to staff.
- **F-POS-019 · Live phone autocomplete** — While typing the buyer phone: after ≥ 4 digits (non-digits stripped) and 350 ms debounce, a dropdown below the phone field lists previous customers: loading text `جاري البحث عن عملاء سابقين…` ("Searching previous customers…"), rows name + `{cc}{phone} · {id} · {nationality}` + history chips. Click → fills name, phone, country code, ID, nationality (and hidden e-mail); toast `تم` / `تم تعبئة بيانات {name}`; suppresses the next automatic search so the dropdown does not reopen.
- **F-POS-020 · Additional holder forms** — Card `معلومات حاملي التذاكر الإضافية ({n} تذكرة|تذاكر)` ("Additional ticket holder information"), sub `التذكرة الأولى مخصصة للعميل أعلاه. املأ معلومات حاملي التذاكر الإضافية هنا.` ("The first ticket belongs to the customer above. Fill additional holders here."). One block per additional ticket: title `التذكرة الإضافية #{i} - {type name}`, `السعر: {price.toFixed(2)} ر.ق`, destructive delete button (Trash2); fields `الاسم *` (placeholder `اسم حامل التذكرة`), `رقم الهاتف` + country code (optional), `الجنسية *` as a Select of the 30 nationalities (placeholder `اختر الجنسية`), `رقم الهوية *`.
- **F-POS-021 · Holder reconciliation algorithm** — `reconcileHolders(cart, existing)`: required additional holders per type = Σ quantity of lines of that type, minus 1 for the type of the FIRST cart line (the buyer occupies its first ticket). Existing holders are kept up to the needed count per type (in order); missing ones are appended as blanks pre-filled with buyer name, nationality (or `قطر`), ID number, phone and country code.
- **F-POS-022 · Delete a holder** — Removes that holder, decrements the first cart line of that type by 1 (drops the line at 0), re-reconciles; toast `تم الحذف` / `تم حذف بيانات حامل التذكرة` ("Deleted / Holder data deleted").
- **F-POS-023 · Buyer ↔ holder sync** — Buyer name is copied into holders whose name is empty or still equals the previously auto-filled value (manual edits kept). Phone, country code, ID number and nationality are FORCE-copied from the buyer to ALL holders on every buyer change. Editing phone / country code / nationality on any holder syncs to all holders AND back to the buyer.
  - *Rebuild:* keep name/phone/country/nationality pre-fill but never overwrite a holder field the cashier edited (track a per-field "dirty" flag), and **do not** propagate the buyer's ID to holders by default — the original force-sync makes every holder share the buyer's ID, so a group of more than 5 hits `TICKET_LIMIT_EXCEEDED` and the per-person statistics are wrong.
- **F-POS-024 · Submit button & client validation** — Full-width button `إتمام الشراء وإرسال الفاتورة` ("Complete purchase and send the invoice"; loading `جاري المعالجة...`, ArrowRight icon), disabled while processing or when the cart is empty. Ordered checks with toasts: (1) empty cart → `السلة فارغة، يرجى إضافة تذاكر`; (2) missing buyer name/phone/nationality → `يرجى ملء جميع الحقول المطلوبة` ("Please fill all required fields"; ID is enforced by HTML required); (3) holders count ≠ totalTickets − 1 → `يرجى ملء معلومات جميع حاملي التذاكر`; (4) any holder missing name/nationality/ID → `يرجى ملء الاسم والجنسية ورقم الهوية لجميع حاملي التذاكر`; (5) cart spans > 1 event → `لا يمكن الجمع بين تذاكر فعاليات مختلفة في طلب واحد`; (6) no current event → `لم يتم تحديد الفعالية`.
  - *Rebuild:* the button label promises an invoice that POS never sends (F-POS-032); either implement sending (NEW) or rename to `إتمام البيع` ("Complete sale").
- **F-POS-025 · Per-person pre-check (5 per event)** — Before submitting, people are grouped (buyer with the type of the first line + all holders) by person key (ID digits ≥ 6, else last 8 phone digits) and RPC `get_person_ticket_count(p_id_number, p_phone, p_event_id)` is called per person: counts that person's normal+VIP holders for the event in confirmed orders or pending orders not yet expired (match by ID when an ID key exists, otherwise by phone; parking excluded). Violations → toast title `تجاوز الحد الأقصى للتذاكر` ("Ticket limit exceeded"), description joined by ` — `: `{name} بلغ الحد الأقصى (5 تذاكر) لهذه الفعالية` ("reached the maximum (5) for this event") or `{name} لديه {existing} تذكرة — يمكنه إضافة {remaining} فقط (طلب {requested})` ("has n tickets — may add only r (requested q)"). RPC errors are ignored (the DB trigger is the hard guard, F-POS-031). `MAX_TICKETS_PER_PERSON = 5`.
- **F-POS-026 · Stock pre-check** — Per cart line RPC `reserve_tickets(p_event_id, p_ticket_type, p_quantity)` — read-only despite its name: `available = available_quantity − sold_quantity`; no row → `{success:false, error:'TICKET_NOT_FOUND', message:'نوع التذكرة غير موجود لهذه الفعالية'}`; insufficient → `{success:false, error:'INSUFFICIENT_CAPACITY', message: available ≤ 0 ? 'عذراً، نفدت التذاكر من هذا النوع حالياً' : 'عذراً، لا تتوفر تذاكر كافية. المتاح: {n} تذكرة', available, requested}`; ok → `{success:true, available, after_reservation}`. Client-side call errors → `فشل في التحقق من توفر التذاكر` / `حدث خطأ غير متوقع`. Failure → toast `خطأ - عدد التذاكر المطلوبة غير متاح` / `{type}: {message}` and availability is refetched.
  - *Rebuild:* name it `TicketAvailability::check()`; it is advisory only — the real guard is the locked transaction in F-POS-030.
- **F-POS-027 · Sale request payload** — Holders for the API: first = buyer with `ticket_id` of the first line; each additional holder `{ticket_id (line of its type), name.trim(), phone (holder's or buyer's).trim(), country_code, nationality, id_number.trim()}`. Request `create-order {source:"pos", payment_method:"cash_pos", customer:{name, email, phone, country_code, nationality, id_number}, items:[{ticket_id, quantity}], holders, pos_user_id, staff_passcode}`. Prices, totals, statuses and event id are never sent.
- **F-POS-028 · Server validation (shared with web orders)** — Body must be a JSON object ≤ 64,000 chars (413 `payload_too_large`; invalid JSON 400 `invalid_json`). Fields are whitespace-collapsed and trimmed; control characters rejected. Customer: name 2–100; e-mail optional but if present ≤ 254 and `^[^\s@]+@[^\s@]+\.[^\s@]{2,}$`; phone chars `[+\d\s\-()]` with 6–15 digits; country code `^\+\d{1,4}$` (default `+974`); nationality 1–60; ID `^[\p{L}\p{N}\-_/ ]{3,30}$` (required). Items: 1–6 lines, ticket_id UUID, no duplicates, quantity integer 1–20, Σ ≤ 30. Holders: one per ticket, ticket_id UUID, name ≥ 2, valid phone, nationality ≤ 60, valid ID; holders per ticket_id must equal item quantity. `payment_method ∈ {sadad, cash_pos}`; `source ∈ {web, pos}` (default web); `pos_user_id` UUID or null. All errors returned at once: `400 {success:false, code:'validation_failed', error:'The order details are invalid', errors:[…]}`.
  - *Rebuild:* `StorePosSaleRequest` FormRequest with identical rules and the same error list shape (Pest tests port `validation.test.ts`).
- **F-POS-029 · Server auth & cash-only rule** — For `source:"pos"`: staff identity required (admin or moderator JWT, or `staff_passcode` equal to the team passcode) else `401 unauthorized "Staff only"`; adding `source:'pos'` to a public request must give 401. POS must be `cash_pos`, else `400 validation_failed "Point-of-sale orders are cash orders"`. Actor = signed-in user id, or null for passcode staff. POS is exempt from the web throttle "max 4 open unpaid orders per phone" (429 `too_many_pending_orders`).
- **F-POS-030 · Order creation transaction** — Single DB transaction (`create_order`): lock all `tickets` rows of the event `FOR UPDATE` ordered by id; expire stale Sadad holds of the event; all items must belong to ONE event (`invalid_tickets` 422); POS skips the `is_active` event check and the web per-order 5 admission / 5 parking caps; per line stock `available_quantity − sold_quantity ≥ qty` else `insufficient_stock:<type>` (409 with `ticket_type`); holders count/type must match (`holders_mismatch` 422). A NEW `customers` row is inserted for every sale (`email` '' when absent). Order row: `ticket_type` = most expensive type in the cart, `quantity` = total tickets, `total_amount` = Σ server price × qty, `payment_method='cash_pos'`, `payment_status='confirmed'`, booking reference `POS-<D>-<M>-<YYYY>-<12 hex>` (event date in Qatar time; up to 5 retries on collision), `confirmed_by = actor`, `confirmed_at = paid_at = now()`, `pos_user_id`, `n8n_response_message = 'طلب من نقطة البيع - POS'` and `n8n_responded_at = now()` (marks the WhatsApp automation as handled so nothing is sent). Holders inserted with codes `<ref>-TKT01…`; holder country code = holder's, else `+code` prefix parsed from the holder phone (`^\s*(\+\d{1,4})\s`), else the customer's, else `+974`. Each holder insert reserves one seat (`sold_quantity + 1`, or `insufficient_stock:<type>`).
  - *Rebuild:* `CreateOrderAction` in `DB::transaction()` with `Ticket::where('event_id',…)->orderBy('id')->lockForUpdate()`, retry on deadlock; store `ticket_id` on each holder (FK) instead of matching by (event, type); upsert customers by (normalised phone, ID) instead of one row per sale.
- **F-POS-031 · Per-person limit (server)** — BEFORE INSERT trigger on holders (normal/VIP only, skipped when no ID key and no phone key): advisory lock on `id:<digits>` or `ph:<last8>` + event, then `get_person_ticket_count ≥ 5` → `TICKET_LIMIT_EXCEEDED: الحد الأقصى هو 5 تذاكر (عادي + VIP) لكل شخص. {name} لديه {n} تذكرة بالفعل` → API `422 ticket_limit_exceeded` with that Arabic text. Applies to all channels (web, cash, POS, restore). Test-pinned: after 3 tickets for an ID, an order of 3 more for the same ID must fail, an order of 2 more must succeed, the refused order must hold no seats. People already over the limit keep their tickets.
  - *Rebuild:* `PersonTicketLimit` domain service called inside the order transaction with `Cache::lock("person:{key}:{event}")` (Redis) or `GET_LOCK`; count rows already inserted in the same transaction (C notes report that same-statement inserts may be invisible in one code path; E's tests say they are counted — the Laravel version must count them explicitly).
- **F-POS-032 · Post-sale side effects** — After commit: (a) background `notify-admin-sale {order_id}` → admin e-mail template `admin-sale-alert` (subject `بيع جديد — <ref> (<total> ر.ق)`, source label `بيع عبر نقاط البيع`, payment method `نقدي / نقاط البيع`, idempotency key `admin-sale-<order id>`, logged in `email_delivery_events`; failure never fails the sale); (b) one QR PNG per holder rendered and uploaded to bucket `qr-codes/<code>.png`, URL saved in `ticket_holders.qr_image_url` (failures logged; ticket still scans by its code) — this runs BEFORE responding (adds latency). No customer e-mail, no WhatsApp, no n8n webhook for POS sales.
  - *Rebuild:* dispatch `OrderConfirmed` event → queued listeners `NotifyAdminOfSale`, `GenerateTicketQrCodes` (Horizon), so the cashier gets the response immediately.
- **F-POS-033 · Sale response** — `200 {success:true, order:{id, booking_reference, total_amount, payment_method, payment_status, payment_expires_at:null}}`, header `Cache-Control: no-store`.
- **F-POS-034 · After-success handling** — On success: open success dialog + replay fireworks (trigger counter +1); write activity log `pos_form` (user type admin, identifier = buyer phone, data `{booking_reference, customer_*, total_amount, ticket_count, cart_items[{ticket_type, quantity, price}], ticket_holders[{name, nationality, id_number}]}`, metadata url + browser); reset cart, holders, name, e-mail, phone, nationality (`قطر`), ID, collapse nationalities (country code NOT reset); refetch ticket availability.
- **F-POS-035 · Success dialog** — Dialog with glowing border: pinging success ring + check icon, `تم بنجاح!` ("Success!"), `تم إنشاء الطلب بنجاح` ("Order created successfully"), `عدد التذاكر` (big number), `أنواع التذاكر:` rows `{qty}x {type name}`, `حاملي التذاكر:` list (first prefixed `👤 `), button `حسناً` ("OK"). The booking reference is NOT shown and nothing is printed.
  - *Rebuild:* show the booking reference (copyable, large), the QR of the first ticket, and buttons "Print tickets" / "Send via WhatsApp" (NEW, see upgrades).
- **F-POS-036 · Fireworks on success** — Full-screen canvas celebration for 6000 ms on every successful sale, disabled under `prefers-reduced-motion: reduce` (exact spec F-SFX-009…016).
- **F-POS-037 · Error message mapping** — Toast title `تجاوز الحد الأقصى للتذاكر` when code is `ticket_limit_exceeded` or the message contains `TICKET_LIMIT_EXCEEDED`, else `خطأ`. Description: server Arabic message for limits (prefix stripped; default `الحد الأقصى هو 5 تذاكر لكل شخص`); otherwise by code: `insufficient_stock` → `عذراً، لم تعد هناك تذاكر كافية من هذا النوع. يرجى تقليل الكمية أو اختيار نوع آخر.` + ` (VIP|الدخول العام|المواقف)`; `quantity_limit_exceeded` → `تجاوزت الحد الأقصى للتذاكر في الطلب الواحد.`; `event_not_available` → `هذه الفعالية غير متاحة للحجز حالياً.`; `validation_failed` → `يرجى التحقق من البيانات المُدخلة والمحاولة مرة أخرى.`; `invalid_tickets` → `إحدى التذاكر المختارة لم تعد متاحة. يرجى العودة واختيار التذاكر من جديد.`; `holders_mismatch` → `يرجى إدخال بيانات حامل كل تذكرة.`; `unauthorized` → `انتهت الجلسة أو كلمة مرور الفريق غير صحيحة. يرجى تسجيل الدخول من جديد.`; `forbidden` → `ليست لديك صلاحية لتنفيذ هذا الإجراء.`; `network_error` → `تعذر الاتصال بالخادم. تحقق من اتصالك بالإنترنت وحاول مرة أخرى.`; other codes → `حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.`. Legacy guard fallbacks: `CAPACITY_EXCEEDED` → `نفدت التذاكر من هذا النوع. يرجى اختيار نوع آخر أو المحاولة لاحقاً.`, `AMOUNT_MISMATCH` → `تغيّر سعر التذاكر. يرجى تحديث الصفحة وإعادة المحاولة.`, `INVALID_QUANTITY` → `عدد التذاكر غير صالح. يرجى تعديل الطلب.`, final fallback `فشل إنشاء الطلبات`.
  - *Rebuild:* keep the messages in `lang/ar/orders.php` keyed by error code; plain `insufficient_stock` without `:type` (raised by the status-sync trigger) is unmapped in the original → 500; map it too.
- **F-POS-038 · Capacity alert bar** — Shown only when the selected event is not expired. One alert per type with badge `{label}: {confirmed}/{capacity} ({pct}%)` plus ` - متبقي {remaining}` (except critical). Thresholds: ≥ 100% critical red `تجاوز السعة!` ("Over capacity!"); 90–99% warning orange `تحذير - السعة تقارب الامتلاء (90%+)`; 80–89% nearing yellow `السعة تقترب (80%+)`; < 80% normal green `السعة متاحة` ("Capacity available"). Labels: vip `VIP`, normal `عادي`, parking `موقف`. Recomputed on any holder change and any order update (channel `capacity-alerts`).
- **F-POS-039 · Capacity-increase banner** — When `tickets.available_quantity` of the selected event increases compared with the remembered value (admin added tickets): fixed centred banner with gradient by type (VIP amber→orange, normal emerald→green, parking blue→cyan), bouncing Sparkles icon, title `تذاكر جديدة متاحة للبيع!` ("New tickets available for sale!"), text `تم إضافة {increase} تذكرة {type label} • الإجمالي: {newCapacity}`, X dismiss, shrinking progress bar (4 s animation); auto-hide after 10 s on POS; plays the alert bell (F-SFX-002); toast `🎉 تذاكر جديدة متاحة!` / `تم إضافة {n} تذكرة {label} ({total} إجمالي)` (duration 8000 ms).
  - *Rebuild:* align banner timeout, progress animation and toast duration (original mismatch 10 s / 4 s / 8 s); broadcast `TicketCapacityIncreased` on a private event channel.
- **F-POS-040 · Realtime availability** — Original channels: `tickets-realtime` (UPDATE on `tickets` of the event → patch sold/available), `ticket-holders-realtime` (ANY change on `ticket_holders`, unfiltered, platform-wide → refetch holder counts), `orders-realtime-pos` (orders UPDATE of the event where payment_status changed → refetch), `capacity-alerts`, `capacity-notification-{eventId}`.
  - *Rebuild:* Reverb private channel `event.{id}.stock` receiving `StockChanged {type, available, sold}` (debounced server-side, max 1/s) — never subscribe to all holders of the platform; polling fallback every 10 s when the socket is down.
- **F-POS-041 · Fullscreen toggle** — Hidden on mobile. Maximize/Minimize icon button, title `ملء الشاشة` / `تصغير الشاشة`; listens to `fullscreenchange`; failure toast `خطأ` / `لا يمكن تفعيل وضع ملء الشاشة` ("Cannot enable fullscreen").
- **F-POS-042 · Manual check-in dialog — search** — Header button `تسجيل الحضور` ("Register attendance", UserCheck, gradient) opens dialog `تسجيل الحضور اليدوي` ("Manual check-in"), sub `ابحث برقم الهاتف أو الاسم أو البريد أو رقم الحجز، ثم سجّل الحضور بلمسة واحدة` ("Search by phone, name, e-mail or booking reference, then check in with one tap"). Autofocus input (h-12) placeholder `مثال: 66793776 أو أحمد أو QTR-...`, spinner while loading. Search at ≥ 2 chars, debounce 300 ms → server mode `search` (F-GATE-044). Counter `{n} تذكرة · {present} حاضر`. Empty states: before typing `اكتب حرفين على الأقل لبدء البحث` ("Type at least two characters"); no match `لا توجد تذاكر مطابقة لهذا البحث`. Failure toast `تعذر البحث، حاول مرة أخرى`.
- **F-POS-043 · Manual check-in dialog — result card** — Per result: holder name; type chip (VIP Crown / `عادي` Ticket / `مواقف` Car in type colours); badge `حاضر` (green) when present, or outline `ليست لليوم` ("Not for today") when `is_today` false; phone (holder's or customer's), e-mail, `#ref`; `{event_title} · العميل: {customer_name}`; `سُجّل بواسطة {confirmed_by_name}` ("Registered by"); right side: `تم` (green check) when present, otherwise button `تسجيل الحضور` (spinner while pending). Payment-pending tickets are listed with the button; the server refuses them.
- **F-POS-044 · Manual check-in action** — Button → server mode `manual {holder_id, staff_name: selected cashier NAME, passcode}` (rules F-GATE-045). Success toast = server message or `تم تسجيل الحضور`; the row becomes present with `confirmed_at` and `confirmed_by_name`. Failure toast = server message or `تعذر تسجيل الحضور`; if the response has `already:true` the row is marked present. Dialog state resets on close.
  - *Rebuild:* show a disabled button with reason for payment-pending / not-today rows instead of letting the server refuse.
- **F-POS-045 · Loading & layout** — Loading text `جاري التحميل...`. Two columns on lg (`minmax(0,1fr) 360px`) with the sticky cart on the side; single column on mobile.
- **F-POS-046 · Dead code not to port** — Date-picker handler `handleDateSelect` (events by local-day range) and the Calendar/Popover/`selectedDate` imports are unused; `togglePresence`-style helpers and duplicate `canAddToCart` logic are not features.

**Upgrades (new, not in original):**
- NEW: Payment types beyond cash — `card_terminal` (with terminal reference / last 4 / auth code; links the OCR receipt of RCP to the order), `split` (cash + card), and optional Sadad QR/payment link for the customer's own phone. The original POS is cash only (`payment_method` enum `sadad|cash_pos`).
- NEW: Receipt & ticket printing (80 mm thermal receipt with booking reference, totals, cashier, and one QR per holder; PDF via spatie/laravel-pdf or ESC/POS), and "Send tickets via WhatsApp/e-mail" button using the existing n8n contract.
- NEW: Show booking reference and per-holder QR codes in the success dialog; "reprint last sale".
- NEW: Cashier shift / cash-drawer reconciliation report (sales per cashier per shift, cash expected vs counted), using `pos_user_id`.
- NEW: Refund/void from POS with supervisor approval (cancellation must make the tickets invalid at the gate — see defects).
- NEW: Offline-tolerant POS is not recommended (stock must be locked server-side); instead show a clear "offline" banner and disable submit.
- NEW: Barcode/ID-card scanner input for the ID number field.

**Original defects to avoid:**
- Button text promises `وإرسال الفاتورة` but no customer invoice/WhatsApp is ever sent for POS (n8n marked responded at creation).
- Hidden e-mail field silently sent from lookup.
- Buyer's ID force-copied to every holder → false `TICKET_LIMIT_EXCEEDED` for groups and wrong per-person data.
- Client availability uses confirmed holders while the server uses `sold_quantity` (includes pending holds) → UI shows stock the server refuses.
- `reserve_tickets` reserves nothing (misleading name).
- Unfiltered realtime subscription on all `ticket_holders` → refetch storms at peak times.
- New `customers` row per sale (no dedupe); lookup dedupes client-side only.
- Activity log (with full PII: names, national IDs) written from the browser — forgeable; must be server-side.
- `staff-customer-lookup` returns national IDs to passcode-only staff and accepts the passcode with no throttling (brute-force vector shared with all staff endpoints).
- `get_person_event_history` callable by anonymous users.
- POS sales by passcode staff have `confirmed_by` null; with no cashier selected the sale is not attributable to anyone.
- Booking reference never displayed after a sale; no printing.
- 18:00 sales cutoff hard-coded; event dates shown in English date-fns format.
- POS cash orders are later admitted at the gate even if cancelled/refunded (see F-GATE-039 and GATE defects).

---

### RCP — POS card-receipt reader (OCR)
Digitises card-terminal (bank POS machine) payment slips for the daily reconciliation report: photograph a slip, a vision LLM reads amount / SEQ / masked card / time / AUTH, a row is saved, the cashier types how many normal/VIP/parking tickets that card payment covered, and a summary + styled Excel export is produced. Original route `/admin/pos-receipts` (route guard: any staff; data effectively admin-only because of RLS/storage policies). Not linked to orders. UI is English, LTR (`dir="ltr"`), except a few Arabic words. New app: `/staff/receipts` (Livewire) — roles `admin`, `accountant`, `supervisor`, and optionally `cashier` for capture only.

- **F-RCP-001 · Access** — The page is linked from the staff hub (`قراءة إيصال POS` / `تحويل الإيصال إلى بيانات`) and opens for any staff identity, but table `pos_receipts` and storage writes are restricted to admins (`is_admin(auth.uid())`, i.e. `admin_users`). Moderators and passcode staff can open the page but listing, upload and insert fail silently or with `Failed to process receipt`.
  - *Rebuild:* explicit permissions `receipts.capture` (cashier, supervisor, admin) and `receipts.manage` (edit counts, delete, export: accountant, admin). The route and the data policy must agree.
- **F-RCP-002 · Page shell** — Title `POS Receipt Reader`; English LTR layout.
  - *Rebuild:* Arabic RTL like the rest of the app (keep English as secondary locale); the original mixes languages.
- **F-RCP-003 · Capture or upload** — Card `Capture or Upload Receipt Image` with two large buttons (height 96px, 40px icons): `Capture with Camera` (Camera icon, primary) backed by a hidden `<input type=file accept="image/*" capture="environment">`, and `Choose from Gallery` (Image icon, secondary) backed by a hidden `<input accept="image/*">`. While processing both show a spinner + `Processing...` and are disabled. Both file inputs are reset after each attempt.
- **F-RCP-004 · Preview & processing overlay** — The chosen file is read as a data URL and shown as a preview (max-height 16rem) with a red X button (clears the preview only) and an overlay spinner `Reading receipt...` while processing. Preview is cleared after a successful save.
- **F-RCP-005 · Image storage** — The image is uploaded FIRST (before OCR) to storage bucket `pos-receipts`, object name `receipts/{Date.now()}-{random base36 ≈5–6 chars}.{original extension or 'jpg'}`, `cacheControl '3600'`, `upsert false`; upload failure → error "Failed to upload image". The public URL is stored in `pos_receipts.image_url`. The bucket is PUBLIC-read (anyone with the URL can see card slips).
  - *Rebuild:* spatie/laravel-medialibrary on a **private** disk, served through short-lived signed URLs to authorised users only; upload after a successful OCR (or delete the file when OCR fails) so no orphan images remain.
- **F-RCP-006 · OCR endpoint contract** — `parse-pos-receipt` POST `{imageBase64, passcode?}`: staff identity required (admin, moderator or team passcode) else `401 {success:false, error:"unauthorized", message:"غير مصرح"}` (checked before size); `imageBase64` length > 8,000,000 chars → `413 {error:"Image is too large"}`; missing → `400 {error:"No image provided"}`; missing AI key → `500`.
  - *Rebuild:* `POST /staff/receipts/ocr` (session auth, `receipts.capture`), multipart upload (max 8 MB, mime image/jpeg|png|heic|webp), per-user rate limit (e.g. 20/min) because every call costs AI credits.
- **F-RCP-007 · AI model & prompt** — Model `google/gemini-2.5-flash` via an OpenAI-compatible gateway (`/v1/chat/completions`, Bearer key), no timeout. Image always declared as `data:image/jpeg;base64,…`. System prompt (intent, keep verbatim in rebuild): "You are an AI specialized in reading POS receipt images… extract ONLY: `amount_qar` (number), `seq_number` (string), `card_number_masked` (string), `time` (HH:MM:SS), `auth_number` (string). Rules: amount_qar is a number without currency symbols (e.g. 1000 not "QAR 1000.00"); seq_number must match SEQ NO / SEQ NUMBER; card_number_masked keeps the masking exactly (**** allowed); time is 24-hour HH:MM:SS; auth_number must match AUTH NO / AUTH NUMBER; missing/unreadable → null; do NOT guess; no explanations; return ONLY the JSON object." User message: the image + "Extract the receipt data from this POS receipt image and return it as JSON."
  - *Rebuild:* prism-php/prism with a configurable vision provider, structured output schema (JSON schema with the 5 nullable fields), 30 s timeout, correct mime type.
- **F-RCP-008 · AI error mapping** — Upstream 429 → `429 {error:"Rate limit exceeded. Please try again later."}`; 402 → `402 {error:"Payment required. Please add credits to your workspace."}`; other non-OK → `500 {error:"AI gateway error"}`.
- **F-RCP-009 · Response parsing** — `choices[0].message.content` trimmed, leading ```` ```json ```` / ```` ``` ```` and trailing ```` ``` ```` stripped, `JSON.parse`; failure → `422 {error:"Failed to parse receipt data", rawResponse}`. Success → `200 {success:true, data:{amount_qar, seq_number, card_number_masked, time, auth_number}}`. No validation of values.
  - *Rebuild:* validate: amount decimal ≥ 0 with 2 decimals; time `HH:MM:SS`; SEQ/AUTH alphanumeric ≤ 20; card mask `^[\d*X ]{4,19}$`; never store a full PAN (reject/mask any 13–19 consecutive digits).
- **F-RCP-010 · Save row** — Client: on transport error → catch; on `data.error` → `toast.error(data.error)` and stop (image already uploaded → orphan). Otherwise INSERT `pos_receipts {amount_qar, seq_number, card_number_masked, time, auth_number, image_url}` (ticket counts default 0; `created_by` never filled); toast `Receipt read and saved successfully`; preview cleared (the realtime event adds the row). Any other error → `Failed to process receipt`.
  - *Rebuild:* fill `created_by` and `pos_user_id`, record the event date; duplicate detection on (`seq_number`, `auth_number`, `amount`, same day) with a warning "possible duplicate" (original allows saving the same slip twice).
- **F-RCP-011 · Data model** — `pos_receipts`: `id`, `amount_qar` numeric, `seq_number` text, `card_number_masked` text, `time` text (time printed on the slip), `auth_number` text, `image_url` text, `created_at`, `created_by` (FK users, unused), `ticket_type` text default 'normal' (unused, legacy: comment "normal (200 QAR), vip (300 QAR), or parking (500 QAR)"), `num_tickets` int (unused), `normal_tickets` / `vip_tickets` / `parking_tickets` int default 0. In realtime publication.
  - *Rebuild:* drop `ticket_type`/`num_tickets`; add `event_id`, `pos_user_id`, `order_id` (nullable link), `amount` DECIMAL(10,2), `printed_time` TIME, `status` (`unreconciled|matched|void`), soft deletes.
- **F-RCP-012 · Realtime list** — Channel `pos_receipts_realtime` (all events): INSERT → prepend; UPDATE → replace; DELETE → remove. Several devices see the same list live.
  - *Rebuild:* broadcast `ReceiptSaved/Updated/Deleted` on private channel `receipts`, or Livewire polling 10 s.
- **F-RCP-013 · Saved receipts list** — Card `Saved Receipts ({n})` with outline button `Export Excel` (FileDown; disabled when empty). Loads `pos_receipts` ordered `created_at desc` — ALL rows, no pagination, no date filter. Loading spinner. Empty: `No saved receipts` / `Capture or upload an image to add a receipt`.
  - *Rebuild:* default filter = today (Qatar) with a date-range picker and event filter; paginate.
- **F-RCP-014 · Mobile layout (< sm)** — One card per receipt: `#{i}`, delete (ghost red Trash2, no confirmation), `Amount` `{amount.toLocaleString()} QAR`, `Seq #`, three number inputs `Normal` / `VIP` / `Parking` (min 0), `Auth #`, `Card`, `Time`, footer created_at `toLocaleString("en-US")`.
- **F-RCP-015 · Desktop table** — Columns: `#`, `Amount (QAR)`, `Seq Number`, `Normal` (input w-14), `VIP`, `Parking`, `Card Number` (md+), `Time` (lg+), `Auth Number` (lg+), `Date` (`toLocaleDateString("en-US")`), `Delete`.
- **F-RCP-016 · Editable ticket counts** — The three count inputs write on every keystroke: UPDATE `pos_receipts.{normal|vip|parking}_tickets = parseInt(value) || 0`, then patch local state; error toast `Failed to update tickets`. OCR fields themselves are not editable.
  - *Rebuild:* debounce 500 ms (`wire:model.live.debounce.500ms`), and allow correcting OCR fields (amount, SEQ, AUTH, card, time) with an audit trail; flag rows where Σ(count × price) ≠ amount.
- **F-RCP-017 · Delete receipt** — Immediate DELETE without confirmation; toast `Receipt deleted` / `Failed to delete receipt`. The storage file is NOT deleted.
  - *Rebuild:* confirmation modal, soft delete, delete media on force-delete, audit log entry.
- **F-RCP-018 · Summary table** — Centred table (max-w-md): header `POS` (colspan 3); columns `Type` / `Tickets` / `Amount (QAR)`; rows `normal` (green), `VIP` (amber), `parking` (blue), `المجموع` ("Total", primary tint, bold). Tickets = Σ counts per type; amount = count × HARD-CODED price: **normal 200, VIP 300, parking 500 QAR**; total row = Σ tickets and Σ amounts.
  - *Rebuild:* prices from the selected event's `tickets.price` (or a configurable "card price list" setting); show also Σ OCR amounts and the difference.
- **F-RCP-019 · Excel export — file & title** — maatwebsite/excel equivalent of the original xlsx-js-style output: file `pos_receipts_{YYYY-MM-DD}.xlsx` (original uses the UTC ISO date — use Qatar date), sheet `POS Receipts`. Row 1: title merged A1:J1 `كشف تحميل رسوم تذاكر دخول مهرجان قطر للابل- جزيلا العطا - بتاريخ {today dd/mm/yyyy} (POS)`, bold 14 white on `#7C3AED`, centred, row height 25 pt. Row 2 empty.
- **F-RCP-020 · Excel export — headers & data** — Row 3 headers: `#`, `Amount (QAR)`, `Seq Number`, `Normal`, `VIP`, `Parking`, `Card Number`, `Time`, `Auth Number`, `Date Added`; bold 11 white on `#1E3A5F`, thin black borders, height 22 pt. Data from row 4: index, amount ('' if null), seq, normal, vip, parking (0 default), card, time, auth, created_at (`en-US` locale string); font 10, zebra fill `#F3F4F6` (even index) / `#FFFFFF`, centred, thin `#E5E7EB` borders. Column widths (wch): 5, 15, 15, 10, 10, 10, 22, 12, 15, 20. Empty data → toast `No data to export`; success → `Data exported successfully`.
- **F-RCP-021 · Excel export — summary block** — After one empty row, columns B–D: header row `pos` (bold, fill `#D4E6F1`), `normal | count | amount` (fill `#D5F5E3`), `VIP` (`#FCF3CF`), `parking` (`#D6EAF8`), `المجموع | total tickets | total amount` (bold, `#F5CBA7`); all thin black borders. Same fixed prices as F-RCP-018.
  - *Rebuild:* make the festival name in the title a setting (`reports.receipt_title`); export the filtered date range only.
- **F-RCP-022 · Toast catalogue** — `Failed to load receipts`, `Receipt read and saved successfully`, `Failed to process receipt`, `Failed to update tickets`, `Receipt deleted`, `Failed to delete receipt`, `No data to export`, `Data exported successfully`, plus server errors shown verbatim (rate limit / credits / parse).
  - *Rebuild:* translate to Arabic primary strings.
- **F-RCP-023 · Stored image never viewable** — `image_url` is stored but no UI displays it.
  - *Rebuild:* thumbnail + lightbox per row through signed URLs (for audit of OCR mistakes).

**Upgrades (new, not in original):**
- NEW: Link a receipt to a POS order (card payment type in POS) and auto-match by amount/time; reconciliation dashboard (receipts vs card sales vs cash sales per day/cashier).
- NEW: Bulk capture (multiple photos queued, processed by Horizon jobs) with progress.
- NEW: Re-run OCR on a stored image; manual entry form when OCR fails.
- NEW: Daily PDF report alongside Excel.

**Original defects to avoid:**
- Public storage bucket containing card slips (masked PAN, AUTH codes, amounts) — must be private with signed URLs.
- Image uploaded before OCR → orphan files on OCR failure; delete does not remove the file.
- No delete confirmation, no audit, `created_by` never set.
- Hard-coded prices 200/300/500 in summary and export.
- Route open to all staff while data is admin-only (silent failures for moderators/passcode users).
- Per-keystroke DB writes on count inputs; free-text `seq_number`/`time`; amount never validated; no duplicate detection.
- Mixed English/Arabic UI; export date in UTC.
- Passcode accepted on the OCR endpoint with no throttling (cost and brute-force vector).

---

### GATE — Gate QR scanner & check-in
Gate check-in station: scan a ticket QR with the device camera (or search by booking reference / ticket code / phone), review the ticket(s), confirm presence (`ticket_holders.is_present = true`). Includes already-scanned alerts, related tickets of the same phone, scan history and admin-only undo. Original route `/admin/qr-scanner` + edge function `ticket-checkin`. New app: `/staff/scanner` (Livewire + Alpine, installable PWA), roles `gatekeeper`, `supervisor`, `admin`, `super_admin`, `passcode_staff`; server logic in `App\Checkin\CheckinService`.

- **F-GATE-001 · Access & in-page role detection** — Page guard = staff (admin, moderator, passcode). On mount: with a session, `isAdminUser = exists admin_users(id) OR user_roles(role='admin')` (enables undo buttons); moderators allowed without undo; without a session the page is allowed if a passcode is stored, else redirect to `/admin/login`.
- **F-GATE-002 · Scanner theme & header** — Dedicated dark luxury theme (tokens `scanner-background`, `scanner-surface`, `scanner-elevated`, `scanner-foreground`, `scanner-muted`, `scanner-gold`, `scanner-maroon`, `success`; font Lusail). Sticky blurred header: logo tile with gold border (or maroon QrCode tile) acting as a button to `/staff`; title `ماسح التذاكر الذكي` ("Smart ticket scanner"); sub-line with pulsing green dot `نظام إدارة الدخول — متصل` ("Entry management system — online"; static text, no real connectivity check).
  - *Rebuild:* drive the "online" dot from `navigator.onLine` + a 30 s server ping; show `غير متصل` when offline.
- **F-GATE-003 · Gate staff selector** — Select (w-40/52, UserRound icon) listing active `pos_users` ordered by name, items `{icon||"⭐"} {name}`; value = the NAME (not id); placeholder `اختر موظف البوابة...` ("Choose gate staff..."); empty list → `لا يوجد موظفون مفعّلون` ("No active staff"). Persisted in `localStorage['scanner_staff_name']` on every change and restored on load. Sent with every check-in as `staff_name` → stored as `confirmed_by_name` (display label).
  - *Rebuild:* store `gate_staff_id` (FK pos_users/staff profile) as well as the label; keep the label for history display.
- **F-GATE-004 · Logout** — Icon button (title `تسجيل الخروج`) → sign out and go to `/admin/login`. It does NOT revoke a stored passcode (passcode staff stay logged in).
  - *Rebuild:* logout invalidates the session for every identity type.
- **F-GATE-005 · Camera start** — Library `html5-qrcode` bound to `#qr-reader` (min-height 280px, 400px on sm+). Checks `navigator.mediaDevices.getUserMedia` exists else error `المتصفح لا يدعم الكاميرا` ("Browser does not support the camera"). `getCameras()`; none → `لم يتم العثور على كاميرا`. Camera choice: first whose lower-cased label contains `back` or `rear`, else the first camera. `start(cameraId, {fps: 10, qrbox: {width: 250, height: 250}})`; per-frame decode errors ignored. Success toast `تم تشغيل الكاميرا بنجاح` ("Camera started"). Start button (maroon, h-12) `تشغيل الكاميرا` / `جاري تشغيل الكاميرا...`.
  - *Rebuild:* same library (or `zxing-wasm`/BarcodeDetector API with html5-qrcode fallback) in an Alpine component; remember the chosen camera id; add torch toggle when supported (NEW).
- **F-GATE-006 · Camera error messages** — `NotAllowedError` → `يرجى السماح بالوصول إلى الكاميرا` ("Please allow camera access"); `NotFoundError` → `لم يتم العثور على كاميرا`; `NotReadableError` → `الكاميرا قيد الاستخدام من تطبيق آخر` ("Camera in use by another app"); else error message or `فشل تشغيل الكاميرا`. Shown inline in a red box (AlertCircle) and as a toast.
- **F-GATE-007 · Scanner card** — Title `مسح التذكرة` ("Scan ticket"), sub `ضع رمز QR داخل الإطار ليتم التحقق تلقائياً` ("Place the QR code inside the frame to verify automatically"); status pill `الكاميرا نشطة` (green pulse) / `الكاميرا متوقفة` ("Camera stopped"). Idle overlay: 4 gold corner brackets (size-10, 2px) around a ScanLine icon and `الكاميرا جاهزة للتشغيل` ("Camera ready"). While scanning: animated gold scan line (`animate-scanner-line`) and status `الكاميرا تعمل - جاهز للمسح` ("Camera running - ready to scan").
- **F-GATE-008 · Auto stop / auto restart** — On a successful decode the camera stops, a "camera mode" flag is set and the text is processed. The camera stays off while a ticket is displayed. It restarts automatically: after a successful confirm (when in camera mode), after the already-scanned alert auto-closes (4 s, F-GATE-029), and via `مسح تذكرة جديدة`. The scanner stops on page unmount.
- **F-GATE-009 · Overlapping-scan protection** — While a lookup is in flight (`processingRef`), further decodes/submits are ignored. Each new lookup clears ticket list, selection and related lists.
  - *Rebuild:* plus a 2 s same-code debounce on the client and server-side idempotency (the atomic update, F-GATE-040).
- **F-GATE-010 · Legacy QR normalisation** — `normalizeScannedCode(raw)`: trim; if it starts with `http://` or `https://` (case-insensitive) → drop `?query` and `#hash`, take the last path segment, remove a trailing `.png` (case-insensitive). Otherwise return as-is. Old printed tickets encoded the QR image URL whose file name is the ticket code (`QTR-…-TKT01.png`).
- **F-GATE-011 · Search activity log** — Before every lookup, activity `qr_search` (user type admin) with `{search_query: code, search_type: 'phone'|'ticket_code'|'booking_reference'}` is written (awaited).
  - *Rebuild:* server-side `activity()->log()` inside the lookup action (the original inserts from the browser → forgeable).
- **F-GATE-012 · Input classification** — Phone if `/^[\d\s+\-()]+$/`; else specific ticket if the code contains `-TKT`; else booking reference.
- **F-GATE-013 · Effective payment rule (client)** — `isPOS = booking_reference starts with 'POS-' OR payment_method === 'cash_pos'` → effective status `confirmed`; else `orders.payment_status`. (Mirrors the server; the booking-reference branch only checks the `POS-` prefix — inconsistent.)
  - *Rebuild:* one server-computed `admissible` flag returned with every ticket (F-GATE-039 rules); the client never derives payment state.
- **F-GATE-014 · Manual search form** — Ghost toggle `بحث يدوي` / `إخفاء البحث اليدوي` ("Manual search / Hide manual search"). Input placeholder `أدخل رقم الحجز أو رقم الهاتف` ("Enter booking number or phone"), forced to UPPERCASE on change, centred monospace; submit (gold) `بحث عن التذكرة` / `جاري البحث...`; empty → toast `الرجاء إدخال رقم الحجز`. Enter submits. Manual search clears camera mode. Processing line: spinner + `جاري المعالجة...`.
- **F-GATE-015 · Phone search** — `cleanPhone = code.replace(/[^\d+]/g,'')`; holders where `phone ILIKE %cleanPhone%` with order/customer/event joins, newest first; keep only `payment_status='confirmed'` or `payment_method='cash_pos'`. None → error card (`customer_name "غير موجود"`, `event_title "-"`, `payment_status "غير مؤكد"`) + toast `لا توجد تذاكر لهذا الرقم` ("No tickets for this number"). More than one ticket: all listed as selectable; if the FIRST ticket's effective status ≠ confirmed → error + toast `⚠️ حالة الدفع: قيد الانتظار - لا يمكن عرض التذاكر` ("Payment status: pending - cannot display tickets"); else summary `booking_reference = "{n} تذكرة"`, quantity n, toast.info `تم العثور على {n} تذكرة لهذا الرقم - اختر التذكرة المراد تأكيدها`. Exactly one: pending → same error; else auto-select if not present, fill details, present → already-scanned alert, else toast `معلومات التذكرة - جاهز للتأكيد` ("Ticket info - ready to confirm"); then related tickets (F-GATE-031/032). No date filter (tickets of all events listed). Works only with a real session (direct table query under RLS) — passcode staff get nothing.
  - *Rebuild:* server action `lookupByPhone` (min 8 digits, last-8 match, today + future events only, per-ticket admissibility), available to all gate identities.
- **F-GATE-016 · Ticket-code search (`-TKT`)** — Exact `qr_code = code`; fallback `qr_code ILIKE %code%` (first row); fallback for passcode staff: server mode `lookup` → `results[0]`. Not found → error card + toast `تذكرة غير موجودة` ("Ticket not found"). Pending → error card (type "-") + pending toast. Present → already-scanned alert. Else toast `معلومات التذكرة - جاهز للتأكيد`. No related-tickets fetch in this branch; confirm uses the single-ticket fallback.
- **F-GATE-017 · Booking-reference search** — `orders.booking_reference = code` with customer/event; not found → server `lookup` fallback (builds the order from `results[0].orders`), else error card + toast `حجز غير موجود` ("Booking not found"). Found → all holders of the order ordered by `qr_code`. Payment rule here = `POS-` prefix only. Pending → error + pending toast. Else list; exactly one not-present ticket → auto-select; quantity = count; toast `تم العثور على التذكرة - جاهز للتأكيد` (1) or info `تم العثور على {n} تذكرة - اختر التذكرة المراد تأكيدها`. Then related tickets for the first holder's phone and same-booking tickets. Generic exception → error card (`خطأ`) + toast `خطأ في التحقق من التذكرة`.
- **F-GATE-018 · Passcode-staff lookup fallback** — For identities without table access, lookups go through server mode `lookup` (F-GATE-043).
  - *Rebuild:* unnecessary as a separate path — all lookups are server actions authorised by session.
- **F-GATE-019 · Ticket selection list** — Blue block `اختر التذاكر ({available} متاحة):` ("Choose tickets (n available)"); checkbox `اختيار الكل` ("Select all") selects all NOT-present tickets and toggles off when all are selected. Row: position badge `{i}/{total in same booking}`, name, `الفعالية: {title}`, `الرقم المرجعي:` + mono ref, `الهاتف:`, `النوع:` (uppercase), status `✅ حاضر` / `⭕ غير حاضر`; present rows 50% opacity and not selectable; selected rows primary border. Bulk button `✓ تأكيد حضور {n} تذكرة`.
- **F-GATE-020 · Selecting one ticket** — When exactly one ticket is selected, the details panel is re-derived from it (quantity 1); if it is already present → toast.info `هذه التذكرة تم تسجيل حضورها مسبقاً` ("This ticket was already checked in").
- **F-GATE-021 · Result card & detail rows** — Border-2 card: green when found & paid → `✅ تم التحقق من التذكرة` ("Ticket verified"); yellow when found & unpaid → `⚠️ معلومات التذكرة (الدفع معلق)`; red otherwise → `❌ تذكرة غير صالحة` ("Invalid ticket"). Rows: `رقم الحجز`, `اسم حامل التذكرة`, `رقم الهاتف`, `الجنسية`, `رقم الهوية`, `إسم العميل`, [single-ticket button `✓ تأكيد الحضور` when no list, not present, paid], `اسم الحدث`, `نوع التذكرة` (uppercase), `الكمية`, `حالة الدفع` → `مؤكد` (green) or `معلق`/`قيد الانتظار` (orange). Already present + error: `⚠️ تم تسجيل الدخول مسبقاً`.
- **F-GATE-022 · Unpaid warning box** — Yellow: `⚠️ تحذير: الدفع غير مؤكد - لا يمكن تسجيل الدخول` / `يرجى تأكيد الدفع قبل السماح بالدخول` ("Payment not confirmed — cannot enter / confirm payment before allowing entry").
- **F-GATE-023 · Payment-pending block** — Tickets whose effective status is not confirmed cannot be displayed for confirmation nor checked in (client blocks; server refuses with `⚠️ الدفع غير مؤكد - لا يمكن تسجيل الدخول`). POS-prefixed and `cash_pos` orders are treated as paid (see defects).
- **F-GATE-024 · Review popup** — Dialog auto-opens whenever a lookup succeeds with confirmed payment (not for already-present single tickets): header `تم العثور على التذكرة` / `راجع بيانات التذكرة ثم اضغط تأكيد الحضور` ("Ticket found / review then press confirm"); rows `رقم الحجز`, `اسم حامل التذكرة`, `رقم الهاتف`, `الجنسية`, `رقم الهوية`, `اسم العميل`, `اسم الحدث`, `نوع التذكرة`, `الكمية`, `حالة الدفع` = `مدفوعة ✓`; when list > 1: `تذاكر الحجز ({n} متاحة) — اختر للتأكيد:` with checkboxes (`✓ حاضر` for present ones); confirm button `✓ تأكيد الحضور` (+ ` ({n})`), disabled when list > 1 and nothing selected.
- **F-GATE-025 · Sticky confirm bar** — Below the header (top-16, z-30, success border) when the ticket is paid AND (some selected OR not present): `{holder name || customer} — {booking_reference}` + big green `✓ تأكيد الحضور` (+ ` ({n})` when > 1 selected); loading `جاري التأكيد...`.
- **F-GATE-026 · Floating confirm button** — Fixed right-4, vertically centred, 64×64 green pulsing square with check icon and selected count; visible when ≥ 1 ticket is selected.
- **F-GATE-027 · Confirm presence (multi-ticket)** — Requires ticket info; list present but nothing selected → toast `الرجاء اختيار التذاكر المراد تأكيدها`. Tickets = selected items, else single fallback built from the holder code; none → error dialog `لم يتم تحديد أي تذكرة للتأكيد`. For EACH ticket SEQUENTIALLY: call check-in with `{booking_reference: ticket.qr_code, staff_name, passcode}` (an `admin_id` is also sent and ignored). Transport error → counted silently; `success` → counted + activity `ticket_scan` (`{ticket_holder_id, ticket_holder_name, ticket_type, qr_code, action:'confirm_presence'}`); `success:false` → error dialog with server message (last one wins) or `فشل تأكيد التذكرة`. If ≥ 1 success: success dialog, close popup, toast `✅ تم تأكيد حضور {n} تذكرة`, reload history, clear state and auto-restart camera in camera mode. If all failed: keep the UI for retry.
  - *Rebuild:* single server call `checkinMany(holder_ids[])` returning per-ticket results; error dialog lists every failure (original shows only the last; network failure shows nothing).
- **F-GATE-028 · Success dialog** — Green circle check, `تم تأكيد الحضور!` ("Attendance confirmed!"), `تم تسجيل الحضور بنجاح`, `عدد التذاكر` big number, `أنواع التذاكر:` rows `{qty}x {TYPE}` (uppercase type key), `حاملي التذاكر:` (first prefixed `👤 `) with `({TYPE})`; button `حسناً`. No success sound in the original.
- **F-GATE-029 · Already-scanned alert** — When the looked-up single ticket is already present: result = error; dialog with amber pulsing triangle, `تم مسح هذه التذكرة مسبقاً` ("This ticket was already scanned"), holder name, reference (LTR), `وقت المسح: {full date-time ar-u-nu-latn Asia/Qatar}`, `بواسطة {confirmed_by_name}`, note `الكاميرا ستكون جاهزة لمسح تذكرة جديدة خلال لحظات…`, button `مسح تذكرة جديدة`. Plays the error buzzer (F-SFX-003). If the scan came from the camera, after **4000 ms** the alert auto-closes and the camera restarts; in manual mode it stays until closed.
- **F-GATE-030 · Error dialog** — Red X, `لا يمكن تسجيل الحضور` ("Cannot check in"), server message (pre-wrap), destructive button `إغلاق`. Opening it ALWAYS plays the error buzzer. Server duplicate refusals (race or other device) arrive here as `تم استخدام التذكرة مسبقاً`.
- **F-GATE-031 · Related tickets — same phone, same day** — `fetchRelatedTickets(phone, eventDate, excludeIds)`: phone stripped of spaces/dashes/parens; holders `phone ILIKE %clean%`, excluding current ids, paid or `cash_pos`; those whose event date equals the current ticket's date → emerald block `تذاكر أخرى لنفس رقم الهاتف - نفس اليوم ({n})` (name, `الفعالية:`, `النوع:`, ref, status) — display only, not selectable.
  - *Rebuild:* make same-day related tickets selectable for one-tap family check-in (NEW); compare dates in Qatar time (original uses browser timezone).
- **F-GATE-032 · Related tickets — other days** — Amber block `تذاكر أخرى لنفس رقم الهاتف - أيام أخرى ({n})` with note `⚠️ هذه التذاكر ليوم مختلف - لا يمكن تسجيل الحضور اليوم`; grouped by date ascending, collapsible pills `📅 {dd MMMM yyyy}` (English month names in original — use Arabic) or `تاريخ غير محدد`, count badge `{n} تذكرة`, gradient separators, rows at 80% opacity.
- **F-GATE-033 · Same-booking tickets** — Purple block `تذاكر أخرى بنفس الرقم المرجعي ({n})` (name, `النوع:`, status), fed by `fetchSameBookingTickets` (holders of the booking not already listed — in practice always empty).
  - *Rebuild:* drop the separate query; the booking list already contains them.
- **F-GATE-034 · New-scan reset** — Button `مسح تذكرة جديدة` (default variant on success, outline otherwise; `جاري التحميل...` while the camera starts) clears all state and starts the camera.
- **F-GATE-035 · Scan history** — Card `آخر عمليات الدخول` ("Latest entries"), sub `{n} تذكرة مسجلة`, button `تحديث`; empty `لا يوجد سجل بعد`. Source: server mode `history` (last 30, all events). List (max-h 32rem): numbered square (gold if VIP else maroon), name, `{ref} · {TYPE} · {event title}`, timestamp `ar-u-nu-latn` Asia/Qatar dateStyle short + timeStyle short, `بواسطة {confirmed_by_name}` (gold). Reloaded after each confirm and on mount.
  - *Rebuild:* scope to the current gate event by default with an "all" toggle; live-update via broadcast `TicketCheckedIn`.
- **F-GATE-036 · Admin undo scan (client)** — Admin-only red button `إلغاء المسح` (RotateCcw; title `إلغاء المسح (للأدمن فقط)`) per history row → `confirm("إلغاء مسح تذكرة {name}؟ ستعود التذكرة صالحة من جديد.")` → server mode `reset {holder_id}` (no passcode sent). Failure toast `data.message || "تعذر إلغاء المسح — هذه العملية للأدمن فقط"`; success toast message or `تم إلغاء المسح`, row removed; exception toast `تعذر إلغاء المسح`.
  - *Rebuild:* modal confirmation with mandatory reason (stored in audit log).
- **F-GATE-037 · Server identity & staff label** — All modes require staff identity (admin/moderator session or team passcode) else `401 {success:false, error:"unauthorized", message:"غير مصرح"}`. `actorId` = signed-in user id (null for passcode) — the admitter is never taken from the request (`admin_id` ignored). `staffLabel` = `staff_name` whitespace-collapsed, trimmed, max 80 chars, or null.
- **F-GATE-038 · Server time rules** — Qatar time. `isEventDateToday` = event's Qatar calendar date equals today's. `isEventExpired` = event instant < now AND (same Qatar day and current Qatar hour ≥ **18**, or an earlier day). `qatarDayStartUtc` = today 00:00 Qatar as UTC. Messages format dates `ar-u-nu-latn` `{year numeric, month long, day numeric}`.
  - *Rebuild:* cutoff per event (`checkin_closes_at`, default 18:00 Qatar) — the hard-coded 18:00 makes an evening event (e.g. starting 19:00) impossible to check in on its own day.
- **F-GATE-039 · Check-in by code (default mode) — rule order** — Input `booking_reference` = scanned ticket code; empty → `400 {success:false, error:'Booking reference is required', message:'رقم الحجز مطلوب'}`. Holder lookup: exact `qr_code` with inner joins and event_date ≥ Qatar midnight today (past events invisible). Found → `ticket_info` `{booking_reference, customer_name ('غير معروف'), event_title, ticket_type, ticket_holder_name, ticket_holder_phone, ticket_holder_nationality, ticket_holder_id_number, quantity:1, payment_status, is_present}` and checks in order: (1) expired → `200 {success:false, error:'Event expired', message:'⏰ انتهت صلاحية التذكرة - الحدث انتهى في {date}'}`; (2) not today → `200 {error:'Wrong day', message:'📅 هذه التذكرة ليست لليوم — صالحة في {date} فقط'}`; (3) already present → `200 {error:'Already checked in', message:'تم استخدام التذكرة مسبقاً', ticket_info{…, is_present:true, confirmed_at}}`; (4) payment: effective status (`POS-` prefix or `cash_pos` → confirmed) ≠ confirmed → `200 {error:'Payment not confirmed', message:'⚠️ الدفع غير مؤكد - لا يمكن تسجيل الدخول'}`; (5) atomic admit (F-GATE-040); (6) automation marker (F-GATE-047); (7) `200 {success:true, message:'✅ تم التحقق من تذكرة {name}', ticket_info{…, is_present:true, confirmed_at}}`. Uncaught → `500 {success:false, message:'خطأ في التحقق من التذكرة'}`.
  - *Rebuild order (mandatory):* exists → code not in `expired_qr_codes` (reject `⛔ هذا الرمز لم يعد صالحاً — تم تغيير موعد الحجز`) → order not cancelled/refunded (applies to POS and cash too) → event expired (configurable cutoff) → event is today → event matches the gate's current event(s) (NEW, optional) → already present → paid (`confirmed`; web `cash_pos` pending only if "collect at gate" is enabled, with a "collect cash" step) → atomic update → automation marker → broadcast.
- **F-GATE-040 · Atomic, race-safe admission** — `UPDATE ticket_holders SET is_present=true, confirmed_at=now(), confirmed_by=actorId?, confirmed_by_name=staffLabel? WHERE id=? AND is_present=false`; exactly one concurrent scanner wins; 0 rows → `Already checked in` (`تم استخدام التذكرة مسبقاً`); DB error → 500.
  - *Rebuild:* `TicketHolder::whereKey($id)->where('is_present',false)->update([...])` and check the affected-row count; keep this test-pinned.
- **F-GATE-041 · Legacy order-level check-in** — When no holder matches: `orders.booking_reference = code` with event_date ≥ today start; none → `404 {error:'Ticket not found', message:'تذكرة غير موجودة'}`; expired → 200 expired message; not today → `200 {error:'Wrong date', message:'📅 لا يمكن تسجيل الدخول - التذكرة صالحة فقط في {date}'}`; unpaid → **400** `{error:'Payment not confirmed', message:'الدفع غير مؤكد'}` (checked before presence here); `orders.is_present` → 200 already; else conditional update `orders.is_present/confirmed_at/confirmed_by` (no staff label) → `✅ تم التحقق من التذكرة بنجاح` with `quantity = order.quantity`.
  - *Rebuild:* migrate legacy holder-less orders into holder rows during data migration and drop this path; never reuse `orders.confirmed_at/confirmed_by` (payment fields) for gate admission.
- **F-GATE-042 · Server mode `history`** — Last **30** holders `is_present=true AND confirmed_at not null` ordered `confirmed_at desc` (all events): `id, name, ticket_type, qr_code, confirmed_at, confirmed_by_name, orders(booking_reference, events(title))` → `{success:true, message:'ok', history}`; error → `200 {success:false, message:'تعذر جلب سجل المسح', history:[]}`.
- **F-GATE-043 · Server mode `lookup` (read-only)** — `code` trimmed; empty → `{success:false, message:'رقم التذكرة مطلوب', results:[]}`. Tries (limit 20 each, first non-empty wins): exact `qr_code`; `qr_code ILIKE %code%` (code without `% , ( )`); `orders.booking_reference = code`. Not date-restricted. Items `{id, name, phone, nationality, id_number, ticket_type, qr_code, is_present, confirmed_at, confirmed_by_name, order_id, orders:{booking_reference, payment_status, payment_method, customers:{name||'غير معروف'}, events:{title, event_date}}}`.
  - *Rebuild:* third query must use a proper join (original filter on an embedded column may return unrelated rows with `orders: null`).
- **F-GATE-044 · Server mode `search`** — `value` trimmed; < 2 chars → `{success:true, results:[]}`. `like = %value%` (strip `% , ( )`). Only events with `event_date ≥ today 00:00 Qatar`. Three queries (limit 40 each, newest first) merged by holder id: holder `name|phone|id_number ILIKE`; `orders.booking_reference ILIKE`; holders of orders whose customer `name|phone|email ILIKE` (pre-query up to 60 orders). Items `{holder_id, name, phone: country_code+phone, id_number, ticket_type, is_present, confirmed_at, confirmed_by_name, booking_reference, payment_status, payment_method, customer_name||'غير معروف', customer_phone, customer_email, event_title, event_date, is_today}`.
- **F-GATE-045 · Server mode `manual`** — Admit a holder by id (used by POS manual check-in): no id → `التذكرة غير محددة`; not found → `تذكرة غير موجودة`; expired → `⏰ انتهت صلاحية التذكرة - الحدث انتهى في {date}`; not today → `📅 هذه التذكرة ليست لليوم — صالحة في {date} فقط`; already present → `{success:false, message:'تم تسجيل حضور هذه التذكرة مسبقاً', already:true}`; payment (POS/cash rule) → `⚠️ الدفع غير مؤكد - لا يمكن تسجيل الحضور`; atomic update (DB error → `تعذر تسجيل الحضور، حاول مرة أخرى`; 0 rows → already + `already:true`); automation marker; → `{success:true, message:'✅ تم تسجيل حضور {name}', confirmed_at}`. All responses HTTP 200.
- **F-GATE-046 · Server mode `reset` (undo)** — Admin ACCOUNT only (Bearer re-checked: admin_users or role admin; moderators and passcode refused): no token → `401 {success:false, message:'إلغاء المسح متاح للأدمن فقط'}`, not admin → 403 same message; no `holder_id` → `معرّف التذكرة مطلوب`; `UPDATE … SET is_present=false, confirmed_at=null, confirmed_by=null, confirmed_by_name=null WHERE id AND is_present=true`; error → `تعذر إلغاء المسح`; 0 rows → `التذكرة غير ممسوحة أصلاً` ("Ticket was not scanned"); insert activity `ticket_checkin_reset` `{holder_id, qr_code, holder_name}` → `{success:true, message:'تم إلغاء المسح، التذكرة صالحة من جديد'}`.
  - *Rebuild:* policy `checkin.reset` (admin, super_admin); the original audit insert fails silently because the `activity_logs` CHECK constraint does not allow `ticket_checkin_reset` — the rebuild must persist it.
- **F-GATE-047 · WhatsApp/automation marker** — On every successful admission (code, manual, legacy), if the order's `n8n_responded_at` is null → set `n8n_response_message = 'تم التسجيل يدوياً ✓'` ("Registered manually ✓") and `n8n_responded_at = now()` — marks the WhatsApp/automation column as handled because the customer is already at the gate.
  - *Rebuild:* separate column `automation_status` (`pending|sent|failed|skipped_checked_in`) instead of overloading the n8n response text.
- **F-GATE-048 · Error code catalogue** — HTTP 200 `success:false` errors: `Event expired`, `Wrong day`, `Wrong date` (legacy), `Already checked in`, `Payment not confirmed` (holder path); HTTP 400 `Booking reference is required`, `Payment not confirmed` (legacy path); 401 `unauthorized`; 403 reset non-admin; 404 `Ticket not found`; 500 `خطأ في التحقق من التذكرة`. The emojis are part of the messages.
  - *Rebuild:* JSON `{ok, code, message, ticket}` with stable codes `not_found, code_revoked, order_cancelled, event_expired, wrong_day, wrong_event, already_checked_in, payment_pending, unauthorized`, HTTP 200 for business refusals.
- **F-GATE-049 · Activity logging** — Client writes `qr_search` per lookup and `ticket_scan` per confirmed ticket (user identifier = user id); server writes `ticket_checkin_reset` on undo.
  - *Rebuild:* all audit server-side via spatie/laravel-activitylog with causer = user/kiosk session, properties = holder id, code, gate staff, device id.
- **F-GATE-050 · Admission policy facts** — One admission per ticket for life (no re-entry); only an admin reset re-validates. No per-ticket-type scan limits (parking admitted like any ticket). No "current gate event": any event happening today is accepted at any gate. No offline mode, no local queue, no IndexedDB cache, no re-entry mode; the unconfirm mode is dead code (`scanMode` hard-coded `'confirm'`). Every confirm needs network.

**Upgrades (new, not in original):**
- NEW: Offline-first PWA scanner — service worker + IndexedDB cache of today's admissible ticket codes (hashed) per gate event, local duplicate detection, an outbound sync queue of check-ins with device id and client timestamp, replay with server-side idempotency (`checkin_uuid`), conflict report ("scanned on another device at …"), clear offline banner and queue counter.
- NEW: Gate assignment — each device/session selects the current gate event(s); tickets for other same-day events are refused with `wrong_event`.
- NEW: Re-entry mode (exit/re-enter with counter) as an optional per-event setting.
- NEW: Signed QR payload (HMAC of the code) so forged codes from a guessed reference are rejected before DB lookup.
- NEW: Success beep and haptic vibration on admit; distinct sounds for already-scanned vs invalid.
- NEW: Hardware scanner (keyboard-wedge) input mode and continuous-scan mode without the review popup for high-throughput gates.
- NEW: One-tap family check-in of same-phone same-day tickets.

**Original defects to avoid:**
- **Critical:** POS-prefixed and any `cash_pos` order are always treated as paid — even when `payment_status='cancelled'` (refunded/returned via `cancel_order`) → refunded POS tickets still get in. Web "pay at venue" pending orders are admitted without collecting payment.
- `expired_qr_codes` (codes invalidated by `change-order-event`) is never checked (`isQRCodeExpired` defined but never called).
- Team passcode accepted in the body of `ticket-checkin` (and gate-stats, staff-attendance, staff-customer-lookup, create-order, parse-pos-receipt) with **no throttling** — only `staff-auth` throttles → brute-force vector. Passcode staff can search and read national IDs and phones.
- Missing i18n keys: `validationError`, `processing`, `alreadyCheckedIn` do not exist; i18next returns the key (truthy) so the `|| 'Arabic fallback'` never applies → the UI literally shows `validationError` / `processing` / `alreadyCheckedIn`. Hard-code/define the Arabic strings.
- Direct browser queries on `ticket_holders`/`orders` relying on RLS; phone search has no path for passcode staff; phone search lists all dates and pending cash orders with substring match (3 digits match many).
- Inconsistent POS rule between branches (`POS-` prefix vs `cash_pos`).
- Related-ticket date split in browser timezone; English month names in Arabic UI; static `متصل` indicator.
- Error dialog shows only the last failure; network failures on confirm show nothing.
- Heavy `console.log` debugging including PII.
- Logout keeps the passcode.
- History and lookup not scoped to an event; hard-coded 18:00 cutoff.
- `ticket_checkin_reset` audit rows rejected by the `activity_logs` CHECK constraint (lost audit trail).
- Gate board counts only confirmed orders while the gate admits pending cash orders → scanned can exceed issued.

---

### LIVE — Live bookings dashboard & live visitors
Big-screen boards. **Live bookings** (original `/live-bookings`, staff guard): confirmed bookings of one event day with KPIs, online-vs-POS split, breakdowns, holder cards/table, realtime toasts and sounds, daily sales dialog. **Live visitors** (original `/live-visitors`, admin-only guard; moderators redirected to `/staff`, passcode holders pass the guard but get no data): website presence + historical analytics. New app: `/staff/live-bookings` (Livewire + Echo/Reverb, roles supervisor+, passcode_staff read-only) and Filament page `/admin/visitors` (admin only).

- **F-LIVE-001 · Access** — Live bookings: any staff identity. Live visitors: admins only by route; data (RLS on `active_visitors`) admin-only.
  - *Rebuild:* `live.view` permission; lifetime totals (F-LIVE-017) must work for every identity allowed on the page (original RPC is admin-only → badge silently hidden for staff).
- **F-LIVE-002 · Header** — Sticky header with background `public_settings.header_bg_color` (default `hsl(var(--card) / 0.5)`; same colour used as the "recent" glow); logo (h-8/12) or text `الحجوزات المباشرة` ("Live bookings"). Buttons: view toggle (F-LIVE-012), `إحصائيات` (mobile `📊`, BarChart3, title `إحصائيات المبيعات اليومية`) → F-LIVE-028, check-in notifications Bell/BellOff (blue when on; titles `إيقاف إشعارات الحضور` / `تفعيل إشعارات الحضور`), sound Volume2/VolumeX (green when on; `إيقاف الصوت` / `تفعيل الصوت`), fullscreen (F-LIVE-030).
- **F-LIVE-003 · Default date** — First active event (order event_date asc) still sellable (`canPurchaseTickets`: not past 18:00 Qatar of its day) → its Qatar date; else today (Qatar); on error today.
- **F-LIVE-004 · Date card & "show all"** — First KPI tile: popover button showing `dd/MM` (Qatar) or `تاريخ`, single-date calendar; ghost button `عرض الكل` ("Show all") clears the date → all confirmed orders of all time.
  - *Rebuild:* "all" must be paginated/aggregated server-side (original downloads every confirmed order ever).
- **F-LIVE-005 · Bookings query** — Paged by 1000 rows: `orders` with `customers(name,email,phone,id_number,nationality)`, `events!inner(title,event_date)`, `ticket_holders(*)`, `pos_users(name,icon)` where `payment_status='confirmed'` order `created_at desc`; when a date is selected: `event_date >= 'YYYY-MM-DD' AND < 'YYYY-MM-DD+1'` (bare strings = UTC midnight, wrong for events 00:00–03:00 Qatar).
  - *Rebuild:* filter by Qatar day boundaries converted to UTC.
- **F-LIVE-006 · Holder enrichment** — Holders flattened from orders and enriched with `booking_reference, payment_method, n8n_responded_at, event_title, created_at (order's), pos_user_name, pos_user_icon`.
- **F-LIVE-007 · KPI tiles** — Grid 2/3/6 columns: (1) date card; (2) `إجمالي الحجوزات` ("Total bookings", blue, Users) = orders count; (3) `الحجوزات المؤكدة` ("Confirmed bookings", green) = orders with status confirmed (always equal to total); (4) `إجمالي التذاكر` (purple) = holders count; (5) `الحاضرون` ("Present", orange) = present holders; (6) `آخر ساعة` ("Last hour", cyan, Clock) = orders created in the last 60 min.
  - *Rebuild:* replace the duplicate "confirmed" tile with revenue (Σ `total_amount`) or "not yet entered".
- **F-LIVE-008 · Stats algorithm** — Per type `{total, present}` from holders; payment split: `sadad` tickets = Σ quantity of sadad orders, `pos` = Σ quantity of `cash_pos` orders (includes web pay-at-venue orders once confirmed); per payment & type `{total, present}` (key 'sadad' else 'pos'); nationality per holder (default `غير محدد`): `online` (sadad) / `pos` (other) / `total` / `present`.
- **F-LIVE-009 · Online vs POS split** — Two cards: Online (blue, Globe) badge `أونلاين`, big number, `إجمالي تذاكر سداد` ("Total Sadad tickets"), per-type rows `{type key}` `{total} حجز` `{present} حضور`; POS (orange, Store) badge `نقاط البيع`, `إجمالي تذاكر POS`, same rows. Rows sorted by total desc; type keys shown raw (`vip`/`normal`/`parking`).
  - *Rebuild:* show Arabic type labels; split "POS cash" vs "web pay-at-venue" (NEW).
- **F-LIVE-010 · Ticket-type breakdown** — Section `التذاكر حسب النوع`: card per type with UPPERCASE badge, `{total} / {capacity}` (capacity = `tickets.available_quantity` of the event of the first order, else the event found by date), `إجمالي`, `{present}` green `حاضر`.
- **F-LIVE-011 · Nationality breakdown & flags** — `التذاكر حسب الجنسية`: scrolling list (max-h 60/80): flag + name, Globe `{online}` | Store `{pos}` | CheckCircle `{present}` + total badge, sorted by total desc. Flag map: قطر🇶🇦 السعودية🇸🇦 الإمارات🇦🇪 الكويت🇰🇼 البحرين🇧🇭 عمان🇴🇲 مصر🇪🇬 الأردن🇯🇴 لبنان🇱🇧 سوريا🇸🇾 العراق🇮🇶 اليمن🇾🇪 المغرب🇲🇦 الجزائر🇩🇿 تونس🇹🇳 ليبيا🇱🇾 السودان🇸🇩 فلسطين🇵🇸 باكستان🇵🇰 الهند🇮🇳 بنغلاديش🇧🇩 الفلبين🇵🇭 إندونيسيا🇮🇩 نيبال🇳🇵 أمريكا🇺🇸 بريطانيا🇬🇧 فرنسا🇫🇷 ألمانيا🇩🇪 إيطاليا🇮🇹 أسبانيا🇪🇸; unknown → 🌍.
- **F-LIVE-012 · View toggle** — `عرض البطاقات` (mobile `بطاقات`, LayoutGrid) / `عرض الجدول` (mobile `جدول`, Table).
  - *Rebuild:* persist view, sound and notification toggles in localStorage (original resets on reload).
- **F-LIVE-013 · Holder cards view** — Grid 1/2/3/4/6 columns; each card: red index badge top-right (F-LIVE-014), event title chip, name + flag, status icon column (F-LIVE-015), phone, booking ref (mono) + copy (F-LIVE-016), lifetime box (F-LIVE-017), type badge `VIP`/`عادي`/`مواقف`, attendance `حاضر ✓` (green) / `غير حاضر`, `تم التأكيد: dd/MM/yyyy - HH:mm`, footer `تم الحجز في: dd/MM/yyyy - HH:mm` (browser timezone in original → Qatar in rebuild). Empty: `لا توجد حجوزات لهذا التاريخ`.
- **F-LIVE-014 · Daily sequential number** — Index badge = `length − index` (newest holder has the highest number).
- **F-LIVE-015 · Status icons** — POS order → Store icon + POS user name (title `POS - {name}`); else `n8n_responded_at` set → green Send (title `تم إرسال الفاتورة` "Invoice sent") or dashed circle (title `لم يتم إرسال الفاتورة` "Invoice not sent"); additionally blue Download icon (title `تم تحميل الفاتورة` "Invoice downloaded") when the reference appears in `activity_logs` (`activity_type='pos_form'`, `action_data.action='invoice_download'`; only the first 500 log rows are read, unordered → misses later ones).
  - *Rebuild:* store `invoice_downloaded_at` on orders instead of scanning logs.
- **F-LIVE-016 · Copy booking reference** — Copy button (title `نسخ`) → clipboard, toast `تم نسخ رقم الحجز`.
- **F-LIVE-017 · Lifetime ticket total per person** — RPC `get_lifetime_ticket_totals()` → `(person_key, total)` over confirmed holders of ALL events (incl. archive), keys `id:<digits>` (ID ≥ 6 digits) and `ph:<last 8>` (phone ≥ 8 digits); `getTotal(phone,id)` = max of the person's keys. Card: box `إجمالي التذاكر` + number, amber highlight when > 1, title `إجمالي التذاكر لهذا الشخص في كل الفعاليات (بما فيها الأرشيف)`, hidden when 0. Refreshed 1500 ms after holder count changes. Admin-only RPC (`Not authorized` otherwise).
  - *Rebuild:* cached aggregate table `person_ticket_totals` updated by listener; one person key (ID preferred, else phone) to avoid double counting.
- **F-LIVE-018 · Table view** — Columns: `الاسم` (+ flag on mobile), `الهاتف` (sm+), `الجنسية` (md+; name lg+), `الرقم المرجعي` (lg+, copy + downloaded icon), `إجمالي التذاكر` (lifetime badge, shown even when 0), `نوع التذكرة`, `الحضور`, `وقت التأكيد` (xl+). Present rows green tint; new rows glow.
- **F-LIVE-019 · Recent-booking pulse** — Holders created within the last **5 min** pulse (`animate-recent-pulse`: scale 1→1.02, 2 s infinite, ring in header colour, background colour-mix 15%); a 30,000 ms tick re-renders to expire highlights.
- **F-LIVE-020 · New-booking glow** — Newly inserted holders glow for 5000 ms (`animate-new-booking`: green glow 1.5 s × 3).
- **F-LIVE-021 · Realtime: orders** — Single channel `live-bookings-unified`: orders INSERT/UPDATE with status confirmed → fetch that order with relations; ignore if its event date ≠ selected date; upsert order + replace holders; on INSERT (after initial load) glow + new-booking notification; UPDATE to cancelled → remove order + holders; DELETE → remove. Web orders are inserted pending and confirmed via UPDATE → no "new booking" toast/sound for online sales (only POS triggers it).
  - *Rebuild:* broadcast `OrderConfirmed` (from POS and from payment confirmation alike) and `OrderCancelled` on `event.{id}.live`; payload includes everything the card needs (no per-event refetch).
- **F-LIVE-022 · Realtime: holders & check-ins** — Holder UPDATE → patch in place; `is_present` false→true (needs old values; REPLICA IDENTITY FULL) → check-in toast + sound; DELETE → remove; INSERT → debounced full refetch (1500 ms).
- **F-LIVE-023 · Realtime: capacity** — `tickets` UPDATE → update capacity; increase vs remembered value → capacity sound (F-SFX-007) + banner (same banner as F-POS-039, auto-dismiss 4000 ms here).
- **F-LIVE-024 · POS booking toast** — Custom orange-gradient toast, top-right, 6000 ms: `تسجيل من نقطة البيع` (Store icon) / `{flag} {customer name||'عميل'} ({nationality||'غير محدد'})` / `🎫 {qty} {qty==1?'تذكرة': qty==2?'تذكرتين': qty<=10?'تذاكر':'تذكرة'}` / `{pos icon||'👤'} بواسطة: {pos user||'موظف'}`; sound = cash register (F-SFX-005).
- **F-LIVE-025 · Online booking toast** — 5000 ms, pre-line RTL: `{flag} حجز جديد من {nationality}` newline `🎫 {qty} تذكرة ليوم {event date ar-u-nu-latn day numeric month long}`; sound = chime (F-SFX-004). (Never fires in the original — F-LIVE-021.)
- **F-LIVE-026 · Check-in toast** — 5000 ms, coloured by type (padding 16×20, radius 12, min-width 280): VIP gradient `#fbbf24→#f59e0b`, border `#d97706`, text `#78350f`, icon 👑; normal `#22c55e→#16a34a`, border `#15803d`, white, 🎫; parking `#3b82f6→#2563eb`, border `#1d4ed8`, white, 🅿️; other grey `#6b7280→#4b5563`, 🎫. Lines: `{name} {flag 24px}`, `تذكرة {VIP|عادي|مواقف} {icon}`, `✅ تم تسجيل الحضور`. Sound per type (F-SFX-006).
- **F-LIVE-027 · Sound & notification toggles** — Sound off suppresses all sounds; check-in notifications off suppresses check-in toasts. In-memory only.
- **F-LIVE-028 · Daily sales — data** — On dialog open and on holder changes while open: all holders of confirmed orders (paged 1000) + all `tickets(event_id,type,price)`; grouped by `event_date.substring(0,10)` (UTC string); per type count and Σ CURRENT ticket price (not the paid amount); ascending; hard-coded lower bound `date ≥ '2026-01-12'` (season start).
  - *Rebuild:* aggregate in SQL by Qatar date from stored order line prices; lower bound = active season start (setting).
- **F-LIVE-029 · Daily sales — table** — Dialog (max-w-5xl, 90vh scroll, RTL) `إحصائيات المبيعات اليومية`: table min-width 700, header `التاريخ` | `VIP` (colspan 3) | `عادي` (3) | `مواقف` (3) | `الإجمالي`; sub-header `العدد`, `السعر`, `المبلغ` per type. Row label = Arabic weekday + day + Arabic month (`الأحد…السبت`, `يناير…ديسمبر`). Cells: count or `-`, price `toFixed(0)` or `-`, amount (red) or `-`, daily total `{n} ر.ق`. Grand total row `الإجمالي الكلي` (Σ counts, price of FIRST day, Σ amounts, grand `{n} ر.ق`). Empty: `لا توجد بيانات مبيعات`.
  - *Rebuild:* add Excel export of this table (NEW).
- **F-LIVE-030 · Fullscreen** — Maximize/Minimize (`ملء الشاشة` / `تصغير الشاشة`), failure toast `لا يمكن تفعيل وضع ملء الشاشة`.
- **F-LIVE-031 · Loading, errors, footer** — Full-screen `جاري التحميل...` (text-2xl); load failure toast `فشل التحميل`; site footer component at the bottom. No print, export, search box or keyboard shortcuts. Dead: `togglePresence` (order-level presence flip with toasts `تم التحديد كحاضر` / `تم التحديد كغائب`) not wired.
- **F-LIVE-032 · Live visitors — header & tabs** — Sticky header: pinging green Activity icon, H1 `تحليلات الزوار` ("Visitor analytics") + pulsing `LIVE` badge, `آخر تحديث: {hh:mm:ss a, Arabic}`, button `تحديث` (spins while loading). Tabs `الزوار المباشرون` (Activity) | `التقارير التفصيلية` (FileBarChart).
- **F-LIVE-033 · Presence data & tracker** — `active_visitors` (one row per browser session): `session_id` unique, `ip_address, country, country_code, city, current_page ('/'), referrer, traffic_source ('direct'), device_type ('desktop'), browser, os, is_new_visitor (true), first_seen_at, last_seen_at, user_agent`; `page_views` (`session_id, page_path, country, country_code, city, device_type, browser, os, traffic_source, is_new_visitor, viewed_at, ip_address`). Public-page tracker heartbeat 300,000 ms; excluded path prefixes `/admin`, `/admin-login`, `/qr-scanner`, `/live-visitors`, `/live-bookings`, `/admin-pos` (`/staff…` pages ARE tracked); row deleted on unload.
  - *Rebuild:* server-side upsert by session id (POST `/t/heartbeat`, throttled), heartbeat 60 s, "active" = last_seen within 2–5 min, exclude all staff/admin routes.
- **F-LIVE-034 · Live counters** — 6 stat cards: `إجمالي الزوار` (total), `زوار جدد` (green; sub `{pct}%` of total), `زوار عائدون` (blue), `سطح المكتب` (purple), `الجوال` (orange), `دول مختلفة` (cyan, number of countries). Tablet has no card.
- **F-LIVE-035 · Live breakdown cards** — `الدول` (flag from regional-indicator code points (127397 + char) or 🌍 for null/`XX`, name, bar = count/total, badge; max-h-64); `مصادر الزيارة` (dot colours: direct blue-500, google red-500, facebook indigo-500, twitter sky-500, instagram pink-500, whatsapp green-500, telegram cyan-500, youtube red-600, referral purple-500, internal gray-500, else muted; bar; badge); `الصفحات النشطة` (mono path truncated 180px, count badge, % badge); `الأجهزة` progress bars `سطح المكتب` (purple), `الجوال` (orange), `التابلت` (cyan) with `value (x.x%)`; `المتصفحات`, `أنظمة التشغيل`, `المدن` lists with count badges (max-h-40). Empty `لا توجد بيانات`.
- **F-LIVE-036 · Live visitors table** — `تفاصيل الزوار ({n})`: `الدولة` (flag + name or `غير معروف`), `المدينة`, `IP` (mono), `الجهاز` (icon + type), `المتصفح`, `النظام`, `الصفحة` (truncate 150px), `المصدر` (coloured outline badge, default `direct`), `النوع` (`جديد` green / `عائد`), `آخر نشاط` (Clock + Arabic relative time with suffix). Empty `لا يوجد زوار نشطون حاليًا`.
  - *Rebuild:* mask IPs (last octet) unless super_admin.
- **F-LIVE-037 · Live refresh & cleanup** — Realtime channel `live_visitors_changes` (any change → full refetch + cleanup call) plus polling every 15,000 ms. Cleanup RPC `cleanup_stale_visitors()` deletes rows with `last_seen_at < now() − 5 min`, but EXECUTE was revoked from authenticated users → the call fails silently; 5-min cleanup equals the 5-min heartbeat → visitors flicker.
  - *Rebuild:* scheduler `visitors:prune` every minute; page polls every 15 s (`wire:poll.15s`), no per-change refetch.
- **F-LIVE-038 · Analytics date range** — Label `الفترة الزمنية:`; popover button `yyyy/MM/dd - yyyy/MM/dd` + ChevronDown; 2-month range calendar (applies when both ends picked); presets `آخر 7 أيام`, `آخر 30 يوم`, `آخر 90 يوم`; button `تحديث`. Default last 7 days; sent as local startOfDay(from)…endOfDay(to).
- **F-LIVE-039 · Analytics totals** — 4 cards: `إجمالي الزيارات` (Σ total visits), `الزوار الفريدين` (Σ daily unique — double counts multi-day sessions), `الصفحات المزارة` (page rows), `الدول` (country rows).
- **F-LIVE-040 · Visitors-over-time chart** — Area chart `الزوار حسب التاريخ` (height 300, ascending dates `MM/dd`): `إجمالي الزيارات` (#3b82f6, gradient 0.3→0) and `الزوار الفريدين` (#10b981); dashed grid. Empty: `جاري التحميل...` / `لا توجد بيانات للفترة المحددة`.
- **F-LIVE-041 · Top pages & countries** — `الصفحات الأكثر زيارة`: top 20 `#{i}` path, views, `{unique} فريد`, bar relative to #1. `الزوار حسب الدولة`: top 20 flag, name, total, `{unique} فريد`, cyan bar.
- **F-LIVE-042 · Daily visits table** — `تفاصيل الزيارات اليومية`: `التاريخ` (`yyyy/MM/dd EEEE` Arabic), `إجمالي الزيارات`, `الزوار الفريدين`, `زوار جدد` (green badge), `زوار عائدون` (blue badge); empty with calendar icon.
- **F-LIVE-043 · Analytics queries** — `get_page_view_stats(start,end)` → `page_path, view_count, unique_visitors` desc; `get_visitors_per_date(start,end)` → `visit_date (UTC DATE), total_visitors (= page views), unique_visitors, new_visitors, returning_visitors` desc; `get_visitors_per_country(start,end)` → `country||'Unknown', country_code||'XX', total, unique` desc. SECURITY DEFINER with no admin check (any signed-in user).
  - *Rebuild:* Eloquent aggregate queries behind admin policy, grouped by Qatar date; unique visitors over the whole range computed with `COUNT(DISTINCT session_id)`.

**Upgrades (new, not in original):** NEW search/filter box (name, phone, ref) on live bookings; NEW Excel/PDF export of the day's holders and daily sales; NEW revenue KPI and "remaining capacity" tiles; NEW TV kiosk mode (auto-rotate sections, larger fonts); NEW GA4-compatible event export for visitors.

**Original defects to avoid:**
- Online (Sadad) bookings never trigger the new-booking toast/sound (inserted pending, confirmed by UPDATE).
- UTC date filtering/grouping; card times in browser timezone; visitors-per-date grouped by UTC.
- Revenue from current price list, grand-total price = first day's price; hard-coded `2026-01-12` season start.
- Duplicate "confirmed" KPI; lifetime totals admin-only on a staff page; double keying of persons.
- New `AudioContext` per sound (browsers cap ~6 → sounds stop).
- Unfiltered realtime on all orders/holders with per-order refetch; "show all" loads every confirmed order.
- Invoice-download marker reads only 500 unordered log rows.
- Visitors: IP addresses shown, broken stale cleanup, realtime + polling double refetch, staff pages tracked, analytics RPCs without admin check, anonymous presence rows writable/deletable by anyone.

---

### STF — Staff access, staff hub, gate board, team attendance
Entry point and support pages for venue staff. Original routes `/staff` (hub), `/staff/gate` (gate board + `gate-stats`), `/staff/attendance` (attendance + `staff-attendance`), all behind the staff guard. New app: same paths, Livewire, RTL.

- **F-STF-001 · Staff route guard** — Order: (1) stored, unexpired passcode → allowed (ignores `adminOnly`, so passcode holders can open admin-only routes such as `/live-visitors`); (2) URL-hash handoff accepted → allowed; (3) no session → denied; (4) moderator without admin: admin-only route → redirect `/staff`, else allowed; (5) admin role → allowed; (6) row in `admin_users` → allowed; else denied. Re-runs on path change and auth state change; spinner while checking.
  - *Rebuild:* middleware + spatie permissions; `passcode_staff` gets only staff-scope permissions (never admin-only routes).
- **F-STF-002 · Unified login card** — Denied state: full-screen RTL page with a card offering e-mail/password sign-in or team passcode; on success returns to the intended path.
- **F-STF-003 · Team passcode verification & throttling** — `staff-auth` POST `{passcode}` (always HTTP 200 except 405; OPTIONS 204): secret trimmed, constant-time exact case-sensitive compare. Per caller `passcode:<ip>` (IP from `cf-connecting-ip`, else first `x-forwarded-for`, ≤ 64 chars, else unknown): **5 wrong in 15 min → blocked until 30 min after the last failure**; global `team-passcode`: **60 wrong in 15 min → 15 min block** (failures only). Responses: ok `{ok:true}`; wrong `{ok:false, attempts_left}` (+ `blocked:true, retry_after_seconds:1800` on the last allowed attempt); blocked `{ok:false, blocked:true, attempts_left:0, retry_after_seconds}` even for the right passcode. Attempts stored in `login_attempts (identifier, kind='passcode', success, ip_address, user_agent ≤300, attempted_at)`.
  - *Rebuild:* `POST /staff/passcode-login` using Laravel `RateLimiter` with identical limits; on success create a session with role `passcode_staff` (no passcode stored client-side, none sent with later requests); login-alert e-mail on lockout.
- **F-STF-004 · Warm-up ping** — Passcode `__warmup__` → `{ok:false, warmup:true}`, not counted (wakes the function).
  - *Rebuild:* not needed (no cold starts); drop.
- **F-STF-005 · Passcode disabled conditions** — Secret unset/blank → `unavailable:'not_configured'`; length < 8 → `'too_short'`; equal to leaked `@@@Qatar123` → `'revoked'` (permanent deny list). Not recorded as attempts.
- **F-STF-006 · 10-day access with sliding renewal** — Client stores flag, plaintext passcode and expiry (10 days) in local+session storage plus cookie `staff_passcode_ok=1; max-age=864000; SameSite=Lax`; legacy grants without expiry get a fresh 10 days; opening a hub link re-grants (sliding).
  - *Rebuild:* server session lifetime 10 days absolute for `passcode_staff`, sliding renewal on activity; no plaintext secret in browser storage.
- **F-STF-007 · Cross-tab handoff** — Hub links append `#sk=<passcode>`; target page strips the hash immediately and re-verifies via `staff-auth`.
  - *Rebuild:* drop (same-domain session cookie covers new tabs).
- **F-STF-008 · Role detection & badge** — `useStaffRole` reads `user_roles` (admin > moderator; ignores `admin_users`). Badge: admin `دخول بحساب مسؤول` ("Signed in with an administrator account"), moderator `دخول بحساب موظف` ("…employee account") (emerald); none/passcode `دخول بكلمة مرور الفريق — الحضور للعرض فقط` ("Team passcode — attendance view only", amber).
  - *Rebuild:* derive from the single roles system (legacy `admin_users`-only admins are shown as passcode in the original).
- **F-STF-009 · Hub header & logout** — Header (max-w-4xl, blurred): logo (`alt="الشعار"`, h-10) or text `لوحة الفريق` ("Team board"); outline `خروج` (LogOut) → revoke passcode, sign out, go to `/admin/login`. Centre: eyebrow `روابط سريعة` (11px, tracking 0.3em), H1 `أهلاً بك` ("Welcome"), sub `اختر المهمة التي تريد البدء بها` ("Choose the task to start").
- **F-STF-010 · Hub quick links** — Grid (2 columns on sm+), each opens in a NEW TAB (`noopener,noreferrer`), in this order: `نقاط البيع` / `بيع التذاكر مباشرة` → POS; `مسح التذكرة` / `تسجيل دخول الحضور بالكاميرا` → scanner; `الحجوزات المباشرة` / `متابعة الحجوزات لحظة بلحظة` → live bookings; `قراءة إيصال POS` / `تحويل الإيصال إلى بيانات` → receipts; `حضور الفريق` / `تسجيل الحضور والغياب اليومي` → attendance; `لوحة البوابة` / `أرقام الحضور لحظة بلحظة` → gate board. Card: right accent bar, 48px icon tile, label + truncated hint, trailing ExternalLink icon nudging on hover, lift −0.5 on hover.
  - *Rebuild:* show only links the user is permitted to use.
- **F-STF-011 · Admin dashboard link** — Admin only: ghost button `الذهاب إلى لوحة التحكم الكاملة ←` → `/admin/dashboard` (same tab).
- **F-STF-012 · Gate board data contract** — `gate-stats {passcode, event_id?}`: staff auth (401); event = body id or `settings.current_event_id`, none → `400 {error:'no_current_event'}`; orders of the event with `payment_status='confirmed'`; holders in chunks (200 order ids × 1000 rows); `totals[type||'normal'] = {issued, scanned}`; `recent` = present holders with `confirmed_at`, newest first, top **25** `{id, name, ticket_type, confirmed_at, confirmed_by_name}`; response `{event, totals, issued, scanned, recent, generated_at}` (no orders → `{event, totals:{}, recent:[], generated_at}`).
  - *Rebuild:* single aggregate query; issued = admissible tickets per the gate rules (so scanned never exceeds issued); "no current event" returned as a 200 with code.
- **F-STF-013 · Gate board header & refresh** — Sticky header: eyebrow `لوحة البوابة`, H1 = event title or `الحضور المباشر` ("Live attendance"); `تحديث` (RefreshCw spins, disabled while refreshing); outline `رجوع` → `/staff`. Silent auto-refresh every **15,000 ms**.
  - *Rebuild:* plus live increments via `TicketCheckedIn` broadcast.
- **F-STF-014 · Gate board counters** — Cards `التذاكر المُصدرة` (issued), `دخلوا البوابة` (scanned, emerald), `لم يدخلوا بعد` (max(0, issued − scanned), muted); `—` during first load.
- **F-STF-015 · Attendance progress & per-type tiles** — `نسبة الحضور` + `{percent}%` = round(scanned/issued×100) (0 when issued 0); bar h-3 with 500 ms width transition; per-type tiles (`عادي`, `VIP`, `مواقف`) `{scanned} / {issued}`.
- **F-STF-016 · Last 25 scans** — Card `آخر عمليات المسح`: name, `{type label} — {confirmed_by_name}`, time `ar-u-nu-latn` Asia/Qatar `HH:mm` (`-` when null); empty `لا توجد عمليات مسح بعد`.
- **F-STF-017 · Gate board errors & footer** — Banner: `لم يتم تحديد فعالية حالية بعد` ("No current event set yet") for `no_current_event`, else `تعذّر تحميل أرقام البوابة`; footer `آخر تحديث: {time of generated_at}`. (Original never shows the specific message because the 400 status hides the body.)
- **F-STF-018 · Attendance page shell** — Header (max-w-6xl): logo or `حضور الفريق`; outline `رجوع` → `/staff`. Same component is used in the admin dashboard attendance tab.
- **F-STF-019 · Attendance data contract** — `staff-attendance` modes: `list {from,to}` → active `pos_users(id,name,icon)` by name + `staff_attendance` rows in range + `can_edit`; `set {date, pos_user_id, status}` (null → delete; else upsert `{status, marked_by: editor id (client value ignored), marked_at}` on unique (pos_user_id, attendance_date)); `bulk_present {date}` → `{success, ids}`; `clear_day {date}`. Errors: 401 `غير مصرح`; writes without an account → `403 {error:'account_required', message:'سجّل الدخول بحسابك لتعديل الحضور'}`; bad dates `400 bad_range`/`bad_input`; `400 bad_status`; `400 bad_mode`; `500`.
  - *Rebuild:* `AttendanceController` with policy `attendance.edit` (supervisor, admin); reject future dates server-side.
- **F-STF-020 · Daily present/absent register** — Person cards (md 2 / xl 3 columns): emoji avatar (`icon` or `👤`), name, status `حاضر` / `غائب` / `لم يُسجَّل بعد` ("not recorded yet"); card tinted emerald/destructive; two 40px buttons ✓ (aria `تسجيل {name} حاضر`) and UserX (aria `تسجيل {name} غائب`), filled when active; clicking the active status again clears it; disabled while saving that row or read-only; optimistic update.
- **F-STF-021 · Date navigation** — ChevronRight `اليوم السابق` (−1 day), long Arabic date (`ar-u-nu-latn`, weekday, day, month, year), native date input (LTR, `max = today`), ChevronLeft `اليوم التالي` (+1, disabled at today). Today = Qatar date computed on mount.
- **F-STF-022 · Attendance stats** — Cards (2 / 4 on lg): `إجمالي الفريق`, `حاضر` (emerald), `غائب` (destructive), `بدون تسجيل` (= total − present − absent).
- **F-STF-023 · Mark all present** — `تحديد الكل حاضر` (CheckCheck; disabled if busy, no users, or read-only); toast `تم` / `تم تسجيل الجميع كحاضرين`; failure `خطأ` / `تعذّر التحديث`.
- **F-STF-024 · Clear day** — Outline `مسح اليوم` (RotateCcw), no confirmation; toast `تم` / `تم مسح سجل هذا اليوم`; failure `تعذّر المسح`.
  - *Rebuild:* confirmation modal.
- **F-STF-025 · Read-only mode** — When `can_edit` false (passcode users): amber banner `أنت في وضع العرض فقط — سجّل الدخول بحسابك (بريد وكلمة مرور) لتسجيل الحضور والغياب.`; edit attempt toast `للقراءة فقط` / `سجّل الدخول بحسابك لتعديل الحضور`.
- **F-STF-026 · 7-day summary** — Table `ملخّص آخر 7 أيام` (7 days ending at the selected date, min-width 520px, horizontal scroll): `الاسم` + 7 day headers (weekday short + day); cells `✓` emerald / `✗` destructive / `–` muted in 24px circles.
- **F-STF-027 · Auto-refresh & messages** — Poll every **30,000 ms**. Header card: eyebrow `حضور الفريق`, H2 `سجل الحضور والغياب`, sub `الأسماء مأخوذة من مستخدمي نقاط البيع النشطين`. Empty: `لا يوجد مستخدمو نقاط بيع نشطون — أضفهم من صفحة «مستخدمي POS».`. Load fail `خطأ` / `تعذّر تحميل بيانات الحضور`; save fail `خطأ` / `لم يتم حفظ الحالة`.

**Upgrades (new, not in original):** NEW check-in/check-out times and shift hours per staff member; NEW monthly attendance export (Excel); NEW per-gate-staff scan counts on the gate board; NEW per-gate (entrance) breakdown when gate assignment exists.

**Original defects to avoid:**
- Passcode stored in plaintext in localStorage/sessionStorage and sent with every request; passcode accepted without throttling on every staff endpoint except `staff-auth`.
- Passcode holders bypass `adminOnly` routes.
- Editor detection fails for a user holding both admin and moderator roles (`maybeSingle` error → read-only).
- Server accepts future attendance dates; no confirmation on clear day.
- Gate board `no_current_event` sent as HTTP 400 → specific message never displayed; issued counts only confirmed orders.
- Legacy admins without `user_roles` shown as passcode users and denied the dashboard link.

---

### SFX — Sound & motion effects
Shared front-end effects used by the scanner, POS and live boards. New app: `resources/js/sfx.js` (one shared `AudioContext`) and `resources/js/fireworks.js`, both Alpine-callable.

- **F-SFX-001 · Audio engine** — Web Audio API only, no audio files. One lazily created `AudioContext` (webkit fallback), resumed if suspended. Each tone: oscillator → gain; gain ramps exponentially from 0.0001 to `volume` in 20 ms then down to 0.0001 at `start + duration`; oscillator stops at `start + duration + 0.02`.
  - *Rebuild:* unlock audio on the first user gesture (button "تفعيل الصوت"); reuse the single context for every sound (live bookings creates a new context per sound in the original).
- **F-SFX-002 · Alert bell** (`playAlertSound`, capacity increase on POS / booking bell) — sine 880 Hz at t=0 for 0.22 s + 1320 Hz at t=0.18 s for 0.30 s, volume 0.35.
- **F-SFX-003 · Error buzzer** (`playErrorSound`, scanner error/already-scanned) — square wave 320 Hz at t=0 for 0.25 s (vol 0.4) + 200 Hz at t=0.22 s for 0.35 s (vol 0.4).
- **F-SFX-004 · New online booking chime** — sine 880 → 1108.73 Hz (t+0.1) → 1318.51 Hz (t+0.2); gain 0.3 → 0.01 over 0.5 s.
- **F-SFX-005 · POS "cha-ching"** — osc1 square 1200 → 800 Hz (t+0.05), 0–0.1 s; osc2 sine 1567.98 → 2093 Hz, 0.1–0.4 s; osc3 triangle 2637 → 3136 Hz, 0.1–0.5 s; gain 0.25 → 0.4 at 0.1 s → 0.01 at 0.5 s.
- **F-SFX-006 · Check-in tones by type** — VIP: sine C5 523.25 → E5 659.25 (0.15 s) → G5 783.99 (0.3 s) + triangle C6 1046.5 → E6 1318.51 → G6 1567.98, gain 0.4 → 0.01 over 0.6 s; normal: sine F5 698.46 → A5 880 (0.1 s), gain 0.35 → 0.01 over 0.4 s; parking: sine F4 349.23 → A4 440 (0.08 s), gain 0.3 → 0.01 over 0.3 s; other: sine 440 → 523.25 (0.1 s), 0.3 s.
- **F-SFX-007 · Capacity-increase tones by type** — VIP: triangle E5 659.25 → G5 → B5 987.77 → E6 at 0/0.12/0.24/0.36 s + sine B4 493.88 → E5 → G5 → B5 + sine E6 → G6 1567.98 → B6 1975.53 → E7 2637.02, gain 0.4 → 0.6 (0.2 s) → 0.01 at 1.0 s; normal: triangle C5 → E5 → G5 → C6 1046.5 (0/0.15/0.3/0.45 s) + sine G4 392 → C5 → E5 → G5, gain 0.5 → 0.6 (0.3 s) → 0.01 at 0.8 s; parking: square C4 261.63 → E4 329.63 → G4 392 (0/0.1/0.2 s), 0.3 → 0.01 over 0.5 s; other: sine 440 → 880 (0.2 s), 0.4 s.
- **F-SFX-008 · Sound toggles** — Live bookings sound and check-in-notification toggles are in-memory only.
  - *Rebuild:* persist per device in localStorage; add a global mute on scanner/POS (NEW); add a success beep on admit (NEW, absent in original).
- **F-SFX-009 · Fireworks — trigger & accessibility** — Component props `trigger` (replays when it changes; POS increments per sale) and `duration` (default **6000 ms**). With `prefers-reduced-motion: reduce` nothing is animated.
- **F-SFX-010 · Fireworks — canvas** — Full-screen `<canvas>` fixed inset-0, `z-index 100`, `pointer-events:none`, `aria-hidden`; device-pixel ratio capped at 2; resizes with the window.
- **F-SFX-011 · Fireworks — palette** — RGB gold `250,204,21`; light gold `253,224,71`; maroon/rose `190,24,93`; emerald `16,185,129`; white `255,255,255`.
- **F-SFX-012 · Fireworks — particle burst** — `explode(cx, cy, color, count=46, power=4.2)`: evenly spaced angles ± 0.12 rad jitter; speed = rand(0.45,1) × power; maxLife rand(50,85) frames; size white rand(1,1.8) else rand(1.4,2.6); gravity 0.035; 35% "sparkle" particles flicker (alpha × 0.35 with 40% chance per frame).
- **F-SFX-013 · Fireworks — opening volley** — (1) centre x∈[0.42,0.58]w, y∈[0.16,0.30]h, colour from first 3 palette entries, 60 particles, power 5; (2) left x∈[0.12,0.30], y∈[0.12,0.24], 40, 4; (3) right x∈[0.70,0.88], y∈[0.12,0.24], emerald, 40, 4; (4) white x∈[0.30,0.45], y∈[0.34,0.44], 24, 3.
- **F-SFX-014 · Fireworks — periodic bursts** — Every **750 ms** until `duration`: random burst x∈[0.15,0.85], y∈[0.12,0.40], random palette colour, count rand(24,44), power rand(3,4.4).
- **F-SFX-015 · Fireworks — rendering** — Per frame: trail fade by `destination-out` fill `rgba(0,0,0,0.28)`; draw with `lighter` compositing; velocity × 0.985; radius = size × t + 0.4 with t = 1 − life/maxLife. After `duration`, keeps animating until all particles die, then clears.
- **F-SFX-016 · Fireworks — cleanup** — On unmount/re-trigger: cancel animation frame, clear interval, remove resize listener, clear canvas.
- **F-SFX-017 · CSS motion tokens** — `animate-scanner-line` (moving gold line in scanner frame), `animate-new-booking` (green glow 1.5 s × 3), `animate-recent-pulse` (scale 1→1.02, 2 s infinite), capacity banner shrinking progress bar (4 s), bouncing Sparkles, pulsing status dots.
  - *Rebuild:* Tailwind 4 `@keyframes` in the theme; all wrapped in `motion-safe:`.

**Original defects to avoid:** new `AudioContext` per live-board sound (context limit → silence); toggles not persisted; no success sound at the gate; browsers block audio until a gesture and the original never prompts for it.

---

### Checklist coverage (C-staff-ops appendix → feature IDs)

| # | Checklist item (abridged) | Feature ID(s) |
|---|---|---|
| 1 | Staff route guard (admin / moderator / passcode; moderators redirected) | F-STF-001 |
| 2 | Unified login card | F-STF-002 |
| 3 | Passcode server verification, per-IP + global throttling, attempts-left/retry-after | F-STF-003 |
| 4 | Passcode warm-up ping | F-STF-004 |
| 5 | Passcode disabled when unset / < 8 / revoked | F-STF-005 |
| 6 | 10-day expiry with sliding renewal | F-STF-006 |
| 7 | Cross-tab URL-hash handoff | F-STF-007 |
| 8 | Staff hub 6 quick links in new tabs | F-STF-010 |
| 9 | Hub role badge and logout | F-STF-008, F-STF-009 |
| 10 | Admin-only link to full dashboard | F-STF-011 |
| 11 | Attendance register present/absent/clear by re-click | F-STF-020, F-STF-019 |
| 12 | Attendance date navigation, no future | F-STF-021 |
| 13 | Attendance stats | F-STF-022 |
| 14 | Mark-all-present and clear-day | F-STF-023, F-STF-024 |
| 15 | Read-only mode for passcode users | F-STF-025 |
| 16 | 7-day summary table | F-STF-026 |
| 17 | Attendance auto-refresh 30 s | F-STF-027 |
| 18 | Gate board issued / entered / not yet | F-STF-012, F-STF-014 |
| 19 | Gate board percentage bar + per-type tiles | F-STF-015 |
| 20 | Gate board last 25 scans | F-STF-016 |
| 21 | Gate board 15 s auto-refresh + manual | F-STF-013 |
| 22 | Scanner camera (rear, 10 fps, 250×250) + error messages | F-GATE-005, F-GATE-006, F-GATE-007 |
| 23 | Auto stop / auto restart | F-GATE-008 |
| 24 | Overlapping-scan protection | F-GATE-009 |
| 25 | Legacy QR URL normalisation | F-GATE-010 |
| 26 | Manual search by ref / -TKT / phone (uppercase) | F-GATE-012, F-GATE-014 |
| 27 | Phone search listing paid tickets with selection | F-GATE-015, F-GATE-019 |
| 28 | Booking-reference search with select-all | F-GATE-017, F-GATE-019 |
| 29 | Passcode-staff server lookup fallback | F-GATE-018, F-GATE-043, F-GATE-016 |
| 30 | Payment-pending blocked (POS/cash treated paid) | F-GATE-013, F-GATE-022, F-GATE-023 |
| 31 | Review popup | F-GATE-024, F-GATE-021 |
| 32 | Multi-ticket sequential confirm + success summary | F-GATE-027, F-GATE-028, F-GATE-020 |
| 33 | Sticky confirm bar + floating button | F-GATE-025, F-GATE-026 |
| 34 | Already-scanned alert, buzzer, 4 s reset | F-GATE-029, F-SFX-003 |
| 35 | Server error dialog with buzzer | F-GATE-030 |
| 36 | Related tickets same day / other days / same booking | F-GATE-031, F-GATE-032, F-GATE-033 |
| 37 | Gate staff selector persisted & stamped | F-GATE-003, F-GATE-037 |
| 38 | Scan history last 30 | F-GATE-035, F-GATE-042 |
| 39 | Admin-only undo with confirmation + audit | F-GATE-036, F-GATE-046 |
| 40 | Server rules: today, 18:00 expiry, one admission, atomic | F-GATE-038, F-GATE-039, F-GATE-040, F-GATE-041, F-GATE-050 |
| 41 | Automation column `تم التسجيل يدوياً ✓` | F-GATE-047 |
| 42 | Activity logging of searches and scans | F-GATE-011, F-GATE-049 |
| 43 | POS event selector | F-POS-004 |
| 44 | POS cashier selector persisted | F-POS-003 |
| 45 | POS ticket cards with availability, price, stepper, sold-out | F-POS-005, F-POS-006, F-POS-007 |
| 46 | POS cart with per-unit tiles, subtotals, total | F-POS-010, F-POS-011, F-POS-012 |
| 47 | VIP+normal ≤ 5 and availability checks | F-POS-008, F-POS-009 |
| 48 | Buyer form (name, 30 country codes, ID, nationality buttons) | F-POS-013, F-POS-014, F-POS-015, F-POS-016 |
| 49 | Previous-customer lookup with history chips | F-POS-017, F-POS-018 |
| 50 | Live phone autocomplete ≥ 4 digits, 350 ms | F-POS-019 |
| 51 | Additional holder forms, pre-filled & synced | F-POS-020, F-POS-021, F-POS-022, F-POS-023 |
| 52 | Per-person 5-per-event pre-check | F-POS-025, F-POS-031 |
| 53 | Stock pre-check | F-POS-026 |
| 54 | Cash sale creation (server-priced, confirmed, POS-, QR, admin e-mail) | F-POS-027 … F-POS-033 |
| 55 | Success dialog with fireworks | F-POS-034, F-POS-035, F-POS-036 |
| 56 | Detailed Arabic error messages | F-POS-024, F-POS-037 |
| 57 | Capacity alert bar thresholds | F-POS-038 |
| 58 | Capacity-increase banner + bell + toast | F-POS-039, F-SFX-002 |
| 59 | Realtime POS availability | F-POS-040 |
| 60 | POS fullscreen | F-POS-041 |
| 61 | Manual check-in dialog | F-POS-042, F-POS-043, F-POS-044, F-GATE-044, F-GATE-045 |
| 62 | POS sale activity log | F-POS-034 |
| 63 | Receipt capture camera / gallery | F-RCP-003, F-RCP-004 |
| 64 | AI OCR with rate-limit / credit / parse errors | F-RCP-006, F-RCP-007, F-RCP-008, F-RCP-009 |
| 65 | Image stored, row saved, realtime list | F-RCP-005, F-RCP-010, F-RCP-011, F-RCP-012 |
| 66 | Editable ticket counts | F-RCP-016 |
| 67 | Receipt delete | F-RCP-017 |
| 68 | Summary at fixed prices 200/300/500 | F-RCP-018 |
| 69 | Styled Excel export | F-RCP-019, F-RCP-020, F-RCP-021 |
| 70 | Responsive receipt list | F-RCP-013, F-RCP-014, F-RCP-015 |
| 71 | Live bookings for chosen date / auto next event / all | F-LIVE-003, F-LIVE-004, F-LIVE-005 |
| 72 | Live KPIs | F-LIVE-007, F-LIVE-008 |
| 73 | Online vs POS split | F-LIVE-009 |
| 74 | Ticket-type breakdown with capacity | F-LIVE-010 |
| 75 | Nationality breakdown with flags | F-LIVE-011 |
| 76 | Cards / table view toggle | F-LIVE-012, F-LIVE-013, F-LIVE-018 |
| 77 | Lifetime ticket total per person | F-LIVE-017 |
| 78 | Invoice sent / not sent / POS user / downloaded icons | F-LIVE-015 |
| 79 | Copy booking reference | F-LIVE-016 |
| 80 | 5-min pulse + new-booking glow | F-LIVE-019, F-LIVE-020, F-LIVE-014 |
| 81 | Realtime POS toast + cash-register sound | F-LIVE-024, F-LIVE-021, F-SFX-005 |
| 82 | Realtime online toast + chime | F-LIVE-025, F-SFX-004 |
| 83 | Realtime check-in toast by type + sound | F-LIVE-026, F-LIVE-022, F-SFX-006 |
| 84 | Sound / notification toggles | F-LIVE-027, F-SFX-008 |
| 85 | Daily sales statistics dialog | F-LIVE-028, F-LIVE-029 |
| 86 | Live bookings fullscreen | F-LIVE-030 |
| 87 | Live visitors counters | F-LIVE-034 |
| 88 | Live visitors breakdowns | F-LIVE-035 |
| 89 | Live visitors detail table | F-LIVE-036 |
| 90 | Realtime + 15 s polling + stale cleanup | F-LIVE-037, F-LIVE-033 |
| 91 | Analytics date range + 7/30/90 presets | F-LIVE-038, F-LIVE-032 |
| 92 | Analytics totals | F-LIVE-039, F-LIVE-043 |
| 93 | Visitors-over-time chart | F-LIVE-040 |
| 94 | Top pages and countries (top 20) | F-LIVE-041 |
| 95 | Daily visits table | F-LIVE-042 |
| 96 | Web Audio effects (bell, buzzer) | F-SFX-001, F-SFX-002, F-SFX-003 |
| 97 | Fireworks respecting reduced motion | F-SFX-009 … F-SFX-016 |


---

## 3. Business rules & constants (single source of truth)

This part is the only normative list of hard business rules and constants. Every other part of the spec (modules, screens, jobs) must reference these IDs instead of repeating numbers. In the Laravel rebuild:

- **Tunable** rules (marked *T* in the "Kind" column) live in `config/qcamelmc.php` (defaults below) and, where an admin must change them at runtime, in a spatie/laravel-settings group (named in the "Laravel home" notes). Code reads them through one `App\Support\Rules` facade so a value is never duplicated.
- **Fixed** rules (marked *F*) are invariants or protocol facts (Sadad, formats). They are constants in code (`App\Domain\...\Constants`) and pinned by tests (Part 5 b).
- "Source" cites the extraction notes: **A** public flow, **B1** admin finance, **B2** admin core, **C** staff ops, **D** backend, **E** database/infra (section numbers refer to those notes).
- Arabic strings are verbatim; English in parentheses is a translation only.

### 3.1 Time, calendar and cut-off

| ID | Rule / constant | Value | Applies to | Kind | Source |
|---|---|---|---|---|---|
| R-001 | Business time zone | `Asia/Qatar` (UTC+3, no DST). Store every timestamp in UTC; convert at the edges. `config('app.timezone')='UTC'`, `config('qcamelmc.timezone')='Asia/Qatar'` | All date logic, display, grouping, reports | F | E header, A §13.7 |
| R-002 | Qatar day range | Day `D` = `[D 00:00:00+03:00, D+1 00:00:00+03:00)` as UTC instants; "today" is computed in Qatar, never in the browser or DB session zone | Filters "today", reports, analytics, gate, attendance | F | B2 §16, C §4.10 |
| R-003 | Event day cut-off (sales/scan/alert) | An event is **expired** when the Qatar date is after the event date, or it is the same Qatar date and the Qatar hour is **≥ 18** (18:00). Original applies it to the start `event_date` only | Public listing (`canPurchaseTickets`), ticket selection, POS event list, check-in (all modes), capacity alerts hidden, auto-invoice skip, Live Bookings default date | T | A §13.7, C §4.10, B2 §14, D §9 |
| R-004 | Cut-off in the rebuild | Per-event columns `sales_cutoff_time` and `checkin_cutoff_time` (default `18:00`, Qatar). Multi-day events (`end_date` set) expire after the cut-off on the Qatar date of `end_date`, not of `event_date` | Same as R-003 | T (NEW column) | Fix of D-040, D-041 |
| R-005 | "Event is today" (check-in) | Original: Qatar date of `event_date` = Qatar today. Rebuild: Qatar today ∈ [date(`event_date`), date(`end_date` ?? `event_date`)] | ticket-checkin default/manual modes, manual-check-in "ليست لليوم" badge | F | C §4.10, B2 §26 |
| R-006 | Search horizon for gate lookups | Only holders whose event `event_date ≥` today 00:00 Qatar (today + future) for check-in by code and `search` mode; `lookup` and `history` are not date-limited | ticket-checkin | F | C §4.10 |
| R-007 | Reference date | Booking reference embeds the **event** date in Qatar time, format `FMDD-FMMM-YYYY` (day and month not zero-padded) | `generate_reference` | F | E §2.2 |
| R-008 | Sadad `txnDate` | Qatar calendar date `YYYY-MM-DD` at request time (21:30Z on 2026-10-09 → `2026-10-10`) | Sadad checkout | F | E §6.5 |
| R-009 | Date display | Locale `ar-u-nu-latn` (Arabic month/weekday names, Latin digits), zone Asia/Qatar. 12-hour times `hh:MM م` / `hh:MM ص` (hour zero-padded, 0→12) | All Arabic UIs, e-mails, PDFs | F | A §1.1, §10.1, D §1.17.3 |
| R-010 | Analytics grouping | Group by **Qatar** date (original grouped page views by UTC date — defect D-083) | Visitors per date, visitors vs scans, daily sales | F | B2 §22, C §9 |
| R-011 | Attendance date | `staff_attendance.attendance_date` is a Qatar calendar date; future dates refused (client and, in the rebuild, server) | Attendance | F | C §2 |

### 3.2 Order composition, limits and pricing

| ID | Rule / constant | Value | Applies to | Kind | Source |
|---|---|---|---|---|---|
| R-012 | Max admission per web order | **5** tickets of `vip`+`normal` combined (`c_max_admission`). Error `quantity_limit_exceeded` (422) | Web orders only (online and cash-at-venue) | T | E §2.1 |
| R-013 | Max parking per web order | **5** `parking` (`c_max_parking`) → `quantity_limit_exceeded` | Web orders only | T | E §2.1 |
| R-014 | Selection stepper cap | Per type: `min(remaining, 5)`; `MAX_ADMISSION_TICKETS=5` (vip+normal), `MAX_TICKETS_PER_TYPE=5` | Ticket selection page (advisory) | T | A §2.1 |
| R-015 | POS cart cap | vip+normal ≤ **5** per sale (client message `الحد الأقصى لتذاكر VIP والعادي معاً هو 5`); parking not capped client-side; the server applies no 5/5 cap to POS | POS | T | C §5.3 |
| R-016 | Request caps (all sources) | ≤ **6** item lines (`MAX_ITEMS_PER_ORDER`), quantity per line integer **1..20** (`MAX_QUANTITY_PER_ITEM`), ≤ **30** tickets per request (`MAX_TICKETS_PER_ORDER`) | create-order validation (web + POS) | F | D §1.8 |
| R-017 | One event per order | All items must belong to the same event, else `invalid_tickets` (422); unknown ticket id → `invalid_tickets`. POS client message `لا يمكن الجمع بين تذاكر فعاليات مختلفة في طلب واحد` | create_order | F | E §2.1 |
| R-018 | Unique lines | Duplicate `ticket_id` in items → validation error `Duplicate ticket type in order` | create-order | F | D §1.8 |
| R-019 | One holder per ticket | `holders.length = Σ quantity` and, per item, holders with that `ticket_id` = its quantity; else `holders_mismatch` (422) / validation error | create_order | F | E §2.1 |
| R-020 | Per-person cap | ≤ **5** `normal`+`vip` tickets per person **per event**, across all orders and all channels (web, cash, POS, restore from bin). Parking excluded. Counts holders of orders `confirmed` OR (`pending` AND (`payment_expires_at` null OR > now)) | DB trigger `enforce_ticket_limit` → Laravel `PersonLimitGuard` | T | E §2.6 |
| R-021 | Person key | `id:<digits(id_number)>` when the ID has ≥ **6** digits; otherwise `ph:<last 8 digits(phone)>` when the phone has ≥ **8** digits; otherwise no key (cap not applied). When an ID key exists the phone is ignored | Per-person cap, lifetime totals, history, POS badges | F | E §2.6, A §13.5 |
| R-022 | Cap error text | `TICKET_LIMIT_EXCEEDED: الحد الأقصى هو 5 تذاكر (عادي + VIP) لكل شخص. {name} لديه {n} تذكرة بالفعل` → HTTP 422 code `ticket_limit_exceeded`, message = text after the prefix | create-order, POS, restore | F | E §2.6, D §1.7 |
| R-023 | Cap pre-check texts | `{name} بلغ الحد الأقصى (5 تذاكر) لهذه الفعالية` / `{name} لديه {existing} تذكرة — يمكنه إضافة {remaining} فقط (طلب {requested})`; default `الحد الأقصى هو 5 تذاكر لكل شخص`. Pre-check is advisory; the server is the guard | Checkout, POS | F | A §13.5, C §5.8 |
| R-024 | Cap scope decision | Per event ("season"), not lifetime; people already above the cap keep their tickets | Product rule | F | E §2.6 |
| R-025 | Same-request counting | Holders inserted earlier in the same order count toward the cap (3 + 3 for one person refused; 3 + 2 allowed). The rebuild counts in-memory holders of the current request plus DB holders | PersonLimitGuard | F | E §2.6, fixes D-031 |
| R-026 | Open unpaid orders per phone | Max **4** orders per customer phone that are `pending` and not expired (online or cash) → HTTP **429** `too_many_pending_orders` (`لديك حجوزات غير مكتملة. يرجى إتمامها أو المحاولة لاحقاً.`). POS exempt. Rebuild compares the normalised E.164 phone, not the raw string | create-order (web) | T | D §1 create-order |
| R-027 | Web sale requires active event | `event.is_active = true` else `event_not_available` (409). POS may sell inactive events. UI additionally requires `is_archived=false`, not expired (R-003) and, when `current_event_id` is set, that event only (`هذه الفعالية غير متاحة للحجز حالياً`) | create_order, ticket selection | F | E §2.1, A §2.1 |
| R-028 | Ticket types per event | Exactly one row per (`event`, `type`), types `vip`, `normal`, `parking`; the public page expects all **3** (warning `هذه الفعالية لا تحتوي على جميع أنواع التذاكر المطلوبة`) | ticket_types | F | E §1.2.2, A §2.1 |
| R-029 | Display order of types | `normal` → `vip` → `parking` on public pages regardless of DB order | Public UI | F | A §2.1 |
| R-030 | Server pricing | Unit price always from `ticket_types.price` (DECIMAL(10,2), ≥ 0) at order time; total = Σ price × qty; client totals, prices and statuses are ignored and never forwarded | create_order | F | E §2.1, §6.5 |
| R-031 | Order label type | `orders.ticket_type` = type of the most expensive line (`ORDER BY price DESC, id`) | create_order | F | E §2.1 |
| R-032 | Order quantity | `orders.quantity` = number of holders | create_order | F | E §2.1 |
| R-033 | Amount guards | `quantity > 0`; `total ≥ min(price) × quantity − 0.01`; Σ holder prices ≤ `total + 0.01` (`AMOUNT_MISMATCH`/`INVALID_QUANTITY` legacy tags) | Order invariant check in `OrderService` | F | E §2.10 |
| R-034 | Sadad minimum | Online (sadad) total must be ≥ **3.00 QAR** → `below_minimum_amount` (422, `الحد الأدنى للدفع عبر سداد هو 3 ريال قطري.`). Cash has no minimum (a 2 QAR cash order succeeds) | create_order | T | E §2.1 |
| R-035 | Currency | QAR, 2 decimals, suffix `ر.ق` (Arabic) / `QAR`. Stored DECIMAL(10,2); arithmetic in integer dirhams (cents) | Everywhere | F | E header |
| R-036 | Payment methods by source | Web: `sadad` or `cash_pos` (cash at venue). POS: `cash_pos` only (sadad → 400) | create-order | F | E §6.5 |
| R-037 | Initial status | POS → `confirmed` immediately with `paid_at = confirmed_at = now`, `confirmed_by = actor` (null for passcode), `pos_user_id = cashier`, `n8n_response_message = 'طلب من نقطة البيع - POS'`, `n8n_responded_at = now`. Web → `pending` | create_order | F | E §2.1 |
| R-038 | Customer row | One new `customers` row per order (never de-duplicated in the original); name/phone trimmed, email `''` when absent, nationality/ID NULL when blank, `country_code` default `+974` | create_order | F | E §2.1 |
| R-039 | Holder country code | First non-empty of: holder `country_code`; `+code` parsed from holder phone `^\s*(\+\d{1,4})\s`; customer `country_code`; `+974` | create_order | F | E §2.1 |
| R-040 | Holder ticket type | Taken from the ticket row, never from the client | create_order | F | E §2.1 |

### 3.3 Holds, expiry and stock

| ID | Rule / constant | Value | Applies to | Kind | Source |
|---|---|---|---|---|---|
| R-041 | Online hold | `payment_expires_at = created_at + 30 min` for web **sadad** orders only (`c_hold_minutes = 30`) | create_order | T | E §2.1 |
| R-042 | Cash-at-venue hold | Original: web `cash_pos` orders are `pending` with **no expiry** (hold seats until staff confirm/cancel). Rebuild: setting `cash_hold_policy` = `none` (original behaviour, default for parity) or `expire_at_event_cutoff` (NEW) | create_order, expiry job | T | E §8 #15 |
| R-043 | Expiry action | `pending` + `sadad` + `payment_expires_at < now` → `cancelled`, `payment_note='expired'`, seats released; idempotent; `FOR UPDATE SKIP LOCKED`; cash and confirmed never expire | `ExpireHoldsAction` | F | E §2.4 |
| R-044 | Expiry triggers | Lazy, scoped to one event, at the start of every order creation for that event and before public availability is shown (`release_expired_holds`); plus scheduled `orders:expire-holds` every minute (NEW) | Order creation, public pages, scheduler | F | E §3.2 |
| R-045 | Stock invariant | `ticket_types.sold_quantity` = number of seats held by orders whose status is `pending` or `confirmed` (per type). Remaining = `available_quantity − sold_quantity`. `available_quantity` is the **total capacity**, not the remainder | StockService | F | E §0 |
| R-046 | Seat transitions | Active→inactive status: release. Inactive→active: reserve or fail `insufficient_stock` (whole change refused). pending↔confirmed: no change. Holder insert on active order: +1 (or `insufficient_stock:<type>`). Holder delete/bin on active order: −1. Order delete when active: release once (no double release via holders). Event move when active: release on old event, reserve on new, or refuse the whole move | StockService | F | E §2.5 |
| R-047 | Lock discipline | Lock all ticket-type rows of the event `ORDER BY id FOR UPDATE` before any stock change; for an event move lock both events ordered by (`event_id`, `id`); retry the transaction on deadlock (max 3) | StockService, OrderService | F | E §2.5, §8 #34 |
| R-048 | Release floor | Release never drives `sold_quantity` below 0 (`GREATEST(0, …)`) | StockService | F | E §2.5 |
| R-049 | Legacy held-seat fallback | An order without holders holds `orders.quantity` seats of `orders.ticket_type` | StockService, recount | F | E §2.5 |
| R-050 | Recount | Admin «إعادة حساب المخزون» recomputes every `sold_quantity` from live orders; returns number of rows changed; admin only (`forbidden` otherwise) | Admin tickets | F | E §2.5 |
| R-051 | Capacity floor | `available_quantity` may not be set below `sold_quantity` (held seats). Original client checked only confirmed holders; rebuild validates against held seats server-side (`لا يمكن أن يقل العدد عن التذاكر المباعة ({sold})`, `لا يمكن خفض عدد تذاكر {type} إلى {q} — تم بيع {sold} تذكرة بالفعل`) | Ticket edit, event edit | F | B1 §8, B2 §10 |
| R-052 | Delete type | A ticket type with `sold_quantity > 0` cannot be deleted (`لا يمكن حذف تذاكر {type} لأنه تم بيع {sold} تذكرة`) | Event edit | F | B2 §10 |
| R-053 | Public held counts | Per type: holders of `confirmed` orders plus `pending` orders whose expiry is null or in the future; an expired-but-unclosed hold stops counting immediately | `get_event_ticket_counts` → `AvailabilityQuery` | F | E §2.6 |
| R-054 | Availability check messages | `TICKET_NOT_FOUND` → `نوع التذكرة غير موجود لهذه الفعالية`; `INSUFFICIENT_CAPACITY` → `عذراً، نفدت التذاكر من هذا النوع حالياً` (none left) or `عذراً، لا تتوفر تذاكر كافية. المتاح: {n} تذكرة` | `check_availability` (was `reserve_tickets`, read-only) | F | E §2.6 |
| R-055 | Low-stock badge | Remaining **≤ 10** and > 0 → `تذاكر محدودة متبقية!`; remaining = 0 → `نفذت الكمية`; checkout warns at ≤ 10 | Checkout, admin tickets | T | A §3.1, B1 §7 |
| R-056 | Late payment | A cancelled (expired/failed) order paid later is confirmed if seats can be re-reserved (clear `payment_note`, `payment_error_reason`); otherwise it stays `cancelled` with `payment_id`, `paid_at`, `payment_note='paid_after_expiry_no_stock'` and must be refunded (`دُفع بعد انتهاء الحجز ولا توجد تذاكر`) | confirm_order_payment | F | E §2.4 |
| R-057 | Admin cancel | Only from `pending`/`confirmed`; note = given note or `admin_cancelled`; seats released («إلغاء وإعادة للبيع»). Second cancel → `already_inactive`. UI notes used: `released by admin` (orders), `returned by admin` (customers) | cancel_order | F | E §2.4, B1 §0.2 |
| R-058 | Final staff cancellation | A `cancelled` order whose note is NOT in {`payment_failed`,`expired`,`paid_after_expiry_no_stock`} is final: never re-checkable by the public poller, never auto-confirmed by a gateway message in the rebuild (gateway success → `review` + refund task, see D-012) | Payment processor | F | D §0.7 #11 |

### 3.4 References, codes and identifiers

| ID | Rule / constant | Value | Applies to | Kind | Source |
|---|---|---|---|---|---|
| R-059 | Booking reference format | `<PREFIX>-<D>-<M>-<YYYY>-<12 uppercase hex>`; PREFIX `QTR` (web) or `POS` (POS); date per R-007; random part = first 12 hex chars of a random UUID (48 bits). Regex `^(QTR|POS)-[0-9]{1,2}-[0-9]{1,2}-[0-9]{4}-[0-9A-F]{12}$` | `ReferenceGenerator` | F | E §2.2 |
| R-060 | Reference uniqueness | UNIQUE; on collision retry up to **5** times, then fail | ReferenceGenerator | F | E §2.1 |
| R-061 | Reference = capability | Holding the reference grants invoice/status read access; never log it to analytics; rate-limit lookups (NEW: 30/min per IP) | Invoice, status, payment pages | F | A App F #2 |
| R-062 | Accepted reference regex (lookups) | `^[A-Za-z0-9][A-Za-z0-9_-]{0,59}$` (1–60 chars) so legacy refs work: `QTR-XXXXXXXX` (8 hex), client-made refs, `QTR-3-9-2026-xxxxxx`, `POS-…` | Status, invoice, verify, restart, callback | F | E §2.2 |
| R-063 | Status lookup batch | At most the first **10** refs per call; invalid refs silently ignored; unknown → `[]` | `get_order_status` | F | E §2.7 |
| R-064 | Ticket code | `<booking_reference>-TKT<NN>`, NN = 1-based ordinal in input order, at least 2 digits (`TKT01`). Original `lpad(n,2)` truncates at 100; rebuild uses `str_pad($n, 2, '0', STR_PAD_LEFT)` without truncation | create_order | F | E §2.3 |
| R-065 | Code uniqueness | `ticket_holders.qr_code` UNIQUE; the QR picture encodes exactly this text; the code never changes after creation (picture regeneration never edits it) | Holders, QR service | F | E §2.3 |
| R-066 | QR object name | Code must match `^[A-Za-z0-9][A-Za-z0-9_-]{0,79}$` → file `<code>.png`; rejected: `../secret`, `a/b`, `a b`, empty, 200 chars, `.hidden`, `a.png`, non-ASCII | QR storage | F | E §2.3, D §1.16 |
| R-067 | Scanned text normalisation | Trim; if `http(s)://…` → drop query/hash, keep last path segment, strip `.png` (case-insensitive) → code | Scanner | F | A §13.4 |
| R-068 | Event-change codes | Moving a booking to another event invalidates the old codes (inserted in `expired_qr_codes`, reason `event_date_changed`) and issues new codes; original new code format `QR-<epoch ms>-<16 hex>`; rebuild issues `<ref>-TKT<NN>` codes under a re-issued reference suffix `-R<k>` (e.g. `QTR-…-R1-TKT01`) — must still satisfy R-066 | change-order-event | F | D §20 |
| R-069 | Browser order memory | localStorage `orderRefs`: last **10** references, de-duplicated (re-adding moves to end), tolerant of corrupt JSON | Public site | F | A §13.1 |
| R-070 | Person keys for totals | Lifetime totals keyed `id:<digits>` (≥ 6) and `ph:<last 8>` (≥ 8); `getTotal` = max over the person's keys | Live bookings badge | F | E §2.6 |
| R-071 | Person event history | Confirmed holders matching ID (digits ≥ 6) OR last 8 phone digits, grouped by event, newest first, **LIMIT 50**; staff only in the rebuild | POS customer lookup | F | E §2.6 |

### 3.5 Input validation (server, create-order and forms)

| ID | Rule / constant | Value | Applies to | Kind | Source |
|---|---|---|---|---|---|
| R-072 | Text cleaning | Collapse whitespace runs to one space and trim; control characters `[\u0000-\u001f\u007f]` in the raw value → `<label> contains invalid characters` | All text inputs | F | D §1.8 |
| R-073 | Names | Customer and holder name **2–100** characters (UI also `minLength 3` hint); trim on submit, never per keystroke | create-order | F | D §1.8 |
| R-074 | E-mail | Optional; if present ≤ **254** chars and `^[^\s@]+@[^\s@]+\.[^\s@]{2,}$`; stored `''` when absent. Sending e-mail requires `^[^@\s]+@[^@\s]+\.[^@\s]+$` | create-order, mail | F | D §1.8, §10 |
| R-075 | Phone | Characters `[+\d\s\-()]` only and **6–15** digits; `'12'` fails | create-order | F | D §1.8 |
| R-076 | Country code | `^\+\d{1,4}$`; customer default `+974`; holder empty allowed (stored `''`, then resolved by R-039) | create-order | F | D §1.8 |
| R-077 | Nationality | **1–60** characters; default `قطر` in UIs | create-order | F | D §1.8 |
| R-078 | ID number | `^[\p{L}\p{N}\-_/ ]{3,30}$` (Unicode letters/digits, `-`, `_`, `/`, space), **required** for customer and every holder | create-order | F | D §1.8 |
| R-079 | Ticket id | UUID v1–v8 regex `^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$` (i); lower-cased | create-order | F | D §1.8 |
| R-080 | Quantity | Integer 1..20 (rejects `0`, `-1`, `1.5`, `21`, `"2"`, null) | create-order | F | E §6.5 |
| R-081 | Source / method | `source ∈ {web,pos}` (default `web`); `payment_method ∈ {sadad,cash_pos}`; `pos_user_id` UUID or null | create-order | F | D §1.8 |
| R-082 | Error envelope | All validation errors at once: HTTP 400 `{success:false, code:'validation_failed', error, errors:[…]}`; Arabic UI text `يرجى التحقق من البيانات المُدخلة والمحاولة مرة أخرى.` | API | F | E §6.5 |
| R-083 | Request body size | JSON body ≤ **64 000** characters → else 413 `payload_too_large`; invalid JSON → 400 `invalid_json`; non-object → 400; OPTIONS 204; non-POST 405 | All JSON endpoints | F | D §0.3 |
| R-084 | Relay body size | `send-to-webhook` body ≤ **200 000** characters | Webhook relay | F | D §15 |
| R-085 | Phone maxLength (UI) | `+974→8`, `+966→9`, `+971→9`, `+965→8`, `+973→8` digits; others unlimited | Checkout | T | A §3.1 |
| R-086 | Per-country phone rules (NEW, server) | Qatar 8 digits starting 3/5/6/7; KSA 9 starting 5; UAE 9 starting 5; Kuwait 8 starting 5/6/9; Bahrain 8 starting 3; others R-075 | Checkout, POS | T (NEW) | A App F #7 |
| R-087 | Digit normalisation | Convert Eastern-Arabic `٠-٩` (and, NEW, Persian `۰-۹`) to `0-9` before validation | Phone/ID inputs | F | A §3.1 |
| R-088 | Phone storage | Store E.164 (`+97455512345`) plus derived display parts; holder phone never keeps a typed prefix (NEW normalisation) | Customers, holders | F | A App F #6 |
| R-089 | Nationality list | 21 Arabic countries in order: السعودية, الإمارات, قطر, الكويت, البحرين, عمان, مصر, الأردن, لبنان, العراق, سوريا, اليمن, ليبيا, السودان, الجزائر, المغرب, تونس, موريتانيا, الصومال, جيبوتي, فلسطين with codes +966 +971 +974 +965 +973 +968 +20 +962 +961 +964 +963 +967 +218 +249 +213 +212 +216 +222 +252 +253 +970 | Checkout | F | A §3.1 |
| R-090 | Staff label | `staff_name` whitespace-collapsed, trimmed, ≤ **80** chars → `checked_in_by_name` | Check-in | F | C §4.10 |
| R-091 | n8n report-back message | ≤ **500** characters (truncated) | n8n-response | F | D §16 |
| R-092 | Event form | Title and location required (`يرجى إدخال اسم الفعالية والموقع`); `end_time > start_time` when both set (`وقت النهاية يجب أن يكون بعد وقت البداية`); end date ≥ start date (`تاريخ النهاية يجب أن يكون بعد تاريخ البداية`); per type quantity integer ≥ 0 and price ≥ 0 (`قيم غير صحيحة لتذاكر {VIP|عادي|مواقف} — يرجى إدخال عدد وسعر صالحين`); new event requires all 6 numbers (`يرجى إدخال عدد وسعر جميع أنواع التذاكر`) | Events | F | B2 §10–11 |
| R-093 | Ticket edit | price finite ≥ 0 (`يرجى إدخال سعر صحيح (رقم أكبر من أو يساوي صفر)`), quantity integer ≥ 0 (`يرجى إدخال عدد تذاكر صحيح`), quantity ≥ held seats | Tickets | F | B1 §8 |
| R-094 | Staff account password | ≥ **8** characters (`البريد وكلمة مرور (8 أحرف على الأقل) مطلوبة`, `كلمة مرور (8 أحرف على الأقل) مطلوبة`) | Staff accounts | F | B2 §24 |
| R-095 | Clarity project id | `^[a-z0-9]{4,20}$` (i) (`معرّف المشروع غير صالح (أحرف وأرقام فقط)`) | Settings | F | B2 §8 |
| R-096 | Admin e-mail setting | Empty → NULL; else `^[^@\s]+@[^@\s]+\.[^@\s]+$` (`البريد الإلكتروني غير صالح`) | Settings | F | B2 §8 |
| R-097 | Search minimums | Customer lookup ≥ **3** chars, limit **8**; POS phone autocomplete ≥ **4** digits; manual check-in / staff search ≥ **2** chars, **40** per sub-query (customer pre-query 60); scanner `lookup` limit 20; MCP search ≥ 2 chars, limit 1..25 | Search endpoints | F | B1 §12, C §4.10, §5.3 |
| R-098 | LIKE escaping | Search terms are bound parameters with `%`, `_`, `\` escaped (original stripped `% , ( )` and left `_` as wildcard) | All searches | F | D §24 |

### 3.6 Payments (Sadad Web Checkout 2.1 + merchant API)

| ID | Rule / constant | Value | Applies to | Kind | Source |
|---|---|---|---|---|---|
| R-099 | Sadad configured | Merchant id, checkout secret and website domain all non-blank; otherwise online orders → **503 `payment_unavailable` before reserving anything** (`الدفع الإلكتروني غير متاح حالياً. يمكنك اختيار الدفع عند الحضور.`); cash needs no gateway | create-order, restart | F | E §6.5 |
| R-100 | Checkout endpoint | `https://sadadqa.com/webpurchase` (form POST, `target=_self`); browser refuses any host other than `sadadqa.com`, `www.sadadqa.com`, `secure.sadadqa.com` over https (look-alikes like `sadadqa.com.evil.example` refused) | Checkout redirect | F | A §13.1 |
| R-101 | Checkout fields | `merchant_id, ORDER_ID (=booking ref), TXN_AMOUNT ('nnn.nn'), WEBSITE, CALLBACK_URL, MOBILE_NO, EMAIL, txnDate, signature, productdetail[i][order_id|itemname|amount|quantity]`; item names `تذكرة VIP`, `تذكرة دخول عامة`, `تصريح مواقف` | SadadGateway | F | E §6.5, D §1 |
| R-102 | Signature | Uppercase hex SHA-256 of `secret + concat(values of all fields sorted by key in byte order (ksort))`, excluding `signature` and `productdetail[...]`, values not URL-encoded. Vector → `6A6D258727B321A8EC7C02CAFD96B2F14D754BA1627029F2920D27E11F4D0B49` | SadadGateway | F | E §6.5 |
| R-103 | Amount format | Exactly 2 decimals computed from integer cents (0.1+0.2 → `0.30`); reject 0, negative, NaN, Infinity; reject empty secret/merchant id; callback URL must be https | SadadGateway | F | E §6.5 |
| R-104 | Mobile number | Digits with country code: own `+code` in the number wins; strip `00`; national number prefixed by the code; empty code → 974 (`+974 5551 2345`→`97455512345`; `0555 1234`,`+966`→`9665551234`; `+20 100 123 4567`→`201001234567`) | Sadad MOBILE_NO, WhatsApp | F | E §6.5, A §13.3 |
| R-105 | Fallback e-mail | Missing customer e-mail → `noreply@<website domain host>`; no domain → `noreply@example.invalid`; non-domain `MYSHOP` → `noreply@myshop.invalid` | Sadad EMAIL | F | D §1.6 |
| R-106 | Callback checksum | Same algorithm over all fields except `checksumhash`, compared case-insensitively; JSON values stringified like PHP (numbers as-is, null → ''); vector `6082cc9b…26b8a`. **Informational only — never proof of payment** | Callback, webhook | F | E §6.5 |
| R-107 | Payload normalisation | `ORDERID`/`ORDER_ID`/`website_ref_no`/`websiteRefNo` → order ref (must match R-062); `transaction_status` 3 → success, 2/7 → failed, 1 → in_progress, else unknown; fallback `STATUS` `TXN_SUCCESS`/`TXN_FAILURE`; `issandboxmode`/`isTestMode` → sandbox | Callback, webhook | F | E §6.5 |
| R-108 | Merchant API | Live `https://api-s.sadad.qa`, sandbox `https://api-sandbox.sadad.qa`; login `POST /api/userbusinesses/login {sadadId:number, secretKey, domain}` → `accessToken` sent raw as `Authorization`; `GET /api/transactions/listTransactions?…website_ref_no=<ref>`; fallback `GET /api/transactions/getTransaction?transactionno=<txn>` (ignored if it belongs to another order). Rebuild caches the token (TTL from response, default 10 min) and uses a dedicated `api_login_secret` (see D-090) | SadadApi | F | E §6.5, D §0.7 |
| R-109 | Environment | `sadad_environment ∈ {auto, sandbox, live}` (default `auto`; unknown → auto). `auto` tries live then sandbox; explicit tries only that one | SadadApi | T | E §1.2.7 |
| R-110 | API status ids | 3 success; 2, 4, 7 failed; 1, 5, 6 in progress; a success row wins over earlier failed rows; non-numeric amount ignored; network/5xx → `unavailable`; unsafe ref → `unavailable` without request; never throws | SadadApi | F | E §6.5 |
| R-111 | Confirm rule | Confirm **only** when the API reports success AND the transaction's `website_ref_no` = this order (else `pending api_reference_mismatch`) AND a transaction number exists (else `review missing_transaction_number`) AND the amount matches the order total within **±0.005** (half a dirham-cent: 150.004 ≈ 150; 149.99 ≠ 150; non-numeric no match) (else `review amount_mismatch`) | PaymentDecision | F | D §1.11 |
| R-112 | Fail rule | Fail only on API `failed` (reason `sadad_reported_failure`). A signed failure alone → pending (order simply expires); an unsigned failure → ignored | PaymentDecision | F | E §6.5 |
| R-113 | Unverified success | Claimed success + API unavailable → `review`; + API not_found/in_progress → `pending`; API not consulted → `review` | PaymentDecision | F | E §6.5 |
| R-114 | Already confirmed | `noop` — the API is not called | PaymentProcessor | F | D §1.12 |
| R-115 | One transaction, one order | `orders.payment_id` UNIQUE (NULLs allowed); reuse → `transaction_already_used`; a second transaction number never overwrites the first (`already_confirmed`) | confirm_order_payment | F | E §2.4 |
| R-116 | Payment audit | Every processing writes exactly one `payment_events` row (source `callback|webhook|poll|admin`, checksum_valid, api_result, decision, outcome); payload capped at **4 000** characters (`{truncated:true, preview}`) | PaymentProcessor | F | E §1.2.12 |
| R-117 | Verify throttle | Public "check my payment": sadad only; status `pending` or `cancelled` with note in {payment_failed, expired, paid_after_expiry_no_stock}; created within **3 days**; at least **10 s** since `last_payment_check_at` (atomic claim). Admin verify bypasses the throttle (source `admin`), but never re-opens a final staff cancellation (R-058) | verify-payment | T | E §2.4 |
| R-118 | Result-page polling | Read status every **3 s**; call verify immediately then at most every **12 s** while `pending`; when `failed` verify exactly **once**; stop on confirmed/failed/cancelled/cash; after **120 s** show `delayed` with «تحقق مرة أخرى» (restarts a fresh 120 s window) | Payment result page (Livewire `wire:poll`) | T | A §12.2 |
| R-119 | Restart payment | Only `pending` + `sadad` → freshly signed request from stored line items and total; confirmed/failed/cash → 409 `not_payable`; unknown 404; not configured 503. Rebuild also refuses when `payment_expires_at` has passed | sadad-payment | F | D §2 |
| R-120 | Checkout failure | If building the signed request fails after the order exists → `mark_order_payment_failed(ref,'checkout_request_failed')` (frees seats) and 502 `payment_unavailable` | create-order | F | D §1 |
| R-121 | Callback response | 303 to `<base>/payment/result?ref=<ref>`; base = `site_url` then the stored `return_origin` if safe; no safe base → 200 HTML page (`تم استلام عملية الدفع`) with the ref; garbage never throws. Both `/payment/result` and `/sadad-callback` routes exist; query keys `ref`, `ORDERID`, `order` | Callback | F | E §6.5, A App F #20 |
| R-122 | Safe origin | `^(https://[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+(:\d+)?|http://localhost(:\d+)?)$` (i); no path/query; refuses `javascript:`, `data:`, protocol-relative, http non-localhost | return_origin, site_url | F | D §1.6 |
| R-123 | Webhook response | Always HTTP 200 `{"status":"success"}`, even for malformed bodies and internal errors; idempotent | sadad-webhook | F | E §6.5 |
| R-124 | Failure classification | `bank` if lower-cased message+code contains any of `card, بطاقة, declin, مرفوض, insufficient, رصيد, bank, بنك, expired card, منتهية, cvv, 3d, authorization, تفويض, issuer`; else `sadad` if any of `sadad, سداد, merchant, checksum, gateway, بوابة`; else `site`. One shared list in the rebuild | payment_errors, e-mails | F | A §13.2, D §1.13 |
| R-125 | Follow-ups on confirm | Exactly once (only the call that flips the status): invoice e-mail, admin sale alert, n8n `payment_confirmed` payload (skipped when the WhatsApp webhook is off); queued, never blocking the gateway response; failures never change the payment outcome | PaymentConfirmed listeners | F | E §6.5 |
| R-126 | Follow-ups on fail | `payment_errors` row (`error_code='PAYMENT_FAILED'`, source by R-124) + payment-failed e-mail; pending/review → nothing | PaymentFailed listeners | F | E §6.5 |
| R-127 | Manual verified flag | `sadad_manually_verified` boolean, admin ticks "verified manually in Sadad" | Orders | F | E §1.2.4 |

### 3.7 Gate check-in

| ID | Rule / constant | Value | Applies to | Kind | Source |
|---|---|---|---|---|---|
| R-128 | Check order | exists → event expired (R-003/R-004: `⏰ انتهت صلاحية التذكرة - الحدث انتهى في {date}`) → not today (`📅 هذه التذكرة ليست لليوم — صالحة في {date} فقط`) → already present (`تم استخدام التذكرة مسبقاً`; manual: `تم تسجيل حضور هذه التذكرة مسبقاً`) → paid (`⚠️ الدفع غير مؤكد - لا يمكن تسجيل الدخول`) → code not in `expired_qr_codes` (NEW enforcement, message `رمز التذكرة منتهي الصلاحية — تم نقل الحجز إلى فعالية أخرى`) → atomic admit | `CheckInService` | F | C §11 |
| R-129 | Paid for admission | Original: `confirmed`, or POS (`POS-` prefix or `cash_pos`) treated as paid **even when cancelled** (defect D-005). Rebuild: `confirmed` only, except web `cash_pos` + `pending` when setting `admit_unpaid_cash_bookings=true` (default true for parity, shows «تحصيل نقدي» banner); `cancelled` is never admitted | CheckInService | T | C §4.10, D §27 |
| R-130 | Single admission | Conditional `UPDATE … SET is_present=1 … WHERE id=? AND is_present=0`; 0 rows → "already used"; no re-entry; only an admin reset re-validates | CheckInService | F | E §6.6 C.4 |
| R-131 | Actor | Admitter id from the authenticated session (null for passcode/kiosk) plus display label (R-090); request-supplied ids ignored | CheckInService | F | D §27 |
| R-132 | Automation marker | On admit, if `n8n_responded_at` is null → `n8n_response_message='تم التسجيل يدوياً ✓'`, `n8n_responded_at=now` | CheckInService | F | C §4.10 |
| R-133 | Reset (undo scan) | Admin account only (`إلغاء المسح متاح للأدمن فقط`); only when present (`التذكرة غير ممسوحة أصلاً`); clears presence fields; audit `ticket_checkin_reset`; success `تم إلغاء المسح، التذكرة صالحة من جديد` | CheckInService | F | C §4.10 |
| R-134 | Lists | Scan history last **30** (all events); gate board recent **25**; gate board refresh **15 s**; issued = holders of `confirmed` orders of the current event (rebuild also counts admissible cash bookings, D-061) | Gate | T | C §3, §4.10 |
| R-135 | Camera | `html5-qrcode`, fps **10**, qrbox **250×250**, prefer camera label containing `back`/`rear`; duplicate-scan alert auto-closes after **4 000 ms** in camera mode then restarts camera; error buzzer on every error dialog | Scanner PWA | T | C §4.2, §4.8 |
| R-136 | Phone lookup at gate | Only confirmed (or admissible cash) tickets; only the current event's day (rebuild; original listed all dates) | Scanner | F | C §4.6 |

### 3.8 Authentication, roles and throttles

| ID | Rule / constant | Value | Applies to | Kind | Source |
|---|---|---|---|---|---|
| R-137 | Role precedence | admin > supervisor (old "moderator") > cashier/gatekeeper > passcode kiosk; legacy `admin_users` rows and `user_roles.role='admin'` both become `admin`; `moderator` → `supervisor` | Auth | F | B2 §0.2 |
| R-138 | Permission matrix | `/admin/dashboard/*`, `/live-visitors`: admin (and roles granted the page permission). Staff pages (`/staff`, `/staff/attendance`, `/staff/gate`, scanner, POS, POS receipts, ticket viewer, live bookings): any staff incl. passcode. Admin-only actions: reset scan, change event, cancel/recount/bin/restore, staff accounts, settings, receipts data, lifetime totals | Policies | F | B2 §0.3 |
| R-139 | Attendance editing | Read: any staff incl. passcode; write (`set`, `bulk_present`, `clear_day`): account staff (admin/supervisor) only (`سجّل الدخول بحسابك لتعديل الحضور`); `marked_by` from session | Attendance | F | B2 §25 |
| R-140 | Team passcode source | Only from secret `STAFF_PASSCODE`; unset/blank → passcode login OFF (`not_configured`); length < **8** → `too_short`; `@@@Qatar123` permanently revoked (`revoked`); exact, case-sensitive match after trimming surrounding spaces; constant-time compare | PasscodeLogin | F | E §6.5 |
| R-141 | Passcode session | Server session with role `passcode_kiosk`, **10-day** absolute expiry (UI text `تبقى الصلاحية فعّالة لمدة ١٠ أيام في هذا المتصفح، ثم تُطلب كلمة المرور من جديد.`); never stored in browser storage, never sent per request | PasscodeLogin | T | B2 §5 |
| R-142 | Passcode throttle (per caller) | Key `passcode:<ip>` (IP from `CF-Connecting-IP`, else first `X-Forwarded-For`, ≤ 64 chars, trusted-proxy aware): **5** wrong in **15 min** → blocked until **30 min** after the latest wrong attempt; attempts_left counts down from 5; a success ends the streak; further wrong attempts during a block extend it | PasscodeLogin | T | E §6.5, D §1.5 |
| R-143 | Passcode throttle (global) | Key `team-passcode`: **60** wrong in **15 min** → **15 min** block for everyone; only wrong guesses are counted; success never resets it | PasscodeLogin | T | E §6.5 |
| R-144 | Blocked answer | `{ok:false, blocked:true, attempts_left:0, retry_after_seconds>0}`, even for the right passcode; warm-up `__warmup__` → `{ok:false, warmup:true}`, not recorded; a log outage still answers correctly | PasscodeLogin | F | E §6.5 |
| R-145 | Account login throttle | Per e-mail **and** per IP: **5** failures in **15 min** → **30 min** lock measured from the latest failure; enforced inside the login controller (not advisory); UI lock fallback **1 800 s**; warning `تبقّت {n} محاولة قبل الإيقاف المؤقت` when ≤ **2** left; banner `حاول مجدداً بعد {m:ss} — تم إشعار الإدارة.` | Fortify login | T | B2 §4, D §22 |
| R-146 | Lockout alert | When a block starts: e-mail `login-alert` to `admin_email` at most once per identifier per **30-min** bucket (idempotency `login-block-<identifier>-<floor(now/30min)>`) + audit `login_blocked` (user_type `staff` for passcode, `admin` for accounts) | Login | F | D §22 |
| R-147 | Login attempt retention | Rows older than **7 days** pruned (daily) | `login_attempts` | T | E §2.12 |
| R-148 | Post-login routing | supervisor-only → `/staff`; admin → safe intended path (starts with `/`, not `//`) else `/admin/dashboard`; other accounts → signed out with `غير مصرح: الدخول للأدمن فقط` | Login | F | B2 §4 |
| R-149 | Admin 2FA (NEW) | TOTP required for `admin` and `super_admin` (Fortify) | Login | T (NEW) | Target stack |
| R-150 | Staff account management | Actions list/create (default role supervisor)/set role/revoke/reset password; rebuild forbids removing or demoting yourself and the last admin, validates roles, audits every action | Staff accounts | F | B2 §24 |
| R-151 | Internal/service auth | Service-to-service calls are in-process (no service key over HTTP). Inbound n8n report-back requires the shared secret `N8N_RESPONSE_SECRET` (header `X-Webhook-Secret` or Bearer, constant-time) — mandatory in the rebuild | n8n-response | F | D §16 |

### 3.9 Notifications, invoices, automation

| ID | Rule / constant | Value | Applies to | Kind | Source |
|---|---|---|---|---|---|
| R-152 | Auto-invoice interval | `auto_invoice_interval_seconds` default **60**, minimum **10** (`يجب أن يكون الفاصل الزمني 10 ثوانٍ على الأقل`); a run before the interval elapsed since `last_invoice_sent_at` does nothing | `SendInvoiceBatch` job | T | B2 §8, D §9 |
| R-153 | Batch size | `invoice_batch_min` default **1** (≥ 1, `يجب أن يكون الحد الأدنى 1 على الأقل`), `invoice_batch_max` default **10** (≥ min, `يجب أن يكون الحد الأقصى أكبر من أو يساوي الحد الأدنى`); size = `min(max, max(min, candidates))` | Invoice batch | T | B1 §3, B2 §8 |
| R-154 | Delay between sends | Random **300..600 s** (`invoice_send_delay_min/max`, min ≥ 1 s), between consecutive sends, not after the last; implemented as delayed queue jobs | Invoice batch | T | B1 §3 |
| R-155 | Candidates | `payment_method='sadad'`, `confirmed`, `n8n_response_message IS NULL` (or one of the "sending" markers when manually resent), oldest first, fetch `batch_max × 2`; skip orders whose event is done (R-003/R-004); skip configurable exclusion dates (original hard-coded 2025-11-06/07/08) | Invoice batch | T | D §9 |
| R-156 | Hold after attempts | `send_attempt_count ≥ 2` without a delivery answer (`n8n_responded_at` null or a "sending" marker) → «موقوفة» (on hold); manual resend only | Invoice batch | T | B1 §3 |
| R-157 | Sending markers | `جاري الإرسال إلى واتساب...` (WhatsApp), `جاري إرسال البريد الإلكتروني...` (e-mail) with `n8n_responded_at=NULL`; set only if the automation accepted the request | send-to-webhook | F | E §6.5 |
| R-158 | Which automation | Action `send_email` → e-mail webhook (fallback main webhook); `send_ticket`/others → main webhook; each null when its `*_enabled=false` → 400 `webhook_not_configured` naming the missing one | Automation relay | F | E §6.5 |
| R-159 | Upstream outcomes | Upstream 404 → HTTP 200 `{error:'Webhook not found or inactive', status:404, solution:'Please ACTIVATE the workflow in n8n (not just test mode).'}`; other non-2xx → 200 `{error:'Webhook returned error'}`; timeout (**10 s**) → 200 timed out; network → 200 failed | Automation relay | F | B1 §0.6 |
| R-160 | When n8n is told | Web cash order created → order-created payload in background; online orders only after payment (`payment_confirmed`, once); POS never (marked handled at creation) | Order events | F | E §6.5 |
| R-161 | WhatsApp number | Digits only via R-104 (`toWhatsAppNumber`), never "country code + raw phone" concatenation | n8n payloads | F | A §13.3 |
| R-162 | E-mail templates | `booking-invoice` (customer, after confirm), `payment-failed` (customer if `payment_failed_email_enabled`, admin copy always), `admin-sale-alert` (every confirmed sale incl. POS), `login-alert` (lockout) | Mailables | F | D §1.17 |
| R-163 | E-mail idempotency | Keys `booking-invoice-<order>-<attempt>`, `payment-failed-<order>`, `payment-failed-admin-<order>`, `admin-sale-<order>`, `login-block-<identifier>-<bucket>`; invoice not re-sent when `invoice_email_sent_at` set unless `force`; payment-failed/admin-sale not re-sent when a `sent` event exists unless `force` | Mail jobs | F | D §C.4 |
| R-164 | E-mail delivery statuses | `queued, retried, sent, failed, suppressed, skipped, bounced, complained, unsubscribed` recorded per send with attempt number and Arabic detail | email_delivery_events | F | D §10 |
| R-165 | Outbound timeouts | Internal HTTP 15 s (only for external calls now), webhook relay 10 s, paid-payload post 8 s, Sadad API 10 s (NEW), vision OCR 30 s (NEW) | HTTP clients | T | D §0.2, §1.13 |
| R-166 | Invoice links | Invoice URL `<APP_URL>/invoice/<urlencoded ref>` (original hard-coded `https://qcamelmc.org`) | E-mails | T | D §10 |
| R-167 | Brand strings | Card footer `شكراً لثقتكم — مهرجان قطر للإبل`; print footer `جميع الحقوق محفوظة © مهرجان قطر للإبل - جزيلات العطا - 2026` — become settings (`festival_name`, `season_label`) | Invoice PDF/card | T | A §13.6 |

### 3.10 Screens: page sizes, polling, debounces, caps

| ID | Rule / constant | Value | Applies to | Kind | Source |
|---|---|---|---|---|---|
| R-168 | Orders list | Page size **30**; search debounce **400 ms**; search covers reference, customer name, phone (server-side, paginated in rebuild; original merged ≤ 100 per sub-query unpaginated); "upcoming only" default ON (event date ≥ Qatar today); view mode remembered (`ordersViewMode`); realtime refresh debounce **1 500 ms**; ticket icon strip ≤ **8** icons | Admin orders | T | B1 §1 |
| R-169 | Transactions | Page size **30**; search debounce **300 ms**; default channel `sadad`; Sadad detail sync concurrency **3**; totals computed server-side (original looped ≤ 20 000 rows) | Admin transactions | T | B1 §2 |
| R-170 | Payment errors | Original latest **500** rows client-side; rebuild paginated 50/page | Admin payment errors | T | B1 §4 |
| R-171 | Activity log | Original latest **500**; rebuild paginated 50/page with filters | Admin logs | T | B2 §19 |
| R-172 | Notifications | Original **200** rows; unread badge poll **30 s** + realtime; badge `+99` above 99; read state per admin (NEW `users.notifications_seen_at`) | Admin shell | T | B2 §1, §16 |
| R-173 | Booked tickets by date | Silent refresh **10 s** + realtime (rebuild: realtime only, 30 s fallback poll); original pages of 1 000 up to 40 pages; recycle bin latest **500** | Admin tickets | T | B1 §10 |
| R-174 | Tickets inventory | Sold % colour: ≥ **90** red, ≥ **70** yellow, else green (capacity 0 → 100 %) | Admin tickets | T | B1 §7 |
| R-175 | Events capacity badge | > **80 %** destructive, > **50 %** default, else secondary; home page **9** events per page | Admin events, home | T | B2 §9, A §1.1 |
| R-176 | Capacity alert levels | `pct = round(confirmed/capacity×100)` (0 if capacity 0): critical ≥ **100** (`تجاوز السعة!`), warning **90–99** (`تحذير - السعة تقارب الامتلاء (90%+)`), nearing **80–89** (`السعة تقترب (80%+)`), normal < 80 (`السعة متاحة`); hidden after the cut-off (R-003/R-004) | Admin shell, POS | T | B2 §14 |
| R-177 | Capacity increase notice | Toast `🎉 تذاكر جديدة متاحة!` **8 000 ms**; banner auto-hide **10 s** (POS) / **4 s** (Live Bookings); progress bar must match the auto-hide (D-079) | POS, Live Bookings | T | B2 §15, C §8 |
| R-178 | Live bookings | "Recent" highlight **5 min**, re-render tick **30 s**, new-holder glow **5 s**, refresh debounce **1 500 ms**, last-hour KPI **60 min**; toasts POS 6 s / online 5 s / check-in 5 s; daily-sales lower bound = season start (setting; original hard-coded `2026-01-12`) | Live bookings | T | C §8.2 |
| R-179 | Visitors presence | Heartbeat original **5 min**, stale cut-off **5 min**; rebuild heartbeat **60 s**, active = seen within **3 min**, prune rows older than **5 min** every minute | Visitor tracking | T | B2 §21, C §9 |
| R-180 | Visitor screens | Live visitors poll **15 s**; visitors tab poll **30 s**; visitors vs scans ranges 1 / **7** (default) / 30 days | Analytics | T | C §9, B2 §21–22 |
| R-181 | Attendance | Poll **30 s**; week strip = 7 days ending at the selected date; tapping the active status clears it (row deleted) | Attendance | T | C §2 |
| R-182 | Debounces | Customer lookup **250 ms**; POS phone autocomplete **350 ms**; manual check-in search **300 ms** | POS | T | C §5.3, §5.9 |
| R-183 | Checkout toasts | Reservation failure toast **6 000 ms**; n8n 404 toasts **8 000 ms** | UI | T | A §3.1, B1 §1 |
| R-184 | Fireworks | POS success animation **6 000 ms** | POS | T | C §5.3 |

### 3.11 Caching, uploads, QR rendering, retention

| ID | Rule / constant | Value | Applies to | Kind | Source |
|---|---|---|---|---|---|
| R-185 | Public caches | Events list **2 min**, public settings **5 min**, Clarity config **10 min**, visitor geo **30 min**, chunk-reload guard **5 min**. Rebuild: server cache `public.events` 60 s and `public.settings` 300 s, both flushed on admin save (so `current_event_id` switches instantly) | Public site | T | A App E |
| R-186 | Event fetch retry | 3 attempts, **10 s** timeout each, back-off 2 s then 4 s (client) | Public home | T | A §1.1 |
| R-187 | Branding uploads | Logo PNG/JPG ≤ **5 MB** (`حجم الصورة يجب أن يكون أقل من 5 ميغابايت`); hero PNG/JPG ≤ **10 MB**; before-footer PNG ≤ **10 MB**; header background PNG/JPG ≤ **10 MB** (`حجم الصورة يجب أن يكون أقل من 10 ميغابايت`); previous file removed on replace (NEW) | Settings | T | B2 §8 |
| R-188 | Receipt photo | Base64 ≤ **8 000 000** chars (≈ 6 MB binary) → else 413 `Image is too large`; rebuild: multipart upload ≤ 6 MB, `image/jpeg|png|webp|heic` | POS receipts | T | C §6.2 |
| R-189 | Stored QR PNG | 800 px, margin 2, error correction **M**, PNG, file `<code>.png` on disk `qr`; QR failure never fails the order (ticket works by code) | QrService | F | D §1.16 |
| R-190 | On-screen QR sizes | Admin order dialog 800 px margin 2 ECC **H**; ticket viewer 400 px margin 2 ECC H; invoice card/PDF 512 px margin 1; enlarged 288 px; customer dialog 200/500 px margin 2 | UIs | F | B1 §1, A §7.2, §10.1 |
| R-191 | QR backfill | Batch **100** holders missing a picture per run; continues past failures; returns `{updated, skipped, errors[]}` | Admin tool | T | B1 §0.6 |
| R-192 | Payment payload cap | 4 000 chars stored (R-116) | payment_events | F | E §1.2.12 |
| R-193 | Visitor staleness | Rows with `last_seen_at < now − 5 min` deleted (R-179) | active_visitors | T | E §2.12 |
| R-194 | Recycle bin | Admin only; moving a ticket stores holder fields **including `qr_image_url`** (fix) plus context `{booking_reference, payment_status, payment_method, event_title, event_date, customer_name}`, `deleted_by`, `deleted_by_name`; seat released; restore re-inserts with the **same id**, re-checks stock and per-person cap, refuses when the order no longer exists (`تعذر الاسترجاع — الحجز الأصلي لم يعد موجوداً`); messages `تم نقل التذكرة إلى سلة المحذوفات`, `تم استرجاع التذكرة`, `العنصر غير موجود في السلة`, `التذكرة غير موجودة`, `هذه العملية متاحة للأدمن فقط`; purge = permanent delete | Recycle bin | F | E §2.8 |
| R-195 | Delete toggles | `show_delete_customer_button` default false (also gates order delete in the original — split in the rebuild), `show_delete_event_button` false, `show_delete_ticket_button` **true**, `show_generate_qr_button` false | Settings | T | E §1.2.7 |
| R-196 | Destructive deletes | Events, orders and customers are soft-deleted in the rebuild; hard purge only from the trash by `super_admin`; events with orders cannot be deleted (archive instead) | Admin | F | E §8 #14 |
| R-197 | Archive | Archive sets `is_archived=true, archived_at=now, is_active=false`; restore clears archive (stays inactive); "start new event" may archive all non-archived events and sets `current_event_id` — in one transaction | Events | F | B2 §9, §11 |
| R-198 | Excel sheets | Sheet names ≤ **31** chars (`فعالية {dd-MM-yyyy}`, `سداد {dd-MM-yyyy}`, `جميع السجلات`, `جميع مشتريات سداد`); status sort confirmed→pending→cancelled | Reports | F | B2 §18 |
| R-199 | POS user icons | 20 emoji in order `⭐ 🚗 🌙 🐉 🦁 🦅 🐺 🦊 🐻 🦋 🔥 ⚡ 💎 🎯 🌟 🚀 🎪 🎭 🎨 🏆`; new user gets the first unused, else random; default `⭐` | POS users | F | B2 §23 |
| R-200 | Receipt summary prices | Original hard-coded normal 200 / VIP 300 / parking 500 QAR; rebuild uses the current event's `ticket_types.price` | POS receipts | F | C §6.2 |
| R-201 | Tracking exclusions | Visitor tracking excludes `/admin*`, `/staff*` (fix), `/live-*`, `/pos*`, `/scanner*`; Clarity excludes `/admin`, `/staff`, `/live-bookings`, `/live-visitors`, `/sadad-redirect`, `/payment`, `/sadad-callback` and (NEW) `/invoice`, `/confirmation` | Analytics | F | A §12.1, §13.12 |
| R-202 | robots.txt | `Disallow: /admin/ /staff /live-bookings /live-visitors /checkout /confirmation /invoice/ /payment/ /sadad-callback` | Public | F | E §7.7 |
| R-203 | Data privacy | National ID (`id_number`) visible to admins only (public invoice API, passcode staff lookups and printed tickets of non-admins omit it); IPs shown only to admins | All read models | F | E §2.7, D §0.7 #10 |

### 3.12 Status & enum dictionary

Laravel: every enum is a PHP 8.3 backed enum (`App\Enums\…`) with a `label()` method returning the Arabic text below; DB columns are `string` + CHECK (no native MySQL `ENUM`, for portability).

| Enum (PHP) | Value | Meaning | Arabic label(s) |
|---|---|---|---|
| `PaymentStatus` (stored `orders.payment_status`) | `pending` | Created, waiting for payment (online hold or cash at venue) | `قيد الانتظار` |
| | `confirmed` | Paid / sold (POS at creation) | `مدفوع / مؤكد`, `مؤكد` |
| | `cancelled` | Inactive; reason in `payment_note` | `ملغي`, `ملغية` |
| `PublicPaymentStatus` (derived, API only) | `failed` | `cancelled` with note ∈ {payment_failed, expired, paid_after_expiry_no_stock}; page offers retry | `فشل`, `لم تكتمل عملية الدفع` |
| | `pending` / `confirmed` / `cancelled` | as above; staff cancellation stays `cancelled`; `payment_note` never exposed | — |
| `PaymentNote` (`orders.payment_note`, nullable) | `payment_failed` | Sadad API reported failure | `فشل الدفع` |
| | `expired` | 30-min hold ran out | `انتهت مهلة الدفع` |
| | `paid_after_expiry_no_stock` | Paid after expiry, no seats → refund | `دُفع بعد انتهاء الحجز ولا توجد تذاكر` |
| | `admin_cancelled` | Staff cancellation without note | `ألغاه المسؤول` |
| | `checkout_request_failed` | Signed request could not be built | `تعذر إنشاء طلب الدفع` |
| | free text (`released by admin`, `returned by admin`, …) | Staff note | shown verbatim |
| `PaymentMethod` | `sadad` | Online card via Sadad | `سداد (أونلاين)`, `دفع إلكتروني`, `Online` |
| | `cash_pos` | Cash at venue (web) or POS sale | `نقاط البيع`, `نقداً / نقطة بيع`, `نقدي / نقاط البيع`, `POS` |
| `OrderSource` (NEW column `orders.source`) | `web` / `pos` | Channel (original derived it from the reference prefix) | `الموقع` / `نقطة البيع` |
| `TicketType` | `vip` | VIP admission | `VIP`, `تذكرة دخول VIP`, `تذكرة VIP` (Sadad line) |
| | `normal` | General admission | `عادي`, `تذكرة دخول عادية`, `الدخول العام`, `تذكرة دخول عامة` |
| | `parking` | Parking permit (excluded from per-person cap) | `مواقف`, `موقف`, `تذكرة موقف السيارات`, `المواقف`, `تصريح مواقف` |
| `Role` (spatie) | `super_admin` (NEW) | Owner; purge, staff roles, secrets | `مالك النظام` |
| | `admin` | Full back-office (was `admin_users`/`admin` role) | `مدير`, `الأدمن` |
| | `supervisor` | Was `moderator`: staff pages, attendance edit, sell, scan | `مشرف` |
| | `cashier` (NEW) | POS + receipts | `كاشير` |
| | `gatekeeper` (NEW) | Scanner + gate board | `موظف بوابة` |
| | `accountant` (NEW) | Read finance, exports | `محاسب` |
| | `viewer` (NEW) | Read-only dashboards | `مشاهد` |
| | `passcode_kiosk` (guard, not a user role) | Team passcode session: scan, POS, view | `فريق العمل (كلمة مرور الفريق)` |
| `PaymentEventSource` | `callback`, `webhook`, `poll`, `admin` | What triggered processing | `رجوع العميل`, `إشعار سداد`, `استعلام العميل`, `تحقق المسؤول` |
| `PaymentDecisionAction` | `confirm`, `fail`, `pending`, `review`, `noop`, `none` | Decision of the payment rule | `تأكيد`, `فشل`, `قيد الانتظار`, `مراجعة`, `لا شيء` |
| `PaymentOutcome` | `confirmed`, `already_confirmed`, `transaction_already_used`, `paid_after_expiry_no_stock`, `failed`, `not_pending`, `pending`, `review`, `noop`, `order_not_found` | Result recorded in `payment_events.outcome` | shown raw + Arabic tooltip |
| `SadadApiResult` | `success`, `failed`, `in_progress`, `not_found`, `unavailable` | Merchant API lookup | `ناجحة`, `فشلت`, `قيد المعالجة`, `غير موجودة`, `غير متاح` |
| `SadadLiveStatus` (transactions screen) | `INPROGRESS`/`IN PROGRESS`, `FAILED`, `SUCCESS`, `REFUND`/`REFUNDED`, `PENDING`, `ONHOLD`/`ON HOLD`, `REJECTED`, unknown | Sadad `transactionstatus.name` | `قيد المعالجة`, `فشلت`, `ناجحة`, `مستردة`, `معلقة`, `موقوفة`, `مرفوضة`, raw / `غير معروف` |
| `PaymentErrorSource` | `sadad` / `bank` / `site` | Where a payment failed | `سداد` / `البنك / البطاقة` / `الموقع` |
| `PaymentErrorCode` | `PAYMENT_FAILED`, `CHECKOUT_FAILED`, `TICKETS_UNAVAILABLE`, `TICKET_LIMIT` | Logged failure kind | shown raw |
| `EmailDeliveryStatus` | `queued` / `retried` / `sent` / `failed` / `suppressed` / `skipped` / `bounced` / `complained` / `unsubscribed` | Mail event | `في قائمة الإرسال` / `إعادة محاولة` / `تم الإرسال` / `فشل الإرسال` / `محظور من الاستقبال` / `تم التخطي` / `ارتد البريد` / `بلاغ إزعاج` / `إلغاء الاشتراك` |
| `EmailTemplate` | `booking-invoice`, `payment-failed`, `admin-sale-alert`, `login-alert` | Mailable key | `فاتورة الحجز`, `فشل الدفع`, `إشعار الإدارة ببيع جديد`, `تنبيه محاولات الدخول الفاشلة` |
| `InvoiceDispatchState` (derived) | `pending`, `sent`, `event_done`, `on_hold` | Invoice tab categories (R-155/156) | `قيد الانتظار`, `تم الإرسال`, `انتهت الفعالية`, `موقوفة` |
| `AttendanceStatus` | `present` / `absent` (no row = unmarked) | Staff daily attendance | `حاضر` / `غائب` / `غير محدد` |
| `ActivityType` (widened) | `pos_form`, `invoice_download`, `qr_search`, `ticket_scan`, `ticket_checkin_reset`, `event_change`, `new_event_started`, `login_blocked`, plus NEW `order_cancelled`, `order_deleted`, `stock_recount`, `settings_changed`, `staff_role_changed`, `ticket_binned`, `ticket_restored`, `price_changed`, `capacity_changed` | Audit category (spatie `log_name`) | `نموذج POS`, `تحميل فاتورة`, `بحث QR`, `فحص تذكرة`, `إلغاء مسح`, `تغيير الفعالية`, `بدء فعالية جديدة`, `حظر دخول`, … |
| `ActorType` (widened) | `admin`, `guest`, `system`, `staff` | Who acted | `مدير`, `ضيف`, `نظام`, `فريق` |
| `ExpiredQrReason` | `event_date_changed` | Why an old code is invalid | `تم تغيير موعد الفعالية` |
| `SadadEnvironment` | `auto` / `sandbox` / `live` | Gateway environment | `تلقائي (يُكتشف من مفتاحك)` / `تجريبي (Sandbox)` / `فعلي (Live)` |
| `CheckInMode` | `checkin`, `lookup`, `search`, `manual`, `history`, `reset` | Gate API operations | — |
| `CapacityLevel` | `critical`, `warning`, `nearing`, `normal` | R-176 | `تجاوز السعة!`, `تحذير`, `السعة تقترب`, `السعة متاحة` |
| `PaymentPhase` (result page) | `checking`, `confirmed`, `failed`, `delayed`, `not_found`, `cash` | UI state | `جارٍ التحقق من الدفع...`, `تم الدفع بنجاح!`, `لم تكتمل عملية الدفع`, `ما زلنا نتحقق من دفعتك`, `لم نعثر على الحجز`, (redirect) |
| `RefundStatus` (NEW) | `requested`, `processing`, `completed`, `rejected` | Refund workflow | `مطلوب`, `قيد التنفيذ`, `مكتمل`, `مرفوض` |
| `DeviceKind` (NEW) | `scanner`, `pos`, `board` | Registered kiosk device | `ماسح`, `نقطة بيع`, `لوحة` |
| `ScanQueueResult` (NEW) | `admitted`, `already_used`, `rejected`, `pending` | Offline scan sync result | `تم الدخول`, `مستخدمة مسبقاً`, `مرفوضة`, `بانتظار المزامنة` |
| `DeviceType` / `TrafficSource` (analytics) | `desktop|mobile|tablet`; `direct, facebook, twitter, instagram, linkedin, tiktok, youtube, whatsapp, telegram, snapchat, google, bing, yahoo, duckduckgo, internal, referral` | Visitor classification | `سطح المكتب`, `الجوال`, `التابلت` |

API error codes (HTTP → Arabic text) are fixed: `insufficient_stock` 409 (+ ` (VIP|الدخول العام|المواقف)`), `quantity_limit_exceeded` 422, `below_minimum_amount` 422, `event_not_available` 409, `too_many_pending_orders` 429, `payment_unavailable` 503/502, `validation_failed` 400, `invalid_tickets` 422, `holders_mismatch` 422, `ticket_limit_exceeded` 422 (server text), `unauthorized` 401 (`انتهت الجلسة أو كلمة مرور الفريق غير صحيحة. يرجى تسجيل الدخول من جديد.`), `forbidden` 403 (`ليست لديك صلاحية لتنفيذ هذا الإجراء.`), `not_payable` 409, `order_not_found` 404, `invalid_reference` 400, `network_error` (client, `تعذر الاتصال بالخادم. تحقق من اتصالك بالإنترنت وحاول مرة أخرى.`), anything else `internal_error` 500 (`حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.`, no internals). Error JSON shape: `{success:false, code, error, ...extra}`.

## 4. Data model for Laravel

### 4.0 Conventions

- **Engine/charset:** MySQL 8.0.16+ InnoDB, `utf8mb4` / `utf8mb4_unicode_ci` (Arabic search is accent-insensitive enough; use `utf8mb4_0900_ai_ci` if all servers are 8.0+). No vendor-only SQL in application code; migrations run unchanged on PostgreSQL.
- **Keys:** every original table keeps its **UUID** primary key (`$table->uuid('id')->primary()`, model trait `HasUuids`) so ids from Supabase are preserved and URLs stay non-sequential. Exceptions: `payment_events` keeps `bigint` auto-increment (as original); Laravel/spatie infrastructure tables keep their default keys. FKs to users are `foreignUuid()` because user ids come from Supabase `auth.users`.
- **Timestamps:** `timestamp` columns hold UTC (`APP_TIMEZONE=UTC`); display in Asia/Qatar (R-001). Use `$table->timestamps()` where the original had `created_at`/`updated_at`; `created_at` is NOT NULL everywhere (fix of nullable originals).
- **Money:** `decimal(10,2)` with `'decimal:2'` cast; arithmetic in integer dirhams through `App\Support\Money` (never floats).
- **Enums:** `string(…)` columns + PHP backed enum casts + CHECK constraints added in the migration with `DB::statement('ALTER TABLE … ADD CONSTRAINT … CHECK (…)')` (ANSI syntax valid on MySQL 8.0.16+ and PostgreSQL). Never native MySQL `ENUM`.
- **JSON:** PostgreSQL `jsonb` → `json` column + `'array'`/`AsArrayObject` cast.
- **Partial indexes:** MySQL has none. Rules used below: (a) a partial UNIQUE on a nullable column becomes a plain UNIQUE (InnoDB allows many NULLs); (b) a partial filter index becomes a composite index whose leading column is the filter column; (c) expression indexes (`lower(x)`) become a STORED generated column + index.
- **Soft deletes:** `events`, `customers`, `orders`, `pos_users`, `popup_banners` get `softDeletes()` (fix of destructive cascades, E §8 #14). Financial FKs use `restrictOnDelete()`; purge is a deliberate `super_admin` action.
- **Realtime:** tables that were in the Supabase publication (plus the two the UI expected) broadcast Laravel events over Reverb (see 4.5). Broadcasting happens from domain events dispatched after commit (`ShouldDispatchAfterCommit`), never from raw DB triggers.
- **Rename policy:** original names are kept except where a rename is clearly better; each rename is called out with **Rename:** and listed again in the migration mapping (4.9).

### 4.1 Identity and access

#### `users` — NEW (replaces Supabase `auth.users`, `admin_users`, `user_roles`)
Model `App\Models\User` (`HasUuids`, `Notifiable`, `HasRoles`, `TwoFactorAuthenticatable`, `SoftDeletes`, `HasApiTokens`).

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid('id')->primary()` | — | = Supabase `auth.users.id` (preserved) |
| name | `string('name', 100)` | NOT NULL | from auth metadata or e-mail local part |
| email | `string('email', 254)->unique()` | NOT NULL | lower-cased on save |
| email_verified_at | `timestamp` | NULL | |
| password | `string('password')` | NOT NULL | bcrypt; Supabase `$2a$` hashes verify with `password_verify` (rotate anyway, D-001) |
| two_factor_secret / two_factor_recovery_codes | `text` | NULL | Fortify (encrypted) |
| two_factor_confirmed_at | `timestamp` | NULL | Fortify |
| is_active | `boolean` | default true | revoke = false (+ remove roles) |
| last_login_at, last_login_ip | `timestamp`, `string(64)` | NULL | |
| notifications_seen_at | `timestamp` | NULL | NEW per-admin read state (was localStorage `admin:notifications:lastSeenAt`) |
| remember_token | `rememberToken()` | NULL | |
| created_at / updated_at / deleted_at | `timestamps()`, `softDeletes()` | | |

Relations: `confirmedOrders()` hasMany Order (`confirmed_by`), `checkIns()` hasMany TicketCheckin, `posReceipts()` hasMany PosReceipt (`created_by`). Policies use `$user->hasRole()/can()`. Service: `StaffAccountService` (create/set role/revoke/reset password, last-admin & self protection, audit) replaces edge fn `manage-staff-users`.

#### spatie/laravel-permission tables — NEW
`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` (package migration; enable `uuid` morph key: `model_morph_key = 'model_uuid'`, `$table->uuid('model_uuid')`). Seeded roles: `super_admin`, `admin`, `supervisor`, `cashier`, `gatekeeper`, `accountant`, `viewer` (3.12). Permissions (examples, one per screen/action): `orders.view`, `orders.cancel`, `orders.delete`, `orders.change_event`, `payments.verify`, `stock.recount`, `tickets.bin`, `tickets.restore`, `tickets.reset_scan`, `customers.view_pii`, `settings.manage`, `staff.manage`, `pos.sell`, `scanner.admit`, `gate.view`, `attendance.edit`, `receipts.manage`, `reports.export`, `logs.view`, `visitors.view`.
- Mapping: every `admin_users.id` and `user_roles(role='admin')` → role `admin`; `user_roles(role='moderator')` → `supervisor`. `is_admin()` / `has_role()` RPCs → `Gate::before` for `super_admin`, `$user->hasRole('admin')`.

#### `login_attempts` (kept)
Model `LoginAttempt` (`HasUuids`, prunable).

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid` PK | | |
| identifier | `string('identifier', 160)` | NOT NULL | lower-cased e-mail, `passcode:<ip>`, or `team-passcode` |
| kind | `string('kind', 16)` | default `account` | CHECK `kind IN ('account','passcode')` |
| ip_address | `string(64)` | NULL | |
| user_agent | `string(300)` | NULL | truncated |
| success | `boolean` | default false | |
| attempted_at | `timestamp` | default CURRENT_TIMESTAMP | |
| created_at | `timestamp` | default CURRENT_TIMESTAMP | |

Indexes: `(identifier, attempted_at)` (original DESC; InnoDB scans backwards fine), `(attempted_at)`. Used by `LoginThrottle` (pure port of `evaluateThrottle`, R-142–R-145) in addition to Laravel `RateLimiter` (Redis) — the table is the audit trail and the source for the lockout window. `MassPrunable` → `attempted_at < now()-7 days` (R-147), scheduled daily. Replaces edge fns `login-guard`, `staff-auth` and RPC `cleanup_login_attempts`.

#### `kiosk_devices` — NEW
Registered scanner/POS/board devices for passcode sessions and offline scanning. Model `KioskDevice`.

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid` PK | | |
| name | `string(80)` | NOT NULL | e.g. «بوابة 1» |
| kind | `string(16)` | NOT NULL | CHECK in (`scanner`,`pos`,`board`) |
| token_hash | `string(64)->unique()` | NOT NULL | SHA-256 of device token (shown once) |
| pos_user_id | `foreignUuid` → `pos_users` | NULL, `nullOnDelete` | default staff label |
| last_seen_at, last_ip | `timestamp`, `string(64)` | NULL | |
| revoked_at | `timestamp` | NULL | |
| created_by | `foreignUuid` → `users` | NULL, `nullOnDelete` | |
| timestamps | | | |

#### Laravel infrastructure — NEW
`sessions` (database or Redis driver; passcode sessions carry `guard=passcode_kiosk`, absolute expiry 10 days R-141), `password_reset_tokens`, `personal_access_tokens` (Sanctum, `uuidMorphs('tokenable')`, REST API v1), `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, Pulse tables (`pulse_values`, `pulse_entries`, `pulse_aggregates`). Default migrations; no business data.

### 4.2 Catalogue

#### `events` (kept)
Model `Event` (`HasUuids`, `SoftDeletes`, `InteractsWithMedia`).

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid` PK | | preserved |
| title | `string('title', 200)` | NOT NULL | |
| description | `text` | NULL | |
| event_date | `timestamp` | NOT NULL | start date-time (UTC); drives reference date (R-007) |
| end_date | `timestamp` | NULL | multi-day seasons; used by R-004/R-005 |
| start_time / end_time | `time` | NULL | display times; CHECK `end_time > start_time` when both set |
| location | `string(200)` | NOT NULL | |
| image_url | `string(2048)` | NULL | legacy URL; new uploads via media collection `cover` |
| video_url | `string(2048)` | NULL | |
| is_active | `boolean` | NOT NULL default true | (original nullable) |
| is_archived | `boolean` | NOT NULL default false | |
| archived_at | `timestamp` | NULL | |
| display_order | `integer` | NOT NULL default 0 | |
| sales_cutoff_time | `time` | NOT NULL default `'18:00:00'` | NEW (R-004) |
| checkin_cutoff_time | `time` | NOT NULL default `'18:00:00'` | NEW (R-004) |
| timestamps, deleted_at | | | |

Indexes: `(display_order)`, `(is_archived)`, `(is_active, is_archived)`, NEW `(event_date)`. Relations: `ticketTypes()` hasMany, `orders()` hasMany, `paymentErrors()` hasMany. Scopes (replace view/RPC logic): `scopePublic()` (active, not archived), `scopeCurrentSeason()`, `scopeArchived()`, `scopeBookable(now)` (R-003/R-004), `scopeToday()` (R-005). Casts: `event_date`, `end_date`, `archived_at` → `immutable_datetime`; `is_*` → bool. Observer `EventObserver`: `updated_at` (was trigger `update_events_updated_at`); refuse delete when orders exist (R-196); on soft delete clear `EventSettings::current_event_id` (was FK `ON DELETE SET NULL`); flush `public.events` cache (R-185). Services: `StartNewEventAction` (archive previous + create + 3 ticket types + set current event, one transaction), `ArchiveEventAction`.

#### `ticket_types` — **Rename:** from `tickets` (the original name collides with "issued tickets" = holders)
Model `TicketType` (`HasUuids`).

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid` PK | | preserved (`tickets.id`, referenced by clients as `ticket_id`) |
| event_id | `foreignUuid('event_id')->constrained()->restrictOnDelete()` | NOT NULL | original CASCADE → RESTRICT (events are soft-deleted) |
| type | `string(16)` | NOT NULL | CHECK in (`vip`,`normal`,`parking`) |
| price | `decimal(10,2)` | NOT NULL | CHECK `price >= 0` |
| available_quantity | `unsignedInteger` | NOT NULL | **total capacity** (name kept for continuity; accessor `capacity`) |
| sold_quantity | `unsignedInteger` | NOT NULL default 0 | seats held by pending+confirmed (R-045); CHECK `sold_quantity <= available_quantity` is **not** added (an admin may lower capacity only to ≥ held, enforced in service R-051; legacy over-sold rows exist) |
| description | `string(500)` | NULL | |
| timestamps | | | original had only `created_at` |

Indexes: UNIQUE `(event_id, type)`; `(event_id)` covered by the unique. Relations: `event()` belongsTo; `holders()` hasMany TicketHolder (via NEW `ticket_holders.ticket_type_id`); `orderItems()` hasMany. Accessor `remaining = available_quantity - sold_quantity`. Casts: `type` → `TicketType` enum, `price` → `decimal:2`. Observer `TicketTypeObserver::updated` → broadcast `TicketTypeStockChanged` and, when `available_quantity` increased, `TicketCapacityIncreased` (R-177); audit `price_changed`/`capacity_changed`. Direct updates of `sold_quantity` are allowed only inside `StockService` (guarded by `TicketType::$stockGuard` flag; observer throws otherwise).

#### `popup_banners` (kept)
Model `PopupBanner` (`HasUuids`, `SoftDeletes`, `InteractsWithMedia`): `id` uuid; `title` string(200) NN; `message` text NN; `image_url` string(2048) NULL; `is_active` boolean NN default **false**; timestamps; deleted_at. Scope `active()`. Original public SELECT of active rows → public read model. (The feature was retired in the UI; keep table for data parity, admin CRUD optional.)

### 4.3 Sales

#### `customers` (kept)
Model `Customer` (`HasUuids`, `SoftDeletes`).

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid` PK | | |
| name | `string(100)` | NOT NULL | |
| email | `string(254)` | NOT NULL default `''` | `''` = none (kept for parity); accessor returns null |
| phone | `string(32)` | NOT NULL | national number as entered (legacy) |
| phone_e164 | `string(20)` | NULL | NEW normalised (R-088); indexed |
| country_code | `string(5)` | NULL default `'+974'` | |
| nationality | `string(60)` | NULL | |
| id_number | `string(30)` | NULL | PII: hidden from non-admins (`$hidden` + policy) |
| person_key | `string(20)` | NULL | NEW: R-021 key computed in PHP on save (portable, no generated column); indexed |
| timestamps, deleted_at | | | |

Indexes: `(phone)`, NEW `(phone_e164)`, `(person_key)`, `(created_at)`. Relations `orders()` hasMany. Customer rows stay one-per-order (original semantics, R-038); a de-duplicated "people" view is the read model `CustomerDirectory` (group by `person_key`). Original FORCE RLS/policies → `CustomerPolicy` (admin only; `customers.view_pii` for ID numbers).

#### `orders` (kept)
Model `Order` (`HasUuids`, `SoftDeletes`).

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid` PK | | preserved |
| customer_id | `foreignUuid()->constrained()->restrictOnDelete()` | NOT NULL | original CASCADE |
| event_id | `foreignUuid()->constrained()->restrictOnDelete()` | NOT NULL | original CASCADE |
| source | `string(8)` | NOT NULL default `web` | NEW; CHECK in (`web`,`pos`); back-filled from prefix |
| ticket_type | `string(16)` | NOT NULL | label = most expensive (R-031); CHECK in enum |
| quantity | `unsignedSmallInteger` | NOT NULL | CHECK `quantity > 0` (was trigger `validate_order_amount`) |
| total_amount | `decimal(10,2)` | NOT NULL | CHECK `total_amount >= 0` |
| payment_method | `string(16)` | NOT NULL | CHECK in (`sadad`,`cash_pos`) |
| payment_status | `string(16)` | NOT NULL default `pending` | CHECK in (`pending`,`confirmed`,`cancelled`) (original nullable) |
| payment_note | `string(255)` | NULL | R-057, enum `PaymentNote` or free text |
| payment_error_reason | `string(500)` | NULL | |
| payment_id | `string(100)->unique()` | NULL | Sadad transaction number; plain UNIQUE replaces partial unique `orders_payment_id_key` (NULLs allowed on MySQL & PG) |
| booking_reference | `string(60)->unique()` | NOT NULL | R-059/R-062 |
| qr_code | `string(100)` | NULL | legacy order-level code (event change wrote it); read-only |
| payment_expires_at | `timestamp` | NULL | R-041 |
| paid_at | `timestamp` | NULL | |
| confirmed_at | `timestamp` | NULL | payment confirmation time (NOT gate scan) |
| confirmed_by | `foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete()` | NULL | POS seller account / manual confirmer |
| pos_user_id | `foreignUuid()->nullable()->constrained('pos_users')->restrictOnDelete()` | NULL | cashier (NO ACTION in original) |
| kiosk_device_id | `foreignUuid()->nullable()->constrained()->nullOnDelete()` | NULL | NEW: device used for a passcode POS sale |
| is_present | `boolean` | NOT NULL default false | legacy order-level attendance (read-only; legacy check-in branch) |
| sadad_manually_verified | `boolean` | NOT NULL default false | R-127 |
| n8n_response_message | `string(500)` | NULL | automation status text (R-157, R-132) |
| n8n_responded_at | `timestamp` | NULL | |
| send_attempt_count | `unsignedTinyInteger` | NOT NULL default 0 | R-156 |
| invoice_email_sent_at | `timestamp` | NULL | R-163 |
| return_origin | `string(255)` | NULL | safe origin only (R-122) |
| last_payment_check_at | `timestamp` | NULL | R-117 throttle |
| review_required_at | `timestamp` | NULL | NEW: set when a decision is `review` or a gateway pays a final-cancelled order (D-012) |
| timestamps, deleted_at | | | `created_at` NOT NULL |

Indexes: UNIQUE `booking_reference`; UNIQUE `payment_id`; `(event_id, payment_status)` (one copy; the two duplicates in the original are dropped); `(created_at)`; `(payment_status, payment_expires_at)` (replaces partial `orders_pending_expiry_idx`); `(pos_user_id)`; `(sadad_manually_verified)` (replaces partial index; low cardinality is acceptable, or drop); `(customer_id)`; NEW STORED generated column `needs_dispatch` = `(n8n_response_message IS NULL)` + index `(payment_method, payment_status, needs_dispatch, created_at)` for the invoice queue (portable; avoids a MySQL prefix index). Dropped: `idx_orders_booking_reference` (dup of unique), `orders_event_status_idx` (dup), `idx_orders_is_present` (legacy).

Relations: `customer()`, `event()`, `holders()` hasMany TicketHolder, `items()` hasMany OrderItem, `posUser()`, `confirmer()` (User), `paymentEvents()`, `paymentErrors()`, `emailDeliveryEvents()`, `refunds()`, `paymentAttempts()`, `automationDispatches()`.
Casts: `payment_status` → `PaymentStatus`, `payment_method` → `PaymentMethod`, `source` → `OrderSource`, `ticket_type` → `TicketType`, `total_amount` → `decimal:2`, dates → `immutable_datetime`. Accessor `public_status` (R-SQL mapping to `failed`). Scopes: `active()` (pending|confirmed), `holdingSeats()`, `expiredHolds()`, `openUnpaidForPhone($e164)` (R-026), `finalCancelled()`, `upcoming()` (event date ≥ Qatar today), `forInvoiceDispatch()` (R-155).

Old DB logic → Laravel:
| Original | Laravel replacement |
|---|---|
| RPC `create_order` | `App\Domain\Orders\CreateOrderAction` (single `DB::transaction`, locks event ticket types `lockForUpdate()->orderBy('id')`, calls `ExpireHoldsAction(event)`, validates R-012…R-040, inserts customer/order/items/holders, reserves stock through `StockService`, `PersonLimitGuard`) |
| `generate_reference` | `ReferenceGenerator::make(prefix, eventDate)` (R-059, 5 retries on `UniqueConstraintViolationException`) |
| trigger `trigger_validate_order_amount`, `validate_holder_amount` | invariant assertions in `CreateOrderAction` + CHECK constraints |
| trigger `orders_sync_stock` | `OrderStateMachine::transition()` → `StockService::release()/reserve()` |
| trigger `orders_move_stock` | `ChangeOrderEventAction` → `StockService::move()` (locks both events in order) |
| trigger `orders_release_stock_on_delete` | `DeleteOrderAction` (soft delete releases seats once; purge never releases again) |
| `confirm_order_payment` | `ConfirmPaymentAction` (row lock `lockForUpdate`, R-115, R-056) |
| `mark_order_payment_failed` | `FailPaymentAction` |
| `cancel_order` | `CancelOrderAction` (policy `orders.cancel`) |
| `claim_payment_check` | `PaymentCheckThrottle::claim()` — atomic `UPDATE orders SET last_payment_check_at=? WHERE … AND (last_payment_check_at IS NULL OR last_payment_check_at < ?)` and affected-rows check |
| `expire_stale_orders`, `release_expired_holds` | `ExpireHoldsAction(?Event)` + scheduled `orders:expire-holds` |
| `get_order_status` | `PublicOrderStatusQuery` (≤10 refs, regex, no PII) |
| `get_public_order` | `PublicInvoiceQuery` (by reference only; order-id path dropped D-022; `id_number` only for admins) |
| `recount_ticket_stock`, `_recount_ticket_stock` | `RecountStockAction` + artisan `stock:recount` |
| Observer | `OrderObserver` → guards status/event_id changes outside services; broadcasts `OrderCreated`, `OrderStatusChanged`, `OrderDeleted`; audit |

#### `order_items` — NEW (line snapshot)
Needed to re-issue the Sadad request from what was sold (D-033), and for revenue at the price actually paid (D-070).

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid` PK | | |
| order_id | `foreignUuid()->constrained()->cascadeOnDelete()` | NOT NULL | |
| ticket_type_id | `foreignUuid()->constrained('ticket_types')->restrictOnDelete()` | NOT NULL | |
| type | `string(16)` | NOT NULL | snapshot |
| unit_price | `decimal(10,2)` | NOT NULL | snapshot of `ticket_types.price` |
| quantity | `unsignedSmallInteger` | NOT NULL | 1..20 |
| line_total | `decimal(10,2)` | NOT NULL | |
| price_source | `string(10)` | NOT NULL default `order` | `order` (new data) or `derived` (back-filled legacy) |
| timestamps | | | |

UNIQUE `(order_id, ticket_type_id)`. Model `OrderItem`.

#### `ticket_holders` (kept; one row per issued ticket)
Model `TicketHolder` (`HasUuids`). **Rename (columns):** check-in fields `confirmed_at/confirmed_by/confirmed_by_name` → `checked_in_at/checked_in_by/checked_in_by_name` (the original reused "confirmed" for scans and payments, defect D-046).

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid` PK | | preserved (restore-from-bin re-uses it) |
| order_id | `foreignUuid()->constrained()->cascadeOnDelete()` | NOT NULL | |
| ticket_type_id | `foreignUuid()->nullable()->constrained('ticket_types')->restrictOnDelete()` | NULL→ backfilled NOT NULL | NEW (D-055): holder ↔ ticket type link, replaces matching by (event, type) |
| ticket_type | `string(16)` | NOT NULL | CHECK in enum (was free text) |
| unit_price | `decimal(10,2)` | NULL | NEW price snapshot |
| name | `string(100)` | NOT NULL | |
| phone | `string(32)` | NOT NULL | |
| phone_e164 | `string(20)` | NULL | NEW |
| country_code | `string(5)` | NULL default `'+974'` | |
| nationality | `string(60)` | NOT NULL default `''` | |
| id_number | `string(30)` | NULL | PII |
| person_key | `string(20)` | NULL | NEW (R-021), indexed with event via join |
| qr_code | `string(100)->unique()` | NULL (NOT NULL for new rows) | ticket code (R-064) |
| qr_image_url | `string(2048)` | NULL | public PNG URL (disk `qr`) |
| is_present | `boolean` | NOT NULL default false | |
| checked_in_at | `timestamp` | NULL | **Rename** of `confirmed_at` |
| checked_in_by | `foreignUuid()->nullable()->constrained('users')->nullOnDelete()` | NULL | **Rename** of `confirmed_by` |
| checked_in_by_name | `string(80)` | NULL | **Rename** of `confirmed_by_name` |
| checked_in_device_id | `foreignUuid()->nullable()->constrained('kiosk_devices')->nullOnDelete()` | NULL | NEW |
| timestamps | | | `created_at` orders holders inside an order |

Indexes: `(order_id)`, UNIQUE `qr_code` (drop redundant `idx_ticket_holders_qr_code`), `(is_present)`, `(checked_in_at)`, NEW `(person_key)`, NEW `(phone_e164)`, NEW `(ticket_type_id)`. Partial `ticket_holders_qr_image_url_idx` is dropped (the backfill job scans `whereNull('qr_image_url')` in id order, 100 at a time).
Relations: `order()`, `ticketType()`, `checker()` (User), `checkins()` hasMany TicketCheckin.
Casts: `ticket_type` enum, `is_present` bool, dates. `$hidden = ['id_number']` unless policy `customers.view_pii` (resource layer).
Old logic → Laravel: `enforce_ticket_limit` → `PersonLimitGuard::assert(event, holders)` called by CreateOrderAction and RestoreTicketAction while the event's ticket-type rows are locked (that lock serialises all writers of the event, replacing `pg_advisory_xact_lock`); `holders_reserve_stock` / `holders_release_stock` → `StockService::reserveOne/releaseOne` called by `CreateOrderAction`, `BinTicketAction`, `RestoreTicketAction`, `DeleteHolderAction`; `bin_ticket_holder` / `restore_ticket_holder` → `BinTicketAction` / `RestoreTicketAction` (R-194); check-in RPC-less updates → `CheckInService` (R-128–R-133). Observer `TicketHolderObserver` broadcasts `TicketCheckedIn`, `TicketCheckinReset`, `HolderCreated`, `HolderDeleted` on the `event.{event_id}` and `order.{order_id}` private channels.

#### `ticket_checkins` — NEW (admission/reset history)
`id` uuid; `ticket_holder_id` FK cascade; `action` string(16) CHECK in (`admit`,`reset`,`rejected`); `reason` string(40) NULL (`already_used`, `expired`, `wrong_day`, `unpaid`, `expired_code`, `not_found`); `actor_id` FK users NULL; `actor_label` string(80) NULL; `kiosk_device_id` FK NULL; `scanned_code` string(100); `source` string(12) (`camera`,`manual`,`search`,`offline_sync`); `created_at`. Index `(ticket_holder_id, created_at)`, `(created_at)`. Feeds scan history (last 30) and the gate board "recent 25" without overloading `ticket_holders`.

#### `scan_queue` — NEW (offline scanner sync)
Scanner PWA stores scans in IndexedDB when offline and uploads them; the server applies them in `scanned_at` order with the normal atomic admit.

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid` PK | | client-generated UUID (idempotency) |
| kiosk_device_id | `foreignUuid()->constrained()->cascadeOnDelete()` | NOT NULL | |
| ticket_code | `string(100)` | NOT NULL | normalised (R-067) |
| scanned_at | `timestamp` | NOT NULL | device clock |
| received_at | `timestamp` | NOT NULL | server |
| processed_at | `timestamp` | NULL | |
| result | `string(16)` | NOT NULL default `pending` | `admitted`, `already_used`, `rejected`, `pending` |
| reason | `string(40)` | NULL | |
| ticket_holder_id | `foreignUuid()->nullable()->constrained()->nullOnDelete()` | NULL | |
| actor_label | `string(80)` | NULL | |

Indexes: `(result, received_at)`, `(kiosk_device_id, scanned_at)`. Job `ProcessScanQueue` (queue `scans`, `ShouldBeUnique` per id). Conflict rule: first admit wins; later ones become `already_used` and are surfaced on the board.

#### `deleted_tickets` (kept; recycle bin)
Model `DeletedTicket` (`HasUuids`).

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid` PK | | |
| holder_id | `uuid('holder_id')` | NOT NULL | original holder id (re-used on restore) — no FK by design |
| order_id | `foreignUuid()->constrained()->cascadeOnDelete()` | NOT NULL | NEW FK (orders are soft-deleted, so the bin survives order soft-delete; purge removes it) |
| ticket_type_id | `uuid` | NULL | NEW snapshot |
| name, phone, nationality, ticket_type | `string(100)`, `string(32)`, `string(60)`, `string(16)` | NOT NULL | |
| qr_code | `string(100)` | NULL | |
| qr_image_url | `string(2048)` | NULL | NEW (fix D-047) |
| is_present | `boolean` | NULL | |
| checked_in_at, checked_in_by, checked_in_by_name | `timestamp`, `uuid`, `string(80)` | NULL | renamed like holders |
| id_number, country_code, unit_price | `string(30)`, `string(5)`, `decimal(10,2)` | NULL | |
| holder_created_at | `timestamp` | NULL | |
| context | `json` | NOT NULL (`{}`) | R-194 keys |
| deleted_at | `timestamp` | NOT NULL default now | (business column, not SoftDeletes) |
| deleted_by | `foreignUuid()->nullable()->constrained('users')->nullOnDelete()` | NULL | NEW FK |
| deleted_by_name | `string(254)` | NULL | e-mail of admin |

Index `(deleted_at)`. Policy: admin only; purge requires `tickets.bin`.

#### `expired_qr_codes` (kept)
Model `ExpiredQrCode` (`HasUuids`): `id` uuid; `qr_code` string(100) NN **UNIQUE** (was plain index; uniqueness makes the gate check a single lookup); `order_id` `foreignUuid()->constrained()->cascadeOnDelete()` (NEW FK); `ticket_holder_id` uuid NULL (NEW); `reason` string(40) NN default `event_date_changed`; `expired_at` timestamp NN; `created_at`. Written by `ChangeOrderEventAction` inside the same transaction as the move (D-034); read by `CheckInService` (R-128).

#### `refunds` — NEW
For `paid_after_expiry_no_stock`, "return ticket", cancelled-but-paid review cases. Model `Refund` (`HasUuids`): `id`; `order_id` FK restrict; `amount` decimal(10,2) NN; `reason` string(60) NN (`paid_after_expiry_no_stock`, `customer_return`, `duplicate_payment`, `admin`); `status` string(16) NN default `requested` (enum `RefundStatus`); `gateway` string(16) default `sadad`; `gateway_reference` string(100) NULL; `requested_by` FK users; `processed_by` FK users NULL; `processed_at` NULL; `note` text NULL; timestamps. Index `(status, created_at)`, `(order_id)`. Refunds are recorded manually after acting in the Sadad panel (the original had no API refund); stock is not affected (seats already released).

#### `pos_users` (kept; cashiers / gate team roster)
Model `PosUser` (`HasUuids`, `SoftDeletes`): `id` uuid; `name` string(80) NN (NEW unique among non-deleted via service check); `icon` string(16) NULL default `⭐` (R-199); `is_active` boolean NN default true; timestamps; deleted_at. Relations: `orders()`, `attendance()`. Hard delete blocked when orders exist (deactivate/soft delete instead, fixes D-073). Public SELECT of active users → only for authenticated staff/kiosk sessions.

#### `pos_receipts` (kept; card-terminal slip OCR)
Model `PosReceipt` (`HasUuids`, `InteractsWithMedia` — collection `receipt` on private disk `receipts`).

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid` PK | | |
| event_id | `foreignUuid()->nullable()->constrained()->nullOnDelete()` | NULL | NEW (prices R-200) |
| amount_qar | `decimal(10,2)` | NULL | was untyped numeric |
| seq_number | `string(40)` | NULL | |
| card_number_masked | `string(32)` | NULL | |
| time | `string(8)` | NULL | `HH:MM:SS` as printed |
| auth_number | `string(20)` | NULL | |
| image_url | `string(2048)` | NULL | legacy public URL; new rows use media + signed URLs |
| ocr_raw | `json` | NULL | NEW model output |
| created_by | `foreignUuid()->nullable()->constrained('users')->nullOnDelete()` | NULL | now always filled (was never set) |
| kiosk_device_id | `foreignUuid()->nullable()->constrained()->nullOnDelete()` | NULL | NEW |
| ticket_type | `string(16)` | NULL default `normal` | legacy, unused |
| num_tickets | `unsignedSmallInteger` | NULL | legacy |
| normal_tickets / vip_tickets / parking_tickets | `unsignedSmallInteger` | NOT NULL default 0 | |
| timestamps | | | |

Indexes: `(created_at)`, NEW UNIQUE `(seq_number, auth_number, amount_qar)` (duplicate detection, D-077) — nullable columns make the unique apply only when all present on PG; on MySQL NULLs also do not collide. Service `ParseReceiptAction` (Prism vision call, R-165 timeout, per-user rate limit) replaces edge fn `parse-pos-receipt`; upload happens **after** a successful parse or the file is deleted on failure.

### 4.4 Payments, messaging and audit

#### `payment_events` (kept; payment audit trail)
Model `PaymentEvent` (no UUID; `id` bigint).

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `id()` | | |
| booking_reference | `string(60)` | NULL | |
| order_id | `foreignUuid()->nullable()->constrained()->nullOnDelete()` | NULL | |
| source | `string(10)` | NOT NULL | CHECK in (`callback`,`webhook`,`poll`,`admin`) |
| payload | `json` | NULL | ≤ 4 000 chars stored (R-116) |
| checksum_valid | `boolean` | NULL | |
| api_result | `string(16)` | NULL | `SadadApiResult` |
| decision | `string(10)` | NULL | `PaymentDecisionAction` |
| outcome | `string(40)` | NULL | `PaymentOutcome` |
| created_at | `timestamp` | NOT NULL | |

Index `(booking_reference, created_at)`. Insert-only (no update/delete permission for anyone; `PaymentEventPolicy` view = admin/accountant). Written by `PaymentProcessor` (port of `processPayment`).

#### `payment_attempts` — NEW (signed checkout requests)
One row per signed Sadad request (order creation and every restart): `id` uuid; `order_id` FK cascade; `amount` decimal(10,2); `txn_date` date; `signature_hash` string(64) (SHA-256 of the signature, never the secret); `return_base` string(255) NULL; `client_ip` string(64) NULL; `created_at`. Index `(order_id, created_at)`. Lets support see how many times a customer re-opened payment.

#### `sadad_transactions` — NEW (cached gateway view for the Transactions screen)
`id` uuid; `transaction_number` string(100) UNIQUE; `order_id` FK nullable nullOnDelete; `website_ref_no` string(60) NULL; `status` string(20) (`SadadLiveStatus`); `amount` decimal(10,2) NULL; `commission` decimal(10,2) NULL; `refund_charge` decimal(10,2) NULL; `environment` string(8); `raw` json; `synced_at` timestamp. Index `(status)`, `(order_id)`. Filled by job `SyncSadadTransaction` (token cached, R-108) so the Sadad-status filter and fee totals are server-side (fixes D-067).

#### `payment_errors` (kept)
Model `PaymentError` (`HasUuids`).

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid` PK | | |
| order_id | `foreignUuid()->nullable()->constrained()->nullOnDelete()` | NULL | |
| booking_reference | `string(60)` | NULL | |
| event_id | `foreignUuid()->nullable()->constrained()->nullOnDelete()` | NULL | |
| customer_name | `string(100)` | NULL | |
| customer_phone | `string(32)` | NULL | |
| quantity | `unsignedSmallInteger` | NULL | |
| amount | `decimal(10,2)` | NULL | |
| payment_id | `string(100)` | NULL | |
| error_source | `string(8)` | NOT NULL default `site` | CHECK in (`sadad`,`bank`,`site`) |
| error_code | `string(40)` | NULL | `PaymentErrorCode` |
| error_message | `string(1000)` | NULL | |
| raw | `json` | NOT NULL (`{}`) | sanitised; UA/URL truncated |
| resolved_at / resolved_by | `timestamp`, FK users | NULL | NEW ("resolved" instead of delete) |
| created_at | `timestamp` | NOT NULL | |

Indexes `(created_at)`, `(event_id)`, NEW `(error_source, created_at)`. Writers: server only (`PaymentFailed` listener, checkout failure handler, and a rate-limited public endpoint `POST /api/v1/payment-errors` for client-side failures: 10/min per IP, validated). Broadcast `PaymentErrorLogged` (the original UI subscribed but the table was not published, D-085).

#### `email_delivery_events` (kept)
Model `EmailDeliveryEvent` (`HasUuids`).

| Column | Migration | Null / default | Notes |
|---|---|---|---|
| id | `uuid` PK | | |
| order_id | `foreignUuid()->nullable()->constrained()->cascadeOnDelete()` | NULL | |
| booking_reference | `string(60)` | NULL | |
| recipient | `string(254)` | NULL | |
| recipient_normalized | `string(254)` STORED generated `lower(recipient)` | — | replaces expression index `lower(recipient)` (`$table->string(...)->storedAs('lower(recipient)')`, works on MySQL and PG) |
| template | `string(40)` | NOT NULL default `booking-invoice` | always the real template (fix D-087) |
| provider_message_id | `string(191)` | NULL | NEW; used to match bounces |
| status | `string(16)` | NOT NULL | `EmailDeliveryStatus` |
| attempt | `unsignedSmallInteger` | NOT NULL default 1 | |
| detail | `string(500)` | NULL | Arabic detail |
| created_at | `timestamp` | NOT NULL | |

Indexes `(order_id, created_at)`, `(recipient_normalized)`, UNIQUE `(provider_message_id, status)` (dedupe of webhooks). Written by `MailDeliveryRecorder` (listeners on `MessageSending`/`MessageSent` + provider webhook controller `POST /webhooks/mail/{provider}` for bounce/complaint/unsubscribe) replacing `handle-email-events`. Broadcast `EmailDeliveryRecorded`.

#### `automation_dispatches` — NEW (n8n / WhatsApp sends)
Replaces in-browser counters and makes the auto-invoice job lock-safe. `id` uuid; `order_id` FK cascade; `channel` string(16) (`whatsapp`,`email_webhook`); `action` string(30) (`order_created`, `payment_confirmed`, `send_ticket`, `send_invoice`, `send_email`, `auto_invoice`); `status` string(16) (`queued`,`sent`,`accepted`,`failed`,`timeout`); `attempt` smallint; `http_status` smallint NULL; `response_excerpt` string(500) NULL; `dispatched_by` FK users NULL; `created_at`. Index `(order_id, created_at)`, `(status, created_at)`. The summary columns on `orders` (`n8n_response_message`, `n8n_responded_at`, `send_attempt_count`) stay for parity and are updated in the same transaction.

#### `activity_log` — **Rename:** spatie/laravel-activitylog table replaces `activity_logs`
Package migration plus extra columns (migration `add_ops_columns_to_activity_log`):

| Column | Migration | Notes |
|---|---|---|
| id | `id()` | package default (bigint) — original uuids kept in `properties->legacy_id` |
| log_name | `string` NULL | = `ActivityType` (widened list, 3.12) |
| description | `text` | Arabic sentence |
| subject_type / subject_id | `nullableUuidMorphs('subject')` | order, holder, event… |
| causer_type / causer_id | `nullableUuidMorphs('causer')` | user |
| event | `string` NULL | |
| properties | `json` | was `action_data` + `metadata` |
| batch_uuid | `uuid` NULL | |
| actor_type | `string(10)` NULL | NEW: `admin|guest|system|staff` (was `user_type`, CHECK widened) |
| actor_identifier | `string(160)` NULL | was `user_identifier` |
| ip_address | `string(64)` NULL | |
| user_agent | `string(300)` NULL | |
| created_at / updated_at | | |

Indexes: package `(log_name)`, plus `(created_at)`, `(actor_identifier)`. Writes are **server-side only** (D-024). Deletion restricted to `super_admin`; retention via `activitylog:clean` (setting, default 365 days). Broadcast `ActivityLogged` on `private-admin` (was realtime).

#### `settings` — **Rename/re-shape:** spatie/laravel-settings table replaces the single-row `settings`
Package table `settings (id, group, name, locked, payload json, timestamps, UNIQUE(group,name))`. Settings classes (each `App\Settings\…`, cached):

| Class (group) | Properties (default) | From original column |
|---|---|---|
| `BrandingSettings` (`branding`) | logo_url, header_bg_color (`hsl(var(--card) / 0.5)`), header_bg_image_url, hero_image_url, hero_text (`''`), before_footer_image_url, copyright_text (`جميع الحقوق محفوظة`), festival_name, season_label (NEW, R-167) | same names |
| `ContactSettings` (`contact`) | admin_phone, admin_email | same |
| `EventSettings` (`events`) | current_event_id (uuid\|null), season_start_date (NEW, R-178) | `current_event_id` |
| `FeatureFlagSettings` (`flags`) | show_delete_customer_button (false), show_delete_order_button (false, NEW split), show_generate_qr_button (false), show_delete_event_button (false), show_delete_ticket_button (true), admit_unpaid_cash_bookings (true), cash_hold_policy (`none`) | same + NEW |
| `AutomationSettings` (`automation`) | webhook_enabled (true), email_webhook_enabled (true), payment_failed_email_enabled (true), auto_invoice_interval_seconds (60, min 10), last_invoice_sent_at, invoice_batch_min (1), invoice_batch_max (10), invoice_send_delay_min (300), invoice_send_delay_max (600), invoice_excluded_dates ([`2025-11-06`,`2025-11-07`,`2025-11-08`]) | same |
| `SadadSettings` (`sadad`) | merchant_id, website_domain, environment (`auto`), site_url | same |
| `AnalyticsSettings` (`analytics`) | clarity_enabled (false), clarity_project_id | same |

Secrets (`sadad_secret`, `sadad_api_key`, `webhook_url`, `email_webhook_url`) move to `.env`/secret manager (`SADAD_CHECKOUT_SECRET`, `SADAD_API_LOGIN_SECRET`, `N8N_WEBHOOK_URL`, `N8N_EMAIL_WEBHOOK_URL`); if an admin must edit them in the UI, store them in a `SecretSettings` group with `encrypted()` properties (spatie `encrypted` array), write-only in the form ("set / not set"). View `public_settings` → read model `PublicSettings::current()` (Branding + Contact.admin_phone only if a NEW flag `publish_admin_phone` is true + current_event_id + delete-button flags + clarity), cached 300 s, flushed by `SettingsSaved` event. Trigger `update_settings_updated_at` → package timestamps.

#### `media` — NEW (spatie/laravel-medialibrary)
Package table (`uuidMorphs('model')`). Collections: `Event.cover` (disk `public`), `PopupBanner.image`, `BrandingSettings` images via a `SiteAsset` model (`logo`, `hero`, `before_footer`, `header_bg`, disk `branding`), `PosReceipt.receipt` (disk `receipts`, private). Replacing an image deletes the previous file (fix D-100).

#### `staff_attendance` (kept)
Model `StaffAttendance` (`HasUuids`): `id`; `pos_user_id` `foreignUuid()->constrained()->cascadeOnDelete()`; `attendance_date` date NN; `status` string(8) NN CHECK in (`present`,`absent`); `marked_by` `foreignUuid()->nullable()->constrained('users')->nullOnDelete()` (NEW FK); `marked_at` timestamp NN; timestamps. UNIQUE `(pos_user_id, attendance_date)` (upsert target), index `(attendance_date)`. Service `AttendanceService` (list/set/clear/bulk_present/clear_day; future dates refused server-side) replaces edge fn `staff-attendance`; trigger `update_staff_attendance_updated_at` → timestamps. Broadcast `AttendanceChanged` (realtime now possible for passcode kiosks via session-authorised channel).

#### `active_visitors` (kept) and `page_views` (kept)
`active_visitors` — Model `ActiveVisitor` (`HasUuids`, `MassPrunable`): `id`; `session_id` string(64) UNIQUE; `ip_address` string(64) NULL (stored hashed+truncated option, setting); `country` string(80), `country_code` string(2), `city` string(80) NULL; `current_page` string(255) NN default `/`; `referrer` string(500) NULL; `traffic_source` string(20) default `direct`; `device_type` string(8) default `desktop`; `browser`, `os` string(40) NULL; `is_new_visitor` boolean default true; `first_seen_at`, `last_seen_at` timestamp NN; `user_agent` string(300) NULL. Index `(last_seen_at)`. Written only by server middleware/heartbeat endpoint `POST /api/v1/presence` (upsert by session id, rate-limited 4/min per session; geo from request IP via local GeoIP DB — no browser call to ipapi.co, D-082). Prune `< now-5 min` every minute (replaces `cleanup_stale_visitors`). Broadcast `VisitorPresenceChanged` throttled to once per 10 s (aggregate counts only).

`page_views` — Model `PageView` (`HasUuids`): `id`; `session_id` string(64) NN; `page_path` string(255) NN; `country`, `country_code`, `city`, `device_type`, `browser`, `os`, `traffic_source` (as above) NULL; `is_new_visitor` boolean default true; `viewed_at` timestamp NN; `ip_address` string(64) NULL; NEW `viewed_on_qatar` date (computed on insert = Qatar date of `viewed_at`, R-010). Indexes `(page_path)`, `(viewed_at)`, `(country)`, `(session_id)`, NEW `(viewed_on_qatar)`. RPCs `get_page_view_stats`, `get_visitors_per_date`, `get_visitors_per_country` → `VisitorAnalyticsQuery` (admin/`visitors.view` only, Qatar dates). Optional retention (NEW setting, default keep).

### 4.5 Realtime → broadcast events (Laravel Reverb + Echo)

| Original realtime table | Broadcast event(s) | Channel(s) | Consumers |
|---|---|---|---|
| `orders` (REPLICA FULL) | `OrderCreated`, `OrderStatusChanged` (old+new status), `OrderDeleted` | `private-admin`, `private-event.{eventId}` | Admin notifications/badge, orders list, live bookings, capacity alert |
| `ticket_holders` (REPLICA FULL) | `TicketCheckedIn` (holder, type, label, time), `TicketCheckinReset`, `HolderCreated`, `HolderDeleted` | `private-event.{eventId}`, `private-order.{orderId}` | Gate board, scanner history, live bookings, ticket viewer, booked-by-date |
| `tickets` → `ticket_types` | `TicketTypeStockChanged` (remaining), `TicketCapacityIncreased` (old, new) | `event.{eventId}.availability` (public, counts only), `private-event.{eventId}` | Ticket selection, POS banner, live bookings |
| `activity_logs` → `activity_log` | `ActivityLogged` | `private-admin` | Activity log page |
| `active_visitors` | `VisitorPresenceChanged` (throttled) | `private-admin` | Live visitors, visitors tab |
| `pos_receipts` | `PosReceiptSaved`, `PosReceiptDeleted` | `private-admin` | Receipts page |
| `payment_errors` (was not published) | `PaymentErrorLogged` | `private-admin` | Payment errors page |
| `email_delivery_events` (was not published) | `EmailDeliveryRecorded` | `private-order.{orderId}` | Invoice timeline |
| NEW | `AttendanceChanged`, `ScanQueueProcessed` | `private-staff` | Attendance, gate board |
| NEW (customer) | `OrderPaymentSettled` | `order-status.{signedRef}` (private, authorised by a signed reference token in the session) | Payment result page (polling stays as fallback) |

Channel authorisation in `routes/channels.php` uses the same policies as HTTP (`private-admin` = admin/accountant/viewer; `private-event.*` and `private-staff` = any staff incl. passcode kiosk session). All events implement `ShouldBroadcast` + `ShouldDispatchAfterCommit`, payloads contain no national IDs.

### 4.6 Storage buckets → filesystem disks

| Supabase bucket | Laravel disk | Visibility | Paths | Notes |
|---|---|---|---|---|
| `qr-codes` (public) — ticket PNGs | `qr` (local `storage/app/public/qr-codes` or S3 prefix `qr-codes/`) | public read | `<code>.png` (R-066) | Writes only by `QrService` (server). Same object names → URLs can be rewritten 1:1 |
| `qr-codes` (public) — branding (`logos/`, `hero-images/`, `before-footer/`, `header-bg/`) | `branding` (public) via medialibrary | public | medialibrary paths | Old files deleted on replace |
| `pos-receipts` (public) | `receipts` (private; S3 private bucket or `storage/app/private/receipts`) | **private**, `temporaryUrl()` 5 min | `receipts/{yyyy}/{mm}/{uuid}.{ext}` | Fix D-006 |
| — | `exports` (private, auto-delete after 24 h) | private | Excel/PDF exports | NEW |
| — | `backups` (private S3) | private | spatie/laravel-backup | NEW |

### 4.7 Scheduler (replaces lazy jobs and the external cron)

| Entry (`routes/console.php`) | Frequency | Replaces | Notes |
|---|---|---|---|
| `orders:expire-holds` (`ExpireHoldsAction` for all events) | every minute, `withoutOverlapping()`, `onOneServer()` | lazy `expire_stale_orders` only | Lazy per-event expiry remains inside order creation and availability reads |
| `orders:expire-cash-holds` | every 5 min | NEW (only when `cash_hold_policy` ≠ `none`) | |
| `invoices:dispatch` (`SendInvoiceBatch`) | every 10 s (`everyTenSeconds()`), `withoutOverlapping()`, `onOneServer()` | `auto-send-invoices` edge fn + browser countdown | Honours interval/batch/delay settings; marks rows before sending inside `lockForUpdate()->skipLocked()` |
| `visitors:prune` | every minute | `cleanup_stale_visitors` | R-179 |
| `model:prune` (LoginAttempt, ActiveVisitor) | daily 03:00 Qatar | `cleanup_login_attempts` | |
| `sadad:reconcile-pending` | every 5 min | disabled `recover-pending-orders` | NEW: verify sadad orders `pending` older than 2 min and failed/expired within 3 days via `PaymentProcessor(source:'poll')`, max 50 per run |
| `sadad:sync-transactions` | every 15 min | Transactions page live calls | NEW |
| `stock:verify` | hourly | — | NEW: compares recount result without writing; alerts admin on drift |
| `activitylog:clean` | daily | — | retention setting |
| `backup:clean`, `backup:run` | daily 02:00 / 02:30 Qatar | — | spatie/laravel-backup |
| `horizon:snapshot` | every 5 min | — | |
| `queue:prune-failed --hours=168` | daily | — | |

### 4.8 Views, RPCs and edge functions → read models / services (summary)

| Original | Laravel |
|---|---|
| `get_event_ticket_counts`, `reserve_tickets` (read-only) | `AvailabilityQuery::forEvent()` / `check()` (named `check_availability`, R-054) |
| `get_person_ticket_count` | `PersonLimitGuard::count(idNumber, phone, event)` (public pre-check endpoint rate-limited 20/min per IP) |
| `get_person_event_history` | `PersonHistoryQuery` (staff only, R-071) |
| `get_lifetime_ticket_totals` | `LifetimeTotalsQuery` (admin + supervisor; single key per person: ID key preferred, else phone — fixes double-keying D-095) |
| legacy `create_public_booking`, `create_pos_booking`, `enforce_ticket_capacity`, `update_ticket_sold_quantity`, `generate_booking_reference`, `generate_ticket_holder_reference` | **not ported** (dead code, D-097) |
| edge fns `create-order`, `sadad-payment`, `sadad-callback`, `sadad-webhook`, `verify-payment`, `sadad-diagnose`, `sadad-transaction` | `Api\V1\OrderController`, `Payments\SadadCheckoutController`, `Payments\SadadCallbackController` (GET/POST, CSRF-exempt), `Payments\SadadWebhookController`, `Payments\VerifyPaymentController`, `Admin\SadadDiagnoseAction`, `Admin\SadadTransactionQuery` |
| `send-invoice-email`, `send-payment-failed-email`, `notify-admin-sale`, `handle-email-events`, `preview-transactional-email` | queued Mailables `BookingInvoiceMail`, `PaymentFailedMail`, `AdminSaleAlertMail`, `LoginAlertMail` + `MailWebhookController` + Filament preview page |
| `send-to-webhook`, `n8n-response`, `auto-send-invoices` | `AutomationRelay` (driver interface `WhatsAppChannel`: `N8nWebhookDriver`, future `WhatsAppCloudDriver`), `N8nResponseController`, `SendInvoiceBatch` |
| `generate-qr-code`, `backfill-qr-codes`, `regenerate-booking-qr-codes` | `QrService` + admin actions `BackfillQrImages` (100/run), `RegenerateBookingQr` |
| `change-order-event` | `ChangeOrderEventAction` (one transaction) |
| `ticket-checkin`, `gate-stats`, `staff-customer-lookup`, `staff-attendance`, `staff-auth`, `login-guard`, `manage-staff-users`, `parse-pos-receipt` | `CheckInService` + `ScannerController`, `GateBoardQuery`, `CustomerLookupQuery`, `AttendanceService`, `PasscodeLoginController`, Fortify pipeline + `LoginThrottle`, `StaffAccountService`, `ParseReceiptAction` |
| `mcp` | `laravel/mcp` server with read-only tools `list_events`, `event_sales_summary`, `find_booking`, `search_customers` (Sanctum/OAuth, policies) |

### 4.9 Data migration from Supabase

**Approach:** a one-off artisan command set `migrate:from-supabase {--dry-run}` that reads a `pg_dump --data-only --column-inserts` (or a read-only connection `supabase_legacy`) and writes through Eloquent query builder **without** model events (`Model::withoutEvents`) and without stock triggers; stock is recomputed at the end. Every step is idempotent (`upsert` by id) and logs counts.

**Pre-flight (on the Supabase side, before export):**
1. Apply migrations `20261009100100_orders_payments_additive.sql` and `20261009100200_close_open_access.sql` if production is not yet on them (they contain the URL→code split, stale-hold closing and recount). If they cannot be applied, the importer performs fixes F-01…F-04 itself.
2. Freeze writes (maintenance page on the old site; disable n8n and the auto-send cron) and snapshot the database and both storage buckets.
3. Rotate every secret ever exposed (D-001, D-002) — the new system starts with new values.

**Export / import order** (respects FKs; ids preserved as UUID strings):

| Step | Source | Target | Notes |
|---|---|---|---|
| 1 | `auth.users` (+ `admin_users`, `user_roles`) | `users`, `model_has_roles` | id, email, `encrypted_password` → `password` (bcrypt `$2a$`); `admin_users` or role admin → `admin`; moderator → `supervisor`; users with no role imported inactive; force password reset for all admins (D-001) |
| 2 | `events` | `events` | `is_active` NULL → true; cut-off columns default 18:00 |
| 3 | `tickets` | `ticket_types` | `sold_quantity` copied then recomputed in step 15 |
| 4 | `pos_users` | `pos_users` | |
| 5 | `customers` | `customers` | compute `phone_e164`, `person_key`; NULL `country_code` → `+974` (F-07) |
| 6 | `orders` | `orders` | `payment_status` NULL → `pending`, `created_at` NULL → `confirmed_at`/`paid_at`/now (F-08); `source` = `pos` when reference starts with `POS-` or `pos_user_id` set, else `web`; `confirmed_by` kept only if the user exists |
| 7 | (derived) | `order_items` | From holders grouped by type; `unit_price` = current `tickets.price` when Σ(price×count) = `total_amount`, else proportional split of `total_amount` (rounded to cents, remainder on the first line); `price_source='derived'` |
| 8 | `ticket_holders` | `ticket_holders` | Rename check-in columns; set `ticket_type_id` by (order.event_id, ticket_type) (F-10); `unit_price` from step 7; `phone_e164`, `person_key`; F-01, F-05, F-06 |
| 9 | `deleted_tickets` | `deleted_tickets` | rows whose order no longer exists → exported to CSV archive and skipped (FK) |
| 10 | `expired_qr_codes` | `expired_qr_codes` | de-duplicate by `qr_code` (keep earliest) |
| 11 | `payment_events` | `payment_events` | keep bigint ids (set auto-increment after) |
| 12 | `payment_errors` | `payment_errors` | |
| 13 | `email_delivery_events` | `email_delivery_events` | |
| 14 | `staff_attendance`, `pos_receipts`, `popup_banners`, `page_views`, `activity_logs` (→ `activity_log`, F-09), `login_attempts` (last 7 days only) | same | `active_visitors` is **not** migrated (ephemeral) |
| 15 | — | — | `stock:recount` (must report the expected drift list), then `stock:verify` must return 0 |
| 16 | `settings` row | spatie settings groups | secrets → `.env` (new rotated values), not imported; `current_event_id` validated |
| 17 | Storage `qr-codes/*.png` | disk `qr` (same names) | rewrite `ticket_holders.qr_image_url` host to the new `APP_URL`; missing pictures → `BackfillQrImages` |
| 18 | Storage `qr-codes/{logos,hero-images,before-footer,header-bg}/*` | medialibrary `branding` | only files referenced by settings |
| 19 | Storage `pos-receipts/receipts/*` | private disk `receipts` + media rows | then delete the public bucket |

**Legacy data fixes (from E; each must be a tested importer step):**
- **F-01** `ticket_holders.qr_code` containing an image URL → move URL to `qr_image_url`; code = file name without `.png` and query (`regexp_replace(regexp_replace(qr_code,'^.*/',''),'\.png(\?.*)?$','','i')`); afterwards no `qr_code` matches `^https?://`.
- **F-02** Pending sadad orders older than 30 min (no `payment_expires_at`) → `cancelled`, note `expired`; fresh ones get `payment_expires_at = created_at + 30 min`.
- **F-03** Recount `sold_quantity` from live orders (holders, or the legacy per-order fallback R-049).
- **F-04** Duplicate `orders.payment_id` (if `orders_payment_id_key` was created non-unique): keep it on the confirmed/earliest order, move the others' value into `payment_note` (`duplicate_payment_id:<txn>`) and create a `refunds` row (`reason=duplicate_payment`, status `requested`) for review; then add the UNIQUE index.
- **F-05** Holders without a code → `<booking_reference>-TKT<NN>` ordered by `created_at` (repair 20251012105122).
- **F-06** `is_present=true AND confirmed_at IS NULL` → `is_present=false` (POS used to mark tickets present at sale).
- **F-07** NULL `country_code` → `+974` (customers, holders).
- **F-08** NULL `payment_status` → `pending`; NULL `created_at` → best known timestamp.
- **F-09** `activity_logs` values outside the old CHECK (if any slipped in) and `user_type='staff'` imported as-is into the widened enums; `pos_form` + `action_data.action='invoice_download'` → `log_name='invoice_download'`.
- **F-10** `ticket_holders.ticket_type` not in (vip, normal, parking) → report and fix manually before import (it silently skipped stock and the cap in the original, D-055).
- **F-11** Legacy references (`QTR-XXXXXXXX`, client-made, roadmap formats) kept untouched; lookups accept R-062.
- **F-12** Test data already deleted in 20260903062719 (events `1111…`, `2222…`, customers `salem@test.qa`, `pos@test.qa`, `x/y/z@test.qa`) — importer asserts none remain.
- **F-13** Orders created 2025-11-06/07/08 are kept; they are excluded from auto-invoicing only through the `invoice_excluded_dates` setting.
- **F-14** Holder phones stored with a typed prefix (`"+971 55510002"`) → split into `country_code` + national number + `phone_e164`.

**Verification after import:** row counts per table equal source (minus documented skips); Σ `total_amount` of confirmed orders equal; every `qr_code` unique and matching R-066 or legacy; `stock:verify` = 0; sample of 50 references renders the same invoice data in old and new systems; the DB test suite of Part 5 (b) passes on the imported copy (security assertions).

## 5. Known defects of the original (must NOT be reproduced) & test-pinned MUST rules

### 5.a Consolidated defect list

De-duplicated across notes A–E. Severity: **Critical** (data/money/admission at risk now), **High** (exploitable or causes wrong money/stock), **Medium** (wrong behaviour or privacy leak), **Low** (quality, dead code, UX). "Fix" is the required rebuild behaviour; the matching acceptance test lives in 5.b or in the module's tests.

| ID | Sev. | Where | Defect | Required fix in the rebuild |
|---|---|---|---|---|
| D-001 | Critical | Login page, source | Admin password was displayed on the old login page; team passcode `@@@Qatar123` was hard-coded in source/login page | Treat both as compromised: force password reset + 2FA for all admins; passcode deny-list (R-140) |
| D-002 | Critical | `settings` | Sadad secret/API key and n8n URLs stored in plain text; publicly readable 2025-10-08→2026-08-13; Settings screen loads secrets into the browser | Secrets in `.env`/encrypted settings, write-only inputs ("set / not set"), rotate all values |
| D-003 | Critical | RLS history | `customers`, `orders`, `ticket_holders`, `settings`, `page_views`, `expired_qr_codes` were publicly readable (3 213+ customers); "own orders" policy matched every order | Deny-by-default policies on every model; security tests T-007…T-020 |
| D-004 | Critical | Legacy RPCs | `create_pos_booking` callable by anon (free confirmed tickets); `create_public_booking` trusted client total and reference | Only `CreateOrderAction`; POS requires staff session; never port legacy RPCs |
| D-005 | Critical | ticket-checkin, scanner | POS (`POS-` prefix) and any `cash_pos` order treated as paid **even when cancelled** → refunded/returned tickets still admitted | Admission per R-129: `cancelled` never admitted |
| D-006 | High | Storage `pos-receipts` | Card-slip photos (masked PAN, auth codes) in a public bucket | Private disk + 5-min signed URLs |
| D-007 | High | `RequireAdmin` | Passcode/hand-off holders pass `adminOnly` (whole dashboard UI, `/live-visitors`) | Server-side role middleware/policies; passcode kiosk never reaches admin routes |
| D-008 | High | staffAccess/staffHandoff | Passcode kept in plaintext in local/session storage, re-sent in every request body, passed in URL hash `#sk=`; missing expiry silently re-stamped for 10 days | Server session (R-141); no client copy; drop hash hand-off |
| D-009 | High | Staff edge fns | Only `staff-auth` throttled; `ticket-checkin`, `gate-stats`, `staff-attendance`, `staff-customer-lookup`, `create-order` accepted the passcode unthrottled (brute force) | Single throttled passcode login; other endpoints use the session |
| D-010 | High | `login-guard` | Advisory (client can skip); unauthenticated writes → lock any admin out + alert spam; block measured inside the 15-min window (≈ 15 min effective); fail-open on errors; shares `team-passcode` key with a different rule | Enforce in login controller (R-145), per e-mail + IP, block from latest failure, separate keys, fail closed for the lockout check only when the store is reachable |
| D-011 | High | Grants | Migration 20260207 granted SELECT/INSERT/UPDATE/DELETE on all tables (and future ones) to anon/authenticated; RLS was the only gate | Policies per model/action; no table reachable without a policy |
| D-012 | High | `confirm_order_payment`, verify | A gateway success confirms an **admin-cancelled** order (webhook/callback/admin verify); «تحقق من سداد» stays visible for released orders because notes differ (`released by admin`) | Final cancellations (R-058) are never auto-confirmed: record `review`, set `review_required_at`, create refund task; hide verify for final cancellations |
| D-013 | High | Auth everywhere | `is_admin()` checks only `admin_users`; edge code also accepts `user_roles.admin`; `useStaffRole` ignores `admin_users`; guard and server disagree for moderator+admin_users users | One role system (spatie) with precedence R-137 |
| D-014 | High | CustomersTab, OrdersTab | Browser posts directly to the n8n webhook (URL exposed, `webhook_enabled` ignored); OrdersTab relays the full order row (internal fields) to n8n | All automation via server `AutomationRelay`; payloads built server-side only |
| D-015 | High | OrdersTab confirm toggle | Manual payment confirmation done from the browser in 5+ non-atomic steps (order, holders, QR backfill of the whole backlog, webhook); sadad orders confirmed without transaction number; overwrites holder scan fields | `ManualConfirmAction` in one transaction; requires txn number or reason; never touches check-in fields |
| D-016 | High | `auto-send-invoices` | No authentication, no lock (concurrent runs double-send), ignores `webhook_enabled`, runtime-UTC date math, no fetch timeout | Scheduled job with `withoutOverlapping`, `skipLocked`, settings flags, Qatar dates, 10 s timeout (R-152–R-156) |
| D-017 | High | InvoiceTab | Sending scheduler runs in the admin's browser (closing the tab stops it, two tabs double-send); "actual success" heuristic never matches → every order goes on hold after 2 sends; send list/mark-all not scoped to current/archive; «الكل» count unscoped | Server job + `automation_dispatches`; reset attempts on accepted delivery; scoped queries |
| D-018 | High | create_order | Web cash-at-venue orders never expire: hold seats and count toward the per-person cap and the 4-open-orders limit forever (stock-blocking abuse) | `cash_hold_policy` (R-042) + admin list of stale cash bookings |
| D-019 | High | create-order | Open-order limit (4) compares the raw phone string; formatting variations bypass it | Compare `phone_e164` (R-026) |
| D-020 | Medium | `get_person_event_history` | Callable by anon: enumerate any person's attendance by ID/phone | Staff only (R-071) |
| D-021 | Medium | staff lookup, ticket print | National ID returned to passcode staff; moderators' printed ticket shows `undefined` for ID | R-203; printed ticket omits the field when hidden |
| D-022 | Medium | `get_public_order` | Lookup also by order UUID; returns e-mail and phones; no rate limit on a 48-bit reference | Reference only, throttled (R-061); minimal fields |
| D-023 | Medium | QR storage | Admin browser uploads/overwrites public QR files; legacy QR pictures fetched from third-party `api.qrserver.com` (leaks codes) | Server-side `QrService` only; no third-party fallback (local renderer) |
| D-024 | Medium | Logs/analytics tables | Anonymous INSERT into `activity_logs`, `payment_errors`, `page_views`, `active_visitors`; anyone can UPDATE/DELETE any presence row; client-side logs are forgeable | Server-side writes only; rate-limited validated public endpoints |
| D-025 | Medium | `activity_logs` | CHECK allows only `pos_form/qr_search/ticket_scan` and `admin/guest/system` → `event_change`, `ticket_checkin_reset`, `login_blocked`, `new_event_started`, user type `staff` silently lost (EventShiftBadge always empty) | Widened enums (3.12), server-side audit |
| D-026 | Medium | Analytics RPCs | No admin check (any signed-in user) | `visitors.view` permission |
| D-027 | Medium | Visitors | `cleanup_stale_visitors` EXECUTE revoked → stale rows; 5-min heartbeat = 5-min cutoff → flicker; anonymous upsert fails RLS | Scheduled prune, heartbeat 60 s (R-179), server upsert |
| D-028 | Medium | `n8n-response` | Open unless secret set; unknown reference → 500 (`.single()`) instead of 404; `status` ignored | Mandatory secret, 404, store status |
| D-029 | Medium | Deletes | Deleting an event cascades to orders/holders; deleting a customer cascades to orders; orders hard-deleted without audit; order delete gated by the customer flag | Soft deletes, RESTRICT FKs, separate flags, audit (R-195, R-196) |
| D-030 | Medium | ticket-checkin | `expired_qr_codes` never consulted (`isQRCodeExpired` dead) | Enforced in R-128 |
| D-031 | Medium | POS holders | Buyer ID number force-copied to all holders → whole group counted as one person (refused) or limit miscounted | Per-holder ID kept; cap counts in-memory request holders (R-025) |
| D-032 | Medium | Payment errors | Anonymous unvalidated inserts; 500-row client-side list; delete without confirmation; no link to the order | Server-side writes, pagination, "resolved" flag, order link |
| D-033 | Medium | `sadad-payment` restart | Product lines rebuilt from current prices (may not sum to the stored total); expired-but-unswept orders re-payable | Use `order_items`; refuse after `payment_expires_at` (R-119) |
| D-034 | Medium | `change-order-event` | Not transactional (expired codes written before a move that may fail); new event not validated; new codes off-scheme; customer not re-notified; UI select fires instantly, lists archived events, swallows the 409 | One transaction, validated target, R-068 codes, confirmation dialog, optional auto-resend |
| D-035 | Medium | `mapOrderError` | Plain `insufficient_stock` (from status/event changes) is unmapped → 500 | Map to 409 `insufficient_stock` everywhere |
| D-036 | Medium | create-order | QR PNGs rendered synchronously before responding | Queue `RenderTicketQr` after commit |
| D-037 | Medium | Customers | One customer row per order; repeat-buyer badge by exact phone string; "edit customer" overwrites the phone of all holders of all orders | `person_key`/`phone_e164` directory; edit only the customer record |
| D-038 | Medium | `tickets` | No `sold ≤ capacity` guarantee; CHECKs added NOT VALID; client capacity check used confirmed-only counts | Server validation R-051 |
| D-039 | Medium | EditEventDialog | Saves event date as local midnight (time lost); non-transactional; `end_date`, `video_url` not editable | Qatar date-time pickers, transaction, all fields |
| D-040 | Medium | Cut-off | 18:00 cut-off hard-coded: an event starting 19:00 cannot be checked in on its day | Per-event cut-off (R-004) |
| D-041 | Medium | Multi-day | Check-in "today", capacity alert and listing ignore `end_date` → later days of a season fail | R-004/R-005 |
| D-042 | Medium | StartNewEventDialog | Archive-all then create is non-transactional (can leave no current event); browser time zone | `StartNewEventAction` transaction, Qatar zone |
| D-043 | Medium | Excel import | Excel serial dates inserted raw; invalid rows skipped silently; no `end_date`/ticket types | Maatwebsite import with validation report |
| D-044 | Medium | Settings data tab | Bulk deletes done client-side, non-transactional, unaudited, no typed confirmation | Server actions, typed confirmation, audit, super_admin only |
| D-045 | Medium | Settings | WhatsApp webhook "Save" coupled to logo save (requires logo URL); no URL/hex validation | Separate forms, validation rules |
| D-046 | Medium | Holders/orders | "confirmed_at/by" used for both payment confirmation and gate scan; legacy check-in writes `orders.confirmed_at` | Renamed check-in columns (4.3) |
| D-047 | Low | Recycle bin | `qr_image_url` not copied → restored ticket loses its picture | Copied (R-194) |
| D-048 | Medium | Holder delete/bin | Order `quantity`/`total_amount` not adjusted → order totals disagree with holders | Show both "original" and "active" counts; refunds explicit |
| D-049 | Medium | Stock display | UI "sold/remaining" = confirmed holders, DB counter = pending+confirmed → screens show stock the server refuses | One definition: capacity, held, confirmed, remaining = capacity − held |
| D-050 | Medium | Many lists | PostgREST 1 000-row truncation and unpaginated loads (customers, event counts, notifications 200, logs 500, payment errors 500, «عرض الكل», 40k/60k holder scans) | Server pagination + SQL aggregates |
| D-051 | Medium | Realtime | Unfiltered subscriptions on `ticket_holders`/`orders` (selection page, viewer, POS, capacity alert, live bookings, duplicate channels in Orders) → refetch storms | Scoped channels (4.5), debounced UI |
| D-052 | Medium | Live Bookings | Online sales never raise the new-booking toast (INSERT is pending); invoice markers read only 500 log rows; a new `AudioContext` per sound; toggles not persisted | Broadcast on confirmation; indexed lookup; one audio context; persist toggles |
| D-053 | Medium | Orders tab | Pending orders listed under «فشل»; counts confirmed-only; unescaped search breaks queries; booked time in browser zone | Three status tabs, server search, Qatar times |
| D-054 | Medium | Scanner | Phone search queries tables directly (passcode staff get nothing), lists all dates and unpaid cash; POS rule inconsistent across branches; related-ticket split in browser zone; transport errors silent; only the last error shown; PII in console logs | Server lookup endpoint, R-136, consistent `CheckInService`, aggregated results |
| D-055 | Low | `ticket_holders.ticket_type` | Free text; unknown type silently skips stock and cap; holder↔ticket link only by (event, type) | Enum + `ticket_type_id` FK |
| D-056 | Medium | Scanner i18n | Missing keys render literally (`validationError`, `processing`, `alreadyCheckedIn`); English month names; static «متصل» badge | Real Arabic strings; live connection indicator |
| D-057 | Low | i18n | Missing `phone` and `bookingConfirmed` keys; English toasts/pages (receipts page, QR tool, diagnostics, en-US dates) | `lang/ar` + `lang/en` complete; CI check for missing keys |
| D-058 | Medium | Checkout | Name field trimmed on every keystroke → multi-word names cannot be typed | Trim on submit |
| D-059 | Medium | Checkout | Submit button outside the form: HTML5 validation dead; Enter bypasses the terms checkbox | Single form, server-validated terms acceptance |
| D-060 | Medium | Checkout | Changing quantities wipes holder data; parking not capped on the checkout stepper; extra holders' country code forced by nationality | Preserve holder rows; caps R-012/R-013; independent code selector |
| D-061 | Medium | Gate board | Counts only `confirmed` orders (cash bookings admitted at the gate → scanned > issued); `no_current_event` returned as 400 so its message never shows | Count admissible tickets; 200 with code |
| D-062 | Low | Result/confirmation | Resume button condition dead for `failed`; confirmation shows only the latest of 10 refs; cancelled shown as pending; copy promises e-mail vs WhatsApp | Correct states and copy; list of remembered bookings |
| D-063 | Low | Invoice page | Renders for pending/cancelled orders without a clear status | Prominent status banner; QR hidden unless admissible |
| D-064 | Low | Print/PDF | Brand and year hard-coded; violet `#8B5CF6` print ticket off-brand; PDF total printed without 2 decimals | Settings-driven brand (R-167), luxury theme, money formatter |
| D-065 | Medium | Attendance | A user holding both roles resolves to 2 rows → treated as non-editor; server accepts future dates; «مسح اليوم» without confirmation | R-139, R-011, confirmation |
| D-066 | Medium | Staff accounts | Lists only first 200 auth users; admins only in `admin_users` not listed; non-atomic role switch; any role string; no self/last-admin protection; no audit | R-150 |
| D-067 | Medium | Transactions | Sadad status filter page-local; totals loop capped at 20 000 rows; Sadad API login on every row (≈ 30 per page); error messages lost; cache never invalidated; debounced input glitch | `sadad_transactions` cache + server totals + token cache |
| D-068 | Medium | CapacityAlert | "Today's event" by browser-local day and start date only; 4 queries per realtime event | Server query per R-004, cached 30 s |
| D-069 | Medium | Notifications | Read state per browser; badge counts all statuses; 200-row cap; raw ticket type shown | `notifications_seen_at`, filters, labels |
| D-070 | Medium | Revenue figures | Revenue = holders × **current** price (Tickets tab, Live Bookings, MCP summary, Customers/TicketViewer price lookup ignoring the event) | Use `order_items.unit_price` / `orders.total_amount` |
| D-071 | Medium | Reports | Orders without event excluded from «جميع السجلات»; totals mix statuses; all rows loaded client-side | Queued export with status-aware totals |
| D-072 | Medium | POS | Button promises «…وإرسال الفاتورة» but no customer invoice is sent; hidden e-mail field still submitted; booking reference not shown after sale; PII logged client-side | Decide: send invoice when e-mail/phone present; show reference + print/QR; server audit |
| D-073 | Low | POS users | Hard delete fails when referenced; management RLS by `admin_users` only | Deactivate/soft delete; policies |
| D-074 | Low | Sadad diagnostic/dialog | English check messages; default domain `qcamelmc.org` hard-coded; stale dialog data | Arabic messages, config-driven |
| D-075 | Medium | Hard-coded business values | `https://qcamelmc.org` in e-mails; season start `2026-01-12`; excluded invoice dates 2025-11-06..08; receipt prices 200/300/500 | Settings/config (R-155, R-166, R-178, R-200) |
| D-076 | Low | Indexes | Duplicate indexes (`idx_orders_booking_reference`, `orders_event_status_idx`, `idx_ticket_holders_qr_code`, no-op `idx_page_views_viewed_at`) | Single index set (4.3) |
| D-077 | Medium | POS receipts | No duplicate detection; image uploaded before OCR (orphans); row delete leaves the file; `created_by` never set; hard-coded prices; route open to staff but data admin-only; English UI; per-keystroke writes | R-188, R-200, unique key, parse-then-store, debounced saves, permission `receipts.manage` |
| D-078 | Low | Naming | `reserve_tickets` / `useReserveTickets` imply a reservation but only read | `check_availability` (R-054) |
| D-079 | Low | Capacity banner | Progress bar animates 4 s while the banner closes after 10 s | Bar duration = auto-hide (R-177) |
| D-080 | Low | FKs | `user_roles.user_id`, `staff_attendance.marked_by`, `deleted_tickets.deleted_by`, `expired_qr_codes.order_id` without FKs | FKs in 4.x |
| D-081 | Low | Nullability | `orders.payment_status`, `orders.created_at`, `events.is_active` nullable | NOT NULL with defaults |
| D-082 | Medium | Visitor tracking | Browser calls `ipapi.co` (privacy/consent); Android→Linux, iPhone→macOS mis-detection; `/staff*` and `/invoice/*` tracked; raw IPs stored and shown; SPA navigations only recorded on `popstate`/5-min tick | Server-side tracking from request, proper UA parser, exclusions R-201, IP masking setting |
| D-083 | Medium | Time zones | UTC grouping in analytics and daily sales; browser-zone grouping in Tickets/Orders/Live Bookings; UTC midnight date filters | R-001/R-002/R-010 everywhere |
| D-084 | Low | `public_settings` | Publishes `admin_phone`; definer view bypasses RLS | `publish_admin_phone` flag; read model |
| D-085 | Low | Realtime gaps | UI subscribed to `payment_errors` and `email_delivery_events`, which were never published | Broadcast events (4.5) |
| D-086 | Low | Docs | `.env.example` mentions a non-existent `private_settings` table | Accurate `.env.example` |
| D-087 | Low | `handle-email-events` | Records every bounce as template `booking-invoice`; matches recipient with raw ILIKE (wildcards); dedupe by detail text | `provider_message_id` matching |
| D-088 | Low | Ticket code | `lpad(n,2)` truncates at ≥ 100 → duplicate code | R-064 |
| D-089 | Low | `orders_payment_id_key` | May have been created non-unique when duplicates existed | Importer F-04 then UNIQUE |
| D-090 | Medium | Sadad API | `sadad_api_key` loaded but unused; `_shared` logs in with the checkout secret while `sadad-transaction` uses env `SADAD_API_SECRET_KEY`, live host only, different endpoint, no timeout, no token cache | One `SadadApi` client, dedicated `api_login_secret`, env-aware, token cache, 10 s timeout |
| D-091 | Low | Payment-failed mail | Admin copy re-attempted on every call; `failureSource` always yields `sadad` (reason constant); two different bank keyword lists | One classifier (R-124); admin copy deduped |
| D-092 | Low | Client caches | 2–5 min localStorage caches show stale availability/branding; `current_event_id` switch not instant | Server cache with explicit flush (R-185) |
| D-093 | Low | Ticket selection | Assumes exactly 3 ticket rows; counts keyed by type; dead code (`handleQuantityChange`, unused state/imports); English error toasts | Robust per-type data; Arabic messages |
| D-094 | Low | `customers.email` | NOT NULL storing `''` | Accessor returns null; validation consistent |
| D-095 | Low | Lifetime totals | Each holder counted under both ID and phone keys (approximate); admin-only RPC on a staff page → badge silently hidden for staff | Single key per holder; staff permission |
| D-096 | Low | Shell/auth UX | Logout does not clear passcode access; signed-in non-staff sees the login card without sign-out; mobile menu icon never toggles | Session logout clears all guards; explicit sign-out; fixed icon |
| D-097 | Low | Dead code | Legacy RPCs (`create_public_booking`, `create_pos_booking`, `enforce_ticket_capacity`, `update_ticket_sold_quantity`, `generate_booking_reference`, `generate_ticket_holder_reference`), `recover-pending-orders` stub, `App.css`, hidden home links, `forgetOrders`, `getRemainingAllowance`, `orderIds`, `bookingGuardMessage` tags, student-ticket/banner i18n keys, `togglePresence`, `confirmPayment`, unconfirm scanner branch, `fetchSameBookingTickets`, unused imports | Do not port |
| D-098 | Low | Concurrency | Every sale of an event serialised by row locks; never load-tested; no deadlock retry | R-047 retry, load test 200 concurrent checkouts in CI-nightly |
| D-099 | Low | Tooling | DB tests on PGlite (PG 18) vs production 15/17; `strictNullChecks` off; ~180 `any` warnings | CI runs Pest on MySQL 8 and PostgreSQL; Larastan level 8 |
| D-100 | Low | Uploads | Replaced images never deleted; no "remove image" for hero/before-footer/header background | Medialibrary single-file collections with delete |
| D-101 | Medium | POS customer lookup | One history RPC per result (N+1); search by phone only; `eventId` prop unused (allowance badge missing) | Single query with history; search name/phone/ID; show remaining allowance (R-023) |
| D-102 | Low | ticket-checkin `lookup` | Third query filters an embedded column without inner join → unrelated rows with `orders: null` | Proper join in `CheckInService::lookup` |
| D-103 | Medium | Booked tickets by date | Unpaid/cancelled tickets mixed with paid and counted as "not scanned"; 10 s polling plus realtime | Filters by payment state, realtime only |
| D-104 | Low | Tickets tab | Grand-total row shows the first day's prices; dates grouped in browser zone; two events with the same timestamp merge | Per-event grouping, Qatar dates |
| D-105 | Medium | Hold expiry | No scheduled expiry (lazy only) and the recovery job is a disabled stub → expired seats stay held until the next sale/visit of that event; paid-but-unnoticed orders wait for manual verify | R-044 scheduler + `sadad:reconcile-pending` |
| D-106 | Low | Email preview | Preview endpoint compares API key with plain `!==` | Filament page behind `settings.manage` |

### 5.b Test-pinned MUST rules (Pest acceptance tests)

Port of `tests/db/*.sql` (DB-level assertions), `tests/edge/*.ts` (HTTP/domain) and `src/**/*.test.*` (client behaviour, now Livewire/JS or PHP helpers). Fixture (keep the same numbers, `tests/Pest/Fixtures/Festival.php`): admin user (role admin), member user (no role); **Event A** active (vip 10 @ 200, normal 10 @ 100, parking 10 @ 10), **Event B** active (normal 10 @ 50), **Event X** inactive (normal 10 @ 50); customer phone `5551 2345`, ID `29850123456`, nationality `قطر`. Each item below is one Pest `it()`; "MUST" items are release-blocking.

**Data migration (importer, from `05-legacy-data`)**

| ID | Acceptance test |
|---|---|
| T-001 | Importer keeps webhook URL, Sadad secret, merchant id and logo unchanged in their new homes; `sadad.environment` defaults to `auto`; `site_url` starts null |
| T-002 | A holder whose `qr_code` is an image URL becomes code = file name without `.png`/query, URL moved to `qr_image_url`; plain codes untouched with null picture; afterwards no code matches `^https?://` |
| T-003 | A 3-day-old unpaid sadad order is closed `cancelled`/`expired`; a 5-minute-old one stays `pending` with `payment_expires_at` ≈ created + 30 min (between now+24 and now+26 min); confirmed and cancelled orders untouched |
| T-004 | Recount after import: normal = 2 confirmed online + 1 confirmed POS = 3 (cancelled excluded); vip = 1 (fresh pending hold); parking drift 5 → 0 |
| T-005 | No legacy counter/capacity logic exists (no listener or trigger equivalent); the per-person cap is active |
| T-006 | `orders.payment_id` has a UNIQUE index |

**Access control (from `10-security`)**

| ID | Acceptance test |
|---|---|
| T-007 | Guest receives no customers, orders, holders or users through any route/API (0 rows / 401) |
| T-008 | Guest cannot read payment events, Sadad secret or webhook URL (403/401) |
| T-009 | Guest cannot create customers, orders (even "confirmed") or holders except through `POST /api/v1/orders` |
| T-010 | Guest cannot update orders (0 changes) |
| T-011 | Guest cannot call create-as-POS, confirm, fail, expire, cancel, recount or claim-check operations (401/403) |
| T-012 | Guest sees only active events (2 of 3), can read ticket prices (≥ 4 types) and public settings (logo, header colour, hero text) |
| T-013 | Public settings never contain Sadad secret/API key/merchant id, webhook URL or site URL |
| T-014 | Guest may check availability (1 normal → success), per-person count (≥ 0) and release expired holds (≥ 0) |
| T-015 | Status lookup `['QTR-NOPE']` → `[]`; invoice lookup `QTR-NOPE` → 404; a real reference returns holders **without** `id_number` |
| T-016 | A signed-in member without role sees 0 customers/orders/holders/settings/payment events, cannot create orders as POS, cannot update orders; cancel and recount → 403 `forbidden` |
| T-017 | A member cannot write to the QR disk through any endpoint; an admin can trigger QR generation |
| T-018 | Admin invoice lookup includes holder `id_number` `29850123456`; admin sees customers/orders/holders (1 each), can read the Sadad secret status, can update `site_url` |
| T-019 | Admin cancel returns `cancelled` and stores note `admin_cancelled` |
| T-020 | Queue workers / system context can read secrets and create orders |

**Order creation (from `20-orders`, `create-order`, `validation`)**

| ID | Acceptance test |
|---|---|
| T-021 | Total comes from DB prices: 1 vip (200) + 2 normal (100) = **400.00** |
| T-022 | Web sadad order starts `pending`, `payment_expires_at` within 29–31 min, reserves stock (vip sold 1, normal 2, parking 0) |
| T-023 | Reference matches `^QTR-[0-9]{1,2}-[0-9]{1,2}-[0-9]{4}-[0-9A-F]{12}$` and embeds the event date in Asia/Qatar, unpadded |
| T-024 | One holder per ticket with codes `<ref>-TKT01..03`; order `ticket_type` = most expensive (vip); customer ID stored |
| T-025 | Web cash order is `pending` with no `payment_expires_at` |
| T-026 | POS order (admin actor, cashier) is `confirmed`, ref `^POS-`, `confirmed_by`=admin, `pos_user_id`=cashier, `paid_at`/`confirmed_at` set, `n8n_response_message`=`طلب من نقطة البيع - POS` |
| T-027 | Holder country code: explicit `+966` kept; phone `+971 55510002` without code yields `+971` |
| T-028 | Rejections: 11 normal when 10 exist (POS) → `insufficient_stock:normal` (409 + `ticket_type`); web 3 vip + 3 normal → `quantity_limit_exceeded`; web 6 parking → `quantity_limit_exceeded`; 2 items 1 holder → `holders_mismatch`; items from 2 events → `invalid_tickets`; unknown ticket → `invalid_tickets`; web sale of inactive event → `event_not_available`; empty items → validation; source `admin` → `invalid_source`/validation |
| T-029 | Any rejected order leaves no customer, order, holder or stock change |
| T-030 | Per person: after 3 tickets for ID `30011112222` the count is 3; another 3 → `ticket_limit_exceeded` (422, Arabic text); exactly 2 more succeed (total 5); refused order holds no seats; another person unaffected |
| T-031 | 2 QAR sadad order → `below_minimum_amount`; same as cash succeeds (total 2.00) |
| T-032 | An expired online order is not closed by a sale of another event; it is closed (`cancelled`/`expired`) by the next sale of the same event and its seats return |
| T-033 | Input is trimmed/normalised; customer country code defaults `+974`; e-mail defaults `''` |
| T-034 | Rejects non-object bodies (null, 42, "x", []), unknown payment method, quantities 0/-1/1.5/21/"2"/null, malformed UUIDs, duplicate ticket ids, holders not one-per-ticket, names > 100 or with control chars, invalid e-mail (empty allowed), > 6 items, > 30 tickets, bad holder country code, phone with letters or `'12'`, invalid ID, nationality > 60, unknown source |
| T-035 | All validation errors are returned at once: 400 `{code:'validation_failed', errors:[…]}` |
| T-036 | Client `total_amount`, `price` and `payment_status` are ignored (never reach the action) |
| T-037 | OPTIONS → 204; non-POST → 405; invalid JSON → 400 `invalid_json`; body > 64 000 chars → 413 |
| T-038 | Errors use `{success:false, code, error, ...}`; unexpected errors → 500 `internal_error` without internals |
| T-039 | Sadad not configured → 503 `payment_unavailable` **before** any stock is reserved; cash orders still work |
| T-040 | A 5th open unpaid order for the same phone → 429 `too_many_pending_orders`; POS exempt; formatting variants of the phone count as the same phone |
| T-041 | `return_origin` stored only for a safe origin (`javascript:` → null) |
| T-042 | Online response contains `payment.url='https://sadadqa.com/webpurchase'` and fields `merchant_id, ORDER_ID, TXN_AMOUNT 'nnn.nn', WEBSITE, CALLBACK_URL, MOBILE_NO ('97455512345'), EMAIL, txnDate (Qatar), signature (64 upper hex), productdetail[i][…]`; the secret never appears; missing e-mail → `noreply@<domain>` / `.invalid` variants |
| T-043 | Cash web order dispatches the order-created n8n payload (`bookingReference, adminPhone '97455500000', customer, order, ticketHolders, timestamp`) in background; online orders dispatch nothing until paid |
| T-044 | One QR PNG per ticket stored as `<code>.png` with URL in `qr_image_url`; a QR failure does not fail the order |
| T-045 | POS: guest, anon and plain member → 401; adding `source:'pos'` to a public request → 401; admin, supervisor and passcode session allowed (actor null for passcode); sadad → 400; invalid `pos_user_id` → 400; web orders never carry a cashier; admin sale alert dispatched in background and its failure does not fail the sale |

**Payments and stock (from `30-payments-and-stock`, `payment-*` tests)**

| ID | Acceptance test |
|---|---|
| T-046 | Online order of 2 holds 2 seats; confirm → `confirmed`, stores `payment_id`, stamps `paid_at`/`confirmed_at`, stock unchanged |
| T-047 | Replay → `already_confirmed`; a different txn → `already_confirmed` and `payment_id` not overwritten; unknown ref → `order_not_found` |
| T-048 | A txn already used by another order → `transaction_already_used`, order stays pending |
| T-049 | Fail on pending → `failed`: status `cancelled`, note `payment_failed`, reason `declined`, seats released; repeat → `not_pending` (no double release); on confirmed → `not_pending`, stays confirmed |
| T-050 | Expiry closes only pending **sadad** orders past expiry (returns 1; cash/confirmed exempt), note `expired`, seat released; second run returns 0 |
| T-051 | Late payment on an expired order with seats left → confirmed, seat re-reserved, note and error reason cleared |
| T-052 | Late payment when sold out → `paid_after_expiry_no_stock`, stays `cancelled`, `payment_id` stored, note set, no oversell (and a refund task is created — NEW) |
| T-053 | Admin status change to cancelled releases seats; reinstating to pending re-reserves; reinstating when sold out fails `insufficient_stock` (409) |
| T-054 | Deleting one holder of an active order releases 1 seat; deleting the order releases the rest; deleting an already-cancelled order releases nothing |
| T-055 | Bin (admin) frees the seat; restore retakes it with the same holder id; restore when sold out fails `insufficient_stock`; bin keeps `qr_image_url` (NEW) |
| T-056 | Moving an order to another event frees old seats and takes new ones; moving back restores counts; moving to a full event fails and changes nothing; old codes land in `expired_qr_codes` |
| T-057 | Admin cancel with note `customer asked` → `cancelled` + note; second cancel → `already_inactive`; seats released |
| T-058 | Recount makes `sold_quantity` equal holders of pending+confirmed orders |
| T-059 | Check throttle: first call allowed, second within 10 s refused; confirmed and unknown refs refused; failed online payment re-checkable; staff-cancelled not |
| T-060 | Status lookup (guest) with `[real, 'QTR-NOPE', 'bad ref!', real, real]` returns exactly the 3 real ones; confirmed → `confirmed`, failed online → `failed`, staff cancel → `cancelled`; includes `event.title`, `event_id`, `tickets[]`; never `customer_id`/`id_number`/`payment_note` |
| T-061 | Held counts include live pending holds and exclude an expired-but-unclosed hold immediately; release-expired (guest) closes it (≥ 1) |
| T-062 | Confirm only when API success + matching amount (150.004 ≈ 150 matches; 149.99 ≠ 150; non-numeric no match) + matching `website_ref_no` + txn number |
| T-063 | API success with amount mismatch → `review` even if the signed callback states the right amount; txn of another order → `pending`; valid checksum + API unavailable → `review`; + not_found/in_progress → `pending`; API not consulted → `review`; unsigned success + unavailable → `review`; unsigned + not_found → `pending` |
| T-064 | Fail only on API `failed`; signed failure alone → pending; unsigned failure ignored |
| T-065 | Already confirmed → `noop` without calling Sadad; expired/failed order may still be confirmed by API success; final staff-cancelled order → `review` (NEW, D-012) |
| T-066 | Every processing writes exactly one `payment_events` row with payload ≤ 4 000 chars (test bound < 8 100); logging/notification failures do not change the outcome; unknown order → `order_not_found` without Sadad call |
| T-067 | On `confirmed` (not `already_confirmed`): invoice mail, admin alert and n8n `payment_confirmed` dispatched exactly once (n8n skipped when off; mails still sent); queued so the response does not wait |
| T-068 | On `failed`: `payment_errors` row (`PAYMENT_FAILED`, source bank vs sadad by keywords) + payment-failed mail; pending/review → no follow-ups |
| T-069 | Callback (form POST or GET): 303 to `<site_url or return_origin>/payment/result?ref=<ref>`; no safe base → 200 HTML containing the ref; garbage never throws; `/sadad-callback` behaves the same |
| T-070 | Webhook always 200 `{status:'success'}` (malformed bodies, internal errors); a replay neither confirms nor notifies twice |
| T-071 | Verify `{booking_reference}` → `{success:true, checked, payment_status}`; throttled → `checked:false` without Sadad call; admin bypasses throttle (event source `admin`, else `poll`); bad ref 400; unknown 404 |
| T-072 | Restart payment: pending sadad → freshly signed request from stored lines/total; confirmed/failed/cash → 409 `not_payable`; unknown 404; not configured 503; expired → 409 (NEW) |
| T-073 | Repository/read model maps cancelled + {payment_failed, expired, paid_after_expiry_no_stock} → `failed`, other cancelled → `cancelled`; `payment_note` never exposed; unknown order → null |
| T-074 | Payment error record copies event, quantity, payment id, customer name/phone from the order |

**Sadad protocol (from `sadad-core`, `sadad-api`)**

| ID | Acceptance test |
|---|---|
| T-075 | Signature of the reference field set with secret `S3CR3T` equals `6A6D258727B321A8EC7C02CAFD96B2F14D754BA1627029F2920D27E11F4D0B49` (ksort byte order, `signature`/`productdetail` excluded, no URL-encoding) |
| T-076 | Amount `0.1+0.2` formats `0.30`; 0, negative, NaN, INF rejected; empty secret/merchant rejected; non-https callback rejected |
| T-077 | `txnDate` for 2026-10-09T21:30Z is `2026-10-10` |
| T-078 | Callback checksum verifies the reference vector `6082cc9b43e1c98b95f4911c9b696810cfa912f4a88e306217500a144bf26b8a` case-insensitively; JSON numbers/null stringified like PHP |
| T-079 | Normalisation: `ORDERID`/`websiteRefNo` → ref (unsafe → null); status 3→success, 2/7→failed, 1→in_progress, else unknown; `TXN_SUCCESS`/`TXN_FAILURE` fallback; `issandboxmode`/`isTestMode` → sandbox |
| T-080 | API: login body `{sadadId:number, secretKey, domain}`; token sent raw in `Authorization`; list by `website_ref_no`; fallback getTransaction ignored when it belongs to another order; `auto` tries live then sandbox, explicit env only that one |
| T-081 | API status ids 3 success; 2/4/7 failed; 1/5/6 in progress; success preferred over earlier failed rows; non-numeric amount ignored; network/5xx → unavailable; unsafe ref → unavailable without request; never throws; environment detection returns errors like `login_live_401`, `login_sandbox_401` |
| T-082 | Mobile: `('55512345','+974')`→`97455512345`; `'+974 5551 2345'`→same; `'0097455512345'`→`97455512345`; `('0555 1234','+966')`→`9665551234`; empty code→974; `('+966 5551 0001','+974')`→`96655510001`; `'+20 100 123 4567'`→`201001234567` |
| T-083 | `isSadadConfigured` requires secret, merchant id and website domain; callback URL strips trailing slash; return base prefers `site_url` then https origin (or `http://localhost`), refuses `javascript:`, http non-localhost, paths/queries, `data:`, protocol-relative, junk |
| T-084 | Diagnose returns `{ready, environment, callbackUrl, webhookUrl, checks:[merchant_id|secret|api_login|website: ok|warn|error]}`; a URL-looking website → `warn`; never reveals the secret |

**Staff access and throttling (from `staff-access`, `http-auth`)**

| ID | Acceptance test |
|---|---|
| T-085 | Passcode only from `STAFF_PASSCODE`: unset/blank → `not_configured`; < 8 chars → `too_short`; `@@@Qatar123` → `revoked`; none of these record attempts |
| T-086 | Match is exact and case-sensitive after trimming surrounding spaces; non-strings never match; comparison is constant-time (`hash_equals`) |
| T-087 | Staff = admin, supervisor or passcode session; a member with the right passcode counts as passcode staff; an auth backend failure never grants access |
| T-088 | Admin-only endpoints refuse supervisors and passcode sessions (403) and guests (401) |
| T-089 | Per-caller: 5 wrong in 15 min → blocked until 30 min after the last failure; attempts_left counts down 5→0; success ends the streak; row order irrelevant; failures older than the window ignored |
| T-090 | Global: 60 wrong in 15 min (any IPs) → 15-min block; only wrong guesses counted; success does not reset |
| T-091 | Responses: ok → `{ok:true}`; wrong → `{ok:false, attempts_left:n}`; `__warmup__` → `{ok:false, warmup:true}` not recorded; blocked → `{ok:false, blocked:true, attempts_left:0, retry_after_seconds>0}` even for the right passcode; log outage still answers; junk body → 200; GET → 405; OPTIONS → 204 |
| T-092 | Account login: 5 failures in 15 min lock the e-mail **and** IP for 30 min from the last failure (cannot be bypassed by calling the auth endpoint directly); lockout sends one `login-alert` per identifier per 30-min bucket and logs `login_blocked` (NEW enforcement of D-010) |
| T-093 | Bearer extraction is case-insensitive; the public/anon token is not a session; `optionalAdmin` returns null (never throws) for guests/members |
| T-094 | JSON helper returns CORS headers where the API is cross-origin; `HttpError` becomes its status/code; unexpected messages never leak |
| T-095 | Error mapping: `TICKET_LIMIT_EXCEEDED: <text>` → 422 `ticket_limit_exceeded` with text; `insufficient_stock:<type>` → 409 + `ticket_type`; plain `insufficient_stock` → 409 (NEW, D-035); quantity/minimum/event/tickets/holders/request/forbidden mapped as R-codes; unknown → null → 500 |

**Admin endpoints, automation, QR (from `admin-endpoints`, `notify-qr-settings`, `followups-and-repo`, `qr-roundtrip`)**

| ID | Acceptance test |
|---|---|
| T-096 | `send-to-webhook`, `generate-qr-code`, `backfill-qr-codes`, `regenerate-booking-qr-codes`, `sadad-diagnose` equivalents: 401 without session, 403 for non-admins, 204 for OPTIONS (API routes) |
| T-097 | Relay forwards a JSON object → `{success:true, response}`; not configured → 400 `webhook_not_configured` naming the missing automation; upstream 404 → 200 `{error:'Webhook not found or inactive', status:404}`; network failure → 200 with error; non-object body → 400 |
| T-098 | `send_email` goes to the e-mail webhook (fallback main); `send_ticket` to the main webhook |
| T-099 | Success marks the order "sending" (`جاري إرسال البريد الإلكتروني...` / `جاري الإرسال إلى واتساب...`, `n8n_responded_at` null) by id, else by reference; a refused automation marks nothing |
| T-100 | `notify:'payment_confirmed'` builds the payload from the DB (ignores browser data); unknown ref 404; bad ref 400 |
| T-101 | Generate QR `{text, filename}` → `{url}`; filenames `../x`, `a/b`, `''`, `x.png`, `' '` and empty text → 400 |
| T-102 | Backfill creates pictures for holders missing `qr_image_url` → `{success, updated, skipped, errors[]}`, continues past failures, max 100 per run, never changes codes |
| T-103 | Regenerate `{booking_reference}` → `{success, updated, total}`; unknown 404; bad ref 400 |
| T-104 | QR object name accepts `QTR-ABC123DEF456-TKT01` → `QTR-ABC123DEF456-TKT01.png`; rejects `../secret`, `a/b`, `a b`, empty, 200 chars, `.hidden`, `a.png`, `ünï` |
| T-105 | Rendered QR PNG has the PNG signature `137,80,78,71,13,10,26,10` and decodes back to exactly the ticket code (round trip) |
| T-106 | Order-created payload `{bookingReference, customer{…, phone international}, order, adminPhone (digits)|null, timestamp ISO, ticketHolders[{…, phone with holder code, qr_code (code), qr_code_image (url|null)}]}` |
| T-107 | Paid payload: order + `customers`, `events`, `ticket_holders` (qr_code = image URL if present else code, `qr_code_text`, `qr_code_image`, `phone_international`, `ticket_price` from the event's prices), `action:'payment_confirmed'`, `timestamp`, `sadad_response:{transaction_number}`, singular `customer`/`event` aliases |
| T-108 | Webhook poster never throws; non-2xx → `{ok:false,status}`; refuses non-http(s) URLs |
| T-109 | Settings reader trims values; WhatsApp/e-mail switches off → URL null; unknown environment → `auto`; missing settings → all null + `auto` |
| T-110 | Admin = admin role (migrated from `admin_users` or role admin); supervisor = migrated moderator |
| T-111 | `create_order` receives the cashier; the DB limit message becomes the customer-facing 422 |
| T-112 | Holder country code accepted when `^\+\d{1,4}$`, rejected otherwise; source defaults to `web`; only `web`/`pos` exist |

**Client behaviour (from `usePaymentStatus.test.tsx`, `payment.test.ts`, `api-client.test.ts`, `phone.test.ts`, `tickets.test.ts`) — Pest + Playwright**

| ID | Acceptance test |
|---|---|
| T-113 | Result page: confirmed on first read → no verify call; pending → verify at 0 s then every ≥ 12 s; `delayed` after 120 s and polling stops; failed → verify exactly once; cancelled → never verify; cash → redirect to confirmation; unknown ref → not found; network errors retried every 3 s; «تحقق مرة أخرى» restarts; leaving the page stops timers |
| T-114 | Sadad redirect form: `method=post`, `target=_self`, every field as hidden input with exact values (quotes, `&`, `<` intact); refuses non-https and non-`sadadqa.com` hosts and look-alikes (`sadadqa.com.evil.example`); submits only when attached to the DOM |
| T-115 | Remembered bookings keep the last 10, de-duplicate (re-add moves to end), survive corrupt JSON |
| T-116 | API client: create body passed through; error body `{code,error,ticket_type}` surfaced with status; non-JSON → `request_failed`; fetch failure → `network_error`; never throws; restart/verify send only `{booking_reference}`; empty status list makes no call; cancel maps `forbidden`; POS body never contains `total|price|amount|payment_status`; `ticket_limit_exceeded` message verbatim; unknown codes → generic Arabic message |
| T-117 | `toWhatsAppNumber`: `('0555 1234','+966')`→`9665551234`; `('+966 5551 0001','+974')`→`96655510001`; never forces the Qatar prefix onto another country's number; empty → null |
| T-118 | `normalizeScannedCode`: code unchanged; whitespace/newlines trimmed; image URL (with query/hash, `.PNG`) → code; empty/odd input never throws |

**NEW rules the rebuild adds (must also be tested)**

| ID | Acceptance test |
|---|---|
| T-119 | Gate refuses a cancelled POS or cash order (D-005) and a code listed in `expired_qr_codes` (D-030) |
| T-120 | Two concurrent admits of the same ticket: exactly one succeeds, the other gets «تم استخدام التذكرة مسبقاً»; offline queue replay is idempotent by client UUID |
| T-121 | Check-in before/after the per-event cut-off and on day 2 of a multi-day event behaves per R-004/R-005 |
| T-122 | Passcode kiosk session cannot open any `/admin/dashboard/*` page or admin API (403) and expires after 10 days |
| T-123 | Receipt images are not publicly reachable; signed URL expires after 5 minutes |
| T-124 | Auto-invoice job run twice concurrently sends each order once; respects interval (≥ 10 s), batch 1–10, 2-attempt hold, cut-off and excluded dates |
| T-125 | Analytics and daily figures group by Qatar date (a page view at 22:30Z belongs to the next Qatar day) |
| T-126 | Deleting an event with orders is refused; soft-deleted orders release seats once and stay auditable |
| T-127 | Staff account service refuses to demote/revoke the last admin and the acting admin |
| T-128 | No settings response, Livewire payload or broadcast contains a secret or a national ID for non-admins |


---

## Part 6 — Target architecture, standards & Definition of Done

### 6.1 Technology stack (fixed decisions)
| Layer | Choice | Why |
|---|---|---|
| Language / framework | PHP 8.3+ (8.4 recommended), **Laravel 12.x** (latest stable at build time) | LTS-grade ecosystem, first-party queues/realtime/scheduler |
| Database | **MySQL 8.0.16+** (InnoDB, `utf8mb4_0900_ai_ci`); code stays portable to PostgreSQL 16 | Matches the owner's hosting; CHECK constraints, row locks, JSON |
| Cache / locks / rate limits / queues | **Redis 7** | Atomic locks for stock, throttles, Horizon |
| Queues | **Laravel Horizon** (queues: `payments` (high), `notifications`, `automation`, `qr`, `exports`, `default`) | Visibility, retries, metrics |
| Realtime | **Laravel Reverb** + Laravel Echo (private channels, after-commit events) | Replaces Supabase Realtime |
| Scheduler | Laravel Scheduler (`routes/console.php`) with `withoutOverlapping()->onOneServer()` | Replaces pg_cron, lazy expiry and browser-run schedulers |
| Admin back-office | **Filament v4** with a custom luxury theme; custom Filament pages for complex screens (Invoices console, Notifications, Reports, Visitors) | Fast, consistent, role-aware CRUD/tables/filters/actions |
| Public site & staff apps | **Blade + Livewire 3 + Alpine.js + Tailwind CSS 4**, RTL-first, Arabic default + English | SEO-friendly, fast, no SPA chunk errors |
| Staff PWA | Scanner & POS installable (manifest + service worker), scanner offline queue (Phase 20) | Gate reliability |
| Auth | Session guard + **Laravel Fortify** (login, password reset, **2FA TOTP**), **spatie/laravel-permission**, Sanctum (API tokens), passcode kiosk session | Replaces Supabase Auth + `admin_users` + `user_roles` |
| Settings | **spatie/laravel-settings** (groups: `branding`, `automation`, `invoices`, `payments`, `features`, `analytics`, `business`) | Typed, cached; secrets in `.env` |
| Audit | **spatie/laravel-activitylog** | Replaces `activity_logs` |
| Uploads | **spatie/laravel-medialibrary** (disks in Part 4.6) | Image conversions, cleanup |
| QR | **endroid/qr-code** v6 (PNG 800 px, margin 2, ECC M per R-189; on-screen sizes R-190; SVG for print) | Server-side, deterministic |
| PDF | **spatie/laravel-pdf** (Browsershot/Chromium) — mPDF fallback; Arabic fonts Tajawal/Lusail embedded | Correct Arabic shaping & RTL |
| Excel | **maatwebsite/excel** (PhpSpreadsheet) with styled exports & queued large exports | Replaces client-side xlsx |
| HTTP clients | Laravel HTTP client with timeouts/retries (Sadad 8 s, n8n 10 s, geo 3 s) | |
| AI OCR | **prism-php/prism** (provider configurable: Gemini / OpenAI) | Replaces Lovable AI gateway |
| MCP | **laravel/mcp** (read-only tools) | Replaces Lovable MCP |
| API docs | **dedoc/scramble** (OpenAPI at `/docs/api`, admin only) | |
| Quality | **Pest 3**, Larastan level 8+, Pint, Rector, Playwright E2E, k6 load tests | |
| Observability | Sentry (errors), **Laravel Pulse** (perf), Telescope (local only), Horizon metrics, health checks (`spatie/laravel-health`) | |
| Backups | **spatie/laravel-backup** (DB + private disks → S3), daily, 30-day retention | |
| CI/CD | GitHub Actions: lint → static analysis → tests (MySQL + Redis services) → build assets → deploy (zero-downtime, e.g. Deployer/Envoyer/SixPanel git deploy) | |

### 6.2 Application structure
```
app/
  Domain/                      # pure business logic, no HTTP
    Booking/   Actions/ (CreateOrderAction, CancelOrderAction, ChangeOrderEventAction, ManualConfirmAction, RestoreTicketAction…)
               Services/ (StockLedger, PersonLimitGuard, BookingReferenceGenerator, TicketCodeGenerator, HoldExpiry)
               Queries/ (AvailabilityQuery, PersonHistoryQuery, LifetimeTotalsQuery)
               Data/ (DTOs: OrderRequestData, HolderData…)  Enums/ (OrderStatus, PaymentMethod, TicketKind, OrderSource, PaymentNote…)
    Payments/  Contracts/PaymentGateway.php  Sadad/ (SadadConfig, SadadSigner, SadadCheckoutBuilder, SadadApiClient, SadadCallbackParser, SadadDiagnose)
               PaymentDecisionEngine, PaymentProcessor, VerifyPaymentThrottle
    Tickets/   QrService, CheckInService, GateBoardQuery, ScanQueueProcessor
    Messaging/ Mail/ (BookingInvoiceMail, PaymentFailedMail, AdminSaleAlertMail, LoginAlertMail), AutomationRelay, Drivers/ (N8nWebhookDriver, WhatsAppCloudDriver), SendInvoiceBatch
    Staff/     AttendanceService, StaffAccountService, PasscodeAuthenticator, LoginThrottle
    Analytics/ VisitorTracker, VisitorAnalyticsQuery, VisitorsVsScansQuery
    Receipts/  ParseReceiptAction
  Http/Controllers/ (Public, Payments, Webhooks, Api/V1, Staff), Middleware/ (EnsureStaff, EnsureKioskOrStaff, SetLocale, SecurityHeaders, TrackVisitor)
  Livewire/ Public/ (EventList, TicketSelection, Checkout, PaymentResult, Invoice), Staff/ (Pos, Scanner, LiveBookings, GateBoard, Attendance, Hub, Receipts)
  Filament/ Resources/ (Orders, Transactions, Customers, Events, TicketTypes, PaymentErrors, ActivityLog, PosUsers, StaffAccounts), Pages/ (Settings, Invoices, Notifications, Reports, Visitors, VisitorsVsScans, Attendance, LiveVisitors, SadadDiagnostic), Widgets/ (CapacityAlert…)
  Events/ (OrderCreated, OrderStatusChanged, TicketCheckedIn, TicketTypeStockChanged, TicketCapacityIncreased, …) Listeners/ Jobs/ Policies/ Observers/ Settings/ Mcp/
lang/ar/*.php, lang/en/*.php       # every UI string; Arabic is the source of truth
resources/views/ (public, staff, mail, pdf), resources/css/theme.css (design tokens)
tests/ Feature/ Unit/ Browser/ Pest/Fixtures/Festival.php
docs/ SPEC.md, SPEC.ar.md, PROGRESS.md, DECISIONS.md, RUNBOOK.md
```
Rules: controllers/Livewire components stay thin and call **Actions**; every state change of an order happens inside one `DB::transaction()` in an Action; Actions dispatch domain events **after commit**; side effects (mail, n8n, QR) run in queued listeners that are idempotent.

### 6.3 Non-negotiable engineering rules
1. **Server is the authority**: prices, totals, statuses, limits, stock, references and payment state are computed on the server only (fixes D-004, D-015, T-036).
2. **Stock invariant** (`sold_quantity = holders of pending+confirmed orders`) is maintained by `StockLedger` methods called from Actions, with `SELECT … FOR UPDATE` on the event's ticket types in a fixed order (by id) to avoid deadlocks; a nightly `stock:verify` detects drift.
3. **Idempotency** everywhere money or messages are involved: unique `orders.payment_id`, `payment_events` log, `automation_dispatches` unique keys, invoice e-mail `invoice_email_sent_at`, `Idempotency-Key` header on order creation (NEW), scanner check-ins with client UUIDs.
4. **Time**: store UTC, compute business dates with `CarbonImmutable::now('Asia/Qatar')` through a `QatarClock` service (fakeable in tests). Never use the server's local date.
5. **Money**: `decimal(10,2)` in DB, `Money` value object in integer dirhams in PHP; Sadad amounts formatted `number_format(x, 2, '.', '')`; comparison tolerance ±0.005 (R-111).
6. **Security baseline**: deny-by-default Policies on every model and Filament resource; Form Requests for every input; rate limiters per endpoint (Part 3.8); CSRF on all web forms except gateway callbacks/webhooks (which are verified otherwise); strict security headers (CSP with nonces, HSTS, X-Frame-Options DENY except Sadad redirect page, Referrer-Policy, Permissions-Policy camera only on scanner); secrets only in `.env`/encrypted settings, write-only inputs in UI; national IDs never sent to non-admin clients (T-128); passwords bcrypt/argon2id; 2FA for admins; session cookies `Secure; HttpOnly; SameSite=Lax`; signed URLs for private files; audit log on every admin mutation.
7. **No browser-side schedulers or integrations**: everything that the original ran in the admin's browser (invoice countdown, n8n posts, manual confirm sequences, QR backfill loops) runs on the server (D-014, D-016, D-017).
8. **Realtime is a convenience, never a source of truth**: every live screen also refreshes on an interval and after reconnect.
9. **i18n**: no hard-coded strings in views/components; Arabic keys complete (D-056/D-057 fixed); numbers shown with Western digits as in the original, dates in Arabic month names, 12-hour time with «ص/م».
10. **Accessibility & quality**: WCAG 2.2 AA contrast, keyboard navigation, focus rings, `prefers-reduced-motion`, Lighthouse ≥ 90 (performance, accessibility, best practices, SEO) on public pages.

### 6.4 Design direction — "desert luxury"
Keep the original identity (Part 2.1 UI: cream `#F5EFE7`, sand gold `#D4B78A`, terracotta `#A85740`, dark coffee `#6B4E3D`, bronze `#8B6F47`, Tajawal/Lusail typography, RTL) and elevate it:
- Design tokens as CSS variables (light + dark), used by both Tailwind (public/staff) and the Filament theme so admin and public feel like one brand.
- Typography scale with a display face for hero/event titles, generous spacing, 8-pt grid, soft layered shadows, subtle grain/sand texture, gold hairline dividers, rounded-2xl cards, glass header on scroll.
- Motion: 150–250 ms ease-out transitions, skeleton loaders, number count-up on KPIs, success micro-animations (fireworks kept for POS), all disabled under reduced motion.
- Components: event cards with image ratio 16:9, sticky booking summary, step indicator in checkout (Tickets → Details → Payment), toast system top-right (6 visible, as original), bottom-sheet dialogs on mobile.
- Staff apps: high-contrast large-touch "operations" theme (dark option), big status colours (green admitted / amber already used / red refused), sound + haptics.
- Admin: Filament with brand colours, Arabic RTL, collapsible grouped sidebar matching the original 5 groups, dashboards with charts (ApexCharts/Chart.js via Filament widgets).

### 6.5 Environments & configuration
`.env` keys (full list with mapping in 2.2 API env table): `APP_*`, `DB_*`, `REDIS_*`, `QUEUE_CONNECTION=redis`, `BROADCAST_CONNECTION=reverb`, `REVERB_*`, `MAIL_*`, `SADAD_MERCHANT_ID`, `SADAD_SECRET_KEY`, `SADAD_API_LOGIN_SECRET`, `SADAD_WEBSITE`, `SADAD_ENV=auto|sandbox|live`, `FRONTEND_URL`, `STAFF_PASSCODE_HASH`, `N8N_WHATSAPP_URL`, `N8N_EMAIL_URL`, `N8N_RESPONSE_SECRET`, `MAIL_WEBHOOK_SECRET`, `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL`, `GEO_IP_PROVIDER`, `CLARITY_PROJECT_ID`, `SENTRY_LARAVEL_DSN`, `BACKUP_*`. Environments: `local`, `staging` (Sadad sandbox), `production` (Sadad live).

### 6.6 Definition of Done (applies to every phase)
A phase is done only when **all** of these are true:
1. Every F-ID listed for the phase is implemented and ticked in `docs/PROGRESS.md` with the file(s) that implement it.
2. Every R-rule touched by the phase is enforced on the server and covered by a test.
3. Every D-defect related to the phase is fixed and has a regression test.
4. Every T-test assigned to the phase exists in Pest and passes.
5. `composer test` (Pest), `composer analyse` (Larastan ≥ 8), `composer lint` (Pint), `npm run build` and the Playwright smoke suite pass in CI.
6. All UI strings exist in `lang/ar` and `lang/en`; RTL layout verified at 360 px, 768 px and 1440 px.
7. No N+1 queries (Laravel `preventLazyLoading()` in non-production), no query > 100 ms on seeded data (10 events, 50 000 holders), pages paginate server-side.
8. Policies cover every new route/resource/action, with tests for guest, member without role, kiosk, cashier, gatekeeper, supervisor, accountant, viewer, admin.
9. `docs/DECISIONS.md` records any interpretation made; `docs/PROGRESS.md` updated; commit messages reference IDs (e.g. `feat(pos): F-POS-001..012`).


---

## Part 7 — Advanced upgrades roadmap (NEW — not in the original)

Parity (Parts 2–5) comes first. The upgrades below make the product "advanced, smart, luxury, modern and professional". Each module in Part 2 ends with its own "Upgrades (new, not in original)" list; this part consolidates them into delivery tiers. Tier A is built inside the parity phases because it is cheap and removes risk; Tiers B–D are Phase 20 (sub-phases 20A–20D).

### 7.1 Tier A — built during parity phases (low cost, high value)
| # | Upgrade | Module refs | Phase |
|---|---|---|---|
| A1 | Server-side schedulers for hold expiry (every minute), Sadad reconciliation (every 5 min), invoice batches (every 10 s tick) | ORD, PAY, NTF | P5, P7, P8 |
| A2 | `Idempotency-Key` on order creation; `order_items` price snapshot; `payment_attempts` log | ORD, PAY | P5, P7 |
| A3 | Personal staff accounts with granular roles (super_admin, admin, supervisor, cashier, gatekeeper, accountant, viewer) + 2FA for admins | AUTH | P3 |
| A4 | Passcode kiosk as a throttled server session (rotate passcode, on/off switch, list/revoke kiosk devices) | AUTH, STF | P3 |
| A5 | "Payments needing review" list + refund tasks (`refunds` table) for `paid_after_expiry_no_stock`, payments on cancelled orders, amount/reference mismatches | PAY, ADM-ORD | P7, P9 |
| A6 | Order timeline drawer (payment events, status changes, messages, scans) | ADM-ORD | P9 |
| A7 | XLSX export of any filtered admin table (orders, transactions, customers, booked tickets, payment errors) | ADM-* | P9–P11 |
| A8 | English locale + language switch (Arabic stays default) | PUB, UI | P6 |
| A9 | Signed (HMAC) QR payload `{code}.{hmac8}` accepted alongside legacy plain codes | QR, GATE | P5, P13 |
| A10 | Per-event configurable limits (per order, per person, minimum online amount, hold minutes, sales/check-in cut-off times) | ORD, ADM-EVT | P4, P5 |
| A11 | Settings "send test" buttons (n8n WhatsApp, n8n e-mail, test e-mail) and settings change history | ADM-SET | P4, P8 |
| A12 | Global admin search (booking reference, phone, name, ticket code) | ADM-SHL | P9 |

### 7.2 Tier B — operations excellence (Phase 20A)
| # | Upgrade | Value |
|---|---|---|
| B1 | **Offline-first PWA scanner**: service worker + IndexedDB cache of today's admissible ticket hashes per gate event, local duplicate detection, outbound sync queue (`scan_queue`) with device id + client time, server-side idempotent replay and conflict report | Gates keep working on bad venue Wi-Fi |
| B2 | Gate assignment per device/session (current gate event), `wrong_event` refusal, optional re-entry mode with counters, keyboard-wedge hardware scanner mode, continuous-scan mode, one-tap family check-in | Throughput at peak |
| B3 | POS payment types: `card_terminal` (terminal ref, last 4, auth code; auto-link to OCR receipt), `split` (cash + card), Sadad payment link/QR for the customer's phone | Real venue payments |
| B4 | 80 mm thermal receipt + per-holder ticket printing (PDF/ESC-POS), "reprint last sale", send tickets via WhatsApp/e-mail from POS | Faster queues |
| B5 | Cashier shifts & cash-drawer reconciliation (expected vs counted), sales per cashier per shift, POS refunds/voids with supervisor approval (tickets invalid at gate immediately) | Cash control |
| B6 | Receipt reconciliation dashboard (receipts vs card sales vs cash per day/cashier), bulk OCR queue, re-run OCR, manual entry fallback, daily PDF | Finance accuracy |
| B7 | Gate board per gate/per staff counts, TV kiosk mode for live bookings (auto-rotating sections, large type) | Command centre |
| B8 | Attendance check-in/check-out times, shift hours, monthly Excel matrix | HR |

### 7.3 Tier C — customer delight (Phase 20B)
| # | Upgrade | Value |
|---|---|---|
| C1 | **Apple Wallet / Google Wallet** passes per ticket (same code), updated on event change | Premium experience |
| C2 | "My bookings" (remembered in session) + booking lookup page (reference + last 4 phone digits, rate-limited) to re-open invoices on any device | Fewer support calls |
| C3 | Payment hold countdown on the result page; one-click "pay again" while the hold is valid | Higher conversion |
| C4 | Home announcement banner with start/end dates (replaces retired pop-up banners) | Marketing |
| C5 | Rich event pages: gallery, video (`video_url` exists in schema), map/directions, schedule, FAQ, share buttons, Open Graph per event | SEO & sharing |
| C6 | Cookie-consent gate for Clarity; privacy page; terms checkbox at checkout | Compliance |
| C7 | WhatsApp Cloud API driver (official templates) as an alternative to n8n, with delivery receipts | Reliability |
| C8 | English e-mail templates chosen by booking locale; "add to calendar" (.ics) attachment | International visitors |

### 7.4 Tier D — smart & analytics (Phase 20C)
| # | Upgrade | Value |
|---|---|---|
| D1 | Executive dashboard: revenue (gross/fees/net from cached Sadad data), tickets by channel, conversion funnel (visits → checkout → paid → scanned), hourly heatmap of sales and entries | Decisions |
| D2 | Daily settlement report (gross, Sadad fees, net) matching Sadad payouts; per-cashier cash summary | Accounting |
| D3 | Inventory history per ticket type (price/capacity changes, recounts) and stock drift alerts | Control |
| D4 | Customer 360: merge duplicates (same phone/ID), lifetime value, repeat-visitor badge, notes | CRM |
| D5 | AI assistant for admins via MCP (read-only): "sales today?", "find booking of 5551…", "which events are near capacity?"; optional `list_payment_reviews` tool | Speed |
| D6 | AI anomaly alerts: unusual refund/failed-payment spikes, gate duplicate-scan spikes, capacity forecasting from booking velocity | Proactive ops |
| D7 | Public REST API v1 (Sanctum) + OpenAPI for a future mobile app (Capacitor config existed in the original) | Mobile |

### 7.5 Tier E — platform (Phase 20D, optional)
- Multi-organiser / multi-venue support (tenant column on events and settings).
- Promo codes & complimentary (zero-price) invitations with quotas and audit (not in original — only if the business wants it).
- Seat/zone maps for future venues.
- Status page and uptime monitoring; synthetic checks of the booking flow every 5 minutes.


---

## Part 8 — Phased build prompts (copy-paste, in order)

### 8.0 How these prompts work
- Each phase is one prompt in a `text` block. Paste it as-is into your AI coding agent, inside the new Laravel repository, after `docs/SPEC.md` (this file) is committed.
- Prompts reference IDs instead of repeating the specification: the agent must open `docs/SPEC.md` and read every referenced F-, R-, D- and T- item **in full** before writing code (search with `grep -n "F-POS-0" docs/SPEC.md`).
- Phase sizes: S ≈ 1 session, M ≈ 2–3 sessions, L ≈ 4+ sessions. For M/L phases paste the same prompt again with "Continue — read docs/PROGRESS.md" until the phase report says DONE.
- After every phase run **8.22 Phase QA**. Before go-live run **8.23 Coverage audit**. For any bug use **8.24 Bug-fix template**.

| Phase | Title | Size | Main IDs |
|---|---|---|---|
| P0 | Master context (standing rules) | — | all |
| P1 | Foundation & tooling | M | UI, Part 6 |
| P2 | Data model, enums, factories, seeders | L | Part 4, T-005…T-006 |
| P3 | Authentication, roles, passcode kiosk, staff accounts | L | AUTH, STF (access), ADM-STF (accounts) |
| P4 | Admin shell, settings, events & seasons, ticket types | L | ADM-SHL, ADM-SET, ADM-EVT |
| P5 | Booking engine, stock, limits, references, QR | L | ORD, QR (core) |
| P6 | Public website (luxury RTL) | L | PUB, UI |
| P7 | Sadad payments end-to-end | L | PAY |
| P8 | Notifications, e-mail, n8n/WhatsApp, invoice batches | M | NTF |
| P9 | Admin — orders, transactions, payment errors, Sadad tools | L | ADM-ORD, ADM-TRX, ADM-PERR, ADM-SDD |
| P10 | Admin — invoice console, ticket inventory, booked tickets, customers | L | ADM-INV, ADM-TKT, ADM-CUS |
| P11 | Admin — notifications, capacity, reports, logs, visitor analytics | M | ADM-NTF, ADM-CAP, ADM-RPT, ADM-LOG, ADM-VIS |
| P12 | POS, POS users, receipt reader | L | POS, RCP, ADM-STF (POS users) |
| P13 | Gate scanner, check-in service, ticket viewer | L | GATE, QR (viewer) |
| P14 | Staff hub, gate board, attendance, live bookings, live visitors, sounds | L | STF, LIVE, ADM-STF (attendance), SFX |
| P15 | REST API v1, MCP server, OpenAPI | M | API |
| P16 | Data migration from Supabase | M | Part 4.9, T-001…T-005 |
| P17 | Security hardening | M | Part 5.a (all), T-007…T-020, T-128 |
| P18 | Performance, load & resilience | M | R-047, D-098 |
| P19 | Full QA, E2E, accessibility, RTL visual review | M | Part 5.b (all) |
| P20 | Deployment, operations, cut-over, then upgrades 20A–20D | L | Part 6.5, Part 7 |

---

### 8.1 Phase 0 — Master context (paste first, once per new agent session)
```text
You are the lead engineer rebuilding "qcamelmc" — an Arabic-first (RTL) event-ticketing platform for a camel festival in Qatar (currency QAR, timezone Asia/Qatar) — from a React/Supabase app into a new Laravel application. The complete specification is in docs/SPEC.md (English) and docs/SPEC.ar.md (Arabic, same IDs). It was produced by reading every file of the original repository. Treat it as the contract.

GOAL
- 100% feature parity: every F-ID in docs/SPEC.md Part 2 must exist and behave exactly as specified (numbers, limits, labels, statuses, side effects).
- Zero known defects: no D-ID from Part 5.a may be reproduced; each fix gets a regression test.
- All T-IDs from Part 5.b exist as Pest tests and pass.
- A luxury, modern, professional UI ("desert luxury", Part 6.4), Arabic default with English, fast and accessible.

STACK (fixed — do not substitute without writing the reason in docs/DECISIONS.md and asking me)
PHP 8.3+ · Laravel 12.x · MySQL 8 (portable to PostgreSQL) · Redis · Horizon · Reverb + Echo · Scheduler · Filament v4 (admin) · Livewire 3 + Alpine + Tailwind CSS 4 (public & staff apps, PWA for POS/scanner) · Fortify (+2FA) · spatie/laravel-permission · spatie/laravel-settings · spatie/laravel-activitylog · spatie/laravel-medialibrary · spatie/laravel-backup · endroid/qr-code · spatie/laravel-pdf (Browsershot; mPDF fallback) · maatwebsite/excel · prism-php/prism (receipt OCR) · laravel/mcp · Sanctum · dedoc/scramble · Pest 3 · Larastan 8+ · Pint · Rector · Playwright · Sentry · Pulse.

HOW TO READ THE SPEC
- Part 0.4 precedence: defects (5.a) beat features; Part 4 beats Part 2 on names (tickets→ticket_types, confirmed_*→checked_in_* on holders, moderator→supervisor, activity_logs→spatie activity_log, settings singleton→spatie settings groups, secrets→.env); Part 3 beats any other number.
- Parity defaults: when a fix would change business behaviour, add a setting whose default keeps original behaviour (e.g. cash_hold_policy=none, admit_unpaid_cash_bookings=true) and document it.
- NEW items are upgrades; build them only when a phase prompt says so.
- Before coding a phase, grep every ID the phase lists and read the full entry, including its *Rebuild:* lines and the module's "Original defects to avoid".

STANDING RULES
1. Architecture per Part 6.2: thin controllers/Livewire/Filament → Domain Actions/Services/Queries; one DB transaction per state change; domain events dispatched after commit; queued idempotent listeners for side effects.
2. Server is the authority for prices, totals, statuses, limits, stock, references, payment state (never trust client values).
3. Store UTC, compute business dates through a QatarClock service (Asia/Qatar). Money as decimal(10,2) + Money value object; never floats.
4. Deny-by-default Policies for every model, route, Filament resource and action; Form Requests for every input; named rate limiters per Part 3.8; no secrets or national IDs to non-admin clients.
5. Every UI string in lang/ar and lang/en (Arabic is source of truth, verbatim labels from the spec). No hard-coded strings.
6. Every list paginated server-side; no N+1 (preventLazyLoading in non-production); indexes per Part 4.
7. Every realtime screen also refreshes on an interval and after reconnect.
8. Tests first for business rules: write the T-tests and rule tests of a phase before or with the code. Use the Festival fixture (Part 5.b) with the same numbers as the original tests.
9. Keep docs/PROGRESS.md: a table of every F-ID of the current phase with status (todo/doing/done), implementing files and tests. Keep docs/DECISIONS.md for interpretations. Never mark done without a test or a manual verification note.
10. Commit small, conventional commits referencing IDs (e.g. "feat(booking): F-ORD-001..010, R-012..R-025").
11. If something in the spec is ambiguous, choose the option closest to the original behaviour, record it in DECISIONS.md, and continue. Ask me only for decisions that cannot be undone (data deletion, payment behaviour changes, external account setup).
12. Never invent features silently. If you see a better approach, propose it in DECISIONS.md as NEW.

PHASE REPORT FORMAT (end of every phase/session)
- Status: DONE / IN PROGRESS (what remains)
- IDs completed (F/R/D/T), files changed, migrations added
- Test results (counts), static analysis level, lint
- Open questions / decisions taken
- Next step

Acknowledge by summarising the stack, the precedence rules and the phase list (P1–P20) in 15 lines, then wait for Phase 1.
```

---

### 8.2 Phase 1 — Foundation & tooling (M)
```text
PHASE 1 — FOUNDATION. Read docs/SPEC.md Part 6 (all), Part 2.1 module UI (F-UI-001…017), Part 3.1 (R-001…R-011), R-185…R-203, and defects D-083, D-086, D-097, D-099.

BUILD
1. New Laravel 12 project; PHP 8.3+; configure APP_TIMEZONE=UTC, config/qcamelmc.php with timezone 'Asia/Qatar', currency 'QAR', locale 'ar' default + 'en', fallback 'ar'.
2. Install and configure: Horizon (queues payments/notifications/automation/qr/exports/default with priorities), Reverb + Echo, Redis cache/locks/sessions, Filament v4 (panel id "admin", path /admin, RTL, brand colours), Livewire 3, Tailwind CSS 4, Alpine, Fortify, spatie permission/settings/activitylog/medialibrary/backup/health, endroid/qr-code, spatie/laravel-pdf, maatwebsite/excel, prism-php/prism, laravel/mcp, Sanctum, scramble, Sentry, Pulse, Telescope (local only).
3. Quality tooling: Pest 3 (+ arch tests: controllers must not use DB facade directly; Domain must not depend on Http), Larastan level 8, Pint (laravel preset), Rector, composer scripts `test`, `analyse`, `lint`, `fix`; Playwright project with a smoke test; GitHub Actions CI running on MySQL 8 + Redis services (and a PostgreSQL 16 job, allowed to fail until Phase 19) → lint, analyse, test, build assets.
4. Core support classes with unit tests: QatarClock (now, today, dayRange(D) per R-002, isAfterCutoff(event, kind) placeholder), Money (from decimal string, add, multiply, format 'nnn.nn', equalsWithinTolerance ±0.005), PhoneNumber (E.164 normalisation and toWhatsAppNumber per T-082/T-117), ApiError envelope {success:false, code, error, ...} with exception→status mapping (T-094, T-095, T-038).
5. Design system (F-UI-001…017 + Part 6.4): CSS variables for the desert palette (cream #F5EFE7, sand #D4B78A, terracotta #A85740, coffee #6B4E3D, bronze #8B6F47, white cards) in light + dark, Tajawal/Lusail fonts self-hosted, typography scale, spacing, radii, shadows, motion tokens (reduced-motion aware). Shared Blade components: button, card, badge, input, select, phone-input (country code), stepper, dialog/bottom-sheet, toast (top-right, 6 visible), skeleton, empty-state, page header, step indicator. Filament theme using the same tokens.
6. Layouts: public layout (RTL, header with logo/colour/image from settings, footer), staff layout (operations theme, large touch targets, fullscreen helper), error pages 404/403/419/429/500/503 in Arabic & English matching the brand (F-PUB 404 entry).
7. Localisation scaffolding: lang/ar and lang/en files per module; a test/CI command that fails on missing keys between ar and en (fixes D-056/D-057); date helpers for Arabic month names with Latin digits and 12-hour «ص/م» (R-009).
8. Security headers middleware (CSP with nonce, HSTS, frame-ancestors none, Referrer-Policy, Permissions-Policy camera=(self) only on scanner routes), trusted proxies, HTTPS forced in production.
9. docs/PROGRESS.md, docs/DECISIONS.md, docs/RUNBOOK.md, accurate .env.example (all keys from Part 6.5, no values) — fixes D-086.

ACCEPTANCE
- CI green; `php artisan about` shows timezone UTC, locale ar; Horizon dashboard and Reverb start; Filament login page renders RTL with the brand theme; public layout renders at 360/768/1440 px; QatarClock/Money/PhoneNumber tests pass (include T-082, T-117 vectors); missing-translation check runs in CI.
Finish with the phase report.
```

---

### 8.3 Phase 2 — Data model, enums, factories, seeders (L)
```text
PHASE 2 — DATA MODEL. Read docs/SPEC.md Part 4 (4.0–4.8 fully), Part 3.12 (status & enum dictionary), Part 3.2–3.4, and defects D-011, D-024, D-029, D-038, D-046, D-055, D-076, D-078, D-080, D-081, D-089, D-094.

BUILD
1. Migrations for EVERY table in Part 4 (kept, renamed and NEW): users, spatie permission tables, login_attempts, kiosk_devices, events, ticket_types, popup_banners, customers, orders, order_items, ticket_holders, ticket_checkins, scan_queue, deleted_tickets, expired_qr_codes, refunds, pos_users, pos_receipts, payment_events, payment_attempts, sadad_transactions, payment_errors, email_delivery_events, automation_dispatches, activity_log (spatie), settings (spatie), media, staff_attendance, active_visitors, page_views + Laravel infrastructure (sessions, cache, jobs, failed_jobs, job_batches, personal_access_tokens, pulse, notifications).
   - Exact columns, types, nullability, defaults, CHECK constraints (added via DB::statement, ANSI syntax), UUID primary keys (HasUuids) except where Part 4 says otherwise, FKs with the specified onDelete (RESTRICT for financial data), unique and composite indexes, generated columns instead of partial/expression indexes, soft deletes where listed.
   - orders.payment_id UNIQUE (T-006); ticket_types unique (event_id, kind); staff_attendance unique (pos_user_id, attendance_date); holder ticket_code unique.
2. PHP backed enums for every status/kind in 3.12 (OrderStatus, PaymentStatus, PaymentMethod (sadad, cash_pos, + NEW card_terminal/split reserved), OrderSource (web, pos), TicketKind (vip, normal, parking), PaymentNote, SadadEnvironment, AttendanceStatus, EmailDeliveryStatus, PaymentDecision, ActorType…), each with label('ar'/'en') returning the verbatim Arabic labels of the spec and a colour token.
3. Eloquent models with relations, casts (decimal:2, enums, json→array, encrypted where secrets), query scopes for read models from Part 4.8 (e.g. Order::current()/archived(), Event::active()/visible(), TicketType::remaining()), accessors (customers.email '' → null, D-094), and model-level guards against forbidden mass assignment.
4. spatie settings classes (branding, automation, invoices, payments, features, analytics, business) with defaults exactly per the settings keys table in ADM-SET (e.g. auto_invoice_interval_seconds 60, invoice_batch_min 1, invoice_batch_max 10, invoice_send_delay_min 300, invoice_send_delay_max 600, show_delete_ticket_button true, others false, sadad environment 'auto', copyright 'جميع الحقوق محفوظة') + new business settings (cash_hold_policy=none, admit_unpaid_cash_bookings=true, event-day cut-off 18:00, excluded invoice dates, receipt prices 200/300/500). Secrets (Sadad secret, API login secret, passcode hash, n8n secret) are NOT settings — they live in .env/config.
5. Factories for every model and the Festival fixture of Part 5.b (same events, prices, capacities, users, orders as the original tests). Seeders: RolesAndPermissionsSeeder (roles super_admin, admin, supervisor, cashier, gatekeeper, accountant, viewer; permissions per the target roles matrix in AUTH), SettingsSeeder, DemoSeeder (10 events, 3 ticket types each, 2 000 orders, 5 000 holders, 6 POS users) for local/staging only.
6. Broadcast event classes (Part 4.5) as empty shells implementing ShouldBroadcast + ShouldDispatchAfterCommit with channel names; routes/channels.php authorisation stubs with tests.
7. Filesystem disks (Part 4.6): qr (public), branding (public, medialibrary), receipts (private, temporaryUrl 5 min), exports (private, 24 h cleanup), backups.
8. ER diagram (Mermaid) in docs/ERD.md generated from the migrations.

TESTS: T-005, T-006; schema tests asserting every CHECK, unique and FK (insert violating rows → exception); enum label tests (Arabic labels verbatim); migration runs on MySQL 8 and PostgreSQL 16 in CI.
ACCEPTANCE: `php artisan migrate:fresh --seed` works on both databases; Larastan clean; docs/ERD.md committed; PROGRESS lists every table of Part 4 as done.
Finish with the phase report.
```

---

### 8.4 Phase 3 — Authentication, roles, passcode kiosk, staff accounts (L)
```text
PHASE 3 — AUTH & ACCESS. Read docs/SPEC.md module AUTH (F-AUTH-001…031, both roles matrices, upgrades U-AUTH-01…05), STF access entries (passcode, guard, warm-up, 10-day expiry, handoff), ADM-STF staff accounts entries, Part 3.8 (R-137…R-151), defects D-001, D-007, D-008, D-009, D-010, D-013, D-065, D-066, D-096, and tests T-085…T-093, T-110, T-122, T-127.

BUILD
1. Users & roles: spatie roles/permissions seeded per the target matrix; precedence super_admin > admin > supervisor > others (R-137); helper `$user->staffKind()`.
2. Unified login (`/login`, also used by Filament `/admin/login`): tabs «حساب» (email/password) and team passcode, exactly as F-AUTH entries (texts, errors, countdown, attempts-left warning, logo header, back-to-home link, `next` param restricted to safe relative paths). Role-based redirect: admin/super_admin → intended or /admin; supervisor/cashier/gatekeeper → /staff; accountant/viewer → /admin (read-only resources); others → signed out with «غير مصرح: الدخول للأدمن فقط».
3. Fortify: password reset, 2FA TOTP (mandatory for super_admin/admin — U-AUTH-01), recovery codes, password confirmation for sensitive actions.
4. LoginThrottle (R-145): per e-mail AND per IP, 5 failures in 15 min → 30 min lock measured from the latest failure, enforced inside the login pipeline (not advisory), records login_attempts, success ends the streak, attempts pruned after 7 days; LoginAlertMail to admin_email once per identifier per 30-minute block + activity log "login_blocked" (R-146/R-147).
5. Passcode kiosk (R-140…R-144): passcode only from STAFF_PASSCODE_HASH (argon2id) — disabled when unset, shorter than 8 chars at rotation time, or equal to the revoked list (["@@@Qatar123"]); constant-time verify; per-IP 5 wrong/15 min → 30 min block; global 60 wrong/15 min → 15 min; responses {ok}, {ok:false, attempts_left}, {ok:false, warmup:true} for "__warmup__" (not counted), blocked {retry_after}; success creates a server-side kiosk session (10 days, sliding renewal on staff page visits, bound to a kiosk_devices row with device name, IP, UA, last_seen). No passcode stored in the browser, no URL-hash hand-off (D-008). Admin actions: rotate passcode (invalidates all kiosk sessions), enable/disable passcode login, list/revoke kiosk devices (U-AUTH-02).
6. Middleware & policies: `staff` (any staff role or kiosk), `admin-panel` (Filament access per matrix), permission middleware per route; kiosk sessions can never reach /admin or admin APIs (T-122, D-007). Logout clears account and kiosk sessions (D-096).
7. Staff accounts (Filament resource "حسابات الفريق" / page `staff-accounts` with the hero header text of the spec): list all users with role badges, create (name, e-mail, role, temporary password ≥ 8 or invitation e-mail), change role (admin-only roles restricted to super_admin), reset password (≥ 8), revoke (soft delete + session kill), protections: cannot demote/revoke the last admin or yourself (T-127, R-150), every change in activity log. Read-only `login_attempts` page with unblock action (U-AUTH-03), session list with "sign out all devices" (U-AUTH-05).
8. Staff hub skeleton `/staff` with role badge (admin / «موظف» / «عرض فقط» for kiosk) and logout; links are added in later phases.

TESTS: T-085…T-093, T-110, T-122, T-127 + role matrix tests for every role on /admin, /staff, admin APIs; throttle time-travel tests; 2FA enforced for admins.
ACCEPTANCE: all AUTH F-IDs ticked; no client storage of passcode; penetration checklist for login (user enumeration safe messages, lockout cannot be triggered for another user's IP-less e-mail beyond the rule — document the trade-off in DECISIONS.md).
Finish with the phase report.
```

---

### 8.5 Phase 4 — Admin shell, settings, events & seasons, ticket types (L)
```text
PHASE 4 — ADMIN SHELL, SETTINGS, EVENTS. Read modules ADM-SHL (F-ADM-SHL-001…016), ADM-SET (F-ADM-SET-001…042 + the settings keys table), ADM-EVT (F-ADM-EVT-001…025), ADM-TKT edit-ticket entries, rules R-001…R-011, R-185…R-188, R-195…R-200, defects D-002, D-039…D-045, D-075, D-084, D-100, tests T-012, T-013, T-126, T-128.

BUILD
1. Filament panel shell: brand header (logo or «لوحة تحكم المسؤول», header colour from settings), logout, collapsible grouped sidebar with the original 5 groups, every item with its verbatim Arabic label and order (map original routes → Filament resources/pages as in Part 1.5), persisted collapse state per user, active highlight (gradient + side bar per U-ADM-SHL-02), mobile drawer, unread-notifications badge (server-side last-seen per admin, +99 cap, realtime + 30 s poll — data source built in P11, stub now), quick links (POS, scanner, receipts, live bookings, live visitors, website; new tab where the original did), dashboard index → orders. Global search (U-ADM-SHL-01) over orders/customers/ticket codes.
2. Settings page (Filament custom page, tabs mirroring the original sections): every key of the settings table with its control, validation and limits — branding images via medialibrary (hero URL or PNG/JPG ≤ 10 MB, before-footer PNG ≤ 10 MB, header background PNG/JPG ≤ 10 MB with gradient preview, logo URL or PNG/JPG ≤ 5 MB, remove-image actions — D-100), header colour (picker + text, hex/hsl validation), hero text, copyright text, delete-button toggles (customer, generate-QR, event, ticket), WhatsApp n8n URL + switch, e-mail n8n URL + switch (separate forms — D-045), auto-invoice interval (min 10 s), batch min/max, delay min/max, Sadad section (merchant id, website domain, environment auto/sandbox/live; secrets write-only "set / not set" inputs that write to an encrypted store or show the .env key — D-002), site URL, admin phone (+ publish_admin_phone flag — D-084), admin e-mail (validated), payment-failed e-mail toggle, Clarity id (regex ^[a-z0-9]{4,20}$i) + toggle (requires valid id), the static Sadad troubleshooting guide, and NEW business settings (cut-off, cash_hold_policy, admit_unpaid_cash_bookings, excluded invoice dates, receipt prices). Every save writes an activity log with old/new values (secrets masked) — U-ADM-SET-03. "Send test" buttons are added in P8.
3. Public settings read model: only branding + public flags (never secrets, URLs of webhooks, site_url or merchant data — T-013), cached, flushed on save (R-185).
4. Data management tab (server-side, queued, super_admin only, typed confirmation, audited — D-043, D-044): download events Excel template, import events (Arabic or English headers, Excel serial dates and time parsing, booleans, validation report with row errors, optional ticket types), export events, delete all events (refused when orders exist — soft delete only), export ticket types with event title/date, delete all ticket types (guarded), export customers, delete all customers (guarded, soft).
5. Events & seasons (Filament resource + custom list): current vs archived views, season folders by consecutive months (collapsible, count badge), filter by date, event card (image, display order, title, active badge, description, Qatar date range, location, per-type capacity bars sold vs max with thresholds 50/80 % and totals — show held and confirmed, U-ADM-EVT-03), edit form (title, description, Qatar date-time, end_date, location, start/end time, image upload/URL, video URL, display order, active, VIP/normal/parking capacity & price; NEW per-event limits and cut-offs), ticket-type add/update/remove with guard "capacity cannot go below held" (D-038, R-051), archive/restore, active toggle, delete only when the setting allows AND the event has no orders (T-126), duplicate event (U-ADM-EVT-01).
6. Start-new-season dialog (`StartNewEventAction`, one transaction, Qatar zone — D-042): date range, times, location, image, the three ticket types with price/capacity, optional archive of previous events, auto-set current event, activity log "new_event_started".
7. Current event selector (customer-facing event or «جميع الفعاليات النشطة») with clear button; writes settings.current_event_id and broadcasts a cache flush.
8. Ticket type edit dialog (price, capacity, description) with validation, used from events and tickets pages.

TESTS: T-012, T-013, T-126, T-128 (settings part); import with Arabic headers and Excel serial dates; capacity guard; start-season rollback on failure; policy tests (accountant/viewer read-only).
ACCEPTANCE: every ADM-SHL, ADM-SET, ADM-EVT F-ID ticked; settings round-trip; no secret ever rendered in HTML or Livewire payload (assert in tests).
Finish with the phase report.
```

---

### 8.6 Phase 5 — Booking engine, stock, limits, references, QR (L)
```text
PHASE 5 — BOOKING ENGINE. Read module ORD (F-ORD-001…045), QR (F-QR-001…024, generation/storage/regeneration parts), Part 3.2–3.5 (R-012…R-098), R-189…R-194, Part 4.3 (orders, order_items, ticket_holders, deleted_tickets, expired_qr_codes), defects D-004, D-018, D-019, D-023, D-031, D-033, D-035, D-036, D-037, D-038, D-046, D-048, D-049, D-055, D-088, D-098, D-105, tests T-021…T-041, T-044, T-045, T-053…T-058, T-061, T-095, T-101…T-105, T-111, T-112, T-117.

BUILD (Domain/Booking + Domain/Tickets)
1. OrderRequestData + validation exactly per R-072…R-098 and F-ORD validation entries: body is an object; customer name 2–100, phone 6–15 digits, ID 3–30, nationality ≤ 60, optional e-mail ≤ 254, country code ^\+\d{1,4}$, no control characters; items ≤ 6 types, quantity integer 1–20 per type, ≤ 30 tickets, unique ticket ids (UUID), exactly one holder per ticket matched by ticket id; payment method sadad|cash_pos (web) or pos-cash (staff); payload ≤ 64 000 chars (413); invalid JSON 400 invalid_json; all errors at once → 400 {code:'validation_failed', errors:[…]} (T-034, T-035, T-037). Client totals/prices/statuses ignored (T-036).
2. CreateOrderAction (one transaction, R-047 retry on deadlock up to 3 times):
   - Lazy expiry of stale holds of the same event first (T-032).
   - Event must be active for web; POS may sell inactive events; web sales refused after the event's sales cut-off (NEW server enforcement, R-004).
   - Lock the event's ticket_types FOR UPDATE in id order; check remaining = capacity − held per type → 409 insufficient_stock:<type> (T-028).
   - Limits: web ≤ 5 admission (vip+normal) and ≤ 5 parking per order (R-012/R-013); per-person ≤ 5 vip+normal per event across all channels counting confirmed + unexpired pending, person key = ID digits (≥ 6) else last 8 phone digits, parking excluded, holders of the same request counted (D-031) → 422 ticket_limit_exceeded with the Arabic message naming the person and count (T-030); max 4 open unpaid orders per phone_e164 (online + cash; POS exempt) → 429 too_many_pending_orders (T-040, D-019); Sadad minimum 3 QAR (T-031) checked BEFORE reserving stock; Sadad not configured → 503 payment_unavailable before reserving (T-039).
   - Prices from DB; total = Σ unit_price × qty; order_items snapshot; order ticket_type label = most expensive type; quantity = holders count (T-021, T-024).
   - Booking reference `QTR|POS-<D>-<M>-<YYYY>-<12 HEX>` using the EVENT date in Qatar, unpadded day/month, unique with up to 5 retries (T-023, R-059…R-063); ticket codes `<ref>-TKTnn` (zero-pad 2, widen beyond 99 — D-088).
   - Status: web sadad → pending, payment_expires_at = now + 30 min (T-022); web cash → pending without expiry (parity; cash_hold_policy setting — D-018); POS → confirmed, paid_at/confirmed_at now, confirmed_by = acting user (null for kiosk), pos_user_id = cashier, automation status message per spec (T-026).
   - Customer row per order (country code default +974, e-mail '' → null accessor — T-033) + person directory key (D-037); holders with country code explicit → parsed from phone → customer → +974 (T-027).
   - return_origin stored only if a safe http(s) origin (T-041).
   - After commit: OrderCreated event; queue RenderTicketQr per holder (D-036); for web cash orders queue the n8n order-created payload (implemented in P8).
3. StockLedger: reserve/release/move/recount with the invariant sold_quantity = holders of pending+confirmed (R-048…R-058) used by every Action: cancel, fail, expire, reinstate (insufficient_stock when sold out), holder delete, order soft-delete (releases once), recycle-bin move/restore (same holder id, qr_image_url kept — D-047), event move (T-053…T-058). Map every insufficient_stock to 409 (D-035).
4. HoldExpiry: `orders:expire-holds` scheduled every minute (onOneServer, withoutOverlapping) + lazy per-event expiry; closes only pending sadad orders past expiry → cancelled with note expired, seats released (T-050, D-105).
5. Read models: AvailabilityQuery (check_availability per event/type with Arabic messages TICKET_NOT_FOUND / INSUFFICIENT_CAPACITY, held counts excluding expired holds — T-061), PersonLimitGuard::count (public pre-check endpoint, rate-limited 20/min/IP), PersonHistoryQuery and LifetimeTotalsQuery (staff only — D-020, D-095).
6. QrService: render PNG 800 px, margin 2, ECC M to disk qr as `<code>.png` with safe-name validation (T-104), round-trip decode test (T-105), no third-party fallback (D-023); actions GenerateQr {text, filename} (admin), BackfillQrImages (100 per run, continues past failures — T-102), RegenerateBookingQr (T-103); optional HMAC payload `{code}.{hmac8}` behind a feature flag (Tier A9).
7. HTTP: POST /api/v1/orders (public web; staff session for source=pos — T-045), GET availability, POST person-count, POST release-expired (public, throttled), with the ApiError envelope; `Idempotency-Key` header support (Tier A2).

TESTS: all T-IDs listed above, plus a concurrency test (two simultaneous orders for the last seat → exactly one succeeds) and a 200-concurrent-checkout smoke (nightly).
ACCEPTANCE: every ORD F-ID and QR generation F-ID ticked; zero client-trusted values; stock invariant verified by `stock:verify` on the demo seed.
Finish with the phase report.
```

---

### 8.7 Phase 6 — Public website (L)
```text
PHASE 6 — PUBLIC WEBSITE. Read module PUB (F-PUB-001…097 in full — every page, field, label, colour, state, storage key), UI (F-UI-001…017 + luxury direction), QR invoice/PDF entries, rules R-009, R-168…R-188, defects D-027, D-057…D-064, D-082, D-092, D-093, tests T-113…T-116, and Part 7 Tier A8 (English) and C3 (hold countdown).

BUILD (Livewire 3, Blade, Tailwind, RTL, Arabic default + English switch)
1. Home `/`: header (logo/colour/image from settings), hero image + gradient overlay + multi-line hero text, events grid (9 per page, overlap hero, cards with accent bar, image, title, description clamp, 12-hour times «وقت البداية/وقت النهاية», location, «احجز التذاكر الآن» or disabled «انتهى وقت الحجز»), pagination «صفحة x من y» with RTL chevrons, before-footer image, footer (copyright), WhatsApp floating button (admin phone if published), current-event rule (only that event when set), visibility = active, not archived, not past cut-off (server-side, cached with explicit flush instead of the 2-minute browser cache — D-092).
2. Ticket selection `/events/{event}/tickets`: every element of F-PUB ticket-selection entries (types VIP/عادي/مواقف with price, remaining, steppers, limits 5 admission + 5 parking, live availability over Reverb public counts channel + polling fallback, sold-out states, totals in «ريال قطري», continue button), robust to any number of types (D-093), Arabic error messages.
3. Checkout `/checkout` with a 3-step indicator: buyer form (name — trim only on submit (D-058), phone with country-code selector (list from spec), ID number, nationality list with flags, optional e-mail), per-ticket holder forms (pre-filled from buyer, preserved when quantities change (D-060), independent country code), per-person 5-ticket pre-check with the Arabic messages, terms acceptance validated server-side (single form — D-059), payment method choice (Sadad online / cash at venue) exactly as the original, order summary sticky card, submit → CreateOrderAction → for Sadad an auto-submitting signed form (T-114) built in P7 (stub with a fake gateway now), for cash → confirmation.
4. Payment result `/payment/result?ref=`: phases and Arabic headlines of the spec, first read; pending → verify immediately then every ≥ 12 s, "delayed" state after 120 s and polling stops (T-113); retry/pay-again button for pending sadad while hold valid with countdown (Tier C3), failure reasons, links to invoice/home; remembered bookings (last 10, de-duplicated, corrupt-safe — T-115) in the session (and localStorage mirror only for convenience).
5. Confirmation and Invoice `/invoice/{ref}`: invoice card (logo, reference, status banner — D-063, event, date/time, buyer, holders with per-ticket QR shown only when admissible, prices, total with 2 decimals, «ر.ق»), download PDF and PNG generated server-side (spatie/laravel-pdf, Arabic fonts; brand from settings — D-064), national ID hidden for non-admins, throttled reference lookup (D-022).
6. 404 and error pages (Arabic/English), chunk-error concept replaced by server rendering; Clarity script only when enabled + consent (Tier C6) and never on staff/admin/invoice pages; server-side visitor tracking middleware (sessions, page views, device/browser/OS/source detection server-side, IP geolocation server-side with caching and IP truncation — D-082, heartbeat 60 s — D-027), excluding /staff, /admin, /invoice.
7. Language switch (ar ⇄ en) with LTR layout for English; SEO: meta tags, Open Graph image (public/og-image), sitemap.xml, robots.txt exactly per F-PUB-003 (+ X-Robots-Tag noindex on invoice/payment/confirmation), JSON-LD Event schema.
8. Performance: Lighthouse ≥ 90 mobile; images responsive (medialibrary conversions WebP/AVIF), lazy below the fold, fonts preloaded.

TESTS: T-113…T-116 (as Livewire/feature tests), Playwright E2E: book 2 normal + 1 parking with fake gateway, cash booking, limit exceeded message, sold-out state, language switch, invoice PDF download.
ACCEPTANCE: every PUB and UI F-ID ticked; screenshots at 360/768/1440 in docs/screenshots/public; no hard-coded strings.
Finish with the phase report.
```

---

### 8.8 Phase 7 — Sadad payments end-to-end (L)
```text
PHASE 7 — SADAD PAYMENTS. Read module PAY (F-PAY-001…060 + the payment state machine table + upgrades), Part 3.6 (R-099…R-127), Part 4.4 (payment_events, payment_attempts, sadad_transactions, payment_errors, refunds), defects D-012, D-022, D-033, D-090, D-091, D-105, tests T-039, T-042, T-046…T-052, T-059…T-084, T-114.

BUILD (Domain/Payments behind interface PaymentGateway: createCheckout, handleReturn, handleWebhook, lookup, diagnose)
1. SadadConfig (merchant id, website, environment auto/sandbox/live, secrets from .env; isConfigured requires secret + merchant + website — T-083).
2. SadadSigner + SadadCheckoutBuilder for Web Checkout 2.1: fields, product detail lines from order_items, amount 'nnn.nn' (T-076), txnDate Qatar (T-077), mobile normalisation (T-082), fallback e-mail, SHA-256 uppercase signature with sorted keys and secret prefix — must reproduce the reference vector of T-075 byte for byte; POST target https://sadadqa.com/webpurchase; auto-submit form view (T-114).
3. Pay again: re-sign from stored order_items/total for pending sadad orders whose hold is still valid (R-119, D-033); confirmed/failed/cash → 409 not_payable (T-072); log payment_attempts.
4. Callback controller (GET/POST/JSON, CSRF-exempt): parse & normalise (T-079), verify checksum (T-078) and record it, NEVER confirm or fail from the callback alone; run PaymentProcessor(source:'callback') which asks the merchant API; redirect 303 to `<site_url or safe return_origin>/payment/result?ref=` or render the Arabic fallback HTML (T-069).
5. Webhook controller: always 200 {"status":"success"} (T-070), processes via PaymentProcessor(source:'webhook') on the queue; replays never double-notify.
6. SadadApiClient: login {sadadId:number, secretKey, domain} with token cached 10 min, raw token in Authorization, list transactions by website_ref_no, fallback single transaction, live/sandbox/auto hosts, 8 s timeouts, status-id mapping (T-080, T-081), one credential strategy (D-090).
7. PaymentDecisionEngine (pure, unit-tested): confirm only when API success + amount equal within ±0.005 + website_ref_no = this order + txn number present; mismatch → review; fail only on API failed; already confirmed → noop without calling Sadad; expired/failed order may still be confirmed (late payment) — but final staff cancellations are never auto-confirmed: record review + refund task (D-012) (T-062…T-065).
8. PaymentProcessor: lock the order, apply decision through ConfirmPaymentAction / FailPaymentAction (semantics already_confirmed, transaction_already_used, paid_after_expiry_no_stock, confirmed, not_pending — T-046…T-052), write exactly one payment_events row per processing with payload ≤ 4 000 chars (T-066), dispatch after-commit PaymentConfirmed / PaymentFailed events (listeners in P8: invoice mail, admin alert, n8n payment_confirmed exactly once — T-067; payment_errors row with bank/sadad/site classifier (one keyword list — D-091) + payment-failed mail — T-068, T-074).
9. Verify endpoint: public POST {booking_reference} → {success, checked, payment_status}; throttle per order 10 s, only sadad, only pending or payment-failed/expired, only within 3 days; admin bypass (T-059, T-071). Status lookup ≤ 10 references with safe regex, failed mapping (T-060, T-073).
10. Scheduler: `sadad:reconcile-pending` every 5 min (pending > 2 min and failed/expired within 3 days, max 50 per run), `sadad:sync-transactions` every 15 min filling sadad_transactions (status, commission, refund charge, net, mode) for the admin Transactions page.
11. Diagnose action (admin): {ready, environment, callbackUrl, webhookUrl, checks[merchant_id|secret|api_login|website|automation|site_url : ok|warn|error]} with Arabic messages (T-084, D-074); transaction details query (status in Arabic, amount, commission, refund charge, net, mode, entity, date, website ref).
12. Payments-needing-review list data + refunds table workflow (Tier A5) — UI in P9.

TESTS: every T-ID above with recorded Sadad fixtures (Http::fake), plus the full state machine table as a data-driven test (each row: state, trigger, actor → next state, side effects).
ACCEPTANCE: sandbox end-to-end payment on staging succeeds and returns to the result page; no confirmation path exists without the merchant API; every PAY F-ID ticked.
Finish with the phase report.
```

---

### 8.9 Phase 8 — Notifications, e-mail, n8n/WhatsApp, invoice batches (M)
```text
PHASE 8 — NOTIFICATIONS & AUTOMATION. Read module NTF (F-NTF-001…045 incl. every payload and template), API endpoint catalogue rows for e-mail/n8n functions, Part 3.9 (R-152…R-167), defects D-014, D-016, D-017, D-028, D-087, D-091, D-106, tests T-043, T-067, T-068, T-096…T-100, T-106…T-109, T-124.

BUILD
1. Mailables (queued, Arabic RTL, brand from settings, inline-CSS tested in Gmail/Outlook/Apple Mail): BookingInvoiceMail (attempt counting, resend with force, suppression handling, invoice_email_sent_at idempotency), PaymentFailedMail (customer when setting enabled + admin copy deduped), AdminSaleAlertMail (online and POS, once per order unless forced, holders summary), LoginAlertMail. Subjects, every field and layout exactly per the NTF template entries; English variants (NEW, by booking locale).
2. Mail delivery tracking: email_delivery_events (queued, retried, sent, failed, suppressed, skipped, bounced, complained, unsubscribed) written by listeners on MessageSending/MessageSent/failures; provider webhook controller (signed with MAIL_WEBHOOK_SECRET) for bounces/complaints/unsubscribes matched by provider_message_id with dedupe (D-087); suppression list checked before every send; broadcast EmailDeliveryRecorded.
3. Filament e-mail preview page behind settings.manage (D-106) with "send test e-mail" (U-ADM-SET-01).
4. AutomationRelay with driver interface WhatsAppChannel (N8nWebhookDriver default — payloads byte-compatible with the original: order-created, payment_confirmed (server-built from DB), notify/passthrough modes, send_email to the e-mail webhook with fallback to main, send_ticket to the main webhook — T-097, T-098, T-100, T-106, T-107), 10 s timeout, never throws, non-2xx → {ok:false,status}, refuses non-http(s) (T-108), respects enable switches (T-109), writes automation_dispatches (unique key per order+kind+attempt), sets the order's automation status message («جاري إرسال البريد الإلكتروني...» / «جاري الإرسال إلى واتساب...») (T-099). WhatsAppCloudDriver stub (Tier C7).
5. n8n-response endpoint: mandatory secret (header x-webhook-secret or Bearer), unknown reference → 404, message ≤ 500 chars, stores status + message + responded_at (D-028).
6. SendInvoiceBatch job (`invoices:dispatch` tick every 10 s, withoutOverlapping, onOneServer): server-side switch invoice_auto_send_enabled; interval ≥ 10 s since last_invoice_sent_at; batch size random within [min,max]; random delay between sends within [delay_min, delay_max] seconds via delayed queued jobs; picks confirmed sadad orders not yet sent, current season only, skipping events past cut-off and excluded dates; lockForUpdate()->skipLocked(); attempts counter, on hold after 2 failed attempts, reset on accepted delivery (D-016, D-017, T-124).
7. Listeners for PaymentConfirmed / PaymentFailed / OrderCreated(cash web) / PosSaleCompleted wiring mails and n8n exactly once.
8. Settings "send test" buttons for both n8n webhooks.

TESTS: T-IDs above with Http::fake and Mail::fake; concurrency test for the batch job; payload snapshot tests against the original JSON shapes.
ACCEPTANCE: every NTF F-ID ticked; no browser code posts to n8n anywhere.
Finish with the phase report.
```

---

### 8.10 Phase 9 — Admin: orders, transactions, payment errors, Sadad tools (L)
```text
PHASE 9 — ADMIN ORDERS & FINANCE. Read modules ADM-ORD (F-ADM-ORD-001…041), ADM-TRX (F-ADM-TRX-001…018), ADM-PERR (F-ADM-PERR-001…009), ADM-SDD (F-ADM-SDD-001…013), their upgrades, R-168…R-184, defects D-014, D-015, D-032, D-034, D-048, D-050, D-051, D-053, D-067, D-070, D-074, tests T-019, T-053, T-056, T-057.

BUILD (Filament resources/pages; every action = a Domain Action with policy + confirmation + activity log)
1. Orders: status tabs (keep the original «نجح» / «فشل» semantics but add a third «قيد الانتظار» tab so pending is not shown as failed — D-053; counts per tab), stat cards, payment-method filter (الكل / سداد / نقدي-POS with counts), event filter (incl. archived, by date), server-side search by booking reference, customer name or phone (escaped), «القادمة فقط» upcoming-only switch (default on, Qatar today) with count badge, list/grid view toggle persisted per user, pagination 30 «عرض x–y من N حجز», realtime refresh debounced 1.5 s on scoped channels (D-051).
2. Order card/row: status badge (مؤكد / فشل / قيد الانتظار), cashier name or payment method icon, copy reference, «تم النقل» event-shift badge with history dialog, event date, booked datetime (Qatar), paid-after-expiry refund warning, «انتهت مهلة الدفع», payment error reason box, amount, customer name, nationality flag, phone, e-mail, ticket type icons and mixed summary.
3. Actions: «عرض التذاكر» → staff ticket viewer; holders dialog (price per holder from order_items — D-070, QR button, present/absent); enlarged QR dialog (stored image or 800 px ECC H render); «تفاصيل سداد» live transaction dialog; «تحقق من سداد» (admin verify, unthrottled, hidden for final cancellations — D-012); «إلغاء وإعادة للبيع» (release pending + restock, note); «تأكيد الدفع» manual confirm via ManualConfirmAction in one transaction requiring txn number or reason, never touching check-in fields, then QR + notifications via events (D-015); «إلغاء التأكيد» (final admin cancellation); «تغيير إلى نجح» (re-confirm with stock check, insufficient stock message); toggle «تأكيد سداد» manually-verified flag; change event date via ChangeOrderEventAction in one transaction (validate target event, expire old codes in expired_qr_codes, new codes `<ref>-R<k>-TKT<NN>`, move stock or 409, activity log, re-notify customer — D-034, T-056); soft delete (setting-gated by its own flag, typed confirmation — D-029); «Generate Missing QR Codes» (setting-gated, queued, 100 per run); n8n error toasts with hints (404 inactive workflow).
4. Order timeline drawer (Tier A6) and XLSX export of the filtered list (Tier A7); bulk verify (queued); "refunds needed" filter + refund handled flag (Tier A5).
5. Transactions: Sadad vs POS channel switch (Sadad default), current vs archived season, summary cards (confirmed amount, successful count, total count), Qatar day filter + «عرض كل الأيام», status filter, Sadad status filter (server-side using sadad_transactions — D-067), search by reference or Sadad transaction number, table columns per spec incl. Sadad status, commission, net after fees (from cached sadad_transactions with per-row refresh and bulk «جلب حالة سداد للصفحة» queued, 3 concurrent), refresh, pagination 30, details dialog (all order/payment fields, failure reason, live Sadad block with 9 fields, refund badge, explanation, refresh), open invoice, other orders of the same customer (up to 10). NEW: daily settlement summary + XLSX export.
6. Payment errors: server-paginated list (no 500 cap — D-050), current vs archived, source filter with counts (سداد / البنك-البطاقة / الموقع), search (name, phone, reference, payment id), columns per spec, link to order, resolved flag, delete with confirmation (D-032), realtime PaymentErrorLogged.
7. Sadad diagnostic panel (in Settings) and Sadad transaction dialog per ADM-SDD: readiness badge, environment badge, per-check list with Arabic messages, copyable webhook & callback URLs, readable errors (not configured / login rejected).
8. "Payments needing review" page (decision=review, paid_after_expiry_no_stock, transaction_already_used, payments on cancelled orders) with resolve/refund actions.

TESTS: T-019, T-053, T-056, T-057 + action policy tests for each role + Livewire table tests for filters/search/pagination + manual-confirm transaction rollback test.
ACCEPTANCE: every ADM-ORD/TRX/PERR/SDD F-ID ticked; all original actions available with identical labels; no browser-side multi-step mutations.
Finish with the phase report.
```

---

### 8.11 Phase 10 — Admin: invoice console, ticket inventory, booked tickets, customers (L)
```text
PHASE 10 — INVOICES CONSOLE, TICKETS, CUSTOMERS. Read modules ADM-INV (F-ADM-INV-001…024), ADM-TKT (F-ADM-TKT-001…026), ADM-CUS (F-ADM-CUS-001…019), upgrades, R-152…R-167, R-193…R-194, defects D-017, D-021, D-037, D-047, D-048, D-049, D-070, D-075, D-103, D-104, tests T-055, T-124.

BUILD
1. Invoice sending console (Filament custom page): list of confirmed Sadad orders for WhatsApp/e-mail invoices, current vs archived, filter tabs all / pending / sent / on hold / event done with scoped counts (D-017), auto-send status panel driven by the SERVER job (start/stop toggles the server setting invoice_auto_send_enabled; countdown to next tick from last_invoice_sent_at + interval), «إرسال الدفعة» send pending batch now (queued), per-order WhatsApp send, per-order e-mail send, attempt counting with on-hold after 2, «إعادة المحاولة» reset attempts, «إعادة» reset n8n status, «تحديد الكل كمرسل» (scoped, confirmation), event-done rule (after cut-off on event day), status cell states (sent via e-mail / WhatsApp, waiting reply, sending, queued, event done, on hold, not sent), n8n reply column (time + message), nationality flag column, warning banner when the WhatsApp webhook is missing/disabled, realtime refresh, e-mail delivery timeline dialog, configurable excluded dates (was hard-coded 6–8 Nov 2025 — D-075), per-order send log (automation_dispatches).
2. Ticket types & inventory page: grouping by event date tabs and by type (VIP / عادي / مواقف), stock card (price, remaining with colour by sold %, sold, sold % bar, low stock badge ≤ 10, sold-out badge) using ONE stock definition (capacity, held, confirmed, remaining = capacity − held — D-049), payment breakdown per type (paid / pending / failed holders), current vs archived, recount stock action (queued, audited), daily sales statistics table (count, unit price from order_items, amount per type, daily total, grand total — per event, Qatar dates — D-104) with show/hide persisted per user, per-event sales tiles, edit ticket type dialog (from P4).
3. Booked tickets by date (embedded on the tickets page): date filter today / tomorrow / specific day / all (Qatar), payment-state filter (paid / pending / cancelled — D-103) and scan filter all / scanned / not scanned with counts, search (holder name/phone, booking ref, customer name/phone), ticket cards (type, source POS/online, sale time, scan status, scanner name, payment badge), enlarged QR (paid & unscanned only), details dialog (14 fields + payment status + scan note; national ID only for admins — D-021), realtime on check-in (no extra polling), delete to recycle bin (admin + setting) keeping context and qr_image_url (D-047), recycle bin dialog with context, restore (same id, stock + limit re-checked — T-055), purge (super_admin). XLSX export (NEW).
4. Customers: confirmed-order customers grouped into collapsible month/season folders, current vs archived, server search (name, e-mail, phone, booking reference, ticket code), customer card (event header, repeat-buyer badge by person_key — D-037, POS/Online badge, ticket count, main and additional tickets with name, flag, ID (admins), phone, type), send ticket to WhatsApp via AutomationRelay (server), soft delete customer (setting-gated, typed confirmation), details dialog (orders, status badges, QR, amount, quantity, holders), edit customer (name, e-mail, phone, nationality list with flags — edits only the customer record, not every holder — D-037), return ticket (cancel order, restock, confirmation), send invoice to WhatsApp per order, edit holder phone + country code, send single ticket to WhatsApp per holder, un-mark holder attendance (admin, audited, ticket_checkins reset row). NEW: merge duplicates, XLSX export per season folder.
5. CustomerLookup component for POS (phone ≥ 3 chars, 250 ms debounce, max 8, dedupe, previous-event history chips in a single query — D-101, keyboard accessible, staff permission) — used in P12.

TESTS: T-055, T-124 (console side), filters/search tests, recycle-bin round trip, merge safety tests.
ACCEPTANCE: every ADM-INV/TKT/CUS F-ID ticked; nothing scheduled in the browser.
Finish with the phase report.
```

---

### 8.12 Phase 11 — Admin: notifications, capacity, reports, logs, visitor analytics (M)
```text
PHASE 11 — ADMIN INSIGHT. Read modules ADM-NTF (F-ADM-NTF-001…014), ADM-CAP (F-ADM-CAP-001…010), ADM-RPT (F-ADM-RPT-001…011), ADM-LOG (F-ADM-LOG-001…012), ADM-VIS (F-ADM-VIS-001…017), rules R-010, R-176…R-184, defects D-024, D-025, D-026, D-050, D-068, D-069, D-071, D-079, D-083, D-085, tests T-125.

BUILD
1. Notifications page: live incoming bookings split into POS vs online (Sadad) columns with counts and totals, date filter today / yesterday / specific day / all (Qatar), «جديد» badges with per-admin server-side read state + mark-all-read (D-069), realtime insert animation and update patching, details dialog (status, reference, customer, phone, tickets with labels, amount, event, full Qatar timestamp), invoice popup and open-in-new-tab, sidebar badge data (today's orders since last seen, +99 cap, realtime + 30 s poll).
2. Capacity alert widget on every admin page and the POS/live screens (shared component): levels available < 80 %, nearing ≥ 80 %, warning ≥ 90 %, over ≥ 100 % with remaining counts, today's event per R-004/R-005 (multi-day aware — D-041), hidden after the cut-off, server query cached 30 s (D-068); capacity-increase banner + bell sound + toast when admins add capacity (bar duration = auto-hide 10 s — D-079).
3. Reports (queued exports to the exports disk, notification when ready): full orders workbook — sheets «جميع السجلات» (all records incl. orders without event — D-071), one sheet per event day, Sadad summary and per-purchase-date sheets, styling (RTL, header colours, zebra rows, totals per status — status-aware totals), and the Sadad-only report; columns and sheet names exactly per ADM-RPT entries.
4. Activity logs (spatie): tabs all / invoice downloads / POS / search / scan (+ new kinds event_change, ticket_checkin_reset, login_blocked, new_event_started, settings_changed — D-025) with counts, JSON detail expander, delete single (admin) / purge all (super_admin, typed confirmation), realtime ActivityLogged, server-side writes only (D-024), retention setting.
5. Visitor analytics (admin permission visitors.view — D-026): KPIs (total, new, returning, desktop, mobile, tablet), breakdown by country, traffic source, current page, detail table (flag, country, city, truncated IP, device, browser, OS, page, source, new/returning, relative last seen), manual refresh, 30 s auto refresh + realtime, clear all; reports with date range and 7/30/90 presets, totals, area chart over time, top pages and top-20 countries, daily table (total, unique, new, returning), all grouped by Qatar date (T-125, D-083). Visitors vs scans page: ranges today/7/30 days, totals, gate reach rate, daily dual-line chart, refresh. NEW: page-view stats per path/country and conversion funnel (U-ADM-VIS-01/02).

TESTS: T-125; capacity level boundaries (79.9/80/90/100 %); report snapshot test (sheet names, headers, totals); log permission tests.
ACCEPTANCE: every ADM-NTF/CAP/RPT/LOG/VIS F-ID ticked.
Finish with the phase report.
```

---

### 8.13 Phase 12 — POS, POS users, receipt reader (L)
```text
PHASE 12 — POINT OF SALE & RECEIPTS. Read modules POS (F-POS-001…046), RCP (F-RCP-001…023), ADM-STF entries for POS users (cashiers) and the manual check-in dialog, SFX entries used by POS, rules R-012…R-040 (POS parts), R-200, defects D-006, D-031, D-072, D-073, D-077, D-101, tests T-026, T-045, T-111, T-123.

BUILD (Livewire staff app at /staff/pos, PWA-installable, operations theme, fullscreen toggle)
1. POS users (Filament resource «مستخدمي POS»): add (auto emoji icon), rename inline, toggle active, regenerate icon, deactivate/soft delete instead of failing hard delete (D-073); NEW sales count/amount per cashier.
2. POS screen: event selector (active, non-archived, still sellable events, Qatar-formatted dates), cashier selector persisted per device, ticket type cards (live availability, price, quantity stepper, sold-out state, TicketAddItem behaviour), cart with per-unit removable tiles, line subtotals, grand total QAR, limit VIP + normal ≤ 5 per sale, availability checks, capacity alert bar (green < 80 %, yellow ≥ 80 %, orange ≥ 90 %, red over), capacity-increase banner + bell + toast, realtime availability (scoped channels).
3. Buyer form: name, phone with the 30 country codes of the spec, ID number (NEW: ID-card/barcode scanner input), nationality buttons (Gulf + extended list), live phone autocomplete (≥ 4 digits, 350 ms debounce) and CustomerLookup with history chips (tickets per past event) filling the form, remaining personal allowance badge (D-101).
4. Additional holder forms auto-generated from the cart, pre-filled and synced from the buyer, but each holder keeps its own ID (D-031); per-person 5-per-event pre-check with readable violations; stock pre-check before submit.
5. Submit → CreateOrderAction(source=pos, payment cash): server-priced, confirmed, POS- reference, QR jobs, admin sale e-mail; success dialog with fireworks (reduced-motion aware), summary, booking reference and per-holder QR codes, "reprint last sale" (D-072: the button text must match what actually happens — if no customer invoice is sent, the label must not promise it; implement optional "send tickets via WhatsApp/e-mail" checkbox using AutomationRelay); detailed Arabic error messages for stock, limits, validation, auth, network; POS sale activity log (server-side, no PII in client logs).
6. Manual check-in dialog from the POS header (search phone/name/e-mail/booking ref/ID, results with type, presence, event, customer, «ليس اليوم» badge, one-tap mark present via CheckInService manual mode with staff label) — CheckInService is completed in P13; wire it now behind the same service.
7. Receipt reader `/staff/receipts` (permission receipts.manage: cashier, supervisor, accountant, admin): capture with camera or gallery, upload to the PRIVATE receipts disk after a successful OCR attempt (no orphans — D-077), ParseReceiptAction via prism (vision model) extracting amount, SEQ, masked card, time, AUTH with handling of 429/402/422 and an 8M-char image limit, duplicate detection (same SEQ+AUTH+amount), editable normal/VIP/parking counts, delete (removes file), summary table at configurable prices (default 200/300/500 — R-200) with grand total, styled Excel export (Arabic title, zebra rows, coloured summary), responsive list (cards on mobile, table on desktop), realtime PosReceiptSaved/Deleted, created_by stored, Arabic UI, signed URLs 5 min (D-006, T-123).

TESTS: T-026, T-045, T-111, T-123; POS limit and per-holder-ID tests; receipt duplicate test; Playwright: full POS sale on a tablet viewport.
ACCEPTANCE: every POS and RCP F-ID ticked; a cashier can complete a 3-ticket sale in under 30 seconds on a tablet (timed in E2E).
Finish with the phase report.
```

---

### 8.14 Phase 13 — Gate scanner, check-in service, ticket viewer (L)
```text
PHASE 13 — GATE & CHECK-IN. Read module GATE (F-GATE-001…050 incl. ALL server rules and error codes), QR viewer entries (TicketViewer: printable ticket, actions, WhatsApp send errors), SFX scanner sounds, Part 3.7 (R-128…R-136), defects D-005, D-021, D-030, D-040, D-041, D-046, D-054, D-056, D-061, D-102, tests T-118…T-121.

BUILD
1. CheckInService (Domain/Tickets) — modes: admit by ticket code, legacy order-level admit by booking reference, read-only lookup (exact, partial, booking ref, proper joins — D-102), search holders (name / phone / ID / booking ref / customer name/phone/e-mail) for today + future events, manual admit of a searched holder, history (last 30), reset (admin only, activity log persisted — fixes the lost audit row). Rules: event is today in Qatar (multi-day aware — R-005, D-041), not after the per-event check-in cut-off (default 18:00 — R-004, D-040), single admission per ticket enforced atomically (UPDATE … WHERE checked_in_at IS NULL; two concurrent scans → one success, the other «تم استخدام التذكرة مسبقاً» — T-120), payment rule R-129 (confirmed; web cash pending only when admit_unpaid_cash_bookings=true with «تحصيل نقدي» banner; cancelled NEVER — D-005, T-119), expired_qr_codes refused (D-030, T-119), HMAC suffix verified when present, ticket_checkins history row, admitter = authenticated identity + gate staff label, automation marker «تم التسجيل يدوياً ✓» when no automation reply yet, broadcast TicketCheckedIn. Error codes and Arabic messages exactly per GATE entries.
2. Scanner UI `/staff/scanner` (Livewire + JS camera via html5-qrcode or zxing-js; PWA): rear-camera preference, 10 fps, 250×250 box, permission/in-use/no-camera messages, auto-stop on read and auto-restart after confirm/duplicate alert, overlapping-scan protection, legacy QR URL normalisation (T-118), manual search box (booking reference, ticket code -TKT, phone; uppercase input), phone search listing all PAID admissible tickets for that number with selection, booking search listing all tickets with select-all, review popup with full holder details (national ID only for admins — D-021), multi-ticket sequential confirm with summary dialog (counts by type, holder names), sticky confirm bar + floating button with selected count, already-scanned alert with original time and staff + error buzzer + 4 s auto-reset in camera mode, server error dialog «لا يمكن تسجيل الدخول» with buzzer, related tickets for the same phone (same day / other days grouped by Qatar date, collapsible; same booking), gate staff selector from active POS users persisted per device, scan history (30) with refresh, admin-only undo with confirmation, live connection indicator (not a static «متصل» — D-056), all strings translated (D-056), sounds per SFX (success beep NEW, haptics NEW).
3. Ticket viewer `/staff/tickets/{ref}`: per spec (holder list, QR images, statuses, printable ticket in brand colours not violet — D-064, WhatsApp send via relay with readable errors, reset scan for admins).
4. Activity logging of scanner searches and confirmed scans (server-side).

TESTS: T-118…T-121 + one test per error code + a race test with parallel processes.
ACCEPTANCE: every GATE F-ID ticked; 1 000 sequential scans on staging under 200 ms p95 server time.
Finish with the phase report.
```

---

### 8.15 Phase 14 — Staff hub, gate board, attendance, live bookings, live visitors, sounds (L)
```text
PHASE 14 — STAFF OPERATIONS BOARDS. Read modules STF (F-STF-001…027), LIVE (F-LIVE-001…043), ADM-STF attendance entries, SFX (F-SFX-001…017), rules R-011, R-176…R-184, defects D-050, D-051, D-052, D-061, D-065, D-095, tests T-125 (dates).

BUILD
1. Staff hub `/staff`: role badge (admin / «موظف» / kiosk «عرض فقط»), logout, quick links opening in new tabs (POS, scanner, live bookings, receipt reader, team attendance, gate board), admin-only link to the dashboard, sliding renewal of kiosk session on link open; NEW «مبيعاتي اليوم» card (U-ADM-STF-01) for account staff.
2. Team attendance (`/staff/attendance` and the Filament page): Qatar date navigation (prev/next, picker, no future dates — server enforced, D-065), active POS users list, present/absent one-tap toggles with clear on second tap, counters (total, present, absent, unmarked), mark all present, clear day (with confirmation), last-7-days summary table (✓ / ✗ / –), read-only mode for kiosk sessions with the explanatory banner, edits require an account (supervisor/admin; users with several roles resolved by precedence — D-065), 30 s refresh + AttendanceChanged realtime.
3. Gate board `/staff/gate-board`: issued vs entered vs not-yet-entered for the current (or selected) event counting ADMISSIBLE tickets (D-061), attendance % progress bar, per-type scanned/issued tiles, last 25 scans with staff and Qatar time, 15 s silent refresh + manual refresh + realtime, `no_current_event` as a friendly 200 state.
4. Live bookings `/staff/live-bookings`: event-date selector auto-selecting the next event (or all dates), KPIs (bookings, confirmed, tickets, present, last hour), online vs POS split with per-type booked/present, ticket-type breakdown with capacity and present, nationality breakdown (online / POS / present / total with flags), holder cards view and table view toggle, per-holder lifetime total highlighted when > 1 (single person key — D-095), invoice-sent / not-sent / POS user / invoice-downloaded icons (indexed queries — D-052), copy reference, 5-minute recent-booking pulse and new-booking glow, realtime toasts: new POS booking with cash-register sound, new ONLINE booking raised on payment confirmation (D-052) with chime, check-in toast coloured by type with type-specific sound, sound on/off and check-in notifications toggles persisted per user, daily sales statistics dialog (per Qatar date: VIP/normal/parking count, unit price from order_items, amount, daily and grand totals), fullscreen; NEW search box, Excel/PDF export, TV kiosk mode.
5. Live visitors `/admin/live-visitors` (admin): counters (total, new, returning, desktop, mobile, countries), breakdowns (countries, sources, pages, devices, browsers, OS, cities), detail table, realtime + 15 s polling, stale cleanup by scheduler (5 min staleness, 60 s heartbeat).
6. Sound & motion library (SFX): Web Audio tones generated in JS with the exact frequencies/durations of the spec (alert bell, error buzzer, cash register, chime, per-type check-in tones), ONE shared AudioContext unlocked on first interaction (D-052), global mute, fireworks overlay with reduced-motion fallback.

TESTS: attendance rules, gate board counting rules, live-bookings aggregates against fixtures (Qatar date boundaries), Livewire polling/realtime tests.
ACCEPTANCE: every STF, LIVE, SFX and attendance F-ID ticked; boards stay correct after a 2-minute network drop (reconnect refresh).
Finish with the phase report.
```

---

### 8.16 Phase 15 — REST API v1, MCP server, OpenAPI (M)
```text
PHASE 15 — APIS. Read module API (F-API-001…012, endpoint catalogue, env table, upgrades), Part 4.8, tests T-093, T-094, T-096.

BUILD
1. /api/v1 (Sanctum tokens for apps; same Actions/Queries as web): events + availability, create order (Idempotency-Key), order status (≤ 10 refs), invoice, verify payment, staff endpoints (scanner admit/lookup/search/history, gate board, attendance, customer lookup) with permission scopes; uniform error envelope; CORS only for configured origins; rate limiters per Part 3.8.
2. OpenAPI via scramble at /docs/api (admin only), with examples.
3. MCP server (laravel/mcp) read-only tools list_events, event_sales_summary (revenue from orders/order_items — D-070), find_booking, search_customers; OAuth/Sanctum auth with consent screen (replaces /.lovable/oauth/consent), policies applied as the calling user; NEW optional list_payment_reviews.
4. Webhook/callback route catalogue documented in docs/RUNBOOK.md (Sadad callback + webhook URLs, n8n-response, mail webhook) with the values to register in each provider.

TESTS: T-093, T-094, T-096, API contract tests generated from the OpenAPI document, MCP tool tests.
ACCEPTANCE: every API F-ID ticked; Postman/Bruno collection exported to docs/api/.
Finish with the phase report.
```

---

### 8.17 Phase 16 — Data migration from Supabase (M)
```text
PHASE 16 — DATA MIGRATION. Read Part 4.9 (all steps and legacy fixes F-01…F-14), T-001…T-005, D-089, and module PAY/NTF notes about legacy data.

BUILD
1. Command set `migrate:from-supabase {--dry-run} {--step=}` reading a pg_dump (data-only, column inserts) or a read-only `supabase_legacy` connection; writes with Model::withoutEvents, preserves UUIDs, idempotent upserts, logs counts per step, resumable.
2. Users: import auth users + admin_users + user_roles → users + roles (admin→admin, moderator→supervisor — T-110); force password reset + 2FA enrolment for all admins (D-001).
3. Settings → spatie settings groups; secrets NOT copied from the DB — put rotated values in .env (D-002) (T-001 adapted).
4. Legacy fixes: split URL-in-qr_code into code + qr_image_url (T-002), close stale pending Sadad orders as expired (T-003), map legacy references, de-duplicate payment ids before adding UNIQUE (D-089), copy QR images from Supabase Storage to the qr disk keeping names, rewrite URLs 1:1, receipts to the private disk.
5. Recount stock after import (T-004) and run `stock:verify`; reconciliation report (counts per table, totals per event, confirmed revenue) old vs new, saved to docs/migration-report.md.
6. Dry run on a copy of production data on staging; record duration for the cut-over plan.

TESTS: T-001…T-005 with fixture dumps.
ACCEPTANCE: dry run on real data reconciles 100 % (counts and money); report committed.
Finish with the phase report.
```

---

### 8.18 Phase 17 — Security hardening (M)
```text
PHASE 17 — SECURITY. Read Part 5.a fully (D-001…D-106), T-007…T-020, T-128, Part 6.3 rule 6.

BUILD / VERIFY
1. For EVERY D-ID: confirm the fix exists, link the regression test, mark it in docs/SECURITY-CHECKLIST.md (ID, fix location, test). Any D-ID without a test is not done.
2. Authorization sweep: an automated test that iterates every route (web, api, Filament resources/actions, Livewire actions, broadcast channels) and asserts the expected status for guest, member-without-role, kiosk, cashier, gatekeeper, supervisor, accountant, viewer, admin (T-007…T-020, T-122).
3. Data exposure: assert no response/Livewire payload/broadcast contains secrets or national IDs for non-admins (T-128); PII minimisation in logs; IP truncation in analytics.
4. Headers & transport: CSP (nonce, no unsafe-inline scripts), HSTS preload-ready, cookies Secure/HttpOnly/SameSite, CSRF everywhere except verified webhooks, signed URLs for private files.
5. Rate limits on every public endpoint (order create, person count, availability, verify, status lookup, invoice lookup, booking lookup, passcode, login, n8n-response, webhooks) per Part 3.8; abuse tests.
6. Dependency audit (`composer audit`, `npm audit`), secrets scanning in CI (gitleaks), Larastan security rules, OWASP ZAP baseline scan on staging.
7. Rotate all secrets that were ever exposed in the original (Sadad secret/API key, n8n URLs, admin passwords, team passcode) — produce the rotation checklist for the owner (D-001, D-002).

ACCEPTANCE: SECURITY-CHECKLIST 106/106 with tests; ZAP baseline without high findings; authorization sweep green.
Finish with the phase report.
```

---

### 8.19 Phase 18 — Performance, load & resilience (M)
```text
PHASE 18 — PERFORMANCE. Read R-047, D-050, D-051, D-098, Part 4 indexes, Part 6.6 point 7.

BUILD / VERIFY
1. Seed a production-like dataset (30 events, 120 000 holders, 60 000 orders, 500 000 page views).
2. Query review: EXPLAIN every list/report query; add missing composite indexes; no query > 100 ms p95; server-side pagination everywhere; cursor pagination for large tables.
3. Caching: public settings, event listing, availability (short TTL + event-driven flush), capacity alert (30 s), gate board (10 s), dashboards; HTTP caching for static assets; image conversions.
4. Load tests (k6) in CI-nightly: 200 concurrent checkouts on one event (no oversell, p95 < 800 ms), 50 scans/second at the gate (p95 < 200 ms), 500 concurrent public visitors; record results in docs/PERFORMANCE.md.
5. Resilience: Sadad/n8n/mail/geo/AI timeouts and circuit breakers (graceful messages), queue retry/backoff policies, Horizon alerts, Reverb reconnect handling, deadlock retry verified, idempotent listeners verified by re-dispatch tests.
6. Lighthouse ≥ 90 on public pages (mobile) and Core Web Vitals green.

ACCEPTANCE: PERFORMANCE.md with numbers meeting targets; no oversell under load.
Finish with the phase report.
```

---

### 8.20 Phase 19 — Full QA, E2E, accessibility, RTL visual review (M)
```text
PHASE 19 — QA. Read Part 5.b (T-001…T-128), Part 9 (traceability), Part 6.6.

BUILD / VERIFY
1. Ensure every T-ID exists as a Pest test with the ID in its name (e.g. `it('T-075 signs the reference vector')`); produce docs/TEST-MATRIX.md (T-ID → test file → status).
2. Playwright E2E suites (Arabic and English): public booking (Sadad sandbox + fake), cash booking, payment failure and retry, invoice download, POS sale, receipt OCR (mocked), scanner admit/duplicate/refusals, manual check-in, attendance, live boards update, admin order actions (confirm, cancel, change event, delete, restore), invoice console, reports download, settings save, staff accounts, passcode kiosk.
3. Accessibility: axe checks on all pages (0 serious violations), keyboard-only flows for checkout and POS, screen-reader labels in Arabic.
4. RTL/visual review: screenshots of every screen at 360/768/1440 in light/dark, compared against the design tokens; fix misalignment, mirrored icons, number/date formats.
5. Run the PostgreSQL CI job and make it required.
6. Run 8.23 Coverage audit and fix every gap.

ACCEPTANCE: TEST-MATRIX 128/128 green; E2E green; axe clean; coverage audit 862/862.
Finish with the phase report.
```

---

### 8.21 Phase 20 — Deployment, operations, cut-over, then upgrades (L)
```text
PHASE 20 — GO-LIVE & UPGRADES. Read Part 6.1, 6.5, Part 4.7 (scheduler), Part 7.

A. DEPLOYMENT & OPERATIONS
1. Server: Nginx + PHP-FPM 8.3/8.4 (OPcache, JIT off), MySQL 8 tuned, Redis, Supervisor (Horizon, Reverb, scheduler `schedule:work` or cron `* * * * *`), Chromium for PDFs, TLS (Let's Encrypt/Cloudflare), firewall, fail2ban, UTC server clock with NTP.
2. Zero-downtime deploys from GitHub Actions (build assets in CI, `php artisan migrate --force`, `optimize`, `filament:optimize`, `horizon:terminate`, `reverb:restart`), staging first, manual approval for production, rollback procedure.
3. Backups (spatie/laravel-backup, daily DB + private disks to S3, 30-day retention, monthly restore drill), health checks (spatie/laravel-health: DB, Redis, queue backlog, Horizon, Reverb, disk, Sadad config, scheduler heartbeat), Sentry, Pulse, uptime monitor.
4. docs/RUNBOOK.md: incidents (Sadad down, n8n down, mail bounces, queue stuck, gate offline), daily checklist on event days, how to rotate the passcode, how to verify a disputed payment, how to refund.
5. Cut-over plan: freeze the old app (maintenance page), final delta migration (P16), DNS switch, register the new Sadad callback/webhook URLs and n8n-response URL, smoke test a live payment of the minimum amount and refund it, monitor for 48 h, keep the old system read-only for 30 days.

B. UPGRADES (only after go-live is stable; one sub-phase per prompt run)
- 20A Tier B (operations): offline-first PWA scanner + scan_queue sync, gate assignment/re-entry/hardware scanner/continuous mode/family check-in, POS card & split payments + receipt link, thermal printing & reprint, cashier shifts & cash reconciliation, POS refunds/voids with approval, receipt reconciliation, gate board per gate/staff, TV mode, attendance times.
- 20B Tier C (customer delight): Apple/Google Wallet passes, booking lookup page, hold countdown & pay again, announcement banner, rich event pages, consent & privacy pages, WhatsApp Cloud API driver, English e-mails + .ics.
- 20C Tier D (smart): executive dashboard & funnel, settlement report, inventory history & drift alerts, customer 360 & merge, MCP admin assistant tools, AI anomaly alerts & capacity forecasting, public API for mobile.
- 20D Tier E (platform, optional): multi-organiser, promo codes & invitations, seat maps, status page.
For each sub-phase: write the spec of each upgrade into docs/SPEC-UPGRADES.md first (user stories, rules, data, permissions, tests), get my approval, then build with the same Definition of Done.

ACCEPTANCE (A): production live, monitoring green for 48 h, restore drill done, runbook complete.
Finish with the phase report.
```

---

### 8.22 Phase QA prompt (run after every phase)
```text
QA REVIEW OF PHASE <N>. Do not write new features.
1. List every F-, R-, D- and T- ID assigned to Phase <N> in docs/SPEC.md Part 8 and docs/PROGRESS.md.
2. For each F-ID: open its full entry in docs/SPEC.md, then open the implementing code and verify every detail (numbers, labels verbatim, statuses, edge cases, permissions, side effects). Report mismatches as a table (ID, expected, found, file:line).
3. For each R-ID: point to the server-side enforcement and the test. For each D-ID: point to the fix and the regression test. For each T-ID: run it.
4. Run composer test, composer analyse, composer lint, npm run build, Playwright smoke; report results.
5. Check i18n (ar/en complete), RTL at 360/768/1440, policies for every role, N+1 (preventLazyLoading), slow queries.
6. Fix every mismatch you found (small commits referencing IDs), re-run everything, and end with the phase report marking the phase DONE only if zero mismatches remain.
```

### 8.23 Coverage audit prompt (before go-live)
```text
COVERAGE AUDIT. Goal: prove that the Laravel app contains 100% of the original features.
1. Extract every F-ID from docs/SPEC.md Part 2 (expect 862). For each, find the implementing code and at least one test or a documented manual verification; build docs/COVERAGE.md (ID, title, files, tests, status).
2. Walk Part 9 (source file → feature IDs): for each original file, confirm all its IDs are covered.
3. Extract every R-ID (203), D-ID (106) and T-ID (128) and confirm enforcement/fix/test.
4. List gaps; implement them; re-run until COVERAGE.md shows 862/862 F, 203/203 R, 106/106 D, 128/128 T.
5. Produce a short executive summary for the owner (Arabic and English): what was built, what was improved, known limitations, NEW upgrades available.
```

### 8.24 Bug-fix template
```text
BUG FIX. Symptom: <what happened, where, as which role, steps, expected vs actual>.
1. Find the related IDs in docs/SPEC.md (F/R/D/T) and quote the expected behaviour.
2. Write a failing Pest/Playwright test that reproduces the bug (named with the ID).
3. Fix the root cause in the Domain layer (not in the view), keeping all other tests green.
4. Check whether the same pattern exists elsewhere (search the codebase) and fix it too.
5. Update docs/PROGRESS.md / DECISIONS.md and report: cause, fix, tests, files.
```

### 8.25 Change-request template (new feature after go-live)
```text
CHANGE REQUEST: <describe the new feature>.
1. Write it into docs/SPEC-UPGRADES.md as a new U-ID with: user story, screens & labels (ar/en), business rules with numbers, data changes, permissions per role, notifications, edge cases, tests.
2. Show me the spec and wait for approval.
3. Build it with the Definition of Done (Part 6.6), feature flag first, then enable.
```


---

## Part 9 — Traceability matrix (original source file → specification IDs)

Every file of the original repository (except `.git`, `node_modules` and lock files) is listed below with what it is and the specification IDs that cover it — feature IDs `F-<MODULE>-NNN` (Part 2), rules `R-` (Part 3), data-model sections (Part 4), defects `D-` and test-pinned rules `T-` (Part 5); a file with no ID is marked explicitly as config, tooling or dead code.

Notation: `F-POS-001…046` and `F-PUB-008–010` = every ID in the range; `;` separates modules/kinds; `D-` = defect the rebuild fixes, not behaviour to copy. Paths in 9.6, 9.8–9.9 and 9.12–9.14 are relative to the folder named in the section heading.

### 9.1 Static assets (`public/`, `src/assets/`)

| Path | What it is | Covered by |
|---|---|---|
| `public/favicon.ico`, `public/favicon.png` | Browser tab icons | F-PUB-002; F-UI-015 |
| `public/og-image.jpg` | Open Graph / Twitter social card image | F-PUB-002; F-UI-015 |
| `public/robots.txt` | Crawler rules (disallow admin/staff/checkout/payment paths) | F-PUB-003; R-202 |
| `public/placeholder.svg` | Vite template placeholder, unused | no feature (dead file) — not ported (Part 6.3) |
| `src/assets/qatar-event-hero.jpg` | Fallback hero image of the home page | F-PUB-009; F-UI-015; F-ADM-SET-005 |
| `src/assets/visa-logo.png`, `mastercard-logo.png`, `applepay-logo.png`, `sadad-logo.png` | Payment trust-row logos in the footer | F-PUB-011; F-UI-015 |
| `src/assets/hand-cursor.png` | Imported but never rendered | no feature (dead file) — dropped (A §10.4) |

### 9.2 Application root (`index.html`, `src/*.tsx|css`)

| Path | What it is | Covered by |
|---|---|---|
| `index.html` | HTML shell: `lang=ar dir=rtl`, title/meta, Tajawal font preload, OG tags | F-PUB-002; F-UI-001, F-UI-012 |
| `src/main.tsx` | Bootstrap + one-time cleanup of popup keys, caches and service workers | F-PUB-006, F-PUB-013 |
| `src/App.tsx` | Router, provider tree, lazy pages, toasts, global trackers | F-PUB-001, F-PUB-004–005; F-AUTH-021–022; F-ADM-SHL-001, F-ADM-SHL-015; Part 1.5 (route map) |
| `src/index.css` | Design tokens (light/dark), gradients, shadows, animations, RTL utilities | F-UI-001…013; F-SFX-017 |
| `src/App.css` | Vite template leftovers, not imported | no feature (dead file) — not ported (A §0.6) |
| `src/vite-env.d.ts` | Vite type reference | no feature (config) — Part 6 |

### 9.3 Public & staff pages (`src/pages/`)

| Path | What it is | Covered by |
|---|---|---|
| `src/pages/EventHome.tsx` | Home `/`: header branding, hero, events grid, pagination, booking cut-off | F-PUB-008–010, F-PUB-014…022; R-003, R-185–186; D-092 |
| `src/pages/TicketSelection.tsx` | `/tickets/:eventId`: ticket types, live stock, steppers, total | F-PUB-023…033; F-ORD-033; R-012–014, R-053; D-051, D-093 |
| `src/pages/Checkout.tsx` | `/checkout`: summary, customer + holders forms, terms, payment choice, submit pipeline, Sadad hand-off | F-PUB-034…062; F-PAY-018–019; R-020–023, R-085–089, R-183; D-058–060 |
| `src/pages/PaymentResult.tsx` | `/payment/result`, `/sadad-callback`: polling, phase headlines, delayed notice, pay again | F-PUB-063…069; F-PAY-047–048; R-118–119; D-062 |
| `src/pages/Confirmation.tsx` | `/confirmation`: remembered booking, confirmed/pending views, purchase analytics | F-PUB-070…073; F-PUB-097; D-062 |
| `src/pages/InvoicePage.tsx` | `/invoice/:ref`: public invoice with live refresh and states | F-PUB-074…083; F-ORD-038; R-061; D-022, D-063 |
| `src/pages/TicketViewer.tsx` | `/admin/tickets?ref=`: staff ticket viewer, print, WhatsApp, undo check-in | F-QR-013…024; F-NTF-035; D-021, D-064, D-070 |
| `src/pages/NotFound.tsx` | 404 page | F-PUB-085 |
| `src/pages/OAuthConsent.tsx` | OAuth consent page for the AI-assistant (MCP) connector | F-PUB-086; F-API-001 |
| `src/pages/AdminLogin.tsx` | `/admin/login` page around the unified login card | F-AUTH-001, F-AUTH-005–006, F-AUTH-012; R-148; D-001 |
| `src/pages/AdminDashboard.tsx` | Dashboard shell: header, grouped sidebar, drawer, quick links, unread badge, capacity alert | F-ADM-SHL-001…015; F-AUTH-025; D-096 |
| `src/pages/AdminPOS.tsx` | `/admin/pos`: cash point of sale (cart, buyer, holders, pre-checks, success, fullscreen) | F-POS-001…027, F-POS-034…046; F-ADM-CAP-006–010; R-015, R-020–025; D-031, D-072 |
| `src/pages/POSReceiptPage.tsx` | `/admin/pos-receipts`: card-slip capture, list, counts, summary, Excel export | F-RCP-001…005, F-RCP-010…023; R-188, R-200; T-123; D-006, D-077 |
| `src/pages/QRScanner.tsx` | `/admin/qr-scanner`: camera scan, manual search, review, confirm, related tickets, history, undo | F-GATE-001…036; F-SFX-003, F-SFX-006; R-090, R-128–136; D-005, D-054, D-056 |
| `src/pages/LiveBookings.tsx` | `/live-bookings`: live bookings board, KPIs, breakdowns, toasts, sounds, daily sales | F-LIVE-001…031; F-ADM-CAP-006–010; R-178; D-052, D-070 |
| `src/pages/LiveVisitors.tsx` | `/live-visitors`: live presence and visitor analytics tabs | F-LIVE-032…043; F-ADM-VIS-017; R-179–180; D-027 |
| `src/pages/StaffHub.tsx` | `/staff`: staff hub with role badge, quick links, logout | F-STF-008…011 |
| `src/pages/StaffGateBoard.tsx` | `/staff/gate`: gate board counters, per-type tiles, last 25 scans | F-STF-012…017; F-ADM-STF-028; D-061 |
| `src/pages/StaffAttendance.tsx` | `/staff/attendance`: staff-side wrapper of the attendance board | F-STF-018…027; R-139, R-181 |

### 9.4 Admin route pages (`src/pages/admin/`)

| Path | What it is | Covered by |
|---|---|---|
| `src/pages/admin/OrdersPage.tsx` | Route wrapper → `OrdersTab` | F-ADM-SHL-001; F-ADM-ORD-001…041 |
| `src/pages/admin/TransactionsPage.tsx` | Route wrapper → `TransactionsTab` | F-ADM-TRX-001…018 |
| `src/pages/admin/InvoicesPage.tsx` | Route wrapper → `InvoiceTab` | F-ADM-INV-001…024 |
| `src/pages/admin/PaymentErrorsPage.tsx` | Route wrapper → `PaymentErrorsTab` | F-ADM-PERR-001…009 |
| `src/pages/admin/TicketsPage.tsx` | Route wrapper → `TicketsTab` | F-ADM-TKT-001…026 |
| `src/pages/admin/CustomersPage.tsx` | Route wrapper → `CustomersTab` | F-ADM-CUS-001…016 |
| `src/pages/admin/EventsPage.tsx` | Route wrapper → `EventsTab` | F-ADM-EVT-001…025 |
| `src/pages/admin/SettingsPage.tsx` | Route wrapper → `SettingsTab` | F-ADM-SET-001…042 |
| `src/pages/admin/NotificationsPage.tsx` | Route wrapper → `NotificationsTab` | F-ADM-NTF-001…013 |
| `src/pages/admin/ReportsPage.tsx` | Route wrapper → `ReportsTab` | F-ADM-RPT-001…011 |
| `src/pages/admin/ActivityLogsPage.tsx` | Route wrapper → `ActivityLogsTab` | F-ADM-LOG-001…011 |
| `src/pages/admin/VisitorsPage.tsx` | Route wrapper → `VisitorAnalyticsTab` | F-ADM-VIS-001…008 |
| `src/pages/admin/VisitorsVsScansPage.tsx` | Route wrapper → `VisitorsVsScansTab` | F-ADM-VIS-009…012 |
| `src/pages/admin/POSUsersPage.tsx` | POS users page: `StaffRolesCard` + `POSUsersTab` | F-ADM-STF-001, F-ADM-STF-010; F-AUTH-026 |
| `src/pages/admin/StaffAccountsPage.tsx` | Staff accounts page with hero + `StaffRolesCard` | F-ADM-SHL-016; F-ADM-STF-012; F-AUTH-026…030 |
| `src/pages/admin/AttendancePage.tsx` | Route wrapper → `AttendanceTab` | F-ADM-STF-013 |

### 9.5 Shared components (`src/components/`, `src/components/auth/`)

| Path | What it is | Covered by |
|---|---|---|
| `src/components/InvoiceCard.tsx` | Invoice card (header, details, payments, QR grid, enlarge, PDF/PNG buttons) | F-PUB-076…083; F-QR-007; R-190 |
| `src/components/InvoiceDialog.tsx` | Invoice pop-up used by the notifications feed (15 s polling) | F-PUB-084; F-ADM-NTF-012 |
| `src/components/OrderSummary.tsx` | Order summary panel on the payment result page | F-PUB-067 |
| `src/components/Footer.tsx` | Public footer with copyright and payment trust row | F-PUB-011; F-ADM-SET-011 |
| `src/components/WhatsAppButton.tsx` | Floating WhatsApp support button (home) | F-PUB-012 |
| `src/components/FireworksBurst.tsx` | Canvas fireworks after a POS sale (reduced-motion aware) | F-SFX-009…016; F-POS-036; F-UI-010; R-184 |
| `src/components/ClarityTracker.tsx` | Loads Microsoft Clarity on trackable routes | F-PUB-087–088; F-ADM-SET-030–031 |
| `src/components/ChunkLoadErrorBoundary.tsx` | Stale-bundle error boundary with reload | F-PUB-089 |
| `src/components/DevErrorLogger.tsx` | Developer error panel (dev builds only) | F-PUB-092 |
| `src/components/RequireAdmin.tsx` | Route guard (admin-only vs staff, inline login card) | F-AUTH-021–022; F-STF-001; R-137–138; T-122; D-007, D-096 |
| `src/components/auth/UnifiedLoginCard.tsx` | Account + team-passcode login card, lock banner, warm-up, attempts warning | F-AUTH-002…008, F-AUTH-011, F-AUTH-013, F-AUTH-016; F-STF-002, F-STF-004–005; R-141–145; D-001 |

### 9.6 Admin components (`src/components/admin/`)

| Path | What it is | Covered by |
|---|---|---|
| `OrdersTab.tsx` | Orders management: tabs, stats, filters, cards, all order actions | F-ADM-ORD-001…036, F-ADM-ORD-038…041; F-ORD-040; R-057, R-127, R-168; D-014–015, D-053 |
| `EventShiftBadge.tsx` | "Moved" badge + event-change history dialog on order cards | F-ADM-ORD-014; F-ADM-EVT-024–025 |
| `TransactionsTab.tsx` | Transactions ledger (Sadad/POS), filters, live Sadad cells, details dialog | F-ADM-TRX-001…018; R-169; D-067 |
| `InvoiceTab.tsx` | Invoice sending console: batches, countdown, resets, n8n replies, e-mail log | F-ADM-INV-001…023; F-NTF-039…044; R-152–157; D-017 |
| `PaymentErrorsTab.tsx` | Payment errors log viewer (source filter, search, delete, live) | F-ADM-PERR-001…008; R-170; D-032, D-085 |
| `SadadDiagnostic.tsx` | Sadad readiness check card inside Settings | F-ADM-SDD-001…009; F-PAY-052; F-ADM-SET-026; D-074 |
| `SadadTransactionDialog.tsx` | Live Sadad record of one order (modal) | F-ADM-SDD-010…013; F-ADM-ORD-024; F-PAY-058 |
| `TicketsTab.tsx` | Ticket types inventory, payment breakdown, badges, recount, daily sales | F-ADM-TKT-001…010; F-ORD-041; R-050, R-055, R-174; D-038, D-049, D-070, D-104 |
| `EditTicketDialog.tsx` | Edit ticket type dialog with validation | F-ADM-TKT-011–012; R-051, R-093 |
| `TicketAddItem.tsx` | POS ticket tile with sold-out state | F-ADM-TKT-013–014; F-POS-005–007 |
| `BookedTicketsByDate.tsx` | Booked tickets panel: date/scan filters, cards, QR, details, recycle bin | F-ADM-TKT-015…026; F-ORD-042; R-173, R-194; D-047–048, D-103 |
| `CustomersTab.tsx` | Customers directory: season folders, cards, WhatsApp, edit, return ticket, delete | F-ADM-CUS-001…016; F-NTF-034–035; R-195–196; D-014, D-029, D-037 |
| `CustomerLookup.tsx` | POS previous-customer lookup with event-history chips | F-ADM-CUS-017…019; F-POS-017–019; R-182; D-101 |
| `SettingsTab.tsx` | Settings: branding, toggles, integrations (n8n, Sadad, e-mail, Clarity), data management | F-ADM-SET-001…041; R-096, R-187, R-195–198; D-002, D-043–045, D-100 |
| `EventsTab.tsx` | Events & seasons list: folders, cards, capacity bars, archive/restore/delete/active | F-ADM-EVT-001…010, F-ADM-EVT-016…019; R-175, R-197; T-126; D-029 |
| `EditEventDialog.tsx` | Event edit dialog incl. ticket types with sold guard | F-ADM-EVT-011…015; R-052, R-092; D-039 |
| `StartNewEventDialog.tsx` | Start-new-season dialog (archive all + create) | F-ADM-EVT-020–021; D-042 |
| `CurrentEventSelector.tsx` | Customer-facing current-event selector and clear | F-ADM-EVT-022–023 |
| `CapacityAlert.tsx` | Global capacity banner for today's event (levels, 18:00 hide) | F-ADM-CAP-001…005; F-POS-038; R-176; D-068 |
| `CapacityNotificationBanner.tsx` | "New tickets available" banner | F-ADM-CAP-007, F-ADM-CAP-010; F-POS-039; R-177; D-079 |
| `NotificationsTab.tsx` | Notifications feed: POS vs online columns, unread, realtime, details | F-ADM-NTF-001…013; R-172; D-069 |
| `EmailDeliveryTimeline.tsx` | E-mail delivery timeline (shared component) | F-ADM-NTF-014; F-ADM-INV-021; F-NTF-019; R-164 |
| `ReportsTab.tsx` | Styled multi-sheet Excel reports (all / Sadad-only) | F-ADM-RPT-001…011; R-198; D-071 |
| `ActivityLogsTab.tsx` | Activity log viewer: tabs, JSON expander, delete, realtime | F-ADM-LOG-001…011; R-171; D-024–025 |
| `VisitorAnalyticsTab.tsx` | Active visitors dashboard: KPIs, breakdowns, table | F-ADM-VIS-001…008; R-180, R-193; D-026–027 |
| `VisitorsVsScansTab.tsx` | Visitors vs gate scans comparison with daily chart | F-ADM-VIS-009…012; R-010; T-125; D-083 |
| `POSUsersTab.tsx` | POS users (cashiers) CRUD with emoji icons | F-ADM-STF-002…009, F-ADM-STF-011; R-199; D-073 |
| `StaffRolesCard.tsx` | Staff accounts card: list, create, role, reset password, revoke | F-AUTH-026…030; F-ADM-STF-010, F-ADM-STF-012; R-150; D-066 |
| `AttendanceTab.tsx` | Team attendance board (admin and staff views) | F-ADM-STF-013…023; F-STF-018…027; R-139, R-181; D-065 |
| `ManualCheckInDialog.tsx` | Manual check-in dialog opened from POS | F-ADM-STF-024…027; F-POS-042…044; F-GATE-045 |

### 9.7 UI primitives (`src/components/ui/`)

| Path | What it is | Covered by |
|---|---|---|
| `src/components/ui/*.tsx` (49 shadcn/ui files: accordion … tooltip, `use-toast.ts`) | Radix/shadcn primitives (buttons, dialogs, tables, tabs, toasts, sidebar…); no business logic | Replaced by the Blade/Livewire + Filament component library and design tokens: F-UI-001…017; toasts F-PUB-004; Part 6.2, 6.4 |

### 9.8 Hooks (`src/hooks/`)

| Path | What it is | Covered by |
|---|---|---|
| `usePaymentStatus.ts` | Result-page polling + verify contract (0 s, ≥ 12 s, 120 s delayed) | F-PUB-063–065, F-PUB-068; F-PAY-047; R-118 |
| `usePaymentStatus.test.tsx` | Unit tests of the polling contract | T-113 |
| `useReserveTickets.ts` | Read-only availability check before submit (misnamed) | F-PUB-054; F-ORD-032; R-054; D-078 |
| `useLifetimeTicketTotals.ts` | Lifetime confirmed tickets per person key | F-ORD-044; F-LIVE-017; R-070; D-095 |
| `useOrdersQuery.ts` | Orders list query, counts and stats for the Orders tab | F-ADM-ORD-003, F-ADM-ORD-009–010, F-ADM-ORD-037; R-168; D-050 |
| `useCapacityNotification.ts` | Detects capacity increases (POS, live bookings) + bell + toast | F-ADM-CAP-006…010; F-SFX-002, F-SFX-007 |
| `useActivityLog.ts` | Client activity logger (POS form, QR search, ticket scan) | F-ADM-LOG-011–012; F-GATE-011, F-GATE-049; F-POS-034; D-024 |
| `useStaffRole.ts` | Resolves admin / moderator / passcode role | F-AUTH-024; F-STF-008; R-137; D-013 |
| `useVisitorTracking.ts` | Visitor identity, geo/device, presence heartbeat, page views | F-PUB-090–091; F-ADM-VIS-013…016; F-LIVE-033; R-179, R-201; D-082 |
| `use-toast.ts` | shadcn toast store | F-PUB-004 (replaced by Filament/Livewire notifications) |
| `use-mobile.tsx` | `< 768 px` breakpoint hook for the shadcn sidebar | no feature (UI helper) — F-UI-016 |

### 9.9 Libraries (`src/lib/`)

| Path | What it is | Covered by |
|---|---|---|
| `payment.ts` | Sadad host allow-list, hidden POST form builder, remembered bookings (10) | F-PUB-060, F-PUB-096–097; F-PAY-018; R-069; D-092 |
| `payment.test.ts` | Tests of the redirect form and remembered bookings | T-114–115 |
| `api-client.ts` | Typed never-throwing API gateway + Arabic error messages | F-PUB-058–059, F-PUB-061, F-PUB-063; F-ORD-002, F-ORD-022, F-ORD-037, F-ORD-040; F-PAY-020, F-PAY-044, F-PAY-049; F-POS-037; F-ADM-ORD-040; R-082 |
| `api-client.test.ts` | Tests of the API gateway error mapping | T-116 |
| `api.ts` | Singleton `api = createApi(supabase)` | same as `api-client.ts` (F-ORD-002); Part 6.2 |
| `paymentErrors.ts` | Classify (sadad/bank/site) and log client payment errors | F-PUB-062; F-PAY-054–055; F-ADM-PERR-009; R-124; D-024, D-032 |
| `phone.ts` | `toWhatsAppNumber` international digits | F-NTF-023; R-161; T-117 (via phone.test) |
| `phone.test.ts` | Tests of WhatsApp number formatting | T-117 |
| `tickets.ts` | `normalizeScannedCode` (legacy QR URL → code) | F-QR-008; F-GATE-010; R-067 |
| `tickets.test.ts` | Tests of scanned-code normalisation | T-118 |
| `ticketLimit.ts` | 5-per-person pre-check, person key, Arabic limit messages | F-PUB-056; F-POS-025; F-ORD-020, F-ORD-035; R-020–025 |
| `generateInvoicePdf.ts` | Printable A4 invoice HTML → print/save as PDF | F-PUB-081; F-UI-009; D-064 |
| `eventUtils.ts` | Qatar-time 18:00 event cut-off (`isEventExpired`) | F-PUB-016; R-001–004; D-040–041 |
| `sfx.ts` | Web Audio tones (bell, buzzer, chimes) | F-SFX-001…008; F-UI-014 |
| `customerLookup.ts` | Customer search by phone (direct or staff function), de-duplication | F-ADM-CUS-017; F-POS-017, F-POS-019; R-097–098 |
| `personEventHistory.ts` | Previous-event history per person | F-ORD-043; F-ADM-CUS-018; F-POS-018; R-071; D-020 |
| `staffAccess.ts` | Passcode access persistence (10 days, sliding) | F-AUTH-018; F-STF-006; R-141; D-008 |
| `staffHandoff.ts` | Cross-tab / cross-origin access hand-off via URL hash | F-AUTH-020; F-STF-007; D-008 |
| `clarity.ts` | Clarity loader, id validation, excluded paths | F-PUB-087–088; F-ADM-SET-031; R-095, R-201 |
| `utils.ts` | `cn()` class-name merge helper | no feature (UI helper) — Part 6.2 |

### 9.10 MCP client tools (`src/lib/mcp/`)

| Path | What it is | Covered by |
|---|---|---|
| `src/lib/mcp/index.ts` | MCP server definition ("qcamelmc") registering the tools | F-API-001 |
| `src/lib/mcp/supabase.ts` | Supabase client for MCP tools (OAuth user context) | F-API-001; Part 4.8 |
| `src/lib/mcp/tools/list-events.ts` | Tool `list_events` | F-API-002 |
| `src/lib/mcp/tools/event-sales-summary.ts` | Tool `event_sales_summary` | F-API-003; D-070 |
| `src/lib/mcp/tools/find-booking.ts` | Tool `find_booking` | F-API-004 |
| `src/lib/mcp/tools/search-customers.ts` | Tool `search_customers` | F-API-005 |

### 9.11 i18n, contexts, integrations

| Path | What it is | Covered by |
|---|---|---|
| `src/i18n/config.ts` | i18next Arabic-only resources (key groups) | F-PUB-093–095; F-UI-017; R-009; D-056–057 |
| `src/contexts/SettingsContext.tsx` | Global public settings provider (cached 5 min) | F-PUB-007; F-ADM-SET-042; R-185; T-128; D-084, D-092 |
| `src/integrations/supabase/client.ts` | Supabase JS client (anon key, session storage) | no feature (infrastructure) — replaced by Eloquent/Sanctum/Echo, Part 6.1; Part 4.8 |
| `src/integrations/supabase/previewAuthStorage.ts` | Lovable preview auth-storage shim | no feature (Lovable tooling) — not ported (Part 6.3) |
| `src/integrations/supabase/types.ts` | Generated DB types (tables, views, RPCs, enums) | Part 4.1–4.4 tables; Part 3.12 enums |

### 9.12 Edge functions (`supabase/functions/<name>/`)

| Path | What it is | Covered by |
|---|---|---|
| `supabase/config.toml` | Per-function `verify_jwt` gateway settings | F-API-010; Part 4.8; D-009 |
| `create-order/` (`index.ts`, `handler.ts`) | Order creation (web + POS): validate, price, reserve, QR, Sadad request, notify | F-ORD-001…027; F-POS-027…033; F-PAY-008, F-PAY-011; F-QR-005; F-NTF-025; R-016–040; T-021–045; D-018–019, D-036 |
| `sadad-payment/` (`index.ts`, `handler.ts`) | Re-issue a signed Sadad checkout for a pending order | F-PAY-011…020; R-119–120; T-072; D-033 |
| `sadad-callback/` (`index.ts`, `handler.ts`) | Browser return from Sadad (GET/POST) → process → 303 to result page | F-PAY-021…024, F-PAY-027; R-121–122; T-069 |
| `sadad-webhook/` (`index.ts`, `handler.ts`) | Sadad server notification, always 200 | F-PAY-025–026; R-123; T-070 |
| `verify-payment/` (`index.ts`, `handler.ts`) | Public (throttled) / admin payment verification | F-PAY-044…047; F-ADM-ORD-025; R-117; T-059, T-071 |
| `sadad-diagnose/` (`index.ts`, `handler.ts`) | Sadad readiness diagnostics (admin) | F-PAY-029, F-PAY-051; F-ADM-SDD-001…009; T-084, T-096 |
| `sadad-transaction/index.ts` | Live Sadad transaction details (admin) | F-PAY-056…058; F-ADM-SDD-011; F-ADM-TRX-012–013, F-ADM-TRX-016; D-090 |
| `recover-pending-orders/index.ts` | Legacy recovery stub (disabled) | F-PAY-059; Part 4.7; D-105 |
| `auto-send-invoices/index.ts` | Server batch WhatsApp invoice sender | F-NTF-039…043, F-NTF-045; F-ADM-INV-024; R-152–156; T-124; D-016 |
| `send-invoice-email/` (`index.ts`, `deno.json`) | Booking invoice e-mail (guards, attempts) | F-NTF-003…008; R-163, R-166; D-075 |
| `send-payment-failed-email/` (`index.ts`, `deno.json`) | Payment-failed e-mail (customer toggle + admin copy) | F-NTF-009…013; R-126; D-091 |
| `notify-admin-sale/` (`index.ts`, `deno.json`) | Admin new-sale alert e-mail | F-NTF-014…016; F-POS-032 |
| `handle-email-events/` (`index.ts`, `deno.json`) | E-mail provider delivery webhook | F-NTF-019–020; R-164; D-087 |
| `preview-transactional-email/` (`index.ts`, `deno.json`) | E-mail template preview endpoint | F-NTF-021; D-106 |
| `send-to-webhook/` (`index.ts`, `handler.ts`) | Admin n8n relay (notify + passthrough shapes) | F-NTF-028…036; F-ADM-INV-011; R-084, R-157–158; T-096–100 |
| `n8n-response/index.ts` | n8n delivery callback into orders | F-NTF-037–038; F-API-012; R-091, R-159; D-028 |
| `generate-qr-code/` (`index.ts`, `handler.ts`) | Admin arbitrary QR picture tool | F-QR-011; T-096, T-101 |
| `backfill-qr-codes/` (`index.ts`, `handler.ts`) | Backfill missing QR pictures (≤ 100/run) | F-QR-009; F-ADM-ORD-035; R-191; T-102 |
| `regenerate-booking-qr-codes/` (`index.ts`, `handler.ts`) | Regenerate QR pictures of one booking | F-QR-010; T-103 |
| `change-order-event/index.ts` | Move an order to another event date (codes, stock) | F-ADM-ORD-032; F-ORD-031; F-QR-012; R-068; T-056; D-034 |
| `staff-auth/` (`index.ts`, `handler.ts`) | Team passcode verifier with per-caller + global throttle | F-AUTH-009…011, F-AUTH-015; F-STF-003…005; R-140–144; T-085–091 |
| `login-guard/index.ts` | Account login lockout + alert e-mail | F-AUTH-014…017, F-AUTH-019; F-NTF-017–018; R-145–147; T-092; D-010 |
| `manage-staff-users/index.ts` | Staff accounts endpoint (list/create/role/reset/revoke) | F-AUTH-026…031; R-094, R-150; T-127; D-066 |
| `staff-customer-lookup/index.ts` | Staff customer search by phone (POS autocomplete) | F-POS-019; F-ADM-CUS-017; R-097; D-021 |
| `staff-attendance/index.ts` | Attendance list/set/bulk/clear | F-ADM-STF-014; F-STF-019; R-139, R-011; D-065 |
| `gate-stats/index.ts` | Gate board figures for staff | F-STF-012; D-009, D-061 |
| `ticket-checkin/index.ts` | Check-in server: admit, history, lookup, search, manual, reset | F-GATE-037…050; F-QR-019, F-QR-023; F-ADM-STF-027; R-005–006, R-128–136; T-119–121; D-005, D-009, D-030, D-046, D-102 |
| `parse-pos-receipt/index.ts` | AI OCR of card-terminal slips | F-RCP-006…009; R-188 |
| `mcp/index.ts` | Generated MCP Streamable-HTTP handler (OAuth) | F-API-001…005 |

### 9.13 Shared server modules (`supabase/functions/_shared/`)

| Path | What it is | Covered by |
|---|---|---|
| `_shared/http.ts` | CORS, `HttpError`, JSON reader (64 000 chars), error envelope | F-API-006…009; F-ORD-002; R-082–083; T-037–038, T-094 |
| `_shared/auth.ts` | Bearer parsing, passcode rules, constant-time compare, admin/staff guards | F-AUTH-009–010, F-AUTH-023; F-API-010; R-140, R-151; T-085–088, T-093 |
| `_shared/staffAuth.ts` | Deno wrappers: env passcode, admin/moderator lookups, service-role check | F-AUTH-023–024; R-151; D-013 |
| `_shared/internal-call.ts` | Service-to-service function caller (15 s timeout) | F-API-011; R-165 |
| `_shared/throttle.ts` | Pure throttle evaluator (streak, window, block) | F-AUTH-015; F-STF-003; R-142–145; T-089–090 |
| `_shared/settings.ts` | Private settings reader, Sadad configured rule, safe return origin | F-PAY-001…010; F-NTF-022; R-099, R-105, R-122; T-041, T-083, T-109 |
| `_shared/order-errors.ts` | DB error text → HTTP code mapping | F-ORD-022; R-022; T-095; D-035 |
| `_shared/validation.ts` | `parseCreateOrderInput` server validation | F-ORD-003–004; F-POS-028; R-016, R-072–081; T-033–036, T-112 |
| `_shared/sadad-core.ts` | Web Checkout 2.1: signature, amount, mobile, request builder, checksum, normaliser | F-PAY-011…017, F-PAY-021…023; R-008, R-100–107; T-075–079, T-082 |
| `_shared/sadad-api.ts` | Merchant API login, environment detection, transaction lookup | F-PAY-028…032; R-108–110; T-080–081; D-090 |
| `_shared/payment-decision.ts` | `decidePayment` — the only confirm/fail rule | F-PAY-033…035; R-111–113; T-062–065 |
| `_shared/payment-processor.ts` | `processPayment` orchestration + audit | F-PAY-036…040, F-PAY-060; R-114–116, R-192; T-065–066 |
| `_shared/payment-wiring.ts` | Follow-ups on confirm/fail (mail, alert, n8n, payment_errors) | F-PAY-038–039, F-PAY-053; R-124–126; T-067–068 |
| `_shared/repo.ts` | Service-role data access (orders, confirm/fail RPCs, errors) | F-PAY-041…043; F-ORD-037; R-073 (public status); T-073–074, T-110–111 |
| `_shared/notify.ts` | n8n payload builders + safe poster | F-NTF-023…027; R-158–161; T-043, T-106–108 |
| `_shared/qr.ts` | QR PNG rendering + safe object names | F-QR-001, F-QR-003–004; R-064, R-066, R-189; T-104–105 |
| `_shared/qr-tools.ts` | Generate / backfill / regenerate QR tool logic | F-QR-009…011; T-101–103 |

### 9.14 E-mail templates (`_shared/transactional-email-templates/`)

| Path | What it is | Covered by |
|---|---|---|
| `send-email.ts` | `sendTemplateEmail` (provider call, idempotency key) | F-NTF-001; R-163 |
| `registry.ts` | Template registry | F-NTF-001–002; R-162 |
| `booking-invoice.tsx` | Customer booking invoice template | F-NTF-006…008; R-166–167 |
| `payment-failed.tsx` | Payment-failed template | F-NTF-012–013 |
| `admin-sale-alert.tsx` | Admin new-sale alert template | F-NTF-015–016 |
| `login-alert.tsx` | Login-block security alert template | F-NTF-018; F-AUTH-017; R-146 |

### 9.15 Database migrations (`supabase/migrations/`, 116 files)

| Path | What it is | Covered by |
|---|---|---|
| `20251001200356` … `20251003173535` (4 files) | Initial schema: enums, events, tickets, customers, orders, admin_users, `is_admin`, ticket_holders | Part 4.1 (`users`), 4.2 (`events`, `ticket_types`), 4.3 (`customers`, `orders`, `ticket_holders`); Part 3.12; F-ORD-045; R-059; D-003, D-055, D-094, D-097 |
| `20251005162722` … `20251019162251` (32 files) | Settings row, popup_banners, holder QR codes and check-in columns, `qr-codes` bucket, ID numbers, country codes, Sadad keys, RLS churn | Part 4.2 (`popup_banners`), 4.3 (`ticket_holders`), 4.4 (`settings`), 4.6 (disks); F-ADM-SET-001; R-064–066, R-076; D-002–003, D-023 |
| `20251026152120` … `20251108213134` (18 files) | Event order/times, hero/copyright/domain settings, manual-verified flag, n8n reply columns, invoice batching, `expired_qr_codes`, sold-count trigger, `activity_logs` | Part 4.2–4.4 (`events`, `orders`, `expired_qr_codes`, `activity_log`, `settings`); R-127, R-152–154; D-025, D-030, D-097 |
| `20260106105051` … `20260208071946` (21 files) | Image settings, `active_visitors`, `page_views` + analytics RPCs, `pos_receipts` + bucket, `pos_users`, send attempts, `reserve_tickets`, e-mail webhook, blanket GRANT (20260207), indexes | Part 4.3 (`pos_users`, `pos_receipts`), 4.4 (`active_visitors`, `page_views`), 4.6; R-193; D-006, D-011, D-026–027, D-076, D-078 |
| `20260813083452` … `20260827215149` (14 files) | Event end/archive, current event, Hardening 1 (`public_settings`, lookups, counts), 5-ticket cap trigger, `email_delivery_events`, webhook switches, person history, `confirmed_by_name` | Part 4.2, 4.4 (`email_delivery_events`), 4.8; F-ADM-SET-042; R-020–025, R-071, R-197; D-020, D-084 |
| `20260902104641` … `20260921093705` (25 files) | Amount/capacity triggers, legacy booking RPCs, reserve v3, test-data purge, `user_roles`/`app_role`, grants, `payment_errors`, e-mail/Clarity settings, `staff_attendance`, Hardening 2, `deleted_tickets` bin, `login_attempts` | Part 4.1 (`login_attempts`, roles), 4.3 (`deleted_tickets`), 4.4 (`payment_errors`, `staff_attendance`), 4.9 (F-12); R-137, R-147, R-194; D-004, D-013, D-080–081, D-097 |
| `20261009100100_orders_payments_additive.sql` | Payments/stock rewrite: new settings + order columns, `qr_image_url`, `payment_events`, stock engine, `create_order`, confirm/fail/cancel/claim/status/expire RPCs | F-ORD-008…041, F-ORD-045; F-PAY-041…043, F-PAY-045, F-PAY-049–050, F-PAY-060; F-QR-002, F-QR-006; Part 4.3–4.4, 4.7, 4.9 (F-01…F-04); R-007, R-041–063; T-001–006, T-021–032, T-046–061; D-012, D-088–089, D-098 |
| `20261009100200_close_open_access.sql` | Close open doors: drop anon INSERT, revoke legacy RPCs, `get_public_order` hides ID | F-ORD-038; R-061, R-203; T-007–020; D-003–004, D-022 |

### 9.16 Drizzle (Lovable DB tooling)

| Path | What it is | Covered by |
|---|---|---|
| `drizzle.config.ts` | Drizzle config (`LOVABLE_DB_MIGRATION_URL`) | no feature (config) — Part 6 |
| `drizzle/schema.ts` | Intentionally blank schema | no feature (dead file) — Part 6 |
| `drizzle/migrations/0000_orders_payments_additive.sql` | Identical copy of migration 20261009100100 | same as `20261009100100_orders_payments_additive.sql` (see 9.15) |
| `drizzle/migrations/meta/_journal.json`, `meta/0000_snapshot.json` | Drizzle journal and snapshot | no feature (config) — Part 6 |

### 9.17 Database tests (`tests/db/`)

| Path | What it is | Covered by |
|---|---|---|
| `00-supabase-shim.sql` | Emulates Supabase roles, auth, storage for PGlite | no feature (test tooling) — Part 6.6; D-099 |
| `01-helpers.sql` | SQL assertion library and fixtures | no feature (test tooling) — fixture reused in Part 5.b |
| `02-legacy-seed.sql` | Production-shaped legacy rows for the data migration | Part 4.9; T-001–006 (fixture) |
| `05-legacy-data.test.sql` | Data-migration assertions | T-001–006 |
| `10-security.test.sql` | Access-control assertions (guest, member, admin, service) | T-007–020 |
| `20-orders.test.sql` | `create_order` pricing, stock, limits, references | T-021–032 |
| `30-payments-and-stock.test.sql` | Confirm/fail/expire, stock invariant, bin, move, recount, throttle | T-046–061 |

### 9.18 Edge tests (`tests/edge/`)

| Path | What it is | Covered by |
|---|---|---|
| `helpers.ts` | Fakes / request builders for handler tests | no feature (test tooling) — Part 6.6 |
| `create-order.test.ts` | Create-order handler behaviour | T-036–045 |
| `validation.test.ts` | Order input validation | T-033–036, T-112 |
| `payment-decision.test.ts` | `decidePayment` rules | T-062–065 |
| `payment-processor.test.ts` | `processPayment` orchestration | T-065–067 |
| `payment-endpoints.test.ts` | Callback, webhook, verify, restart endpoints | T-069–072 |
| `followups-and-repo.test.ts` | Repository mapping and post-payment follow-ups | T-067–068, T-073–074, T-110–111 |
| `sadad-core.test.ts` | Signature, amounts, txnDate, checksum, normaliser, mobile | T-075–079, T-082 |
| `sadad-api.test.ts` | Merchant API client and environment detection | T-080–081 |
| `staff-access.test.ts` | Passcode rules, staff identity, throttling | T-085–092 |
| `http-auth.test.ts` | HTTP envelope, bearer/admin guards, error mapping | T-093–095 |
| `admin-endpoints.test.ts` | Admin-only functions, relay, notify, QR tools, diagnose | T-084, T-096–103 |
| `notify-qr-settings.test.ts` | Settings helpers, return base, n8n payloads, QR names | T-083, T-104, T-106–109 |
| `qr-roundtrip.test.ts` | QR PNG signature + decode round trip | T-105 |

### 9.19 Documentation, scripts, CI, Lovable plans

| Path | What it is | Covered by |
|---|---|---|
| `README.md` | Project summary + Lovable boilerplate | no feature (docs) — Part 1, Part 6 |
| `docs/DEPLOYMENT.md` | Deployment runbook, tunables, known limitations | Part 3 (R-041, R-012–013, R-026, R-034, R-142); Part 4.9 pre-flight; Part 8.21 |
| `roadmap.md` | Completed task roadmap (9 items) | R-059; F-ADM-NTF-009–010; F-ADM-VIS-009…012; F-STF-012…017; R-139; F-AUTH-014, F-AUTH-026…030 |
| `scripts/db-test.mjs` | Replays all migrations + SQL tests in PGlite | no feature (test tooling) — Part 6.6; D-099 |
| `scripts/deno-check.mjs` | Deno type-check of every edge function | no feature (tooling) — Part 6.3 |
| `scripts/qr-smoke.ts` | QR PNG smoke test | T-105 |
| `.github/workflows/ci.yml` | CI: lint, typecheck, deno, unit, db tests, build | no feature (CI) — Part 6.1, 6.6 |
| `.lovable/mcp/manifest.json` | MCP manifest (path, OAuth) | F-API-001 |
| `.lovable/plan/audit-result-hardening-plan-2026-08-13.md` | Plan: audit + hardening (public reads closed) | D-003, D-007; F-ADM-SET-042; F-ORD-037–038; F-AUTH-022 |
| `.lovable/plan/enforce-a-hard-5-ticket-maximum-per-person-2026-08-14.md` | Plan: 5 tickets per person | F-ORD-020, F-ORD-035; F-PUB-056; F-POS-025, F-POS-031; R-020–025 |
| `.lovable/plan/تتب-ع-أخطاء-الدفع-سداد-في-لوحة-التحكم-2026-09-14.md` | Plan: Sadad payment-error tracking page | F-ADM-PERR-001…009; F-PAY-053…055 |
| `.lovable/plan/فاتورة-قابلة-للتحميل-بعد-الدفع-عبر-سداد-2026-08-13.md` | Plan: downloadable invoice after Sadad payment | F-PUB-070…083 |
| `.lovable/plan/قائمة-جانبية-بقوائم-فرعية-صفحة-حضور-الفريق-2026-09-15.md` | Plan: grouped sidebar + team attendance | F-ADM-SHL-004…010; F-ADM-STF-013…023 |
| `.lovable/plan/لوحة-الفريق-تتب-ع-الزوار-وحماية-تسجيل-الدخول-2026-09-21.md` | Plan: staff board, visitor tracking, login protection | F-STF-012…017; F-ADM-VIS-009…016; F-AUTH-014…019 |

### 9.20 Root configuration

| Path | What it is | Covered by |
|---|---|---|
| `package.json` | Dependencies and npm scripts | no feature (config) — Part 6.1 (stack mapping) |
| `vite.config.ts` | Vite (port 8080, `@` alias, MCP plugin, tagger) | no feature (config) — Part 6.1; F-API-001 |
| `vitest.config.ts` | Unit-test runner config | no feature (config) — Part 6.6 |
| `tailwind.config.ts` | Tailwind theme (colours, fonts, radius, keyframes) | F-UI-001…004, F-UI-011, F-UI-013 |
| `postcss.config.js` | PostCSS (Tailwind, autoprefixer) | no feature (config) — Part 6.1 |
| `components.json` | shadcn/ui generator config | no feature (config) — replaced by F-UI library, Part 6.2 |
| `eslint.config.js` | ESLint rules | no feature (config) — Part 6.3 (Pint/Larastan) |
| `tsconfig.json`, `tsconfig.app.json`, `tsconfig.node.json`, `tsconfig.tests.json` | TypeScript configs (app non-strict, tests strict) | no feature (config) — Part 6.3; D-099 |
| `capacitor.config.ts` | Capacitor mobile wrapper (Lovable preview URL) | no feature (config) — Part 7 (PWA / mobile API) |
| `.env` | Public Supabase URL / anon key / project id | no feature (config) — F-API env table; Part 6.5 |
| `.env.example` | Env template (mentions non-existent `private_settings`) | no feature (config) — Part 6.5; D-086 |
| `.gitignore` | Git ignore rules | no feature (config) |
| `bun.lockb`, `package-lock.json` | Lock files | no feature (config) — excluded |

### 9.21 Coverage summary

| Module | Name | F-IDs | Main source files |
|---|---|---|---|
| PUB | Public website | 97 | `App.tsx`, `main.tsx`, `index.html`, public pages (8), `InvoiceCard`, `InvoiceDialog`, `OrderSummary`, `Footer`, `WhatsAppButton`, `ClarityTracker`, `ChunkLoadErrorBoundary`, `DevErrorLogger`, `SettingsContext`, `i18n/config.ts`, `payment.ts`, `api-client.ts`, `ticketLimit.ts`, `eventUtils.ts`, `clarity.ts`, `usePaymentStatus`, `useReserveTickets`, `useVisitorTracking`, `robots.txt` |
| ORD | Booking & order engine | 45 | `create-order/*`, `_shared/validation.ts`, `_shared/order-errors.ts`, `_shared/repo.ts`, `ticketLimit.ts`, `personEventHistory.ts`, `useLifetimeTicketTotals.ts`, `api-client.ts`, migrations (2026-08, 2026-09, 20261009100100/100200) |
| QR | Ticket & QR codes | 24 | `TicketViewer.tsx`, `tickets.ts`, `_shared/qr.ts`, `_shared/qr-tools.ts`, `generate-qr-code/*`, `backfill-qr-codes/*`, `regenerate-booking-qr-codes/*`, `change-order-event`, `InvoiceCard.tsx`, migrations 2025-10 + 20261009100100 |
| UI | Design system & brand | 17 | `index.css`, `tailwind.config.ts`, `index.html`, `src/components/ui/*`, `src/assets/*`, `generateInvoicePdf.ts`, `FireworksBurst.tsx`, `sfx.ts` |
| PAY | Sadad payments | 60 | `sadad-payment`, `sadad-callback`, `sadad-webhook`, `verify-payment`, `sadad-diagnose`, `sadad-transaction`, `recover-pending-orders`, `_shared/settings|sadad-core|sadad-api|payment-decision|payment-processor|payment-wiring|repo.ts`, `payment.ts`, `paymentErrors.ts`, `usePaymentStatus.ts`, migration 20261009100100 |
| NTF | Notifications & automation | 45 | `send-invoice-email`, `send-payment-failed-email`, `notify-admin-sale`, `handle-email-events`, `preview-transactional-email`, `send-to-webhook`, `n8n-response`, `auto-send-invoices`, `_shared/notify.ts`, `_shared/transactional-email-templates/*`, `InvoiceTab.tsx`, `phone.ts` |
| API | Integrations & APIs (MCP, HTTP) | 12 | `mcp/index.ts`, `src/lib/mcp/**`, `.lovable/mcp/manifest.json`, `OAuthConsent.tsx`, `_shared/http.ts`, `_shared/auth.ts`, `_shared/internal-call.ts`, `supabase/config.toml` |
| ADM-ORD | Orders management | 41 | `OrdersTab.tsx`, `useOrdersQuery.ts`, `EventShiftBadge.tsx`, `OrdersPage.tsx`, `change-order-event`, `verify-payment`, `backfill-qr-codes` |
| ADM-TRX | Transactions | 18 | `TransactionsTab.tsx`, `TransactionsPage.tsx`, `sadad-transaction` |
| ADM-INV | Invoice sending console | 24 | `InvoiceTab.tsx`, `InvoicesPage.tsx`, `EmailDeliveryTimeline.tsx`, `send-to-webhook`, `auto-send-invoices` |
| ADM-PERR | Payment errors log | 9 | `PaymentErrorsTab.tsx`, `PaymentErrorsPage.tsx`, `paymentErrors.ts` |
| ADM-SDD | Sadad diagnostic & transaction dialog | 13 | `SadadDiagnostic.tsx`, `SadadTransactionDialog.tsx`, `sadad-diagnose`, `sadad-transaction` |
| ADM-TKT | Ticket types & booked tickets | 26 | `TicketsTab.tsx`, `EditTicketDialog.tsx`, `TicketAddItem.tsx`, `BookedTicketsByDate.tsx`, `TicketsPage.tsx` |
| ADM-CUS | Customers & lookup | 19 | `CustomersTab.tsx`, `CustomerLookup.tsx`, `CustomersPage.tsx`, `customerLookup.ts`, `personEventHistory.ts`, `staff-customer-lookup` |
| AUTH | Authentication, roles & access | 31 | `AdminLogin.tsx`, `UnifiedLoginCard.tsx`, `RequireAdmin.tsx`, `staffAccess.ts`, `staffHandoff.ts`, `useStaffRole.ts`, `StaffRolesCard.tsx`, `staff-auth`, `login-guard`, `manage-staff-users`, `_shared/auth.ts`, `_shared/staffAuth.ts`, `_shared/throttle.ts`, `login-alert.tsx` |
| ADM-SHL | Admin dashboard shell | 16 | `AdminDashboard.tsx`, `App.tsx`, `StaffAccountsPage.tsx` |
| ADM-SET | Settings | 42 | `SettingsTab.tsx`, `SettingsPage.tsx`, `SadadDiagnostic.tsx`, `SettingsContext.tsx`, `clarity.ts`, settings migrations |
| ADM-EVT | Events & seasons | 25 | `EventsTab.tsx`, `EditEventDialog.tsx`, `StartNewEventDialog.tsx`, `CurrentEventSelector.tsx`, `EventShiftBadge.tsx`, `EventsPage.tsx` |
| ADM-CAP | Capacity alerts | 10 | `CapacityAlert.tsx`, `CapacityNotificationBanner.tsx`, `useCapacityNotification.ts` |
| ADM-NTF | Notifications feed | 14 | `NotificationsTab.tsx`, `NotificationsPage.tsx`, `InvoiceDialog.tsx`, `EmailDeliveryTimeline.tsx` |
| ADM-RPT | Reports | 11 | `ReportsTab.tsx`, `ReportsPage.tsx` |
| ADM-LOG | Activity logs | 12 | `ActivityLogsTab.tsx`, `ActivityLogsPage.tsx`, `useActivityLog.ts` |
| ADM-VIS | Visitor analytics | 17 | `VisitorAnalyticsTab.tsx`, `VisitorsVsScansTab.tsx`, `VisitorsPage.tsx`, `VisitorsVsScansPage.tsx`, `useVisitorTracking.ts`, `LiveVisitors.tsx` |
| ADM-STF | POS users, staff roles & attendance | 28 | `POSUsersTab.tsx`, `StaffRolesCard.tsx`, `AttendanceTab.tsx`, `ManualCheckInDialog.tsx`, `POSUsersPage.tsx`, `AttendancePage.tsx`, `StaffAccountsPage.tsx`, `staff-attendance` |
| POS | Point of sale | 46 | `AdminPOS.tsx`, `TicketAddItem.tsx`, `CustomerLookup.tsx`, `ManualCheckInDialog.tsx`, `FireworksBurst.tsx`, `create-order/*`, `staff-customer-lookup` |
| RCP | Card-receipt reader (OCR) | 23 | `POSReceiptPage.tsx`, `parse-pos-receipt` |
| GATE | Gate scanner & check-in | 50 | `QRScanner.tsx`, `ticket-checkin`, `tickets.ts`, `useActivityLog.ts`, `sfx.ts` |
| LIVE | Live bookings & live visitors | 43 | `LiveBookings.tsx`, `LiveVisitors.tsx`, `useLifetimeTicketTotals.ts`, `useVisitorTracking.ts`, `useCapacityNotification.ts` |
| STF | Staff hub, gate board, attendance | 27 | `StaffHub.tsx`, `StaffGateBoard.tsx`, `StaffAttendance.tsx`, `AttendanceTab.tsx`, `RequireAdmin.tsx`, `UnifiedLoginCard.tsx`, `staffAccess.ts`, `staffHandoff.ts`, `gate-stats`, `staff-attendance`, `staff-auth` |
| SFX | Sound & motion effects | 17 | `sfx.ts`, `FireworksBurst.tsx`, `index.css` |
| **Total** | | **862** | |

Result: 245 rows cover all 424 tracked files (lock files included for completeness; 49 shadcn primitives in one row, 116 migrations in 8 rows, multi-file edge functions one row each). Every one of the 862 feature IDs, every D- and T- ID and every R- ID except R-149 (admin 2FA, NEW — no original source) is referenced at least once. Source files without a feature ID are only configuration, tooling, generated or dead files, each marked above.
