<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data="appLayout()"
    x-init="init()"
    :class="{ dark: theme === 'dark' }"
    :data-theme="theme"
    class="h-full overflow-hidden"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">

    <title>{{ $title ?? 'Dashboard' }} — {{ config('app.name') }}</title>

    @if ($favicon = \App\Support\AppTheme::faviconUrl())
        <link rel="icon" href="{{ $favicon }}">
    @endif

    {{-- Google Fonts: Outfit --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    {{-- Runtime brand colors (Branding / Theme Studio settings) --}}
    <style id="app-theme-vars">{!! \App\Support\AppTheme::cssVariables() !!}</style>

    {{-- Prevent theme flash --}}
    <script>
        if (localStorage.getItem('sidebar-collapsed') === 'true') document.documentElement.setAttribute('data-sidebar-collapsed', 'true');
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.classList.toggle('dark', savedTheme === 'dark');
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body class="h-full overflow-hidden font-sans text-ink bg-surface antialiased">

{{-- Skip to content (a11y) --}}
<a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[9999] focus:px-4 focus:py-2 focus:bg-brand focus:text-white focus:rounded-lg focus:text-sm focus:font-medium focus:shadow-lg">
    Skip to main content
</a>

{{-- Offline indicator --}}
<div x-data="{ online: navigator.onLine }"
     x-init="window.addEventListener('online', () => online = true); window.addEventListener('offline', () => online = false)"
     x-show="!online"
     x-transition
     class="fixed top-0 left-0 right-0 z-[9999] bg-amber-500 text-white text-center py-1.5 text-sm font-medium"
     style="display: none;">
    You're offline. Changes will sync when you reconnect.
</div>

{{-- Navigation progress bar --}}
<div id="mb-progress-bar"></div>

<div class="relative flex h-full min-h-0">

    {{-- ═══════════════════════════════════════════════
         SIDEBAR
         ═══════════════════════════════════════════════ --}}
    @persist('sidebar')
    <aside
        class="admin-sidebar app-sidebar fixed inset-y-0 left-0 z-40 -translate-x-full transition-transform duration-300 lg:sticky lg:top-0 lg:translate-x-0"
        :class="{
            'sidebar-collapsed': sidebarCollapsed,
            '!translate-x-0': sidebarOpen
        }"
        :style="{ width: sidebarCollapsed ? '5rem' : '240px' }"
        style="width: 240px;"
    >
        {{-- Brand --}}
        <div class="px-3 pt-4 pb-2 sidebar-brand flex items-center justify-between gap-2">
            <a href="{{ url('/dashboard') }}" class="flex items-center min-w-0 gap-2" wire:navigate>
                @if ($logo = \App\Support\AppTheme::logoUrl())
                    <img src="{{ $logo }}" alt="{{ config('app.name') }}" class="flex h-8 w-8 shrink-0 rounded-xl object-contain">
                @else
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-brand text-white shadow-soft">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                @endif
                <span class="font-bold text-base tracking-tight text-ink truncate" x-show="!sidebarCollapsed">{{ config('app.name') }}</span>
            </a>
            {{-- Mobile close --}}
            <button type="button"
                    class="lg:hidden shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-lg text-muted hover:text-ink hover:bg-surface-2 transition-colors"
                    x-on:click="sidebarOpen = false"
                    aria-label="Close sidebar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Navigation --}}
        <nav id="app-sidebar-nav" class="flex-1 overflow-y-auto overflow-x-hidden px-3 pb-3" aria-label="Main navigation">
            @php
            $navItems = [
                ['label' => 'Dashboard',       'href' => '/dashboard',       'icon' => '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect>'],
                ['label' => 'Customers',       'href' => '/customers',       'icon' => '<circle cx="9" cy="8" r="3"></circle><path d="M3 19c1.8-3 4.8-4.5 6-4.5s4.2 1.5 6 4.5"></path><path d="M15.5 11a3 3 0 1 0 0-6"></path><path d="M18 19c.6-1.1 1.6-2.2 3-3"></path>'],
                ['label' => 'Campaigns',       'href' => '/campaigns',       'icon' => '<line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>'],
                ['label' => 'Collections',     'href' => '/collections',     'icon' => '<rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line>'],
                ['label' => 'Inventory',       'href' => '/inventory',       'icon' => '<path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>'],
                ['label' => 'Workflows',       'href' => '/workflows',       'icon' => '<polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline>'],
                ['label' => 'SMS Templates',   'href' => '/templates',       'icon' => '<path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>'],
                ['label' => 'Email Templates', 'href' => '/email-templates', 'icon' => '<path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>'],
                ['label' => 'Analytics',       'href' => '/analytics',       'icon' => '<path d="M3 3v18h18"></path><path d="M7 14l4-4 3 3 5-6"></path>'],
                ['label' => 'Activity',        'href' => '/activity',        'icon' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>'],
                ['label' => 'Help Center',     'href' => '/help',            'icon' => '<circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line>'],
                ['section' => 'Administration'],
                ['label' => 'Staff',           'href' => '/staff',           'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'],
                ['label' => 'Settings',        'href' => '/settings/paygro',        'icon' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>'],
            ];
            @endphp

            <ul class="menu menu-sm w-full p-0 gap-0.5"
                x-data="{ currentPath: window.location.pathname }"
                @popstate.window="currentPath = window.location.pathname"
                x-init="document.addEventListener('livewire:navigated', () => { currentPath = window.location.pathname })">
                @foreach($navItems as $item)
                    @if(isset($item['section']))
                        <li class="menu-title mt-2.5" x-show="!sidebarCollapsed"><span>{{ $item['section'] }}</span></li>
                    @else
                        @php $itemPath = '/' . ltrim($item['href'], '/'); @endphp
                        <li>
                            <a href="{{ url($item['href']) }}" wire:navigate.hover
                               class="nav-item"
                               :class="(currentPath === '{{ $itemPath }}' || (currentPath.startsWith('{{ $itemPath }}/') && '{{ $itemPath }}' !== '/')) ? 'nav-item-active' : ''"
                               aria-label="{{ $item['label'] }}"
                               :aria-current="(currentPath === '{{ $itemPath }}' || currentPath.startsWith('{{ $itemPath }}/')) ? 'page' : 'false'">
                                <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">@safeSvg($item['icon'])</svg>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        {{-- Sidebar footer --}}
        <div
            x-show="!sidebarCollapsed"
            x-transition:enter="transition-opacity duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="border-t border-border/40 px-3 py-2 shrink-0 sidebar-footer"
        >
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand/20 text-brand text-xs font-semibold">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold truncate leading-tight text-ink">{{ auth()->user()->name ?? 'User' }}</p>
                    <p class="text-[10px] text-muted truncate leading-tight">{{ auth()->user()->email ?? '' }}</p>
                </div>
            </div>
            <div class="mt-2 flex items-center gap-2 text-[10px] text-muted">
                <span>&copy; {{ date('Y') }} {{ config('app.name') }}</span>
            </div>
        </div>

        {{-- Collapsed bottom icon --}}
        <div class="sidebar-brand-icon-bottom" style="display:none">
            <a href="{{ url('/dashboard') }}" wire:navigate>
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand text-white shadow-soft">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
            </a>
        </div>
    </aside>
    @endpersist

    {{-- Mobile overlay --}}
    <div
        class="sidebar-overlay lg:hidden"
        x-show="sidebarOpen"
        x-on:click="sidebarOpen = false"
        x-transition:enter="transition-opacity duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none;"
    ></div>

    {{-- ═══════════════════════════════════════════════
         MAIN CONTENT AREA
         ═══════════════════════════════════════════════ --}}
    <div class="app-content">

        {{-- Header --}}
        <header class="app-header">
            <div class="flex items-center justify-between gap-4 px-6 py-3">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    {{-- Mobile hamburger --}}
                    <button type="button" class="shrink-0 lg:hidden icon-button" x-on:click="sidebarOpen = !sidebarOpen" aria-label="Toggle sidebar">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    {{-- Sidebar collapse toggle (desktop) --}}
                    <button type="button" class="hidden lg:flex icon-button" x-on:click="sidebarCollapsed = !sidebarCollapsed" :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                        <svg x-show="!sidebarCollapsed" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                        <svg x-show="sidebarCollapsed" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                    </button>

                    {{-- Customer search --}}
                    <div class="hidden sm:block relative flex-1 max-w-md"
                         x-data="{
                             query: '',
                             open: false,
                             results: [],
                             loading: false,
                             debounceTimer: null,
                             async search() {
                                 if (this.query.trim().length < 2) { this.results = []; this.open = false; return; }
                                 this.loading = true;
                                 this.open = true;
                                 clearTimeout(this.debounceTimer);
                                 this.debounceTimer = setTimeout(async () => {
                                     try {
                                         const q = encodeURIComponent(this.query.trim());
                                         const res = await fetch('/api/search/customers?q=' + q, {
                                             headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                                         });
                                         if (res.ok) { const j = await res.json(); this.results = j.data || []; }
                                     } catch(e) {}
                                     this.loading = false;
                                 }, 300);
                             },
                             clear() { this.query = ''; this.open = false; this.results = []; }
                         }"
                         @click.outside="open = false"
                         x-init="document.addEventListener('keydown', (e) => { if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); $refs.searchInput.focus(); } })">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted pointer-events-none z-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
                            <input type="text"
                                   x-model="query"
                                   @input="search()"
                                   @focus="if(query.length >= 2) open = true"
                                   x-ref="searchInput"
                                   @keydown.enter.prevent="if(query.trim()) window.location.href='/customers?search=' + encodeURIComponent(query.trim())"
                                   @keydown.escape="clear()"
                                   placeholder="Search customers… (Ctrl+K)"
                                   aria-label="Search customers"
                                   class="input pl-9 pr-16 w-full">
                            <kbd x-show="!query" class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none hidden sm:inline-flex items-center gap-0.5 rounded-lg border border-border bg-surface-2 px-1.5 py-0.5 text-[10px] font-medium text-muted">
                                Ctrl K
                            </kbd>
                            <button x-show="query.length > 0" x-cloak @click="clear()" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-ink">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- Search dropdown --}}
                        <div x-show="open" x-transition class="absolute left-0 right-0 mt-2 rounded-2xl border border-border bg-surface-2 shadow-xl z-50 overflow-hidden" style="display:none">
                            <div x-show="loading" class="px-4 py-3 text-center">
                                <svg class="animate-spin h-4 w-4 text-brand mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </div>
                            <template x-if="results.length > 0">
                                <div>
                                    <p class="px-4 pt-3 pb-1 text-[10px] font-semibold uppercase tracking-widest text-muted">Customers</p>
                                    <template x-for="c in results" :key="c.id">
                                        <a :href="'/customers/' + c.id" class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface transition-colors">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand text-xs font-bold" x-text="(c.first_name || c.name || '?')[0].toUpperCase()"></span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-ink truncate" x-text="c.first_name + ' ' + (c.last_name || '')"></p>
                                                <p class="text-xs text-muted truncate" x-text="c.phone || c.email || ''"></p>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </template>
                            <div x-show="!loading && results.length === 0 && query.length >= 2" class="px-4 py-4 text-center">
                                <p class="text-sm text-muted">No customers found for "<span class="font-medium text-ink" x-text="query"></span>"</p>
                            </div>
                            <a x-show="query.length >= 2" :href="'/customers?search=' + encodeURIComponent(query)" class="flex items-center justify-center gap-2 px-4 py-2.5 border-t border-border/60 text-xs font-semibold text-brand hover:bg-brand/5 transition-colors">
                                View all results
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Right side actions --}}
                <div class="flex shrink-0 items-center gap-2">

                    {{-- Theme toggle --}}
                    <x-theme-toggle />

                    {{-- Notification bell --}}
                    <div class="relative"
                         x-data="{
                             open: false,
                             unreadCount: 0,
                             notifications: [],
                             loaded: false,
                             async fetchNotifications() {
                                 {{-- Simulate system notifications from overdue customers / pending campaigns --}}
                                 try {
                                     const r = await fetch('/api/notifications', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
                                     if (!r.ok) { this.loaded = true; return; }
                                     const j = await r.json();
                                     this.notifications = j.notifications || [];
                                     this.unreadCount   = j.unread_count || 0;
                                 } catch(e) {}
                                 this.loaded = true;
                             },
                             markAllRead() { this.unreadCount = 0; this.notifications = this.notifications.map(n => ({...n, read: true})); },
                         }"
                         x-on:click.outside="open = false">
                        <button x-on:click="open = !open; if (open && !loaded) fetchNotifications()"
                                class="icon-button relative"
                                aria-label="Notifications"
                                :aria-expanded="open">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
                                <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                            </svg>
                            <span x-show="unreadCount > 0" x-cloak
                                  x-text="unreadCount > 9 ? '9+' : unreadCount"
                                  class="absolute -top-0.5 -right-0.5 min-w-[16px] h-[16px] px-1 bg-danger text-white text-[10px] font-bold rounded-full ring-2 ring-surface-2 flex items-center justify-center"></span>
                        </button>

                        <div x-show="open" x-transition
                             class="absolute right-0 mt-2 w-80 rounded-2xl border border-border/70 bg-surface-2 shadow-soft z-50 overflow-hidden"
                             style="display: none;">
                            <div class="px-4 py-3 border-b border-border/50 flex items-center justify-between">
                                <p class="text-sm font-semibold text-ink">Notifications</p>
                                <button x-show="unreadCount > 0" @click="markAllRead()"
                                        class="text-xs text-brand font-medium hover:text-brand-strong transition-colors">
                                    Mark all read
                                </button>
                            </div>

                            <div class="max-h-96 overflow-y-auto">
                                {{-- Loading state --}}
                                <div x-show="!loaded" class="p-6 text-center">
                                    <div class="inline-block w-5 h-5 border-2 border-brand border-t-transparent rounded-full animate-spin"></div>
                                    <p class="text-xs text-muted mt-2">Loading...</p>
                                </div>

                                {{-- Empty state --}}
                                <div x-show="loaded && notifications.length === 0" x-cloak class="p-6 text-center">
                                    <svg class="w-10 h-10 mx-auto text-muted/40 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                    <p class="text-sm font-medium text-ink/70">All caught up!</p>
                                    <p class="text-xs text-muted mt-1">We'll notify you about campaigns, overdue customers, and sync results.</p>
                                </div>

                                {{-- Notification items --}}
                                <template x-for="n in notifications" :key="n.id">
                                    <a :href="n.url || '#'"
                                       :class="n.read ? 'opacity-60' : 'bg-brand/5'"
                                       class="flex items-start gap-3 px-4 py-3 border-b border-border/30 hover:bg-surface transition-colors">
                                        <span class="flex-shrink-0 w-8 h-8 rounded-full bg-brand/10 text-brand flex items-center justify-center mt-0.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"/></svg>
                                        </span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-ink truncate" x-text="n.title"></p>
                                            <p class="text-xs text-muted mt-0.5" x-text="n.body"></p>
                                            <p class="text-xs text-muted/60 mt-1" x-text="n.time"></p>
                                        </div>
                                        <span x-show="!n.read" class="w-2 h-2 rounded-full bg-brand shrink-0 mt-2"></span>
                                    </a>
                                </template>
                            </div>

                            <div class="px-4 py-2.5 border-t border-border/50 flex items-center justify-between">
                                <a href="{{ route('settings.paygro') }}" wire:navigate class="text-xs font-medium text-brand hover:text-brand-strong transition-colors" @click="open = false">
                                    Settings &rarr;
                                </a>
                                <a href="{{ route('activity') }}" wire:navigate class="text-xs font-medium text-muted hover:text-ink transition-colors" @click="open = false">
                                    View activity log &rarr;
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- User menu --}}
                    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                        <button x-on:click="open = !open"
                                class="flex items-center gap-2.5 transition rounded-xl px-2 py-1.5 hover:bg-surface"
                                aria-label="User menu"
                                aria-haspopup="true"
                                :aria-expanded="open">
                            <span class="hidden text-right md:block">
                                <span class="block text-sm font-semibold text-ink leading-tight">{{ auth()->user()->name ?? 'User' }}</span>
                                <span class="block text-xs text-muted">{{ Str::limit(auth()->user()->email ?? '', 24) }}</span>
                            </span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand/15 text-brand text-sm font-bold ring-2 ring-brand/20">
                                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}{{ strtoupper(substr(explode(' ', trim(auth()->user()->name ?? 'U '))[1] ?? '', 0, 1)) }}
                            </span>
                            <svg class="h-3.5 w-3.5 text-muted hidden md:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </button>

                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-56 rounded-2xl border border-border/70 bg-surface-2 p-2 shadow-soft z-50"
                            style="display: none;"
                        >
                            <div class="px-3 py-2 mb-1">
                                <p class="text-xs font-semibold uppercase tracking-widest text-muted">Account</p>
                            </div>
                            <a href="{{ route('settings.paygro') }}" wire:navigate class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-ink transition hover:bg-muted/10">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 text-muted" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4"></circle><path d="M5 21v-1a7 7 0 0 1 14 0v1"></path></svg>
                                Profile
                            </a>
                            <a href="{{ url('/staff') }}" wire:navigate class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-ink transition hover:bg-muted/10">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 text-muted" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                Team / Staff
                            </a>
                            <div class="my-1 border-t border-border/60"></div>
                            {{-- Theme toggle row --}}
                            <button type="button" @click="toggleTheme(); open = false"
                                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm text-ink transition hover:bg-muted/10">
                                <svg x-show="theme === 'dark'" viewBox="0 0 24 24" class="h-4 w-4 text-muted" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                                <svg x-show="theme !== 'dark'" viewBox="0 0 24 24" class="h-4 w-4 text-muted" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                                <span x-text="theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'"></span>
                            </button>
                            <div class="my-1 border-t border-border/60"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm text-danger transition hover:bg-danger/10">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                    Sign Out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- Toast container — must be before flash dispatches --}}
        <x-toast-container />

        {{-- Page content --}}
        <main class="app-main">

            {{-- Flash messages as toasts --}}
            @if(session('success'))
                <x-toast type="success" :message="session('success')" />
            @endif
            @if(session('error'))
                <x-toast type="error" :message="session('error')" :duration="0" />
            @endif
            @if(session('warning'))
                <x-toast type="warning" :message="session('warning')" :duration="8000" />
            @endif
            @if(session('info'))
                <x-toast type="info" :message="session('info')" :duration="8000" />
            @endif

            <div id="main-content" class="flex-1">
                {{ $slot }}
            </div>

            <div id="live-announcements" aria-live="polite" aria-atomic="true" class="sr-only"></div>
        </main>
    </div>
