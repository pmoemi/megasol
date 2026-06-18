<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">Email / SMTP</h1>
        <p class="text-sm text-muted mt-1">Configure the outgoing mail server used to send campaign and test emails.</p>
    </div>

    @if ($statusMessage)
        <div class="p-3 rounded-xl text-sm border {{ $statusIsError ? 'bg-danger/10 border-danger/20 text-danger' : 'bg-success/10 border-success/20 text-success' }}">
            {{ $statusMessage }}
        </div>
    @endif

    <form wire:submit="save" class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-4 border-b border-border">
            <div class="w-10 h-10 bg-brand/15 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h2 class="text-base font-semibold text-ink">SMTP Server</h2>
                <p class="text-xs text-muted mt-0.5">Outgoing email delivery</p>
            </div>
        </div>

        <div class="p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Mailer</label>
                    <select wire:model.live="mail_mailer" class="input">
                        <option value="smtp">SMTP</option>
                        <option value="log">Log (no real sending)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Encryption</label>
                    <select wire:model="mail_encryption" class="input">
                        <option value="tls">TLS (STARTTLS, port 587)</option>
                        <option value="ssl">SSL (port 465)</option>
                        <option value="none">None</option>
                    </select>
                </div>
            </div>

            @if ($mail_mailer === 'smtp')
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-muted mb-1">Host *</label>
                        <input type="text" wire:model="mail_host" placeholder="smtp.mailgun.org" class="input @error('mail_host') !border-danger @enderror">
                        @error('mail_host') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Port *</label>
                        <input type="number" wire:model="mail_port" placeholder="587" class="input @error('mail_port') !border-danger @enderror">
                        @error('mail_port') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Username</label>
                        <input type="text" wire:model="mail_username" autocomplete="off" class="input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Password</label>
                        <input type="password" wire:model="mail_password" autocomplete="new-password"
                               placeholder="{{ $passwordIsSet ? '•••••••• (leave blank to keep)' : 'SMTP password' }}" class="input">
                    </div>
                </div>
            @endif

            <div class="border-t border-border pt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">From Address *</label>
                    <input type="email" wire:model="mail_from_address" placeholder="no-reply@yourdomain.com" class="input @error('mail_from_address') !border-danger @enderror">
                    @error('mail_from_address') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">From Name *</label>
                    <input type="text" wire:model="mail_from_name" placeholder="MegaSol" class="input @error('mail_from_name') !border-danger @enderror">
                    @error('mail_from_name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border bg-surface/40">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save Settings</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </form>

    {{-- Send a test email --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-base font-semibold text-ink mb-1">Send a test email</h2>
        <p class="text-xs text-muted mb-3">Saves the settings above, then sends a test message to verify your SMTP configuration.</p>
        <div class="flex flex-col sm:flex-row gap-2">
            <input type="email" wire:model="test_recipient" placeholder="you@example.com" class="input flex-1 @error('test_recipient') !border-danger @enderror">
            <button type="button" wire:click="sendTest" class="btn-secondary shrink-0" wire:loading.attr="disabled" wire:target="sendTest">
                <span wire:loading.remove wire:target="sendTest">Send Test</span>
                <span wire:loading wire:target="sendTest">Sending…</span>
            </button>
        </div>
        @error('test_recipient') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
    </div>
</div>
