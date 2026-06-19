<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">SMS Gateway</h1>
        <p class="text-sm text-muted mt-1">Configure Africa's Talking for outbound SMS sending and inbound replies.</p>
    </div>

    @if ($statusMessage)
        <div class="p-3 rounded-xl text-sm border {{ $statusIsError ? 'bg-danger/10 border-danger/20 text-danger' : 'bg-success/10 border-success/20 text-success' }}">
            {{ $statusMessage }}
        </div>
    @endif

    {{-- Automation kill-switch --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4 p-6">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {{ $automations_paused ? 'bg-warning/15' : 'bg-success/15' }}">
                @if ($automations_paused)
                    <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 9v6m4-6v6M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @else
                    <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="text-base font-semibold text-ink">Scheduled SMS Automations</h2>
                <p class="text-xs text-muted mt-0.5">
                    @if ($automations_paused)
                        <span class="font-semibold text-warning">Paused</span> — the hourly runner sends no automated messages (payment reminders, overdue, welcome, etc.) until resumed.
                    @else
                        <span class="font-semibold text-success">Active</span> — payment reminders, overdue, welcome and other automations send on their schedule. Pause to stop all automated sending instantly.
                    @endif
                </p>
            </div>
            <button type="button"
                    wire:click="toggleAutomationsPaused"
                    wire:confirm="{{ $automations_paused ? 'Resume scheduled SMS automations?' : 'Pause ALL scheduled SMS automations? No automated messages will be sent until you resume.' }}"
                    class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-xl transition-colors
                           {{ $automations_paused ? 'text-white bg-success hover:bg-success/90' : 'text-white bg-warning hover:bg-warning/90' }}">
                @if ($automations_paused)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><circle cx="12" cy="12" r="9"/></svg>
                    Resume automations
                @else
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Pause automations
                @endif
            </button>
        </div>

        {{-- Dynamic automation behaviour --}}
        <form wire:submit="saveAutomationSettings" class="border-t border-border p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Remind before due (days)</label>
                    <input type="number" min="0" max="365" wire:model="reminder_lead_days" class="input @error('reminder_lead_days') !border-danger @enderror">
                    @error('reminder_lead_days') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted mt-1">Payment reminders go out this many days before the due date. 0 = no date filter.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Overdue reminder after (days)</label>
                    <input type="number" min="0" max="365" wire:model="overdue_after_days" class="input @error('overdue_after_days') !border-danger @enderror">
                    @error('overdue_after_days') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted mt-1">Wait this many days past due before sending an overdue reminder. 0 = immediately.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Resend cooldown (hours)</label>
                    <input type="number" min="0" max="8760" wire:model="cooldown_hours" class="input @error('cooldown_hours') !border-danger @enderror">
                    @error('cooldown_hours') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted mt-1">Minimum gap before the same automation can message a customer again. 0 = no limit.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Max messages per run</label>
                    <input type="number" min="1" max="100000" wire:model="max_per_run" class="input @error('max_per_run') !border-danger @enderror">
                    @error('max_per_run') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted mt-1">Safety cap on recipients per automation per run.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Quiet hours start</label>
                    <input type="time" wire:model="quiet_hours_start" class="input @error('quiet_hours_start') !border-danger @enderror">
                    @error('quiet_hours_start') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted mt-1">No sends during quiet hours (leave blank to disable).</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Quiet hours end</label>
                    <input type="time" wire:model="quiet_hours_end" class="input @error('quiet_hours_end') !border-danger @enderror">
                    @error('quiet_hours_end') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted mt-1">Supports overnight ranges, e.g. 20:00 → 08:00.</p>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveAutomationSettings">
                    <span wire:loading.remove wire:target="saveAutomationSettings">Save automation settings</span>
                    <span wire:loading wire:target="saveAutomationSettings">Saving…</span>
                </button>
            </div>
        </form>
    </div>

    <form wire:submit="save" class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-4 border-b border-border">
            <div class="w-10 h-10 bg-success/15 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h2 class="text-base font-semibold text-ink">Africa's Talking</h2>
                <p class="text-xs text-muted mt-0.5">Outbound &amp; inbound SMS delivery</p>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $at_username && $apiKeyIsSet ? 'bg-success/15 text-success' : 'bg-surface text-muted' }}">
                {{ $at_username && $apiKeyIsSet ? 'Configured' : 'Not configured' }}
            </span>
        </div>

        <div class="p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Username *</label>
                    <input type="text" wire:model="at_username" placeholder="sandbox" class="input @error('at_username') !border-danger @enderror">
                    @error('at_username') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">API Key</label>
                    <input type="password" wire:model="at_api_key" autocomplete="new-password"
                           placeholder="{{ $apiKeyIsSet ? '•••••••• (leave blank to keep)' : 'Africa\'s Talking API key' }}" class="input @error('at_api_key') !border-danger @enderror">
                    @error('at_api_key') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Sender ID / Shortcode</label>
                    <input type="text" wire:model="at_sender_id" placeholder="e.g. MEGASOL" class="input @error('at_sender_id') !border-danger @enderror">
                    @error('at_sender_id') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted mt-1">Leave blank to use your account's default sender.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Default Country Code *</label>
                    <input type="text" wire:model="at_default_country_code" placeholder="254" class="input @error('at_default_country_code') !border-danger @enderror">
                    @error('at_default_country_code') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted mt-1">Used to convert local numbers like 0712... to international format.</p>
                </div>
            </div>

            <div class="border-t border-border pt-4">
                <label class="block text-sm font-medium text-muted mb-1">Delivery Report Secret</label>
                <input type="text" wire:model="at_dlr_secret" placeholder="Optional shared secret" class="input @error('at_dlr_secret') !border-danger @enderror">
                @error('at_dlr_secret') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-muted mt-1">Appended as <span class="font-mono text-ink/70">?secret=...</span> to the delivery-report webhook URL below to verify requests from Africa's Talking.</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border bg-surface/40">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save Settings</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>

        {{-- Inbound (two-way) — separate shortcode / credentials --}}
        <div class="flex items-center gap-4 px-6 py-4 border-t border-border">
            <div class="w-10 h-10 bg-info/15 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h2 class="text-base font-semibold text-ink">Inbound (Two-Way) SMS</h2>
                <p class="text-xs text-muted mt-0.5">Optional dedicated shortcode for customer replies. Campaigns &amp; single sends always use the settings above.</p>
            </div>
        </div>

        <div class="p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Inbound Shortcode / Sender ID</label>
                    <input type="text" wire:model="in_sender_id" placeholder="e.g. 20880" class="input @error('in_sender_id') !border-danger @enderror">
                    @error('in_sender_id') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted mt-1">Replies (STOP, BALANCE, etc.) are sent from this. Leave blank to reuse the outbound sender.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Inbound Webhook Secret</label>
                    <input type="text" wire:model="in_secret" placeholder="Optional shared secret" class="input @error('in_secret') !border-danger @enderror">
                    @error('in_secret') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Inbound Username</label>
                    <input type="text" wire:model="in_username" placeholder="e.g. megasol_inbound" class="input @error('in_username') !border-danger @enderror">
                    @error('in_username') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Inbound API Key</label>
                    <input type="password" wire:model="in_api_key" autocomplete="new-password"
                           placeholder="{{ $inApiKeyIsSet ? '•••••••• (leave blank to keep)' : 'This shortcode\'s own API key' }}" class="input @error('in_api_key') !border-danger @enderror">
                    @error('in_api_key') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted mt-1">The inbound shortcode uses its own credentials, separate from the outbound API key above.</p>
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

    {{-- Webhook URLs for Africa's Talking dashboard --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-4">
        <div>
            <h2 class="text-base font-semibold text-ink mb-1">Webhook URLs</h2>
            <p class="text-xs text-muted">Configure these in your Africa's Talking dashboard so delivery reports and inbound replies (e.g. "STOP", "BALANCE") reach this app.</p>
        </div>
        <div>
            <label class="block text-xs font-semibold text-muted uppercase tracking-wider mb-1.5">Delivery Reports (DLR)</label>
            <div class="px-4 py-2.5 text-sm bg-surface border border-border rounded-xl text-ink/70 font-mono break-all">
                {{ $dlr_url }}{{ $at_dlr_secret ? '?secret='.$at_dlr_secret : '' }}
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-muted uppercase tracking-wider mb-1.5">Incoming Messages (Inbound Shortcode)</label>
            <div class="px-4 py-2.5 text-sm bg-surface border border-border rounded-xl text-ink/70 font-mono break-all">
                @php($inboundSecret = $in_secret ?: $at_dlr_secret)
                {{ $inbound_url }}{{ $inboundSecret ? '?secret='.$inboundSecret : '' }}
            </div>
        </div>
    </div>

    {{-- Send a test SMS --}}
    <form wire:submit.prevent="sendTest" class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-base font-semibold text-ink mb-1">Send a test SMS</h2>
        <p class="text-xs text-muted mb-3">Sends one message using the username and sender above plus the saved API key (or a new key pasted in the API Key field). Does not auto-save — click <strong class="font-medium text-ink/80">Save Settings</strong> to persist changes. Usually takes 5–10 seconds while Africa's Talking responds.</p>
        <div class="flex flex-col sm:flex-row gap-2">
            <input type="text" wire:model="test_phone" placeholder="254791474748" class="input sm:w-48 @error('test_phone') !border-danger @enderror" wire:loading.attr="disabled" wire:target="sendTest">
            <input type="text" wire:model="test_message" class="input flex-1" wire:loading.attr="disabled" wire:target="sendTest">
            <button type="submit" class="btn-secondary shrink-0" wire:loading.attr="disabled" wire:target="sendTest" @disabled($testSendInProgress)>
                <span wire:loading.remove wire:target="sendTest">Send Test</span>
                <span wire:loading wire:target="sendTest">Sending… (wait)</span>
            </button>
        </div>
        @error('test_phone') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
        @error('at_username') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
        <p class="text-xs text-muted mt-2">Working credentials: username <code class="font-mono text-ink/70">megawattenergies</code>, sender <code class="font-mono text-ink/70">MEGATECH</code>.</p>
    </form>
</div>
