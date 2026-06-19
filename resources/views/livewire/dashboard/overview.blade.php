<div class="space-y-5 text-ink relative">

    {{-- Subtle loading indicator for wire:poll refreshes --}}
    <div wire:loading class="absolute top-2 right-2 z-10">
        <svg class="animate-spin h-4 w-4 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
    </div>

    {{-- Welcome banner --}}
    <div class="bg-surface-2 border border-border rounded-xl px-4 py-3">
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
            <div>
                <h1 class="text-lg font-bold text-ink">Welcome back, {{ auth()->user()->name ?? 'there' }} 👋</h1>
                <p class="text-xs text-muted mt-0.5">Here's what's happening with your customers today.</p>
            </div>
            <div class="flex gap-1.5 flex-wrap">
                <a href="{{ route('settings.paygro') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-brand text-white shadow-sm hover:bg-brand-strong transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    PayGro Sync
                </a>
                <a href="{{ route('campaigns.create') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg border border-border bg-surface-2 text-ink/80 hover:bg-surface hover:border-brand/30 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    New Campaign
                </a>
            </div>
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        @php
        $cards = [
            [
                'label'    => 'Total Customers',
                'value'    => number_format($totalCustomers),
                'period'   => 'all time',
                'gradient' => 'from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20',
                'icon_bg'  => 'bg-info/15 dark:bg-blue-900/40',
                'icon_txt' => 'text-blue-600 dark:text-blue-400',
                'bar'      => 'bg-blue-300 dark:bg-blue-700',
                'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
            ],
            [
                'label'    => 'SMS Sent Today',
                'value'    => number_format($smsSentToday),
                'period'   => 'today',
                'gradient' => 'from-purple-50 to-violet-50 dark:from-purple-900/20 dark:to-violet-900/20',
                'icon_bg'  => 'bg-brand/15 dark:bg-purple-900/40',
                'icon_txt' => 'text-purple-600 dark:text-purple-400',
                'bar'      => 'bg-purple-300 dark:bg-purple-700',
                'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>',
            ],
            [
                'label'    => 'Delivery Rate',
                'value'    => $deliveryRate . '%',
                'period'   => 'all time avg',
                'gradient' => 'from-emerald-50 to-teal-50 dark:from-emerald-900/20 dark:to-teal-900/20',
                'icon_bg'  => 'bg-success/15 dark:bg-emerald-900/40',
                'icon_txt' => 'text-emerald-600 dark:text-emerald-400',
                'bar'      => 'bg-emerald-300 dark:bg-emerald-700',
                'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
            [
                'label'    => 'Active Campaigns',
                'value'    => number_format($activeCampaigns),
                'period'   => 'running now',
                'gradient' => 'from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20',
                'icon_bg'  => 'bg-warning/15 dark:bg-amber-900/40',
                'icon_txt' => 'text-amber-600 dark:text-amber-400',
                'bar'      => 'bg-amber-300 dark:bg-amber-700',
                'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
            ],
            [
                'label'    => 'Overdue Customers',
                'value'    => number_format($overdueCustomers),
                'period'   => 'need attention',
                'gradient' => 'from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20',
                'icon_bg'  => 'bg-danger/15 dark:bg-red-900/40',
                'icon_txt' => 'text-red-600 dark:text-red-400',
                'bar'      => 'bg-red-300 dark:bg-red-700',
                'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
            ],
            [
                'label'    => 'Active Workflows',
                'value'    => number_format($activeWorkflows),
                'period'   => 'automating',
                'gradient' => 'from-cyan-50 to-sky-50 dark:from-cyan-900/20 dark:to-sky-900/20',
                'icon_bg'  => 'bg-info/15 dark:bg-cyan-900/40',
                'icon_txt' => 'text-cyan-600 dark:text-cyan-400',
                'bar'      => 'bg-cyan-300 dark:bg-cyan-700',
                'icon'     => '<polyline stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" points="16 18 22 12 16 6"/><polyline stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" points="8 6 2 12 8 18"/>',
            ],
        ];
        @endphp

        @foreach($cards as $card)
        <div class="relative bg-gradient-to-br {{ $card['gradient'] }} rounded-2xl border border-border overflow-hidden hover:shadow-sm hover:-translate-y-0.5 transition-all duration-200 group bg-surface-2">
            <div class="relative p-3.5">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-8 h-8 {{ $card['icon_bg'] }} rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 {{ $card['icon_txt'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $card['icon'] !!}</svg>
                    </div>
                    <span class="text-[11px] font-medium text-muted leading-tight line-clamp-2">{{ $card['label'] }}</span>
                </div>
                <div class="text-2xl font-bold text-ink tracking-tight">{{ $card['value'] }}</div>
                <div class="text-[10px] text-muted mt-0.5 font-medium">{{ $card['period'] }}</div>
                {{-- Sparkline --}}
                <div class="flex items-end gap-0.5 mt-2 h-3.5">
                    @foreach($sparklineHeights as $h)
                    <div class="flex-1 {{ $card['bar'] }} rounded-sm opacity-60" style="height: {{ max(10, $h) }}%"></div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Quick Actions --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-6">
            <div class="flex items-center gap-2.5 mb-4">
                <div class="w-8 h-8 bg-brand/15 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h2 class="text-base font-bold text-ink">Quick Actions</h2>
            </div>
            <div class="space-y-2">
                @php
                $actions = [
                    ['href' => route('settings.paygro'),  'label' => 'PayGro Sync',        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>', 'color' => 'bg-info/15 text-info'],
                    ['href' => route('campaigns.create'),  'label' => 'New Campaign',       'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>', 'color' => 'bg-brand/15 text-brand'],
                    ['href' => route('workflows.create'),  'label' => 'New Workflow',       'icon' => '<polyline stroke-linecap="round" stroke-linejoin="round" stroke-width="2" points="16 18 22 12 16 6"/><polyline stroke-linecap="round" stroke-linejoin="round" stroke-width="2" points="8 6 2 12 8 18"/>',  'color' => 'bg-warning/15 text-warning'],
                    ['href' => route('analytics'),         'label' => 'View Analytics',    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v18h18"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 14l4-4 3 3 5-6"/>', 'color' => 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400'],
                ];
                @endphp
                @foreach($actions as $action)
                <a href="{{ $action['href'] }}" wire:navigate
                   class="flex items-center gap-3 p-3 rounded-xl border border-border hover:bg-surface hover:border-border hover:shadow-sm hover:-translate-y-0.5 transition-all duration-150 group">
                    <div class="w-8 h-8 {{ $action['color'] }} rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $action['icon'] !!}</svg>
                    </div>
                    <span class="text-sm font-medium text-ink group-hover:text-brand transition-colors">{{ $action['label'] }}</span>
                    <svg class="w-4 h-4 text-muted ml-auto opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                @endforeach
            </div>
        </div>

        {{-- Recent Customers --}}
        <div class="lg:col-span-2 bg-surface-2 rounded-2xl border border-border">
            <div class="flex items-center justify-between px-6 pt-6 pb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-info/15 dark:bg-blue-900/40 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-ink">Recent Customers</h2>
                        <p class="text-xs text-muted mt-0.5">Latest additions to your database</p>
                    </div>
                </div>
                <a href="{{ route('customers.index') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-all duration-200">
                    View All
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
            <div class="px-6 pb-6">
                @php
                $statusColors = [
                    'current'  => 'bg-success/15 text-success',
                    'due_soon' => 'bg-warning/15 text-warning',
                    'overdue'  => 'bg-danger/15 text-danger',
                    'paid_off' => 'bg-surface text-muted',
                ];
                $avatarPalette = ['bg-brand/15 text-brand','bg-info/15 text-info','bg-success/15 text-success','bg-warning/15 text-warning','bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300'];
                @endphp
                @forelse($recentCustomers as $customer)
                <a href="{{ route('customers.show', $customer) }}" wire:navigate
                   class="flex items-center gap-3 p-3.5 rounded-xl border border-transparent hover:border-border hover:bg-surface transition-all duration-150 group -mx-2 mb-1">
                    <div class="w-9 h-9 {{ $avatarPalette[$customer->id % count($avatarPalette)] }} rounded-full flex items-center justify-center text-sm font-bold shrink-0">
                        {{ $customer->initials }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-ink group-hover:text-brand truncate transition-colors">{{ $customer->full_name }}</p>
                        <p class="text-xs text-muted truncate">{{ $customer->phone ?? $customer->email ?? 'No contact info' }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if($customer->payment_status)
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$customer->payment_status] ?? 'bg-surface text-muted' }} whitespace-nowrap">
                            {{ str_replace('_', ' ', ucfirst($customer->payment_status)) }}
                        </span>
                        @endif
                        <span class="text-xs text-muted">{{ $customer->created_at->diffForHumans(null, true) }}</span>
                    </div>
                </a>
                @empty
                <div class="py-10 text-center">
                    <div class="w-12 h-12 bg-surface rounded-2xl flex items-center justify-center mx-auto mb-3 border border-border">
                        <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <p class="text-sm font-medium text-ink">No customers yet</p>
                    <p class="text-xs text-muted mt-1">Run PayGro sync to import customers.</p>
                    <a href="{{ route('settings.paygro') }}" wire:navigate class="inline-flex items-center gap-2 mt-3 px-4 py-2 text-sm font-medium btn-primary">
                        PayGro Sync
                    </a>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Overdue alert --}}
    @if($overdueCustomers > 0)
    <div class="flex items-start gap-3 p-4 bg-danger/10 dark:bg-red-900/20 border border-danger/20 dark:border-red-800 rounded-2xl">
        <svg class="w-5 h-5 text-danger shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div class="flex-1">
            <p class="text-sm font-semibold text-danger">{{ number_format($overdueCustomers) }} customer{{ $overdueCustomers > 1 ? 's' : '' }} overdue</p>
            <p class="text-xs text-danger/80 mt-0.5">These customers have overdue payments. Send a reminder campaign to follow up.</p>
        </div>
        <a href="{{ route('campaigns.create') }}" wire:navigate
           class="shrink-0 px-3 py-1.5 text-xs font-semibold text-white bg-danger hover:bg-red-700 rounded-lg transition-colors">
            Send Reminder
        </a>
    </div>
    @endif

</div>
