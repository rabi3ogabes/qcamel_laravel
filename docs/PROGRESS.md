# Progress

Rebuild of the qcamelmc ticketing app (React + Supabase) as Laravel 12, against `docs/SPEC.md`.
Last updated: 2026-10-12.

## Delivered

| Area | Scope | Status |
|---|---|---|
| Public site | Home (events, hero), ticket selection (live stock), checkout (holders, terms, Sadad / cash), Sadad redirect, payment result (verify loop, delayed, pay again), confirmation, invoice (PDF / PNG), booking lookup, branded error pages, bookings drawer, WhatsApp support, Clarity with consent | ✅ |
| Staff apps | Hub, POS (cashier, customer recall, manual check-in), gate scanner (camera, continuous mode, family tickets, undo), gate board, team attendance, live bookings, receipt reader (OCR), ticket viewer, kiosk passcode sign-in, PWA manifest | ✅ |
| Admin — bookings & finance | Orders (tabs, review, verify, confirm, cancel, move event, refunds, WhatsApp/e-mail), transactions (Sadad / POS, fees), invoice console (auto-send), payment errors | ✅ |
| Admin — tickets & customers | Inventory (guarded edits, recount, daily sales), booked tickets, recycle bin, customers (search incl. QR, relations) | ✅ |
| Admin — events | Season folders, capacity bars (confirmed + held), transactional edit, start new season, archive / restore / delete-gated, current event, capacity alerts | ✅ |
| Admin — team & security | Dashboard widgets, team accounts (roles, invites, revoke), POS users, attendance, sign-in attempts (unblock), kiosk devices | ✅ |
| Admin — insight | Notifications feed (per-admin read state), Excel reports, activity logs, visitor analytics, visitors vs scans, e-mail preview | ✅ |
| Admin — system | Settings (design, messaging, Sadad + diagnose, contact/analytics, business rules, data import/export), AI assistant tokens | ✅ |
| Integrations | Sadad Web Checkout 2.1 + merchant API, n8n (WhatsApp / e-mail), transactional mail, MCP server (5 read-only tools), REST API v1, OpenAPI at `/docs/api` | ✅ |
| Brand | Desert-luxury design system, Qaf monogram favicon set, Open Graph card | ✅ |
| Quality | 205 Pest tests / 660+ assertions (127 of 128 spec test IDs, see TEST-MATRIX), Pint, Larastan level 5 (baseline), CI | ✅ |
| Data migration | `migrate:from-supabase {--dry-run} {--step=} {--storage=}`: users/roles, events, tickets, customers, orders + derived lines, holders, recycle bin, payments, analytics, settings; fixes F-01…F-14; recount; reconciliation report | ✅ |
| Operations | Dockerfile (runtime + web), docker-compose (horizon, scheduler, reverb, mysql, redis), `.env.example`, RUNBOOK, SECURITY-CHECKLIST, ERD, DECISIONS | ✅ |

## Defects found and fixed during testing

- Payment callback returned **500** when no safe return URL existed (missing fallback view) — now a branded page.
- Callback form posts were not read when the raw body was already parsed — reader falls back to parsed fields.
- `SadadConfig` read `env()` directly — after `config:cache` the `.env` fallbacks (incl. the checkout secret) became
  `null` and online payments would refuse. Now read through config, with a regression test.
- Receipt OCR read an undefined `services.ai` config (no API key ever) — config added (`AI_API_KEY`, `AI_BASE_URL`).
- Blocked actions showed a generic "no permission" instead of the specific reason — `DomainError` keeps it.
- Importer: refunds for duplicate payment ids were written before their order (FK), and re-runs created duplicate holder rows for legacy orders without holders — both fixed with tests.
- Attendance and POS-user changes were logged as "role changes" — dedicated log types.

## Pending

| Item | Notes |
|---|---|
| Production data cut-over | Run `migrate:from-supabase --dry-run` against a read-only copy of production, review the report, then run it for real (RUNBOOK §8). |
| Docker image build verification | Dockerfile and compose validated (`docker compose config`); image not built locally because Docker Desktop was not running — CI builds it on every push. |
| Larastan backlog | ~340 baseline entries, mostly missing relation generics; reduce over time. |
| Operational setup | Real Sadad keys, mail provider, n8n workflows, backups off-site, `TRUSTED_PROXIES` (see SECURITY-CHECKLIST). |
