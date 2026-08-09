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
        <!-- Toast Notifications -- listens for the 'notify' browser event that
             every component's dispatchBrowserEvent('notify', ...) call fires.
             This layout had no listener for it at all until now, so every
             success/error confirmation across the app was invisible: the
             underlying action worked, but nothing ever told the user. -->
        <div x-data="{
                notifications: [],
                addNotification(type, message) {
                    const id = Date.now() + Math.random();
                    this.notifications.push({ id, type, message });
                    setTimeout(() => this.removeNotification(id), 5000);
                },
                removeNotification(id) {
                    this.notifications = this.notifications.filter(n => n.id !== id);
                }
            }"
            @notify.window="addNotification($event.detail.type, $event.detail.message)"
            class="fixed top-4 right-4 z-[10000] space-y-2 max-w-md">
            <template x-for="notification in notifications" :key="notification.id">
                <div x-show="true"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform translate-x-8"
                     x-transition:enter-end="opacity-100 transform translate-x-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform translate-x-0"
                     x-transition:leave-end="opacity-0 transform translate-x-8"
                     class="relative rounded-lg shadow-2xl p-4 flex items-start gap-3 backdrop-blur-sm border"
                     :class="{
                        'bg-green-600/90 border-green-500': notification.type === 'success',
                        'bg-red-600/90 border-red-500': notification.type === 'error',
                        'bg-yellow-600/90 border-yellow-500': notification.type === 'warning',
                        'bg-[var(--gold)]/90 border-[var(--gold)]': notification.type === 'info'
                     }">
                    <div class="flex-shrink-0">
                        <template x-if="notification.type === 'success'">
                            <svg class="w-6 h-6 text-[var(--stone)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </template>
                        <template x-if="notification.type === 'error'">
                            <svg class="w-6 h-6 text-[var(--stone)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </template>
                        <template x-if="notification.type === 'warning'">
                            <svg class="w-6 h-6 text-[var(--stone)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </template>
                        <template x-if="notification.type === 'info'">
                            <svg class="w-6 h-6 text-[var(--ink)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </template>
                    </div>
                    <div class="flex-1 font-medium" :class="notification.type === 'info' ? 'text-[var(--ink)]' : 'text-[var(--stone)]'" x-text="notification.message"></div>
                    <button @click="removeNotification(notification.id)" class="flex-shrink-0 transition-colors" :class="notification.type === 'info' ? 'text-[var(--ink)]/70 hover:text-[var(--ink)]' : 'text-[var(--stone)]/70 hover:text-[var(--stone)]'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
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

        <div class="min-h-screen flex">
            <!-- Sidebar Navigation -->
            @livewire('sidebar')

            <!-- Main Content -->
            <div class="flex-1 flex flex-col">
                <!-- Top Navigation -->
                @livewire('navbar')

                <!-- Page Content -->
                <main class="flex-1 overflow-auto bg-[var(--ink)]">
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
    </body>
</html>
