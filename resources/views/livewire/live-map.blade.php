{{-- 60s, visible-only: positions land in the DB on the 5-minute location
     cadence, so this keeps markers within a minute of the freshest honest
     data without polling from background tabs. Marker updates are diffed
     and animated in live-map.js -- the map never reloads. --}}
<div wire:poll.visible.60s="refreshPositions">
    <!-- Leaflet CSS - loaded directly in component -->

    <div class="container mx-auto py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Left: Map and controls -->
            <div class="w-full space-y-6">
                <div class="bg-[var(--ink-soft)] rounded-lg shadow-lg p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h1 class="text-2xl font-bold text-[var(--stone)]">Live Fleet Tracking</h1>
                            <x-freshness :timestamp="$telemetryFreshestAt" :stale-after="$telemetryStaleAfter" label="Telemetry" />
                        </div>
                        <div class="flex flex-wrap gap-2 items-center">
                            <button wire:click="toggleMachines" class="px-4 py-2 min-w-[9rem] rounded-lg transition-colors {{ $showMachines ? 'bg-green-600 hover:bg-green-700' : 'bg-white/5 hover:bg-white/10 border border-[var(--line)]' }} text-[var(--stone)] text-sm">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    Machines {{ $showMachines ? '(On)' : '(Off)' }}
                                </span>
                            </button>
                            <button wire:click="toggleGeofences" class="px-4 py-2 min-w-[9rem] rounded-lg transition-colors {{ $showGeofences ? 'bg-blue-600 hover:bg-blue-700' : 'bg-white/5 hover:bg-white/10 border border-[var(--line)]' }} text-[var(--stone)] text-sm">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13"></path>
                                    </svg>
                                    Geofences {{ $showGeofences ? '(On)' : '(Off)' }}
                                </span>
                            </button>
                            <!-- Action Buttons moved here -->
                            <a href="{{ route('fleet.route-planning') }}" class="px-4 py-2 min-w-[9rem] text-sm bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-all font-display font-semibold flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Plan New Route
                            </a>
                        </div>
                    </div>
                    <div class="flex flex-col md:flex-row gap-4 mb-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Map Style</label>
                            <div class="flex gap-2">
                                <button wire:click="changeMapStyle('osm')" class="flex-1 px-3 py-2 rounded-lg {{ $mapStyle === 'osm' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)]' }} text-sm transition-colors">
                                    Standard
                                </button>
                                <button wire:click="changeMapStyle('satellite')" class="flex-1 px-3 py-2 rounded-lg {{ $mapStyle === 'satellite' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)]' }} text-sm transition-colors">
                                    Satellite
                                </button>
                            </div>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Filter by Status</label>
                            <select wire:model.live="selectedStatus" class="w-full px-3 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] text-sm focus:outline-none focus:border-[var(--gold)]">
                                <option value="">All Machines</option>
                                <option value="active">Active Only</option>
                                <option value="idle">Idle Only</option>
                                <option value="maintenance">Maintenance Only</option>
                            </select>
                        </div>

                        <div class="flex-1">
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Select Mine Area</label>
                            <select id="mineAreaSelect" wire:model.live="selectedMineAreaId" class="w-full px-3 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] text-sm focus:outline-none focus:border-[var(--gold)]">
                                <option value="">All Areas</option>
                                @foreach($mineAreas ?? [] as $area)
                                    <option value="{{ data_get($area, 'id') }}">{{ data_get($area, 'name') }} @if(data_get($area, 'type')) ({{ ucfirst(data_get($area, 'type')) }})@endif</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <div class="bg-green-600/20 rounded-lg p-2 border border-green-600">
                            <p class="text-green-400 text-xs font-semibold">ACTIVE</p>
                            <p class="text-green-300 text-lg font-bold">{{ $machineStatuses['active'] ?? 0 }}</p>
                        </div>
                        <div class="bg-blue-600/20 rounded-lg p-2 border border-blue-600">
                            <p class="text-blue-400 text-xs font-semibold">IDLE</p>
                            <p class="text-blue-300 text-lg font-bold">{{ $machineStatuses['idle'] ?? 0 }}</p>
                        </div>
                        <div class="bg-red-600/20 rounded-lg p-2 border border-red-600">
                            <p class="text-red-400 text-xs font-semibold">MAINT</p>
                            <p class="text-red-300 text-lg font-bold">{{ $machineStatuses['maintenance'] ?? 0 }}</p>
                        </div>
                    </div>
                    <div
                        wire:ignore
                        id="map"
                        class="w-full h-[36rem] rounded-lg shadow-lg bg-[var(--ink)]"
                        style="height:36rem;"
                        data-machines="{{ json_encode($machines) }}"
                        data-geofences="{{ json_encode($geofences) }}"
                        data-mine-areas="{{ json_encode($mineAreas ?? []) }}"
                        data-map-style="{{ $mapStyle }}"
                        data-show-machines="{{ $showMachines ? '1' : '0' }}"
                        data-show-geofences="{{ $showGeofences ? '1' : '0' }}"
                        data-center-lat="{{ $centerLat }}"
                        data-center-lng="{{ $centerLng }}"
                        data-zoom-level="{{ $zoomLevel }}"
                    ></div>
                    <div id="map-toast" class="hidden fixed top-4 right-4 z-50 pointer-events-none">
                        <div class="bg-[var(--gold)] text-[var(--ink)] px-4 py-2 rounded shadow-lg text-sm message"></div>
                    </div>
                    <div id="map-loading" class="absolute inset-0 bg-[var(--ink)]/90 flex items-center justify-center z-50" style="display:none;">
                        <div class="text-center">
                            <div class="text-6xl mb-4">🗺️</div>
                            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-[var(--gold)]"></div>
                            <h3 class="text-xl font-semibold text-[var(--stone)] mt-4 mb-2">Loading map...</h3>
                            <p class="text-[var(--sand)] mb-2">Fetching live fleet and geofence data.</p>
                            <ul class="text-sm text-[var(--sand)] mb-4 space-y-1">
                                <li>• If loading takes too long, check your connection</li>
                                <li>• Refresh the page if the map does not appear</li>
                                <li>• Data will update in real time once loaded</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Map Toast and Loading Indicator (overlay, always present) removed: handled inside map card -->
    
    <!-- Leaflet JS - loaded directly in component -->
    
    @vite(['resources/js/live-map.js'])

    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
    #map {
        width: 100%;
        height: 100%;
        min-height: 50rem;
        border-radius: 0.5rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        background: #111827;
    }
    .leaflet-popup-content {
        font-family: inherit;
        margin: 0;
        padding: 0;
    }
    .leaflet-popup-content-wrapper,
    .leaflet-popup-tip {
        background-color: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .machine-marker {
        filter: drop-shadow(0 0 8px rgba(0, 0, 0, 0.5));
    }
    .geofence-polygon:hover {
        fill-opacity: 0.2 !important;
    }
    .leaflet-control-zoom {
        background-color: rgba(31, 41, 55, 0.9) !important;
        border: 1px solid rgba(107, 114, 128, 0.5) !important;
        border-radius: 8px !important;
    }
    .leaflet-control-zoom a {
        color: white !important;
        background-color: rgba(55, 65, 81, 0.8) !important;
        border-bottom: 1px solid rgba(107, 114, 128, 0.5) !important;
    }
    .leaflet-control-zoom a:hover {
        background-color: rgba(75, 85, 99, 0.9) !important;
    }
    .leaflet-control-zoom a.leaflet-disabled {
        background-color: rgba(31, 41, 55, 0.5) !important;
        color: rgba(156, 163, 175, 0.5) !important;
    }
    </style>
</div>
