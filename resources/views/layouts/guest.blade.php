<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

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

        <!-- Styles -->
        @livewireStyles

        <style>
            :root {
                --ink: #211a14;
                --ink-soft: #2c2319;
                --umber: #6b4226;
                --gold: #d99e2b;
                --gold-soft: #f0c669;
                --stone: #f4efe4;
                --sand: #c9b896;
                --line: rgba(244, 239, 228, 0.12);
                --font-display: 'Outfit', system-ui, sans-serif;
                --font-body: 'Plus Jakarta Sans', system-ui, sans-serif;
                --font-mono: 'JetBrains Mono', ui-monospace, monospace;
            }
            html { background: var(--ink); }
            body { background: var(--ink); }
            .font-display { font-family: var(--font-display); }
            .font-mono { font-family: var(--font-mono); }
        </style>
    </head>
    <body class="antialiased">
        <div class="font-[Plus_Jakarta_Sans] text-[var(--stone)]" style="font-family: var(--font-body);">
            {{ $slot }}
        </div>

        @livewireScripts
    </body>
</html>
