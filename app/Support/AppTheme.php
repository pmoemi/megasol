<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Central place for appearance + general app settings that are managed from
 * the Settings UI (Branding / Theme Studio / General) and stored in the
 * `settings` table. Provides the CSS-variable overrides injected into the
 * layout head and the runtime timezone application.
 */
class AppTheme
{
    public const KEY_BRAND = 'theme.brand';
    public const KEY_BRAND_STRONG = 'theme.brand_strong';
    public const KEY_ACCENT = 'theme.accent';

    public const KEY_TIMEZONE = 'app.timezone';
    public const KEY_APP_NAME = 'app.name';
    public const KEY_CURRENCY = 'app.currency';

    public const KEY_LOGO_PATH = 'branding.logo_path';
    public const KEY_FAVICON_PATH = 'branding.favicon_path';

    public const DEFAULT_BRAND = '#6366f1';
    public const DEFAULT_BRAND_STRONG = '#4f46e5';
    public const DEFAULT_ACCENT = '#B1AAFA';

    protected static function get(string $key, ?string $default = null): ?string
    {
        try {
            if (! Schema::hasTable('settings')) {
                return $default;
            }
        } catch (\Throwable) {
            return $default;
        }

        $value = Setting::get($key);

        return ($value === null || $value === '') ? $default : $value;
    }

    /**
     * @return array{brand:string, brand_strong:string, accent:string}
     */
    public static function colors(): array
    {
        return [
            'brand' => (string) self::get(self::KEY_BRAND, self::DEFAULT_BRAND),
            'brand_strong' => (string) self::get(self::KEY_BRAND_STRONG, self::DEFAULT_BRAND_STRONG),
            'accent' => (string) self::get(self::KEY_ACCENT, self::DEFAULT_ACCENT),
        ];
    }

    /**
     * CSS custom-property overrides for the configured brand colors. Injected
     * after the compiled stylesheet so it overrides the @theme defaults.
     */
    public static function cssVariables(): string
    {
        $c = self::colors();

        return ':root{'
            ."--color-brand:{$c['brand']};"
            ."--color-brand-strong:{$c['brand_strong']};"
            ."--color-accent:{$c['accent']};"
            ."--color-primary:{$c['brand']};"
            ."--color-primary-500:{$c['brand']};"
            ."--color-primary-600:{$c['brand_strong']};"
            .'}';
    }

    /**
     * Public URL of the configured logo, or null if none has been uploaded.
     */
    public static function logoUrl(): ?string
    {
        $path = self::get(self::KEY_LOGO_PATH);

        return self::publicDiskUrl($path);
    }

    /**
     * Public URL of the configured favicon, or null if none has been uploaded.
     */
    public static function faviconUrl(): ?string
    {
        $path = self::get(self::KEY_FAVICON_PATH);

        return self::publicDiskUrl($path);
    }

    /**
     * URL for a file on the public disk (/storage/...).
     *
     * Uses a root-relative path so logos work on HTTPS even when APP_URL
     * is still set to http:// in .env (common on cPanel).
     */
    public static function publicDiskUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return '/storage/'.ltrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Display name from Settings → General, falling back to config / .env.
     */
    public static function appName(): string
    {
        return (string) self::get(self::KEY_APP_NAME, config('app.name', 'MegaSol'));
    }

    /**
     * Apply the configured app name over config at boot.
     */
    public static function applyAppName(): void
    {
        $name = self::get(self::KEY_APP_NAME);

        if ($name) {
            config(['app.name' => $name]);
        }
    }

    /**
     * Apply the configured default timezone over app config at boot.
     */
    public static function applyTimezone(): void
    {
        $tz = self::get(self::KEY_TIMEZONE);

        if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $tz]);
            date_default_timezone_set($tz);
        }
    }
}
