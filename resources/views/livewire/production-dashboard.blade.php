<div class="h-screen flex flex-col bg-[var(--ink)]">
    <!-- Header -->
    <div class="bg-[var(--ink-soft)] border-b border-[var(--line)] p-6">
        <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-3xl font-display font-semibold text-[var(--stone)] flex items-center gap-2">
                    Production Dashboard
                </h1>
                <p class="mt-1 text-sm text-[var(--sand)]">
                    Track loads, cycles, tonnages, and BCM's across your fleet
                </p>
                <div class="mt-1">
                    <x-freshness :timestamp="$telemetryFreshestAt" :stale-after="$telemetryStaleAfter" label="Telemetry" />
                </div>
            </div>

            <!-- Date Filters -->
            <div class="flex flex-wrap gap-2 items-end">
                <div class="flex gap-2 items-center">
                    <button wire:click="setPeriod('day')"
                        class="px-4 py-2 rounded-lg transition-all {{ $dateFilter === 'day' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-[var(--ink-soft)] text-[var(--sand)] hover:bg-white/10' }}">
                        Today
                    </button>
                    <button wire:click="setPeriod('week')"
                        class="px-4 py-2 rounded-lg transition-all {{ $dateFilter === 'week' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-[var(--ink-soft)] text-[var(--sand)] hover:bg-white/10' }}">
                        Week
                    </button>
                    <button wire:click="setPeriod('month')"
                        class="px-4 py-2 rounded-lg transition-all {{ $dateFilter === 'month' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-[var(--ink-soft)] text-[var(--sand)] hover:bg-white/10' }}">
                        Month
                    </button>
                    <button wire:click="setPeriod('year')"
                        class="px-4 py-2 rounded-lg transition-all {{ $dateFilter === 'year' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-[var(--ink-soft)] text-[var(--sand)] hover:bg-white/10' }}">
                        Year
                    </button>
                    @if ($dateFilter === 'custom')
                        {{-- Manual picker edits put the page on a user-chosen
                             range; make that explicit instead of leaving a
                             stale quick-toggle highlighted. --}}
                        <span class="px-4 py-2 rounded-lg bg-[var(--gold)] text-[var(--ink)] font-medium">Custom</span>
                    @endif
                </div>

                <!-- Custom Date Range Picker. The value attribute is rendered
                     server-side so quick-toggle updates (setPeriod) actually
                     reach the DOM: without it, Livewire's morph sees identical
                     input HTML and leaves the stale picker value in place. -->
                <div class="flex gap-2 items-center bg-[var(--ink-soft)] rounded-lg px-3 py-2 border border-[var(--line)]">
                    <input type="date" wire:model.live="startDate" value="{{ $startDate }}"
                        class="bg-transparent border-0 text-sm focus:ring-0 text-[var(--stone)] px-4 py-2 h-8"
                        aria-label="Start date">
                    <span class="text-[var(--sand)]">→</span>
                    <input type="date" wire:model.live="endDate" value="{{ $endDate }}"
                        class="bg-transparent border-0 text-sm focus:ring-0 text-[var(--stone)] px-4 py-2 h-8"
                        aria-label="End date">
                </div>
            </div>
        </div>

        <!-- Live today: counter arithmetic from the operational snapshot
             (Bell cumulative counters), independent of the date filters
             below. Null means no counter baseline yet -- say so, never 0. -->
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg px-6 py-4 mb-6 flex flex-wrap items-center gap-x-8 gap-y-2">
            <span class="text-sm font-medium text-[var(--gold)] uppercase tracking-wide">Live Today</span>
            @if ($fleetToday['loads'] !== null)
                <span class="text-sm text-[var(--sand)]">Loads <span class="text-lg font-semibold text-[var(--stone)] ml-1">{{ number_format($fleetToday['loads']) }}</span></span>
                <span class="text-sm text-[var(--sand)]">Hauled <span class="text-lg font-semibold text-[var(--stone)] ml-1">{{ number_format($fleetToday['tonnes'], 1) }} t</span></span>
                <span class="text-xs text-[var(--sand)]/70">{{ $fleetToday['reporting'] }} of {{ $fleetToday['total'] }} machines reporting counters</span>
            @else
                <span class="text-sm text-[var(--sand)]">Awaiting API data — no machine has reported a counter baseline yet today.</span>
            @endif
        </div>

        <!-- Production Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-8">
            <!-- Total Loads -->
            <div class="bg-[var(--ink-soft)] rounded-lg shadow p-4 border border-[var(--line)]">
                <div class="flex items-center justify-between mb-2">
                    <div class="p-2 bg-blue-500/15 rounded-lg">
                        <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-xl font-bold text-[var(--stone)] mb-0">{{ number_format($summary['total_loads']) }}</h3>
                <p class="text-xs text-[var(--sand)]">Total Loads</p>
            </div>

            <!-- Total Cycles -->
            <div class="bg-[var(--ink-soft)] rounded-lg shadow p-4 border border-[var(--line)]">
                <div class="flex items-center justify-between mb-2">
                    <div class="p-2 bg-purple-500/15 rounded-lg">
                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-xl font-bold text-[var(--stone)] mb-0">{{ number_format($summary['total_cycles']) }}</h3>
                <p class="text-xs text-[var(--sand)]">Total Cycles</p>
            </div>

            <!-- Total Tonnage -->
            <div class="bg-[var(--ink-soft)] rounded-lg shadow p-4 border border-[var(--line)]">
                <div class="flex items-center justify-between mb-2">
                    <div class="p-2 bg-green-500/15 rounded-lg">
                        <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-xl font-bold text-[var(--stone)] mb-0">{{ number_format($summary['total_tonnage'], 2) }}</h3>
                <p class="text-xs text-[var(--sand)]">Tonnage (T)</p>
            </div>

            <!-- Total BCM -->
            <div class="bg-[var(--ink-soft)] rounded-lg shadow p-4 border border-[var(--line)]">
                <div class="flex items-center justify-between mb-2">
                    <div class="p-2 bg-[var(--gold)]/15 rounded-lg">
                        <svg class="w-4 h-4 text-[var(--gold)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-xl font-bold text-[var(--stone)] mb-0">{{ number_format($summary['total_bcm'], 2) }}</h3>
                <p class="text-xs text-[var(--sand)]">BCM (m³)</p>
            </div>

            <!-- Active Areas -->
            <div class="bg-[var(--ink-soft)] rounded-lg shadow p-4 border border-[var(--line)]">
                <div class="flex items-center justify-between mb-2">
                    <div class="p-2 bg-indigo-500/15 rounded-lg">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-xl font-bold text-[var(--stone)] mb-0">{{ number_format($summary['active_areas']) }}</h3>
                <p class="text-xs text-[var(--sand)]">Active Areas</p>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Daily Production Trend -->
            <div class="lg:col-span-2 bg-[var(--ink-soft)] rounded-xl shadow-lg p-6 border border-[var(--line)]">
                <h2 class="text-xl font-display font-semibold text-[var(--stone)] mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-[var(--gold)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Daily Production Trend
                </h2>

                @if(count($dailyChart) > 0)
                    @php
                        $maxTonnage = collect($dailyChart)->max('tonnage') ?: 1;
                        $maxLoads = collect($dailyChart)->max('loads') ?: 1;
                        $columnCount = count($dailyChart);
                        // Thin axis labels to ~10 per chart: a label on every
                        // column set each column's min-content width (a rotated
                        // element keeps its unrotated layout box), so flex could
                        // not shrink the row and a month/year range overflowed
                        // the card. Thinned labels are absolutely positioned --
                        // out of layout flow, they can never widen a column.
                        // A colliding axis is as unreadable as a missing
                        // one: past ten columns thin to ~6 labels, and CSS
                        // hides every other one below the sm breakpoint --
                        // Blade cannot know the card's rendered width, but
                        // the breakpoint can.
                        $labelEvery = $columnCount > 10 ? max(1, (int) ceil($columnCount / 6)) : 1;
                        $gapClass = $columnCount > 45 ? 'gap-px' : 'gap-1';
                    @endphp

                    {{-- overflow-hidden here is containment for the decorative
                         label/tooltip spill only; column sizing itself is fixed
                         by min-w-0 + absolute labels above. --}}
                    <div class="space-y-6 overflow-hidden">
                        {{-- Percentage bar heights need a DEFINITE-height track:
                             the old markup put height:% on a child of an
                             auto-height flex column, which resolves to 0, so
                             the chart rendered labels but no bars at all. --}}
                        <!-- Tonnage Chart -->
                        <div>
                            <h3 class="text-sm font-medium text-[var(--sand)] mb-3">Tonnage (T)</h3>
                            <div class="flex items-end {{ $gapClass }}">
                                @foreach($dailyChart as $day)
                                    <div class="flex-1 min-w-0 flex flex-col items-center group">
                                        <div class="w-full h-28 flex items-end">
                                            <div class="w-full bg-green-500 hover:bg-green-600 rounded-t transition-all relative"
                                                 style="height: {{ $maxTonnage > 0 ? ($day['tonnage'] / $maxTonnage * 100) : 0 }}%; {{ $day['tonnage'] > 0 ? 'min-height: 3px;' : '' }}"
                                                 title="{{ $day['date'] }}: {{ number_format($day['tonnage'], 2) }}T">
                                                <span class="hidden group-hover:block absolute -top-8 left-1/2 transform -translate-x-1/2 bg-[var(--ink)] text-[var(--stone)] text-xs px-2 py-1 rounded whitespace-nowrap z-10">
                                                    {{ number_format($day['tonnage'], 2) }}T
                                                </span>
                                            </div>
                                        </div>
                                        @if($loop->index % $labelEvery === 0)
                                            <div class="relative h-6 w-full" data-chart-label>
                                                <span class="absolute {{ $loop->first ? 'left-0' : 'left-1/2 -translate-x-1/2' }} {{ (intdiv($loop->index, $labelEvery) % 2) === 1 ? 'hidden sm:block' : '' }} top-1 text-xs text-[var(--sand)] whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('M j') }}</span>
                                            </div>
                                        @else
                                            <div class="h-6 w-full"></div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Loads Chart -->
                        <div>
                            <h3 class="text-sm font-medium text-[var(--sand)] mb-3">Loads</h3>
                            <div class="flex items-end {{ $gapClass }}">
                                @foreach($dailyChart as $day)
                                    <div class="flex-1 min-w-0 flex flex-col items-center group">
                                        <div class="w-full h-28 flex items-end">
                                            <div class="w-full bg-blue-500 hover:bg-blue-600 rounded-t transition-all relative"
                                                 style="height: {{ $maxLoads > 0 ? ($day['loads'] / $maxLoads * 100) : 0 }}%; {{ $day['loads'] > 0 ? 'min-height: 3px;' : '' }}"
                                                 title="{{ $day['date'] }}: {{ number_format($day['loads']) }} loads">
                                                <span class="hidden group-hover:block absolute -top-8 left-1/2 transform -translate-x-1/2 bg-[var(--ink)] text-[var(--stone)] text-xs px-2 py-1 rounded whitespace-nowrap z-10">
                                                    {{ number_format($day['loads']) }} loads
                                                </span>
                                            </div>
                                        </div>
                                        @if($loop->index % $labelEvery === 0)
                                            <div class="relative h-6 w-full" data-chart-label>
                                                <span class="absolute {{ $loop->first ? 'left-0' : 'left-1/2 -translate-x-1/2' }} {{ (intdiv($loop->index, $labelEvery) % 2) === 1 ? 'hidden sm:block' : '' }} top-1 text-xs text-[var(--sand)] whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('M j') }}</span>
                                            </div>
                                        @else
                                            <div class="h-6 w-full"></div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center h-64 text-[var(--sand)]">
                        <svg class="w-16 h-16 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <p class="text-sm">No production data available for this period</p>
                    </div>
                @endif
            </div>

            <!-- Material Type Breakdown -->
            <div class="bg-[var(--ink-soft)] rounded-xl shadow-lg p-6 border border-[var(--line)]">
                <h2 class="text-xl font-display font-semibold text-[var(--stone)] mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-[var(--gold)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
                    </svg>
                    Material Breakdown
                </h2>

                @if(count($materialBreakdown) > 0)
                    <div class="space-y-4">
                        @php 
                            $maxMaterialTonnage = collect($materialBreakdown)->max('tonnage') ?: 1;
                            $colors = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-amber-500', 'bg-red-500', 'bg-indigo-500'];
                        @endphp
                        
                        @foreach($materialBreakdown as $index => $material)
                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm font-medium text-[var(--sand)]">{{ $material['material'] }}</span>
                                    <span class="text-sm text-[var(--sand)]">{{ number_format($material['tonnage'], 2) }}T</span>
                                </div>
                                <div class="w-full bg-white/10 rounded-full h-2">
                                    <div class="{{ $colors[$index % count($colors)] }} h-2 rounded-full transition-all" 
                                         style="width: {{ ($material['tonnage'] / $maxMaterialTonnage * 100) }}%"></div>
                                </div>
                                <div class="flex justify-between items-center mt-1">
                                    <span class="text-xs text-[var(--sand)]">{{ number_format($material['loads']) }} loads</span>
                                    <span class="text-xs text-[var(--sand)]">{{ $material['records'] }} records</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center h-64 text-[var(--sand)]">
                        <svg class="w-16 h-16 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                        </svg>
                        <p class="text-sm">No material data available</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Operator Fatigue Management Section -->
        <div class="mb-8">
            <div class="bg-[var(--ink-soft)] rounded-xl shadow-lg border border-[var(--line)] p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-display font-semibold text-[var(--stone)] flex items-center gap-2">
                        <svg class="w-6 h-6 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Operator Fatigue Management
                    </h2>
                    <div class="flex gap-2 text-xs">
                        <span class="px-2 py-1 rounded bg-green-900 text-green-200">● Normal</span>
                        <span class="px-2 py-1 rounded bg-yellow-900 text-yellow-200">● Caution</span>
                        <span class="px-2 py-1 rounded bg-orange-900 text-orange-200">● Alert</span>
                        <span class="px-2 py-1 rounded bg-red-900 text-red-200">● Critical</span>
                    </div>
                </div>

                @if(count($fatigueData) > 0)
                    <!-- Fatigue Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-gradient-to-br from-green-900/20 to-green-800/20 rounded-lg p-4 border border-green-700/40">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-2xl font-bold text-green-300">{{ $fatigueStats['well_rested'] }}</p>
                                    <p class="text-xs text-green-400">Well Rested</p>
                                </div>
                                <div class="p-2 bg-green-700/40 rounded-lg">
                                    <svg class="w-5 h-5 text-green-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-yellow-900/20 to-yellow-800/20 rounded-lg p-4 border border-yellow-700/40">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-2xl font-bold text-yellow-300">{{ $fatigueStats['needs_monitoring'] }}</p>
                                    <p class="text-xs text-yellow-400">Need Monitoring</p>
                                </div>
                                <div class="p-2 bg-yellow-700/40 rounded-lg">
                                    <svg class="w-5 h-5 text-yellow-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-orange-900/20 to-orange-800/20 rounded-lg p-4 border border-orange-700/40">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-2xl font-bold text-orange-300">{{ $fatigueStats['high_fatigue'] }}</p>
                                    <p class="text-xs text-orange-400">High Fatigue</p>
                                </div>
                                <div class="p-2 bg-orange-700/40 rounded-lg">
                                    <svg class="w-5 h-5 text-orange-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-red-900/20 to-red-800/20 rounded-lg p-4 border border-red-700/40">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-2xl font-bold text-red-300">{{ $fatigueStats['needs_rest'] }}</p>
                                    <p class="text-xs text-red-400">Needs Rest</p>
                                </div>
                                <div class="p-2 bg-red-700/40 rounded-lg">
                                    <svg class="w-5 h-5 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Operator Fatigue Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-[var(--sand)] uppercase tracking-wider">Operator</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-[var(--sand)] uppercase tracking-wider">Machine</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-[var(--sand)] uppercase tracking-wider">Shift</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-[var(--sand)] uppercase tracking-wider">Hours</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-[var(--sand)] uppercase tracking-wider">Consec. Days</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-[var(--sand)] uppercase tracking-wider">Fatigue Level</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-[var(--sand)] uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--line)]">
                                @foreach($fatigueData as $fatigue)
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-8 w-8 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-[var(--stone)] text-xs font-bold">
                                                    {{ substr($fatigue['operator_name'], 0, 2) }}
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm font-medium text-[var(--stone)]">{{ $fatigue['operator_name'] }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="text-xs font-mono bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-gray-700 dark:text-[var(--sand)]">
                                                {{ $fatigue['machine_name'] ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="text-xs px-2 py-1 rounded font-medium
                                                {{ $fatigue['shift_type'] === 'morning' ? 'bg-amber-900 text-amber-200' : '' }}
                                                {{ $fatigue['shift_type'] === 'afternoon' ? 'bg-orange-900 text-orange-200' : '' }}
                                                {{ $fatigue['shift_type'] === 'night' ? 'bg-indigo-900 text-indigo-200' : '' }}">
                                                {{ ucfirst($fatigue['shift_type']) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-[var(--stone)] font-medium">
                                            {{ number_format($fatigue['hours_worked'], 1) }}h
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-[var(--stone)] font-medium">
                                            {{ number_format($fatigue['consecutive_days'], 0) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="flex flex-col items-center gap-1">
                                                <div class="w-full bg-white/10 rounded-full h-2">
                                                    <div class="h-2 rounded-full transition-all
                                                        {{ $fatigue['fatigue_score'] < 20 ? 'bg-green-500' : '' }}
                                                        {{ $fatigue['fatigue_score'] >= 20 && $fatigue['fatigue_score'] < 40 ? 'bg-yellow-500' : '' }}
                                                        {{ $fatigue['fatigue_score'] >= 40 && $fatigue['fatigue_score'] < 60 ? 'bg-orange-500' : '' }}
                                                        {{ $fatigue['fatigue_score'] >= 60 ? 'bg-red-500' : '' }}" 
                                                        style="width: {{ $fatigue['fatigue_score'] }}%">
                                                    </div>
                                                </div>
                                                <span class="text-xs font-medium text-gray-600 dark:text-[var(--sand)]">{{ $fatigue['fatigue_score'] }}%</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            @if($fatigue['alert_level'] === 'none')
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-900 text-green-200">
                                                    Normal
                                                </span>
                                            @elseif($fatigue['alert_level'] === 'low' || $fatigue['alert_level'] === 'medium')
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-900 text-yellow-200">
                                                    Monitor
                                                </span>
                                            @elseif($fatigue['alert_level'] === 'high')
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-900 text-orange-200">
                                                    Caution
                                                </span>
                                            @else
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-900 text-red-200">
                                                    Rest Required
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-16 text-gray-500 dark:text-[var(--sand)]">
                        <svg class="w-16 h-16 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p class="text-sm">No fatigue data available for this period</p>
                        <p class="text-xs mt-2">Operator fatigue tracking will appear here once shifts are logged</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Area Performance Table -->
        <div class="bg-white dark:bg-[var(--ink-soft)] rounded-xl shadow-lg border border-gray-200 dark:border-[var(--line)]">
            <div class="p-6 border-b border-gray-200 dark:border-[var(--line)]">
                <h2 class="text-xl font-semibold text-[var(--stone)] flex items-center gap-2">
                    <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13"/>
                    </svg>
                    Mine Area Performance
                </h2>
            </div>
            
            @if(count($areaPerformance) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-white/5">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-[var(--sand)] uppercase tracking-wider">Area</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-[var(--sand)] uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-[var(--sand)] uppercase tracking-wider">Loads</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-[var(--sand)] uppercase tracking-wider">Cycles</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-[var(--sand)] uppercase tracking-wider">Tonnage</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-[var(--sand)] uppercase tracking-wider">BCM</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--line)]">
                            @foreach($areaPerformance as $area)
                                <tr class="hover:bg-white/5 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-[var(--stone)]">{{ $area['area_name'] }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-900 text-blue-200">
                                            {{ $area['area_type'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-[var(--stone)] font-medium">
                                        {{ number_format($area['loads']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-[var(--stone)] font-medium">
                                        {{ number_format($area['cycles']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-[var(--stone)] font-medium">
                                        {{ number_format($area['tonnage'], 2) }}T
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-[var(--stone)] font-medium">
                                        {{ number_format($area['bcm'], 2) }}m³
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-16 text-gray-500 dark:text-[var(--sand)]">
                    <svg class="w-16 h-16 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13"/>
                    </svg>
                    <p class="text-sm">No area performance data available for this period</p>
                    <p class="text-xs mt-2">Production records will appear here once data is recorded</p>
                </div>
            @endif
        </div>
    </div>
</div>
