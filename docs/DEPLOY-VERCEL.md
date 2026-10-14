# Deploying QCAMELMC to Vercel

Vercel runs the app as a PHP 8.4 serverless function (community runtime `vercel-php@0.8.0`).
Everything the app needs is already in the repository (`vercel.json`, `api/index.php`,
`.vercelignore`); this guide covers the part only you can do: the Vercel project settings,
the environment variables, and the external services a serverless host needs.

## 1. What is different on Vercel

| Concern | Normal server | Vercel |
|---|---|---|
| Filesystem | writable | **read-only**: the entrypoint moves `storage/` and `bootstrap/cache` to `/tmp` on every cold start |
| Database | local MySQL/Postgres | **external** Postgres (Supabase pooler) or MySQL (PlanetScale, Neon, etc.) |
| Uploads, QR images, PDFs | local disk | **S3-compatible bucket** (`STORAGE_DRIVER=s3`), e.g. Supabase Storage or Cloudflare R2 |
| Queue worker / Horizon | resident process | **database queue drained by a cron request** every minute (`/vercel/cron/minute`) |
| Scheduler | `schedule:work` | same cron request runs `schedule:run` first |
| Sadad webhook | queued job | **processed inline** inside the webhook request (`SERVERLESS=1`) |
| Websockets (Reverb) | optional | not available: every screen already polls, nothing to configure |
| Cache / sessions | Redis | **database** (`CACHE_STORE=database`, `SESSION_DRIVER=database`) |

The per-minute cron needs the **Vercel Pro plan**. On the Hobby plan Vercel only allows one
cron run per day. Section 6 explains the free alternative.

## 2. Create the project

1. Vercel dashboard → **Add New → Project** → import `rabi3ogabes/qcamel_laravel`.
2. Framework preset: **Other**. Leave build settings as they are: `vercel.json` already sets the install
   command (`npm ci` + `composer install`), the build command (`npm run build`) and the output directory (`public`).
3. Add the environment variables from section 3 **before** the first deploy (Production environment).
4. Deploy. The first build takes 3–5 minutes (Composer + Vite).

## 3. Environment variables

Set these in **Settings → Environment Variables** (Production). Values marked *secret* must only live there.

### Application

| Variable | Value |
|---|---|
| `APP_NAME` | `QCAMELMC` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://<your-domain>` (the Vercel URL until the custom domain is attached) |
| `APP_KEY` | *secret* — generate locally with `php artisan key:generate --show` |
| `SERVERLESS` | `1` |
| `CRON_SECRET` | *secret* — any random string of 32+ characters; Vercel sends it as the bearer token of the cron request |
| `CRON_BUDGET_SECONDS` | `45` (must stay below the function `maxDuration` of 60) |
| `TRUSTED_PROXIES` | `*` |
| `LOG_CHANNEL` | `stderr` (logs appear in Vercel → Logs) |
| `CACHE_STORE` | `database` |
| `SESSION_DRIVER` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `BROADCAST_CONNECTION` | `null` |
| `FILESYSTEM_DISK` | `s3` |
| `STORAGE_DRIVER` | `s3` |

### Database (Supabase Postgres, pooled connection)

Supabase → Project Settings → Database → **Connection string → Transaction pooler** (port 6543).

| Variable | Value |
|---|---|
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | `aws-0-<region>.pooler.supabase.com` |
| `DB_PORT` | `6543` |
| `DB_DATABASE` | `postgres` |
| `DB_USERNAME` | `postgres.<project-ref>` |
| `DB_PASSWORD` | *secret* |
| `DB_SSLMODE` | `require` |

Any managed MySQL works too (`DB_CONNECTION=mysql`); the CI matrix runs the suite on both.

### Storage (S3-compatible)

With Supabase Storage: create two buckets, `qcamel-public` (public) and `qcamel-private` (private),
then Project Settings → Storage → **S3 access keys**.

| Variable | Value |
|---|---|
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | *secret* |
| `AWS_DEFAULT_REGION` | the project region, e.g. `eu-central-1` |
| `AWS_BUCKET` | `qcamel-public` |
| `AWS_ENDPOINT` | `https://<project-ref>.supabase.co/storage/v1/s3` |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` |
| `AWS_URL` | `https://<project-ref>.supabase.co/storage/v1/object/public/qcamel-public` |
| `AWS_PRIVATE_BUCKET` | `qcamel-private` |

