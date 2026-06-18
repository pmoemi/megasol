# MegaSol SMS

Customer communications platform for MegaSol — SMS campaigns, automations, customer 360, PayGro integration, and collections.

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

Upload `deploy/megasol-deploy.zip` to your subdomain root and extract in cPanel File Manager.

## Key features

- SMS campaigns and templates (Africa's Talking)
- Customer profiles, segments, and import
- PayGro token sync and SMS delivery
- Branding (logo, colors, app name)
- Workflow automations and scheduled jobs

## Project structure

```
app/           Application code (Livewire, services, models)
deploy/        cPanel deploy scripts and root .htaccess
resources/     Blade views and frontend source
routes/        Web and console routes
database/      Migrations and seeders
```

## License

Proprietary — MegaWatt Energies Ltd.
