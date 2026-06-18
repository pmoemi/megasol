<div class="space-y-6">

    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition
         class="flex items-center gap-3 px-4 py-3 bg-success/10 border border-success/20 rounded-xl text-sm text-success">
        <span>{{ session('success') }}</span>
    </div>
    @endif

    @if($statusMessage)
    <div x-data="{ show: true }" x-show="show" x-transition
         class="flex items-start gap-3 px-4 py-3 border rounded-xl text-sm {{ $statusIsError ? 'bg-danger/10 border-danger/20 text-danger' : 'bg-info/10 border-info/20 text-info' }}">
        <span>{{ $statusMessage }}</span>
        <button type="button" @click="show = false" class="ml-auto opacity-60 hover:opacity-100">&times;</button>
    </div>
    @endif

    <div>
        <h1 class="text-2xl font-bold text-ink">PayGro Integration</h1>
        <p class="text-sm text-muted mt-1">Connect with your PayGro credentials. Sessions refresh automatically before each sync.</p>
    </div>

    {{-- Connection status --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-5">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-3">
                @if($connection['connected'])
                <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $connection['session_stale'] ? 'bg-warning/15' : 'bg-success/15' }}">
                    @if($connection['session_stale'])
                    <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @else
                    <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @endif
                </span>
                <div>
                    <p class="text-sm font-semibold text-ink">
                        Connected to PayGro
                        @if($connection['session_stale'])
                        <span class="ml-1.5 text-xs font-medium text-warning">(session stale — will refresh on next sync)</span>
                        @endif
                    </p>
                    <p class="text-xs text-muted">
                        @if($connection['account_name'])
                            {{ $connection['account_name'] }}
                            @if($connection['account_type_name']) &middot; {{ $connection['account_type_name'] }} @endif
                        @endif
                        @if($connection['last_refresh'])
                            &middot; session refreshed {{ \Carbon\Carbon::parse($connection['last_refresh'])->diffForHumans() }}
                            (auto-refreshes after {{ $connection['session_max_age_minutes'] }}min)
                        @else
                            Session active — no refresh timestamp yet
                        @endif
                    </p>
                </div>
                @else
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-danger/15">
                    <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-ink">Not connected</p>
                    <p class="text-xs text-muted">Save credentials and click Connect to PayGro</p>
                </div>
                @endif
            </div>

            @if($connection['username'])
            <span class="text-xs text-muted bg-surface px-3 py-1.5 rounded-lg border border-border">
                Account: <span class="text-ink font-medium">{{ $connection['username'] }}</span>
            </span>
            @endif

            @if($connection['account_email'])
            <span class="text-xs text-muted bg-surface px-3 py-1.5 rounded-lg border border-border">
                Email: <span class="text-ink font-medium">{{ $connection['account_email'] }}</span>
            </span>
            @endif

            @if($connection['first_sync_completed'])
            <span class="text-xs px-2.5 py-1 rounded-full bg-success/15 text-success font-medium">Initial sync completed</span>
            @else
            <span class="text-xs px-2.5 py-1 rounded-full bg-brand/15 text-brand font-medium">First sync required</span>
            @endif
        </div>
    </div>

    {{-- Credentials & connection --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-6 py-4 border-b border-border">
            <h2 class="text-base font-semibold text-ink">Account & connection</h2>
            <p class="text-xs text-muted mt-0.5">Credentials are encrypted. Login renews session cookies automatically.</p>
        </div>

        <form wire:submit="save" class="p-6 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1.5">PayGro base URL</label>
                    <input type="url" wire:model="paygro_base_url"
                           class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                    @error('paygro_base_url') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1.5">Distributor company ID</label>
                    <input type="number" min="1" wire:model="paygro_distributor_company_srl_no"
                           class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                    @error('paygro_distributor_company_srl_no') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1.5">Username</label>
                    <input type="text" wire:model="paygro_username" autocomplete="username"
                           class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500"
                           placeholder="PayGro user ID">
                    @error('paygro_username') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1.5">
                        Password
                        @if($connection['has_password'])
                        <span class="text-xs text-muted font-normal">(saved — leave blank to keep)</span>
                        @endif
                    </label>
                    <input type="password" wire:model="paygro_password" autocomplete="current-password"
                           class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500"
                           placeholder="{{ $connection['has_password'] ? '••••••••' : 'Enter password' }}">
                    @error('paygro_password') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" wire:loading.attr="disabled" class="btn-primary inline-flex items-center gap-2 text-sm disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">Save settings</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
                <button type="button" wire:click="connect" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-ink bg-surface border border-border rounded-xl hover:border-brand/30 transition-colors disabled:opacity-60">
                    <span wire:loading.remove wire:target="connect">Connect to PayGro</span>
                    <span wire:loading wire:target="connect">Connecting…</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Sync --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-6 py-4 border-b border-border">
            <h2 class="text-base font-semibold text-ink">
                @if(!$connection['first_sync_completed'])
                    Initial customer sync
                @else
                    Sync customers
                @endif
            </h2>
            <p class="text-xs text-muted mt-0.5">
                @if(!$connection['first_sync_completed'])
                    Select the date range for your first import from PayGro.
                @else
                    Pull customer updates for a date range. Scheduled syncs use the default range below.
                @endif
            </p>
        </div>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1.5">Sync start date</label>
                    <input type="date" wire:model="sync_start_date"
                           class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                    @error('sync_start_date') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1.5">Sync end date</label>
                    <input type="date" wire:model="sync_end_date"
                           class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                    @error('sync_end_date') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="button" wire:click="runSync" wire:loading.attr="disabled"
                    class="btn-primary inline-flex items-center gap-2 text-sm disabled:opacity-60">
                <span wire:loading.remove wire:target="runSync">
                    @if(!$connection['first_sync_completed'])
                        Run initial sync
                    @else
                        Run sync now
                    @endif
                </span>
                <span wire:loading wire:target="runSync">Syncing…</span>
            </button>
        </div>
    </div>

    {{-- Default range for scheduled sync --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-6 py-4 border-b border-border">
            <h2 class="text-base font-semibold text-ink">Scheduled sync defaults</h2>
            <p class="text-xs text-muted mt-0.5">Used by daily <code class="text-brand">paygro:sync</code> when no manual dates are chosen. Leave blank for rolling window (60 days back, 30 days ahead).</p>
        </div>
        <form wire:submit="save" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1.5">Default start date</label>
                    <input type="date" wire:model="paygro_report_start_date"
                           class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1.5">Default end date</label>
                    <input type="date" wire:model="paygro_report_end_date"
                           class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            <p class="text-xs text-muted mt-4">Save account settings above to apply default date changes.</p>
        </form>
    </div>

    @if($recentSyncs->isNotEmpty())
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-6 py-4 border-b border-border">
            <h2 class="text-base font-semibold text-ink">Recent sync logs</h2>
            <p class="text-xs text-muted mt-0.5">Last {{ $recentSyncs->count() }} runs, newest first.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Date</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Status</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden sm:table-cell">Source</th>
                        <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Processed</th>
                        <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Failed</th>
                        <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">Duration</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @foreach($recentSyncs as $log)
                    <tr class="hover:bg-surface transition-colors">
                        <td class="px-4 py-3 text-sm text-ink/80 whitespace-nowrap">
                            {{ $log->started_at?->format('M j, Y H:i') ?? $log->created_at->format('M j, Y H:i') }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $log->status === 'completed' ? 'bg-success/15 text-success' : ($log->status === 'failed' ? 'bg-danger/15 text-danger' : 'bg-warning/15 text-warning') }}">
                                    {{ ucfirst($log->status) }}
                                </span>
                                @if($log->session_refreshed)
                                <span class="text-[10px] text-info bg-info/10 px-1.5 py-0.5 rounded-full" title="Session was refreshed during this run">⟳ session</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-muted capitalize hidden sm:table-cell">{{ $log->source ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($log->records_processed) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums {{ $log->records_failed > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($log->records_failed) }}</td>
                        <td class="px-4 py-3 text-right text-xs text-muted hidden md:table-cell tabular-nums">
                            @if($log->duration_ms !== null)
                                {{ $log->duration_ms < 1000 ? $log->duration_ms.'ms' : number_format($log->duration_ms / 1000, 1).'s' }}
                            @else —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-muted hidden lg:table-cell max-w-xs">
                            @if($log->error_message)
                            <span class="text-danger">{{ Str::limit($log->error_message, 80) }}</span>
                            @elseif($log->payload['fetch_source'] ?? null)
                            via {{ $log->payload['fetch_source'] }}
                            @else —
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
