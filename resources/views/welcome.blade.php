<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dot.Mines — Fleet operations software for mining</title>
        <meta name="description" content="Track every truck, drill, and loader in real time. Catch maintenance before it becomes downtime. One live view of the fleet, built for mining operations.">

        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

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
                --ease-out: cubic-bezier(0.23, 1, 0.32, 1);
            }
            html { background: var(--ink); }
            body { font-family: var(--font-body); background: var(--ink); color: var(--stone); }
            .font-display { font-family: var(--font-display); }
            .font-mono { font-family: var(--font-mono); }

            .press { transition: transform 160ms var(--ease-out); }
            .press:active { transform: scale(0.97); }

            @media (prefers-reduced-motion: no-preference) {
                .reveal {
                    opacity: 0;
                    transform: translateY(14px);
                    transition: opacity 600ms var(--ease-out), transform 600ms var(--ease-out);
                }
                .reveal.is-visible { opacity: 1; transform: translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .reveal { opacity: 1; transform: none; }
            }

            @media (hover: hover) and (pointer: fine) {
                .row-hover:hover { background: rgba(244, 239, 228, 0.03); }
                .link-underline { background-size: 0% 1px; }
                .link-underline:hover { background-size: 100% 1px; }
            }
            .link-underline {
                background-image: linear-gradient(currentColor, currentColor);
                background-position: 0 100%;
                background-repeat: no-repeat;
                transition: background-size 220ms var(--ease-out);
            }
        </style>
    </head>
    <body class="antialiased">

        <!-- Nav -->
        <header
            x-data="{ scrolled: false, mobileMenuOpen: false }"
            @scroll.window="scrolled = window.pageYOffset > 24"
            :class="scrolled ? 'bg-[#211a14]/95 backdrop-blur-md border-b border-[var(--line)]' : 'border-b border-transparent'"
            class="fixed top-0 left-0 right-0 z-50 transition-colors duration-300"
        >
            <nav class="max-w-[1400px] mx-auto px-5 sm:px-8 py-3 flex items-center justify-between">
                <a href="/" class="flex items-center gap-2.5 press">
                    <img src="{{ asset('images/logo-light.png') }}" alt="Dot.Mines" class="h-16 sm:h-20 w-auto">
                </a>

                <div class="hidden md:flex items-center gap-8 font-mono text-[13px] tracking-wide uppercase text-[var(--sand)]">
                    <a href="#capabilities" class="link-underline hover:text-[var(--stone)] pb-0.5">Product</a>
                    <a href="#features" class="link-underline hover:text-[var(--stone)] pb-0.5">Features</a>
                    <a href="{{ route('pricing') }}" class="link-underline hover:text-[var(--stone)] pb-0.5">Pricing</a>
                </div>

                @if (Route::has('login'))
                    <div class="flex items-center gap-3">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="press flex items-center gap-2 px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#211a14] text-sm font-display font-semibold rounded-lg transition-colors">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="hidden sm:block text-sm font-medium text-[var(--sand)] hover:text-[var(--stone)] transition-colors">
                                Sign in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="press px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#211a14] text-sm font-display font-semibold rounded-lg transition-colors">
                                    Start free trial
                                </a>
                            @endif
                        @endauth

                        <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden press p-2 -mr-2 text-[var(--stone)]" aria-label="Toggle menu">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 7h16M4 12h16M4 17h16"></path>
                                <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                @endif
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
                    <a href="#capabilities" class="px-3 py-2.5 text-[var(--sand)] hover:text-[var(--stone)]">Product</a>
                    <a href="#features" class="px-3 py-2.5 text-[var(--sand)] hover:text-[var(--stone)]">Features</a>
                    <a href="{{ route('pricing') }}" class="px-3 py-2.5 text-[var(--sand)] hover:text-[var(--stone)]">Pricing</a>
                    @guest
                        <a href="{{ route('login') }}" class="px-3 py-2.5 text-[var(--sand)] hover:text-[var(--stone)]">Sign in</a>
                    @endguest
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative min-h-[100dvh] flex items-end overflow-hidden">
            <!-- Photo: excavators at a mine, by Dominik Vanyi, unsplash.com/photos/Mk2ls9UBO2E -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1523848309072-c199db53f137?q=80&w=2400&auto=format&fit=crop');"></div>
            <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(33,26,20,0.55) 0%, rgba(33,26,20,0.72) 45%, #211a14 92%);"></div>
            <div class="absolute inset-0" style="background: linear-gradient(90deg, #211a14 0%, rgba(33,26,20,0.55) 38%, transparent 68%);"></div>

            <!-- Headframe silhouette — line-art nod to the real headframe icon in the Dot.Mines mark -->
            <svg class="hidden lg:block absolute right-[6%] bottom-0 h-[78%] w-auto opacity-[0.14] pointer-events-none" viewBox="0 0 200 320" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M60 320V60L100 10L140 60V320" stroke="#f4efe4" stroke-width="3"/>
                <path d="M60 60H140" stroke="#f4efe4" stroke-width="3"/>
                <path d="M78 60V320M122 60V320" stroke="#f4efe4" stroke-width="1.5"/>
                <circle cx="100" cy="42" r="18" stroke="#f4efe4" stroke-width="3"/>
                <path d="M60 120L140 190M140 120L60 190M60 200L140 270M140 200L60 270" stroke="#f4efe4" stroke-width="1.5"/>
                <rect x="70" y="8" width="60" height="14" stroke="#f4efe4" stroke-width="3"/>
            </svg>

            <div class="relative z-10 max-w-[1400px] mx-auto px-5 sm:px-8 pt-32 pb-16 sm:pb-20 w-full">
                <div class="max-w-2xl reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--gold)] mb-6">
                        Fleet operations software
                    </p>

                    <h1 class="font-display font-bold text-4xl sm:text-5xl lg:text-6xl leading-[1.05] tracking-tight text-[var(--stone)] mb-6">
                        Run the pit.<br>Not the paperwork.
                    </h1>

                    <p class="text-lg text-[var(--sand)] leading-relaxed max-w-xl mb-10">
                        Track every truck, drill, and loader in real time. Catch maintenance before it becomes downtime. Dot.Mines replaces the whiteboard, the spreadsheet, and the radio call with one live view of the fleet.
                    </p>

                    @guest
                        <div class="flex flex-wrap items-center gap-4">
                            <a href="{{ route('register') }}" class="press px-7 py-3.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#211a14] font-display font-semibold rounded-lg transition-colors">
                                Start free trial
                            </a>
                            <a href="#features" class="press flex items-center gap-2 px-7 py-3.5 text-[var(--stone)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--sand)] transition-colors">
                                See what it tracks
                            </a>
                        </div>
                    @endguest
                </div>
            </div>

            <!-- Live data strip — instrumentation-styled, not a fabricated metric -->
            <div class="relative z-10 w-full border-t border-[var(--line)] bg-[#211a14]/60 backdrop-blur-sm">
                <div class="max-w-[1400px] mx-auto px-5 sm:px-8 py-4 flex flex-wrap gap-x-8 gap-y-2 font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--sand)]">
                    <span>GPS &amp; geofencing</span>
                    <span class="text-[var(--gold)]">·</span>
                    <span>Fuel &amp; consumption</span>
                    <span class="text-[var(--gold)]">·</span>
                    <span>Predictive maintenance</span>
                    <span class="text-[var(--gold)]">·</span>
                    <span>Alerts &amp; audit trail</span>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="py-24 sm:py-28 px-5 sm:px-8">
            <div class="max-w-[1400px] mx-auto">
                <div class="max-w-xl mb-16 reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--gold)] mb-4">What it does</p>
                    <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--stone)] leading-tight">
                        Everything the fleet office runs on, in one place
                    </h2>
                </div>

                <div class="grid md:grid-cols-2 border-t border-[var(--line)]">
                    @php
                        $features = [
                            ['tag' => 'Tracking', 'title' => 'Live fleet tracking', 'body' => 'Real-time GPS on interactive maps, with geofence monitoring, route history, and location analytics for every unit on site.'],
                            ['tag' => 'Analytics', 'title' => 'AI-assisted analytics', 'body' => 'Productivity insights and predictive maintenance signals, surfaced from your own fleet data rather than a generic benchmark.'],
                            ['tag' => 'Fuel', 'title' => 'Fuel management', 'body' => 'Track consumption, monitor costs in ZAR, and get alerted on abnormal usage before it eats into the budget.'],
                            ['tag' => 'Maintenance', 'title' => 'Maintenance scheduling', 'body' => 'Automated reminders by hours or calendar, full service history, and preventive workflows that keep units off the workshop.'],
                            ['tag' => 'Alerts', 'title' => 'Smart alerts', 'body' => 'Instant notifications for maintenance windows, geofence breaches, and anomalies — by email, SMS, or in-app.'],
                            ['tag' => 'Reports', 'title' => 'Custom reports', 'body' => 'Build reports for any period, export to PDF or Excel, and schedule them to reach stakeholders automatically.'],
                        ];
                    @endphp
                    @foreach ($features as $i => $f)
                        <div class="row-hover border-b border-[var(--line)] {{ $i % 2 === 0 ? 'md:border-r' : '' }} px-1 py-8 sm:py-10 transition-colors reveal" data-reveal>
                            <p class="font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--gold)] mb-3">{{ $f['tag'] }}</p>
                            <h3 class="font-display font-semibold text-xl text-[var(--stone)] mb-2.5">{{ $f['title'] }}</h3>
                            <p class="text-[var(--sand)] leading-relaxed max-w-md">{{ $f['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Capabilities -->
        <section id="capabilities" class="py-24 sm:py-28 px-5 sm:px-8 bg-[var(--ink-soft)] border-y border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto">
                <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-12 lg:gap-20">
                    <div class="reveal" data-reveal>
                        <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--gold)] mb-4">Built for the operation</p>
                        <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--stone)] leading-tight mb-5">
                            Runs one site or twenty, from a browser
                        </h2>
                        <p class="text-[var(--sand)] leading-relaxed max-w-sm">
                            No hardware to install, no IT project to run. Sign in, add your fleet, and the rest of the platform is ready.
                        </p>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-x-10">
                        @php
                            $capabilities = [
                                ['title' => 'Cloud-based infrastructure', 'body' => 'Secure hosting with automatic backups. Access your data from anywhere, on any device.'],
                                ['title' => 'Multi-site support', 'body' => 'Manage every mining site from a single dashboard, no matter how many locations you run.'],
                                ['title' => 'Audit & compliance', 'body' => 'A full audit trail for every action — compliance reports, safety incidents, and operational logs.'],
                                ['title' => 'Unlimited data retention', 'body' => 'Years of historical data with no storage limits, for long-term trend analysis and compliance.'],
                                ['title' => '24/7 support & onboarding', 'body' => 'A support team and onboarding process built for teams that can\'t afford to be offline.'],
                                ['title' => 'Custom dashboards', 'body' => 'Build the views your team actually reads, and export the rest in the format you need.'],
                            ];
                        @endphp
                        @foreach ($capabilities as $c)
                            <div class="py-6 border-t border-[var(--line)] reveal" data-reveal>
                                <h3 class="font-display font-medium text-base text-[var(--stone)] mb-1.5">{{ $c['title'] }}</h3>
                                <p class="text-sm text-[var(--sand)] leading-relaxed">{{ $c['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="relative py-28 sm:py-36 px-5 sm:px-8 overflow-hidden">
            <!-- Photo: open-pit quarry, by MiningWatch Portugal, unsplash.com/photos/YG0qc-e6hgg -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1709489662983-3674d790b224?q=80&w=2400&auto=format&fit=crop');"></div>
            <div class="absolute inset-0" style="background: linear-gradient(180deg, #211a14 0%, rgba(33,26,20,0.82) 50%, #211a14 100%);"></div>

            <div class="relative z-10 max-w-2xl mx-auto text-center reveal" data-reveal>
                <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--stone)] leading-tight mb-5">
                    See your fleet the way your team already thinks about it
                </h2>
                <p class="text-[var(--sand)] leading-relaxed mb-10 max-w-lg mx-auto">
                    Free for 14 days. No hardware, no credit card, no call with a sales team required to try it.
                </p>

                @guest
                    <div class="flex flex-wrap justify-center gap-4">
                        <a href="{{ route('register') }}" class="press px-8 py-3.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#211a14] font-display font-semibold rounded-lg transition-colors">
                            Start free trial
                        </a>
                        <a href="{{ route('login') }}" class="press px-8 py-3.5 text-[var(--stone)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--sand)] transition-colors">
                            Sign in
                        </a>
                    </div>
                @endguest
            </div>
        </section>

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

        <script>
            if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches && 'IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            io.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
                document.querySelectorAll('[data-reveal]').forEach((el) => io.observe(el));
            } else {
                document.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-visible'));
            }
        </script>
    </body>
</html>
