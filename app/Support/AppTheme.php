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

    public const KEY_FONT_FAMILY = 'theme.font_family';
    public const KEY_FONT_WEIGHTS = 'theme.font_weights';
    public const KEY_FONT_SIZE = 'theme.font_size';
    public const KEY_CARD_RADIUS = 'theme.card_radius';

    public const DEFAULT_BRAND = '#6366f1';
    public const DEFAULT_BRAND_STRONG = '#4f46e5';
    public const DEFAULT_ACCENT = '#B1AAFA';

    public const DEFAULT_FONT_FAMILY = 'outfit';
    public const DEFAULT_FONT_WEIGHTS = '300;400;500;600;700;800';
    public const DEFAULT_FONT_SIZE = '16';
    public const DEFAULT_CARD_RADIUS = '1rem';

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
     * Selectable font families. Each maps to a CSS stack and the Google Fonts
     * `family=` query for loading it (null = system fonts, nothing to load).
     *
     * @return array<string, array{label: string, stack: string, google: ?string}>
     */
    public static function fonts(): array
    {
        return [
            'outfit' => ['label' => 'Outfit', 'stack' => "'Outfit', system-ui, sans-serif", 'google' => 'Outfit'],
            'inter' => ['label' => 'Inter', 'stack' => "'Inter', system-ui, sans-serif", 'google' => 'Inter'],
            'poppins' => ['label' => 'Poppins', 'stack' => "'Poppins', system-ui, sans-serif", 'google' => 'Poppins'],
            'roboto' => ['label' => 'Roboto', 'stack' => "'Roboto', system-ui, sans-serif", 'google' => 'Roboto'],
            'open-sans' => ['label' => 'Open Sans', 'stack' => "'Open Sans', system-ui, sans-serif", 'google' => 'Open+Sans'],
            'lato' => ['label' => 'Lato', 'stack' => "'Lato', system-ui, sans-serif", 'google' => 'Lato'],
            'montserrat' => ['label' => 'Montserrat', 'stack' => "'Montserrat', system-ui, sans-serif", 'google' => 'Montserrat'],
            'nunito' => ['label' => 'Nunito', 'stack' => "'Nunito', system-ui, sans-serif", 'google' => 'Nunito'],
            'system' => ['label' => 'System Default', 'stack' => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif", 'google' => null],
        ];
    }

    /**
     * Sanitised semicolon-separated Google Fonts weight list (e.g. "400;600;700").
     * Fewer weights = smaller font download. Falls back to the default when the
     * stored value is empty or malformed.
     */
    public static function fontWeights(): string
    {
        $raw = self::appearanceRaw('font_weights', self::DEFAULT_FONT_WEIGHTS);

        $weights = collect(preg_split('/[;,\s]+/', $raw))
            ->map(fn ($w) => (int) trim($w))
            ->filter(fn ($w) => $w >= 100 && $w <= 900)
            ->unique()
            ->sort()
            ->values();

        return $weights->isEmpty() ? self::DEFAULT_FONT_WEIGHTS : $weights->implode(';');
    }

    /**
     * Base font-size presets (px) applied to <html>; the rem-based UI scales
     * proportionally.
     *
     * @return array<string, string>
     */
    public static function fontSizes(): array
    {
        return [
            '14' => 'Compact',
            '15' => 'Small',
            '16' => 'Normal',
            '17' => 'Comfortable',
            '18' => 'Large',
            '20' => 'Extra Large',
        ];
    }

    /**
     * Card / surface border-radius presets (cards use the rounded-2xl token).
     *
     * @return array<string, string>
     */
    public static function cardRadii(): array
    {
        return [
            '0' => 'Square',
            '0.5rem' => 'Small',
            '0.75rem' => 'Medium',
            '1rem' => 'Large',
            '1.5rem' => 'Extra Large',
            '2rem' => 'Rounded',
        ];
    }

    /**
     * Resolve an appearance value for the current request. Appearance is
     * strictly per-user: the logged-in user's own preference wins, otherwise the
     * hard default. There is intentionally no global override — one user's font
     * or radius never affects anyone else.
     */
    protected static function appearanceRaw(string $prefKey, string $default): string
    {
        try {
            $user = auth()->user();
        } catch (\Throwable) {
            $user = null;
        }

        if ($user && method_exists($user, 'appearancePreference')) {
            $pref = $user->appearancePreference($prefKey);

            if ($pref !== null) {
                return $pref;
            }
        }

        return $default;
    }

    public static function fontFamilyKey(): string
    {
        $key = self::appearanceRaw('font_family', self::DEFAULT_FONT_FAMILY);

        return array_key_exists($key, self::fonts()) ? $key : self::DEFAULT_FONT_FAMILY;
    }

    public static function fontStack(): string
    {
        return self::fonts()[self::fontFamilyKey()]['stack'];
    }

    public static function fontSize(): string
    {
        $size = self::appearanceRaw('font_size', self::DEFAULT_FONT_SIZE);

        return array_key_exists($size, self::fontSizes()) ? $size : self::DEFAULT_FONT_SIZE;
    }

    public static function cardRadius(): string
    {
        $radius = self::appearanceRaw('card_radius', self::DEFAULT_CARD_RADIUS);

        return self::sanitizeRadius($radius) ?? self::DEFAULT_CARD_RADIUS;
    }

    /**
     * Validate a border-radius value before it is injected into a <style> block.
     * Accepts the presets and any custom "0", "<n>px", "<n>rem", or "<n>em" —
     * anything else returns null so a malicious value can never break out of the
     * CSS custom property.
     */
    public static function sanitizeRadius(string $value): ?string
    {
        $value = trim($value);

        if ($value === '0') {
            return '0';
        }

        return preg_match('/^\d+(\.\d+)?(px|rem|em)$/', $value) === 1 ? $value : null;
    }

    /**
     * Google Fonts stylesheet URL for the configured family, or null when a
     * system font (nothing to fetch) is selected.
     */
    public static function googleFontUrl(): ?string
    {
        $google = self::fonts()[self::fontFamilyKey()]['google'] ?? null;

        if (! $google) {
            return null;
        }

        return 'https://fonts.googleapis.com/css2?family='.$google.':wght@'.self::fontWeights().'&display=swap';
    }

    /**
     * CSS custom-property overrides for the configured brand colors, font, base
     * size, and card radius. Injected after the compiled stylesheet so it
     * overrides the @theme defaults.
     */
    public static function cssVariables(): string
    {
        $c = self::colors();
        $fontStack = self::fontStack();
        $cardRadius = self::cardRadius();
        $fontSize = self::fontSize();

        return ':root{'
            ."--color-brand:{$c['brand']};"
            ."--color-brand-strong:{$c['brand_strong']};"
            ."--color-accent:{$c['accent']};"
            ."--color-primary:{$c['brand']};"
            ."--color-primary-500:{$c['brand']};"
            ."--color-primary-600:{$c['brand_strong']};"
            ."--font-sans:{$fontStack}, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';"
            ."--card-radius:{$cardRadius};"
            ."--radius-2xl:{$cardRadius};"
            .'}'
            ."html{font-size:{$fontSize}px;}";
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