</div>

{{-- Keyboard Shortcuts Modal --}}
<x-keyboard-shortcuts />

{{-- Help Panel --}}
<div x-data="{ helpOpen: false }" @keydown.escape.window="helpOpen = false">
    <button @click="helpOpen = !helpOpen"
            :aria-expanded="helpOpen"
            aria-label="Open help"
            class="fixed bottom-6 right-6 z-50 h-12 w-12 rounded-full bg-brand text-white shadow-lg hover:bg-brand-strong focus:outline-none focus:ring-2 focus:ring-brand/40 focus:ring-offset-2 transition-all duration-200 flex items-center justify-center text-xl font-bold">?</button>
    <div x-show="helpOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="helpOpen = false" class="fixed inset-0 z-50 bg-ink/20" x-cloak></div>
    <aside x-show="helpOpen"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="translate-x-full"
           class="fixed top-0 right-0 z-50 h-full w-80 max-w-[90vw] bg-surface-2 shadow-2xl flex flex-col border-l border-border/50" x-cloak>
        <div class="flex items-center justify-between px-5 py-4 border-b border-border/50">
            <h2 class="text-lg font-semibold text-ink">Help &amp; Quick Links</h2>
            <button @click="helpOpen = false" aria-label="Close help panel" class="icon-button">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto px-5 py-4">
            <h3 class="text-xs font-bold text-muted uppercase tracking-wider mb-3">Quick Links</h3>
            <ul class="space-y-1">
                @foreach([
                    ['label' => 'PayGro Sync', 'href' => '/settings/paygro', 'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
                    ['label' => 'Create Campaign', 'href' => '/campaigns/create', 'icon' => 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8'],
                    ['label' => 'New Workflow', 'href' => '/workflows/create?guided=1', 'icon' => 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z'],
                    ['label' => 'SMS Templates', 'href' => '/templates', 'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z'],
                    ['label' => 'Email Templates', 'href' => '/email-templates', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                    ['label' => 'View Analytics', 'href' => '/analytics', 'icon' => 'M3 3v18h18M7 14l4-4 3 3 5-6'],
                    ['label' => 'Settings', 'href' => '/settings/paygro', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
                ] as $link)
                <li>
                    <a href="{{ url($link['href']) }}" wire:navigate @click="helpOpen = false" class="nav-item text-sm">
                        <svg viewBox="0 0 24 24" class="h-4 w-4 text-brand shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $link['icon'] }}"/></svg>
                        {{ $link['label'] }}
                    </a>
                </li>
                @endforeach
            </ul>

            <div class="mt-5 pt-4 border-t border-border/50">
                <h3 class="text-xs font-bold text-muted uppercase tracking-wider mb-3">Keyboard Shortcuts</h3>
                <button @click="$dispatch('toggle-shortcuts'); helpOpen = false"
                        class="flex items-center gap-2.5 w-full px-3 py-2 text-sm font-medium text-brand bg-brand/10 rounded-xl hover:bg-brand/15 transition-colors">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h.01M18 8h.01M6 12h.01M10 12h.01M14 12h.01M18 12h.01M6 16h12"/></svg>
                    View All Shortcuts
                </button>
            </div>
        </nav>
        <div class="px-5 py-3 border-t border-border/50 text-[10px] text-muted text-center">
            &copy; {{ date('Y') }} {{ config('app.name') }} — Internal Platform
        </div>
    </aside>
</div>

{{-- Custom confirm modal (replaces browser confirm()) --}}
<div id="mb-confirm-modal" x-data="{ show: false, message: '' }" x-cloak
     @mb-confirm.window="message = $event.detail.message; show = true;"
     @keydown.escape.window="if(show) { show = false; }">
    <template x-teleport="body">
        <div x-show="show" x-transition.opacity.duration.200ms class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="show = false"></div>
            <div x-show="show"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-surface-2 rounded-2xl shadow-xl border border-border w-full max-w-sm p-6 z-10">
                <div class="flex items-start gap-3 mb-5">
                    <div class="w-10 h-10 rounded-xl bg-warning/10 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-ink">Confirm Action</h3>
                        <p class="text-sm text-muted mt-1 leading-relaxed" x-text="message"></p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button @click="show = false"
                            class="px-4 py-2 text-sm font-medium text-muted hover:text-ink rounded-xl hover:bg-surface transition-colors">
                        Cancel
                    </button>
                    <button @click="show = false; window.dispatchEvent(new CustomEvent('mb-confirmed'));"
                            class="px-4 py-2 text-sm font-semibold text-white bg-brand rounded-xl hover:bg-brand-strong transition-colors shadow-sm">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

@livewireScripts

<script>
function appLayout() {
    return {
        theme: localStorage.getItem('theme') || 'dark',
        sidebarOpen: false,
        sidebarCollapsed: localStorage.getItem('sidebar-collapsed') === 'true',

        init() {
            document.documentElement.setAttribute('data-theme', this.theme);
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
            document.documentElement.toggleAttribute('data-sidebar-collapsed', this.sidebarCollapsed);
            this.$watch('sidebarCollapsed', v => {
                localStorage.setItem('sidebar-collapsed', v);
                document.documentElement.toggleAttribute('data-sidebar-collapsed', v);
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                const tag = document.activeElement?.tagName;
                const isInput = ['INPUT','TEXTAREA','SELECT'].includes(tag) || document.activeElement?.isContentEditable;

                if (e.key === '/' && !isInput) {
                    e.preventDefault();
                    document.querySelector('[aria-label="Search customers"]')?.focus();
                }

                if ((e.ctrlKey || e.metaKey) && e.key === '.') {
                    e.preventDefault();
                    this.toggleTheme();
                }
            });
        },

        toggleTheme() {
            this.theme = this.theme === 'light' ? 'dark' : 'light';
            localStorage.setItem('theme', this.theme);
            document.documentElement.setAttribute('data-theme', this.theme);
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
        },
    };
}

// Custom confirm override for wire:confirm
(function() {
    var pendingEl = null;
    window.confirm = function(msg) {
        pendingEl = document.activeElement;
        window.dispatchEvent(new CustomEvent('mb-confirm', { detail: { message: msg } }));
        return false;
    };
    window.addEventListener('mb-confirmed', function() {
        if (!pendingEl) return;
        var el = pendingEl; pendingEl = null;
        window.confirm = function() { return true; };
        el.click();
        setTimeout(function() {
            window.confirm = function(msg) {
                pendingEl = document.activeElement;
                window.dispatchEvent(new CustomEvent('mb-confirm', { detail: { message: msg } }));
                return false;
            };
        }, 300);
    });
})();

// wire:navigate progress bar
(function () {
    const bar = document.getElementById('mb-progress-bar');
    let timer = null;
    function start() {
        if (!bar) return;
        clearTimeout(timer);
        bar.style.transition = 'none'; bar.style.width = '0%';
        bar.classList.add('active'); bar.offsetHeight;
        bar.style.transition = 'width 300ms ease'; bar.style.width = '60%';
        timer = setTimeout(() => { bar.style.width = '80%'; }, 400);
    }
    function finish() {
        if (!bar) return;
        clearTimeout(timer);
        bar.style.transition = 'width 200ms ease'; bar.style.width = '100%';
        setTimeout(() => {
            bar.style.transition = 'opacity 300ms ease';
            bar.classList.remove('active');
            setTimeout(() => { bar.style.width = '0%'; }, 350);
        }, 200);
    }
    document.addEventListener('livewire:navigating', start);
    document.addEventListener('livewire:navigated', finish);
    document.addEventListener('livewire:navigated', () => {
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.classList.toggle('dark', savedTheme === 'dark');
        document.documentElement.setAttribute('data-theme', savedTheme);
    });
})();

// Persist sidebar scroll position across SPA navigations
(function() {
    var nav = document.getElementById('app-sidebar-nav');
    if (!nav) return;
    var saved = sessionStorage.getItem('app-sidebar-scroll');
    if (saved) nav.scrollTop = parseInt(saved, 10);
    nav.addEventListener('scroll', function() { sessionStorage.setItem('app-sidebar-scroll', nav.scrollTop); });
    document.addEventListener('livewire:navigating', function() { sessionStorage.setItem('app-sidebar-scroll', nav.scrollTop); });
})();
</script>
</body>
</html>
