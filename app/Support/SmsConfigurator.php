<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Applies Africa's Talking SMS settings stored in the `settings` table over
 * the runtime `africastalking` config, so the SMS gateway can be managed
 * from the Settings UI instead of only via .env. Runs on every request and
 * queue worker boot (see AppServiceProvider).
 */
class SmsConfigurator
{
    public const KEY_USERNAME = 'sms.africastalking.username';
    public const KEY_API_KEY = 'sms.africastalking.api_key';
    public const KEY_SENDER_ID = 'sms.africastalking.sender_id';
    public const KEY_DEFAULT_COUNTRY_CODE = 'sms.africastalking.default_country_code';
    public const KEY_DLR_SECRET = 'sms.africastalking.dlr_secret';

    // Inbound (two-way) — separate shortcode / credentials / secret.
    public const KEY_INBOUND_USERNAME = 'sms.africastalking.inbound_username';
    public const KEY_INBOUND_API_KEY = 'sms.africastalking.inbound_api_key';
    public const KEY_INBOUND_SENDER_ID = 'sms.africastalking.inbound_sender_id';
    public const KEY_INBOUND_SECRET = 'sms.africastalking.inbound_secret';

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
                self::KEY_USERNAME, self::KEY_API_KEY, self::KEY_SENDER_ID,
                self::KEY_DEFAULT_COUNTRY_CODE, self::KEY_DLR_SECRET,
                self::KEY_INBOUND_USERNAME, self::KEY_INBOUND_API_KEY,
                self::KEY_INBOUND_SENDER_ID, self::KEY_INBOUND_SECRET,
            ])
            ->pluck('value', 'key');

        if ($values->isEmpty()) {
            return;
        }

        if (! empty($values[self::KEY_USERNAME])) {
            config(['africastalking.username' => $values[self::KEY_USERNAME]]);
        }

        if (! empty($values[self::KEY_API_KEY])) {
            config(['africastalking.api_key' => trim((string) $values[self::KEY_API_KEY])]);
        }

        if (array_key_exists(self::KEY_SENDER_ID, $values->all())) {
            config(['africastalking.sender_id' => $values[self::KEY_SENDER_ID] ?: null]);
        }

        if (! empty($values[self::KEY_DEFAULT_COUNTRY_CODE])) {
            config(['africastalking.default_country_code' => $values[self::KEY_DEFAULT_COUNTRY_CODE]]);
        }

        if (array_key_exists(self::KEY_DLR_SECRET, $values->all())) {
            config(['africastalking.dlr_secret' => $values[self::KEY_DLR_SECRET] ?: null]);
        }

        // Inbound (two-way) — separate shortcode / credentials / secret.
        if (array_key_exists(self::KEY_INBOUND_USERNAME, $values->all())) {
            config(['africastalking.inbound.username' => $values[self::KEY_INBOUND_USERNAME] ?: null]);
        }

        if (array_key_exists(self::KEY_INBOUND_API_KEY, $values->all())) {
            config(['africastalking.inbound.api_key' => $values[self::KEY_INBOUND_API_KEY] ?: null]);
        }

        if (array_key_exists(self::KEY_INBOUND_SENDER_ID, $values->all())) {
            config(['africastalking.inbound.sender_id' => $values[self::KEY_INBOUND_SENDER_ID] ?: null]);
        }

        if (array_key_exists(self::KEY_INBOUND_SECRET, $values->all())) {
            config(['africastalking.inbound.secret' => $values[self::KEY_INBOUND_SECRET] ?: null]);
        }
    }
}
