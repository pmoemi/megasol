<x-layouts.app :title="$title ?? 'Settings'">
    <div class="flex flex-col lg:flex-row gap-6">
        <aside class="lg:w-52 flex-shrink-0">
            <nav class="bg-surface-2 border border-border rounded-2xl p-3 lg:sticky lg:top-24 space-y-1"
                 x-data="{ mobileOpen: false }">
                <button @click="mobileOpen = !mobileOpen"
                        class="lg:hidden w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium text-ink">
                    <span>Settings Menu</span>
                    <svg class="w-4 h-4 text-muted transition-transform" :class="mobileOpen && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div :class="mobileOpen ? 'block' : 'hidden lg:block'" class="space-y-1">
                    @php
                    $settingsNav = [
                        ['label' => 'General', 'href' => route('settings.general'), 'icon' => '<path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><circle cx="12" cy="12" r="3"></circle>'],
                        ['label' => 'Branding', 'href' => route('settings.branding'), 'icon' => '<path d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>'],
                        ['label' => 'Theme Studio', 'href' => route('settings.theme-studio'), 'icon' => '<circle cx="13.5" cy="6.5" r="2.5"></circle><circle cx="17.5" cy="10.5" r="2.5"></circle><circle cx="8.5" cy="7.5" r="2.5"></circle><circle cx="6.5" cy="12.5" r="2.5"></circle><path d="M12 2a10 10 0 100 20 1.5 1.5 0 001.06-2.56A1.5 1.5 0 0114 18a2 2 0 012-2h2a4 4 0 004-4 10 10 0 00-10-10z"></path>'],
                        ['label' => 'PayGro', 'href' => route('settings.paygro'), 'icon' => '<path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'],
                        ['label' => 'SMS Gateway', 'href' => route('settings.sms'), 'icon' => '<path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>'],
                        ['label' => 'Email / SMTP', 'href' => route('settings.email'), 'icon' => '<path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>'],
                        ['label' => 'Profile', 'href' => route('settings.profile'), 'icon' => '<circle cx="12" cy="8" r="4"></circle><path d="M5 21v-1a7 7 0 0 1 14 0v1"></path>'],
                        ['label' => 'Security', 'href' => route('settings.security'), 'icon' => '<rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0110 0v4"></path>'],
                        ['label' => 'Notifications', 'href' => route('settings.notifications'), 'icon' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>'],
                    ];
                    @endphp

                    @foreach($settingsNav as $item)
                    @php
                        $path = parse_url($item['href'], PHP_URL_PATH) ?? $item['href'];
                        $basePath = parse_url(url('/'), PHP_URL_PATH) ?? '';
                        if ($basePath && $basePath !== '/' && str_starts_with($path, $basePath)) {
                            $path = substr($path, strlen($basePath));
                        }
                        $path = ltrim($path, '/');
                        $active = $path !== '' && (request()->is($path) || request()->is($path.'/*'));
                    @endphp
                    <a href="{{ $item['href'] }}" wire:navigate
                       class="nav-item {{ $active ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">@safeSvg($item['icon'])</svg>
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                    @endforeach
                </div>
            </nav>
        </aside>

        <div class="flex-1 min-w-0">
            {{ $slot }}
        </div>
    </div>
</x-layouts.app>
