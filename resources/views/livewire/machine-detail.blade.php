{{-- 60s, visible-only: telemetry/loss panels re-render as new metric rows
     land (5-minute location cadence, 15-minute full sync). --}}
<div wire:poll.visible.60s>
    <!-- Header -->
    <div class="mb-6">
        <a href="{{ route('fleet') }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)] mb-4 inline-flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Fleet
        </a>
        <h1 class="text-3xl font-bold text-[var(--stone)]">{{ $machine->name }}</h1>
        <p class="text-[var(--sand)] mt-2">{{ $machine->manufacturer }} {{ $machine->model }}</p>
        <div class="mt-2">
            <x-freshness :timestamp="$metrics->first()?->recorded_at" :stale-after="1800" label="Telemetry" />
        </div>
    </div>

    <!-- Machine Information Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <!-- Status Card -->
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6">
            <p class="text-[var(--sand)] text-sm">Status</p>
            <div class="mt-2">
                @if ($machine->status === 'active')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-500/20 text-green-400">Active</span>
                @elseif ($machine->status === 'idle')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-500/20 text-blue-400">Idle</span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-500/20 text-red-400">Maintenance</span>
                @endif
            </div>
        </div>

        <!-- Serial Number Card -->
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6">
            <p class="text-[var(--sand)] text-sm">Serial Number</p>
            <p class="text-xl font-semibold text-[var(--stone)] mt-2">{{ $machine->serial_number ?? 'N/A' }}</p>
        </div>

        <!-- Capacity Card -->
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6">
            <p class="text-[var(--sand)] text-sm">Capacity</p>
            <p class="text-xl font-semibold text-[var(--stone)] mt-2">{{ $machine->capacity ? number_format($machine->capacity) . ' tons' : 'N/A' }}</p>
        </div>

        <!-- Engine Hours Card (latest cumulative reading from telemetry) -->
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6">
            <p class="text-[var(--sand)] text-sm">Engine Hours</p>
            <p class="text-xl font-semibold text-[var(--stone)] mt-2">
                {{ $machine->latestEngineHoursMetric ? number_format($machine->latestEngineHoursMetric->operating_hours, 1) . ' hrs' : 'N/A' }}
            </p>
        </div>

        <!-- Last Updated Card -->
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6">
            <p class="text-[var(--sand)] text-sm">Last Updated</p>
            <p class="text-xl font-semibold text-[var(--stone)] mt-2">{{ $machine->updated_at?->diffForHumans() ?? 'Never' }}</p>
        </div>
    </div>

    <!-- Location Information. The card used to read $machine->latitude /
         ->longitude, columns that don't exist (the real ones are
         last_location_latitude / last_location_longitude), so it never
         rendered even for machines with live GPS. -->
    @if ($machine->last_location_latitude && $machine->last_location_longitude)
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">Current Location</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-[var(--sand)] text-sm">Latitude</p>
                    <p class="text-[var(--stone)] font-mono">{{ $machine->last_location_latitude }}</p>
                </div>
                <div>
                    <p class="text-[var(--sand)] text-sm">Longitude</p>
                    <p class="text-[var(--stone)] font-mono">{{ $machine->last_location_longitude }}</p>
                </div>
            </div>
            <p class="text-sm text-[var(--sand)]/70 mt-4">
                <a href="https://maps.google.com/?q={{ $machine->last_location_latitude }},{{ $machine->last_location_longitude }}" target="_blank" class="text-[var(--gold)] hover:text-[var(--gold-soft)]">
                    View on Google Maps →
                </a>
            </p>
        </div>
    @endif

    <!-- Production Loss Accountability -->
    @livewire('production-loss-panel', ['machine' => $machine], key('loss-panel-'.$machine->id))

    <!-- Recent Metrics -->
    @if ($metrics->count() > 0)
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">Recent Sensor Data</h3>
            @php
                // The table used to read $metric->rpm and $metric->payload_weight --
                // columns that don't exist (the real ones are engine_rpm and
                // load_weight) -- so every reading rendered N/A even when real
                // telemetry was stored. A null here means the feed genuinely did
                // not report that sensor, so render an explained dash instead.
                $sensorCell = fn ($value, int $decimals = 1) => $value === null ? '—' : number_format((float) $value, $decimals);
            @endphp
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-[var(--line)]">
                        <tr>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">Time</th>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">RPM</th>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">Temp (°C)</th>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">Fuel (%)</th>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">Load</th>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">Engine (hrs)</th>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">Idle (hrs)</th>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">DEF (%)</th>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">Odometer</th>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">Engine</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--line)]">
                        @foreach ($metrics as $metric)
                            @php
                                $odometer = data_get($metric->raw_data, 'odometer');
                                $odometerUnits = data_get($metric->raw_data, 'odometer_units');
                                $odometerUnits = ['kilometre' => 'km', 'mile' => 'mi'][$odometerUnits] ?? $odometerUnits;
                                $engineRunning = data_get($metric->raw_data, 'engine_running');
                            @endphp
                            <tr class="hover:bg-white/5" wire:key="metric-{{ $metric->id }}">
                                {{-- recorded_at is the provider's own reading timestamp
                                     (Bell: TelemetryDate); created_at is only when the
                                     row was synced. --}}
                                <td class="px-4 py-2 text-[var(--sand)]">{{ ($metric->recorded_at ?? $metric->created_at)->format('H:i:s') }}</td>
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $sensorCell($metric->engine_rpm, 0) }}</td>
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $sensorCell($metric->engine_temperature ?? $metric->coolant_temperature) }}</td>
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $sensorCell($metric->fuel_level, 0) }}</td>
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $sensorCell($metric->load_weight) }}</td>
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $sensorCell($metric->operating_hours) }}</td>
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $sensorCell($metric->idle_hours) }}</td>
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $sensorCell(data_get($metric->raw_data, 'def_percent'), 0) }}</td>
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $odometer === null ? '—' : number_format((float) $odometer) . ($odometerUnits ? ' ' . $odometerUnits : '') }}</td>
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $engineRunning === null ? '—' : ($engineRunning ? 'On' : 'Off') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-[var(--sand)]/70 mt-3">
                — = not reported by this machine's telemetry feed.
                @if (strtolower($machine->manufacturer ?? '') === 'bell')
                    Bell's ISO 15143-3 fleet feed does not include engine RPM, engine temperature, or instantaneous load.
                @endif
            </p>
        </div>
    @endif

    <!-- Recent Alerts -->
    @if ($recentAlerts->count() > 0)
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6">
            <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">Recent Alerts</h3>
            <div class="space-y-3">
                @foreach ($recentAlerts as $alert)
                    <div class="flex items-start justify-between p-4 bg-white/5 rounded-lg">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                @if ($alert->priority === 'high')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/20 text-red-400">High</span>
                                @elseif ($alert->priority === 'medium')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-500/20 text-yellow-400">Medium</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500/20 text-blue-400">Low</span>
                                @endif
                                <span class="text-[var(--stone)] font-medium">{{ ucfirst(str_replace('_', ' ', $alert->type)) }}</span>
                            </div>
                            <p class="text-[var(--sand)] text-sm mt-1">{{ $alert->message }}</p>
                            <p class="text-[var(--sand)]/70 text-xs mt-1">{{ $alert->created_at->diffForHumans() }}</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            @if ($alert->status === 'open')
                                bg-red-500/20 text-red-400
                            @elseif ($alert->status === 'acknowledged')
                                bg-yellow-500/20 text-yellow-400
                            @else
                                bg-gray-500/20 text-[var(--sand)]
                            @endif
                        ">
                            {{ ucfirst($alert->status) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6 text-center">
            <p class="text-[var(--sand)]">No alerts for this machine</p>
        </div>
    @endif
</div>
