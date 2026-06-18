<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Support\AppTheme;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.settings', ['title' => 'Theme Studio'])]
class ThemeStudio extends Component
{
    public string $brand = AppTheme::DEFAULT_BRAND;

    public string $brand_strong = AppTheme::DEFAULT_BRAND_STRONG;

    public string $accent = AppTheme::DEFAULT_ACCENT;

    /**
     * Built-in color presets.
     *
     * @return array<string, array{label:string, brand:string, brand_strong:string, accent:string}>
     */
    public function presets(): array
    {
        return [
            'indigo' => ['label' => 'Indigo', 'brand' => '#6366f1', 'brand_strong' => '#4f46e5', 'accent' => '#B1AAFA'],
            'violet' => ['label' => 'Violet', 'brand' => '#8b5cf6', 'brand_strong' => '#7c3aed', 'accent' => '#c4b5fd'],
            'emerald' => ['label' => 'Emerald', 'brand' => '#10b981', 'brand_strong' => '#059669', 'accent' => '#6ee7b7'],
            'sky' => ['label' => 'Sky', 'brand' => '#0ea5e9', 'brand_strong' => '#0284c7', 'accent' => '#7dd3fc'],
            'rose' => ['label' => 'Rose', 'brand' => '#f43f5e', 'brand_strong' => '#e11d48', 'accent' => '#fda4af'],
            'amber' => ['label' => 'Amber', 'brand' => '#f59e0b', 'brand_strong' => '#d97706', 'accent' => '#fcd34d'],
            'teal' => ['label' => 'Teal', 'brand' => '#14b8a6', 'brand_strong' => '#0d9488', 'accent' => '#5eead4'],
            'slate' => ['label' => 'Slate', 'brand' => '#475569', 'brand_strong' => '#334155', 'accent' => '#94a3b8'],
        ];
    }

    public function mount(): void
    {
        $colors = AppTheme::colors();
        $this->brand = $colors['brand'];
        $this->brand_strong = $colors['brand_strong'];
        $this->accent = $colors['accent'];
    }

    public function applyPreset(string $key): void
    {
        $preset = $this->presets()[$key] ?? null;
        if (! $preset) {
            return;
        }

        $this->brand = $preset['brand'];
        $this->brand_strong = $preset['brand_strong'];
        $this->accent = $preset['accent'];
    }

    protected function rules(): array
    {
        $hex = 'required|regex:/^#([0-9a-fA-F]{6})$/';

        return [
            'brand' => $hex,
            'brand_strong' => $hex,
            'accent' => $hex,
        ];
    }

    public function save(): void
    {
        $this->validate();

        Setting::set(AppTheme::KEY_BRAND, $this->brand);
        Setting::set(AppTheme::KEY_BRAND_STRONG, $this->brand_strong);
        Setting::set(AppTheme::KEY_ACCENT, $this->accent);

        session()->flash('success', 'Theme saved.');

        // Full reload so the theme CSS variables in the layout head refresh.
        $this->redirect(route('settings.theme-studio'));
    }

    public function render()
    {
        return view('livewire.settings.theme-studio', [
            'presets' => $this->presets(),
        ]);
    }
}
