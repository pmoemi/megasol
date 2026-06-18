<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">General</h1>
        <p class="text-sm text-muted mt-1">Application name, default timezone, and currency.</p>
    </div>

    @if ($statusMessage)
        <div class="p-3 rounded-xl text-sm bg-success/10 border border-success/20 text-success">{{ $statusMessage }}</div>
    @endif

    <form wire:submit="save" class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
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
        <div class="flex items-center justify-end px-6 py-4 border-t border-border bg-surface/40">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save Settings</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </form>
</div>
