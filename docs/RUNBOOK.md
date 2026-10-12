# Runbook

Operational guide for the Qatar Camel Festival ticketing system. Times are Qatar time (UTC+3) unless stated.

## 1. First deployment

```bash
cp .env.example .env            # fill every [SECRET]; APP_ENV=production, APP_DEBUG=false
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force     # roles/permissions + the owner account from SEED_ADMIN_*
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```

With Docker: `docker compose build && docker compose up -d`, then run the `migrate`/`db:seed` commands through
`docker compose exec app …`. The stack runs **app** (PHP-FPM), **web** (nginx), **horizon** (queues),
**scheduler**, **reverb** (websockets), **mysql** and **redis**.

After the first sign-in: change the owner password, enrol two-factor authentication, then configure
**Settings → Payments (Sadad)** and run **Payment readiness check**.

## 2. Processes that must always run

| Process | Command | Why |
|---|---|---|
| Queue workers | `php artisan horizon` | Invoices, payment follow-ups, QR rendering, n8n, exports. |
| Scheduler | `php artisan schedule:work` (or cron `* * * * * php artisan schedule:run`) | Expires 30-min online holds, reconciles pending Sadad payments, auto-invoices, prunes visitors/logs, backups. |
| Websockets | `php artisan reverb:start` | Live bookings, gate board, capacity alerts (pages also poll as a fallback). |

Health: `GET /up` (Laravel) and the Horizon dashboard at `/horizon` (admins only).

## 3. Season operations

| Task | Where |
|---|---|
| Start a new season / event | Admin → Events → **Start a new event** (creates first, archives the rest only on success). |
| Change capacity or price | Admin → Events → Edit, or Tickets & inventory (never below held seats). |
| Choose what customers see | Admin → Events → **Current event**. |
| Team on the gate | Kiosks sign in with the team passcode; staff accounts sign in with e-mail + password. |
| Attendance | Admin → Team attendance, or `/staff/attendance`. |
| Reports | Admin → Reports → Export Excel (IDs masked without the personal-data permission). |

## 4. Payment incidents (Sadad)

1. **A customer paid but sees "pending"** → Admin → Bookings → open the booking → **Verify with Sadad**. The
   decision rule only confirms on Sadad's own API answer (amount + reference + transaction must match).
2. **"Payment needs review"** (paid after the hold expired with no seats left, or after a staff cancellation):
   Admin → Bookings → *Review* tab. A refund task is created automatically; mark it handled once refunded.
3. **"Checksumhash did not match"** at checkout → Settings → Payments → troubleshooting guide; the registered domain
   must match exactly (`qcamelmc.org` ≠ `www.qcamelmc.org`).
4. **Gateway down** → online checkout answers "payment unavailable" *before* reserving seats; cash at the
   venue keeps working. Nothing to clean up.
5. Reconcile manually: `php artisan sadad:reconcile-pending`. Diagnose: `php artisan sadad:diagnose`.

## 5. Stock and data integrity

- Verify counters: `php artisan stock:verify` (read-only). Fix drift: `php artisan stock:recount` (audited).
- Release expired holds now: `php artisan orders:expire-holds`.
- Regenerate missing QR images: `php artisan tickets:backfill-qr --batch=100`.
- Orders are **soft-deleted**; a deleted active order releases its seats exactly once.

## 6. Security incidents

| Situation | Action |
|---|---|
| Team passcode leaked | Set a new `STAFF_PASSCODE_HASH`, redeploy, then Admin → Team devices → **Revoke all devices**. |
| Staff member leaves | Admin → Team accounts → **Revoke access** (deactivates and ends sessions immediately). |
| Account locked out | Admin → Sign-in attempts → **Unblock** (audited). Lockout: 5 failures / 15 min → 30 min. |
| AI-assistant token exposed | The owner revokes it in Admin → AI assistant (MCP); tokens are shown once and stored hashed. |
| Suspicious activity | Admin → Activity logs (filter by actor / type / date). |

## 7. Backups and restore

- `spatie/laravel-backup` runs nightly from the scheduler (database + `storage/app`). Keep `BACKUP_DISK_DRIVER`
  pointed at off-site storage in production.
- Restore drill: create an empty database, import the dump, copy `storage/app`, run `php artisan migrate --force`
  (no-op on a current schema), then `php artisan stock:verify`.

## 8. Development

```bash
composer install && npm ci && npm run build
cp .env.example .env && php artisan key:generate   # DB_CONNECTION=sqlite works locally
php artisan migrate:fresh --seed                    # demo season, POS users, owner from SEED_ADMIN_*
vendor/bin/pest                                     # 192 tests, in-memory DB, HTTP faked
vendor/bin/pint && vendor/bin/phpstan analyse --memory-limit=2G
python tools/test-matrix.py                         # refresh docs/TEST-MATRIX.md
node tools/brand/render.mjs "$(pwd)"                # re-render favicon set + Open Graph card
```
