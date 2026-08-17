<nav class="bg-[var(--ink-soft)] border-b border-[var(--line)] sticky top-0 z-40"
     x-data="{ mobileOpen: false }"
     @mobile-nav-changed.window="mobileOpen = $event.detail.open">
    <div class="px-6 py-4 flex justify-between items-center">
        <!-- Left Section -->
        <div class="flex items-center gap-4">
            <!-- Mobile nav toggle -->
            <button
                @click="window.mobileNav.toggle()"
                type="button"
                class="md:hidden -ml-2 p-2 rounded-lg text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)] transition-colors"
                aria-label="Toggle navigation menu"
                aria-controls="mobile-sidebar"
                :aria-expanded="mobileOpen.toString()"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>

            @php
                $routeName = optional(request()->route())->getName();
                    $mapping = [
                    'dashboard' => 'Dashboard',
                    'fleet' => 'Fleet Management',
                    'fleet.replay' => 'Fleet Replay',
                    'fleet.route-planning' => 'Route Planning',
                        'map' => 'Live Operations Map',
                    'geofences' => 'Geofences',
                    'geofences.show' => 'Geofence Details',
                    'mine-areas' => 'Mine Areas',
                    'mine-areas.show' => 'Mine Area',
                    'reports' => 'Reports',
                    'report-generator' => 'Generate Report',
                        'alerts' => 'Operational Alerts',
                    'production' => 'Production',
                    'fuel' => 'Fuel Management',
                    'maintenance' => 'Maintenance',
                    'ai-optimization' => 'AI Optimization',
                    'ai-analytics' => 'AI Analytics',
                    'documentation' => 'Documentation',
                    'integrations' => 'Integrations',
                    'billing.index' => 'Billing',
                    'settings' => 'Settings',
                    'teams.show' => 'Team Settings',
                    'api-tokens.index' => 'API Tokens',
                ];

                $pageTitle = null;
                if ($routeName && isset($mapping[$routeName])) {
                    $pageTitle = $mapping[$routeName];
                } else {
                    // Fallbacks by pattern matching
                    if (str_starts_with($routeName ?? '', 'fleet')) {
                        $pageTitle = 'Fleet Management';
                    } elseif (str_starts_with($routeName ?? '', 'geofences')) {
                        $pageTitle = 'Geofences';
                    } elseif (str_starts_with($routeName ?? '', 'mine-areas')) {
                        $pageTitle = 'Mine Areas';
                    } elseif (str_starts_with($routeName ?? '', 'reports')) {
                        $pageTitle = 'Reports';
                    } elseif (str_starts_with($routeName ?? '', 'ai-')) {
                        $pageTitle = 'AI';
                    }
                }

                // Final fallback: use first URI segment
                if (! $pageTitle) {
                    $firstSegment = explode('/', trim(request()->path(), '/'))[0] ?? null;
                    $pageTitle = $firstSegment ? ucfirst(str_replace('-', ' ', $firstSegment)) : 'Mines';
                }
            @endphp

            <h1 class="text-2xl font-display font-semibold text-[var(--stone)]">{{ $pageTitle }}</h1>
        </div>

        <!-- Right Section -->
        <div class="flex items-center gap-4">
            <!-- Team Info -->
            @if ($team)
                <div class="text-right hidden sm:block">
                    <div class="text-sm text-[var(--sand)]">Current Team</div>
                    <div class="text-sm font-semibold text-[var(--stone)]">{{ $team->name }}</div>
                </div>
            @endif

            <!--
                Real-time connection indicator. Driven entirely client-side by
                the 'realtime-connection-changed' window event dispatched from
                resources/js/livewire-realtime.js's connection monitor -- no
                server roundtrip, since connection state is only known in the
                browser. Users should never be left assuming they're seeing
                live data while the socket is actually down.
            -->
            <div
                x-data="{ state: 'connecting' }"
                x-init="window.addEventListener('realtime-connection-changed', (e) => { state = e.detail.state })"
                class="hidden sm:flex items-center gap-1.5"
                :title="{
                    connected: 'Live updates connected',
                    connecting: 'Connecting to live updates…',
                    reconnecting: 'Reconnecting to live updates…',
                    disconnected: 'Live updates disconnected',
                }[state]"
            >
                <span
                    class="w-2 h-2 rounded-full"
                    :class="{
                        connected: 'bg-green-500',
                        connecting: 'bg-amber-500 animate-pulse',
                        reconnecting: 'bg-amber-500 animate-pulse',
                        disconnected: 'bg-red-500',
                    }[state]"
                ></span>
            </div>

            <!-- Notifications (real AI alert data, not the static sample content this used to show) -->
            <livewire:ai-notifications />

            <!-- User Profile -->
            <div class="relative">
                <button wire:click="toggleProfileMenu" class="flex items-center gap-2 p-2 hover:bg-white/5 rounded-lg transition-colors">
                    <div class="w-8 h-8 bg-[var(--gold)] rounded-full flex items-center justify-center">
                        <span class="text-sm font-bold text-[var(--ink)]">{{ substr($user?->name ?? 'U', 0, 1) }}</span>
                    </div>
                    <span class="text-sm text-[var(--sand)] hidden sm:block">{{ $user?->name }}</span>
                </button>

                <!-- Profile Dropdown -->
                @if ($profileMenuOpen)
                    <div class="absolute right-0 mt-2 bg-[var(--ink-soft)] rounded-lg shadow-lg border border-[var(--line)] w-48">
                        <div class="p-4 border-b border-[var(--line)]">
                            <p class="text-sm font-semibold text-[var(--stone)]">{{ $user?->name }}</p>
                            <p class="text-xs text-[var(--sand)]">{{ $user?->email }}</p>
                        </div>
                        <div class="p-2 space-y-1">
                            <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)] rounded-lg transition-colors">
                                My Profile
                            </a>
                            <a href="{{ route('settings') }}" class="block px-4 py-2 text-sm text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)] rounded-lg transition-colors">
                                Settings
                            </a>
                            <a href="{{ route('documentation') }}" class="block px-4 py-2 text-sm text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)] rounded-lg transition-colors">
                                Documentation
                            </a>
                            <a href="{{ route('integrations') }}" class="block px-4 py-2 text-sm text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)] rounded-lg transition-colors">
                                Integrations
                            </a>
                            <a href="{{ route('api-tokens.index') }}" class="block px-4 py-2 text-sm text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)] rounded-lg transition-colors">
                                API Tokens
                            </a>
                            <a href="{{ route('teams.show', auth()->user()->currentTeam) }}" class="block px-4 py-2 text-sm text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)] rounded-lg transition-colors">
                                Team
                            </a>
                            <a href="{{ route('billing.index') }}" class="block px-4 py-2 text-sm text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)] rounded-lg transition-colors">
                                Billing
                            </a>
                        </div>
                        <div class="p-2 border-t border-[var(--line)]">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-white/5 rounded-lg transition-colors">
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</nav>
