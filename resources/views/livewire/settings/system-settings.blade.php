<div class="space-y-6">

    @if($statusMessage)
    <div x-data="{ show: true }" x-show="show" x-transition
         class="flex items-start gap-3 px-4 py-3 border rounded-xl text-sm {{ $statusIsError ? 'bg-danger/10 border-danger/20 text-danger' : 'bg-success/10 border-success/20 text-success' }}">
        <span>{{ $statusMessage }}</span>
        <button type="button" @click="show = false" class="ml-auto opacity-60 hover:opacity-100">&times;</button>
    </div>
    @endif

    <div>
        <h1 class="text-2xl font-bold text-ink">System</h1>
        <p class="text-sm text-muted mt-1">Database maintenance, cache tools, and server cron jobs for production when shell access is unavailable.</p>
    </div>

    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden" x-data="{ copied: null }" wire:poll.60s="refreshCronHealth">
        <div class="px-6 py-4 border-b border-border flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-ink">Cron jobs (cPanel)</h2>
                <p class="text-xs text-muted mt-0.5">
                    Copy into cPanel → <strong class="font-medium text-ink/80">Cron Jobs</strong> → <strong class="font-medium text-ink/80">Once Per Minute</strong>, then paste the command below.
                    Each subdomain needs its own cron — do not reuse another app’s path (e.g. CRM vs MegaSol).
                </p>
            </div>
            @php
                $cronState = $cronHealth['state'] ?? 'unknown';
                $cronBadge = match ($cronState) {
                    'ok' => ['label' => 'Cron running', 'class' => 'bg-success/15 text-success'],
                    'warning' => ['label' => 'Cron delayed', 'class' => 'bg-warning/15 text-warning'],
                    'error' => ['label' => 'Cron not detected', 'class' => 'bg-danger/15 text-danger'],
                    default => ['label' => 'Awaiting first run', 'class' => 'bg-surface text-muted'],
                };
            @endphp
            <span class="text-xs px-2.5 py-1 rounded-full font-medium {{ $cronBadge['class'] }}">
                {{ $cronBadge['label'] }}
            </span>
        </div>

        <div class="p-6 space-y-5">
            <div class="rounded-xl border border-border bg-surface/40 p-4 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-ink">Scheduler health</p>
                        <p class="text-xs text-muted mt-0.5">{{ $cronHealth['message'] ?? 'Checking scheduler status…' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button"
                                wire:click="refreshCronHealth"
                                wire:loading.attr="disabled"
                                wire:target="refreshCronHealth,runSchedulerNow"
                                class="inline-flex items-center px-3.5 py-2 text-sm font-medium text-ink/80 bg-surface border border-border rounded-xl hover:bg-surface-2 transition-colors">
                            <span wire:loading.remove wire:target="refreshCronHealth">Refresh</span>
                            <span wire:loading wire:target="refreshCronHealth">Refreshing…</span>
                        </button>
                        <button type="button"
                                wire:click="runSchedulerNow"
                                wire:loading.attr="disabled"
                                wire:target="refreshCronHealth,runSchedulerNow"
                                class="inline-flex items-center px-3.5 py-2 text-sm font-medium text-ink/80 bg-surface border border-border rounded-xl hover:bg-surface-2 transition-colors">
                            <span wire:loading.remove wire:target="runSchedulerNow">Run scheduler now</span>
                            <span wire:loading wire:target="runSchedulerNow">Running…</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-border bg-surface px-4 py-3">
                        <p class="text-xs text-muted uppercase tracking-wide">Last scheduler tick</p>
                        <p class="text-sm font-semibold text-ink mt-1">{{ $cronHealth['last_run_human'] ?? 'Never' }}</p>
                        @if(! empty($cronHealth['last_run_at']))
                        <p class="text-xs font-mono text-muted mt-1">{{ $cronHealth['last_run_at'] }}</p>
                        @endif
                    </div>
                    <div class="rounded-xl border border-border bg-surface px-4 py-3">
                        <p class="text-xs text-muted uppercase tracking-wide">Expected cadence</p>
                        <p class="text-sm font-semibold text-ink mt-1">Every minute via cPanel cron</p>
                        <p class="text-xs text-muted mt-1">Healthy when the last tick was within {{ $cronHealth['heartbeat_ok_minutes'] ?? 3 }} minutes.</p>
                    </div>
                </div>

                @if(! empty($cronHealth['tasks']))
                <div>
                    <p class="text-sm font-medium text-ink mb-2">Scheduled task activity</p>
                    <ul class="rounded-xl border border-border divide-y divide-border/60">
                        @foreach($cronHealth['tasks'] as $task)
                        @php
                            $taskState = $task['state'] ?? 'unknown';
                            $taskBadge = match ($taskState) {
                                'ok' => 'bg-success/15 text-success',
                                'warning' => 'bg-warning/15 text-warning',
                                default => 'bg-surface text-muted',
                            };
                        @endphp
                        <li class="px-4 py-3 space-y-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium text-ink">{{ $task['label'] }}</p>
                                    <code class="text-xs font-mono text-muted">{{ $task['command'] }}</code>
                                </div>
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-medium {{ $taskBadge }}">
                                    {{ ucfirst($taskState) }}
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
                                <span>{{ $task['message'] ?? '' }}</span>
                                <span>{{ $task['frequency'] ?? '' }}</span>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="rounded-xl border border-border bg-surface/40 px-4 py-3">
                    <p class="text-xs text-muted uppercase tracking-wide">App URL</p>
                    <p class="text-sm font-mono text-ink mt-1 break-all">{{ $appUrl ?: '—' }}</p>
                </div>
                <div class="rounded-xl border border-border bg-surface/40 px-4 py-3">
                    <p class="text-xs text-muted uppercase tracking-wide">Artisan path</p>
                    <p class="text-sm font-mono text-ink mt-1 break-all">{{ $artisanPath }}</p>
                </div>
                <div class="rounded-xl border border-border bg-surface/40 px-4 py-3">
                    <p class="text-xs text-muted uppercase tracking-wide">Queue driver</p>
                    <p class="text-sm font-mono text-ink mt-1">{{ $queueConnection }}</p>
                    @if($queueConnection === 'sync')
                    <p class="text-xs text-muted mt-1">SMS sends immediately — no queue worker cron needed.</p>
                    @endif
                </div>
            </div>

            <div class="space-y-4">
                @foreach($cronJobs as $index => $job)
                <div class="rounded-xl border border-border bg-surface/40 p-4 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-ink">{{ $job['label'] }}</p>
                        @if($job['required'])
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-brand/15 text-brand font-medium uppercase tracking-wide">Required</span>
                        @endif
                        <span class="text-xs font-mono text-muted">{{ $job['schedule'] }}</span>
                    </div>
                    <p class="text-xs text-muted">{{ $job['description'] }}</p>
                    <div class="space-y-2">
                        <div>
                            <p class="text-[10px] font-semibold text-muted uppercase tracking-wider mb-1">cPanel command (paste this)</p>
                            <div class="flex flex-col sm:flex-row gap-2">
                                <input type="text"
                                       readonly
                                       value="{{ $job['shell_command'] }}"
                                       class="input flex-1 font-mono text-xs text-ink/80 bg-surface"
                                       onclick="this.select()">
                                <button type="button"
                                        @click="navigator.clipboard.writeText(@js($job['shell_command'])); copied = {{ $index }}; setTimeout(() => copied = null, 2000)"
                                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-sm font-medium text-ink/80 bg-surface border border-border rounded-xl hover:bg-surface-2 transition-colors shrink-0">
                                    <span x-show="copied !== {{ $index }}">Copy</span>
                                    <span x-show="copied === {{ $index }}" x-cloak class="text-success">Copied!</span>
                                </button>
                            </div>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold text-muted uppercase tracking-wider mb-1">Full cron line (includes schedule)</p>
                            <input type="text"
                                   readonly
                                   value="{{ $job['command'] }}"
                                   class="input w-full font-mono text-xs text-ink/60 bg-surface"
                                   onclick="this.select()">
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div>
                <p class="text-sm font-medium text-ink mb-2">Tasks run by the scheduler</p>
                <ul class="rounded-xl border border-border divide-y divide-border/60">
                    @foreach($scheduledTasks as $task)
                    <li class="px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <code class="font-mono text-xs text-ink/80">{{ $task['command'] }}</code>
                        <span class="text-xs text-muted">{{ $task['frequency'] }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>

            <p class="text-xs text-muted">
                If cPanel rejects the PHP path, try <code class="font-mono text-ink/70">/usr/local/bin/php</code> or select PHP 8.3+ in cPanel → Select PHP Version and use the path shown there.
            </p>
        </div>
    </div>

    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-ink">Database migrations</h2>
                <p class="text-xs text-muted mt-0.5">Applies pending Laravel migrations (<code class="text-brand">php artisan migrate --force</code>).</p>
            </div>
            @if(($migrationStatus['pending_count'] ?? 0) > 0)
            <span class="text-xs px-2.5 py-1 rounded-full bg-warning/15 text-warning font-medium">
                {{ $migrationStatus['pending_count'] }} pending
            </span>
            @else
            <span class="text-xs px-2.5 py-1 rounded-full bg-success/15 text-success font-medium">Up to date</span>
            @endif
        </div>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="rounded-xl border border-border bg-surface/40 px-4 py-3">
                    <p class="text-xs text-muted uppercase tracking-wide">Applied</p>
                    <p class="text-2xl font-bold text-ink mt-1">{{ $migrationStatus['ran_count'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-border bg-surface/40 px-4 py-3">
                    <p class="text-xs text-muted uppercase tracking-wide">Pending</p>
                    <p class="text-2xl font-bold text-ink mt-1">{{ $migrationStatus['pending_count'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-border bg-surface/40 px-4 py-3">
                    <p class="text-xs text-muted uppercase tracking-wide">Last batch</p>
                    <p class="text-2xl font-bold text-ink mt-1">{{ $migrationStatus['last_batch'] ?? '—' }}</p>
                </div>
            </div>

            @if(! $webMigrationsEnabled)
            <div class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                Web migrations are disabled on this server. Set <code class="font-mono text-xs">ALLOW_WEB_MIGRATIONS=true</code> in <code class="font-mono text-xs">.env</code> or run migrations via SSH.
            </div>
            @endif

            @if(! empty($migrationStatus['pending']))
            <div>
                <p class="text-sm font-medium text-ink mb-2">Pending migrations</p>
                <ul class="rounded-xl border border-border divide-y divide-border/60 max-h-56 overflow-y-auto">
                    @foreach($migrationStatus['pending'] as $migration)
                    <li class="px-4 py-2.5 text-sm font-mono text-muted">{{ $migration }}</li>
                    @endforeach
                </ul>
            </div>
            @else
            <p class="text-sm text-muted">No pending migrations. Your database schema matches the application code.</p>
            @endif

            @if($migrationOutput)
            <div>
                <p class="text-sm font-medium text-ink mb-2">Last run output</p>
                <pre class="rounded-xl border border-border bg-surface px-4 py-3 text-xs font-mono text-muted whitespace-pre-wrap overflow-x-auto">{{ $migrationOutput }}</pre>
            </div>
            @endif
        </div>

        <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-border bg-surface/40">
            <p class="text-xs text-muted">Only runs pending migrations. Rollbacks are not available from the UI.</p>
            <button type="button"
                    wire:click="runMigrations"
                    wire:loading.attr="disabled"
                    wire:target="runMigrations"
                    wire:confirm="Run all pending database migrations now?"
                    @disabled(! $webMigrationsEnabled || ($migrationStatus['pending_count'] ?? 0) === 0)
                    class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="runMigrations">Run migrations</span>
                <span wire:loading wire:target="runMigrations">Running…</span>
            </button>
        </div>
    </div>

    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-ink">Application cache</h2>
                <p class="text-xs text-muted mt-0.5">Clear stale compiled views and Laravel caches after uploading code changes.</p>
            </div>
            @if(($cacheStatus['compiled_views'] ?? 0) > 0 || ($cacheStatus['config_cached'] ?? false) || ($cacheStatus['routes_cached'] ?? false))
            <span class="text-xs px-2.5 py-1 rounded-full bg-warning/15 text-warning font-medium">Cache active</span>
            @else
            <span class="text-xs px-2.5 py-1 rounded-full bg-success/15 text-success font-medium">Fresh</span>
            @endif
        </div>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="rounded-xl border border-border bg-surface/40 px-4 py-3">
                    <p class="text-xs text-muted uppercase tracking-wide">Compiled views</p>
                    <p class="text-2xl font-bold text-ink mt-1">{{ $cacheStatus['compiled_views'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-border bg-surface/40 px-4 py-3">
                    <p class="text-xs text-muted uppercase tracking-wide">Config cached</p>
                    <p class="text-2xl font-bold text-ink mt-1">{{ ($cacheStatus['config_cached'] ?? false) ? 'Yes' : 'No' }}</p>
                </div>
                <div class="rounded-xl border border-border bg-surface/40 px-4 py-3">
                    <p class="text-xs text-muted uppercase tracking-wide">Routes cached</p>
                    <p class="text-2xl font-bold text-ink mt-1">{{ ($cacheStatus['routes_cached'] ?? false) ? 'Yes' : 'No' }}</p>
                </div>
                <div class="rounded-xl border border-border bg-surface/40 px-4 py-3">
                    <p class="text-xs text-muted uppercase tracking-wide">Events cached</p>
                    <p class="text-2xl font-bold text-ink mt-1">{{ ($cacheStatus['events_cached'] ?? false) ? 'Yes' : 'No' }}</p>
                </div>
            </div>

            @if(! $webCacheClearEnabled)
            <div class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                Web cache clearing is disabled. Set <code class="font-mono text-xs">ALLOW_WEB_CACHE_CLEAR=true</code> in <code class="font-mono text-xs">.env</code> or run artisan commands via SSH.
            </div>
            @else
            <p class="text-sm text-muted">
                If UI changes do not appear after deploy, clear <strong class="font-medium text-ink">All caches</strong> or at minimum <strong class="font-medium text-ink">Compiled views</strong>.
            </p>
            @endif

            @if($cacheOutput)
            <div>
                <p class="text-sm font-medium text-ink mb-2">Last clear output</p>
                <pre class="rounded-xl border border-border bg-surface px-4 py-3 text-xs font-mono text-muted whitespace-pre-wrap overflow-x-auto">{{ $cacheOutput }}</pre>
            </div>
            @endif
        </div>

        <div class="px-6 py-4 border-t border-border bg-surface/40 space-y-3">
            <div class="flex flex-wrap gap-2">
                <button type="button"
                        wire:click="clearCache('all')"
                        wire:loading.attr="disabled"
                        wire:target="clearCache"
                        wire:confirm="Clear all application caches now?"
                        @disabled(! $webCacheClearEnabled)
                        class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="clearCache">Clear all caches</span>
                    <span wire:loading wire:target="clearCache">Clearing…</span>
                </button>
                <button type="button"
                        wire:click="clearCache('view')"
                        wire:loading.attr="disabled"
                        wire:target="clearCache"
                        @disabled(! $webCacheClearEnabled)
                        class="inline-flex items-center px-3.5 py-2 text-sm font-medium text-ink/80 bg-surface border border-border rounded-xl hover:bg-surface-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    Compiled views
                </button>
                <button type="button"
                        wire:click="clearCache('config')"
                        wire:loading.attr="disabled"
                        wire:target="clearCache"
                        @disabled(! $webCacheClearEnabled)
                        class="inline-flex items-center px-3.5 py-2 text-sm font-medium text-ink/80 bg-surface border border-border rounded-xl hover:bg-surface-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    Config
                </button>
                <button type="button"
                        wire:click="clearCache('route')"
                        wire:loading.attr="disabled"
                        wire:target="clearCache"
                        @disabled(! $webCacheClearEnabled)
                        class="inline-flex items-center px-3.5 py-2 text-sm font-medium text-ink/80 bg-surface border border-border rounded-xl hover:bg-surface-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    Routes
                </button>
                <button type="button"
                        wire:click="clearCache('application')"
                        wire:loading.attr="disabled"
                        wire:target="clearCache"
                        @disabled(! $webCacheClearEnabled)
                        class="inline-flex items-center px-3.5 py-2 text-sm font-medium text-ink/80 bg-surface border border-border rounded-xl hover:bg-surface-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    Application
                </button>
            </div>
            <p class="text-xs text-muted">Equivalent to <code class="font-mono">php artisan optimize:clear</code> and individual clear commands.</p>
        </div>
    </div>
</div>
