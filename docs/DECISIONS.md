# Decisions

Architecture and product decisions taken during the rebuild, with the reason. Spec references in brackets.

| # | Decision | Why |
|---|---|---|
| 1 | **Laravel 12 monolith**: Filament v4 admin, Livewire 3 public + staff apps, REST API v1, MCP server. | One codebase, one deploy; server-side rules everywhere instead of client trust (the original's RLS gaps). |
| 2 | **Domain layer** (`app/Domain/*` actions, queries, services) used by web, API, Livewire and MCP alike. | A rule exists once (e.g. `CreateOrderAction`, `CheckInService`), so every surface behaves identically and is testable. |
| 3 | **`Actor` value object** (user / kiosk / system / guest) passed into every action. | Permissions and audit attribution without coupling actions to HTTP. |
| 4 | **Stock ledger**: `sold_quantity` = held seats (pending + confirmed), changed only inside row-locked transactions. | Kills the original's two sources of truth and oversells; recount can always rebuild it (T-058). |
| 5 | **Payment decision rule** (`PaymentDecision::decide`): only Sadad's API confirms or fails. | Callbacks/checksums are forgeable; mismatches go to human review rather than silently confirming. [R-111…R-114] |
| 6 | **Qatar time** via `QatarClock`; storage in UTC. | Cut-offs, references, reports and analytics previously mixed UTC, browser and Qatar dates. |
| 7 | **Multi-day seasons**: "today" = Qatar date within `[event_date, end_date]`; cut-off on the last day, configurable. | The original refused day-2 tickets of multi-day seasons. [R-004, R-005] |
| 8 | **Soft deletes for orders**; events with bookings cannot be deleted (archive instead). | Financial records stay auditable; seats are released exactly once. [D-029, T-126] |
| 9 | **Secrets in encrypted settings**, `.env` only as a fallback read through config. | Editable by the owner without redeploys, never displayed, safe under `config:cache`. |
| 10 | **Kiosks use a team passcode → device token**, accounts use e-mail + 2FA. | Shared gate tablets need fast sign-in; devices stay individually revocable. [R-140, R-141] |
| 11 | **Admin = Filament pages + resources** with domain actions behind every button. | Rich, consistent UI (tables, filters, modals) while keeping business rules out of the UI layer. |
| 12 | **Desert-luxury design system**: maroon `#9C1638`, gold `#F9B11F`, cream/sand surfaces, Tajawal, Sadu motif, double-bezel cards, RTL-first. | Brand fidelity and a premium feel across public, staff (dark ops theme) and admin. |
| 13 | **Notifications read-state per admin on the server** (`users.notifications_seen_at`). | Consistent badge across devices (the original used localStorage). [R-172] |
| 14 | **Reports**: multi-sheet Excel, totals split by status, IDs masked without PII permission. | The original mixed pending/cancelled into totals and exposed IDs. |
| 15 | **MCP server is read-only**, Sanctum token with `mcp:read`, tools re-check permissions, no national IDs. | AI assistants get useful answers without becoming a data-exfiltration path. [F-API-001] |
| 16 | **Tests on in-memory SQLite with all outbound HTTP faked**; the T-ID is part of each test title; `TEST-MATRIX.md` is generated. | Fast, isolated, never touches real data or Sadad/n8n; coverage gaps are visible. |
| 17 | **Larastan level 5 with a baseline** for relation-generic annotations. | Catches new defects in CI today (it already found the `env()`/config-cache bug) while the annotation backlog is paid down. |
| 18 | **Brand assets rendered from HTML** (`tools/brand/render.mjs`). | Pixel-perfect Arabic typography; regenerated in seconds after a design change. |
| 19 | **Importer reads a read-only legacy connection, writes with the query builder in one transaction**, ids preserved, deterministic ids for synthesised rows, secrets not copied unless `--with-secrets`. | Idempotent and resumable, `--dry-run` rolls back, no model events/stock side effects, rotated secrets by default (D-002). |
