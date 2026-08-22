<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline — {{ config('app.name', 'Dot.Mines') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{--
    The offline shell (hybrid spec Slice 2, brief §11). The service worker
    caches this page and serves it for any navigation while the network is
    down; resources/js/local/offlineSnapshot.js then renders the last-synced
    fleet/production/notification state from IndexedDB. Deliberately
    standalone: no navigation, no Livewire, nothing that needs a server.
--}}
<body class="min-h-screen bg-[var(--ink)] text-[var(--stone)] antialiased">
    <main class="max-w-2xl mx-auto px-4 py-10">
        <div class="flex items-center gap-3 mb-6">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-zinc-500"></span>
            <h1 class="text-xl font-semibold">You're offline</h1>
        </div>
        <p class="text-sm text-[var(--sand)] mb-6">
            Dot.Mines can't reach the server right now. Below is the most recent data
            synchronised to this device. It will refresh automatically once connectivity returns.
        </p>

        <div id="offline-snapshot" class="space-y-4"></div>

        <a href="/dashboard" class="inline-block mt-8 text-sm text-[var(--gold)] hover:underline">
            Try reconnecting &rarr;
        </a>
    </main>
</body>
</html>
