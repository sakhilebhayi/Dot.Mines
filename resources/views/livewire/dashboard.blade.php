<div class="animate-fade-in" wire:poll.10s="loadDashboardData">
    <!-- Loading Spinner -->
    @if ($isLoading)
        <div class="flex justify-center items-center h-96">
            <svg class="animate-spin h-12 w-12 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
        </div>
        <script nonce="{{ request()->attributes->get('csp_nonce') }}">window.scrollTo(0,0);</script>
    @else
        <!-- Chart Visualization Placeholder removed -->
        <!-- Activity Feed -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
               <!-- <span class="text-2xl">📝</span> -->
                <h1 class="text-3xl font-bold text-white mb-2">
                    @php
                        $hour = now()->format('H');
                        $greeting = $hour < 12 ? 'Good Morning' : ($hour < 18 ? 'Good Afternoon' : 'Good Evening');
                    @endphp
                    {{ $greeting }}, {{ auth()->user()->name }}! 👋
                </h1>
                <p class="text-blue-100 text-sm">
                    {{ now()->format('l, F j, Y') }} • {{ auth()->user()->currentTeam?->name ?? 'No Team' }}
                </p>
            </h2>
            <!-- <div class="flex gap-2 flex-wrap">
                <a href="{{ route('fleet') }}" 
                    class="px-4 py-2 bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white rounded-lg transition-all duration-200 text-sm font-medium flex items-center gap-2 hover:scale-105 transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add Machine
                </a>
                <a href="{{ route('reports') }}" 
                    class="px-4 py-2 bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white rounded-lg transition-all duration-200 text-sm font-medium flex items-center gap-2 hover:scale-105 transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    New Report
                </a>
                <a href="{{ route('alerts') }}" 
                    class="px-4 py-2 bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white rounded-lg transition-all duration-200 text-sm font-medium flex items-center gap-2 hover:scale-105 transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    View Alerts
                </a>
            </div> -->
        </div>
        

    <!-- Statistics Cards -->
    <div class="flex flex-col gap-6 mb-8 md:grid md:grid-cols-2 lg:grid-cols-4 md:flex-none overflow-x-auto pb-2">
        <!-- Total Machines Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300 animate-scale-in min-w-[260px] md:min-w-0">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-blue-100 dark:bg-blue-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
            </div>
            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-1">Total Machines</p>
            <p class="text-4xl font-bold text-gray-900 dark:text-white" x-data="{ count: 0 }" x-init="() => { let target = {{ $totalMachines }}; let duration = 2000; let increment = target / (duration / 16); let timer = setInterval(() => { count += increment; if (count >= target) { count = target; clearInterval(timer); } }, 16); }">
                <span x-text="Math.floor(count)">0</span>
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Fleet inventory</p>
            @if ($totalMachines === 0)
                <div class="text-center py-2">
                    <span class="text-xs text-gray-400">No machines found. <a href="{{ route('fleet') }}" class="text-blue-600">Add a machine</a>.</span>
                </div>
            @endif
        </div>

        <!-- Active Machines Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300 animate-scale-in min-w-[260px] md:min-w-0" style="animation-delay: 0.2s">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-green-100 dark:bg-green-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-1">Active Now</p>
            <p class="text-4xl font-bold text-gray-900 dark:text-white" x-data="{ count: 0 }" x-init="() => { let target = {{ $activeMachines }}; let duration = 2000; let increment = target / (duration / 16); let timer = setInterval(() => { count += increment; if (count >= target) { count = target; clearInterval(timer); } }, 16); }">
                <span x-text="Math.floor(count)">0</span>
            </p>
            <p class="text-xs text-green-600 dark:text-green-400 mt-2 font-medium">
                {{ round(($activeMachines / max($totalMachines, 1)) * 100) }}% operational
            </p>
            @if ($activeMachines === 0)
                <div class="text-center py-2">
                    <span class="text-xs text-gray-400">No active machines. <a href="{{ route('fleet') }}" class="text-blue-600">View fleet</a>.</span>
                </div>
            @endif
        </div>

        <!-- Active Alerts Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300 animate-scale-in min-w-[260px] md:min-w-0" style="animation-delay: 0.3s">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-red-100 dark:bg-red-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                </div>
            </div>
            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-1">Active Alerts</p>
            <p class="text-4xl font-bold text-gray-900 dark:text-white" x-data="{ count: 0 }" x-init="() => { let target = {{ $activeAlerts }}; let duration = 2000; let increment = target / (duration / 16); let timer = setInterval(() => { count += increment; if (count >= target) { count = target; clearInterval(timer); } }, 16); }">
                <span x-text="Math.floor(count)">0</span>
            </p>
            <p class="text-xs text-red-600 dark:text-red-400 mt-2 font-medium">
                {{ $activeAlerts > 0 ? 'Requires attention' : 'All clear' }}
            </p>
            @if ($activeAlerts === 0)
                <div class="text-center py-2">
                    <span class="text-xs text-gray-400">No active alerts. <a href="{{ route('alerts') }}" class="text-blue-600">View alerts</a>.</span>
                </div>
            @endif
        </div>

        <!-- Geofences Card -->
        <div class="bg-amber-500 rounded-lg shadow p-6 text-white hover:shadow-xl transition-all duration-200 min-w-[260px] md:min-w-0">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-white/10 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13"></path>
                    </svg>
                </div>
            </div>
            <p class="text-amber-100 text-sm font-medium mb-1">Geofences</p>
            <p class="text-4xl font-bold" x-data="{ count: 0 }" x-init="() => { let target = {{ $totalGeofences }}; let duration = 2000; let increment = target / (duration / 16); let timer = setInterval(() => { count += increment; if (count >= target) { count = target; clearInterval(timer); } }, 16); }">
                <span x-text="Math.floor(count)">0</span>
            </p>
            <p class="text-xs text-amber-100 mt-2 opacity-90">
                Monitoring zones
            </p>
            @if ($totalGeofences === 0)
                <div class="text-center py-2">
                    <span class="text-xs text-amber-100">No geofences set. <a href="{{ route('map') }}" class="text-blue-200 underline">Add geofence</a>.</span>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Live Telemetry Stats (rendered when any OEM telemetry is present) ──────────── --}}
    @if ($hasBellTelemetry)
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">
        {{-- Running Engines --}}
        <div class="bg-emerald-600 rounded-lg shadow p-4 text-white flex items-center gap-3">
            <div class="p-2 bg-white/20 rounded-lg flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold leading-none">{{ $runningMachines }}</p>
                <p class="text-xs text-emerald-100 mt-0.5">Engines Running</p>
            </div>
        </div>

        {{-- Offline Machines --}}
        <div class="bg-red-600 rounded-lg shadow p-4 text-white flex items-center gap-3">
            <div class="p-2 bg-white/20 rounded-lg flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 11-12.728 0"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v9"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold leading-none">{{ $offlineMachines }}</p>
                <p class="text-xs text-red-100 mt-0.5">Offline</p>
            </div>
        </div>

        {{-- Average Fuel --}}
        <div class="bg-amber-500 rounded-lg shadow p-4 text-white flex items-center gap-3">
            <div class="p-2 bg-white/20 rounded-lg flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10M4 18h6"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold leading-none">
                    {{ $avgFuelPercent !== null ? $avgFuelPercent.'%' : '—' }}
                </p>
                <p class="text-xs text-amber-100 mt-0.5">Avg Fuel Level</p>
            </div>
        </div>

        {{-- Loads Today --}}
        <div class="bg-blue-600 rounded-lg shadow p-4 text-white flex items-center gap-3">
            <div class="p-2 bg-white/20 rounded-lg flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold leading-none">{{ number_format($loadsToday) }}</p>
                <p class="text-xs text-blue-100 mt-0.5">
                    Loads Today
                    @if ($payloadTodayTonnes > 0)
                        <span class="block">{{ number_format($payloadTodayTonnes, 1) }} t</span>
                    @endif
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Alerts -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="text-2xl">🚨</span>
                    Recent Alerts
                </h2>
                <a href="{{ route('alerts') }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 text-sm font-medium transition-colors">
                    View All →
                </a>
            </div>

            @if (count($recentAlerts) > 0)
                <div class="space-y-3">
                    @foreach ($recentAlerts as $alert)
                        <div class="flex items-start justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 
                            @if ($alert['priority'] === 'high') border-red-500 
                            @elseif ($alert['priority'] === 'medium') border-yellow-500 
                            @else border-blue-500 @endif">
                            <div class="flex items-start gap-4 flex-1">
                                <div class="mt-1">
                                    @if ($alert['priority'] === 'high')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-400">
                                            HIGH
                                        </span>
                                    @elseif ($alert['priority'] === 'medium')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-500/20 text-yellow-700 dark:text-yellow-400">
                                            MEDIUM
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400">
                                            LOW
                                        </span>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ ucfirst(str_replace('_', ' ', $alert['type'])) }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ $alert['message'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">{{ $alert['created_at'] }}</p>
                                </div>
                            </div>
                            @if ($alert['status'] === 'open')
                                <button wire:click="acknowledgeAlert({{ $alert['id'] }})" 
                                    class="px-3 py-1 text-xs bg-blue-600 hover:bg-blue-700 text-white rounded transition-all duration-200 font-medium hover:scale-105 transform">
                                    Acknowledge
                                </button>
                            @else
                                <span class="px-3 py-1 text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded font-medium">
                                    ✓ {{ ucfirst($alert['status']) }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">✅</div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">All Clear!</h3>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">No active alerts at the moment. <a href="{{ route('alerts') }}" class="text-blue-600">View all alerts</a>.</p>
                </div>
            @endif
        </div>

        <!-- Machine Status & Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700 mt-6 lg:mt-0">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                <span class="text-2xl">🚜</span>
                Fleet Status
            </h2>

            @if (count($machineStatus) > 0)
                <div class="space-y-4">
                    @foreach ($machineStatus as $status)
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                    @if($status['status'] === 'Active')
                                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                    @elseif($status['status'] === 'Idle')
                                        <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                                    @elseif($status['status'] === 'Maintenance')
                                        <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                    @else
                                        <span class="w-2 h-2 bg-gray-500 rounded-full"></span>
                                    @endif
                                    {{ $status['status'] }}
                                </span>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $status['count'] }}</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                @php
                                    $percentage = ($status['count'] / max($totalMachines, 1)) * 100;
                                    $color = match($status['status']) {
                                        'Active' => 'bg-green-500',
                                        'Idle' => 'bg-blue-500',
                                        'Maintenance' => 'bg-red-500',
                                        default => 'bg-gray-500',
                                    };
                                @endphp
                                <div class="{{ $color }} h-3 rounded-full transition-all duration-1000 ease-out" style="width: {{ (int) $percentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <p class="text-gray-600 dark:text-gray-400 text-sm">No machine data available. <a href="{{ route('fleet') }}" class="text-blue-600">Add a machine</a>.</p>
                </div>
            @endif

            <!-- Quick Actions -->
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Quick Actions
                </h3>
                <div class="space-y-2">
                    <a href="{{ route('fleet') }}" 
                        class="block w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-colors text-center font-medium">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            View Fleet
                        </span>
                    </a>
                    <a href="{{ route('map') }}" 
                        class="block w-full px-4 py-3 bg-purple-600 hover:bg-purple-700 text-white text-sm rounded-lg transition-colors text-center font-medium">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13"/>
                            </svg>
                            Live Map
                        </span>
                    </a>
                    <a href="{{ route('ai-optimization') }}" 
                        class="block w-full px-4 py-3 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg transition-colors text-center font-medium">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            AI Insights
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Haul Dispatch Tracker ────────────────────────────────────────── --}}
    @livewire('haul-dispatch-dashboard')

    @endif
    <script>
    (function injectDashboardStyles() {
        if (document.getElementById('dashboard-animation-styles')) return;
        var s = document.createElement('style');
        s.id = 'dashboard-animation-styles';
        s.textContent = [
            '@keyframes fadeIn{from{opacity:0}to{opacity:1}}',
            '@keyframes slideDown{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}',
            '@keyframes scaleIn{from{opacity:0;transform:scale(0.95)}to{opacity:1;transform:scale(1)}}',
            '.animate-fade-in{animation:fadeIn 0.6s ease-out}',
            '.animate-slide-down{animation:slideDown 0.5s ease-out}',
            '.animate-scale-in{animation:scaleIn 0.4s ease-out forwards;opacity:0}',
        ].join('');
        document.head.appendChild(s);
    })();
    </script>
</div>
