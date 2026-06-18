<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Support\AppTheme;
use App\Support\PublicDisk;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.settings', ['title' => 'Branding'])]
class BrandingSettings extends Component
{
    use WithFileUploads;

    public string $brand = AppTheme::DEFAULT_BRAND;

    public string $brand_strong = AppTheme::DEFAULT_BRAND_STRONG;

    public string $accent = AppTheme::DEFAULT_ACCENT;

    public $logo = null;

    public $favicon = null;

    public ?string $currentLogoUrl = null;

    public ?string $currentFaviconUrl = null;

    public function mount(): void
    {
        $colors = AppTheme::colors();
        $this->brand = $colors['brand'];
        $this->brand_strong = $colors['brand_strong'];
        $this->accent = $colors['accent'];

        $this->currentLogoUrl = AppTheme::logoUrl();
        $this->currentFaviconUrl = AppTheme::faviconUrl();
    }

    protected function rules(): array
    {
        $hex = 'required|regex:/^#([0-9a-fA-F]{6})$/';

        return [
            'brand' => $hex,
            'brand_strong' => $hex,
            'accent' => $hex,
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'favicon' => 'nullable|mimes:png,jpg,jpeg,svg,ico,webp|max:512',
        ];
    }

    public function save(): void
    {
        $this->validate();

        Setting::set(AppTheme::KEY_BRAND, $this->brand);
        Setting::set(AppTheme::KEY_BRAND_STRONG, $this->brand_strong);
        Setting::set(AppTheme::KEY_ACCENT, $this->accent);

        if ($this->logo) {
            $this->replaceFile(AppTheme::KEY_LOGO_PATH, $this->logo, 'logo');
            $this->logo = null;
        }

        if ($this->favicon) {
            $this->replaceFile(AppTheme::KEY_FAVICON_PATH, $this->favicon, 'favicon');
            $this->favicon = null;
        }

        session()->flash('success', 'Branding saved.');

        // Full reload so the injected theme CSS variables, logo and favicon
        // in the layout head pick up the new values immediately.
        $this->redirect(route('settings.branding'));
    }

    public function removeLogo(): void
    {
        $this->deleteFile(AppTheme::KEY_LOGO_PATH);
        $this->currentLogoUrl = null;

        session()->flash('success', 'Logo removed.');
        $this->redirect(route('settings.branding'));
    }

    public function removeFavicon(): void
    {
        $this->deleteFile(AppTheme::KEY_FAVICON_PATH);
        $this->currentFaviconUrl = null;

        session()->flash('success', 'Favicon removed.');
        $this->redirect(route('settings.branding'));
    }

    /**
     * Store an uploaded file, replacing and removing any previously stored
     * file for the given setting key.
     */
    protected function replaceFile(string $settingKey, $file, string $prefix): void
    {
        $this->deleteFile($settingKey, flushSetting: false);

        $path = $file->store('branding', 'public');

        PublicDisk::mirrorToWeb($path);

        Setting::set($settingKey, $path);
    }

    protected function deleteFile(string $settingKey, bool $flushSetting = true): void
    {
        $existing = Setting::get($settingKey);

        if ($existing) {
            PublicDisk::delete($existing);
        }

        if ($flushSetting) {
            Setting::set($settingKey, null);
        }
    }

    public function resetDefaults(): void
    {
        $this->brand = AppTheme::DEFAULT_BRAND;
        $this->brand_strong = AppTheme::DEFAULT_BRAND_STRONG;
        $this->accent = AppTheme::DEFAULT_ACCENT;
    }

    public function render()
    {
        return view('livewire.settings.branding-settings');
    }
}
