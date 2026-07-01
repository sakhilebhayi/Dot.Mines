<div>
    <!-- Header -->
    <div class="mb-6">
        <a href="{{ route('fleet') }}" class="text-amber-400 hover:text-amber-300 mb-4 inline-flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Fleet
        </a>
        <h1 class="text-3xl font-bold text-white">{{ $machine->name }}</h1>
        <p class="text-gray-400 mt-2">{{ $machine->manufacturer }} {{ $machine->model }}</p>
    </div>

    <!-- Machine Information Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Status Card -->
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
            <p class="text-gray-400 text-sm">Status</p>
            <div class="mt-2">
                @if ($machine->status === 'active')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-500 bg-opacity-20 text-green-400">Active</span>
                @elseif ($machine->status === 'idle')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-500 bg-opacity-20 text-blue-400">Idle</span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-500 bg-opacity-20 text-red-400">Maintenance</span>
                @endif
            </div>
        </div>

        <!-- Serial Number Card -->
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
            <p class="text-gray-400 text-sm">Serial Number</p>
            <p class="text-xl font-semibold text-white mt-2">{{ $machine->serial_number ?? 'N/A' }}</p>
        </div>

        <!-- Capacity Card -->
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
            <p class="text-gray-400 text-sm">Capacity</p>
            <p class="text-xl font-semibold text-white mt-2">{{ $machine->capacity ? number_format($machine->capacity) . ' tons' : 'N/A' }}</p>
        </div>

        <!-- Last Updated Card -->
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
            <p class="text-gray-400 text-sm">Last Updated</p>
            <p class="text-xl font-semibold text-white mt-2">{{ $machine->updated_at?->diffForHumans() ?? 'Never' }}</p>
        </div>
    </div>

    <!-- Location Information -->
    @if ($machine->latitude && $machine->longitude)
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-white mb-4">Current Location</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-400 text-sm">Latitude</p>
                    <p class="text-white font-mono">{{ $machine->latitude }}</p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Longitude</p>
                    <p class="text-white font-mono">{{ $machine->longitude }}</p>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-4">
                <a href="https://maps.google.com/?q={{ $machine->latitude }},{{ $machine->longitude }}" target="_blank" class="text-amber-400 hover:text-amber-300">
                    View on Google Maps →
                </a>
            </p>
        </div>
    @endif

    <!-- Recent Metrics -->
    @if ($metrics->count() > 0)
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-white mb-4">Recent Sensor Data</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-700">
                        <tr>
                            <th class="text-left px-4 py-2 text-gray-400">Time</th>
                            <th class="text-left px-4 py-2 text-gray-400">RPM</th>
                            <th class="text-left px-4 py-2 text-gray-400">Temp (°C)</th>
                            <th class="text-left px-4 py-2 text-gray-400">Fuel (%)</th>
                            <th class="text-left px-4 py-2 text-gray-400">Load</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach ($metrics as $metric)
                            <tr class="hover:bg-gray-700">
                                <td class="px-4 py-2 text-gray-300">{{ $metric->created_at->format('H:i:s') }}</td>
                                <td class="px-4 py-2 text-gray-300">{{ $metric->rpm ?? 'N/A' }}</td>
                                <td class="px-4 py-2 text-gray-300">{{ $metric->coolant_temperature ?? 'N/A' }}</td>
                                <td class="px-4 py-2 text-gray-300">{{ $metric->fuel_level ?? 'N/A' }}</td>
                                <td class="px-4 py-2 text-gray-300">{{ $metric->payload_weight ?? 'N/A' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Recent Alerts -->
    @if ($recentAlerts->count() > 0)
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Recent Alerts</h3>
            <div class="space-y-3">
                @foreach ($recentAlerts as $alert)
                    <div class="flex items-start justify-between p-4 bg-gray-700 rounded-lg">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                @if ($alert->priority === 'high')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500 bg-opacity-20 text-red-400">High</span>
                                @elseif ($alert->priority === 'medium')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-500 bg-opacity-20 text-yellow-400">Medium</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500 bg-opacity-20 text-blue-400">Low</span>
                                @endif
                                <span class="text-white font-medium">{{ ucfirst(str_replace('_', ' ', $alert->type)) }}</span>
                            </div>
                            <p class="text-gray-300 text-sm mt-1">{{ $alert->message }}</p>
                            <p class="text-gray-500 text-xs mt-1">{{ $alert->created_at->diffForHumans() }}</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            @if ($alert->status === 'open')
                                bg-red-500 bg-opacity-20 text-red-400
                            @elseif ($alert->status === 'acknowledged')
                                bg-yellow-500 bg-opacity-20 text-yellow-400
                            @else
                                bg-gray-500 bg-opacity-20 text-gray-400
                            @endif
                        ">
                            {{ ucfirst($alert->status) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 text-center">
            <p class="text-gray-400">No alerts for this machine</p>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────── --}}
    {{-- Bell Equipment Telemetry (only shown when machine is linked)    --}}
    {{-- ─────────────────────────────────────────────────────────────── --}}
    @if ($bellEquipment !== null)
        <div class="mt-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-2 h-6 bg-amber-500 rounded-full"></div>
                <h2 class="text-xl font-bold text-white">Bell Equipment Telemetry</h2>
                <span class="text-xs text-slate-400 bg-slate-800 px-2 py-1 rounded">
                    {{ $bellEquipment->equipment_id }} &middot; {{ $bellEquipment->model }} &middot; SN: {{ $bellEquipment->serial_number }}
                </span>
            </div>

            {{-- 1. Current Status Card --}}
            @if ($bellStatus !== null)
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-slate-800 border border-slate-700 rounded-lg p-4">
                        <p class="text-xs text-slate-400 mb-1">Engine</p>
                        @if ($bellStatus->engine_running)
                            <span class="inline-flex items-center gap-1.5 text-emerald-400 font-semibold">
                                <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span> Running
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-slate-400 font-semibold">
                                <span class="w-2 h-2 bg-slate-500 rounded-full"></span> Off
                            </span>
                        @endif
                    </div>
                    <div class="bg-slate-800 border border-slate-700 rounded-lg p-4">
                        <p class="text-xs text-slate-400 mb-1">Fuel Remaining</p>
                        <p class="text-xl font-bold text-white">{{ $bellStatus->fuel_remaining_percent !== null ? number_format($bellStatus->fuel_remaining_percent, 1).'%' : 'N/A' }}</p>
                    </div>
                    <div class="bg-slate-800 border border-slate-700 rounded-lg p-4">
                        <p class="text-xs text-slate-400 mb-1">Operating Hours</p>
                        <p class="text-xl font-bold text-white">{{ $bellStatus->operating_hours !== null ? number_format($bellStatus->operating_hours, 1).' h' : 'N/A' }}</p>
                    </div>
                    <div class="bg-slate-800 border border-slate-700 rounded-lg p-4">
                        <p class="text-xs text-slate-400 mb-1">Load Count</p>
                        <p class="text-xl font-bold text-white">{{ $bellStatus->load_count !== null ? number_format($bellStatus->load_count) : 'N/A' }}</p>
                    </div>
                </div>
                @if ($bellStatus->updated_date)
                    <p class="text-xs text-slate-500 -mt-4 mb-6">Last updated: {{ \Carbon\Carbon::parse($bellStatus->updated_date)->diffForHumans() }}</p>
                @endif
            @endif

            {{-- 2. Active Caution Codes --}}
            @if ($bellCautionCodes->isNotEmpty())
                <div class="bg-amber-900/20 border border-amber-700/50 rounded-lg p-5 mb-6">
                    <h3 class="text-sm font-semibold text-amber-400 uppercase tracking-wide mb-3">
                        Active Caution Codes ({{ $bellCautionCodes->count() }})
                    </h3>
                    <div class="space-y-2">
                        @foreach ($bellCautionCodes as $code)
                            <div class="flex items-center justify-between bg-slate-800/60 rounded px-3 py-2">
                                <div>
                                    <span class="font-mono text-sm text-amber-300">{{ $code->fault_code }}</span>
                                    @if ($code->fault_description)
                                        <span class="text-slate-300 text-sm ml-2">— {{ $code->fault_description }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs rounded-full px-2 py-0.5
                                        @if (strtolower($code->severity) === 'critical') bg-red-500/20 text-red-300
                                        @elseif (strtolower($code->severity) === 'warning') bg-amber-500/20 text-amber-300
                                        @else bg-slate-700 text-slate-300 @endif
                                    ">{{ ucfirst($code->severity) }}</span>
                                    <span class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($code->occurred_at)->format('d M Y') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- 3. Fuel Level Trend Chart --}}
            @if ($bellFuelHistory->isNotEmpty())
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 mb-6"
                     x-data="{
                         labels: {{ json_encode($bellFuelHistory->pluck('recorded_at')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d M'))->values()) }},
                         data: {{ json_encode($bellFuelHistory->pluck('fuel_remaining_percent')->map(fn($v) => round((float)$v, 1))->values()) }},
                         chart: null,
                         init() {
                             this.chart = new Chart(this.$refs.canvas, {
                                 type: 'line',
                                 data: {
                                     labels: this.labels,
                                     datasets: [{ label: 'Fuel %', data: this.data, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)', tension: 0.3, fill: true, pointRadius: 2 }]
                                 },
                                 options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: '#94a3b8', maxTicksLimit: 10 } }, y: { ticks: { color: '#94a3b8' }, min: 0, max: 100 } } }
                             });
                         }
                     }">
                    <h3 class="text-sm font-semibold text-white mb-4">Fuel Level Trend (May → Today)</h3>
                    <canvas x-ref="canvas" class="w-full" style="max-height:180px"></canvas>
                </div>
            @endif

            {{-- 4. Operating Hours Trend --}}
            @if ($bellOpHoursHistory->isNotEmpty())
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 mb-6"
                     x-data="{
                         labels: {{ json_encode($bellOpHoursHistory->pluck('recorded_at')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d M'))->values()) }},
                         data: {{ json_encode($bellOpHoursHistory->pluck('operating_hours')->map(fn($v) => round((float)$v, 1))->values()) }},
                         chart: null,
                         init() {
                             this.chart = new Chart(this.$refs.canvas, {
                                 type: 'line',
                                 data: {
                                     labels: this.labels,
                                     datasets: [{ label: 'Operating Hours', data: this.data, borderColor: '#22d3ee', backgroundColor: 'rgba(34,211,238,0.1)', tension: 0.3, fill: true, pointRadius: 2 }]
                                 },
                                 options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: '#94a3b8', maxTicksLimit: 10 } }, y: { ticks: { color: '#94a3b8' } } } }
                             });
                         }
                     }">
                    <h3 class="text-sm font-semibold text-white mb-4">Cumulative Operating Hours (May → Today)</h3>
                    <canvas x-ref="canvas" class="w-full" style="max-height:180px"></canvas>
                </div>
            @endif

            {{-- 5. Load Count & Payload History --}}
            @if ($bellLoadHistory->isNotEmpty())
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 mb-6"
                     x-data="{
                         labels: {{ json_encode($bellLoadHistory->pluck('kpi_date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d M'))->values()) }},
                         loads: {{ json_encode($bellLoadHistory->pluck('loads_moved')->map(fn($v) => (int)$v)->values()) }},
                         payload: {{ json_encode($bellLoadHistory->pluck('payload_moved')->map(fn($v) => round((float)$v, 1))->values()) }},
                         chart: null,
                         init() {
                             this.chart = new Chart(this.$refs.canvas, {
                                 type: 'bar',
                                 data: {
                                     labels: this.labels,
                                     datasets: [
                                         { label: 'Loads', data: this.loads, backgroundColor: 'rgba(99,102,241,0.7)', yAxisID: 'y' },
                                         { label: 'Payload (t)', data: this.payload, backgroundColor: 'rgba(16,185,129,0.7)', yAxisID: 'y1' }
                                     ]
                                 },
                                 options: {
                                     responsive: true,
                                     plugins: { legend: { labels: { color: '#94a3b8' } } },
                                     scales: {
                                         x: { ticks: { color: '#94a3b8', maxTicksLimit: 10 } },
                                         y: { ticks: { color: '#94a3b8' }, title: { display: true, text: 'Loads', color: '#94a3b8' } },
                                         y1: { position: 'right', ticks: { color: '#94a3b8' }, title: { display: true, text: 'Payload (t)', color: '#94a3b8' }, grid: { drawOnChartArea: false } }
                                     }
                                 }
                             });
                         }
                     }">
                    <h3 class="text-sm font-semibold text-white mb-4">Daily Loads & Payload (May → Today)</h3>
                    <canvas x-ref="canvas" class="w-full" style="max-height:200px"></canvas>
                </div>
            @endif

            {{-- 6. DEF Level History --}}
            @if ($bellDefHistory->isNotEmpty())
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 mb-6"
                     x-data="{
                         labels: {{ json_encode($bellDefHistory->pluck('recorded_at')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d M'))->values()) }},
                         data: {{ json_encode($bellDefHistory->pluck('def_remaining_percent')->map(fn($v) => round((float)$v, 1))->values()) }},
                         chart: null,
                         init() {
                             this.chart = new Chart(this.$refs.canvas, {
                                 type: 'line',
                                 data: {
                                     labels: this.labels,
                                     datasets: [{ label: 'DEF %', data: this.data, borderColor: '#a78bfa', backgroundColor: 'rgba(167,139,250,0.1)', tension: 0.3, fill: true, pointRadius: 2 }]
                                 },
                                 options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: '#94a3b8', maxTicksLimit: 10 } }, y: { ticks: { color: '#94a3b8' }, min: 0, max: 100 } } }
                             });
                         }
                     }">
                    <h3 class="text-sm font-semibold text-white mb-4">DEF Level Trend (May → Today)</h3>
                    <canvas x-ref="canvas" class="w-full" style="max-height:180px"></canvas>
                </div>
            @endif

            {{-- 7. DPF Regeneration Hours --}}
            @if ($bellRegenHistory->isNotEmpty())
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 mb-6"
                     x-data="{
                         labels: {{ json_encode($bellRegenHistory->pluck('recorded_at')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d M'))->values()) }},
                         data: {{ json_encode($bellRegenHistory->pluck('regeneration_hours')->map(fn($v) => round((float)$v, 2))->values()) }},
                         chart: null,
                         init() {
                             this.chart = new Chart(this.$refs.canvas, {
                                 type: 'line',
                                 data: {
                                     labels: this.labels,
                                     datasets: [{ label: 'Regen Hours', data: this.data, borderColor: '#fb923c', backgroundColor: 'rgba(251,146,60,0.1)', tension: 0.3, fill: true, pointRadius: 2 }]
                                 },
                                 options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: '#94a3b8', maxTicksLimit: 10 } }, y: { ticks: { color: '#94a3b8' } } } }
                             });
                         }
                     }">
                    <h3 class="text-sm font-semibold text-white mb-4">DPF Regeneration Hours (May → Today)</h3>
                    <canvas x-ref="canvas" class="w-full" style="max-height:180px"></canvas>
                </div>
            @endif

            {{-- 8. Location History Table --}}
            @if ($bellLocationHistory->isNotEmpty())
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-white">Location History (latest {{ $bellLocationHistory->count() }} points)</h3>
                        @if ($bellLocationHistory->first()->latitude && $bellLocationHistory->first()->longitude)
                            <a href="https://maps.google.com/?q={{ $bellLocationHistory->first()->latitude }},{{ $bellLocationHistory->first()->longitude }}" target="_blank"
                               class="text-xs text-amber-400 hover:text-amber-300">
                                Latest on Maps →
                            </a>
                        @endif
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-slate-700">
                                <tr>
                                    <th class="text-left px-3 py-2 text-slate-400">Time</th>
                                    <th class="text-left px-3 py-2 text-slate-400">Latitude</th>
                                    <th class="text-left px-3 py-2 text-slate-400">Longitude</th>
                                    <th class="text-left px-3 py-2 text-slate-400">Heading</th>
                                    <th class="text-left px-3 py-2 text-slate-400">Speed (km/h)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700">
                                @foreach ($bellLocationHistory->take(20) as $loc)
                                    <tr class="hover:bg-slate-700/50">
                                        <td class="px-3 py-2 text-slate-300 font-mono text-xs">{{ \Carbon\Carbon::parse($loc->recorded_at)->format('d M H:i') }}</td>
                                        <td class="px-3 py-2 text-slate-300 font-mono text-xs">{{ $loc->latitude }}</td>
                                        <td class="px-3 py-2 text-slate-300 font-mono text-xs">{{ $loc->longitude }}</td>
                                        <td class="px-3 py-2 text-slate-300 text-xs">{{ $loc->heading_degrees !== null ? $loc->heading_degrees.'°' : '—' }}</td>
                                        <td class="px-3 py-2 text-slate-300 text-xs">{{ $loc->speed_kmh !== null ? number_format($loc->speed_kmh, 1) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($bellLocationHistory->count() > 20)
                            <p class="text-xs text-slate-500 mt-2 px-3">Showing 20 of {{ $bellLocationHistory->count() }} records</p>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    @endif
</div>
