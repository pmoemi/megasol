<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Support\AppTheme;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.settings', ['title' => 'General'])]
class GeneralSettings extends Component
{
    public string $app_name = '';

    public string $timezone = 'UTC';

    public string $currency = 'KES';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->app_name = (string) (Setting::get(AppTheme::KEY_APP_NAME) ?: config('app.name', 'MegaSol'));
        $this->timezone = (string) (Setting::get(AppTheme::KEY_TIMEZONE) ?: config('app.timezone', 'UTC'));
        $this->currency = (string) (Setting::get(AppTheme::KEY_CURRENCY) ?: 'KES');
    }

    protected function rules(): array
    {
        return [
            'app_name' => 'required|string|max:120',
            'timezone' => 'required|timezone',
            'currency' => 'required|string|max:8',
        ];
    }

    public function save(): void
    {
        $this->validate();

        Setting::set(AppTheme::KEY_APP_NAME, $this->app_name);
        Setting::set(AppTheme::KEY_TIMEZONE, $this->timezone);
        Setting::set(AppTheme::KEY_CURRENCY, strtoupper($this->currency));

        config(['app.name' => $this->app_name]);
        config(['app.timezone' => $this->timezone]);
        date_default_timezone_set($this->timezone);

        $this->statusMessage = 'General settings saved.';
        session()->flash('success', 'General settings saved.');
    }

    public function render()
    {
        return view('livewire.settings.general-settings', [
            'timezones' => timezone_identifiers_list(),
        ]);
    }
}
