<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="user-id" content="{{ Auth::id() }}">
        <meta name="team-id" content="{{ Auth::user()?->current_team_id ?? Auth::user()?->team_id }}">
        @php($machines = $machines ?? [])

        {{-- Anti-FOUC: apply the correct theme class before ANY CSS or JS is parsed. --}}
        <script nonce="{{ request()->attributes->get('csp_nonce') }}">
            (function () {
                var mode = localStorage.getItem('theme-mode');
                var isDark;
                if (mode === 'dark') {
                    isDark = true;
                } else if (mode === 'light') {
                    isDark = false;
                } else {
                    // 'system' or not set — follow OS preference, defaulting to dark.
                    isDark = !window.matchMedia || window.matchMedia('(prefers-color-scheme: dark)').matches;
                }
                var html = document.documentElement;
                if (isDark) {
                    html.classList.add('dark');
                    html.setAttribute('data-theme', 'dark');
                } else {
                    html.classList.remove('dark');
                    html.setAttribute('data-theme', 'light');
                }
            }());
        </script>

        <title>@hasSection('title')@yield('title') | {{ config('app.name', 'Mines') }}@else{{ config('app.name', 'Mines') }}@endif</title>
        <meta name="description" content="@yield('description', 'Mines mining operations management platform.')">
        <meta name="robots" content="noindex, nofollow">
        <link rel="canonical" href="{{ url()->current() }}">

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' fill='%23f59e0b' rx='15'/><path d='M20 45 L20 30 L35 37 L35 52 L20 45 M35 52 L50 45 L50 60 L35 67 L35 52 M50 60 L65 53 L65 68 L50 75 L50 60 M35 37 L50 30 L50 45 L35 52 L35 37 M50 45 L65 38 L65 53 L50 60 L50 45 M50 30 L65 23 L80 30 L65 38 L50 30' fill='%231e293b' stroke='%231e293b' stroke-width='2'/></svg>">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Leaflet CSS is now loaded per-component to avoid Livewire morphdom issues -->

        <!-- Styles -->
        @livewireStyles
        @stack('styles')
        
        <!-- Map Container Fixes -->
        <style>
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
    <body class="font-sans antialiased">
        <x-banner />
        
        <!-- Notification System -->
        <div x-data="{ 
            notifications: [],
            addNotification(type, message) {
                const id = Date.now();
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
        class="fixed top-4 right-4 z-[10000] space-y-2 max-w-md">
            <template x-for="notification in notifications" :key="notification.id">
                <div 
                    x-show="true"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 transform translate-x-8"
                    x-transition:enter-end="opacity-100 transform translate-x-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 transform translate-x-0"
                    x-transition:leave-end="opacity-0 transform translate-x-8"
                    class="relative rounded-lg shadow-2xl p-4 flex items-start gap-3 backdrop-blur-sm"
                    :class="{
                        'bg-green-600/90 border border-green-500': notification.type === 'success',
                        'bg-red-600/90 border border-red-500': notification.type === 'error',
                        'bg-yellow-600/90 border border-yellow-500': notification.type === 'warning',
                        'bg-blue-600/90 border border-blue-500': notification.type === 'info'
                    }">
                    <!-- Icon -->
                    <div class="flex-shrink-0">
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
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </template>
                    </div>
                    
                    <!-- Message -->
                    <div class="flex-1 text-white font-medium" x-text="notification.message"></div>
                    
                    <!-- Close Button -->
                    <button 
                        @click="removeNotification(notification.id)"
                        class="flex-shrink-0 text-white hover:text-gray-200 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </template>
        </div>

        <!-- Flash Messages Handler -->
        @if (session('success'))
            <div x-data x-init="$dispatch('notify', { type: 'success', message: '{{ session('success') }}' })"></div>
        @endif
        @if (session('error'))
            <div x-data x-init="$dispatch('notify', { type: 'error', message: '{{ session('error') }}' })"></div>
        @endif
        @if (session('warning'))
            <div x-data x-init="$dispatch('notify', { type: 'warning', message: '{{ session('warning') }}' })"></div>
        @endif
        @if (session('info'))
            <div x-data x-init="$dispatch('notify', { type: 'info', message: '{{ session('info') }}' })"></div>
        @endif
        @if ($errors->any())
            @foreach ($errors->all() as $error)
                <div x-data x-init="$dispatch('notify', { type: 'error', message: '{{ $error }}' })"></div>
            @endforeach
        @endif
        
        <!-- Global Loading Bar -->
        <div 
            x-data="{ loading: false }"
            x-init="
                Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
                    loading = true;
                });
                Livewire.hook('commit.pooling', () => { loading = false; });
            "
            x-show="loading"
            x-transition:enter="transition-opacity duration-200"
            x-transition:leave="transition-opacity duration-200"
            class="fixed top-0 left-0 right-0 z-[9999]"
            style="display: none;"
        >
            <div class="h-0.5 bg-amber-500"></div>
        </div>

        <!-- Global Loading Overlay (for longer operations) -->
        <div 
            x-data="{ loading: false }"
            x-init="
                Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
                    setTimeout(() => { loading = true; }, 500);
                });
                Livewire.hook('commit.pooling', () => { loading = false; });
            "
            x-show="loading"
            x-transition:enter="transition-all duration-300 ease-out"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition-all duration-200 ease-in"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[9998] flex items-center justify-center"
            style="display: none;"
        >
            <div class="bg-slate-800 rounded-xl p-8 shadow-2xl flex flex-col items-center gap-4 transform transition-all duration-300">
                <div class="relative">
                    <svg class="animate-spin h-16 w-16 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <p class="text-white font-medium text-lg">Processing...</p>
                <p class="text-gray-400 text-sm">Please wait a moment</p>
            </div>
        </div>

        <div class="min-h-screen flex">
            <!-- Sidebar Navigation -->
            @livewire('sidebar')

            <!-- Main Content -->
            <div class="flex-1 flex flex-col">
                <!-- Top Navigation -->
                @livewire('navbar')

                <!-- Page Content -->
                <main class="flex-1 overflow-auto bg-gray-900">
                    <div class="p-6 page-transition">
                        @yield('content')
                        @isset($slot)
                            {{ $slot }}
                        @endisset
                    </div>
                </main>

                <!-- Footer -->
                <footer class="bg-gray-900 border-t border-gray-800 py-3 px-6 text-center text-xs text-gray-500">
                    &copy; {{ date('Y') }} {{ config('app.name', 'Mines') }}. All rights reserved.
                    <span class="mx-2">&middot;</span>
                    <a href="{{ route('policy.show') }}" class="hover:text-gray-300 transition-colors">Privacy Policy</a>
                    <span class="mx-2">&middot;</span>
                    <a href="{{ route('terms.show') }}" class="hover:text-gray-300 transition-colors">Terms of Service</a>
                </footer>
            </div>
        </div>

        @stack('modals')

        <script>
            // Prevent Livewire from warning about multiple Alpine instances when Alpine
            // is bundled by Vite (`resources/js/app.js`). We mark the global Alpine
            // object as coming from Livewire so Livewire won't attempt to re-initialize it.
            (function(){
                if (window.Alpine) {
                    window.Alpine.__fromLivewire = true;
                }

                // If Alpine initializes later, ensure we set the flag when it does.
                document.addEventListener('alpine:initialized', function(){
                    if (window.Alpine) window.Alpine.__fromLivewire = true;
                });
            })();
        </script>

        @livewireScripts

        <script>
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

        <!-- Cookie Consent -->
        <x-cookie-consent />
    </body>
</html>
