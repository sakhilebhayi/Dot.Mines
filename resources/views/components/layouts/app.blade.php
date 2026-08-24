<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @auth
        <meta name="user-id" content="{{ Auth::id() }}">
        <meta name="team-id" content="{{ Auth::user()->current_team_id ?? Auth::user()->team_id }}">
        @endauth

        <title>{{ config('app.name', 'Dot.Mines') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <!-- Fonts — matches the guest/marketing pages (welcome.blade.php, layouts/guest.blade.php) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="antialiased bg-[var(--ink)] text-[var(--stone)]" style="font-family: var(--font-body);">
        <!-- Keyboard users skip the sidebar/navbar on every page load -->
        <a href="#main-content"
           class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:top-2 focus:left-2 focus:px-4 focus:py-2 focus:bg-[var(--gold)] focus:text-[var(--ink)] focus:rounded-lg">
            Skip to main content
        </a>
        <x-toast-host />

                <!-- Flash Messages Handler -->
        @if (session('success'))
            <div x-data x-init="$dispatch('notify', { type: 'success', message: @js(session('success')) })"></div>
        @endif
        @if (session('error'))
            <div x-data x-init="$dispatch('notify', { type: 'error', message: @js(session('error')) })"></div>
        @endif
        @if (session('warning'))
            <div x-data x-init="$dispatch('notify', { type: 'warning', message: @js(session('warning')) })"></div>
        @endif
        @if (session('info'))
            <div x-data x-init="$dispatch('notify', { type: 'info', message: @js(session('info')) })"></div>
        @endif

        <!-- Mobile nav backdrop -- kept in sync with the same block in the
             sibling resources/views/layouts/app.blade.php (see wiki.md for
             why this file is duplicated). Corrected: this file is NOT dead
             code -- config('livewire.layout') = 'components.layouts.app'
             makes it the default layout for every Livewire component bound
             directly as a route target with no #[Layout(...)] override,
             which includes /alerts, /fuel, /production, /maintenance,
             /operator-fatigue, /mine-areas/{id}(/assignments),
             /ai-optimization, /ai-analytics, /documentation, /billing, and
             /fleet/replay(/route-planning) -- confirmed live by hitting
             /alerts and finding this file's own fonts-comment marker in the
             response. Those pages had no mobile-nav backdrop at all before
             this block was added. -->
        <div
            x-data="{ mobileOpen: false }"
            @mobile-nav-changed.window="mobileOpen = $event.detail.open"
            x-show="mobileOpen"
            x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="window.mobileNav.close()"
            class="fixed inset-0 bg-black/60 z-[45] md:hidden"
            style="display: none;"
            aria-hidden="true"
        ></div>

        <div class="min-h-screen flex">
            <!-- Sidebar Navigation -->
            @livewire('sidebar')

            <!-- Main Content -->
            <div class="flex-1 flex flex-col min-w-0">
                <!-- Top Navigation -->
                @livewire('navbar')

                <!-- Page Content -->
                <main id="main-content" class="flex-1 overflow-auto bg-[var(--ink)]">
                    @isset($header)
                        <div class="border-b border-[var(--line)] px-6 py-4">
                            {{ $header }}
                        </div>
                    @endisset

                    <div class="p-6">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        @stack('modals')

        @livewireScripts
        <!-- Alpine is bundled via Vite in resources/js/app.js; avoid double-loading CDN version -->
            @auth

        <script nonce="{{ request()->attributes->get('csp_nonce') }}">
            window.__syncContext = {
                userId: {{ (int) auth()->id() }},
                teamId: {{ (int) auth()->user()->current_team_id }},
            };
        </script>
        @endauth
        <x-connectivity-pill />
</body>
</html>
