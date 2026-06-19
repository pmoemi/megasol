<div class="space-y-6">

    {{-- ═══════════════════════════════════════════════
         TOP BAR  — title left, all controls right
         Matches reference: Search | Status▼ | Groups▼ | Export
         ═══════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

        {{-- Title + count --}}
        <div>
            <h1 class="text-2xl font-bold text-ink">Customers</h1>
            <p class="text-sm text-muted mt-0.5">{{ number_format($totalCustomers) }} customers total</p>
        </div>

        {{-- Controls --}}
        <div class="flex items-center gap-2 flex-wrap">

            {{-- Search --}}
            <div class="relative">
                <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Search customers..."
                       class="pl-9 pr-4 py-2 text-sm bg-surface-2 border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent placeholder-muted/60 w-52">
            </div>

            {{-- Payment Status dropdown (styled like "All Tags" in ref) --}}
            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium bg-surface-2 border border-border rounded-xl hover:bg-surface hover:border-brand/30 transition-colors whitespace-nowrap"
                        :class="open ? 'border-brand/40 bg-surface' : ''"
                        aria-haspopup="listbox" :aria-expanded="open">
                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    <span class="text-ink">
                        @if($paymentStatusFilter === '')
                            All Statuses
                        @elseif($paymentStatusFilter === 'current')   Current
                        @elseif($paymentStatusFilter === 'due_soon')  Due Soon
                        @elseif($paymentStatusFilter === 'overdue')   Overdue
                        @elseif($paymentStatusFilter === 'paid_off')  Paid Off
                        @else {{ ucfirst(str_replace('_',' ',$paymentStatusFilter)) }}
                        @endif
                    </span>
                    <svg class="w-3.5 h-3.5 text-muted transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                     class="absolute left-0 top-full mt-1.5 z-50 w-44 bg-surface-2 border border-border rounded-xl shadow-lg py-1 origin-top-left" style="display:none;">
                    @foreach(['' => 'All Statuses', 'current' => 'Current', 'due_soon' => 'Due Soon', 'overdue' => 'Overdue', 'paid_off' => 'Paid Off'] as $val => $label)
                    <button type="button" @click="$wire.set('paymentStatusFilter', '{{ $val }}'); open = false" role="option"
                            class="flex items-center gap-2.5 w-full text-left px-4 py-2 text-sm transition-colors hover:bg-surface
                                   {{ $paymentStatusFilter === $val ? 'font-semibold text-brand bg-brand/5' : 'text-ink/80' }}">
                        @if($paymentStatusFilter === $val)
                        <svg class="w-3.5 h-3.5 text-brand shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        @else
                        <span class="w-3.5 h-3.5 shrink-0"></span>
                        @endif
                        {{ $label }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Account Status dropdown --}}
            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium bg-surface-2 border border-border rounded-xl hover:bg-surface hover:border-brand/30 transition-colors whitespace-nowrap"
                        :class="open ? 'border-brand/40 bg-surface' : ''"
                        aria-haspopup="listbox" :aria-expanded="open">
                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-ink">
                        @if($accountStatusFilter === '')
                            All Accounts
                        @else
                            {{ str_replace('_', ' ', ucfirst($accountStatusFilter)) }}
                        @endif
                    </span>
                    <svg class="w-3.5 h-3.5 text-muted transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-transition class="absolute left-0 top-full mt-1.5 z-50 w-44 bg-surface-2 border border-border rounded-xl shadow-lg py-1 origin-top-left" style="display:none;">
                    @foreach(['' => 'All Accounts', 'active' => 'Active', 'paid_off' => 'Fully Paid', 'defaulting' => 'Defaulting'] as $val => $label)
                    <button type="button" @click="$wire.set('accountStatusFilter', '{{ $val }}'); open = false" role="option"
                            class="flex items-center gap-2.5 w-full text-left px-4 py-2 text-sm transition-colors hover:bg-surface {{ $accountStatusFilter === $val ? 'font-semibold text-brand bg-brand/5' : 'text-ink/80' }}">
                        {{ $label }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Groups dropdown --}}
            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium bg-surface-2 border border-border rounded-xl hover:bg-surface hover:border-brand/30 transition-colors whitespace-nowrap"
                        :class="open ? 'border-brand/40 bg-surface' : ''"
                        aria-haspopup="listbox" :aria-expanded="open">
                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span class="text-ink">
                        @if($selectedGroup === '')
                            All Groups
                        @else
                            {{ $customerGroups->firstWhere('id', $selectedGroup)?->name ?? 'All Groups' }}
                        @endif
                    </span>
                    <svg class="w-3.5 h-3.5 text-muted transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                     class="absolute left-0 top-full mt-1.5 z-50 w-52 bg-surface-2 border border-border rounded-xl shadow-lg py-1 origin-top-left max-h-64 overflow-y-auto" style="display:none;">
                    <button type="button" @click="$wire.set('selectedGroup', ''); open = false" role="option"
                            class="flex items-center gap-2.5 w-full text-left px-4 py-2 text-sm transition-colors hover:bg-surface
                                   {{ $selectedGroup === '' ? 'font-semibold text-brand bg-brand/5' : 'text-ink/80' }}">
                        @if($selectedGroup === '')
                        <svg class="w-3.5 h-3.5 text-brand shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        @else
                        <span class="w-3.5 h-3.5 shrink-0"></span>
                        @endif
                        All Groups
                    </button>
                    @forelse($customerGroups as $group)
                    <button type="button" @click="$wire.set('selectedGroup', '{{ $group->id }}'); open = false" role="option"
                            class="flex items-center gap-2.5 w-full text-left px-4 py-2 text-sm transition-colors hover:bg-surface
                                   {{ (string) $selectedGroup === (string) $group->id ? 'font-semibold text-brand bg-brand/5' : 'text-ink/80' }}">
                        @if((string) $selectedGroup === (string) $group->id)
                        <svg class="w-3.5 h-3.5 text-brand shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        @else
                        <span class="w-3.5 h-3.5 shrink-0"></span>
                        @endif
                        {{ $group->name }}
                        @if(isset($group->customers_count))
                        <span class="text-muted font-normal">({{ $group->customers_count }})</span>
                        @endif
                    </button>
                    @empty
                    <div class="px-4 py-3 text-xs text-muted text-center">No groups yet</div>
                    @endforelse
                    <div class="border-t border-border/50 mt-1 pt-1">
                        <a href="{{ route('customers.groups') }}" wire:navigate @click="open = false"
                           class="flex items-center gap-2 px-4 py-2 text-xs text-brand font-medium hover:bg-brand/5 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Manage Groups
                        </a>
                    </div>
                </div>
            </div>

            {{-- Segments --}}
            <a href="{{ route('customers.segments') }}" wire:navigate
               class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-ink/80 bg-surface-2 border border-border rounded-xl hover:bg-surface hover:border-brand/30 transition-colors whitespace-nowrap">
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M3 9h18M3 13.5h18M3 18h18"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15M15 4.5v15"/></svg>
                Segments
            </a>

            {{-- Export --}}
            <button type="button" wire:click="export" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-ink/80 bg-surface-2 border border-border rounded-xl hover:bg-surface hover:border-brand/30 transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed">
                <svg class="w-4 h-4 text-muted" wire:loading.remove wire:target="export" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <svg class="w-4 h-4 text-muted animate-spin" wire:loading wire:target="export" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Export
            </button>
        </div>
    </div>

    {{-- Bulk action bar --}}
    @if(count($selectedIds) > 0)
    <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <span class="text-sm text-primary-700 dark:text-primary-300 font-medium">{{ count($selectedIds) }} customer(s) selected</span>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-surface-2 border border-border rounded-lg hover:bg-surface transition-colors">
                    {{ $bulkAction ? ucfirst(str_replace('_',' ',$bulkAction)) : 'Choose action...' }}
                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" @click="open = false" x-transition class="absolute left-0 top-full mt-1 z-50 w-44 bg-surface-2 border border-border rounded-xl shadow-lg py-1" style="display:none;">
                    @foreach(['delete' => 'Delete', 'add_to_group' => 'Add to Group', 'remove_from_group' => 'Remove from Group', 'export' => 'Export Selected'] as $val => $label)
                    <button type="button" wire:click="$set('bulkAction', '{{ $val }}')"
                            class="w-full text-left px-4 py-2 text-sm hover:bg-surface transition-colors {{ $val === 'delete' ? 'text-danger' : 'text-ink/80' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            @if($bulkAction === 'add_to_group' || $bulkAction === 'remove_from_group')
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-surface-2 border border-border rounded-lg hover:bg-surface transition-colors">
                    Select group...
                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" @click="open = false" x-transition class="absolute left-0 top-full mt-1 z-50 w-52 bg-surface-2 border border-border rounded-xl shadow-lg py-1" style="display:none;">
                    @forelse($customerGroups as $group)
                    <button type="button"
                            @if($bulkAction === 'add_to_group')
                            wire:click="addToGroup({{ $group->id }})"
                            @else
                            wire:click="removeFromGroup({{ $group->id }})"
                            @endif
                            class="w-full text-left px-4 py-2 text-sm text-ink/80 hover:bg-surface transition-colors">
                        {{ $group->name }}
                        @if(isset($group->customers_count))
                        <span class="text-muted">({{ $group->customers_count }})</span>
                        @endif
                    </button>
                    @empty
                    <div class="px-4 py-3 text-xs text-muted text-center">
                        No groups yet.
                        <a href="{{ route('customers.groups') }}" wire:navigate class="text-brand underline">Create one</a>
                    </div>
                    @endforelse
                </div>
            </div>
            @endif

            <button type="button" wire:click="executeBulkAction"
                    wire:confirm="{{ $bulkAction === 'delete' ? 'Delete ' . count($selectedIds) . ' customer(s)? This cannot be undone.' : 'Apply action?' }}"
                    wire:loading.attr="disabled"
                    class="btn-primary px-3 py-1.5 text-sm disabled:opacity-50"
                    @if(!$bulkAction || in_array($bulkAction, ['add_to_group', 'remove_from_group'])) disabled @endif>
                <span wire:loading.remove wire:target="executeBulkAction">Apply</span>
                <span wire:loading wire:target="executeBulkAction" class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Applying...
                </span>
            </button>
            <button type="button" wire:click="$set('selectedIds', [])" class="px-3 py-1.5 text-sm text-muted hover:text-ink transition-colors">Clear</button>
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

    @if($customers->count() > 0)
    {{-- Table --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <p class="text-xs text-muted text-center py-1 md:hidden">Swipe to see more columns</p>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="w-12 px-4 py-3">
                            <input type="checkbox" wire:model.live="selectAll" wire:change="toggleSelectAll"
                                   class="w-4 h-4 text-primary-600 border-border rounded focus:ring-primary-500">
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 cursor-pointer hover:text-ink/80 whitespace-nowrap" wire:click="sortBy('first_name')">
                            Name @if($sortField === 'first_name')<span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden sm:table-cell">Phone</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell cursor-pointer hover:text-ink/80 whitespace-nowrap" wire:click="sortBy('account_number')">
                            Account # @if($sortField === 'account_number')<span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 cursor-pointer hover:text-ink/80 whitespace-nowrap" wire:click="sortBy('payment_status')">
                            Status @if($sortField === 'payment_status')<span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell cursor-pointer hover:text-ink/80 whitespace-nowrap" wire:click="sortBy('outstanding_balance')">
                            Balance @if($sortField === 'outstanding_balance')<span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden xl:table-cell">Groups</th>
                        <th class="w-12 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @foreach($customers as $customer)
                    @php
                        $avatarPalette = ['bg-brand/15 text-brand','bg-info/15 text-info','bg-success/15 text-success','bg-warning/15 text-warning','bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300'];
                        $statusColors  = ['current' => 'bg-success/15 text-success','due_soon' => 'bg-warning/15 text-warning','overdue' => 'bg-danger/15 text-danger','paid_off' => 'bg-surface text-muted'];
                    @endphp
                    <tr class="hover:bg-surface transition-colors" wire:key="customer-{{ $customer->id }}">
                        <td class="px-4 py-3">
                            <input type="checkbox" value="{{ $customer->id }}" wire:model.live="selectedIds"
                                   class="w-4 h-4 text-primary-600 border-border rounded focus:ring-primary-500">
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 {{ $avatarPalette[$customer->id % count($avatarPalette)] }} rounded-full flex items-center justify-center text-sm font-bold shrink-0">
                                    {{ $customer->initials }}
                                </div>
                                <div class="min-w-0">
                                    <a href="{{ route('customers.show', $customer) }}" wire:navigate
                                       class="text-sm font-semibold text-ink hover:text-brand transition-colors block truncate">{{ $customer->full_name }}</a>
                                    @if($customer->email)<p class="text-xs text-muted truncate">{{ $customer->email }}</p>@endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-muted hidden sm:table-cell whitespace-nowrap">{{ $customer->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-muted hidden md:table-cell font-mono">{{ $customer->account_number ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php $acct = $customer->accountStatusMeta(); $acctColors = ['success' => 'bg-success/15 text-success','danger' => 'bg-danger/15 text-danger','muted' => 'bg-surface text-muted','info' => 'bg-info/15 text-info']; @endphp
                            <div class="flex flex-wrap gap-1.5">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $acctColors[$acct['color']] ?? 'bg-surface text-muted' }}">
                                {{ $acct['label'] }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $statusColors[$customer->payment_status] ?? 'bg-surface text-muted' }}">
                                {{ str_replace('_', ' ', ucfirst($customer->payment_status ?? '—')) }}
                            </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink font-medium hidden lg:table-cell whitespace-nowrap">
                            {{ $customer->outstanding_balance !== null ? number_format($customer->outstanding_balance, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3 hidden xl:table-cell">
                            <div class="flex flex-wrap gap-1">
                                @foreach($customer->customerLists->take(2) as $list)
                                <span class="px-2 py-0.5 rounded-md text-xs font-medium bg-surface text-muted border border-border/60 whitespace-nowrap">{{ $list->name }}</span>
                                @endforeach
                                @if($customer->customerLists->count() > 2)
                                <span class="px-2 py-0.5 rounded-md text-xs font-medium bg-surface text-muted border border-border/60">+{{ $customer->customerLists->count() - 2 }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div x-data="{
                                    open: false, menuTop: 0, menuLeft: 0,
                                    reposition() { const r = this.$refs.trigger.getBoundingClientRect(); this.menuLeft = r.right - 176; this.menuTop = r.bottom + 4; },
                                    toggle() { this.open = !this.open; if (this.open) this.$nextTick(() => this.reposition()); },
                                }"
                                 @scroll.window="open && reposition()" @resize.window="open && reposition()" @keydown.escape.window="open = false">
                                <button type="button" x-ref="trigger" @click="toggle()" class="p-1.5 text-muted/60 hover:text-ink rounded-lg hover:bg-surface transition-colors" aria-label="More actions" :aria-expanded="open">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01"/></svg>
                                </button>
                                <template x-teleport="body">
                                    <div x-show="open" @click.outside="open = false" x-transition role="menu"
                                         :style="`position: fixed; top: ${menuTop}px; left: ${menuLeft}px; z-index: 9999;`"
                                         class="w-44 bg-surface-2 rounded-xl shadow-lg border border-border py-1" style="display: none;">
                                        <a href="{{ route('customers.show', $customer) }}" wire:navigate role="menuitem" @click="open = false"
                                           class="flex items-center gap-2 w-full px-4 py-2 text-sm text-ink/80 hover:bg-surface">
                                            <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            View Profile
                                        </a>
                                        <button type="button" role="menuitem"
                                                wire:click="deleteCustomer({{ $customer->id }})"
                                                wire:confirm="Delete {{ $customer->full_name }}? This cannot be undone."
                                                @click="open = false"
                                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-danger hover:bg-danger/10">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
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
        <div class="px-4 py-3 border-t border-border">
            {{ $customers->links() }}
        </div>
    </div>

    @else
    {{-- ═══════════════════════════════════════════════
         EMPTY STATE — matches screenshot exactly:
         circular green-tinted background + group illustration + + badge
         ═══════════════════════════════════════════════ --}}
    <div class="flex flex-col items-center justify-center py-20 text-center">

        {{-- Illustrated circular icon --}}
        <div class="relative mb-6">
            <div class="w-32 h-32 rounded-full bg-success/10 dark:bg-success/5 flex items-center justify-center">
                <svg width="72" height="56" viewBox="0 0 72 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                    {{-- Back-left silhouette --}}
                    <circle cx="14" cy="18" r="9" stroke="#22C55E" stroke-width="2" fill="none" opacity="0.5"/>
                    <path d="M2 47c0-6.627 5.373-12 12-12h1" stroke="#22C55E" stroke-width="2" stroke-linecap="round" opacity="0.5"/>
                    {{-- Back-right silhouette --}}
                    <circle cx="58" cy="18" r="9" stroke="#22C55E" stroke-width="2" fill="none" opacity="0.5"/>
                    <path d="M70 47c0-6.627-5.373-12-12-12h-1" stroke="#22C55E" stroke-width="2" stroke-linecap="round" opacity="0.5"/>
                    {{-- Center (main) person --}}
                    <circle cx="36" cy="16" r="11" stroke="#16A34A" stroke-width="2.2" fill="none"/>
                    <path d="M14 52c0-8.837 9.85-16 22-16s22 7.163 22 16" stroke="#16A34A" stroke-width="2.2" stroke-linecap="round" fill="none"/>
                </svg>
            </div>
            {{-- Green + badge --}}
            <div class="absolute top-1 right-1 w-8 h-8 bg-success rounded-full flex items-center justify-center shadow-lg ring-2 ring-white dark:ring-surface-2">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </div>
        </div>

        @if($search || $paymentStatusFilter || $accountStatusFilter || $lifecycleFilter || $selectedGroup)
            <h3 class="text-lg font-bold text-ink mb-1">No customers found</h3>
            <p class="text-sm text-muted mb-5 max-w-xs">No customers match your current filters. Try adjusting your search or filter criteria.</p>
            <button type="button"
                    wire:click="$set('search', ''); $set('paymentStatusFilter', ''); $set('accountStatusFilter', ''); $set('lifecycleFilter', ''); $set('selectedGroup', '')"
                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-brand border border-brand/30 rounded-xl hover:bg-brand/5 transition-colors">
                Clear all filters
            </button>
        @else
            <h3 class="text-lg font-bold text-ink mb-1">No customers yet</h3>
            <p class="text-sm text-muted mb-5 max-w-xs">Customers are imported from PayGro. Connect and run sync in Settings.</p>
            <a href="{{ route('settings.paygro') }}" wire:navigate
               class="btn-primary inline-flex items-center gap-2 text-sm font-semibold px-6 py-2.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                PayGro Sync
            </a>
        @endif
    </div>
    @endif

</div>
