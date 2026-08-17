<div>
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
    </div>

    <!-- Machine Information Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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

        <!-- Last Updated Card -->
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6">
            <p class="text-[var(--sand)] text-sm">Last Updated</p>
            <p class="text-xl font-semibold text-[var(--stone)] mt-2">{{ $machine->updated_at?->diffForHumans() ?? 'Never' }}</p>
        </div>
    </div>

    <!-- Location Information -->
    @if ($machine->latitude && $machine->longitude)
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">Current Location</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-[var(--sand)] text-sm">Latitude</p>
                    <p class="text-[var(--stone)] font-mono">{{ $machine->latitude }}</p>
                </div>
                <div>
                    <p class="text-[var(--sand)] text-sm">Longitude</p>
                    <p class="text-[var(--stone)] font-mono">{{ $machine->longitude }}</p>
                </div>
            </div>
            <p class="text-sm text-[var(--sand)]/70 mt-4">
                <a href="https://maps.google.com/?q={{ $machine->latitude }},{{ $machine->longitude }}" target="_blank" class="text-[var(--gold)] hover:text-[var(--gold-soft)]">
                    View on Google Maps →
                </a>
            </p>
        </div>
    @endif

    <!-- Recent Metrics -->
    @if ($metrics->count() > 0)
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">Recent Sensor Data</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-[var(--line)]">
                        <tr>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">Time</th>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">RPM</th>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">Temp (°C)</th>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">Fuel (%)</th>
                            <th class="text-left px-4 py-2 text-[var(--sand)]">Load</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--line)]">
                        @foreach ($metrics as $metric)
                            <tr class="hover:bg-white/5">
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $metric->created_at->format('H:i:s') }}</td>
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $metric->rpm ?? 'N/A' }}</td>
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $metric->coolant_temperature ?? 'N/A' }}</td>
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $metric->fuel_level ?? 'N/A' }}</td>
                                <td class="px-4 py-2 text-[var(--sand)]">{{ $metric->payload_weight ?? 'N/A' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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
