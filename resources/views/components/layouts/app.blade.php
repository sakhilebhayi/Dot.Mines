<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark dot-roboto">
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

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('layouts._dot-ui-head')

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="antialiased bg-[var(--ink)] text-[var(--stone)]">
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

        @livewire('navbar')

        <x-shell :header="$header ?? null">
            {{ $slot }}
        </x-shell>

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
</body>
</html>
