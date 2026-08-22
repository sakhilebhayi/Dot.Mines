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

        <!-- Notification System -->
        <div x-data="{
            notifications: [],
            addNotification(type, message) {
                // Date.now() alone collides when two notify events fire in
                // the same millisecond (confirmed live: 3 simultaneous
                // toasts all got the identical id) -- Alpine's x-for :key
                // then treats them as the same tracked element and drops
                // all but one. Matches the sibling components/layouts/app.blade.php
                // copy, which already avoided this.
                const id = Date.now() + Math.random();
                this.notifications.push({ id, type, message });
                setTimeout(() => {
                    this.removeNotification(id);
                }, 5000);
            },
            removeNotification(id) {
                this.notifications = this.notifications.filter(n => n.id !== id);
            }
        }"
        @notify.window="addNotification($event.detail.type, $event.detail.message)"
        @keydown.escape.window="notifications = []"
        aria-label="Notifications"
        class="fixed top-24 right-4 z-[1200] space-y-2 max-w-md">
            <template x-for="notification in notifications" :key="notification.id">
                <div
                    x-show="true"
                    :role="notification.type === 'error' ? 'alert' : 'status'"
                    :aria-live="notification.type === 'error' ? 'assertive' : 'polite'"
                    aria-atomic="true"
                    x-transition:enter="transition ease-out duration-300 motion-reduce:duration-0"
                    x-transition:enter-start="opacity-0 transform translate-x-8 motion-reduce:translate-x-0"
                    x-transition:enter-end="opacity-100 transform translate-x-0"
                    x-transition:leave="transition ease-in duration-200 motion-reduce:duration-0"
                    x-transition:leave-start="opacity-100 transform translate-x-0"
                    x-transition:leave-end="opacity-0 transform translate-x-8 motion-reduce:translate-x-0"
                    class="relative rounded-lg shadow-2xl p-4 flex items-start gap-3 backdrop-blur-sm"
                    :class="{
                        'bg-green-600/90 border border-green-500': notification.type === 'success',
                        'bg-red-600/90 border border-red-500': notification.type === 'error',
                        'bg-yellow-600/90 border border-yellow-500': notification.type === 'warning',
                        'bg-[var(--gold)]/90 border border-[var(--gold)]': notification.type === 'info'
                    }">
                    <!-- Icon. Note: the info-type toast (bg-[var(--gold)], an
                         amber/mid-tone) needs dark icon+text -- --stone
                         (#f4efe4, near-white) on gold is a real contrast
                         failure that the sibling components/layouts/app.blade.php
                         copy already avoided by special-casing info to
                         text-[var(--ink)]. Matched here. -->
                    <div class="flex-shrink-0" aria-hidden="true">
                        <template x-if="notification.type === 'success'">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </template>
                        <template x-if="notification.type === 'error'">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </template>
                        <template x-if="notification.type === 'warning'">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </template>
                        <template x-if="notification.type === 'info'">
                            <svg class="w-6 h-6 text-[var(--ink)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </template>
                        <span class="sr-only" x-text="{success: 'Success', error: 'Error', warning: 'Warning', info: 'Info'}[notification.type]"></span>
                    </div>

                    <!-- Message -->
                    <div class="flex-1 font-medium" :class="notification.type === 'info' ? 'text-[var(--ink)]' : 'text-[var(--stone)]'" x-text="notification.message"></div>

                    <!-- Close Button -->
                    <button
                        @click="removeNotification(notification.id)"
                        aria-label="Dismiss notification"
                        class="flex-shrink-0 transition-colors"
                        :class="notification.type === 'info' ? 'text-[var(--ink)]/70 hover:text-[var(--ink)]' : 'text-[var(--stone)]/70 hover:text-[var(--stone)]'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </template>
        </div>

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
    </body>
</html>
