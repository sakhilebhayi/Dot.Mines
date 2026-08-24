<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="user-id" content="{{ Auth::id() }}">
        <meta name="team-id" content="{{ Auth::user()?->current_team_id ?? Auth::user()?->team_id }}">
        @php($machines = $machines ?? [])

        <title>{{ config('app.name', 'Dot.Mines') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Leaflet CSS is now loaded per-component to avoid Livewire morphdom issues -->

        <!-- Styles -->
        @livewireStyles
        @stack('styles')
        
        <!-- Map Container Fixes -->
        <style nonce="{{ request()->attributes->get('csp_nonce') }}">
            /* Ensure map containers are visible and have proper z-index */
            .leaflet-container {
                background: #1f2937 !important;
                z-index: auto !important;
                height: 100% !important;
                width: 100% !important;
            }
            
            /* Fix map tiles visibility */
            .leaflet-tile-pane {
                z-index: 2;
            }
            
            /* Ensure map controls are visible */
            .leaflet-control-container {
                z-index: 1000;
            }
            
            /* Fix for dark theme */
            .leaflet-container a {
                color: #3b82f6;
            }
            
            /* Make map pane visible */
            .leaflet-pane {
                z-index: auto;
            }
        </style>
    </head>
    <body class="antialiased bg-[var(--ink)] text-[var(--stone)]" style="font-family: var(--font-body);">
        <!-- Keyboard users skip the sidebar/navbar on every page load -->
        <a href="#main-content"
           class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:top-2 focus:left-2 focus:px-4 focus:py-2 focus:bg-[var(--gold)] focus:text-[var(--ink)] focus:rounded-lg">
            Skip to main content
        </a>
        <x-banner />

        {{-- Offline is not the same as "no data": when the BROWSER loses its
             connection, say so explicitly instead of letting stale panels or
             failing polls masquerade as empty data (UX brief §8/§19). Plain
             browser online/offline events -- works with or without Livewire. --}}
        <div x-data="{ offline: ! navigator.onLine }"
             x-on:offline.window="offline = true"
             x-on:online.window="offline = false"
             x-show="offline"
             x-cloak
             class="fixed top-0 inset-x-0 z-[60] bg-amber-500 text-[var(--ink)] text-sm font-medium text-center py-1.5"
             role="alert">
            No connection — showing last known data. Reconnecting automatically…
        </div>

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
        @if ($errors->any())
            @foreach ($errors->all() as $error)
                <div x-data x-init="$dispatch('notify', { type: 'error', message: @js($error) })"></div>
            @endforeach
        @endif
        
        <!-- Mobile nav backdrop -->
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
                    <div class="p-6 page-transition">
                        @yield('content')
                        @isset($slot)
                            {{ $slot }}
                        @endisset
                    </div>
                </main>
            </div>
        </div>

        @stack('modals')

        {{-- Livewire owns Alpine: its script assigns window.Alpine and starts it.
             Do not mark window.Alpine with __fromLivewire here -- that hack only
             existed to silence the "multiple instances of Alpine" warning while
             app.js was overwriting window.Alpine with a second copy, which broke
             entangle() (see resources/js/app.js). The warning must stay audible. --}}
        @livewireScripts

        <script nonce="{{ request()->attributes->get('csp_nonce') }}">
            // Ensure fetch requests include CSRF token and X-Requested-With for servers
            // that require these headers (helps Livewire POSTs avoid 403).
            (function() {
                const meta = document.querySelector('meta[name="csrf-token"]');
                const csrf = meta ? meta.getAttribute('content') : null;
                if (!window._fetchWithCsrf) {
                    const _origFetch = window.fetch.bind(window);
                    window.fetch = function(resource, init = {}) {
                        try {
                            const url = (typeof resource === 'string') ? resource : resource.url;
                            const sameOrigin = url && (new URL(url, window.location.href)).origin === window.location.origin;

                            if (sameOrigin) {
                                init.headers = init.headers || {};
                                if (csrf && !init.headers['X-CSRF-TOKEN'] && !init.headers['x-csrf-token']) {
                                    init.headers['X-CSRF-TOKEN'] = csrf;
                                }
                                if (!init.headers['X-Requested-With'] && !init.headers['x-requested-with']) {
                                    init.headers['X-Requested-With'] = 'XMLHttpRequest';
                                }
                            }
                        } catch (e) {
                            // ignore
                        }
                        return _origFetch(resource, init);
                    };
                    window._fetchWithCsrf = true;
                }
            })();
        </script>

        <!-- Leaflet JS is now loaded per-component to avoid Livewire morphdom issues -->
        @stack('scripts')
        
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
