<div>
<div class="animate-fade-in">
    <!-- Loading Spinner -->
    @if ($isLoading)
        <div class="flex justify-center items-center h-96">
            <svg class="animate-spin h-12 w-12 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
        </div>
        <script>window.scrollTo(0,0);</script>
    @else
        <!-- Chart Visualization Placeholder removed -->
    <!-- Header Section with gradient -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl shadow-lg p-6 mb-6 animate-slide-down">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white flex items-center gap-2">
                    <span class="text-3xl">🚜</span>
                    Fleet Management
                </h1>
                <p class="text-blue-100 text-sm mt-1">
                    Manage and monitor your mining fleet equipment
                </p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('fleet.route-planning') }}" 
                    class="px-4 py-2 bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white rounded-lg transition-all duration-200 flex items-center gap-2 text-sm font-medium hover:scale-105 transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    <span>Route Planning</span>
                </a>
                <a href="{{ route('fleet.replay') }}" 
                    class="px-4 py-2 bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white rounded-lg transition-all duration-200 flex items-center gap-2 text-sm font-medium hover:scale-105 transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Movement Replay</span>
                </a>
                @php $fleetFull = $fleetUsage['max'] && $fleetUsage['current'] >= $fleetUsage['max']; @endphp
                <button wire:click="openCreateModal"
                    @if($fleetFull) disabled title="Fleet slot limit reached — upgrade your plan to add more machines" @endif
                    class="px-4 py-2 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white rounded-lg transition-all duration-200 flex items-center gap-2 text-sm font-medium shadow-lg hover:shadow-xl hover:scale-105 transform disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Add Machine</span>
                </button>
            </div>
        </div>

        {{-- Fleet slot usage bar --}}
        @if ($fleetUsage['max'])
            @php
                $pct = min(100, round($fleetUsage['current'] / $fleetUsage['max'] * 100));
                $barColor = $pct >= 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-amber-400' : 'bg-green-400');
            @endphp
            <div class="mt-4 pt-4 border-t border-white/20">
                <div class="flex items-center justify-between text-xs text-blue-100 mb-1">
                    <span>Fleet Slots Used</span>
                    <span class="{{ $pct >= 100 ? 'text-red-300 font-bold' : '' }}">
                        {{ $fleetUsage['current'] }} / {{ $fleetUsage['max'] }} machines
                        @if ($pct >= 100) — <span class="font-bold">Limit reached</span> @endif
                    </span>
                </div>
                <div class="w-full bg-white/20 rounded-full h-2">
                    <div class="{{ $barColor }} h-2 rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
                </div>
            </div>
        @endif
    </div><!-- end header gradient -->

    {{-- Upgrade prompt: shown when fleet slot limit is reached --}}
    @if ($fleetFull)
        <div class="mb-6 rounded-xl border border-amber-400/50 bg-amber-50 dark:bg-amber-900/20 p-4 flex flex-col sm:flex-row items-start sm:items-center gap-4">
            <div class="flex-shrink-0 p-2 rounded-lg bg-amber-100 dark:bg-amber-800/40">
                <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Fleet slot limit reached</p>
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">
                    You have used all {{ $fleetUsage['current'] }} / {{ $fleetUsage['max'] }} machine slots on your current plan.
                    Upgrade your subscription to add more machines to your fleet.
                </p>
            </div>
            <a href="{{ route('billing.index') }}"
               class="flex-shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium transition-colors shadow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                </svg>
                Upgrade Plan
            </a>
        </div>
    @endif

    <!-- Status Statistics with animation -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 animate-scale-in">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-green-100 dark:bg-green-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-1">Active</p>
            <p class="text-4xl font-bold text-gray-900 dark:text-white" x-data="{ count: 0 }" x-init="() => { let target = {{ $statusStats['active'] }}; let duration = 2000; let increment = target / (duration / 16); let timer = setInterval(() => { count += increment; if (count >= target) { count = target; clearInterval(timer); } }, 16); }">
                <span x-text="Math.floor(count)">0</span>
            </p>
            <p class="text-xs text-green-600 dark:text-green-400 mt-2 font-medium flex items-center gap-1">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                Operating now
            </p>
            @if ($statusStats['active'] === 0)
                <div class="text-center py-2">
                    <span class="text-xs text-gray-400">No active machines. <button wire:click="openCreateModal" class="text-blue-600 underline">Add machine</button>.</span>
                </div>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 animate-scale-in">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-blue-100 dark:bg-blue-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 100-2 1 1 0 000 2zm6 0a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-1">Idle</p>
            <p class="text-4xl font-bold text-gray-900 dark:text-white" x-data="{ count: 0 }" x-init="() => { let target = {{ $statusStats['idle'] }}; let duration = 2000; let increment = target / (duration / 16); let timer = setInterval(() => { count += increment; if (count >= target) { count = target; clearInterval(timer); } }, 16); }">
                <span x-text="Math.floor(count)">0</span>
            </p>
            <p class="text-xs text-blue-600 dark:text-blue-400 mt-2 font-medium">
                Awaiting assignment
            </p>
            @if ($statusStats['idle'] === 0)
                <div class="text-center py-2">
                    <span class="text-xs text-gray-400">No idle machines. <button wire:click="openCreateModal" class="text-blue-600 underline">Add machine</button>.</span>
                </div>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 animate-scale-in">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-red-100 dark:bg-red-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 2.523a6 6 0 008.367 8.367z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-1">Maintenance</p>
            <p class="text-4xl font-bold text-gray-900 dark:text-white" x-data="{ count: 0 }" x-init="() => { let target = {{ $statusStats['maintenance'] }}; let duration = 2000; let increment = target / (duration / 16); let timer = setInterval(() => { count += increment; if (count >= target) { count = target; clearInterval(timer); } }, 16); }">
                <span x-text="Math.floor(count)">0</span>
            </p>
            <p class="text-xs text-red-600 dark:text-red-400 mt-2 font-medium">
                Under service
            </p>
            @if ($statusStats['maintenance'] === 0)
                <div class="text-center py-2">
                    <span class="text-xs text-gray-400">No machines under maintenance. <button wire:click="openCreateModal" class="text-blue-600 underline">Add machine</button>.</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Machine Performance Section -->
    @if($topPerformers->count() > 0 || $worstPerformers->count() > 0)
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Machine Performance</h2>
            <span class="text-xs text-gray-500 dark:text-gray-400">(Last 30 Days)</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top 5 Performers -->
            @if($topPerformers->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2 mb-4">
                    <div class="p-2 bg-green-100 dark:bg-green-500/20 rounded-lg">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Top 5 Performers</h3>
                </div>
                <div class="space-y-3">
                    @foreach($topPerformers as $index => $machine)
                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-lg border border-green-200 dark:border-green-800">
                        <div class="flex-shrink-0 w-8 h-8 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                            {{ $index + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('fleet.show', $machine['machine_id']) }}" class="font-semibold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 truncate block">
                                {{ $machine['machine_name'] }}
                            </a>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $machine['machine_type'])) }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-green-600 dark:text-green-400">{{ $machine['performance_score'] }}%</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Score</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Top 5 Worst Performers -->
            @if($worstPerformers->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2 mb-4">
                    <div class="p-2 bg-red-100 dark:bg-red-500/20 rounded-lg">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Needs Attention</h3>
                </div>
                <div class="space-y-3">
                    @foreach($worstPerformers as $index => $machine)
                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 rounded-lg border border-red-200 dark:border-red-800">
                        <div class="flex-shrink-0 w-8 h-8 bg-gradient-to-br from-red-500 to-orange-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                            {{ $index + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('fleet.show', $machine['machine_id']) }}" class="font-semibold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 truncate block">
                                {{ $machine['machine_name'] }}
                            </a>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $machine['machine_type'])) }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-red-600 dark:text-red-400">{{ $machine['performance_score'] }}%</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Score</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- AI-Powered Fleet Optimization -->
    @if($aiRecommendations->count() > 0 || $aiInsights->count() > 0)
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
            </svg>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">AI Fleet Optimization</h2>
            <span class="badge badge-primary">AI-Powered</span>
        </div>

        <!-- AI Fleet Recommendations - Full Width -->
        @if($aiRecommendations->count() > 0)
        <div class="mb-6">
            <div class="bg-gradient-to-br from-blue-900 to-cyan-900 rounded-lg shadow-lg p-6 border border-blue-700">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2 text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Optimization Recommendations
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($aiRecommendations as $recommendation)
                        <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 border border-white/20">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-semibold">{{ $recommendation['title'] }}</span>
                                    </div>
                                </div>
                                <span class="badge ml-2
                                    @if($recommendation['priority'] === 'critical') badge-error
                                    @elseif($recommendation['priority'] === 'high') badge-warning
                                    @else badge-info
                                    @endif
                                ">{{ ucfirst($recommendation['priority']) }}</span>
                            </div>
                            <p class="text-sm text-gray-200 mb-3">{{ $recommendation['description'] }}</p>
                            
                            @if(isset($recommendation['estimated_savings']) && $recommendation['estimated_savings'] > 0)
                            <div class="flex items-center gap-2 text-green-300 text-sm mb-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>Potential Savings: R{{ number_format($recommendation['estimated_savings'], 2) }}</span>
                            </div>
                            @endif

                            @if(isset($recommendation['estimated_efficiency_gain']) && $recommendation['estimated_efficiency_gain'] > 0)
                            <div class="flex items-center gap-2 text-cyan-300 text-sm mb-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                                <span>Efficiency Gain: +{{ $recommendation['estimated_efficiency_gain'] }}%</span>
                            </div>
                            @endif

                            @if(isset($recommendation['data']['idle_machines']) && $recommendation['data']['idle_machines'] > 0)
                            <div class="bg-yellow-500/20 border border-yellow-500/30 rounded p-2 mb-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span><strong>{{ $recommendation['data']['idle_machines'] }} machines</strong> currently idle ({{ number_format($recommendation['data']['idle_percentage'], 1) }}%)</span>
                                </div>
                            </div>
                            @endif

                            @if(isset($recommendation['impact_analysis']['recommended_allocation']))
                            <details class="collapse collapse-arrow bg-white/5 mt-2">
                                <summary class="collapse-title text-sm font-medium py-2 min-h-0">Impact Analysis</summary>
                                <div class="collapse-content px-2 pb-2">
                                    <ul class="text-xs space-y-1 text-gray-300">
                                        @if(isset($recommendation['impact_analysis']['production_impact']))
                                            <li><strong>Production:</strong> {{ $recommendation['impact_analysis']['production_impact'] }}</li>
                                        @endif
                                        @if(isset($recommendation['impact_analysis']['recommended_allocation']))
                                            <li><strong>Recommended:</strong> {{ $recommendation['impact_analysis']['recommended_allocation'] }}</li>
                                        @endif
                                        @if(isset($recommendation['impact_analysis']['daily_cost']))
                                            <li><strong>Daily Cost:</strong> {{ $recommendation['impact_analysis']['daily_cost'] }}</li>
                                        @endif
                                        @if(isset($recommendation['impact_analysis']['recommended_action']))
                                            <li><strong>Action:</strong> {{ $recommendation['impact_analysis']['recommended_action'] }}</li>
                                        @endif
                                    </ul>
                                </div>
                            </details>
                            @endif

                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-white/10">
                                <span class="text-xs text-gray-300">AI Confidence: {{ number_format($recommendation['confidence_score'] * 100, 0) }}%</span>
                                <div class="flex items-center gap-2">
                                    <button wire:click="implementRecommendation({{ $loop->index }})" class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700">Implement</button>
                                    <button wire:click="openRejectRecommendation({{ $loop->index }})" class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700">Reject</button>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- AI Fleet Insights - Full Width -->
            @if($aiInsights->count() > 0)
            <div class="bg-gradient-to-br from-indigo-900 to-purple-900 rounded-lg shadow-lg p-6 border border-indigo-700">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2 text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Fleet Insights
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($aiInsights as $insight)
                        <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 border border-white/20">
                            <div class="flex items-start justify-between mb-2">
                                <span class="font-semibold">{{ $insight['title'] }}</span>
                                <span class="badge
                                    @if($insight['severity'] === 'critical') badge-error
                                    @elseif($insight['severity'] === 'warning') badge-warning
                                    @elseif($insight['severity'] === 'success') badge-success
                                    @else badge-info
                                    @endif
                                ">{{ ucfirst($insight['type']) }}</span>
                            </div>
                            <p class="text-sm text-gray-200">{{ $insight['description'] }}</p>
                            
                            @if(isset($insight['data']['total_utilization']))
                            <div class="mt-2">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span>Fleet Utilization</span>
                                    <span class="font-bold">{{ number_format($insight['data']['total_utilization'], 1) }}%</span>
                                </div>
                                <progress class="progress progress-primary w-full" value="{{ $insight['data']['total_utilization'] }}" max="100"></progress>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>

                    <!-- AI Info Footer -->
                    <div class="mt-4 p-3 bg-indigo-500/20 border border-indigo-500/30 rounded-lg">
                        <p class="text-xs text-indigo-200">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <strong>AI Analysis:</strong> Recommendations based on machine utilization, allocation patterns, and operational efficiency metrics.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Filters Section with improved design -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6 border border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Search Machines
                </label>
                <input 
                    type="text" 
                    wire:model.live="search" 
                    placeholder="Name, model, or manufacturer..."
                    class="w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                />
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Status Filter
                </label>
                <select 
                    wire:model.live="statusFilter"
                    class="w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="idle">Idle</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>

            <!-- Sort By -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                    </svg>
                    Sort By
                </label>
                <select 
                    wire:model.live="sortBy"
                    class="w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                >
                    <option value="name">Name</option>
                    <option value="manufacturer">Manufacturer</option>
                    <option value="status">Status</option>
                    <option value="created_at">Date Added</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Machines Cards/Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700" wire:poll.60s>
        @if ($machines->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 p-6">
                @foreach ($machines as $machine)
                    @php
                        $machineType = strtolower($machine->machine_type ?? '');
                        $iconMap = [
                            'excavator' => '/machine-emojis/excavator.svg',
                            'articulated_hauler' => '/machine-emojis/dump-truck.svg',
                            'dozer' => '/machine-emojis/bulldozer.svg',
                            'grader' => '/machine-emojis/grader.svg',
                            'support_vehicle' => '/machine-emojis/service-truck.svg',
                        ];
                        $icon = $iconMap[$machineType] ?? '/machine-emojis/service-truck.svg';
                    @endphp
                    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 flex flex-col">
                        <div class="flex flex-col items-center justify-center p-4 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-b from-yellow-50 to-yellow-100 dark:from-yellow-900/30 dark:to-yellow-900/10">
                            <img src="{{ asset($icon) }}" alt="Machine Icon" class="w-20 h-20 object-contain mb-2 drop-shadow-lg">
                            <a href="{{ route('fleet.show', $machine) }}" class="text-lg font-bold text-blue-700 dark:text-blue-300 hover:underline text-center block">{{ $machine->name }}</a>
                            <div class="text-xs text-gray-400 dark:text-gray-500 mt-1 text-center">{{ $machine->manufacturer ?: 'N/A' }} &bull; {{ $machine->model }}</div>
                        </div>
                        <div class="flex-1 flex flex-col justify-between p-4 gap-2">
                            <div class="flex items-center gap-2 mb-2">
                                @if ($machine->status === 'active')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800">
                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5 animate-pulse"></span>
                                        Active
                                    </span>
                                @elseif ($machine->status === 'idle')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800">
                                        Idle
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800">
                                        Maintenance
                                    </span>
                                @endif
                                <span class="ml-auto text-xs text-gray-400 dark:text-gray-500">{{ $machine->capacity ? number_format($machine->capacity) . ' tons' : 'N/A' }}</span>
                            </div>
                            {{-- Timing breakdown widget --}}
                            @if ($machine->cycle_time_minutes || $machine->queue_time_minutes || $machine->loading_time_minutes)
                            @php
                                $ct  = $machine->cycle_time_minutes   ?? 0;
                                $qt  = $machine->queue_time_minutes   ?? 0;
                                $lt  = $machine->loading_time_minutes ?? 0;
                                $tt  = $ct + $qt + $lt;
                                $pct_c = $tt ? round(($ct / $tt) * 100) : 0;
                                $pct_q = $tt ? round(($qt / $tt) * 100) : 0;
                                $pct_l = $tt ? max(0, 100 - $pct_c - $pct_q) : 0;
                            @endphp
                            <div class="mb-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-700">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Cycle Breakdown</p>
                                <div class="space-y-0.5 mb-2">
                                    @if ($ct)
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-amber-600 dark:text-amber-400">↻ Cycle</span>
                                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ $ct }}m</span>
                                    </div>
                                    @endif
                                    @if ($qt)
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-sky-600 dark:text-sky-400">⏳ Queue</span>
                                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ $qt }}m</span>
                                    </div>
                                    @endif
                                    @if ($lt)
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-emerald-600 dark:text-emerald-400">↧ Loading</span>
                                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ $lt }}m</span>
                                    </div>
                                    @endif
                                </div>
                                {{-- Stacked proportional bar --}}
                                <div class="flex w-full h-1.5 rounded-full overflow-hidden gap-px" title="Cycle / Queue / Loading">
                                    @if ($pct_c) <div class="bg-amber-400 h-full" style="width:{{ $pct_c }}%"></div> @endif
                                    @if ($pct_q) <div class="bg-sky-400 h-full" style="width:{{ $pct_q }}%"></div> @endif
                                    @if ($pct_l) <div class="bg-emerald-400 h-full" style="width:{{ $pct_l }}%"></div> @endif
                                </div>
                                <div class="flex justify-between text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    <span>Total {{ $tt }}m</span>
                                </div>
                            </div>
                            @endif
                            {{-- ── Engine Hours ──────────────────────────────── --}}
                            @php
                                $eng       = $engineHoursMap[$machine->id] ?? ['today_hours' => 0.0, 'is_running' => false];
                                $engPct    = min(100, $eng['today_hours'] > 0 ? round(($eng['today_hours'] / 12) * 100) : 0);
                                $engColor  = $engPct >= 90 ? 'bg-red-500' : ($engPct >= 70 ? 'bg-amber-400' : 'bg-green-500');
                            @endphp
                            <div class="mb-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-700">
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                                    <span class="flex items-center gap-1 font-medium">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Engine Hrs (Today)
                                    </span>
                                    <span class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-1">
                                        {{ number_format($eng['today_hours'], 1) }}h
                                        @if($eng['is_running'])
                                            <span class="inline-block w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse" title="Engine running"></span>
                                        @endif
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                    <div class="{{ $engColor }} h-1.5 rounded-full transition-all duration-500" style="width: {{ $engPct }}%"></div>
                                </div>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                        @if($eng['is_running'])
                                            <span class="text-green-600 dark:text-green-400 font-medium">● Running</span>
                                        @else
                                            Off
                                        @endif
                                    </span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">of 12h shift</span>
                                </div>
                            </div>
                            {{-- ── End Engine Hours ──────────────────────────── --}}
                            <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                @if ($machine->excavator)
                                    <span class="font-medium">Excavator:</span> {{ $machine->excavator->name }}
                                    <button wire:click="unassignFromExcavator({{ $machine->id }})" 
                                            class="ml-auto text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                            title="Unassign">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                @else
                                    @php
                                        $isExcavator = in_array(strtolower($machine->machine_type ?? ''), ['excavator','digger','loader']);
                                        $assignedAdtCount = $adts->where('excavator_id', $machine->id)->count();
                                    @endphp

                                    <button wire:click="openAssignModal({{ $machine->id }})" 
                                            class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 text-xs flex items-center gap-1 font-medium transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        @if($isExcavator)
                                            Manage ADTs ({{ $assignedAdtCount }})
                                        @else
                                            Assign to Excavator
                                        @endif
                                    </button>

                                    <button wire:click="openMineAreaAssignModal({{ $machine->id }})"
                                        class="text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300 text-xs flex items-center gap-1 font-medium transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7v6a2 2 0 01-2 2H8m8-8h-8a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2V7z" />
                                        </svg>
                                        @if($machine->mine_area_id)
                                            Change Mine Area
                                        @else
                                            Assign to Mine Area
                                        @endif
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="flex gap-2 p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800">
                            <button wire:click="editMachine({{ $machine->id }})" 
                                class="flex-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded-lg transition-all duration-200 font-medium hover:scale-105 transform">
                                Edit
                            </button>
                            <button wire:click="deleteMachine({{ $machine->id }})" wire:confirm="Are you sure you want to delete this machine?" 
                                class="flex-1 px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-xs rounded-lg transition-all duration-200 font-medium hover:scale-105 transform" 
                                wire:loading.attr="disabled" wire:target="deleteMachine({{ $machine->id }})">
                                <span wire:loading.remove wire:target="deleteMachine({{ $machine->id }})">Delete</span>
                                <span wire:loading wire:target="deleteMachine({{ $machine->id }})" class="flex items-center gap-1">
                                    <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Deleting
                                </span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
            <!-- Pagination -->
            <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-4 border-t border-gray-200 dark:border-gray-600">
                {{ $machines->links('pagination::tailwind') }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="text-6xl mb-4">🚜</div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">No machines found</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">
                    @if($search || $statusFilter)
                        Try adjusting your filters
                    @else
                        Create your first machine to get started
                    @endif
                </p>
                @if(!$search && !$statusFilter)
                <button wire:click="openCreateModal" 
                    class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white rounded-lg transition-all duration-200 shadow-lg hover:shadow-xl hover:scale-105 transform font-medium">
                    Add Your First Machine
                </button>
                @endif
            </div>
        @endif
    </div>

    {{-- ── Timing Analytics ─────────────────────────────────────────────── --}}
    @if (!empty($timingAnalytics['machines']))
    <div class="mt-8">
        {{-- Section header --}}
        <div class="flex items-center gap-3 mb-5">
            <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Timing Analytics</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Cycle, queue &amp; loading time across all fleet machines</p>
            </div>
        </div>

        {{-- Fleet-average stat cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-amber-200 dark:border-amber-800/40 p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Avg Cycle Time</p>
                <p class="text-3xl font-bold text-amber-600 dark:text-amber-400">
                    {{ $timingAnalytics['avg_cycle'] !== null ? $timingAnalytics['avg_cycle'] . ' min' : 'N/A' }}
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Full haul cycle</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-sky-200 dark:border-sky-800/40 p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Avg Queue Time</p>
                <p class="text-3xl font-bold text-sky-600 dark:text-sky-400">
                    {{ $timingAnalytics['avg_queue'] !== null ? $timingAnalytics['avg_queue'] . ' min' : 'N/A' }}
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Wait &amp; queue</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-emerald-200 dark:border-emerald-800/40 p-5 shadow-sm">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Avg Loading Time</p>
                <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                    {{ $timingAnalytics['avg_loading'] !== null ? $timingAnalytics['avg_loading'] . ' min' : 'N/A' }}
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Load cycle</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Grouped bar chart --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Per-Machine Timing Breakdown</h3>
                <div wire:ignore
                    x-data="{
                        chart: null,
                        init() {
                            this.$nextTick(() => this.draw());
                        },
                        draw() {
                            const ctx = this.$refs.timingChart;
                            if (!ctx) return;
                            if (this.chart) { this.chart.destroy(); }
                            const rows = {{ Js::from($timingAnalytics['machines']) }};
                            this.chart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: rows.map(r => r.name),
                                    datasets: [
                                        {
                                            label: 'Cycle (min)',
                                            data: rows.map(r => r.cycle),
                                            backgroundColor: 'rgba(245,158,11,0.85)',
                                            borderRadius: 3,
                                            borderSkipped: false,
                                        },
                                        {
                                            label: 'Queue (min)',
                                            data: rows.map(r => r.queue),
                                            backgroundColor: 'rgba(14,165,233,0.85)',
                                            borderRadius: 3,
                                            borderSkipped: false,
                                        },
                                        {
                                            label: 'Loading (min)',
                                            data: rows.map(r => r.loading),
                                            backgroundColor: 'rgba(16,185,129,0.85)',
                                            borderRadius: 3,
                                            borderSkipped: false,
                                        },
                                    ],
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'bottom',
                                            labels: { boxWidth: 12, padding: 16 },
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: c => c.dataset.label + ': ' + c.raw + ' min',
                                            },
                                        },
                                    },
                                    scales: {
                                        x: { grid: { display: false } },
                                        y: {
                                            beginAtZero: true,
                                            ticks: { callback: v => v + 'm' },
                                        },
                                    },
                                },
                            });
                        },
                    }"
                >
                    <canvas x-ref="timingChart" style="height:280px"></canvas>
                </div>
            </div>

            {{-- Summary table --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Fleet Timing Summary</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Machine</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-amber-600 dark:text-amber-400 uppercase tracking-wide">Cycle</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-sky-600 dark:text-sky-400 uppercase tracking-wide">Queue</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-emerald-600 dark:text-emerald-400 uppercase tracking-wide">Loading</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($timingAnalytics['machines'] as $tRow)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-2.5">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $tRow['name'] }}</p>
                                    <p class="text-xs text-gray-400 capitalize">{{ str_replace('_', ' ', $tRow['type']) }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium text-amber-600 dark:text-amber-400">
                                    {{ $tRow['cycle'] ? $tRow['cycle'] . 'm' : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium text-sky-600 dark:text-sky-400">
                                    {{ $tRow['queue'] ? $tRow['queue'] . 'm' : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium text-emerald-600 dark:text-emerald-400">
                                    {{ $tRow['loading'] ? $tRow['loading'] . 'm' : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-semibold text-gray-700 dark:text-gray-200">
                                    {{ $tRow['total'] ? $tRow['total'] . 'm' : '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-600">
                            <tr>
                                <td class="px-4 py-2.5 text-xs font-bold text-gray-600 dark:text-gray-300 uppercase">Fleet Avg</td>
                                <td class="px-4 py-2.5 text-right text-xs font-bold text-amber-600 dark:text-amber-400">
                                    {{ $timingAnalytics['avg_cycle'] !== null ? $timingAnalytics['avg_cycle'] . 'm' : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-xs font-bold text-sky-600 dark:text-sky-400">
                                    {{ $timingAnalytics['avg_queue'] !== null ? $timingAnalytics['avg_queue'] . 'm' : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ $timingAnalytics['avg_loading'] !== null ? $timingAnalytics['avg_loading'] . 'm' : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-xs font-bold text-gray-700 dark:text-gray-200">
                                    @php
                                        $avgTot = ($timingAnalytics['avg_cycle'] ?? 0)
                                                + ($timingAnalytics['avg_queue'] ?? 0)
                                                + ($timingAnalytics['avg_loading'] ?? 0);
                                    @endphp
                                    {{ $avgTot > 0 ? round($avgTot, 1) . 'm' : '—' }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Create/Edit Modal -->
    @if ($showCreateModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="closeModal">
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 w-full max-w-2xl" @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-white">
                        {{ $editingMachineId ? 'Edit Machine' : 'Add New Machine' }}
                    </h2>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form wire:submit="saveMachine" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Machine Name *</label>
                            <input 
                                type="text" 
                                wire:model="name" 
                                placeholder="e.g., Volvo Excavator #1"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-amber-500"
                            />
                            @error('name') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Machine Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Machine Type *</label>
                            <select 
                                wire:model="machineType"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-amber-500"
                            >
                                <option value="">Select Type</option>
                                <option value="adt">ADT (Articulated Dump Truck)</option>
                                <option value="excavator">Excavator</option>
                                <option value="dozer">Dozer</option>
                                <option value="grader">Grader</option>
                                <option value="loader">Loader</option>
                                <option value="drill">Drill</option>
                                <option value="truck">Truck</option>
                                <option value="ldv">LDV (Light Delivery Vehicle)</option>
                                <option value="other">Other</option>
                            </select>
                            @error('machineType') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Manufacturer -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Manufacturer</label>
                            <select 
                                wire:model="manufacturer"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-amber-500"
                            >
                                <option value="">Select Manufacturer</option>
                                <option value="Volvo">Volvo</option>
                                <option value="CAT">CAT (Caterpillar)</option>
                                <option value="Komatsu">Komatsu</option>
                                <option value="Bell">Bell</option>
                                <option value="Hitachi">Hitachi</option>
                                <option value="John Deere">John Deere</option>
                                <option value="Liebherr">Liebherr</option>
                                <option value="Hyundai">Hyundai</option>
                                <option value="Doosan">Doosan</option>
                                <option value="JCB">JCB</option>
                                <option value="CASE">CASE</option>
                                <option value="Sany">Sany</option>
                                <option value="XCMG">XCMG</option>
                                <option value="Kobelco">Kobelco</option>
                                <option value="New Holland">New Holland</option>
                                <option value="Takeuchi">Takeuchi</option>
                                <option value="Kubota">Kubota</option>
                                <option value="Bobcat">Bobcat</option>
                                <option value="Yanmar">Yanmar</option>
                                <option value="Atlas Copco">Atlas Copco</option>
                                <option value="Sandvik">Sandvik</option>
                                <option value="Epiroc">Epiroc</option>
                                <option value="Other">Other</option>
                            </select>
                            @error('manufacturer') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Model -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Model *</label>
                            <input 
                                type="text" 
                                wire:model="model" 
                                placeholder="e.g., A45G"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-amber-500"
                            />
                            @error('model') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Status *</label>
                            <select 
                                wire:model="status"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-amber-500"
                            >
                                <option value="active">Active</option>
                                <option value="idle">Idle</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                            @error('status') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Serial Number -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Serial Number</label>
                            <input 
                                type="text" 
                                wire:model="serialNumber" 
                                placeholder="e.g., SN123456789"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-amber-500"
                            />
                            @error('serialNumber') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Capacity -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Capacity (tons)</label>
                            <input 
                                type="number" 
                                wire:model="capacity" 
                                step="0.01"
                                placeholder="e.g., 45.5"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-amber-500"
                            />
                            @error('capacity') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Cycle Time -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Cycle Time (minutes)</label>
                            <input
                                type="number"
                                wire:model="cycleTimeMinutes"
                                min="0" max="9999"
                                placeholder="e.g., 25"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-amber-500"
                            />
                            @error('cycleTimeMinutes') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Queue Time -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Queue Time (minutes)</label>
                            <input
                                type="number"
                                wire:model="queueTimeMinutes"
                                min="0" max="9999"
                                placeholder="e.g., 5"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-amber-500"
                            />
                            @error('queueTimeMinutes') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Loading Time -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Loading Time (minutes)</label>
                            <input
                                type="number"
                                wire:model="loadingTimeMinutes"
                                min="0" max="9999"
                                placeholder="e.g., 8"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-amber-500"
                            />
                            @error('loadingTimeMinutes') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Latitude -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Latitude</label>
                            <input 
                                type="number" 
                                wire:model="latitude" 
                                step="0.0001"
                                placeholder="e.g., -25.5095"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-amber-500"
                            />
                            @error('latitude') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Longitude -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Longitude</label>
                            <input 
                                type="number" 
                                wire:model="longitude" 
                                step="0.0001"
                                placeholder="e.g., 131.0044"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-amber-500"
                            />
                            @error('longitude') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-700">
                        <button 
                            type="button" 
                            wire:click="closeModal"
                            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors"
                        >
                            {{ $editingMachineId ? 'Update Machine' : 'Create Machine' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Mine Area Assignment Modal -->
    @if ($showMineAreaAssignModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 animate-fade-in">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 w-full max-w-md animate-scale-in">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="text-amber-500">🏞️</span> Assign Machine to Mine Area
                </h2>
                <div class="mb-4">
                    <label for="mineAreaSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Mine Area</label>
                    <select id="mineAreaSelect" wire:model="selectedMineAreaId" class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                        <option value="">-- Choose Mine Area --</option>
                        @foreach ($mineAreas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }} ({{ ucfirst($area->type) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="closeMineAreaAssignModal" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">Cancel</button>
                    <button wire:click="assignToMineArea" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors disabled:opacity-50" @if(count($mineAreas) === 0) disabled @endif>Assign</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Excavator Assignment Modal -->
    @if ($showAssignModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="closeAssignModal">
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 w-full max-w-md" @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-white">Assign to Excavator</h2>
                    <button wire:click="closeAssignModal" class="text-gray-400 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="mb-6">
                    @if($assignMode === 'assign_adts_to_excavator')
                        <label class="block text-sm font-medium text-gray-300 mb-2">Assign ADTs</label>
                        @if($adts->count() === 0)
                            <p class="text-gray-400 text-sm">No ADTs available. Create ADT machines first.</p>
                        @else
                            <select multiple wire:model="selectedAdtIds" class="w-full h-40 px-3 py-2 bg-gray-900 border border-gray-700 rounded text-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                @foreach($adts as $adt)
                                    <option value="{{ $adt->id }}">{{ $adt->name }} ({{ ucfirst($adt->machine_type) }})</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-2">Tip: hold Ctrl/Cmd (or use touch gestures) to select multiple ADTs.</p>
                        @endif
                    @else
                        <label class="block text-sm font-medium text-gray-300 mb-2">Select Excavator</label>
                        @php $assigning = \App\Models\Machine::find($assigningMachineId); @endphp
                        <select wire:model="selectedExcavatorId" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-amber-500">
                            <option value="">Choose an excavator...</option>
                            @foreach ($excavators as $excavator)
                                @php
                                    $disableOption = false;
                                    $bigTypes = ['excavator','dozer','loader','grader','bulldozer'];
                                    if ($assigning && in_array(strtolower($assigning->machine_type ?? ''), $bigTypes) && in_array(strtolower($excavator->machine_type ?? ''), $bigTypes)) {
                                        $disableOption = true;
                                    }
                                @endphp
                                <option value="{{ $excavator->id }}" @if($disableOption) disabled title="Cannot assign big machines to another big machine" @endif>
                                    {{ $excavator->name }} ({{ ucfirst($excavator->machine_type) }}) @if($disableOption) — not allowed @endif
                                </option>
                            @endforeach

                            @php
                                $target = $selectedExcavatorId ? \App\Models\Machine::find($selectedExcavatorId) : null;
                                $assigningIsExcavator = $assigning && strtolower($assigning->machine_type ?? '') === 'excavator';
                                $targetIsExcavator = $target && strtolower($target->machine_type ?? '') === 'excavator';
                                $blockExcavatorToExcavator = $assigningIsExcavator && $targetIsExcavator;
                            @endphp

                            @if(!empty($assigning) && $blockExcavatorToExcavator)
                                <div class="mt-3 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-sm text-red-700 dark:text-red-300">
                                    Excavators cannot be assigned to other excavators.
                                </div>
                            @endif
                        </select>
                    @endif
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="closeAssignModal" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">Cancel</button>
                    <button wire:click="assignToExcavator" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors disabled:opacity-50" @if($assignMode === 'assign_adts_to_excavator' && count($adts) === 0) disabled @endif @if(isset($blockExcavatorToExcavator) && $blockExcavatorToExcavator) disabled @endif>
                        Assign
                    </button>
                </div>
            </div>
        </div>
    @endif

    @endif
</div>
<script>
(function injectFleetStyles() {
    if (document.getElementById('fleet-styles')) return;
    var s = document.createElement('style');
    s.id = 'fleet-styles';
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
