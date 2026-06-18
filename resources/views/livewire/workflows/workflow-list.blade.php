<div class="space-y-6">

    {{-- Flash --}}
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition
         class="flex items-center gap-3 px-4 py-3 bg-success/10 border border-success/20 rounded-xl text-sm text-success">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>{{ session('success') }}</span>
        <button type="button" @click="show = false" class="ml-auto text-success/70 hover:text-success">&times;</button>
    </div>
    @endif

    {{-- Page header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">Workflow Automation</h1>
            <p class="text-sm text-muted mt-1">Automate repetitive tasks with visual, no-code workflows.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('workflows.create') }}?guided=1" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-ink/80 bg-surface-2 border border-border rounded-xl hover:bg-surface hover:border-brand/30 transition-colors">
                <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-14 9V3z"/></svg>
                Start from Template
            </a>
            <a href="{{ route('workflows.create') }}" wire:navigate
               class="btn-primary inline-flex items-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Workflow
            </a>
        </div>
    </div>

    {{-- Main card --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5">

        {{-- Filters --}}
        <div class="flex items-center gap-3 flex-wrap">
            {{-- Search --}}
            <div class="relative flex-1 min-w-[200px] max-w-sm">
                <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search workflows..."
                       class="w-full pl-9 pr-4 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent placeholder-muted/60">
            </div>

            {{-- Status filter --}}
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-3 py-2 border border-border text-sm font-medium rounded-xl text-ink/80 bg-surface-2 hover:bg-surface transition-colors whitespace-nowrap"
                        aria-haspopup="true" :aria-expanded="open">
                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                    {{ $statusFilter === 'all' ? 'All Statuses' : ucfirst($statusFilter) }}
                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" @click.away="open = false" x-transition
                     class="absolute left-0 top-full mt-1 z-50 w-40 bg-surface-2 border border-border rounded-xl shadow-lg py-1" style="display: none;">
                    @foreach(['all' => 'All Statuses', 'active' => 'Active', 'inactive' => 'Inactive'] as $val => $label)
                    <button type="button" wire:click="$set('statusFilter', '{{ $val }}')" @click="open = false"
                            class="w-full text-left px-4 py-2 text-sm transition-colors hover:bg-surface {{ $statusFilter === $val ? 'bg-surface font-semibold text-ink' : 'text-ink/80' }}">
                        {{ $label }}
                    </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Loading --}}
        <div wire:loading.delay class="text-center py-2">
            <div class="inline-flex items-center gap-2 text-sm text-muted">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Loading...
            </div>
        </div>

        @if($workflows->count() > 0)
        {{-- Table --}}
        <div class="rounded-xl border border-border overflow-hidden">
            <p class="text-xs text-muted text-center py-1 md:hidden">Swipe to see more columns</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-surface border-b border-border">
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Name</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Status</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">Trigger</th>
                            <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell">Success Rate</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell">Last Run</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden xl:table-cell">Created</th>
                            <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @foreach($workflows as $workflow)
                        @php
                        $statusConfig = [
                            'active'   => ['dot' => 'bg-success/100', 'text' => 'text-success',  'bg' => 'bg-success/10',  'label' => 'Active'],
                            'inactive' => ['dot' => 'bg-gray-400',    'text' => 'text-muted',     'bg' => 'bg-surface',     'label' => 'Inactive'],
                        ];
                        $sc = $statusConfig[$workflow->status] ?? $statusConfig['inactive'];

                        $triggerIcons = [
                            'payment_due'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                            'payment_overdue'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                            'customer_created' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>',
                            'manual'           => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/>',
                            'schedule'         => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                            'sms_reply'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>',
                        ];
                        $triggerSvg = $triggerIcons[$workflow->trigger_subtype] ?? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/>';
                        @endphp
                        <tr class="hover:bg-surface transition-colors" wire:key="workflow-{{ $workflow->id }}">
                            {{-- Name --}}
                            <td class="px-4 py-3">
                                <a href="{{ route('workflows.edit', $workflow->id) }}" wire:navigate
                                   class="font-semibold text-ink hover:text-brand transition-colors">
                                    {{ $workflow->name }}
                                </a>
                                @if($workflow->description)
                                <p class="text-xs text-muted mt-0.5 max-w-[260px] truncate">{{ $workflow->description }}</p>
                                @elseif(!empty($workflow->definition['description']))
                                <p class="text-xs text-muted mt-0.5 max-w-[260px] truncate">{{ $workflow->definition['description'] }}</p>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sc['bg'] }} {{ $sc['text'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $sc['dot'] }}"></span>
                                    {{ $sc['label'] }}
                                </span>
                            </td>

                            {{-- Trigger --}}
                            <td class="px-4 py-3 hidden md:table-cell">
                                <div class="flex items-center gap-2 text-muted">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $triggerSvg !!}</svg>
                                    <span class="text-xs capitalize">{{ str_replace('_', ' ', $workflow->trigger_subtype) }}</span>
                                </div>
                            </td>

                            {{-- Success rate --}}
                            <td class="px-4 py-3 text-right tabular-nums text-ink/80 hidden lg:table-cell">
                                {{ $workflow->success_rate }}%
                            </td>

                            {{-- Last run --}}
                            <td class="px-4 py-3 text-muted text-xs hidden lg:table-cell">
                                @if($workflow->last_run_at)
                                <span title="{{ $workflow->last_run_at->format('M j, Y g:i A') }}">{{ $workflow->last_run_at->diffForHumans() }}</span>
                                @else
                                <span class="text-muted/50">Never</span>
                                @endif
                            </td>

                            {{-- Created --}}
                            <td class="px-4 py-3 text-muted text-xs hidden xl:table-cell">
                                {{ $workflow->created_at->format('M j, Y') }}
                            </td>

                            {{-- Actions dropdown --}}
                            <td class="px-4 py-3 text-right">
                                <div x-data="{
                                        open: false,
                                        menuTop: 0, menuLeft: 0,
                                        reposition() { const r = this.$refs.trigger.getBoundingClientRect(); this.menuLeft = r.right - 176; this.menuTop = r.bottom + 4; },
                                        toggle() { this.open = !this.open; if (this.open) this.$nextTick(() => this.reposition()); },
                                    }"
                                     @scroll.window="open && reposition()"
                                     @resize.window="open && reposition()"
                                     @keydown.escape.window="open = false">
                                    <button type="button" x-ref="trigger" @click="toggle()"
                                            class="p-1.5 text-muted/60 hover:text-ink rounded-lg hover:bg-surface transition-colors"
                                            aria-label="More actions" aria-haspopup="true" :aria-expanded="open">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01"/></svg>
                                    </button>
                                    <template x-teleport="body">
                                        <div x-show="open" @click.outside="open = false" x-transition
                                             role="menu"
                                             :style="`position: fixed; top: ${menuTop}px; left: ${menuLeft}px; z-index: 9999;`"
                                             class="w-44 bg-surface-2 border border-border rounded-xl shadow-lg py-1" style="display: none;">
                                            <a href="{{ route('workflows.edit', $workflow->id) }}" wire:navigate role="menuitem" @click="open = false"
                                               class="flex items-center gap-2 px-4 py-2 text-sm text-ink/80 hover:bg-surface transition-colors w-full">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                Edit
                                            </a>
                                            <button type="button" role="menuitem" wire:click="duplicateWorkflow({{ $workflow->id }})" @click="open = false"
                                                    class="flex items-center gap-2 px-4 py-2 text-sm text-ink/80 hover:bg-surface transition-colors w-full text-left">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                Duplicate
                                            </button>
                                            <button type="button" role="menuitem" wire:click="toggleStatus({{ $workflow->id }})" @click="open = false"
                                                    class="flex items-center gap-2 px-4 py-2 text-sm text-ink/80 hover:bg-surface transition-colors w-full text-left">
                                                @if($workflow->is_active)
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                Pause
                                                @else
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                Activate
                                                @endif
                                            </button>
                                            <div class="border-t border-border my-1"></div>
                                            <button type="button" role="menuitem"
                                                    wire:click="deleteWorkflow({{ $workflow->id }})"
                                                    wire:confirm="Delete {{ $workflow->name }}? This cannot be undone."
                                                    @click="open = false"
                                                    class="flex items-center gap-2 px-4 py-2 text-sm text-danger hover:bg-danger/10 transition-colors w-full text-left">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                Delete
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        @if($workflows->hasPages())
        <div class="pt-2">{{ $workflows->links() }}</div>
        @endif

        @else
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <div class="w-16 h-16 bg-surface rounded-2xl flex items-center justify-center mb-4 border border-border">
                <svg class="w-8 h-8 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <polyline stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" points="16 18 22 12 16 6"/>
                    <polyline stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" points="8 6 2 12 8 18"/>
                </svg>
            </div>
            @if($search || $statusFilter !== 'all')
                <h3 class="text-base font-semibold text-ink mb-1">No workflows found</h3>
                <p class="text-sm text-muted mb-4">Try adjusting your search or filters.</p>
                <button type="button" wire:click="$set('search', ''); $set('statusFilter', 'all')"
                        class="px-4 py-2 text-sm text-brand border border-brand/30 rounded-xl hover:bg-brand/5 transition-colors">
                    Clear filters
                </button>
            @else
                <h3 class="text-base font-semibold text-ink mb-1">No workflows yet</h3>
                <p class="text-sm text-muted mb-4">Create your first workflow to start automating repetitive tasks.</p>
                <div class="flex items-center gap-3">
                    <a href="{{ route('workflows.create') }}?guided=1" wire:navigate
                       class="px-4 py-2 text-sm font-medium text-ink bg-surface-2 border border-border rounded-xl hover:bg-surface transition-colors inline-flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-14 9V3z"/></svg>
                        Start from Template
                    </a>
                    <a href="{{ route('workflows.create') }}" wire:navigate class="btn-primary text-sm inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Create Workflow
                    </a>
                </div>
            @endif
        </div>
        @endif

    </div>{{-- /main card --}}
</div>
