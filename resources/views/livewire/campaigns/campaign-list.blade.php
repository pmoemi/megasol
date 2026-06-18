<div class="space-y-6">
    {{-- Flash --}}
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center justify-between">
        <span>{{ session('success') }}</span>
        <button type="button" @click="show = false" class="text-success hover:opacity-70">&times;</button>
    </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">Campaigns</h1>
            <p class="text-sm text-muted mt-0.5">Create, manage, and track your SMS &amp; email campaigns</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            {{-- Search --}}
            <div class="relative">
                <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search campaigns..."
                       class="pl-9 pr-4 py-2 text-sm bg-surface-2 border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent placeholder-muted/60 w-56">
            </div>

            {{-- Templates link --}}
            <a href="{{ route('email-templates.index') }}" wire:navigate
               class="flex items-center gap-2 px-4 py-2 bg-surface-2 border border-border text-ink text-sm font-medium rounded-xl hover:border-brand/30 hover:text-brand transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Templates
            </a>

            {{-- Create campaign --}}
            <a href="{{ route('campaigns.create') }}" wire:navigate
               class="btn-primary flex items-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Campaign
            </a>
        </div>
    </div>

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface-2 rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">Total Campaigns</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ number_format($totalCampaigns) }}</p>
        </div>
        <div class="bg-surface-2 rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">Sent</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ number_format($totalSent) }}</p>
        </div>
        <div class="bg-surface-2 rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">Drafts</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ number_format($draftCount) }}</p>
        </div>
        <div class="bg-surface-2 rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">Scheduled</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ number_format($scheduledCount) }}</p>
        </div>
    </div>

    {{-- Status tabs --}}
    <div class="border-b border-border">
        <nav class="flex gap-6 -mb-px">
            @foreach(['all' => 'All Campaigns', 'draft' => 'Drafts', 'scheduled' => 'Scheduled', 'sent' => 'Sent'] as $key => $label)
            <button type="button" wire:click="$set('activeTab', '{{ $key }}')"
                    class="pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap {{ $activeTab === $key ? 'border-primary-600 text-primary-700 dark:text-primary-300' : 'border-transparent text-muted hover:text-ink/80 hover:border-border' }}">
                {{ $label }}
                @if($key === 'draft' && $draftCount > 0)
                <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-surface text-muted">{{ $draftCount }}</span>
                @elseif($key === 'scheduled' && $scheduledCount > 0)
                <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-info/15 text-info">{{ $scheduledCount }}</span>
                @elseif($key === 'sent' && $sentCount > 0)
                <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-success/15 text-success">{{ $sentCount }}</span>
                @endif
            </button>
            @endforeach
        </nav>
    </div>

    {{-- Bulk action bar --}}
    @if(count($selectedCampaigns) > 0)
    <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <span class="text-sm text-primary-700 dark:text-primary-300 font-medium">{{ count($selectedCampaigns) }} campaign(s) selected</span>
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" wire:click="bulkDuplicateCampaigns"
                    wire:loading.attr="disabled"
                    class="px-3 py-1.5 text-sm text-muted bg-surface-2 border border-border rounded-lg hover:bg-surface transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="bulkDuplicateCampaigns">Duplicate</span>
                <span wire:loading wire:target="bulkDuplicateCampaigns" class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Duplicating...
                </span>
            </button>
            <button type="button" wire:click="bulkDeleteCampaigns"
                    wire:confirm="Delete {{ count($selectedCampaigns) }} campaign(s)? Only draft and completed campaigns will be deleted."
                    wire:loading.attr="disabled"
                    class="px-3 py-1.5 text-sm text-danger bg-surface-2 border border-danger/20 rounded-lg hover:bg-danger/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="bulkDeleteCampaigns">Delete</span>
                <span wire:loading wire:target="bulkDeleteCampaigns" class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Deleting...
                </span>
            </button>
            <button type="button" wire:click="$set('selectedCampaigns', [])" class="px-3 py-1.5 text-sm text-muted hover:text-ink transition-colors">
                Clear
            </button>
        </div>
    </div>
    @endif

    {{-- Loading --}}
    <div wire:loading.delay class="text-center py-2" aria-busy="true">
        <div class="inline-flex items-center gap-2 text-sm text-muted">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            Loading...
        </div>
    </div>

    {{-- Campaign cards --}}
    <div class="space-y-4">
        @if($campaigns->count() > 0)
        <div class="flex items-center gap-2 px-1">
            <input type="checkbox" wire:model.live="selectAll" wire:change="toggleSelectAll"
                   aria-label="Select all campaigns"
                   class="w-4 h-4 text-primary-600 border-border rounded focus:ring-primary-500">
            <span class="text-xs text-muted font-medium">Select all on this page</span>
        </div>
        @endif

        @forelse($campaigns as $campaign)
        @php
            $statusColors = [
                'draft'     => 'bg-surface text-muted',
                'scheduled' => 'bg-info/15 text-info',
                'sending'   => 'bg-warning/15 text-warning',
                'sent'      => 'bg-success/15 text-success',
                'paused'    => 'bg-orange-100 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400',
                'canceled'  => 'bg-danger/15 text-danger',
                'cancelled' => 'bg-danger/15 text-danger',
                'failed'    => 'bg-danger/15 text-danger',
            ];
            $statusColor = $statusColors[$campaign->status] ?? 'bg-surface text-muted';
            $isSms = ($campaign->channel ?? 'sms') === 'sms';
        @endphp
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden hover:shadow-sm transition-shadow {{ in_array((string) $campaign->id, $selectedCampaigns) ? 'ring-2 ring-primary-300 dark:ring-primary-700' : '' }}"
             wire:key="campaign-{{ $campaign->id }}">
            <div class="p-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    {{-- Left: info --}}
                    <div class="flex-1 min-w-0 flex items-start gap-3">
                        <div class="pt-1 shrink-0">
                            <input type="checkbox" value="{{ $campaign->id }}" wire:model.live="selectedCampaigns"
                                   aria-label="Select campaign"
                                   class="w-4 h-4 text-primary-600 border-border rounded focus:ring-primary-500">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <h3 class="text-base font-semibold text-ink">{{ $campaign->name }}</h3>

                                {{-- Status badge --}}
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                    {{ ucfirst($campaign->status) }}
                                </span>

                                {{-- Channel badge --}}
                                @if($isSms)
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-success/15 text-success inline-flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    SMS
                                </span>
                                @else
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-info/15 text-info inline-flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    Email
                                </span>
                                @endif
                            </div>

                            {{-- Preview text --}}
                            @if($isSms && $campaign->body)
                            <p class="text-sm text-muted mb-1">{{ \Illuminate\Support\Str::limit($campaign->body, 100) }}</p>
                            @elseif(!$isSms && $campaign->subject)
                            <p class="text-sm text-muted mb-1">{{ $campaign->subject }}</p>
                            @endif

                            {{-- Meta row --}}
                            <div class="flex items-center gap-4 text-xs text-muted mt-1 flex-wrap">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                    {{ number_format($campaign->recipients_count) }} recipients
                                </span>
                                <span>Created {{ $campaign->created_at->format('M j, Y') }}</span>
                                @if($campaign->scheduled_at && $campaign->status === 'scheduled')
                                <span class="hidden sm:inline">Scheduled: {{ $campaign->scheduled_at->format('M j, Y \a\t g:i A') }}</span>
                                @elseif($campaign->sent_at)
                                <span class="hidden sm:inline">Sent {{ $campaign->sent_at->format('M j, Y \a\t g:i A') }}</span>
                                @endif
                                @if($campaign->creator)
                                <span>By {{ $campaign->creator->name }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Right: action buttons --}}
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($campaign->status === 'draft')
                        <a href="{{ route('campaigns.edit', $campaign->id) }}" wire:navigate
                           class="px-3 py-1.5 text-sm text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/20 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/30 font-medium transition-colors">
                            Edit
                        </a>
                        @elseif($campaign->status === 'scheduled')
                        <a href="{{ route('campaigns.edit', $campaign->id) }}" wire:navigate
                           class="px-3 py-1.5 text-sm text-muted bg-surface rounded-lg hover:bg-surface font-medium transition-colors">
                            Edit
                        </a>
                        @elseif(in_array($campaign->status, ['sent', 'sending']))
                        <a href="{{ route('campaigns.report', $campaign->id) }}" wire:navigate
                           class="px-3 py-1.5 text-sm text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/20 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/30 font-medium transition-colors">
                            View Report
                        </a>
                        @endif

                        {{-- 3-dot overflow menu --}}
                        <div x-data="{
                                open: false, menuTop: 0, menuLeft: 0,
                                reposition() { const r = this.$refs.trigger.getBoundingClientRect(); this.menuLeft = r.right - 160; this.menuTop = r.bottom + 4; },
                                toggle() { this.open = !this.open; if (this.open) this.$nextTick(() => this.reposition()); },
                             }"
                             @scroll.window="open && reposition()" @resize.window="open && reposition()" @keydown.escape.window="open = false">
                            <button type="button" x-ref="trigger" @click="toggle()" class="p-1.5 text-muted/60 hover:text-ink rounded-lg hover:bg-surface transition-colors" aria-label="More actions" aria-haspopup="true" :aria-expanded="open">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01"/></svg>
                            </button>
                            <template x-teleport="body">
                                <div x-show="open" @click.outside="open = false" x-transition
                                     role="menu"
                                     :style="`position: fixed; top: ${menuTop}px; left: ${menuLeft}px; z-index: 9999;`"
                                     class="w-44 bg-surface-2 rounded-xl shadow-lg border border-border py-1" style="display: none;">
                                    <a href="{{ route('campaigns.report', $campaign->id) }}" wire:navigate role="menuitem" @click="open = false"
                                       class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-ink/80 hover:bg-surface">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                        View Report
                                    </a>
                                    <button type="button" role="menuitem" @click="open = false"
                                            wire:click="duplicateCampaign({{ $campaign->id }})"
                                            class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-ink/80 hover:bg-surface">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        Duplicate
                                    </button>
                                    <button type="button" role="menuitem" @click="open = false"
                                            wire:click="deleteCampaign({{ $campaign->id }})"
                                            wire:confirm="Are you sure you want to delete this campaign?"
                                            class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-danger hover:bg-danger/10">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        Delete
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Stats bar (sent / sending campaigns) --}}
                @if(in_array($campaign->status, ['sent', 'sending']))
                <div class="mt-4 pt-4 border-t border-border">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs text-muted uppercase font-medium">Sent</p>
                            <p class="text-lg font-bold text-ink mt-0.5">{{ number_format($campaign->sent_count) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted uppercase font-medium">Delivered</p>
                            <div class="flex items-baseline gap-1.5 mt-0.5">
                                <p class="text-lg font-bold text-ink">{{ number_format($campaign->delivered_count) }}</p>
                                <span class="text-xs font-medium text-success">{{ $campaign->delivery_rate }}%</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs text-muted uppercase font-medium">Delivery Rate</p>
                            <div class="flex items-center gap-2 mt-1.5">
                                <div class="flex-1 h-1.5 bg-surface rounded-full overflow-hidden">
                                    <div class="h-full bg-success/100 rounded-full" style="width: {{ $campaign->delivery_rate }}%"></div>
                                </div>
                                <span class="text-xs font-medium text-muted">{{ $campaign->delivery_rate }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @empty
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <div class="w-16 h-16 bg-surface-2 rounded-2xl flex items-center justify-center mb-4 border border-border">
                <svg class="w-8 h-8 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            @if($search || $activeTab !== 'all')
                <h3 class="text-base font-semibold text-ink mb-1">No campaigns found</h3>
                <p class="text-sm text-muted mb-4">No campaigns match your current filters.</p>
                <button type="button"
                        wire:click="$set('search', ''); $set('activeTab', 'all')"
                        class="px-4 py-2 text-sm text-brand border border-brand/30 rounded-xl hover:bg-brand/5 transition-colors">
                    Clear filters
                </button>
            @else
                <h3 class="text-base font-semibold text-ink mb-1">No campaigns yet</h3>
                <p class="text-sm text-muted mb-4">Create your first campaign to start engaging your audience.</p>
                <a href="{{ route('campaigns.create') }}" wire:navigate
                   class="btn-primary text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Campaign
                </a>
            @endif
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($campaigns->hasPages())
    <div class="mt-4">
        {{ $campaigns->links() }}
    </div>
    @endif
</div>
