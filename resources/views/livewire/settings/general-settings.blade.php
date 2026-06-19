<div class="space-y-6">
    @php
        // Preload every selectable font on this page so the live preview renders
        // in the chosen family before it is saved.
        $previewWeights = \App\Support\AppTheme::DEFAULT_FONT_WEIGHTS;
        $previewFamilies = collect($fonts)->pluck('google')->filter()
            ->map(fn ($family) => $family.':wght@'.$previewWeights)
            ->implode('&family=');
        $previewFontsUrl = $previewFamilies ? 'https://fonts.googleapis.com/css2?family='.$previewFamilies.'&display=swap' : null;
    @endphp
    @if($previewFontsUrl)
        <link href="{{ $previewFontsUrl }}" rel="stylesheet">
    @endif

    <div>
        <h1 class="text-2xl font-bold text-ink">General</h1>
        <p class="text-sm text-muted mt-1">Application name, timezone, currency, and appearance.</p>
    </div>

    @if ($statusMessage)
        <div class="p-3 rounded-xl text-sm bg-success/10 border border-success/20 text-success">{{ $statusMessage }}</div>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{-- Application --}}
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-base font-semibold text-ink">Application</h2>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Application Name *</label>
                    <input type="text" wire:model="app_name" class="input @error('app_name') !border-danger @enderror">
                    @error('app_name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Default Timezone *</label>
                        <select wire:model="timezone" class="input @error('timezone') !border-danger @enderror">
                            @foreach ($timezones as $tz)
                                <option value="{{ $tz }}">{{ $tz }}</option>
                            @endforeach
                        </select>
                        @error('timezone') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-muted mt-1">Used for scheduling and timestamps across the app.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Currency *</label>
                        <input type="text" wire:model="currency" maxlength="8" placeholder="KES" class="input uppercase @error('currency') !border-danger @enderror">
                        @error('currency') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Appearance --}}
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-base font-semibold text-ink">Appearance</h2>
                <p class="text-xs text-muted mt-0.5">Typography and card styling for <strong>your account only</strong> — other users keep their own.</p>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Body Font *</label>
                        <select wire:model.live="font_family" class="input @error('font_family') !border-danger @enderror">
                            @foreach ($fonts as $key => $font)
                                <option value="{{ $key }}">{{ $font['label'] }}{{ $key === \App\Support\AppTheme::DEFAULT_FONT_FAMILY ? ' (default)' : '' }}</option>
                            @endforeach
                        </select>
                        @error('font_family') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-muted mt-1">Applied site-wide (dashboard, settings, customer pages). Loaded from Google Fonts.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Font Weights *</label>
                        <input type="text" wire:model="font_weights" placeholder="300;400;500;600;700;800"
                               class="input font-mono text-xs @error('font_weights') !border-danger @enderror">
                        @error('font_weights') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-muted mt-1">Semicolon-separated weights, e.g. 400;500;700. Fewer = faster page loads.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Base Font Size *</label>
                        <select wire:model.live="font_size" class="input @error('font_size') !border-danger @enderror">
                            @foreach ($fontSizes as $value => $label)
                                <option value="{{ $value }}">{{ $label }} ({{ $value }}px)</option>
                            @endforeach
                        </select>
                        @error('font_size') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-muted mt-1">Scales the whole interface proportionally.</p>
                    </div>
                </div>

                {{-- Card radius presets --}}
                <div>
                    <label class="block text-sm font-medium text-muted mb-2">Card Border Radius *</label>
                    @php $isCustomRadius = ! array_key_exists($card_radius, $cardRadii); @endphp
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex flex-wrap items-center gap-1 p-1 bg-surface border border-border rounded-xl">
                            @foreach ($cardRadii as $value => $label)
                                @php $selected = $card_radius === (string) $value; @endphp
                                <button type="button"
                                        wire:click="$set('card_radius', '{{ $value }}')"
                                        aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                        class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors
                                               {{ $selected ? 'bg-brand text-white shadow-sm' : 'text-muted hover:text-ink hover:bg-surface-2' }}">
                                    <span class="w-3.5 h-3.5 shrink-0"
                                          style="border-top: 2px solid currentColor; border-left: 2px solid currentColor; border-top-left-radius: {{ $value }};"></span>
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Custom value --}}
                        <div class="inline-flex items-center gap-1 p-1 rounded-xl border {{ $isCustomRadius ? 'border-brand bg-brand/5' : 'border-border bg-surface' }}">
                            <span class="w-3.5 h-3.5 shrink-0 ml-1 {{ $isCustomRadius ? 'text-brand' : 'text-muted' }}"
                                  style="border-top: 2px solid currentColor; border-left: 2px solid currentColor; border-top-left-radius: {{ $isCustomRadius ? $card_radius : '0.5rem' }};"></span>
                            <input type="number" min="0" max="200" step="0.05" wire:model="custom_radius_value"
                                   placeholder="Custom" aria-label="Custom radius value"
                                   class="w-16 px-2 py-1 text-xs bg-transparent border-0 focus:outline-none text-ink">
                            <select wire:model="custom_radius_unit" aria-label="Custom radius unit"
                                    class="px-1 py-1 text-xs bg-transparent border-0 focus:outline-none text-ink">
                                <option value="rem">rem</option>
                                <option value="px">px</option>
                                <option value="em">em</option>
                            </select>
                            <button type="button" wire:click="applyCustomRadius"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold text-white bg-brand hover:bg-brand-strong transition-colors">
                                Apply
                            </button>
                        </div>
                    </div>
                    @error('card_radius') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    @error('custom_radius_value') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted mt-1">Pick a preset or enter a custom value. Current: <span class="font-mono text-ink">{{ $card_radius }}</span></p>
                </div>

                {{-- Live preview --}}
                <div>
                    <label class="block text-sm font-medium text-muted mb-2">Preview</label>
                    <div class="border border-dashed border-border p-4 bg-surface"
                         style="border-radius: 0.75rem; font-family: {{ $fonts[$font_family]['stack'] ?? '' }}; font-size: {{ $font_size }}px;">
                        <div class="bg-surface-2 border border-border p-5 shadow-soft"
                             style="border-radius: {{ $card_radius }};">
                            <h3 class="font-bold text-ink">{{ $fonts[$font_family]['label'] ?? 'Font' }} · {{ $fontSizes[$font_size] ?? '' }}</h3>
                            <p class="text-muted mt-1">The quick brown fox jumps over the lazy dog — 0123456789.</p>
                            <div class="mt-3 flex items-center gap-2">
                                <span class="px-3 py-1.5 text-sm font-semibold text-white bg-brand" style="border-radius: {{ $card_radius }};">Primary</span>
                                <span class="px-3 py-1.5 text-sm font-medium text-ink bg-surface border border-border" style="border-radius: {{ $card_radius }};">Secondary</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-muted mt-2">Changes preview live here. Click <strong>Save Settings</strong> to apply across the app.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save Settings</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </form>
</div>
