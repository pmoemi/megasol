# MegaSol SMS

Customer communications platform for MegaSol — SMS campaigns, automations, customer 360, PayGro integration, and collections.

**Production:** [https://megasol.megawattenergiesltd.com](https://megasol.megawattenergiesltd.com)

Built with **Laravel 12**, **Livewire 3**, and **Tailwind CSS**.

## Requirements

- PHP 8.3+
- MySQL 8+
- Composer
- Node.js 20+ (for frontend builds)

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Default admin login (after seeding): `admin@megasol.com` / `password`

## Production deploy (cPanel)

See `deploy/UPLOAD_INSTRUCTIONS.txt`.

```powershell
powershell -ExecutionPolicy Bypass -File deploy\build-package-root.ps1
```

Upload `deploy/megasol-deploy.zip` to your subdomain document root and extract in cPanel File Manager.

### After uploading code

1. Run pending migrations (SSH: `php artisan migrate --force`, or **Settings → System → Run migrations**).
2. Clear caches (**Settings → System → Clear all caches**) so UI changes appear.
3. Configure SMS credentials under **Settings → SMS** and send a test message.
4. Add the cron job(s) shown under **Settings → System → Cron jobs (cPanel)**.

### Cron & queues (cPanel)

Recommended production `.env`:

```env
QUEUE_CONNECTION=sync
```

With `sync`, token SMS, manual sends, and campaigns deliver immediately — no queue worker process is needed.

Add one cron job in cPanel (copy the exact command from **Settings → System**):

```bash
* * * * * /usr/local/bin/php /path/to/app/artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs:

| Task | Frequency |
|------|-----------|
| `sms:run-automations` | Hourly |
| `paygro:sync --source=scheduled` | Daily |

If you use `QUEUE_CONNECTION=database`, also add the queue worker cron shown in System settings (or run `php artisan queue:work` via Supervisor).

## Key features

- **SMS gateway** — Africa's Talking outbound/inbound, test send, delivery reports (Settings → SMS)
- **SMS campaigns & automations** — bulk sends, merge tags, scheduled automations
- **Customer 360** — profile, PayGro units, token fetch/send, SMS timeline
- **Payments tab** — Repayment Schedule (installments) and Payment History with asset filter
- **PayGro sync** — customers, assets, payment plans, balances, days in arrears, repayment schedules
- **Branding & theme** — logo, colors, app name (Settings → Branding / Theme Studio)
- **System tools** — migrations, cache clear, copyable cron commands (Settings → System, admin only)
- **Workflows** — visual builder for multi-step SMS/email flows

## PayGro

Sync from CLI or scheduled cron:

```bash
php artisan paygro:sync
php artisan paygro:sync --customer=174
php artisan paygro:dedupe-customers   # reconcile duplicate customer/asset records
```

Configure API credentials under **Settings → PayGro**. Payment plans, unlock prices, and balances are synced into local tables for fast Customer 360 views.

## SMS configuration

1. Open **Settings → SMS**.
2. Enter Africa's Talking username, API key, sender ID, and country code (`254`).
3. Save and use **Send Test** to verify.
4. Copy webhook URLs from the same page into your Africa's Talking dashboard (DLR + inbound).

All outbound paths (token SMS, campaigns, workflows, automations) use the same gateway credentials loaded from the database via `SmsConfigurator`.

## Project structure

```
app/           Application code (Livewire, services, models, jobs)
deploy/        cPanel deploy scripts and root .htaccess
resources/     Blade views and frontend source
routes/        Web, API, and console routes
database/      Migrations and seeders
tests/         Feature and unit tests
```

## License

Proprietary — MegaWatt Energies Ltd.
