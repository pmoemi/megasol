<div class="space-y-6"
     x-data="{
        brand: @entangle('brand'),
        brandStrong: @entangle('brand_strong'),
        accent: @entangle('accent'),
        apply() {
            const el = document.getElementById('app-theme-vars');
            if (el) {
                el.textContent = ':root{--color-brand:' + this.brand + ';--color-brand-strong:' + this.brandStrong + ';--color-accent:' + this.accent + ';--color-primary:' + this.brand + ';--color-primary-500:' + this.brand + ';--color-primary-600:' + this.brandStrong + ';}';
            }
        }
     }"
     x-init="$watch('brand', () => apply()); $watch('brandStrong', () => apply()); $watch('accent', () => apply());">

    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">Theme Studio</h1>
            <p class="text-sm text-muted mt-1">Pick a preset or craft your own palette — changes preview live across the app.</p>
        </div>
        <button type="button" wire:click="save" class="btn-primary shrink-0" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Save Theme</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </div>

    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="p-3 rounded-xl text-sm bg-success/10 border border-success/20 text-success">{{ session('success') }}</div>
    @endif

    {{-- Presets --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-sm font-semibold text-muted uppercase tracking-wider mb-4">Presets</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach ($presets as $key => $preset)
                <button type="button" wire:click="applyPreset('{{ $key }}')"
                        class="flex items-center gap-3 p-3 rounded-xl border-2 transition-colors text-left"
                        :class="(brand.toLowerCase() === '{{ strtolower($preset['brand']) }}') ? 'border-brand bg-brand/5' : 'border-border hover:border-brand/30'">
                    <span class="flex -space-x-1">
                        <span class="w-5 h-5 rounded-full border border-white/40" style="background-color: {{ $preset['brand'] }}"></span>
                        <span class="w-5 h-5 rounded-full border border-white/40" style="background-color: {{ $preset['accent'] }}"></span>
                    </span>
                    <span class="text-sm font-medium text-ink">{{ $preset['label'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Custom colors --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-sm font-semibold text-muted uppercase tracking-wider mb-4">Custom Palette</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            @php
                $fields = [
                    ['model' => 'brand', 'x' => 'brand', 'label' => 'Primary'],
                    ['model' => 'brand_strong', 'x' => 'brandStrong', 'label' => 'Primary (hover)'],
                    ['model' => 'accent', 'x' => 'accent', 'label' => 'Accent'],
                ];
            @endphp
            @foreach ($fields as $f)
                <div>
                    <label class="block text-sm font-medium text-muted mb-1.5">{{ $f['label'] }}</label>
                    <div class="flex items-center gap-3">
                        <input type="color" x-model="{{ $f['x'] }}" class="w-12 h-12 rounded-xl border border-border cursor-pointer p-1 bg-surface">
                        <input type="text" wire:model="{{ $f['model'] }}" x-model="{{ $f['x'] }}" maxlength="7"
                               class="input font-mono text-xs flex-1 @error($f['model']) !border-danger @enderror">
                    </div>
                    @error($f['model']) <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    </div>

    {{-- Live preview --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-sm font-semibold text-muted uppercase tracking-wider mb-4">Live Preview</h2>
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn-primary">Primary action</button>
                <button type="button" class="px-4 py-2 rounded-xl text-sm font-medium border border-brand/30 text-brand hover:bg-brand/5">Secondary</button>
                <span class="badge badge-primary">Badge</span>
                <a href="#" @click.prevent class="text-sm font-medium text-brand">A themed link</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="rounded-xl border border-border p-4">
                    <div class="w-9 h-9 rounded-lg bg-brand/10 text-brand flex items-center justify-center mb-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <p class="text-sm font-semibold text-ink">Card title</p>
                    <p class="text-xs text-muted">Accent supporting text.</p>
                </div>
                <div class="rounded-xl p-4 text-white" :style="'background-color:' + brand">
                    <p class="text-sm font-semibold">Filled card</p>
                    <p class="text-xs opacity-80">Uses the primary color.</p>
                </div>
                <div class="rounded-xl p-4 text-white" :style="'background-color:' + accent">
                    <p class="text-sm font-semibold">Accent card</p>
                    <p class="text-xs opacity-80">Uses the accent color.</p>
                </div>
            </div>
        </div>
    </div>
</div>
