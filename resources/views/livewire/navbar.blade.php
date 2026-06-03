<nav class="bg-gray-800 border-b border-gray-700 sticky top-0 z-40">
    <div class="px-6 py-4 flex justify-between items-center">
        <!-- Left Section -->
        <div class="flex items-center gap-4">
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
                    'team.settings' => 'Team Settings',
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

            <h1 class="text-2xl font-bold text-white">{{ $pageTitle }}</h1>
        </div>

        <!-- Right Section -->
        <div class="flex items-center gap-4">
            <!-- Team Info -->
            @if ($team)
                <div class="text-right hidden sm:block">
                    <div class="text-sm text-gray-400">Current Team</div>
                    <div class="text-sm font-semibold text-white">{{ $team->name }}</div>
                </div>
            @endif

            <!-- Theme Toggle -->
            <div
                x-data="{
                    mode: localStorage.getItem('theme-mode') || 'system',
                    theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                    cycle() {
                        const next = this.mode === 'system'
                            ? (this.theme === 'dark' ? 'light' : 'dark')
                            : (this.mode === 'dark' ? 'light' : 'dark');
                        this.mode = next;
                        window.ThemeController.setMode(next);
                    },
                    label() {
                        if (this.mode === 'system') return 'Theme: System (' + this.theme + ')';
                        return this.theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
                    }
                }"
                x-init="$el.addEventListener('theme-changed', e => { theme = e.detail.theme; })"
                @theme-changed.window="theme = $event.detail.theme; mode = $event.detail.mode"
                class="flex items-center"
            >
                {{-- 3-state cycle: system → dark → light → dark … --}}
                <button
                    @click="cycle()"
                    :aria-label="label()"
                    :title="label()"
                    class="relative flex items-center justify-center w-9 h-9 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800"
                >
                    {{-- Sun icon — shown in dark mode (click → go light) --}}
                    <svg
                        x-show="theme === 'dark'"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 rotate-90 scale-75"
                        x-transition:enter-end="opacity-100 rotate-0 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 rotate-0 scale-100"
                        x-transition:leave-end="opacity-0 -rotate-90 scale-75"
                        class="w-5 h-5 text-amber-400"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>

                    {{-- Moon icon — shown in light mode (click → go dark) --}}
                    <svg
                        x-show="theme === 'light'"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 rotate-90 scale-75"
                        x-transition:enter-end="opacity-100 rotate-0 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 rotate-0 scale-100"
                        x-transition:leave-end="opacity-0 -rotate-90 scale-75"
                        class="w-5 h-5 text-slate-500"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>
            </div>

            <!-- Notifications -->
            <button wire:click="toggleNotifications" class="relative p-2 text-gray-400 hover:text-white transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
            </button>

            <!-- Notifications Dropdown -->
            @if ($notificationsOpen)
                <div class="absolute right-20 top-14 bg-gray-700 rounded-lg shadow-lg border border-gray-600 w-80">
                    <div class="p-4 border-b border-gray-600">
                        <h3 class="font-semibold text-white">Notifications</h3>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <div class="p-4 space-y-3">
                            <div class="p-3 bg-gray-600 rounded-lg">
                                <p class="text-sm text-white">Low fuel alert on Machine #1</p>
                                <p class="text-xs text-gray-300 mt-1">2 minutes ago</p>
                            </div>
                            <div class="p-3 bg-gray-600 rounded-lg">
                                <p class="text-sm text-white">Machine #3 entered North Pit</p>
                                <p class="text-xs text-gray-300 mt-1">15 minutes ago</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- User Profile -->
            <div class="relative">
                <button wire:click="toggleProfileMenu" class="flex items-center gap-2 p-2 hover:bg-gray-700 rounded-lg transition-colors">
                    <div class="w-8 h-8 bg-amber-500 rounded-full flex items-center justify-center">
                        <span class="text-sm font-bold text-gray-900">{{ substr($user?->name ?? 'U', 0, 1) }}</span>
                    </div>
                    <span class="text-sm text-gray-300 hidden sm:block">{{ $user?->name }}</span>
                </button>

                <!-- Profile Dropdown -->
                @if ($profileMenuOpen)
                    <div class="absolute right-0 mt-2 bg-gray-700 rounded-lg shadow-lg border border-gray-600 w-48">
                        <div class="p-4 border-b border-gray-600">
                            <p class="text-sm font-semibold text-white">{{ $user?->name }}</p>
                            <p class="text-xs text-gray-400">{{ $user?->email }}</p>
                        </div>
                        <div class="p-2 space-y-1">
                            <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600 rounded-lg transition-colors">
                                My Profile
                            </a>
                            <a href="{{ route('settings') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600 rounded-lg transition-colors">
                                Settings
                            </a>
                            <a href="{{ route('documentation') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600 rounded-lg transition-colors">
                                Documentation
                            </a>
                            <a href="{{ route('integrations') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600 rounded-lg transition-colors">
                                Integrations
                            </a>
                            <a href="{{ route('billing.index') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600 rounded-lg transition-colors">
                                Billing
                            </a>
                        </div>
                        <div class="p-2 border-t border-gray-600">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-gray-600 rounded-lg transition-colors">
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
