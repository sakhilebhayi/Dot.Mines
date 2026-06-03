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

        {{-- Anti-FOUC: apply correct theme class before CSS loads --}}
        <script>
            (function () {
                var mode = localStorage.getItem('theme-mode');
                var isDark;
                if (mode === 'dark') {
                    isDark = true;
                } else if (mode === 'light') {
                    isDark = false;
                } else {
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

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex">
            <!-- Sidebar Navigation -->
            @livewire('sidebar')

            <!-- Main Content -->
            <div class="flex-1 flex flex-col">
                <!-- Top Navigation -->
                @livewire('navbar')

                <!-- Page Content -->
                <main class="flex-1 overflow-auto bg-gray-900">
                    <div class="p-6">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        @stack('modals')

        <!-- Cookie Consent -->
        <x-cookie-consent />

        @livewireScripts
        <!-- Alpine is bundled via Vite in resources/js/app.js; avoid double-loading CDN version -->
    </body>
</html>
