# Security checklist

Status of the controls the spec requires. ✅ = implemented and covered by an automated test (T-ID) or verified;
⚠️ = implemented, operational follow-up needed.

## Authentication & sessions
- ✅ Unified sign-in (accounts: e-mail + password; kiosks: team passcode) — Fortify, server-side throttling.
- ✅ Account lockout: 5 failures / 15 min per e-mail **and** per IP → 30 min, one alert per block, audited (T-092).
- ✅ Passcode: hash-first (`STAFF_PASSCODE_HASH`), min 8 chars, revoked legacy value refused, constant-time compare,
  per-IP 5/15 min and global 60/15 min throttles, warm-up not recorded (T-085…T-091).
- ✅ Kiosk sessions are opaque HttpOnly device tokens (hash stored), 10-day expiry, revocable one-by-one or all (T-122).
- ✅ Admin two-factor required (`ADMIN_2FA_REQUIRED=true`); enrolment enforced by middleware.
- ✅ Revoking a staff account deactivates it and ends sessions immediately; last admin / self cannot be removed (T-127).
- ✅ Sessions stored in the database (needed for "sign out everywhere"); secure, encrypted cookies in production.

## Authorisation
- ✅ Role/permission matrix (spatie): super_admin, admin, supervisor, cashier, gatekeeper, accountant, viewer.
- ✅ Admin panel only for panel roles; kiosks never reach `/admin` or admin APIs (403) (T-016, T-088, T-122).
- ✅ Every domain action re-checks permissions server-side (`Actor::can`), not only the UI (T-011, T-045).
- ✅ Guests: no customer/order/holder data through any route (T-007…T-010).

## Personal data
- ✅ National ID numbers never leave the server for non-admins: invoices, status lookups, staff screens,
  MCP tools, Livewire payloads (T-015, T-018, T-060, T-128).
- ✅ Reports mask ID numbers without `customers.view_pii`; MCP masks phones/e-mails likewise.
- ✅ Visitor analytics store masked IPs; full IPs visible to the owner only; no third-party geo calls from browsers.

## Secrets
- ✅ Sadad keys and n8n webhook URLs encrypted at rest (spatie settings `encrypted()`), write-only in the UI (T-013).
- ✅ Secrets never rendered in pages, settings payloads or the diagnose report (T-013, T-084).
- ✅ Audit log redacts secret-like keys (`password`, `secret`, `token`, webhook URLs…).
- ✅ No `env()` outside config (Larastan rule) — fallbacks survive `config:cache` (regression test).
- ⚠️ Rotate `APP_KEY` only with `APP_PREVIOUS_KEYS` set, or encrypted settings become unreadable.

## Payments (Sadad)
- ✅ Only Sadad's API can confirm or fail an order; callbacks/webhooks with a valid checksum are triggers, never
  proof (checksum can be forged by re-splitting customer-chosen text) (T-062…T-065).
- ✅ Amount, reference and transaction number must all match; mismatches go to review (T-062, T-063).
- ✅ Transaction numbers are unique across orders (DB unique index) (T-006, T-048).
- ✅ Checkout form posts only to `https://sadadqa.com` hosts; look-alikes refused (T-114).
- ✅ Prices always from the database; client totals/prices/status ignored (T-021, T-036).
- ✅ Webhook always answers 200 and is idempotent; callback never throws (T-069, T-070).

## HTTP hardening
- ✅ CSP with nonces on public/staff pages, `frame-ancestors 'none'`, `X-Frame-Options: DENY`, `nosniff`,
  Referrer-Policy, Permissions-Policy (camera only on scanner/receipts), HSTS on https.
- ✅ CORS only on `api/v1/*` and `/mcp`, without credentials (T-094).
- ✅ Rate limits on every public endpoint (orders, status, verify, invoice, availability, lookups, MCP 60/min).
- ✅ JSON bodies capped (64 000 chars → 413), invalid JSON → 400, uniform error envelope without internals (T-037, T-038).
- ✅ Receipt images on a private disk behind 5-minute signed URLs (T-123).
- ✅ OpenAPI docs (`/docs/api`) admins only in **every** environment.

## Integrity & audit
- ✅ Stock ledger under row locks; seats released exactly once on cancel/delete/expiry (T-049, T-053, T-054, T-126).
- ✅ Concurrent gate scans: exactly one admission (T-120); offline queue idempotent by client id.
- ✅ Append-only activity log for admins; purge by age owner-only and logged.

## Operational follow-ups
- ⚠️ Set `TRUSTED_PROXIES` to the real load-balancer range instead of `*`.
- ⚠️ Point backups to off-site storage and run the restore drill (RUNBOOK §7) before the season.
- ⚠️ Configure the mail provider's delivery webhook secret (`MAIL_WEBHOOK_SECRET`) and the n8n response secret.
