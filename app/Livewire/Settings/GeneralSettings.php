<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Support\AppTheme;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.settings', ['title' => 'General'])]
class GeneralSettings extends Component
{
    public string $app_name = '';

    public string $timezone = 'UTC';

    public string $currency = 'KES';

    // ── Appearance ───────────────────────────────────────────────────────
    public string $font_family = AppTheme::DEFAULT_FONT_FAMILY;

    public string $font_weights = AppTheme::DEFAULT_FONT_WEIGHTS;

    public string $font_size = AppTheme::DEFAULT_FONT_SIZE;

    public string $card_radius = AppTheme::DEFAULT_CARD_RADIUS;

    public string $custom_radius_value = '';

    public string $custom_radius_unit = 'rem';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->app_name = (string) (Setting::get(AppTheme::KEY_APP_NAME) ?: config('app.name', 'MegaSol'));
        $this->timezone = (string) (Setting::get(AppTheme::KEY_TIMEZONE) ?: config('app.timezone', 'UTC'));
        $this->currency = (string) (Setting::get(AppTheme::KEY_CURRENCY) ?: 'KES');

        $this->font_family = AppTheme::fontFamilyKey();
        $this->font_weights = AppTheme::fontWeights();
        $this->font_size = AppTheme::fontSize();
        $this->card_radius = AppTheme::cardRadius();

        // If the saved radius is a custom value (not a preset), pre-fill the
        // custom inputs so it shows as selected.
        if (! array_key_exists($this->card_radius, AppTheme::cardRadii())
            && preg_match('/^(\d+(?:\.\d+)?)(px|rem|em)$/', $this->card_radius, $m)) {
            $this->custom_radius_value = $m[1];
            $this->custom_radius_unit = $m[2];
        }
    }

    /**
     * Compose a custom radius from the amount + unit inputs and select it.
     */
    public function applyCustomRadius(): void
    {
        $this->validate([
            'custom_radius_value' => 'required|numeric|min:0|max:200',
            'custom_radius_unit' => 'in:px,rem,em',
        ]);

        $amount = (float) $this->custom_radius_value;

        $this->card_radius = $amount == 0.0
            ? '0'
            : rtrim(rtrim(number_format($amount, 4, '.', ''), '0'), '.').$this->custom_radius_unit;
    }

    protected function rules(): array
    {
        return [
            'app_name' => 'required|string|max:120',
            'timezone' => 'required|timezone',
            'currency' => 'required|string|max:8',
            'font_family' => ['required', Rule::in(array_keys(AppTheme::fonts()))],
            'font_weights' => ['required', 'string', 'max:60', 'regex:/^\s*\d{3}(\s*[;,]\s*\d{3})*\s*$/'],
            'font_size' => ['required', Rule::in(array_keys(AppTheme::fontSizes()))],
            // Presets or a sanitized custom value (0 / <n>px / <n>rem / <n>em).
            'card_radius' => ['required', 'string', 'max:12', 'regex:/^(0|\d+(\.\d+)?(px|rem|em))$/'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        // App identity stays global (applies to everyone).
        Setting::set(AppTheme::KEY_APP_NAME, $this->app_name);
        Setting::set(AppTheme::KEY_TIMEZONE, $this->timezone);
        Setting::set(AppTheme::KEY_CURRENCY, strtoupper($this->currency));

        // Appearance is per-user — saved on the logged-in user's preferences.
        $user = auth()->user();
        $prefs = is_array($user->preferences) ? $user->preferences : [];
        $prefs['appearance'] = [
            'font_family' => $this->font_family,
            'font_weights' => $this->font_weights,
            'font_size' => $this->font_size,
            'card_radius' => $this->card_radius,
        ];
        $user->update(['preferences' => $prefs]);

        config(['app.name' => $this->app_name]);
        config(['app.timezone' => $this->timezone]);
        date_default_timezone_set($this->timezone);

        session()->flash('success', 'General settings saved.');

        // Reload so the new font, size, and radius CSS variables take effect.
        $this->redirectRoute('settings.general', navigate: true);
    }

    public function render()
    {
        return view('livewire.settings.general-settings', [
            'timezones' => timezone_identifiers_list(),
            'fonts' => AppTheme::fonts(),
            'fontSizes' => AppTheme::fontSizes(),
            'cardRadii' => AppTheme::cardRadii(),
        ]);
    }
}
