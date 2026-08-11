<div>
<div class="animate-fade-in">
    <!-- Loading Spinner -->
    @if ($isLoading)
        <div class="flex justify-center items-center h-96">
            <svg class="animate-spin h-12 w-12 text-[var(--gold)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
        </div>
        <script nonce="{{ request()->attributes->get('csp_nonce') }}">window.scrollTo(0,0);</script>
    @else
        <!-- Chart Visualization Placeholder removed -->
    <!-- Header Section with gradient -->
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl shadow-lg p-6 mb-6 animate-slide-down">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-[var(--stone)] flex items-center gap-2">
                    Fleet Management
                </h1>
                <p class="text-[var(--sand)] text-sm mt-1">
                    Manage and monitor your mining fleet equipment
                </p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('fleet.route-planning') }}" 
                    class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded-lg transition-all duration-200 flex items-center gap-2 text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    <span>Route Planning</span>
                </a>
                <a href="{{ route('fleet.replay') }}" 
                    class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded-lg transition-all duration-200 flex items-center gap-2 text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Movement Replay</span>
                </a>
                <button wire:click="openCreateModal" 
                    class="px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-all duration-200 flex items-center gap-2 text-sm font-display font-semibold shadow-lg hover:shadow-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Add Machine</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Status Statistics with animation -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-[var(--ink-soft)] rounded-lg shadow-lg p-6 border border-[var(--line)] hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 animate-scale-in">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-green-500/15 rounded-lg">
                    <svg class="w-6 h-6 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
            <p class="text-[var(--sand)] text-sm font-medium mb-1">Active</p>
            <p class="text-4xl font-display font-semibold text-[var(--stone)]" x-data="{ count: 0 }" x-init="() => { let target = {{ $statusStats['active'] }}; let duration = 2000; let increment = target / (duration / 16); let timer = setInterval(() => { count += increment; if (count >= target) { count = target; clearInterval(timer); } }, 16); }">
                <span x-text="Math.floor(count)">0</span>
            </p>
            <p class="text-xs text-green-400 mt-2 font-medium flex items-center gap-1">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                Operating now
            </p>
            @if ($statusStats['active'] === 0)
                <div class="text-center py-2">
                    <span class="text-xs text-[var(--sand)]">No active machines. <button wire:click="openCreateModal" class="text-[var(--gold)] underline">Add machine</button>.</span>
                </div>
            @endif
        </div>

        <div class="bg-[var(--ink-soft)] rounded-lg shadow-lg p-6 border border-[var(--line)] hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 animate-scale-in">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-blue-500/15 rounded-lg">
                    <svg class="w-6 h-6 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 100-2 1 1 0 000 2zm6 0a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
            <p class="text-[var(--sand)] text-sm font-medium mb-1">Idle</p>
            <p class="text-4xl font-display font-semibold text-[var(--stone)]" x-data="{ count: 0 }" x-init="() => { let target = {{ $statusStats['idle'] }}; let duration = 2000; let increment = target / (duration / 16); let timer = setInterval(() => { count += increment; if (count >= target) { count = target; clearInterval(timer); } }, 16); }">
                <span x-text="Math.floor(count)">0</span>
            </p>
            <p class="text-xs text-blue-400 mt-2 font-medium">
                Awaiting assignment
            </p>
            @if ($statusStats['idle'] === 0)
                <div class="text-center py-2">
                    <span class="text-xs text-[var(--sand)]">No idle machines. <button wire:click="openCreateModal" class="text-[var(--gold)] underline">Add machine</button>.</span>
                </div>
            @endif
        </div>

        <div class="bg-[var(--ink-soft)] rounded-lg shadow-lg p-6 border border-[var(--line)] hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 animate-scale-in">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-red-500/15 rounded-lg">
                    <svg class="w-6 h-6 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 2.523a6 6 0 008.367 8.367z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
            <p class="text-[var(--sand)] text-sm font-medium mb-1">Maintenance</p>
            <p class="text-4xl font-display font-semibold text-[var(--stone)]" x-data="{ count: 0 }" x-init="() => { let target = {{ $statusStats['maintenance'] }}; let duration = 2000; let increment = target / (duration / 16); let timer = setInterval(() => { count += increment; if (count >= target) { count = target; clearInterval(timer); } }, 16); }">
                <span x-text="Math.floor(count)">0</span>
            </p>
            <p class="text-xs text-red-400 mt-2 font-medium">
                Under service
            </p>
            @if ($statusStats['maintenance'] === 0)
                <div class="text-center py-2">
                    <span class="text-xs text-[var(--sand)]">No machines under maintenance. <button wire:click="openCreateModal" class="text-[var(--gold)] underline">Add machine</button>.</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Machine Performance Section -->
    @if($topPerformers->count() > 0 || $worstPerformers->count() > 0)
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-6 h-6 text-[var(--gold)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            <h2 class="text-2xl font-display font-semibold text-[var(--stone)]">Machine Performance</h2>
            <span class="text-xs text-[var(--sand)]">(Last 30 Days)</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top 5 Performers -->
            @if($topPerformers->count() > 0)
            <div class="bg-[var(--ink-soft)] rounded-lg shadow-lg p-6 border border-[var(--line)]">
                <div class="flex items-center gap-2 mb-4">
                    <div class="p-2 bg-green-500/15 rounded-lg">
                        <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-[var(--stone)]">Top 5 Performers</h3>
                </div>
                <div class="space-y-3">
                    @foreach($topPerformers as $index => $machine)
                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-green-500/10 to-emerald-500/10 rounded-lg border border-green-500/20">
                        <div class="flex-shrink-0 w-8 h-8 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-[var(--stone)] font-bold text-sm">
                            {{ $index + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('fleet.show', $machine['machine_id']) }}" class="font-semibold text-[var(--stone)] hover:text-[var(--gold)] truncate block">
                                {{ $machine['machine_name'] }}
                            </a>
                            <p class="text-xs text-[var(--sand)]">{{ ucfirst(str_replace('_', ' ', $machine['machine_type'])) }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-green-400">{{ $machine['performance_score'] }}%</div>
                            <div class="text-xs text-[var(--sand)]">Score</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Top 5 Worst Performers -->
            @if($worstPerformers->count() > 0)
            <div class="bg-[var(--ink-soft)] rounded-lg shadow-lg p-6 border border-[var(--line)]">
                <div class="flex items-center gap-2 mb-4">
                    <div class="p-2 bg-red-500/15 rounded-lg">
                        <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-[var(--stone)]">Needs Attention</h3>
                </div>
                <div class="space-y-3">
                    @foreach($worstPerformers as $index => $machine)
                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-red-500/10 to-orange-500/10 rounded-lg border border-red-500/20">
                        <div class="flex-shrink-0 w-8 h-8 bg-gradient-to-br from-red-500 to-orange-600 rounded-full flex items-center justify-center text-[var(--stone)] font-bold text-sm">
                            {{ $index + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('fleet.show', $machine['machine_id']) }}" class="font-semibold text-[var(--stone)] hover:text-[var(--gold)] truncate block">
                                {{ $machine['machine_name'] }}
                            </a>
                            <p class="text-xs text-[var(--sand)]">{{ ucfirst(str_replace('_', ' ', $machine['machine_type'])) }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-red-400">{{ $machine['performance_score'] }}%</div>
                            <div class="text-xs text-[var(--sand)]">Score</div>
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
            <svg class="w-6 h-6 text-[var(--gold)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
            </svg>
            <h2 class="text-2xl font-display font-semibold text-[var(--stone)]">AI Fleet Optimization</h2>
            <span class="badge badge-primary">AI-Powered</span>
        </div>

        <!-- AI Fleet Recommendations - Full Width -->
        @if($aiRecommendations->count() > 0)
        <div class="mb-6">
            <div class="bg-gradient-to-br from-[var(--umber)] to-[var(--ink)] rounded-lg shadow-lg p-6 border border-[var(--line)]">
                <h3 class="text-xl font-display font-semibold mb-4 flex items-center gap-2 text-[var(--stone)]">
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
                            <p class="text-sm text-[var(--sand)] mb-3">{{ $recommendation['description'] }}</p>
                            
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
                                    <ul class="text-xs space-y-1 text-[var(--sand)]">
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
                                <span class="text-xs text-[var(--sand)]">AI Confidence: {{ number_format($recommendation['confidence_score'] * 100, 0) }}%</span>
                                <div class="flex items-center gap-2">
                                    <button wire:click="implementRecommendation({{ $loop->index }})" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-[var(--stone)] rounded text-sm font-medium">Implement</button>
                                    <button wire:click="openRejectRecommendation({{ $loop->index }})" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-[var(--stone)] rounded text-sm font-medium">Reject</button>
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
            <div class="bg-gradient-to-br from-[var(--umber)] to-[var(--ink)] rounded-lg shadow-lg p-6 border border-[var(--line)]">
                <h3 class="text-xl font-display font-semibold mb-4 flex items-center gap-2 text-[var(--stone)]">
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
                            <p class="text-sm text-[var(--sand)]">{{ $insight['description'] }}</p>
                            
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
                    <div class="mt-4 p-3 bg-[var(--gold)]/10 border border-[var(--gold)]/20 rounded-lg">
                        <p class="text-xs text-[var(--sand)]">
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
    <div class="bg-[var(--ink-soft)] rounded-lg shadow-lg p-6 mb-6 border border-[var(--line)]">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-[var(--sand)] mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Search Machines
                </label>
                <input 
                    type="text" 
                    wire:model.live="search" 
                    placeholder="Name, model, or manufacturer..."
                    class="w-full px-4 py-2.5 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:ring-2 focus:ring-[var(--gold)] focus:border-transparent transition-all"
                />
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-[var(--sand)] mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Status Filter
                </label>
                <select 
                    wire:model.live="statusFilter"
                    class="w-full px-4 py-2.5 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:outline-none focus:ring-2 focus:ring-[var(--gold)] focus:border-transparent transition-all"
                >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="idle">Idle</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>

            <!-- Sort By -->
            <div>
                <label class="block text-sm font-medium text-[var(--sand)] mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                    </svg>
                    Sort By
                </label>
                <select 
                    wire:model.live="sortBy"
                    class="w-full px-4 py-2.5 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:outline-none focus:ring-2 focus:ring-[var(--gold)] focus:border-transparent transition-all"
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
    <div class="bg-[var(--ink-soft)] rounded-lg shadow-lg overflow-hidden border border-[var(--line)]">
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
                    <div class="bg-[var(--ink)] rounded-xl shadow-lg border border-[var(--line)] hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 flex flex-col">
                        <div class="flex flex-col items-center justify-center p-4 border-b border-[var(--line)] bg-gradient-to-b from-[var(--gold)]/10 to-[var(--gold)]/5">
                            <img src="{{ asset($icon) }}" alt="Machine Icon" class="w-20 h-20 object-contain mb-2 drop-shadow-lg">
                            <a href="{{ route('fleet.show', $machine) }}" class="text-lg font-display font-semibold text-[var(--stone)] hover:text-[var(--gold)] hover:underline text-center block">{{ $machine->name }}</a>
                            <div class="text-xs text-[var(--sand)] mt-1 text-center">{{ $machine->manufacturer ?: 'N/A' }} &bull; {{ $machine->model }}</div>
                        </div>
                        <div class="flex-1 flex flex-col justify-between p-4 gap-2">
                            <div class="flex items-center gap-2 mb-2">
                                @if ($machine->status === 'active')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-500/15 text-green-400 border border-green-500/25">
                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5 animate-pulse"></span>
                                        Active
                                    </span>
                                @elseif ($machine->status === 'idle')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-500/15 text-blue-400 border border-blue-500/25">
                                        Idle
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-500/15 text-red-400 border border-red-500/25">
                                        Maintenance
                                    </span>
                                @endif
                                <span class="ml-auto text-xs text-[var(--sand)]">{{ $machine->capacity ? number_format($machine->capacity) . ' tons' : 'N/A' }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-[var(--sand)]">
                                @if ($machine->excavator)
                                    <span class="font-medium">Excavator:</span> {{ $machine->excavator->name }}
                                    <button wire:click="unassignFromExcavator({{ $machine->id }})" 
                                            class="ml-auto text-red-400 hover:text-red-300 transition-colors"
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
                                            class="text-blue-400 hover:text-blue-300 text-xs flex items-center gap-1 font-medium transition-colors">
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
                                        class="text-[var(--gold)] hover:text-[var(--gold-soft)] text-xs flex items-center gap-1 font-medium transition-colors">
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
                        <div class="flex gap-2 p-4 border-t border-[var(--line)] bg-white/5">
                            <button wire:click="editMachine({{ $machine->id }})" 
                                class="flex-1 px-3 py-2 bg-white/10 hover:bg-white/20 text-[var(--stone)] text-xs rounded-lg transition-all duration-200 font-medium">
                                Edit
                            </button>
                            <button wire:click="deleteMachine({{ $machine->id }})" wire:confirm="Are you sure you want to delete this machine?" 
                                class="flex-1 px-3 py-2 bg-red-600 hover:bg-red-700 text-[var(--stone)] text-xs rounded-lg transition-all duration-200 font-medium hover:scale-105 transform" 
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
            <div class="bg-white/5 px-6 py-4 border-t border-[var(--line)]">
                {{ $machines->links('pagination::tailwind') }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="text-6xl mb-4">🚜</div>
                <h3 class="text-xl font-display font-semibold text-[var(--stone)] mb-2">No machines found</h3>
                <p class="text-[var(--sand)] text-sm mb-4">
                    @if($search || $statusFilter)
                        Try adjusting your filters
                    @else
                        Create your first machine to get started
                    @endif
                </p>
                @if(!$search && !$statusFilter)
                <button wire:click="openCreateModal" 
                    class="px-6 py-3 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-all duration-200 shadow-lg hover:shadow-xl font-display font-semibold">
                    Add Your First Machine
                </button>
                @endif
            </div>
        @endif
    </div>

    <!-- Create/Edit Modal -->
    @if ($showCreateModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click="closeModal">
            <div class="bg-[var(--ink-soft)] rounded-lg border border-[var(--line)] p-6 w-full max-w-2xl" @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-[var(--stone)]">
                        {{ $editingMachineId ? 'Edit Machine' : 'Add New Machine' }}
                    </h2>
                    <button wire:click="closeModal" class="text-[var(--sand)] hover:text-[var(--stone)]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form wire:submit="saveMachine" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Machine Name *</label>
                            <input 
                                type="text" 
                                wire:model="name" 
                                placeholder="e.g., Volvo Excavator #1"
                                class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]"
                            />
                            @error('name') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Machine Type -->
                        <div>
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Machine Type *</label>
                            <select 
                                wire:model="machineType"
                                class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:outline-none focus:border-[var(--gold)]"
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
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Manufacturer</label>
                            <select 
                                wire:model="manufacturer"
                                class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:outline-none focus:border-[var(--gold)]"
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
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Model *</label>
                            <input 
                                type="text" 
                                wire:model="model" 
                                placeholder="e.g., A45G"
                                class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]"
                            />
                            @error('model') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Status *</label>
                            <select 
                                wire:model="status"
                                class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:outline-none focus:border-[var(--gold)]"
                            >
                                <option value="active">Active</option>
                                <option value="idle">Idle</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                            @error('status') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Serial Number -->
                        <div>
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Serial Number</label>
                            <input 
                                type="text" 
                                wire:model="serialNumber" 
                                placeholder="e.g., SN123456789"
                                class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]"
                            />
                            @error('serialNumber') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Capacity -->
                        <div>
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Capacity (tons)</label>
                            <input 
                                type="number" 
                                wire:model="capacity" 
                                step="0.01"
                                placeholder="e.g., 45.5"
                                class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]"
                            />
                            @error('capacity') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Latitude -->
                        <div>
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Latitude</label>
                            <input 
                                type="number" 
                                wire:model="latitude" 
                                step="0.0001"
                                placeholder="e.g., -25.5095"
                                class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]"
                            />
                            @error('latitude') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Longitude -->
                        <div>
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Longitude</label>
                            <input 
                                type="number" 
                                wire:model="longitude" 
                                step="0.0001"
                                placeholder="e.g., 131.0044"
                                class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]"
                            />
                            @error('longitude') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-[var(--line)]">
                        <button 
                            type="button" 
                            wire:click="closeModal"
                            class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded-lg transition-colors"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-colors"
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
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 animate-fade-in">
            <div class="bg-[var(--ink-soft)] rounded-lg shadow-lg p-8 w-full max-w-md animate-scale-in">
                <h2 class="text-xl font-display font-semibold text-[var(--stone)] mb-4 flex items-center gap-2">
                    Assign Machine to Mine Area
                </h2>
                <div class="mb-4">
                    <label for="mineAreaSelect" class="block text-sm font-medium text-[var(--sand)] mb-2">Select Mine Area</label>
                    <select id="mineAreaSelect" wire:model="selectedMineAreaId" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] text-[var(--stone)] rounded-lg focus:ring-2 focus:ring-[var(--gold)] focus:border-[var(--gold)]">
                        <option value="">-- Choose Mine Area --</option>
                        @foreach ($mineAreas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }} ({{ ucfirst($area->type) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="closeMineAreaAssignModal" class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded-lg transition-colors">Cancel</button>
                    <button wire:click="assignToMineArea" class="px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-colors disabled:opacity-50" @if(count($mineAreas) === 0) disabled @endif>Assign</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Excavator Assignment Modal -->
    @if ($showAssignModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click="closeAssignModal">
            <div class="bg-[var(--ink-soft)] rounded-lg border border-[var(--line)] p-6 w-full max-w-md" @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-[var(--stone)]">Assign to Excavator</h2>
                    <button wire:click="closeAssignModal" class="text-[var(--sand)] hover:text-[var(--stone)]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="mb-6">
                    @if($assignMode === 'assign_adts_to_excavator')
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Assign ADTs</label>
                        @if($adts->count() === 0)
                            <p class="text-[var(--sand)] text-sm">No ADTs available. Create ADT machines first.</p>
                        @else
                            <select multiple wire:model="selectedAdtIds" class="w-full h-40 px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)] focus:outline-none focus:ring-2 focus:ring-[var(--gold)]">
                                @foreach($adts as $adt)
                                    <option value="{{ $adt->id }}">{{ $adt->name }} ({{ ucfirst($adt->machine_type) }})</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-[var(--sand)] mt-2">Tip: hold Ctrl/Cmd (or use touch gestures) to select multiple ADTs.</p>
                        @endif
                    @else
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Select Excavator</label>
                        @php $assigning = \App\Models\Machine::find($assigningMachineId); @endphp
                        <select wire:model="selectedExcavatorId" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:outline-none focus:border-[var(--gold)]">
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
                                <div class="mt-3 p-3 bg-red-500/10 border border-red-500/25 rounded text-sm text-red-300">
                                    Excavators cannot be assigned to other excavators.
                                </div>
                            @endif
                        </select>
                    @endif
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="closeAssignModal" class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded-lg transition-colors">Cancel</button>
                    <button wire:click="assignToExcavator" class="px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-colors disabled:opacity-50" @if($assignMode === 'assign_adts_to_excavator' && count($adts) === 0) disabled @endif @if(isset($blockExcavatorToExcavator) && $blockExcavatorToExcavator) disabled @endif>
                        Assign
                    </button>
                </div>
            </div>
        </div>
    @endif

    @endif
</div>
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.animate-fade-in {
    animation: fadeIn 0.6s ease-out;
}

.animate-slide-down {
    animation: slideDown 0.5s ease-out;
}

.animate-scale-in {
    animation: scaleIn 0.4s ease-out forwards;
    opacity: 0;
}
</style>
</div>
