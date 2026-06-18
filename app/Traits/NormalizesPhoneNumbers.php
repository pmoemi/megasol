<?php

namespace App\Traits;

/**
 * Normalizes phone numbers to E.164 for Africa's Talking SMS.
 */
trait NormalizesPhoneNumbers
{
    protected function normalizePhone(string $phone, ?string $defaultCountryCode = null): string
    {
        $defaultCountryCode = $defaultCountryCode
            ?? config('africastalking.default_country_code', '254');

        $cleaned = preg_replace('/[\s\-\(\)\.]+/', '', trim($phone));

        if ($cleaned === '') {
            return $phone;
        }

        // Local format: 0712345678 → +254712345678
        if (preg_match('/^0(\d{9,10})$/', $cleaned, $matches)) {
            return '+'.$defaultCountryCode.$matches[1];
        }

        if (ctype_digit($cleaned)) {
            return '+'.$cleaned;
        }

        if (preg_match('/^\+\d+$/', $cleaned)) {
            return $cleaned;
        }

        return $cleaned;
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
