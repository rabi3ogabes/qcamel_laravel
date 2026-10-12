<p align="center"><img src="public/favicon-192.png" width="96" alt=""></p>

<h1 align="center">مهرجان قطر للإبل — Ticketing</h1>

<p align="center">Arabic-first ticketing for the Qatar Camel Festival: online booking with Sadad, point of sale,
gate scanning, live operations and a full admin dashboard. Laravel 12 rebuild of <code>qcamelmc</code>.</p>

---

## Highlights

- **Customers** book in a few taps (RTL, mobile-first), pay with **Sadad** or at the venue, and get an invoice with
  one QR code per ticket (PDF / image / WhatsApp / e-mail).
- **Staff** sell at the **POS**, admit visitors with the **camera scanner** (works offline and syncs), follow the
  **gate board** and **live bookings**, and keep the **attendance** register — on any tablet, signed in with a
  team passcode.
- **Admins** run everything from a Filament dashboard: bookings and payments, invoices, inventory, events and
  seasons, customers, reports, analytics, team accounts, security and settings — plus a read-only **AI assistant
  (MCP)** connection.

## Stack

PHP 8.4 · Laravel 12 · Filament 4 · Livewire 3 · Tailwind CSS 4 · Alpine · Fortify (2FA) · Sanctum · Horizon ·
Reverb · spatie permission / settings / activitylog / backup · maatwebsite/excel · mPDF · endroid/qr-code ·
laravel/mcp · Scramble · Pest 3 · Pint · Larastan.

## Quick start (local)

```bash
composer install && npm ci && npm run build
cp .env.example .env && php artisan key:generate
# local: DB_CONNECTION=sqlite, set SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD and STAFF_PASSCODE
php artisan migrate:fresh --seed && php artisan storage:link
php artisan serve
```

- Public site: `/` · Staff apps: `/staff` · Admin: `/admin` · API docs (admins): `/docs/api` · MCP: `POST /mcp`

## Quality

```bash
vendor/bin/pest                              # 192 tests — in-memory DB, all outbound HTTP faked
vendor/bin/pint --test                       # code style
vendor/bin/phpstan analyse --memory-limit=2G # Larastan level 5
python tools/test-matrix.py                  # spec T-ID → test mapping (docs/TEST-MATRIX.md)
```

## Documentation

| Document | Contents |
|---|---|
| [docs/SPEC.md](docs/SPEC.md) | Master specification (features, rules, defects, tests). |
| [docs/PROGRESS.md](docs/PROGRESS.md) | What is delivered, defects fixed, what is pending. |
| [docs/RUNBOOK.md](docs/RUNBOOK.md) | Deployment, daily operations, payment and security incidents, backups. |
| [docs/SECURITY-CHECKLIST.md](docs/SECURITY-CHECKLIST.md) | Controls and their tests. |
| [docs/DECISIONS.md](docs/DECISIONS.md) | Architecture and product decisions. |
| [docs/ERD.md](docs/ERD.md) | Data model. |
| [docs/TEST-MATRIX.md](docs/TEST-MATRIX.md) | Generated spec test coverage. |

## Deployment

`docker compose build && docker compose up -d` brings up the app, nginx, Horizon, the scheduler, Reverb, MySQL and
Redis — see [docs/RUNBOOK.md](docs/RUNBOOK.md). CI (GitHub Actions) runs style, static analysis, the test suite and
the image build on every push.
