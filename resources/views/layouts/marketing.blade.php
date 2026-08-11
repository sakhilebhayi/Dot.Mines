<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth" style="background: var(--ink);">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', 'Dot.Mines') &mdash; Dot.Mines</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-[var(--ink)] text-[var(--stone)]" style="font-family: var(--font-body);">

        <!-- Nav -- same header as the homepage, so every public page tells
             one consistent story. This used to extend layouts.app, which
             embeds the authenticated app's sidebar/navbar (including
             AiNotifications, which reads auth()->user()->currentTeam) --
             a 500 error for any guest, since these pages carry no auth
             middleware at all. -->
        <header
            x-data="{ scrolled: false, mobileMenuOpen: false }"
            @scroll.window="scrolled = window.pageYOffset > 24"
            :class="scrolled ? 'bg-[#211a14]/95 backdrop-blur-md border-b border-[var(--line)]' : 'border-b border-[var(--line)]'"
            class="sticky top-0 left-0 right-0 z-50 transition-colors duration-300"
        >
            <nav class="max-w-[1400px] mx-auto px-5 sm:px-8 py-3 flex items-center justify-between">
                <a href="/" class="flex items-center gap-2.5 press">
                    <img src="{{ asset('images/logo-light.png') }}" alt="Dot.Mines" class="h-14 sm:h-16 w-auto">
                </a>

                <div class="hidden md:flex items-center gap-8 font-mono text-[13px] tracking-wide uppercase text-[var(--sand)]">
                    <a href="{{ route('welcome') }}#capabilities" class="hover:text-[var(--stone)] pb-0.5">Product</a>
                    <a href="{{ route('welcome') }}#features" class="hover:text-[var(--stone)] pb-0.5">Features</a>
                    <a href="{{ route('pricing') }}" class="hover:text-[var(--stone)] pb-0.5">Pricing</a>
                </div>

                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="flex items-center gap-2 px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#211a14] text-sm font-display font-semibold rounded-lg transition-colors">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="hidden sm:block text-sm font-medium text-[var(--sand)] hover:text-[var(--stone)] transition-colors">
                            Sign in
                        </a>
                        <a href="{{ route('register') }}" class="px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#211a14] text-sm font-display font-semibold rounded-lg transition-colors">
                            Start free trial
                        </a>
                    @endauth

                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 -mr-2 text-[var(--stone)]" aria-label="Toggle menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 7h16M4 12h16M4 17h16"></path>
                            <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </nav>

            <div x-show="mobileMenuOpen"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="md:hidden border-t border-[var(--line)] bg-[#211a14]"
                 style="display: none;">
                <div class="flex flex-col px-5 py-4 gap-1 font-mono text-sm uppercase tracking-wide">
                    <a href="{{ route('welcome') }}#capabilities" class="px-3 py-2.5 text-[var(--sand)] hover:text-[var(--stone)]">Product</a>
                    <a href="{{ route('welcome') }}#features" class="px-3 py-2.5 text-[var(--sand)] hover:text-[var(--stone)]">Features</a>
                    <a href="{{ route('pricing') }}" class="px-3 py-2.5 text-[var(--sand)] hover:text-[var(--stone)]">Pricing</a>
                    @guest
                        <a href="{{ route('login') }}" class="px-3 py-2.5 text-[var(--sand)] hover:text-[var(--stone)]">Sign in</a>
                    @endguest
                </div>
            </div>
        </header>

        <main>
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="py-14 px-5 sm:px-8 border-t border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-6">
                <a href="/" class="flex items-center gap-2.5">
                    <img src="{{ asset('images/logo-light.png') }}" alt="Dot.Mines" class="h-11 w-auto opacity-90">
                </a>
                <div class="flex items-center gap-6 font-mono text-xs tracking-wide uppercase text-[var(--sand)]">
                    <a href="{{ route('pricing') }}" class="hover:text-[var(--stone)] transition-colors">Pricing</a>
                    <a href="{{ route('policy.show') }}" class="hover:text-[var(--stone)] transition-colors">Privacy</a>
                    <a href="{{ route('cookies') }}" class="hover:text-[var(--stone)] transition-colors">Cookies</a>
                    <a href="{{ route('terms.show') }}" class="hover:text-[var(--stone)] transition-colors">Terms</a>
                </div>
                <p class="font-mono text-xs tracking-wide text-[var(--sand)]">
                    &copy; {{ date('Y') }} Dot.Mines. Fleet operations software for mining.
                </p>
            </div>
        </footer>

        @livewireScripts
    </body>
</html>
