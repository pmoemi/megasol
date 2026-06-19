<?php

namespace App\Traits;

/**
 * Normalizes Kenyan phone numbers for Africa's Talking SMS.
 *
 * Matches megawatt format: 254XXXXXXXXX (no "+" prefix), which is what
 * the working megawatt CRM uses in SendSmsJob.
 */
trait NormalizesPhoneNumbers
{
    protected function normalizePhone(string $phone, ?string $defaultCountryCode = null): string
    {
        $defaultCountryCode = $defaultCountryCode
            ?? config('africastalking.default_country_code', '254');

        $phone = trim($phone);

        if (preg_match('/^\+?\d+(?:\.\d+)?e\+?\d+$/i', $phone)) {
            $phone = number_format((float) $phone, 0, '', '');
        }

        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return $phone;
        }

        if (preg_match('/^07\d{8}$/', $digits)) {
            return $defaultCountryCode.substr($digits, 1);
        }

        if (preg_match('/^01\d{8}$/', $digits)) {
            return $defaultCountryCode.substr($digits, 1);
        }

        if (preg_match('/^7\d{8}$/', $digits)) {
            return $defaultCountryCode.$digits;
        }

        if (preg_match('/^254\d{9}$/', $digits)) {
            return $digits;
        }

        if (preg_match('/^072\d{6}$/', $digits)) {
            return $defaultCountryCode.substr($digits, 1);
        }

        if (preg_match('/^011\d{6}$/', $digits)) {
            return $defaultCountryCode.substr($digits, 1);
        }

        if (str_starts_with($digits, $defaultCountryCode)) {
            return $digits;
        }

        return $defaultCountryCode.ltrim($digits, '0');
    }

    protected function isValidKenyanMobile(string $phone): bool
    {
        $normalized = $this->normalizePhone($phone);

        return (bool) preg_match('/^254\d{9}$/', $normalized);
    }

    /**
     * @param  array<int, string>  $phones
     * @return array<int, string>
     */
    protected function normalizePhones(array $phones): array
    {
        return array_values(array_unique(array_map(
            fn (string $phone) => $this->normalizePhone($phone),
            array_filter($phones),
        )));
    }
}
