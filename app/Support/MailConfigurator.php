<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Applies SMTP / mail settings stored in the `settings` table over the
 * runtime mail config, so the sending transport can be managed from the
 * Settings UI instead of only via .env. Runs on every request and queue
 * worker boot (see AppServiceProvider).
 */
class MailConfigurator
{
    public const KEY_MAILER = 'mail.mailer';
    public const KEY_HOST = 'mail.host';
    public const KEY_PORT = 'mail.port';
    public const KEY_USERNAME = 'mail.username';
    public const KEY_PASSWORD = 'mail.password';
    public const KEY_ENCRYPTION = 'mail.encryption';
    public const KEY_FROM_ADDRESS = 'mail.from_address';
    public const KEY_FROM_NAME = 'mail.from_name';

    public static function apply(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $values = Setting::query()
            ->whereIn('key', [
                self::KEY_MAILER, self::KEY_HOST, self::KEY_PORT, self::KEY_USERNAME,
                self::KEY_PASSWORD, self::KEY_ENCRYPTION, self::KEY_FROM_ADDRESS, self::KEY_FROM_NAME,
            ])
            ->pluck('value', 'key');

        if ($values->isEmpty()) {
            return;
        }

        $mailer = $values[self::KEY_MAILER] ?? null;
        if ($mailer) {
            config(['mail.default' => $mailer]);
        }

        $host = $values[self::KEY_HOST] ?? null;
        if ($host) {
            $encryption = $values[self::KEY_ENCRYPTION] ?? null;

            config([
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) ($values[self::KEY_PORT] ?? 587),
                'mail.mailers.smtp.username' => $values[self::KEY_USERNAME] ?? null,
                'mail.mailers.smtp.password' => $values[self::KEY_PASSWORD] ?? null,
                // Symfony mailer: 'smtps' forces implicit TLS (port 465);
                // null lets STARTTLS negotiate (port 587).
                'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : null,
                'mail.mailers.smtp.encryption' => $encryption ?: null,
            ]);
        }

        if (! empty($values[self::KEY_FROM_ADDRESS])) {
            config(['mail.from.address' => $values[self::KEY_FROM_ADDRESS]]);
        }
        if (! empty($values[self::KEY_FROM_NAME])) {
            config(['mail.from.name' => $values[self::KEY_FROM_NAME]]);
        }
    }
}
