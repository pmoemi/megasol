<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="h-full"
      x-data="{ theme: localStorage.getItem('theme') || 'dark' }"
      :class="{ dark: theme === 'dark' }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Sign in' }} — {{ config('app.name') }}</title>

    @if ($favicon = \App\Support\AppTheme::faviconUrl())
        <link rel="icon" href="{{ $favicon }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <script>
        (function() {
            const t = localStorage.getItem('theme') || 'dark';
            document.documentElement.classList.toggle('dark', t === 'dark');
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body class="h-full bg-surface text-ink antialiased font-sans">

<div class="min-h-screen flex">
    {{-- Left decorative panel --}}
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-brand via-brand-strong to-accent relative overflow-hidden">
        {{-- Decorative blobs --}}
        <div class="absolute top-[-10%] right-[-10%] w-80 h-80 rounded-full bg-white/10 blur-3xl"></div>
        <div class="absolute bottom-[-5%] left-[-5%] w-64 h-64 rounded-full bg-white/5 blur-2xl"></div>

        <div class="relative z-10 flex flex-col justify-between p-12 text-white w-full">
            {{-- Logo --}}
            <div class="flex items-center gap-3">
                @if ($logo = \App\Support\AppTheme::logoUrl())
                    <img src="{{ $logo }}" alt="{{ config('app.name') }}" class="flex h-10 w-10 rounded-2xl bg-white/20 backdrop-blur-sm object-contain p-1">
                @else
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm">
                        <svg viewBox="0 0 24 24" class="h-6 w-6 text-white" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                @endif
                <span class="text-2xl font-bold tracking-tight">{{ config('app.name') }}</span>
            </div>

            {{-- Tagline --}}
            <div class="space-y-5">
                <h1 class="text-4xl font-bold leading-tight">
                    Customer SMS<br>Engagement Platform
                </h1>
                <p class="text-lg text-white/80 leading-relaxed">
                    Automated payment reminders, bulk campaigns, and customer messaging powered by Africa's Talking.
                </p>

                {{-- Feature highlights --}}
                <div class="space-y-3 pt-2">
                    @foreach([
                        ['icon' => 'M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'text' => 'Africa\'s Talking SMS gateway'],
                        ['icon' => 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8', 'text' => 'Bulk campaigns with smart targeting'],
                        ['icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'text' => 'Visual workflow automation'],
                        ['icon' => 'M3 3v18h18M7 14l4-4 3 3 5-6', 'text' => 'Real-time analytics & reporting'],
                    ] as $feat)
                    <div class="flex items-center gap-3 text-white/90">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/15">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="{{ $feat['icon'] }}"/></svg>
                        </div>
                        <span class="text-sm font-medium">{{ $feat['text'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <p class="text-white/50 text-sm">{{ config('app.name') }} internal communications platform &mdash; authorized personnel only.</p>
        </div>
    </div>

    {{-- Right auth panel --}}
    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:px-12 bg-surface-2 relative">
        {{-- Theme toggle --}}
        <div class="absolute top-4 right-4">
            <button type="button"
                    @click="theme = theme === 'dark' ? 'light' : 'dark'; localStorage.setItem('theme', theme); document.documentElement.classList.toggle('dark', theme === 'dark'); document.documentElement.setAttribute('data-theme', theme)"
                    class="icon-button"
                    :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'">
                <svg x-show="theme === 'dark'" x-cloak viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
                <svg x-show="theme !== 'dark'" x-cloak viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>
        </div>

        <div class="w-full max-w-md">
            {{-- Mobile logo --}}
            <div class="lg:hidden mb-8 flex items-center gap-3">
                @if ($logo = \App\Support\AppTheme::logoUrl())
                    <img src="{{ $logo }}" alt="{{ config('app.name') }}" class="flex h-9 w-9 rounded-xl object-contain">
                @else
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand text-white">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                @endif
                <span class="text-xl font-bold tracking-tight text-ink">{{ config('app.name') }}</span>
            </div>

            {{ $slot }}
        </div>
    </div>
</div>

@livewireScripts
</body>
</html>