Cloudflare R2 or AWS S3 work the same way with their own endpoint and URL.

### Mail, payments, staff access

| Variable | Notes |
|---|---|
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | any SMTP/API provider (Resend, Postmark, SES) |
| `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD` | *secret* — used once by `db:seed` to create the first admin; change the password after first login |
| `STAFF_PASSCODE_HASH` | *secret* — `php artisan tinker --execute="echo Hash::make('your passcode');"` |
| `SADAD_*` | may stay empty: Sadad keys are entered in Admin → Settings → Payments (stored encrypted) |
| `N8N_WEBHOOK_URL`, `N8N_RESPONSE_SECRET`, `MAIL_WEBHOOK_SECRET` | if WhatsApp / mail-event automations are used |

## 4. Migrate and seed (no shell on Vercel)

Vercel functions have no shell, so run the one-time setup **from your machine against the production database**.
Create a throw-away `.env.production.local` (never committed) containing only the `DB_*` and `AWS_*` values
from section 3, plus `APP_KEY`, `SEED_ADMIN_*` and `STAFF_PASSCODE_HASH`, then:

```bash
php artisan migrate --force --env=production.local
```

```bash
php artisan db:seed --force --env=production.local
```

For the data cut-over from the old Supabase app, point the importer at the same file and run it the same way
(`php artisan migrate:from-supabase --sqlite=... --storage=... --env=production.local`), as rehearsed in
section 6 of [DEPLOY-LARAVEL-CLOUD.md](DEPLOY-LARAVEL-CLOUD.md) and [RUNBOOK.md](RUNBOOK.md). Delete the local file afterwards.

Future releases that add migrations: run `migrate --force` the same way before promoting the deployment.

## 5. Verify the deployment

1. `https://<domain>/up` returns `200`.
2. `https://<domain>/` shows the public site, `/login` the unified login, `/admin` the back office.
3. **Cron**: Vercel → Project → **Cron Jobs** shows `/vercel/cron/minute` and its last run returned `{"ok":true,...}`.
   You can call it yourself: `curl -H "Authorization: Bearer $CRON_SECRET" https://<domain>/vercel/cron/minute`.
   Without the header it returns `401`.
4. Place a test order with Sadad in sandbox mode (Admin → Settings → Payments → environment `sandbox`);
   the webhook confirms the order within the request and the ticket QR appears in the public bucket.
5. Vercel → **Logs** shows application errors (`LOG_CHANNEL=stderr`).

## 6. Hobby plan (no per-minute cron)

If the project is on the Hobby plan, change the schedule in `vercel.json` to the daily `0 3 * * *` (Vercel rejects
the deploy otherwise) and trigger the endpoint every minute from an external scheduler instead, e.g.
[cron-job.org](https://cron-job.org) or GitHub Actions, with the header `Authorization: Bearer <CRON_SECRET>`.
The endpoint is idempotent and locked, so overlapping callers are harmless. Payments never depend on the cron
(the webhook is inline); only WhatsApp/e-mail notifications, QR backfills and exports wait for it.

## 7. Known limits on Vercel

- **Cold starts** of 1–2 s after idle periods; the Admin panel is heavier than the public site.
- **Function limit of 60 s**: large Excel exports run in the cron drain, not in the request.
- **No websockets**: Reverb stays off; live screens poll every few seconds as designed.
- **`/tmp` is per instance**: compiled Blade views are rebuilt on each cold start (a few hundred ms). Never store
  anything the app must keep on the local disk, hence `STORAGE_DRIVER=s3`.
- **Backups**: `backup:run` from spatie/laravel-backup writes to the private S3 bucket; database dumps need `pg_dump`,
  which the runtime does not ship. Use the Supabase daily backups instead.

## 8. Custom domain

Vercel → Project → **Domains** → add `tickets.<yourdomain>.qa` and create the CNAME it shows. Then update
`APP_URL` and redeploy so signed URLs and e-mails use the final domain. Sadad's merchant settings must
whitelist the same domain for the callback and webhook URLs shown in Admin → Settings → Payments (see section 7 of [DEPLOY-LARAVEL-CLOUD.md](DEPLOY-LARAVEL-CLOUD.md)).
