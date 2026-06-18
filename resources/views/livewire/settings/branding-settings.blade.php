<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">Branding</h1>
        <p class="text-sm text-muted mt-1">Customize the brand colors used across the app.</p>
    </div>

    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="p-3 rounded-xl text-sm bg-success/10 border border-success/20 text-success">{{ session('success') }}</div>
    @endif

    <form wire:submit="save"
          x-data="{ brand: @entangle('brand'), brandStrong: @entangle('brand_strong'), accent: @entangle('accent') }"
          class="bg-surface-2 rounded-2xl border border-border overflow-hidden">

        {{-- Logo & Favicon --}}
        <div class="px-6 py-4 border-b border-border">
            <h2 class="text-base font-semibold text-ink">Logo &amp; Favicon</h2>
            <p class="text-xs text-muted mt-0.5">Shown in the sidebar, login page and browser tab.</p>
        </div>

        <div class="p-6 space-y-6 border-b border-border">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Logo --}}
                <div>
                    <label class="block text-sm font-medium text-muted mb-1.5">Logo</label>
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-border bg-surface overflow-hidden">
                            @if ($logo)
                                <img src="{{ $logo->temporaryUrl() }}" alt="New logo preview" class="h-full w-full object-contain">
                            @elseif ($currentLogoUrl)
                                <img src="{{ $currentLogoUrl }}" alt="Current logo" class="h-full w-full object-contain">
                            @else
                                <svg viewBox="0 0 24 24" class="h-6 w-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <input type="file" wire:model="logo" accept=".png,.jpg,.jpeg,.svg,.webp" class="input text-xs">
                            <div wire:loading wire:target="logo" class="text-xs text-muted mt-1">Uploading…</div>
                            @error('logo') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-muted mt-1">PNG, JPG, SVG or WEBP. Max 2MB.</p>
                            @if ($currentLogoUrl && ! $logo)
                                <button type="button" wire:click="removeLogo" wire:confirm="Remove the current logo?" class="text-xs text-danger hover:underline mt-1">Remove logo</button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Favicon --}}
                <div>
                    <label class="block text-sm font-medium text-muted mb-1.5">Favicon</label>
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-border bg-surface overflow-hidden">
                            @if ($favicon && $favicon->getClientOriginalExtension() !== 'ico')
                                <img src="{{ $favicon->temporaryUrl() }}" alt="New favicon preview" class="h-8 w-8 object-contain">
                            @elseif ($favicon)
                                <span class="text-xs text-muted text-center px-1">{{ $favicon->getClientOriginalName() }}</span>
                            @elseif ($currentFaviconUrl)
                                <img src="{{ $currentFaviconUrl }}" alt="Current favicon" class="h-8 w-8 object-contain">
                            @else
                                <svg viewBox="0 0 24 24" class="h-6 w-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <input type="file" wire:model="favicon" accept=".png,.jpg,.jpeg,.svg,.ico,.webp" class="input text-xs">
                            <div wire:loading wire:target="favicon" class="text-xs text-muted mt-1">Uploading…</div>
                            @error('favicon') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-muted mt-1">PNG, ICO or SVG. Square, max 512KB.</p>
                            @if ($currentFaviconUrl && ! $favicon)
                                <button type="button" wire:click="removeFavicon" wire:confirm="Remove the current favicon?" class="text-xs text-danger hover:underline mt-1">Remove favicon</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Brand Colors --}}
        <div class="px-6 py-4 border-b border-border">
            <h2 class="text-base font-semibold text-ink">Brand Colors</h2>
        </div>

        <div class="p-6 space-y-6">
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
                        <div class="mt-2 h-2 rounded-full" :style="'background-color:' + {{ $f['x'] }}"></div>
                    </div>
                @endforeach
            </div>

            {{-- Live preview --}}
            <div class="border-t border-border pt-5">
                <h3 class="text-xs font-semibold text-muted uppercase tracking-wider mb-3">Preview</h3>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white shadow-soft" :style="'background-color:' + brand">Primary button</button>
                    <span class="px-3 py-1 rounded-full text-xs font-medium" :style="'background-color:' + brand + '1a; color:' + brand">Badge</span>
                    <span class="px-3 py-1 rounded-full text-xs font-medium text-white" :style="'background-color:' + accent">Accent</span>
                    <a href="#" @click.prevent class="text-sm font-medium" :style="'color:' + brand">Link style</a>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between px-6 py-4 border-t border-border bg-surface/40">
            <button type="button" wire:click="resetDefaults" class="text-sm text-muted hover:text-ink">Reset to defaults</button>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save Branding</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </form>
</div>
