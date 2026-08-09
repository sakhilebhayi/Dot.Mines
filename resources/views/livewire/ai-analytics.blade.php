<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-[var(--stone)]">📊 AI Analytics & Insights</h1>
                <p class="mt-1 text-sm text-[var(--sand)]">
                    Comprehensive analytics and performance metrics for AI optimization
                </p>
            </div>
            <div class="flex gap-2">
                <select wire:model.live="timeRange" class="rounded-lg border border-[var(--line)] bg-white/5 text-[var(--stone)] px-3 py-2">
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="180">Last 6 months</option>
                </select>
            </div>
        </div>

        <!-- Key Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Recommendations -->
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-[var(--stone)] transform hover:scale-105 transition-transform">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-white/20 rounded-lg backdrop-blur-sm">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <span class="text-sm font-medium opacity-90">Total</span>
                </div>
                <h3 class="text-3xl font-bold mb-1">{{ $categoryBreakdown->sum('count') }}</h3>
                <p class="text-sm opacity-90">Recommendations Generated</p>
            </div>

            <!-- Total Savings -->
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-[var(--stone)] transform hover:scale-105 transition-transform">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-white/20 rounded-lg backdrop-blur-sm">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-medium opacity-90">Realized</span>
                </div>
                <h3 class="text-3xl font-bold mb-1">R{{ number_format($categoryBreakdown->sum('savings'), 0) }}</h3>
                <p class="text-sm opacity-90">Total Savings</p>
            </div>

            <!-- Implementation Rate -->
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-[var(--stone)] transform hover:scale-105 transition-transform">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-white/20 rounded-lg backdrop-blur-sm">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-medium opacity-90">Success</span>
                </div>
                <h3 class="text-3xl font-bold mb-1">{{ $implementationRate->count() > 0 ? round($implementationRate->avg('rate'), 1) : 0 }}%</h3>
                <p class="text-sm opacity-90">Implementation Rate</p>
            </div>

            <!-- Average Accuracy -->
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-[var(--stone)] transform hover:scale-105 transition-transform">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-white/20 rounded-lg backdrop-blur-sm">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-medium opacity-90">AI Power</span>
                </div>
                <h3 class="text-3xl font-bold mb-1">{{ round($agents->avg('accuracy_score') * 100, 1) }}%</h3>
                <p class="text-sm opacity-90">Average Accuracy</p>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Category Breakdown Chart -->
            <div class="bg-[var(--ink-soft)] rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">📊 Recommendations by Category</h3>
                <div class="space-y-3">
                    @foreach($categoryBreakdown as $item)
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-medium text-[var(--sand)] capitalize">
                                {{ $item->category }}
                            </span>
                            <span class="text-sm text-[var(--sand)]">
                                {{ $item->count }} ({{ $categoryBreakdown->sum('count') > 0 ? round(($item->count / $categoryBreakdown->sum('count')) * 100, 1) : 0 }}%)
                            </span>
                        </div>
                        <div class="w-full bg-white/10 rounded-full h-3">
                            <div class="h-3 rounded-full transition-all duration-500 @if($item->category === 'fleet') bg-blue-500 @elseif($item->category === 'fuel') bg-yellow-500 @elseif($item->category === 'maintenance') bg-red-500 @elseif($item->category === 'production') bg-green-500 @elseif($item->category === 'route') bg-purple-500 @else bg-gray-500 @endif" 
                                 style="width: {{ $categoryBreakdown->sum('count') > 0 ? ($item->count / $categoryBreakdown->sum('count')) * 100 : 0 }}%">
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Priority Distribution -->
            <div class="bg-[var(--ink-soft)] rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">⚡ Priority Distribution</h3>
                <div class="grid grid-cols-2 gap-4">
                    @foreach($priorityDistribution as $item)
                    <div class="text-center p-4 rounded-lg @if($item->priority === 'critical') bg-red-900/20 @elseif($item->priority === 'high') bg-orange-900/20 @elseif($item->priority === 'medium') bg-yellow-900/20 @else bg-green-900/20 @endif">
                        <div class="text-3xl font-bold @if($item->priority === 'critical') text-red-400 @elseif($item->priority === 'high') text-orange-400 @elseif($item->priority === 'medium') text-yellow-400 @else text-green-400 @endif">
                            {{ $item->count }}
                        </div>
                        <div class="text-xs font-medium uppercase mt-1 @if($item->priority === 'critical') text-red-300 @elseif($item->priority === 'high') text-orange-300 @elseif($item->priority === 'medium') text-yellow-300 @else text-green-300 @endif">
                            {{ $item->priority }}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Agent Performance Section -->
        <div class="bg-[var(--ink-soft)] rounded-xl shadow-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-[var(--stone)] mb-6">🤖 AI Agent Performance</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($agentPerformance as $agent)
                <div class="bg-gradient-to-br from-white/5 to-white/10 rounded-lg p-4 border border-[var(--line)] hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-[var(--stone)] text-sm">{{ $agent['name'] }}</h4>
                        <span class="text-xs px-2 py-1 rounded-full bg-blue-900 text-blue-200">
                            {{ round($agent['accuracy'] * 100) }}%
                        </span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between text-xs">
                            <span class="text-[var(--sand)]">Total</span>
                            <span class="font-medium text-[var(--stone)]">{{ $agent['total_recommendations'] }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-[var(--sand)]">Implemented</span>
                            <span class="font-medium text-green-400">{{ $agent['implemented'] }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-[var(--sand)]">Pending</span>
                            <span class="font-medium text-yellow-400">{{ $agent['pending'] }}</span>
                        </div>
                        @if($agent['total_savings'] > 0)
                        <div class="pt-2 border-t border-[var(--line)]">
                            <div class="text-xs text-[var(--sand)]">Savings</div>
                            <div class="text-sm font-bold text-green-400">R{{ number_format($agent['total_savings'], 0) }}</div>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Savings Timeline -->
            <div class="bg-[var(--ink-soft)] rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">💰 Savings Timeline</h3>
                @if($savingsTimeline->count() > 0)
                <div class="space-y-2">
                    @php
                        $maxSavings = $savingsTimeline->max('savings') ?: 1;
                    @endphp
                    @foreach($savingsTimeline->take(10) as $item)
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-[var(--sand)] w-20">{{ \Carbon\Carbon::parse($item->date)->format('M d') }}</span>
                        <div class="flex-1">
                            <div class="w-full bg-white/10 rounded-full h-6 relative overflow-hidden">
                                <div class="h-6 bg-gradient-to-r from-green-400 to-green-600 rounded-full flex items-center justify-end pr-2 transition-all duration-500" 
                                     style="width: {{ ($item->savings / $maxSavings) * 100 }}%">
                                    <span class="text-xs font-medium text-[var(--stone)]">R{{ number_format($item->savings, 0) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-sm text-[var(--sand)] text-center py-8">No implemented recommendations yet</p>
                @endif
            </div>

            <!-- Implementation Rate Trend -->
            <div class="bg-[var(--ink-soft)] rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">📈 Implementation Rate Trend</h3>
                @if($implementationRate->count() > 0)
                <div class="space-y-2">
                    @foreach($implementationRate->take(10) as $item)
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-[var(--sand)] w-20">{{ \Carbon\Carbon::parse($item->date)->format('M d') }}</span>
                        <div class="flex-1">
                            <div class="w-full bg-white/10 rounded-full h-6 relative overflow-hidden">
                                <div class="h-6 bg-gradient-to-r from-blue-400 to-purple-600 rounded-full flex items-center justify-end pr-2 transition-all duration-500" 
                                     style="width: {{ $item->rate }}%">
                                    <span class="text-xs font-medium text-[var(--stone)]">{{ round($item->rate, 1) }}%</span>
                                </div>
                            </div>
                        </div>
                        <span class="text-xs text-[var(--sand)] w-16">{{ $item->implemented }}/{{ $item->total }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-sm text-[var(--sand)] text-center py-8">No data available</p>
                @endif
            </div>
        </div>

        <!-- Top Recommendations -->
        <div class="bg-[var(--ink-soft)] rounded-xl shadow-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">🏆 Top Implemented Recommendations</h3>
            @if($topRecommendations->count() > 0)
            <div class="space-y-3">
                @foreach($topRecommendations as $rec)
                <div class="flex items-center justify-between p-4 bg-white/5 rounded-lg hover:shadow-md transition-shadow">
                    <div class="flex-1">
                        <h4 class="font-medium text-[var(--stone)] text-sm">{{ $rec->title }}</h4>
                        <p class="text-xs text-[var(--sand)] mt-1">{{ $rec->aiAgent->name }} • {{ $rec->implemented_at->diffForHumans() }}</p>
                    </div>
                    <div class="text-right ml-4">
                        <div class="text-lg font-bold text-green-400">R{{ number_format($rec->estimated_savings, 0) }}</div>
                        <div class="text-xs text-[var(--sand)]">{{ round($rec->confidence_score * 100) }}% confidence</div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-[var(--sand)] text-center py-8">No implemented recommendations yet</p>
            @endif
        </div>

        <!-- Recent Analysis Sessions -->
        <div class="bg-[var(--ink-soft)] rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">🔄 Recent Analysis Sessions</h3>
            @if($recentSessions->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--line)]">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-[var(--sand)] uppercase">Agent</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-[var(--sand)] uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-[var(--sand)] uppercase">Recommendations</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-[var(--sand)] uppercase">Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-[var(--sand)] uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--line)]">
                        @foreach($recentSessions->take(10) as $session)
                        <tr class="hover:bg-white/5">
                            <td class="px-4 py-3 text-sm text-[var(--stone)]">{{ $session->aiAgent->name }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                    @if($session->status === 'completed') bg-green-900 text-green-200
                                    @elseif($session->status === 'running') bg-blue-900 text-blue-200
                                    @else bg-red-900 text-red-200 @endif">
                                    {{ ucfirst($session->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-[var(--stone)]">{{ $session->recommendations_generated }}</td>
                            <td class="px-4 py-3 text-sm text-[var(--sand)]">{{ $session->processing_time_ms ? $session->processing_time_ms . 'ms' : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-[var(--sand)]">{{ $session->created_at->format('M d, H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-sm text-[var(--sand)] text-center py-8">No analysis sessions found</p>
            @endif
        </div>
    </div>
</div>
