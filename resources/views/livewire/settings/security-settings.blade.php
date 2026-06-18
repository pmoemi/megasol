<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">Security</h1>
        <p class="text-sm text-muted mt-1">Manage your password and two-factor authentication.</p>
    </div>

    @if ($statusMessage)
        <div class="p-3 rounded-xl text-sm border {{ $statusIsError ? 'bg-danger/10 border-danger/20 text-danger' : 'bg-success/10 border-success/20 text-success' }}">
            {{ $statusMessage }}
        </div>
    @endif

    {{-- Change Password --}}
    <form wire:submit="updatePassword" class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-4 border-b border-border">
            <div class="w-10 h-10 bg-info/15 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h2 class="text-base font-semibold text-ink">Change Password</h2>
                <p class="text-xs text-muted mt-0.5">Update your account password.</p>
            </div>
        </div>

        <div class="p-6 space-y-4 max-w-lg">
            <div>
                <label class="block text-sm font-medium text-muted mb-1">Current Password</label>
                <input type="password" wire:model="current_password" autocomplete="current-password" class="input @error('current_password') !border-danger @enderror">
                @error('current_password') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-muted mb-1">New Password</label>
                <input type="password" wire:model="password" autocomplete="new-password" class="input @error('password') !border-danger @enderror">
                @error('password') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-muted mb-1">Confirm New Password</label>
                <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="input">
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border bg-surface/40">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="updatePassword">
                <span wire:loading.remove wire:target="updatePassword">Update Password</span>
                <span wire:loading wire:target="updatePassword">Updating…</span>
            </button>
        </div>
    </form>

    {{-- Two-Factor Authentication --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-4 border-b border-border">
            <div class="w-10 h-10 bg-warning/15 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h2 class="text-base font-semibold text-ink">Two-Factor Authentication</h2>
                <p class="text-xs text-muted mt-0.5">Add an extra layer of security to your account using an authenticator app.</p>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $twoFactorEnabled ? 'bg-success/15 text-success' : 'bg-surface text-muted' }}">
                {{ $twoFactorEnabled ? 'Enabled' : 'Disabled' }}
            </span>
        </div>

        <div class="p-6 space-y-4">
            @if (! $twoFactorEnabled)
                <p class="text-sm text-muted">
                    When two-factor authentication is enabled, you will be prompted for a secure, random code from an authenticator app (such as Google Authenticator) during login.
                </p>

                @if ($showingQrCode)
                    <div class="space-y-4 max-w-sm">
                        <div class="bg-white p-4 rounded-xl border border-border inline-block">
                            {!! $qrCodeSvg !!}
                        </div>
                        <p class="text-sm text-muted">Scan the QR code with your authenticator app, then enter the generated code below to confirm.</p>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Authentication Code</label>
                            <input type="text" wire:model="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" class="input @error('code') !border-danger @enderror">
                            @error('code') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        </div>
                        <button type="button" wire:click="confirmTwoFactorAuthentication" class="btn-primary" wire:loading.attr="disabled" wire:target="confirmTwoFactorAuthentication">
                            <span wire:loading.remove wire:target="confirmTwoFactorAuthentication">Confirm</span>
                            <span wire:loading wire:target="confirmTwoFactorAuthentication">Confirming…</span>
                        </button>
                    </div>
                @else
                    <button type="button" wire:click="enableTwoFactorAuthentication" class="btn-primary" wire:loading.attr="disabled" wire:target="enableTwoFactorAuthentication">
                        <span wire:loading.remove wire:target="enableTwoFactorAuthentication">Enable Two-Factor Authentication</span>
                        <span wire:loading wire:target="enableTwoFactorAuthentication">Enabling…</span>
                    </button>
                @endif
            @else
                <p class="text-sm text-success">Two-factor authentication is enabled. You'll be asked for a code from your authenticator app when you sign in.</p>

                @if ($showingRecoveryCodes)
                    <div class="space-y-2 max-w-sm">
                        <p class="text-sm text-muted">Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two-factor authentication device is lost.</p>
                        <div class="bg-surface border border-border rounded-xl p-4 font-mono text-sm text-ink space-y-1">
                            @foreach ($recoveryCodes as $recoveryCode)
                                <div>{{ $recoveryCode }}</div>
                            @endforeach
                        </div>
                        <button type="button" wire:click="regenerateRecoveryCodes" class="btn-secondary" wire:loading.attr="disabled" wire:target="regenerateRecoveryCodes">
                            <span wire:loading.remove wire:target="regenerateRecoveryCodes">Regenerate Recovery Codes</span>
                            <span wire:loading wire:target="regenerateRecoveryCodes">Regenerating…</span>
                        </button>
                    </div>
                @else
                    <button type="button" wire:click="showRecoveryCodes" class="btn-secondary">Show Recovery Codes</button>
                @endif

                <div class="border-t border-border pt-4 space-y-3 max-w-sm">
                    <p class="text-sm font-medium text-ink">Disable Two-Factor Authentication</p>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Confirm Password</label>
                        <input type="password" wire:model="confirm_password" autocomplete="current-password" class="input @error('confirm_password') !border-danger @enderror">
                        @error('confirm_password') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="disableTwoFactorAuthentication" class="btn-secondary !text-danger" wire:loading.attr="disabled" wire:target="disableTwoFactorAuthentication">
                        <span wire:loading.remove wire:target="disableTwoFactorAuthentication">Disable Two-Factor Authentication</span>
                        <span wire:loading wire:target="disableTwoFactorAuthentication">Disabling…</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Active Sessions --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-base font-semibold text-ink mb-1">Active Sessions</h2>
        <p class="text-sm text-muted">
            Sessions are managed using the configured session driver
            (<span class="font-mono text-ink/70">{{ config('session.driver') }}</span>).
            Changing your password or disabling two-factor authentication does not automatically sign out other devices.
        </p>
    </div>
</div>
