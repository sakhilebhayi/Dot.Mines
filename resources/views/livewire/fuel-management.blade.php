<div>
<div class="p-6 bg-[var(--ink)] min-h-screen text-[var(--stone)]">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-display font-semibold">Fuel Management</h1>
            <p class="text-[var(--sand)] mt-2">Monitor fuel levels, track consumption, and manage fuel operations</p>
        </div>
        <div class="flex items-center gap-3">
            <button 
                wire:click="openManageModal" 
                class="px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-colors flex items-center gap-2 font-display font-semibold"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Manage Fuel
            </button>
        </div>
    </div>

    <!-- Current Month Allocation Card -->
    @if($currentAllocation)
    <div class="card bg-gradient-to-br from-[var(--umber)] to-[var(--ink-soft)] text-[var(--stone)] mb-6 border border-[var(--line)]">
        <div class="card-body">
            <div class="flex items-center justify-between mb-2">
                <h2 class="card-title text-2xl">{{ $currentAllocation->period_name }} Fuel Allocation</h2>
                @if($currentAllocation->mine_area_id && $currentAllocation->mineArea)
                    <span class="badge badge-lg bg-[var(--gold)] text-[var(--ink)] border-[var(--gold)]">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        {{ $currentAllocation->mineArea->name }}
                    </span>
                @else
                    <span class="badge badge-lg bg-white/10 text-[var(--stone)] border-[var(--line)]">
                        General Team Allocation
                    </span>
                @endif
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                <div class="stat bg-white/10 rounded-lg p-4">
                    <div class="stat-title text-[var(--sand)]">Allocated</div>
                    <div class="stat-value text-2xl">{{ number_format($currentAllocation->allocated_liters) }}L</div>
                    <div class="stat-desc text-[var(--sand)]">R{{ number_format($currentAllocation->total_budget_zar, 2) }}</div>
                </div>
                <div class="stat bg-white/10 rounded-lg p-4">
                    <div class="stat-title text-[var(--sand)]">Consumed</div>
                    <div class="stat-value text-2xl {{ $currentAllocation->isExceeded() ? 'text-red-400' : '' }}">
                        {{ number_format($currentAllocation->consumed_liters) }}L
                    </div>
                    <div class="stat-desc text-[var(--sand)]">R{{ number_format($currentAllocation->spent_zar, 2) }}</div>
                </div>
                <div class="stat bg-white/10 rounded-lg p-4">
                    <div class="stat-title text-[var(--sand)]">Remaining</div>
                    <div class="stat-value text-2xl {{ $currentAllocation->isNearingLimit() ? 'text-yellow-400' : 'text-green-400' }}">
                        {{ number_format($currentAllocation->remaining_liters) }}L
                    </div>
                    <div class="stat-desc text-[var(--sand)]">R{{ number_format($currentAllocation->remaining_budget_zar, 2) }}</div>
                </div>
                <div class="stat bg-white/10 rounded-lg p-4">
                    <div class="stat-title text-[var(--sand)]">Usage</div>
                    <div class="stat-value text-2xl">{{ number_format($currentAllocation->consumption_percentage, 1) }}%</div>
                    <progress class="progress progress-success w-full mt-2" value="{{ $currentAllocation->consumption_percentage }}" max="100"></progress>
                </div>
            </div>
            @if($currentAllocation->isExceeded())
                <div class="alert alert-error mt-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span><strong>Budget Exceeded!</strong> You have consumed {{ number_format($currentAllocation->consumed_liters - $currentAllocation->allocated_liters) }}L more than allocated.</span>
                </div>
            @elseif($currentAllocation->isNearingLimit())
                <div class="alert alert-warning mt-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <span>You've used {{ number_format($currentAllocation->consumption_percentage, 1) }}% of your monthly allocation. Only {{ number_format($currentAllocation->remaining_liters) }}L remaining.</span>
                </div>
            @endif
        </div>
    </div>
    @else
    <div class="alert alert-info mb-6">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span>No monthly fuel allocation set for {{ now()->format('F Y') }}. Click "Set Monthly Allocation" to configure.</span>
    </div>
    @endif

    <!-- Refuel Modal -->
    @if($showRefuelModal)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click="closeRefuelModal">
        <div class="bg-[var(--ink-soft)] rounded-lg p-6 max-w-lg w-full mx-4 border border-[var(--line)] shadow-lg" x-on:click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold">Refuel Tank</h3>
                <button class="text-[var(--sand)] hover:text-[var(--stone)]" wire:click="closeRefuelModal">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form wire:submit.prevent="refuelTank" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Tank</label>
                    <div class="text-[var(--stone)]">{{ $tanks->firstWhere('id', $refuelTankId)?->name ?? 'Selected Tank' }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Quantity (Liters)</label>
                    <input type="number" step="0.01" min="0.01" wire:model.live="refuelQuantity" class="input input-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]" />
                    @error('refuelQuantity') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Unit Price (ZAR)</label>
                    <input type="number" step="0.01" min="0" wire:model.live="refuelUnitPrice" class="input input-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]" />
                    @error('refuelUnitPrice') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Notes</label>
                    <input type="text" wire:model.live="refuelNotes" class="input input-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]" />
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeRefuelModal" class="btn btn-ghost">Cancel</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="refuelTank">Refuel</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($showDeleteConfirm)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click="closeDeleteConfirm">
        <div class="bg-[var(--ink-soft)] rounded-lg p-6 max-w-md w-full mx-4 border border-[var(--line)] shadow-lg" x-on:click.stop>
            <h3 class="text-lg font-bold mb-2">Confirm Delete</h3>
            <p class="mb-4 text-[var(--sand)]">Are you sure you want to delete the tank "{{ $tanks->firstWhere('id', $confirmDeleteTankId)?->name ?? 'Selected Tank' }}"? This action cannot be undone.</p>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="closeDeleteConfirm" class="btn btn-ghost">Cancel</button>
                <button type="button" wire:click="performDeleteConfirmed" class="btn btn-error" wire:loading.attr="disabled">Delete</button>
            </div>
        </div>
    </div>
    @endif

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
            <input type="checkbox" wire:model.live="showLowFuelOnly" class="checkbox checkbox-primary" />
            <span>Show Low Fuel Only</span>
        </label>

        <!-- Loading Indicator for Filter Changes -->
        <div wire:loading wire:target="selectedPeriod,showLowFuelOnly" class="flex items-center gap-2 text-sm text-[var(--gold)]">
            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Loading...</span>
        </div>
    </div>

    <!-- Tank Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Total Tanks</div>
            <div class="stat-value text-primary">{{ $tankStats['total'] }}</div>
            <div class="stat-desc">{{ $tankStats['active'] }} active</div>
        </div>
        
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Low Fuel Tanks</div>
            <div class="stat-value text-warning">{{ $tankStats['low_fuel'] }}</div>
            <div class="stat-desc">{{ $tankStats['critical'] }} critical</div>
        </div>
        
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Total Capacity</div>
            <div class="stat-value">{{ number_format($tankStats['total_capacity']) }}L</div>
            <div class="stat-desc">{{ number_format($tankStats['current_level']) }}L current</div>
        </div>
        
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Fill Percentage</div>
            <div class="stat-value text-success">
                {{ $tankStats['total_capacity'] > 0 ? number_format(($tankStats['current_level'] / $tankStats['total_capacity']) * 100, 1) : 0 }}%
            </div>
        </div>
    </div>

    <!-- Transaction Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Total Refueled</div>
            <div class="stat-value text-sm">{{ number_format($transactionStats['total_refueled']) }}L</div>
        </div>
        
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Total Consumed</div>
            <div class="stat-value text-sm">{{ number_format($transactionStats['total_consumed']) }}L</div>
        </div>
        
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Total Cost</div>
            <div class="stat-value text-sm text-green-600">R{{ number_format($transactionStats['total_cost'], 2) }}</div>
            <div class="stat-desc">South African Rands</div>
        </div>
        
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-title">Transactions</div>
            <div class="stat-value text-sm">{{ number_format($transactionStats['transaction_count']) }}</div>
        </div>
    </div>

    <!-- Active Alerts -->
    @if($activeAlerts->count() > 0)
    <div class="card bg-warning text-warning-content mb-6">
        <div class="card-body">
            <h2 class="card-title">Active Fuel Alerts ({{ $activeAlerts->count() }})</h2>
            <div class="space-y-2">
                @foreach($activeAlerts as $alert)
                <div class="alert alert-warning">
                    <div>
                        <strong>{{ $alert->title }}</strong>
                        <p class="text-sm">{{ $alert->message }}</p>
                        <p class="text-xs opacity-70">{{ $alert->triggered_at->diffForHumans() }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- AI-Powered Fuel Optimization Insights -->
    @if($aiRecommendations->count() > 0 || $aiInsights->count() > 0)
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-6 h-6 text-[var(--gold)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
            </svg>
            <h2 class="text-2xl font-display font-semibold">AI Fuel Optimization</h2>
            <span class="badge badge-primary">Powered by AI</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- AI Recommendations -->
            @if($aiRecommendations->count() > 0)
            <div class="card bg-gradient-to-br from-[var(--umber)] to-[var(--ink)] text-[var(--stone)] border border-[var(--line)]">
                <div class="card-body">
                    <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Optimization Recommendations
                    </h3>
                    <div class="space-y-3">
                        @foreach($aiRecommendations as $recommendation)
                        <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 border border-white/20">
                            <div class="flex items-start justify-between mb-2">
                                <span class="font-semibold">{{ $recommendation['title'] }}</span>
                                <span class="badge 
                                    @if($recommendation['priority'] === 'critical') badge-error
                                    @elseif($recommendation['priority'] === 'high') badge-warning
                                    @else badge-info
                                    @endif
                                ">{{ ucfirst($recommendation['priority']) }}</span>
                            </div>
                            <p class="text-sm text-[var(--sand)] mb-2">{{ $recommendation['description'] }}</p>
                            @if(isset($recommendation['estimated_savings']) && $recommendation['estimated_savings'] > 0)
                            <div class="flex items-center gap-2 text-green-300 text-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>Potential Savings: R{{ number_format($recommendation['estimated_savings'], 2) }}/month</span>
                            </div>
                            @endif
                            <div class="flex items-center gap-2 mt-2 text-xs text-[var(--sand)]">
                                <span>Confidence: {{ number_format($recommendation['confidence_score'] * 100, 0) }}%</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- AI Insights -->
            @if($aiInsights->count() > 0)
            <div class="card bg-gradient-to-br from-[var(--umber)] to-[var(--ink)] text-[var(--stone)] border border-[var(--line)]">
                <div class="card-body">
                    <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Consumption Insights
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
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Fuel Tanks & Top Consumers -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Fuel Tanks -->
        <div class="card bg-base-200">
            <div class="card-body">
                <h2 class="card-title">Fuel Tanks</h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tank</th>
                                <th>Location</th>
                                <th>Level</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tanks as $tank)
                            <tr>
                                <td>
                                    <strong>{{ $tank->name }}</strong><br>
                                    <span class="text-xs">{{ $tank->fuel_type }}</span>
                                </td>
                                <td>{{ $tank->mineArea?->name ?? 'N/A' }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <progress class="progress w-20 
                                            @if($tank->isCritical()) progress-error
                                            @elseif($tank->isBelowMinimum()) progress-warning
                                            @else progress-success
                                            @endif
                                        " value="{{ $tank->fill_percentage }}" max="100"></progress>
                                        <span class="text-sm">{{ number_format($tank->fill_percentage, 1) }}%</span>
                                    </div>
                                    <span class="text-xs">{{ number_format($tank->current_level_liters) }}L / {{ number_format($tank->capacity_liters) }}L</span>
                                </td>
                                <td>
                                    <span class="badge 
                                        @if($tank->status === 'active') badge-success
                                        @elseif($tank->status === 'maintenance') badge-warning
                                        @else badge-error
                                        @endif
                                    ">{{ $tank->status }}</span>
                                </td>
                                <td class="flex gap-2">
                                    <button wire:click.prevent="openRefuelModal({{ $tank->id }})" class="btn btn-sm btn-outline">Refuel</button>
                                    <button wire:click.prevent="confirmDeleteTank({{ $tank->id }})" class="btn btn-sm btn-error">Delete</button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center">No fuel tanks found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Consumers -->
        <div class="card bg-base-200">
            <div class="card-body">
                <h2 class="card-title">Top Fuel Consumers</h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>Type</th>
                                <th>Consumed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topConsumers as $consumer)
                            <tr>
                                <td>
                                    <strong>{{ $consumer['machine']->name }}</strong>
                                </td>
                                <td>{{ $consumer['machine']->machine_type }}</td>
                                <td>
                                    <strong>{{ number_format($consumer['total_consumed']) }}L</strong>
                                    @if($consumer['total_cost'] > 0)
                                        <br><span class="text-xs text-success">R{{ number_format($consumer['total_cost'], 2) }}</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center">No consumption data</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card bg-base-200">
        <div class="card-body">
            <h2 class="card-title">Recent Transactions</h2>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Tank</th>
                            <th>Machine</th>
                            <th>Quantity</th>
                            <th>Cost</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTransactions as $transaction)
                        <tr>
                            <td>{{ $transaction->transaction_date->format('M d, Y H:i') }}</td>
                            <td>
                                <span class="badge 
                                    @if($transaction->transaction_type === 'dispensing') badge-primary
                                    @elseif(in_array($transaction->transaction_type, ['refill', 'delivery'])) badge-success
                                    @else badge-warning
                                    @endif
                                ">{{ $transaction->transaction_type }}</span>
                            </td>
                            <td>{{ $transaction->fuelTank?->name ?? 'N/A' }}</td>
                            <td>{{ $transaction->machine?->name ?? '-' }}</td>
                            <td>{{ number_format($transaction->quantity_liters) }}L</td>
                            <td class="text-success font-semibold">R{{ number_format($transaction->total_cost, 2) }}</td>
                            <td>{{ $transaction->user?->name ?? 'System' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">No recent transactions</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- Unified Manage Modal -->
    @if($showManageModal)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click="closeManageModal">
        <div class="bg-[var(--ink-soft)] rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto border border-[var(--line)] shadow-lg" x-on:click.stop>
            @if(session('success'))
                <div class="alert alert-success mb-4">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-error mb-4">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-display font-semibold text-[var(--stone)]">Manage Fuel</h2>
                <button wire:click="closeManageModal" class="text-[var(--sand)] hover:text-[var(--stone)]">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <!-- Tabbed interface for actions -->
            <div class="mb-4 flex gap-2">
                <button type="button" wire:click="setManageTab('dispense')"
                    class="px-3 py-2 rounded-lg font-medium transition-colors {{ $manageTab === 'dispense' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 text-[var(--sand)] hover:bg-white/10' }}">
                    Dispense Fuel
                </button>
                <button type="button" wire:click="setManageTab('allocation')"
                    class="px-3 py-2 rounded-lg font-medium transition-colors {{ $manageTab === 'allocation' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 text-[var(--sand)] hover:bg-white/10' }}">
                    Set Monthly Allocation
                </button>
                <button type="button" wire:click="setManageTab('tank')"
                    class="px-3 py-2 rounded-lg font-medium transition-colors {{ $manageTab === 'tank' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 text-[var(--sand)] hover:bg-white/10' }}">
                    Add Fuel Tank
                </button>
            </div>
            <div>
                @if($manageTab === 'dispense')
                    <!-- Dispense Fuel Form -->
                    <form wire:submit.prevent="recordDispensingTransaction" class="space-y-4">
                        <div>
                            <label class="block font-medium mb-1">Tank</label>
                            <select wire:model.live="transactionTankId" class="select select-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]">
                                <option value="">Select Tank</option>
                                @foreach($tanks as $tank)
                                    <option value="{{ $tank->id }}">{{ $tank->name }} ({{ $tank->fuel_type }}) @if($tank->status !== 'active') — {{ ucfirst($tank->status) }} @endif</option>
                                @endforeach
                            </select>
                            @error('transactionTankId') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror

                            @if($tanks->isEmpty())
                                <div class="mt-2 text-sm text-yellow-300">
                                    No active tanks found for this team. Click "Manage Fuel → Add Fuel Tank" to create one.
                                </div>
                            @elseif(isset($canSeeInactiveTanks) && $canSeeInactiveTanks)
                                <div class="mt-2 text-sm text-[var(--sand)]">
                                    Showing inactive tanks as well because your account has admin privileges for this team. Inactive tanks are marked in the dropdown.
                                </div>
                            @endif
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Machine</label>
                            <select wire:model.live="transactionMineAreaId" class="select select-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]">
                                <option value="">Select Machine</option>
                                @foreach($machines as $machine)
                                    <option value="{{ $machine->id }}">{{ $machine->name }} ({{ $machine->machine_type }})</option>
                                @endforeach
                            </select>
                            @error('transactionMineAreaId') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Quantity (Liters)</label>
                            <input type="number" min="1" wire:model.live="transactionQuantity" class="input input-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]" />
                            @error('transactionQuantity') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        @if($transactionError)
                            <div class="text-sm text-red-400">{{ $transactionError }}</div>
                        @endif
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="closeManageModal" class="btn btn-ghost">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="recordDispensingTransaction">Dispense</button>
                        </div>
                    </form>
                @elseif($manageTab === 'allocation')
                    <!-- Set Monthly Allocation Form -->
                    <form wire:submit.prevent="saveAllocation" class="space-y-4">
                        <div>
                            <label class="block font-medium mb-1">Year</label>
                            <input type="number" min="2020" max="2100" wire:model.live="allocationYear" class="input input-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]" />
                            @error('allocationYear') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Month</label>
                            <input type="number" min="1" max="12" wire:model.live="allocationMonth" class="input input-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]" />
                            @error('allocationMonth') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Mine Area</label>
                            <select wire:model.live="mineAreaId" class="select select-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]">
                                <option value="">Select Area</option>
                                @foreach($mineAreas as $area)
                                    <option value="{{ $area->id }}">{{ $area->name }}</option>
                                @endforeach
                            </select>
                            @error('mineAreaId') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Allocated Liters</label>
                            <input type="number" min="1" wire:model.live="allocatedLiters" class="input input-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]" />
                            @error('allocatedLiters') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Fuel Price Per Liter (ZAR)</label>
                            <input type="number" min="0.01" step="0.01" wire:model.live="fuelPricePerLiter" class="input input-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]" />
                            @error('fuelPricePerLiter') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Notes</label>
                            <input type="text" wire:model.live="allocationNotes" class="input input-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]" />
                            @error('allocationNotes') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="closeManageModal" class="btn btn-ghost">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveAllocation">Save Allocation</button>
                        </div>
                    </form>
                @elseif($manageTab === 'tank')
                    <!-- Add/Edit Fuel Tank Form -->
                    <form wire:submit.prevent="saveTank" class="space-y-4">
                        <!-- Removed existing-tank select to only allow creating a new tank -->
                        <div>
                            <label class="block font-medium mb-1">Tank Name</label>
                            <input type="text" wire:model.live="tankName" class="input input-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]" />
                            @error('tankName') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Fuel Type</label>
                            <input type="text" wire:model.live="tankFuelType" class="input input-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]" />
                            @error('tankFuelType') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Capacity (Liters)</label>
                            <input type="number" min="1" wire:model.live="tankCapacity" class="input input-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]" />
                            @error('tankCapacity') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Mine Area</label>
                            <select wire:model.live="tankMineAreaId" class="select select-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]">
                                <option value="">Select Area</option>
                                @foreach($mineAreas as $area)
                                    <option value="{{ $area->id }}">{{ $area->name }}</option>
                                @endforeach
                            </select>
                            @error('tankMineAreaId') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Minimum Level (Liters)</label>
                            <input type="number" min="0" wire:model.live="tankMinimumLevel" class="input input-bordered w-full bg-[var(--ink)] border-[var(--line)] text-[var(--stone)]" />
                            @error('tankMinimumLevel') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="closeManageModal" class="btn btn-ghost">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveTank">Add Tank</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
    @endif