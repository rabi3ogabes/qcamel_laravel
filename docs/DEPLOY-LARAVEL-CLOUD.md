# Deploying to Laravel Cloud

Step-by-step for `rabi3ogabes/qcamel_laravel`. Allow about 45 minutes the first time. Labels in the Cloud dashboard can
change slightly over time — look for the closest match.

> **Before you start:** have ready your Sadad merchant id, secret key and registered domain, an SMTP provider
> (e.g. Postmark, Resend, Amazon SES), the team passcode you want for the gate tablets, and the e-mail + password for
> the owner account.

---

## 1. Create the application

1. Go to **cloud.laravel.com** → sign up / sign in with **GitHub**.
2. Allow the Laravel Cloud GitHub app to access **rabi3ogabes/qcamel_laravel** (you can limit it to this repo).
3. **New application** → choose the repository → branch **main**.
4. **Region**: the closest one to Qatar that Cloud offers (Middle East if listed, otherwise Frankfurt / EU Central).
5. Create. Cloud makes a **production** environment with a `*.laravel.cloud` URL.

## 2. Add the resources (environment → *Resources* / *Infrastructure*)

| Resource | What to pick | Notes |
|---|---|---|
| **Database** | MySQL 8 *or* Postgres 17 | Both are tested in CI. Attach it to the environment — Cloud fills `DB_*` for you. |
| **Cache** | Key-value store (Redis / Valkey) | Attach — Cloud fills `REDIS_*`. Used for cache, locks and the queue. |
| **Object storage** | **Two** buckets: `qcamel-public` (visibility **public**) and `qcamel-private` (**private**) | The server disk is wiped on every deploy, so QR images, event images and receipt photos live here. Keep each bucket's *access key, secret, endpoint* and (public bucket) *URL* — you paste them in step 4. |
| **WebSockets** | Managed Reverb | Attach — powers live bookings, the gate board and capacity alerts. If Cloud does not fill `REVERB_*` / `VITE_REVERB_*`, copy them from the resource page into the variables in step 4. |

## 3. Compute (environment → *App* / *Compute* settings)

1. **PHP 8.4**.
2. **Build commands** (keep Cloud's defaults if they match):
   ```bash
   composer install --no-dev --optimize-autoloader --no-interaction
   npm ci --no-audit --no-fund
   npm run build
   ```
3. **Deploy commands**:
   ```bash
   php artisan migrate --force
   ```
4. **Scheduler**: turn it **on** (expires 30-minute online seat holds, reconciles Sadad payments, auto-invoices,
   pruning, backups).
5. **Queue worker / background process**: add one running
   ```bash
   php artisan horizon
   ```
   (if your plan only offers a plain queue worker, use
   `php artisan queue:work --queue=payments,notifications,automation,qr,exports,default --tries=3`).
6. Hibernation / scale-to-zero: **off** for production — the gate and POS must answer instantly on event days.

## 4. Environment variables (environment → *Settings* → *Environment variables*)

Cloud already sets `APP_KEY`, `DB_*`, `REDIS_*` (and possibly `REVERB_*`). Add the rest — values marked 🔒 are secrets:

```dotenv
APP_NAME="مهرجان قطر للإبل"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<your-app>.laravel.cloud        # later: your own domain
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
APP_BUSINESS_TIMEZONE=Asia/Qatar
TRUSTED_PROXIES=*
LOG_CHANNEL=stderr
LOG_LEVEL=warning

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
CACHE_STORE=redis
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb

# Storage — public bucket
STORAGE_DRIVER=s3
AWS_ACCESS_KEY_ID=<public bucket key>
AWS_SECRET_ACCESS_KEY=<public bucket secret>              🔒
AWS_DEFAULT_REGION=auto
AWS_BUCKET=qcamel-public
AWS_URL=<public bucket URL>
AWS_ENDPOINT=<public bucket endpoint>
# Storage — private bucket
AWS_PRIVATE_ACCESS_KEY_ID=<private bucket key>
AWS_PRIVATE_SECRET_ACCESS_KEY=<private bucket secret>     🔒
AWS_PRIVATE_BUCKET=qcamel-private
AWS_PRIVATE_ENDPOINT=<private bucket endpoint>

# Mail
MAIL_MAILER=smtp
MAIL_HOST=<smtp host>
MAIL_PORT=587
MAIL_USERNAME=<smtp user>
MAIL_PASSWORD=<smtp password>                             🔒
MAIL_FROM_ADDRESS=tickets@<your-domain>
MAIL_FROM_NAME="${APP_NAME}"

# First owner account (used once by db:seed)
SEED_ADMIN_NAME="مالك النظام"
SEED_ADMIN_EMAIL=<owner e-mail>
SEED_ADMIN_PASSWORD=<12+ characters>                      🔒
ADMIN_2FA_REQUIRED=true

# Gate / POS tablets — generate the hash on your computer:
#   php artisan tinker --execute="echo Hash::make('your-passcode');"
STAFF_PASSCODE_HASH=<bcrypt hash>                         🔒

# Optional now (can be set later in Admin → Settings → Payments / Messaging)
SADAD_ENV=auto
FRONTEND_URL="${APP_URL}"
```

## 5. First deploy

1. **Deploy**. Watch the log: build → `migrate --force` must finish without errors.
2. Open the environment's **Commands** (or *Console*) tab and run once:
   ```bash
   php artisan db:seed --force
   ```
   (creates roles/permissions and the owner account from `SEED_ADMIN_*`; demo data is skipped in production).
3. Open `https://<your-app>.laravel.cloud/admin` → sign in as the owner → set a new password → enrol 2FA.
4. **Admin → Settings → Payments (Sadad)**: merchant id, registered domain, secret → **Run check** until it is green.

## 6. Bring over the old data (optional, recommended before go-live)

From your computer, with the backup converted as in RUNBOOK §8:

1. In Cloud, open the database resource and copy its **public connection** details (enable public access temporarily
   if it is off).
2. In a terminal in the project folder (PowerShell):
   ```powershell
   $env:DB_CONNECTION="mysql"; $env:DB_HOST="<host>"; $env:DB_PORT="<port>"; $env:DB_DATABASE="<db>"
   $env:DB_USERNAME="<user>"; $env:DB_PASSWORD="<password>"
   php artisan migrate:from-supabase --sqlite=<path>\legacy.sqlite --dry-run   # rehearsal
   php artisan migrate:from-supabase --sqlite=<path>\legacy.sqlite             # for real
   ```
3. QR images: run `php artisan tickets:backfill-qr` in the Cloud **Commands** tab (re-creates every missing picture in
   the bucket), until it reports 0 remaining.
4. Turn the database's public access off again.

## 7. Your domain

1. Environment → **Domains** → add `tickets.<your-domain>` (or the apex) and create the DNS records Cloud shows.
2. Change `APP_URL` (and `FRONTEND_URL`) to the new domain → redeploy.
3. In the **Sadad merchant panel**: register the new domain, and the webhook URL shown in Admin → Settings → Payments.

## 8. Clean up

- Delete the project on **Vercel** (it can't run this app and fails on every push).
- Keep CI green: every push to `main` runs tests, MySQL/Postgres migrations and the Docker build on GitHub.

## Checklist for event day

- [ ] Admin → Events: current event set, capacities and prices right.
- [ ] A test online booking reaches **confirmed** and the invoice e-mail arrives (Horizon is running).
- [ ] A gate tablet signs in with the team passcode and scans a test ticket.
- [ ] Admin → Notifications shows the test bookings; Live bookings updates without refresh (Reverb).
