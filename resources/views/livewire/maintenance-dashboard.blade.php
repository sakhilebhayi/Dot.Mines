<div>
<div class="w-full">
    <div class="p-6">
        <div class="mb-6 flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-display font-semibold">Maintenance & Health Monitoring</h1>
                <p class="text-[var(--sand)] mt-2">Track machine health, schedule maintenance, and manage work orders</p>
            </div>
            <button wire:click="openBookingModal" class="btn btn-primary gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Book Maintenance
            </button>
        </div>

    <!-- Period Selector -->
    <div class="mb-6 flex items-center gap-4">
        <select wire:model.live="selectedPeriod" class="select select-bordered bg-white/5 border-[var(--line)] text-[var(--stone)] [&>option]:text-[var(--ink)]">
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
            <option value="quarter">This Quarter</option>
            <option value="year">This Year</option>
        </select>
        
        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model.live="showCriticalOnly" class="checkbox checkbox-error" />
            <span>Show Critical Only</span>
        </label>

        <!-- Loading Indicator for Filter Changes -->
        <div wire:loading wire:target="selectedPeriod,showCriticalOnly" class="flex items-center gap-2 text-sm text-[var(--gold)]">
            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Loading...</span>
        </div>
    </div>

    <!-- Health Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Average Health Score</div>
            {{-- 0% with no health rows reads as "the whole fleet is dying";
                 say honestly that nothing has been assessed yet. --}}
            @if (($healthStats['assessed_machines'] ?? 0) > 0)
                <div class="stat-value text-primary">{{ $healthStats['avg_health_score'] }}%</div>
                <div class="stat-desc">{{ $healthStats['assessed_machines'] }} of {{ $healthStats['total_machines'] }} machines assessed</div>
            @else
                <div class="stat-value text-primary">&mdash;</div>
                <div class="stat-desc">No health assessments yet ({{ $healthStats['total_machines'] }} machines)</div>
            @endif
        </div>
        
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Excellent/Good</div>
            <div class="stat-value text-success">{{ $healthStats['excellent'] + $healthStats['good'] }}</div>
            <div class="stat-desc">{{ $healthStats['excellent'] }} excellent, {{ $healthStats['good'] }} good</div>
        </div>
        
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Fair/Poor</div>
            <div class="stat-value text-warning">{{ $healthStats['fair'] + $healthStats['poor'] }}</div>
            <div class="stat-desc">{{ $healthStats['fair'] }} fair, {{ $healthStats['poor'] }} poor</div>
        </div>
        
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Critical</div>
            <div class="stat-value text-error">{{ $healthStats['critical'] }}</div>
            <div class="stat-desc">Require immediate attention</div>
        </div>
    </div>

    <!-- Maintenance Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Completed</div>
            <div class="stat-value text-sm">{{ number_format($maintenanceStats['total_completed']) }}</div>
        </div>
        
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">In Progress</div>
            <div class="stat-value text-sm">{{ number_format($maintenanceStats['in_progress']) }}</div>
        </div>
        
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Total Cost</div>
            <div class="stat-value text-sm">R{{ number_format($maintenanceStats['total_cost'], 2) }}</div>
        </div>
        
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Avg Repair Time</div>
            <div class="stat-value text-sm">{{ number_format($maintenanceStats['avg_repair_time'], 1) }}h</div>
        </div>
    </div>

    <!-- Fleet Delays Overview -->
    @if($delayedMachines->count() > 0)
    <div class="alert alert-error mb-6">
        <div class="flex-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
                <h3 class="font-bold">Fleet Delays Detected</h3>
                <div class="text-sm mt-1">
                    <strong>{{ $delayStats['total_delayed'] }}</strong> machines delayed • 
                    <strong>{{ number_format($delayStats['total_lost_hours'], 1) }}</strong> total hours lost • 
                    <span class="badge badge-error badge-sm">{{ $delayStats['critical'] }} Critical</span>
                    <span class="badge badge-warning badge-sm ml-1">{{ $delayStats['severe'] }} Severe</span>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Fleet Delays Section -->
    <div class="card bg-base-200 mb-6">
        <div class="card-body">
            <h2 class="card-title text-red-600 flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Fleet Delays & Production Impact
                @if($delayedMachines->count() > 0)
                    <span class="badge badge-error">{{ $delayedMachines->count() }}</span>
                @endif
            </h2>
            
            @if($delayedMachines->count() > 0)
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Machine</th>
                                <th>Delay Duration</th>
                                <th>Severity</th>
                                <th>Reason</th>
                                <th>Type</th>
                                <th>Expected Return</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($delayedMachines as $delay)
                            <tr class="hover:bg-base-300">
                                <td>
                                    <div class="flex items-center gap-2">
                                        @if($delay['color_code'] === 'red')
                                            <div class="w-3 h-3 rounded-full bg-red-500 animate-pulse" title="Critical Delay"></div>
                                        @elseif($delay['color_code'] === 'orange')
                                            <div class="w-3 h-3 rounded-full bg-orange-500" title="Severe Delay"></div>
                                        @elseif($delay['color_code'] === 'yellow')
                                            <div class="w-3 h-3 rounded-full bg-yellow-500" title="Warning"></div>
                                        @else
                                            <div class="w-3 h-3 rounded-full bg-blue-500" title="Minor Delay"></div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong>{{ $delay['machine']->name }}</strong>
                                        <br>
                                        <span class="text-xs text-[var(--sand)]">{{ $delay['machine']->model }}</span>
                                    </div>
                                </td>
                                <td>
                                    <strong>{{ number_format($delay['delay_hours'], 1) }}h</strong>
                                    @if($delay['delay_hours'] >= 24)
                                        <br><span class="text-xs text-[var(--sand)]">{{ number_format($delay['delay_hours'] / 24, 1) }} days</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-sm 
                                        @if($delay['severity'] === 'Critical') badge-error
                                        @elseif($delay['severity'] === 'Severe') badge-warning
                                        @elseif($delay['severity'] === 'Warning') badge-warning badge-outline
                                        @else badge-info
                                        @endif">
                                        {{ $delay['severity'] }}
                                    </span>
                                </td>
                                <td>
                                    <div class="max-w-xs">
                                        <p class="text-sm">{{ $delay['delay_reason'] }}</p>
                                    </div>
                                </td>
                                <td>
                                    @if($delay['maintenance_type'])
                                        <span class="badge badge-ghost badge-sm">{{ ucfirst($delay['maintenance_type']) }}</span>
                                    @else
                                        <span class="text-[var(--sand)]">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($delay['expected_return'])
                                        {{ $delay['expected_return']->format('M d, H:i') }}
                                        <br><span class="text-xs text-[var(--sand)]">{{ $delay['expected_return']->diffForHumans() }}</span>
                                    @else
                                        <span class="text-[var(--sand)]">Unknown</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Color Code Legend -->
                <div class="mt-4 p-4 bg-base-300 rounded-lg">
                    <h4 class="font-semibold mb-2 text-sm">Delay Severity Color Codes:</h4>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <span><strong>Critical:</strong> 48+ hours (2+ days)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-orange-500"></div>
                            <span><strong>Severe:</strong> 24-48 hours (1-2 days)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                            <span><strong>Warning:</strong> 12-24 hours</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                            <span><strong>Minor:</strong> Less than 12 hours</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="text-xl font-bold text-green-600 mb-2">No Fleet Delays</h3>
                    <p class="text-[var(--sand)]">All machines are operating as expected with no significant delays.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- AI-Powered Predictive Maintenance -->
    @if($aiRecommendations->count() > 0 || $aiInsights->count() > 0)
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-6 h-6 text-[var(--gold)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
            </svg>
            <h2 class="text-2xl font-bold">AI Predictive Maintenance</h2>
            <span class="badge badge-primary">AI-Powered</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- AI Breakdown Predictions & Recommendations -->
            @if($aiRecommendations->count() > 0)
            <div class="card bg-base-200 border border-red-500/30">
                <div class="card-body">
                    <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        Breakdown Predictions
                    </h3>
                    <div class="space-y-3">
                        @foreach($aiRecommendations as $recommendation)
                        <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 border border-white/20">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-semibold">{{ $recommendation['title'] }}</span>
                                    </div>
                                    @if(isset($recommendation['related_machine_id']))
                                        @php
                                            $machine = \App\Models\Machine::find($recommendation['related_machine_id']);
                                        @endphp
                                        @if($machine)
                                            <div class="text-xs text-[var(--sand)] mb-2">
                                                {{ $machine->manufacturer }} {{ $machine->model }}
                                            </div>
                                        @endif
                                    @endif
                                </div>
                                <span class="badge ml-2
                                    @if($recommendation['priority'] === 'critical') badge-error
                                    @elseif($recommendation['priority'] === 'high') badge-warning
                                    @else badge-info
                                    @endif
                                ">{{ ucfirst($recommendation['priority']) }}</span>
                            </div>
                            <p class="text-sm text-[var(--sand)] mb-3">{{ $recommendation['description'] }}</p>
                            
                            @if(isset($recommendation['data']['risk_score']))
                            <div class="mb-2">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span>Breakdown Risk</span>
                                    <span class="font-bold">{{ number_format($recommendation['data']['risk_score'] * 100, 0) }}%</span>
                                </div>
                                <progress class="progress progress-error w-full" value="{{ $recommendation['data']['risk_score'] * 100 }}" max="100"></progress>
                            </div>
                            @endif

                            @if(isset($recommendation['data']['estimated_days_until_breakdown']))
                            <div class="bg-red-500/20 border border-red-500/30 rounded p-2 mb-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span><strong>Est. {{ $recommendation['data']['estimated_days_until_breakdown'] }} days</strong> until breakdown</span>
                                </div>
                            </div>
                            @endif

                            @if(isset($recommendation['estimated_savings']) && $recommendation['estimated_savings'] > 0)
                            <div class="flex items-center gap-2 text-green-300 text-sm mb-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>Preventive: R{{ number_format($recommendation['estimated_savings'] * 0.2, 2) }} vs Breakdown: R{{ number_format($recommendation['estimated_savings'], 2) }}</span>
                            </div>
                            @endif

                            @if(isset($recommendation['impact_analysis']['recommended_actions']))
                            <details class="collapse collapse-arrow bg-white/5 mt-2">
                                <summary class="collapse-title text-sm font-medium py-2 min-h-0">Recommended Actions</summary>
                                <div class="collapse-content px-2 pb-2">
                                    <ul class="text-xs space-y-1 list-disc list-inside text-[var(--sand)]">
                                        @foreach($recommendation['impact_analysis']['recommended_actions'] as $action)
                                            <li>{{ $action }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </details>
                            @endif

                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-white/10">
                                <span class="text-xs text-[var(--sand)]">AI Confidence: {{ number_format($recommendation['confidence_score'] * 100, 0) }}%</span>
                                <button wire:click="openBookingModal" class="btn btn-xs btn-warning gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Schedule
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- AI Insights & Patterns -->
            @if($aiInsights->count() > 0)
            <div class="card bg-base-200 border border-blue-500/30">
                <div class="card-body">
                    <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Health Insights
                    </h3>
                    <div class="space-y-3">
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
                            
                            @if(isset($insight['data']['risk_score']))
                            <div class="mt-2 text-xs text-[var(--sand)]">
                                Risk Score: <span class="font-bold">{{ $insight['data']['risk_score'] }}%</span>
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
                            <strong>AI Analysis:</strong> Predictions based on historical patterns, operating hours, maintenance history, and real-time sensor data.
                        </p>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Active Alerts -->
    @if($activeAlerts->count() > 0)
    <div class="card bg-error text-error-content mb-6">
        <div class="card-body">
            <h2 class="card-title">Active Maintenance Alerts ({{ $activeAlerts->count() }})</h2>
            <div class="space-y-2">
                @foreach($activeAlerts->take(5) as $alert)
                <div class="alert 
                    @if($alert->severity === 'critical') alert-error
                    @elseif($alert->severity === 'warning') alert-warning
                    @else alert-info
                    @endif
                ">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="badge 
                                @if($alert->severity === 'critical') badge-error
                                @elseif($alert->severity === 'warning') badge-warning
                                @else badge-info
                                @endif
                            ">{{ $alert->severity }}</span>
                            <strong>{{ $alert->title }}</strong>
                        </div>
                        <p class="text-sm">{{ $alert->message }}</p>
                        <p class="text-xs opacity-70">{{ $alert->machine?->name }} - {{ $alert->triggered_at->diffForHumans() }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Due & Overdue Maintenance -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Overdue Maintenance -->
        <div class="card bg-error text-error-content">
            <div class="card-body">
                <h2 class="card-title">Overdue Maintenance ({{ $overdueSchedules->count() }})</h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>Maintenance</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($overdueSchedules as $schedule)
                            <tr>
                                <td><strong>{{ $schedule->machine->name }}</strong></td>
                                <td>{{ $schedule->title }}</td>
                                <td>
                                    {{ $schedule->next_service_date?->format('M d, Y') ?? 'N/A' }}
                                    @if($schedule->next_service_date)
                                    <br><span class="text-xs">{{ $schedule->next_service_date->diffForHumans() }}</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center">No overdue maintenance</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Due Maintenance -->
        <div class="card bg-warning text-warning-content">
            <div class="card-body">
                <h2 class="card-title">Due Maintenance ({{ $dueSchedules->count() }})</h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>Maintenance</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dueSchedules as $schedule)
                            <tr>
                                <td><strong>{{ $schedule->machine->name }}</strong></td>
                                <td>{{ $schedule->title }}</td>
                                <td>
                                    {{ $schedule->next_service_date?->format('M d, Y') ?? 'N/A' }}
                                    @if($schedule->next_service_date)
                                    <br><span class="text-xs">{{ $schedule->next_service_date->diffForHumans() }}</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center">No due maintenance</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Planned vs Actual Maintenance -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Planned Maintenance (Scheduled) -->
        <div class="card bg-base-200">
            <div class="card-body">
                <h2 class="card-title text-[var(--gold)]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Planned Maintenance ({{ $scheduledMaintenance->count() }})
                </h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>Type</th>
                                <th>Scheduled Date</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($scheduledMaintenance as $record)
                            <tr>
                                <td><strong>{{ $record->machine->name }}</strong></td>
                                <td>
                                    <span class="badge badge-sm badge-outline">{{ ucfirst($record->maintenance_type) }}</span>
                                    <br>
                                    <span class="text-xs">{{ $record->title }}</span>
                                </td>
                                <td>
                                    {{ \Carbon\Carbon::parse($record->scheduled_date)->format('M d, Y') }}
                                    <br><span class="text-xs text-[var(--sand)]">{{ \Carbon\Carbon::parse($record->scheduled_date)->diffForHumans() }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-sm 
                                        @if($record->priority === 'critical') badge-error
                                        @elseif($record->priority === 'high') badge-warning
                                        @elseif($record->priority === 'medium') badge-info
                                        @else badge-ghost
                                        @endif">
                                        {{ ucfirst($record->priority) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <button wire:click="completeScheduledMaintenance({{ $record->id }})" 
                                                wire:confirm="Mark this maintenance as completed?"
                                                class="btn btn-xs btn-success" title="Complete">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </button>
                                        <button wire:click="cancelScheduledMaintenance({{ $record->id }})" 
                                                wire:confirm="Cancel this scheduled maintenance?"
                                                class="btn btn-xs btn-ghost" title="Cancel">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-[var(--sand)]">
                                    <div class="py-8">
                                        <svg class="w-12 h-12 mx-auto text-[var(--sand)] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <p>No planned maintenance scheduled</p>
                                        <button wire:click="openBookingModal" class="btn btn-sm btn-primary mt-2">Schedule Now</button>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Actual Maintenance (In Progress) -->
        <div class="card bg-base-200">
            <div class="card-body">
                <h2 class="card-title text-orange-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Actual Maintenance ({{ $inProgressMaintenance->count() }})
                </h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>Type</th>
                                <th>Started</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($inProgressMaintenance as $record)
                            <tr>
                                <td><strong>{{ $record->machine->name }}</strong></td>
                                <td>
                                    <span class="badge badge-sm badge-outline">{{ ucfirst($record->maintenance_type) }}</span>
                                    <br>
                                    <span class="text-xs">{{ $record->title }}</span>
                                </td>
                                <td>
                                    @if($record->started_at)
                                        {{ $record->started_at->format('M d, Y H:i') }}
                                        <br><span class="text-xs text-[var(--sand)]">{{ $record->started_at->diffForHumans() }}</span>
                                    @else
                                        <span class="text-[var(--sand)]">Not started</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-sm 
                                        @if($record->priority === 'critical') badge-error
                                        @elseif($record->priority === 'high') badge-warning
                                        @elseif($record->priority === 'medium') badge-info
                                        @else badge-ghost
                                        @endif">
                                        {{ ucfirst($record->priority) }}
                                    </span>
                                </td>
                                <td>
                                    <button wire:click="completeScheduledMaintenance({{ $record->id }})" 
                                            wire:confirm="Mark this maintenance as completed?"
                                            class="btn btn-xs btn-success" title="Complete">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-[var(--sand)]">
                                    <div class="py-8">
                                        <svg class="w-12 h-12 mx-auto text-[var(--sand)] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <p>No maintenance currently in progress</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Machines Needing Attention & Recent Maintenance -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Machines Needing Attention -->
        <div class="card bg-base-200">
            <div class="card-body">
                <h2 class="card-title">Machines Needing Attention</h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>Health Status</th>
                                <th>Score</th>
                                <th>Faults</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($machinesNeedingAttention as $health)
                            <tr>
                                <td><strong>{{ $health->machine->name }}</strong></td>
                                <td>
                                    <span class="badge 
                                        @if($health->health_status === 'critical') badge-error
                                        @elseif($health->health_status === 'poor') badge-warning
                                        @else badge-info
                                        @endif
                                    ">{{ $health->health_status }}</span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <progress class="progress w-16
                                            @if($health->overall_health_score < 40) progress-error
                                            @elseif($health->overall_health_score < 70) progress-warning
                                            @else progress-success
                                            @endif
                                        " value="{{ $health->overall_health_score }}" max="100"></progress>
                                        <span class="text-sm">{{ $health->overall_health_score }}%</span>
                                    </div>
                                </td>
                                <td>
                                    @if($health->fault_code_count > 0)
                                    <span class="badge badge-error">{{ $health->fault_code_count }} codes</span>
                                    @else
                                    -
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center">All machines in good health</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Maintenance -->
        <div class="card bg-base-200">
            <div class="card-body">
                <h2 class="card-title">Recent Maintenance</h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>WO#</th>
                                <th>Machine</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentMaintenance as $record)
                            <tr>
                                <td><strong>{{ $record->work_order_number }}</strong></td>
                                <td>{{ $record->machine->name }}</td>
                                <td>
                                    <span class="badge badge-sm">{{ $record->maintenance_type }}</span>
                                </td>
                                <td>
                                    <span class="badge 
                                        @if($record->status === 'completed') badge-success
                                        @elseif($record->status === 'in_progress') badge-warning
                                        @else badge-info
                                        @endif
                                    ">{{ $record->status }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center">No recent maintenance</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Health Status Details -->
    <div class="card bg-base-200">
        <div class="card-body">
            <h2 class="card-title">All Machines Health Status</h2>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Machine</th>
                            <th>Status</th>
                            <th>Overall Score</th>
                            <th>Engine</th>
                            <th>Transmission</th>
                            <th>Hydraulics</th>
                            <th>Electrical</th>
                            <th>Last Scan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($healthStatuses->take(20) as $health)
                        <tr>
                            <td><strong>{{ $health->machine->name }}</strong></td>
                            <td>
                                <span class="badge 
                                    @if($health->health_status === 'excellent') badge-success
                                    @elseif($health->health_status === 'good') badge-info
                                    @elseif($health->health_status === 'fair') badge-warning
                                    @else badge-error
                                    @endif
                                ">{{ $health->health_status }}</span>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <progress class="progress w-20
                                        @if($health->overall_health_score >= 75) progress-success
                                        @elseif($health->overall_health_score >= 60) progress-warning
                                        @else progress-error
                                        @endif
                                    " value="{{ $health->overall_health_score }}" max="100"></progress>
                                    <span class="text-sm">{{ $health->overall_health_score }}%</span>
                                </div>
                            </td>
                            <td><span class="text-sm">{{ $health->engine_health ?? 'N/A' }}%</span></td>
                            <td><span class="text-sm">{{ $health->transmission_health ?? 'N/A' }}%</span></td>
                            <td><span class="text-sm">{{ $health->hydraulics_health ?? 'N/A' }}%</span></td>
                            <td><span class="text-sm">{{ $health->electrical_health ?? 'N/A' }}%</span></td>
                            <td>
                                <span class="text-xs">{{ $health->last_diagnostic_scan?->format('M d, Y') ?? 'Never' }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">No health data available</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Book Maintenance Modal -->
    @if($showBookingModal)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click="closeBookingModal">
        <div class="bg-[var(--ink-soft)] rounded-lg p-6 max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto border border-[var(--line)] text-[var(--stone)] shadow-lg" x-on:click.stop>
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-display font-semibold text-[var(--stone)]">Book Maintenance</h2>
                <button wire:click="closeBookingModal" class="text-[var(--sand)] hover:text-[var(--stone)]">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="bookMaintenance" class="space-y-4">
                <!-- Machine Selection -->
                <div>
                    <label class="block text-sm font-medium text-[var(--sand)] mb-2">Machine *</label>
                    <select wire:model="machine_id" class="w-full px-4 py-2 border border-[var(--line)] rounded-lg focus:ring-2 focus:ring-blue-500 text-gray-100 bg-[var(--ink-soft)]" required>
                        <option value="">Select a machine...</option>
                        @foreach($machines as $machine)
                            <option value="{{ $machine->id }}">{{ $machine->name }} - {{ $machine->model }}</option>
                        @endforeach
                    </select>
                    @error('machine_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Maintenance Type -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Maintenance Type *</label>
                            <select wire:model="maintenance_type" class="w-full px-4 py-2 border border-[var(--line)] rounded-lg focus:ring-2 focus:ring-blue-500 text-gray-100 bg-[var(--ink-soft)]" required>
                            <option value="preventive">Preventive</option>
                            <option value="corrective">Corrective</option>
                            <option value="predictive">Predictive</option>
                            <option value="emergency">Emergency</option>
                            <option value="routine">Routine</option>
                            <option value="inspection">Inspection</option>
                            <option value="calibration">Calibration</option>
                            <option value="overhaul">Overhaul</option>
                            <option value="breakdown">Breakdown</option>
                            <option value="seasonal">Seasonal</option>
                        </select>
                        @error('maintenance_type') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Priority -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Priority *</label>
                        <select wire:model="priority" class="w-full px-4 py-2 border border-[var(--line)] rounded-lg focus:ring-2 focus:ring-blue-500 text-gray-100 bg-[var(--ink-soft)]" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                        @error('priority') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-[var(--sand)] mb-2">Title *</label>
                    <input 
                        type="text" 
                        wire:model="title" 
                        class="w-full px-4 py-2 border border-[var(--line)] rounded-lg focus:ring-2 focus:ring-blue-500 bg-[var(--ink-soft)] text-gray-100" 
                        placeholder="e.g., Oil Change, Brake Inspection" 
                        required
                    >
                    @error('title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-[var(--sand)] mb-2">Description</label>
                    <textarea 
                        wire:model="description" 
                        class="w-full px-4 py-2 border border-[var(--line)] rounded-lg focus:ring-2 focus:ring-blue-500 bg-[var(--ink-soft)] text-gray-100" 
                        rows="3" 
                        placeholder="Additional details about the maintenance work..."
                    ></textarea>
                    @error('description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Scheduled Date -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Scheduled Date *</label>
                        <input 
                            type="datetime-local" 
                            wire:model="scheduled_date" 
                            class="w-full px-4 py-2 border border-[var(--line)] rounded-lg focus:ring-2 focus:ring-blue-500 bg-[var(--ink-soft)] text-gray-100" 
                            required
                        >
                        @error('scheduled_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Estimated Duration -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Estimated Duration (hours)</label>
                        <input 
                            type="number" 
                            wire:model="estimated_duration_hours" 
                            step="0.5" 
                            min="0" 
                            class="w-full px-4 py-2 border border-[var(--line)] rounded-lg focus:ring-2 focus:ring-blue-500 bg-[var(--ink-soft)] text-gray-100" 
                            placeholder="0"
                        >
                        @error('estimated_duration_hours') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Estimated Cost -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Estimated Cost (ZAR)</label>
                        <input 
                            type="number" 
                            wire:model="estimated_cost" 
                            step="0.01" 
                            min="0" 
                            class="w-full px-4 py-2 border border-[var(--line)] rounded-lg focus:ring-2 focus:ring-blue-500 bg-[var(--ink-soft)] text-gray-100" 
                            placeholder="0.00"
                        >
                        @error('estimated_cost') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Required Parts -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Required Parts</label>
                        <input 
                            type="text" 
                            wire:model="required_parts" 
                            class="w-full px-4 py-2 border border-[var(--line)] rounded-lg focus:ring-2 focus:ring-blue-500 bg-[var(--ink-soft)] text-gray-100" 
                            placeholder="e.g., Oil filter, Brake pads"
                        >
                        @error('required_parts') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Technician Notes -->
                <div>
                    <label class="block text-sm font-medium text-[var(--sand)] mb-2">Technician Notes</label>
                    <textarea 
                        wire:model="technician_notes" 
                        class="w-full px-4 py-2 border border-[var(--line)] rounded-lg focus:ring-2 focus:ring-blue-500 bg-[var(--ink-soft)] text-gray-100" 
                        rows="2" 
                        placeholder="Special instructions or notes for the technician..."
                    ></textarea>
                    @error('technician_notes') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <!-- Form Actions -->
                <div class="flex gap-3 pt-4">
                    <button 
                        type="submit" 
                        class="flex-1 px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-colors font-medium flex items-center justify-center gap-2"
                        wire:loading.attr="disabled"
                        wire:target="bookMaintenance"
                    >
                        <span wire:loading.remove wire:target="bookMaintenance" class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Book Maintenance
                        </span>
                        <span wire:loading wire:target="bookMaintenance" class="flex items-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Booking...
                        </span>
                    </button>
                    <button 
                        type="button" 
                        wire:click="closeBookingModal"
                        class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded-lg transition-colors font-medium"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
</div>
