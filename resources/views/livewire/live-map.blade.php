<div wire:poll.{{ $pollInterval }}s="refreshMachinePositions">
<div>
    <!-- Leaflet CSS - loaded directly in component -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    
    <div class="container mx-auto py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Left: Map and controls -->
            <div class="w-full space-y-6">
                <div class="bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h1 class="text-2xl font-bold text-white">Live Fleet Tracking</h1>
                        <div class="flex flex-wrap gap-2 items-center">
                            <button wire:click="toggleMachines" class="px-4 py-2 min-w-[9rem] rounded-lg transition-colors {{ $showMachines ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-700 hover:bg-gray-600' }} text-white text-sm">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    Machines {{ $showMachines ? '(On)' : '(Off)' }}
                                </span>
                            </button>
                            <button wire:click="toggleGeofences" class="px-4 py-2 min-w-[9rem] rounded-lg transition-colors {{ $showGeofences ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-700 hover:bg-gray-600' }} text-white text-sm">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13"></path>
                                    </svg>
                                    Geofences {{ $showGeofences ? '(On)' : '(Off)' }}
                                </span>
                            </button>
                            <!-- Action Buttons moved here -->
                            <a href="{{ route('fleet.route-planning') }}" class="px-4 py-2 min-w-[9rem] text-sm bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition-colors font-medium flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Plan New Route
                            </a>
                                <button wire:click="toggleRoutes" class="px-4 py-2 min-w-[9rem] rounded-lg transition-colors {{ $showRoutes ? 'bg-violet-600 hover:bg-violet-700' : 'bg-gray-700 hover:bg-gray-600' }} text-white text-sm">
                                    <span class="flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13"></path>
                                        </svg>
                                        Routes {{ $showRoutes ? '(On)' : '(Off)' }}
                                    </span>
                                </button>
                                <button wire:click="toggleTMP" class="px-4 py-2 min-w-[9rem] rounded-lg transition-colors {{ $showTMP ? 'bg-orange-500 hover:bg-orange-600 ring-2 ring-orange-300' : 'bg-gray-700 hover:bg-gray-600' }} text-white text-sm">
                                    <span class="flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                        </svg>
                                        TMP {{ $showTMP ? '(On)' : '(Off)' }}
                                    </span>
                                </button>
                                <button wire:click="toggleHeatMap" class="px-4 py-2 min-w-[9rem] rounded-lg transition-colors {{ $showHeatMap ? 'bg-rose-600 hover:bg-rose-700 ring-2 ring-rose-300' : 'bg-gray-700 hover:bg-gray-600' }} text-white text-sm">
                                    <span class="flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"/>
                                        </svg>
                                        Heat Map {{ $showHeatMap ? '(On)' : '(Off)' }}
                                    </span>
                                </button>
                                <button wire:click="toggleEvents" class="px-4 py-2 min-w-[9rem] rounded-lg transition-colors {{ $showEvents ? 'bg-teal-600 hover:bg-teal-700 ring-2 ring-teal-300' : 'bg-gray-700 hover:bg-gray-600' }} text-white text-sm">
                                    <span class="flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        Events {{ $showEvents ? '(On)' : '(Off)' }}
                                    </span>
                                </button>
                        </div>
                    </div>
                    <div class="flex flex-col md:flex-row gap-4 mb-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Map Style</label>
                            <div class="flex gap-2">
                                <button wire:click="changeMapStyle('osm')" class="flex-1 px-3 py-2 rounded-lg {{ $mapStyle === 'osm' ? 'bg-amber-600' : 'bg-gray-700 hover:bg-gray-600' }} text-white text-sm transition-colors">
                                    Standard
                                </button>
                                <button wire:click="changeMapStyle('satellite')" class="flex-1 px-3 py-2 rounded-lg {{ $mapStyle === 'satellite' ? 'bg-amber-600' : 'bg-gray-700 hover:bg-gray-600' }} text-white text-sm transition-colors">
                                    Satellite
                                </button>
                            </div>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Filter by Status</label>
                            <select wire:model.live="selectedStatus" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500">
                                <option value="">All Machines</option>
                                <option value="active">Active Only</option>
                                <option value="idle">Idle Only</option>
                                <option value="maintenance">Maintenance Only</option>
                            </select>
                        </div>

                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Select Mine Area</label>
                            <select id="mineAreaSelect" wire:model.live="selectedMineAreaId" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500">
                                <option value="">All Areas</option>
                                @foreach($mineAreas ?? [] as $area)
                                    <option value="{{ data_get($area, 'id') }}">{{ data_get($area, 'name') }} @if(data_get($area, 'type')) ({{ ucfirst(data_get($area, 'type')) }})@endif</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <div class="bg-green-600 bg-opacity-20 rounded-lg p-2 border border-green-600">
                            <p class="text-green-400 text-xs font-semibold">ACTIVE</p>
                            <p class="text-green-300 text-lg font-bold">{{ $machineStatuses['active'] ?? 0 }}</p>
                        </div>
                        <div class="bg-blue-600 bg-opacity-20 rounded-lg p-2 border border-blue-600">
                            <p class="text-blue-400 text-xs font-semibold">IDLE</p>
                            <p class="text-blue-300 text-lg font-bold">{{ $machineStatuses['idle'] ?? 0 }}</p>
                        </div>
                        <div class="bg-red-600 bg-opacity-20 rounded-lg p-2 border border-red-600">
                            <p class="text-red-400 text-xs font-semibold">MAINT</p>
                            <p class="text-red-300 text-lg font-bold">{{ $machineStatuses['maintenance'] ?? 0 }}</p>
                        </div>
                    </div>

                    <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-4 mb-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-white">Traffic Management Plan</h3>
                            <span class="text-xs text-amber-300">Operational Rules</span>
                        </div>

                        <div class="grid grid-cols-2 lg:grid-cols-5 gap-2 mb-3">
                            <div class="bg-red-900/30 border border-red-700 rounded p-2">
                                <p class="text-[10px] text-red-300 uppercase">Restricted</p>
                                <p class="text-red-200 font-bold">{{ data_get($trafficPlanData, 'restricted_zones', 0) }}</p>
                            </div>
                            <div class="bg-emerald-900/30 border border-emerald-700 rounded p-2">
                                <p class="text-[10px] text-emerald-300 uppercase">Safe</p>
                                <p class="text-emerald-200 font-bold">{{ data_get($trafficPlanData, 'safe_zones', 0) }}</p>
                            </div>
                            <div class="bg-amber-900/30 border border-amber-700 rounded p-2">
                                <p class="text-[10px] text-amber-300 uppercase">Warning</p>
                                <p class="text-amber-200 font-bold">{{ data_get($trafficPlanData, 'warning_zones', 0) }}</p>
                            </div>
                            <div class="bg-violet-900/30 border border-violet-700 rounded p-2">
                                <p class="text-[10px] text-violet-300 uppercase">Active Routes</p>
                                <p class="text-violet-200 font-bold">{{ data_get($trafficPlanData, 'active_routes', 0) }}</p>
                            </div>
                            <div class="bg-blue-900/30 border border-blue-700 rounded p-2">
                                <p class="text-[10px] text-blue-300 uppercase">Speed-Limited</p>
                                <p class="text-blue-200 font-bold">{{ data_get($trafficPlanData, 'routes_with_speed_limit', 0) }}</p>
                            </div>
                        </div>

                        <div class="text-xs text-gray-300 space-y-1">
                            <p>Default speed limits: Haul Road {{ data_get($trafficPlanData, 'default_speed_limits.haul_road', 40) }} km/h, Loading Zone {{ data_get($trafficPlanData, 'default_speed_limits.loading_zone', 20) }} km/h, Shared Zone {{ data_get($trafficPlanData, 'default_speed_limits.shared_zone', 15) }} km/h.</p>
                            <p>Rules: Avoid restricted zones, enforce one-way flow, and prioritize pedestrians in shared zones.</p>
                        </div>
                    </div>

                    {{-- ── Map Events Filter Bar (shown when events layer is on) ── --}}
                    @if ($showEvents)
                    <div class="bg-gray-900/60 border border-teal-800/60 rounded-lg px-4 py-2.5 mb-4">
                        <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                            <p class="text-xs font-semibold text-teal-300 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Map Events · last 12 h
                                <span class="ml-1 bg-teal-700/70 text-teal-200 text-[10px] px-1.5 py-0.5 rounded-full font-bold">
                                    {{ count($mapEvents) }}
                                </span>
                            </p>
                            {{-- Per-type filter pills --}}
                            <div class="flex flex-wrap gap-1">
                                <button wire:click="filterEventType('all')"
                                        class="text-[10px] px-2 py-0.5 rounded-full font-semibold transition-all
                                            {{ $eventTypeFilter === 'all' ? 'bg-teal-500 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}">
                                    All
                                </button>
                                @foreach($eventTypeConfig as $typeKey => $cfg)
                                    <button wire:click="filterEventType('{{ $typeKey }}')"
                                            class="text-[10px] px-2 py-0.5 rounded-full font-semibold transition-all
                                                {{ $eventTypeFilter === $typeKey ? 'text-white shadow' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}"
                                            @if($eventTypeFilter === $typeKey) style="background-color:{{ $cfg['color'] }}" @endif>
                                        {{ $cfg['emoji'] }} {{ $cfg['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Scrollable mini event feed --}}
                        @if (count($mapEvents) > 0)
                        <div class="max-h-28 overflow-y-auto space-y-1 pr-1 map-events-feed">
                            @foreach (array_slice($mapEvents, 0, 20) as $ev)
                            <div class="flex items-start gap-2 text-[11px] py-1 border-b border-gray-700/50 last:border-0 cursor-pointer hover:bg-white/5 rounded px-1 transition-colors"
                                 onclick="window.mapFocusEvent({{ $ev['id'] }})">
                                <span class="text-base leading-none shrink-0 mt-0.5">{{ $ev['emoji'] }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-white truncate">{{ $ev['title'] }}</p>
                                    <p class="text-gray-400 truncate">
                                        {{ $ev['machine_name'] }}
                                        @if($ev['mine_area'] !== '—') · {{ $ev['mine_area'] }}@endif
                                    </p>
                                </div>
                                <span class="text-gray-500 shrink-0 whitespace-nowrap">{{ $ev['occurred_human'] }}</span>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <p class="text-xs text-gray-500 mt-1">No events recorded in this period.</p>
                        @endif
                    </div>
                    @endif

                    <div class="relative">
                    <div wire:ignore id="map" class="w-full h-[36rem] rounded-lg shadow-lg bg-gray-900"></div>

                    {{-- Heat Map Legend (shown only when heat map is active, toggled by JS) --}}
                    <div id="heatmap-legend" class="absolute bottom-10 right-3 z-[1000] rounded-lg overflow-hidden shadow-lg pointer-events-none
                         {{ $showHeatMap ? '' : 'hidden' }}" style="min-width:130px">
                        <div class="bg-gray-900/90 backdrop-blur-sm px-3 pt-2 pb-1">
                            <p class="text-xs font-semibold text-white mb-1.5 flex items-center gap-1">
                                <svg class="w-3 h-3 text-rose-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>
                                </svg>
                                Activity Intensity
                            </p>
                            {{-- Gradient bar --}}
                            <div class="w-full h-3 rounded-full mb-1" style="background:linear-gradient(to right,#0000ff,#00ff00,#ffff00,#ff8000,#ff0000)"></div>
                            <div class="flex justify-between text-[9px] text-gray-400 mb-1">
                                <span>Low</span>
                                <span>Med</span>
                                <span>High</span>
                            </div>
                            <div class="mt-1.5 space-y-0.5 border-t border-gray-700 pt-1.5">
                                <div class="flex items-center gap-1.5 text-[9px] text-gray-300">
                                    <span class="w-2 h-2 rounded-full inline-block" style="background:#ff0000"></span>Active trucks / dump zones
                                </div>
                                <div class="flex items-center gap-1.5 text-[9px] text-gray-300">
                                    <span class="w-2 h-2 rounded-full inline-block" style="background:#ffff00"></span>Loading areas
                                </div>
                                <div class="flex items-center gap-1.5 text-[9px] text-gray-300">
                                    <span class="w-2 h-2 rounded-full inline-block" style="background:#0000ff"></span>Idle / offline
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div id="map-toast" class="hidden fixed top-4 right-4 z-50 pointer-events-none">
                        <div class="bg-amber-600 text-white px-4 py-2 rounded shadow-lg text-sm message"></div>
                    </div>
                    <div id="map-loading" class="absolute inset-0 bg-gray-900 bg-opacity-90 flex items-center justify-center z-50 hidden">
                        <div class="text-center">
                            <div class="text-6xl mb-4">🗺️</div>
                            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-amber-500"></div>
                            <h3 class="text-xl font-semibold text-white mt-4 mb-2">Loading map...</h3>
                            <p class="text-gray-400 mb-2">Fetching live fleet and geofence data.</p>
                            <ul class="text-sm text-gray-500 mb-4 space-y-1">
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
    <script nonce="{{ request()->attributes->get('csp_nonce') }}" src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script nonce="{{ request()->attributes->get('csp_nonce') }}" src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-providers/1.13.0/leaflet-providers.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Leaflet.heat – canvas-based heat map plugin -->
    <script nonce="{{ request()->attributes->get('csp_nonce') }}" src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js" crossorigin=""></script>
    
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
    document.addEventListener('DOMContentLoaded', function() {
        let map = null;
        let markers = {};
        let geofencePolygons = {};
        let currentLayer = null;
        let layers = {};
        let initRetryCount = 0;
        const MAX_INIT_RETRIES = 100;

        let machinesData = @json($machines);
        let geofencesData = @json($geofences);
        let mineAreasData = @json($mineAreas ?? []);
            let routesData = @json($routes ?? []);
            let showRoutesData = @js($showRoutes);
            let routeLayers = {}; // keyed by route id
        // TMP layer state
        let showTMPData = @js($showTMP);
        let tmpRoutesData = @json($tmpRoutes ?? []);
        let tmpGeofencesData = @json($geofences); // reuse geofences (now include geofence_type)
        let tmpLayerGroup = null;
        // ── Heat map state ────────────────────────────────────────────────────
        let showHeatMapData = @js($showHeatMap);
        let heatLayer = null;
        // ── Map events state ──────────────────────────────────────────────────
        let showEventsData  = @js($showEvents);
        let eventsData      = @json($mapEvents);
        let eventTypeConfig = @json($eventTypeConfig);
        let eventMarkers    = {};    // id → L.Marker
        let eventLayerGroup = null;
        // Keep a copy of the original machines list for client-side filtering
        let originalMachinesData = Array.isArray(machinesData) ? JSON.parse(JSON.stringify(machinesData)) : [];
        let mapStyleData = @js($mapStyle);
        let showMachinesData = @js($showMachines);
        let showGeofencesData = @js($showGeofences);

        function debugLog(...args) {
            if (window && window.console) {
                console.log('[LiveMap Debug]', ...args);
            }
        }

        function initMap() {
            try {
                // Check if Leaflet is loaded - more robust check
                if (typeof L === 'undefined') {
                    initRetryCount++;
                    if (initRetryCount > MAX_INIT_RETRIES) {
                        console.error('Leaflet failed to load after maximum retries');
                        showError('Map library failed to load. Please refresh the page.');
                        return;
                    }
                    debugLog('Leaflet not loaded yet, retry', initRetryCount);
                    setTimeout(initMap, 200);
                    return;
                }

                const mapContainer = document.getElementById('map');
                if (!mapContainer) {
                    debugLog('Map container not found, retrying...');
                    setTimeout(initMap, 100);
                    return;
                }

                // Clean up existing map instance if present
                if (map !== null) {
                    debugLog('Removing existing map instance');
                    try {
                        map.remove();
                    } catch (e) {
                        console.log('Error removing old map:', e);
                    }
                    map = null;
                }

                // Clear any residual Leaflet state
                if (mapContainer._leaflet_id) {
                    delete mapContainer._leaflet_id;
                }

                const loadingEl = document.getElementById('map-loading');
                if (loadingEl) {
                    loadingEl.classList.add('hidden');
                }

                debugLog('Map center:', {{ $centerLat }}, {{ $centerLng }}, 'Zoom:', {{ $zoomLevel }});
                
                // Initialize map with canvas renderer for better performance
                map = L.map('map', {
                    preferCanvas: true,
                    renderer: L.canvas()
                }).setView([{{ $centerLat }}, {{ $centerLng }}], {{ $zoomLevel }});
                
                debugLog('Map initialized:', map);
                const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 19
                });
                
                // Esri World Imagery (satellite) - free to use
                const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    attribution: 'Tiles © Esri — Source: Esri, Maxar, Earthstar Geographics',
                    maxZoom: 19
                });

                let tileErrorCount = 0;
                function fallbackToOSM() {
                    debugLog('Fallback to OSM due to satellite tile loading errors');
                    if (map.hasLayer(satelliteLayer)) {
                        map.removeLayer(satelliteLayer);
                    }
                    osmLayer.addTo(map);
                    currentLayer = 'osm';
                    showToast('Satellite tiles failed to load. Switched to standard view.');
                }
                
                satelliteLayer.on('tileerror', function() {
                    tileErrorCount++;
                    if (tileErrorCount > 5) {
                        fallbackToOSM();
                    }
                });

                layers = {
                    osm: osmLayer,
                    satellite: satelliteLayer
                };

                const initialLayer = layers[mapStyleData] ? mapStyleData : 'osm';
                if (initialLayer !== mapStyleData) {
                    console.warn('Unknown map style requested, defaulting to standard view.');
                }
                debugLog('Adding initial layer:', initialLayer);
                layers[initialLayer].addTo(map);
                currentLayer = initialLayer;

                debugLog('Adding map controls');
                L.control.zoom({ position: 'bottomright' }).addTo(map);

                debugLog('Machines data:', machinesData.length, 'items');
                debugLog('Show machines:', showMachinesData);
                debugLog('Geofences data:', geofencesData.length, 'items');
                debugLog('Show geofences:', showGeofencesData);
                
                debugLog('Adding machine markers');
                addMachineMarkers();
                debugLog('Adding geofences');
                addGeofences();

                    debugLog('Adding routes');
                    addRoutes();

                // TMP layer — render on mount if toggled on
                if (showTMPData) {
                    addTMPLayer(tmpRoutesData, tmpGeofencesData);
                }

                // Events layer — render on mount
                if (showEventsData && eventsData.length > 0) {
                    addEventMarkers(eventsData);
                }

                // Listen for Livewire events
                window.addEventListener('map-updated', (event) => {
                    debugLog('Livewire map-updated event received', event.detail);
                    updateMap(event.detail[0] || event.detail);
                });

                // TMP layer toggle event
                window.addEventListener('tmp-layer-toggle', (event) => {
                    const payload = event.detail?.[0] ?? event.detail ?? {};
                    showTMPData = !!payload.show;
                    if (payload.routes)    tmpRoutesData    = payload.routes;
                    if (payload.geofences) tmpGeofencesData = payload.geofences;
                    clearTMPLayer();
                    if (showTMPData) addTMPLayer(tmpRoutesData, tmpGeofencesData);
                });

                // Heat map toggle event
                window.addEventListener('heatmap-toggle', (event) => {
                    const payload = event.detail?.[0] ?? event.detail ?? {};
                    showHeatMapData = !!payload.show;
                    clearHeatMap();
                    if (showHeatMapData && Array.isArray(payload.points) && payload.points.length > 0) {
                        addHeatMap(payload.points);
                    }
                    // Show/hide the legend DOM element
                    const legend = document.getElementById('heatmap-legend');
                    if (legend) legend.classList.toggle('hidden', !showHeatMapData);
                });

                // Events layer toggle / filter event
                window.addEventListener('events-layer-toggle', (event) => {
                    const payload = event.detail?.[0] ?? event.detail ?? {};
                    showEventsData = !!payload.show;
                    clearEventMarkers();
                    if (showEventsData && Array.isArray(payload.events) && payload.events.length > 0) {
                        addEventMarkers(payload.events);
                        eventsData = payload.events;
                    }
                });

                // Real-time: new map event pushed via WebSocket (Laravel Echo / Reverb)
                if (window.Echo) {
                    const teamId = document.querySelector('[data-team-id]')?.dataset?.teamId
                        ?? @js(Auth::user()?->currentTeam?->id ?? 0);
                    if (teamId) {
                        window.Echo.private(`team.${teamId}`)
                            .listen('.map-event.recorded', (payload) => {
                                if (!showEventsData) return;
                                if (payload.latitude == null || payload.longitude == null) return;
                                // Append and render the new marker immediately
                                eventsData.unshift(payload);
                                addSingleEventMarker(payload, true);
                            })
                            .listen('.machine.location.updated', (payload) => {
                                // Move an existing machine marker in real time as GPS updates arrive.
                                if (!payload.id || payload.latitude == null || payload.longitude == null) return;
                                const machineId = payload.id;
                                const newLatLng = L.latLng(parseFloat(payload.latitude), parseFloat(payload.longitude));

                                if (markers[machineId] && map) {
                                    // Animate the marker smoothly to its new position.
                                    // Duration is 80% of the poll interval so the animation
                                    // completes before the next update arrives.
                                    const animDuration = Math.round(@js(config('integrations.bell_polling.location_interval_seconds', 300)) * 800);
                                    animateMarkerTo(markers[machineId], newLatLng, animDuration);
                                }

                                // Refresh data store so the next full redraw uses the latest position.
                                const idx = machinesData.findIndex(m => m.id === machineId);
                                if (idx !== -1) {
                                    machinesData[idx].last_location_latitude  = payload.latitude;
                                    machinesData[idx].last_location_longitude = payload.longitude;
                                    if (payload.status) machinesData[idx].status = payload.status;
                                }

                                if (payload.speed != null && payload.speed > 3) {
                                    debugLog('Machine moving', payload.name, 'at', payload.speed, 'km/h');
                                }
                            });
                    }
                }

                // Render heat map on initial load if the toggle was persisted on
                if (showHeatMapData) {
                    // Data must be fetched from server on first render via Livewire poll
                    // Dispatch a synthetic event from Livewire on mount is handled server-side
                }

                debugLog('Map initialization complete');
            } catch (error) {
                console.error('Map initialization error:', error);
                showError('Error initializing map: ' + error.message);
            }
        }
        function showToast(message) {
            const toast = document.getElementById('map-toast');
            if (!toast) return;
            const messageEl = toast.querySelector('.message');
            if (!messageEl) return;
            messageEl.textContent = message;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 4000);
        }
        function showError(message) {
            const loadingEl = document.getElementById('map-loading');
            if (loadingEl) {
                loadingEl.innerHTML = '<div class="text-center"><svg class="w-16 h-16 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg><p class="text-white text-lg mb-2">Map Loading Error</p><p class="text-gray-400 text-sm">' + message + '</p><button id="map-refresh-btn" class="mt-4 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg">Refresh Page</button></div>';
                const refreshBtn = loadingEl.querySelector('#map-refresh-btn');
                if (refreshBtn) {
                    refreshBtn.addEventListener('click', () => location.reload());
                }
            }
        }
        function handleTileError(layerKey, fallbackKey) {
            console.error(layerKey + ' tiles failed to load; falling back to ' + fallbackKey);
            if (!map || !layers[fallbackKey]) return;
            if (layers[layerKey] && map.hasLayer(layers[layerKey])) {
                map.removeLayer(layers[layerKey]);
            }
            layers[fallbackKey].addTo(map);
            currentLayer = fallbackKey;
            showToast('Satellite tiles failed to load. Switched to standard view.');
        }
        
        // Get machine emoji image based on machine type
        function getMachineEmojiImage(machineType) {
            const emojiMap = {
                'excavator': '/machine-emojis/excavator.svg',
                'articulated_hauler': '/machine-emojis/dump-truck.svg',
                'dozer': '/machine-emojis/bulldozer.svg',
                'grader': '/machine-emojis/grader.svg',
                'support_vehicle': '/machine-emojis/service-truck.svg'
            };
            return emojiMap[machineType] || '/machine-emojis/service-truck.svg';
        }
        
        function addMachineMarkers() {
            debugLog('addMachineMarkers called - showMachinesData:', showMachinesData, 'machinesData.length:', machinesData.length);
            if (!showMachinesData || !map) {
                debugLog('Skipping machine markers - showMachinesData:', showMachinesData, 'map:', !!map);
                return;
            }

            machinesData.forEach(machine => {
                try {
                    if (!machine.last_location_latitude || !machine.last_location_longitude) {
                        debugLog('Skipping machine - no coordinates:', machine.name);
                        return;
                    }

                    const lat = parseFloat(machine.last_location_latitude);
                    const lng = parseFloat(machine.last_location_longitude);
                    
                    if (isNaN(lat) || isNaN(lng)) {
                        console.warn('Invalid coordinates for machine:', machine.name, 'lat:', lat, 'lng:', lng);
                        return;
                    }

                    debugLog('Adding machine marker:', machine.name, 'at', lat, lng);

                    const statusClass = {
                        'active': 'machine-status-active',
                        'idle': 'machine-status-idle',
                        'maintenance': 'machine-status-maintenance',
                        'working': 'machine-status-active',
                        'travelling': 'machine-status-active',
                        'idling': 'machine-status-idle',
                        'parked': 'machine-status-idle',
                        'offline': 'machine-status-maintenance',
                    }[machine.status] || 'machine-status-default';

                    const displayStatus = machine.telemetry_status || machine.status?.toUpperCase() || 'UNKNOWN';
                    const fuelLine = machine.fuel_remaining_percent != null
                        ? `<p class="text-xs text-gray-600">⛽ Fuel: <span class="font-semibold ${machine.fuel_remaining_percent < 20 ? 'text-red-600' : ''}">${Math.round(machine.fuel_remaining_percent)}%</span></p>`
                        : '';
                    const hoursLine = machine.telemetry_operating_hours != null
                        ? `<p class="text-xs text-gray-600">⏱ Op. Hours: <span class="font-semibold">${Math.round(machine.telemetry_operating_hours).toLocaleString()} h</span></p>`
                        : '';
                    const loadsLine = machine.telemetry_load_count != null
                        ? `<p class="text-xs text-gray-600">📦 Total Loads: <span class="font-semibold">${machine.telemetry_load_count.toLocaleString()}</span></p>`
                        : '';
                    const lastSeenLine = machine.last_seen_human
                        ? `<p class="text-xs text-gray-500 mt-1">📡 ${machine.last_seen_human}</p>`
                        : '';
                    const engineLine = machine.engine_running != null
                        ? `<p class="text-xs text-gray-600">🔑 Engine: <span class="font-semibold ${machine.engine_running ? 'text-emerald-600' : ''}">${machine.engine_running ? 'Running' : 'Off'}</span></p>`
                        : '';
                    
                    const emojiImageUrl = getMachineEmojiImage(machine.machine_type);

                    const statusIcon = L.divIcon({
                        html: `
                            <div class="machine-status-icon ${statusClass}">
                                <img src="${emojiImageUrl}" class="machine-emoji" alt="Machine" />
                            </div>
                        `,
                        iconSize: [40, 40],
                        className: 'machine-marker'
                    });

                    const marker = L.marker([lat, lng], { icon: statusIcon })
                        .bindPopup(`
                            <div class="p-3 min-w-[180px]">
                                <p class="font-bold text-gray-900">${machine.name}</p>
                                <p class="text-sm text-gray-700 mb-1">${machine.manufacturer || 'Unknown'} ${machine.model || ''}</p>
                                <span class="inline-block px-2 py-0.5 rounded text-white text-xs ${statusClass} mb-2">${displayStatus}</span>
                                ${engineLine}
                                ${fuelLine}
                                ${hoursLine}
                                ${loadsLine}
                                ${lastSeenLine}
                                <a href="/fleet/${machine.id}" class="text-blue-600 hover:underline text-xs mt-2 inline-block">View Details →</a>
                            </div>
                        `)
                        .addTo(map);

                    markers[machine.id] = marker;
                    debugLog('Machine marker added successfully:', machine.name);
                } catch (error) {
                    console.error('Error adding marker for machine:', machine.name, error);
                }
            });
            debugLog('Total markers added:', Object.keys(markers).length);
        }

        function addGeofences() {
            debugLog('addGeofences called - showGeofencesData:', showGeofencesData, 'geofencesData.length:', geofencesData.length);
            if (!showGeofencesData || !map) {
                debugLog('Skipping geofences - showGeofencesData:', showGeofencesData, 'map:', !!map);
                return;
            }

            geofencesData.forEach(geofence => {
                try {
                    if (!geofence.coordinates || geofence.coordinates.length < 3) {
                        debugLog('Skipping geofence - invalid coordinates:', geofence.name, geofence.coordinates);
                        return;
                    }

                    const latlngs = geofence.coordinates
                        .map(coord => {
                            // Handle both array [lat, lng] and object {lat, lng} formats
                            const lat = parseFloat(coord.lat !== undefined ? coord.lat : coord[0]);
                            const lng = parseFloat(coord.lng !== undefined ? coord.lng : coord[1]);
                            return [lat, lng];
                        })
                        .filter(coord => !isNaN(coord[0]) && !isNaN(coord[1]));
                    
                    debugLog('Geofence latlngs:', geofence.name, latlngs);
                    
                    if (latlngs.length < 3) {
                        console.warn('Not enough valid coordinates for geofence:', geofence.name);
                        return;
                    }

                    const polygon = L.polygon(latlngs, {
                        color: '#3b82f6',
                        weight: 2,
                        opacity: 0.7,
                        fillColor: '#3b82f6',
                        fillOpacity: 0.1,
                        className: 'geofence-polygon'
                    })
                        .bindPopup(`
                            <div class="p-3">
                                <p class="font-bold text-gray-900">${geofence.name}</p>
                                <p class="text-xs text-gray-600 mt-1">
                                    Area: ~${((latlngs.length * 50) / 1000).toFixed(2)} sq km
                                </p>
                            </div>
                        `)
                        .addTo(map);

                    geofencePolygons[geofence.id] = polygon;
                    debugLog('Geofence added successfully:', geofence.name);
                } catch (error) {
                    console.error('Error adding geofence:', geofence.name, error);
                }
            });
            debugLog('Total geofences added:', Object.keys(geofencePolygons).length);
            // Also add mine area polygons (if separate from geofences)
            if (Array.isArray(mineAreasData) && mineAreasData.length > 0) {
                mineAreasData.forEach(area => {
                    try {
                        if (!area.coordinates || area.coordinates.length < 3) return;
                        const latlngs = area.coordinates.map(coord => {
                            const lat = parseFloat(coord.lat !== undefined ? coord.lat : coord[0]);
                            const lng = parseFloat(coord.lng !== undefined ? coord.lng : coord[1]);
                            return [lat, lng];
                        }).filter(c => !isNaN(c[0]) && !isNaN(c[1]));
                        if (latlngs.length < 3) return;
                        const polygon = L.polygon(latlngs, {
                            color: '#f59e0b',
                            weight: 2,
                            opacity: 0.8,
                            fillColor: '#f59e0b',
                            fillOpacity: 0.08,
                            className: 'mine-area-polygon'
                        }).bindPopup(`<div class="p-2"><strong>${area.name}</strong></div>`).addTo(map);
                        geofencePolygons['minearea-' + area.id] = polygon;
                    } catch (e) {
                        console.error('Error adding mine area polygon:', area.name, e);
                    }
                });
                debugLog('Total mine area polygons added:', mineAreasData.length);
            }
        }

        function addRoutes() {
            debugLog('addRoutes called - showRoutesData:', showRoutesData, 'routesData.length:', routesData.length);
            if (!showRoutesData || !map) return;

            routesData.forEach(route => {
                try {
                    // Ensure waypoints are sorted by sequence_order (server already orders them, but be safe)
                    const waypoints = (route.waypoints || []).slice().sort((a, b) => a.sequence_order - b.sequence_order);

                    // Build coordinate path: start → waypoints in order → end
                    let coordinates = [];
                    if (route.route_geometry && route.route_geometry.length > 1) {
                        coordinates = route.route_geometry;
                    } else {
                        coordinates.push([route.start_latitude, route.start_longitude]);
                        waypoints.forEach(wp => coordinates.push([wp.latitude, wp.longitude]));
                        coordinates.push([route.end_latitude, route.end_longitude]);
                    }

                    const routeGroup = L.layerGroup();

                    // Draw route polyline
                    const polyline = L.polyline(coordinates, {
                        color: '#8b5cf6',
                        weight: 4,
                        opacity: 0.85,
                        lineJoin: 'round',
                        lineCap: 'round',
                        dashArray: null
                    });
                    const totalTime = route.estimated_time
                        ? Math.floor(route.estimated_time / 60) + 'h ' + (route.estimated_time % 60) + 'm'
                        : '';
                    polyline.bindPopup(
                        '<div class="route-popup">' +
                        '<strong>' + route.name + '</strong><br>' +
                        '<span class="route-popup-meta">📏 ' + (route.total_distance ? route.total_distance.toFixed(1) + ' km' : '') + (totalTime ? ' &bull; ⏱️ ' + totalTime : '') + '</span>' +
                        '</div>'
                    );
                    polyline.addTo(routeGroup);

                    // Start marker (green circle)
                    L.marker([route.start_latitude, route.start_longitude], {
                        icon: L.divIcon({
                            html: '<div class="route-marker route-marker-start"></div>',
                            className: '',
                            iconSize: [16, 16],
                            iconAnchor: [8, 8]
                        }),
                        title: route.name + ' — Start'
                    }).bindPopup('<strong>' + route.name + '</strong><br><span class="route-label-start">● Start</span>').addTo(routeGroup);

                    // Waypoint markers with sequence numbers
                    waypoints.forEach((wp, idx) => {
                        const typeIcons = {
                            fuel_station: '⛽',
                            loading_point: '📦',
                            dump_point: '🚮',
                            geofence: '🚧',
                            standard: '' + (wp.sequence_order || (idx + 1))
                        };
                        const label = typeIcons[wp.waypoint_type] || (wp.sequence_order || (idx + 1));
                        L.marker([wp.latitude, wp.longitude], {
                            icon: L.divIcon({
                                html: '<div class="route-marker route-marker-waypoint">' + label + '</div>',
                                className: '',
                                iconSize: [26, 26],
                                iconAnchor: [13, 13]
                            })
                        }).bindPopup(
                            '<strong>' + (wp.name || 'Waypoint ' + (wp.sequence_order || (idx + 1))) + '</strong>' +
                            '<br>Type: ' + (wp.waypoint_type || 'standard') +
                            (wp.distance_from_previous ? '<br>+' + parseFloat(wp.distance_from_previous).toFixed(1) + ' km' : '') +
                            (wp.estimated_time_from_previous ? ' / ' + wp.estimated_time_from_previous + ' min' : '')
                        ).addTo(routeGroup);
                    });

                    // End marker (red circle)
                    L.marker([route.end_latitude, route.end_longitude], {
                        icon: L.divIcon({
                            html: '<div class="route-marker route-marker-end"></div>',
                            className: '',
                            iconSize: [16, 16],
                            iconAnchor: [8, 8]
                        }),
                        title: route.name + ' — End'
                    }).bindPopup('<strong>' + route.name + '</strong><br><span class="route-label-end">● End</span>').addTo(routeGroup);

                    routeGroup.addTo(map);
                    routeLayers[route.id] = routeGroup;
                    debugLog('Route added to map:', route.name, 'waypoints:', waypoints.length);
                } catch (err) {
                    console.error('Error adding route to map:', route.name, err);
                }
            });
            debugLog('Total route overlays:', Object.keys(routeLayers).length);
        }

        function clearRoutes() {
            if (!map) return;
            Object.values(routeLayers).forEach(group => {
                try { if (map.hasLayer(group)) map.removeLayer(group); } catch (e) {}
            });
            routeLayers = {};
        }

        // ─── Traffic Management Plan layer ───────────────────────────────────────

        // Calculate compass bearing between two lat/lng points (degrees, 0=N, clockwise)
        function calcBearing(lat1, lng1, lat2, lng2) {
            const toRad = d => d * Math.PI / 180;
            const dLng = toRad(lng2 - lng1);
            const y = Math.sin(dLng) * Math.cos(toRad(lat2));
            const x = Math.cos(toRad(lat1)) * Math.sin(toRad(lat2))
                    - Math.sin(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.cos(dLng);
            return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
        }

        function addDirectionalArrows(coords, layerGroup, color) {
            if (!coords || coords.length < 2) return;
            // Place one arrow roughly every 8 coordinate steps (or at midpoint if short)
            const step = Math.max(1, Math.floor(coords.length / Math.max(1, Math.floor(coords.length / 6))));
            for (let i = step; i < coords.length - 1; i += step) {
                const [lat1, lng1] = Array.isArray(coords[i - 1]) ? coords[i - 1] : [coords[i-1].lat, coords[i-1].lng];
                const [lat2, lng2] = Array.isArray(coords[i])     ? coords[i]     : [coords[i].lat,   coords[i].lng];
                if (!isFinite(lat1) || !isFinite(lng1) || !isFinite(lat2) || !isFinite(lng2)) continue;
                const bearing = calcBearing(lat1, lng1, lat2, lng2);
                const arrowSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 18" width="12" height="18"
                    style="transform:rotate(${bearing}deg);display:block;filter:drop-shadow(0 1px 2px rgba(0,0,0,.6))">
                    <polygon points="6,0 12,18 6,13 0,18" fill="${color}" opacity="0.95"/>
                </svg>`;
                L.marker([lat2, lng2], {
                    icon: L.divIcon({ html: arrowSvg, className: '', iconSize: [12, 18], iconAnchor: [6, 9] }),
                    zIndexOffset: 300,
                    interactive: false
                }).addTo(layerGroup);
            }
        }

        function addTMPLayer(routes, geofences) {
            if (!map) return;
            clearTMPLayer();
            tmpLayerGroup = L.layerGroup().addTo(map);

            // 1. Restricted / Safe / Warning zones (color-coded)
            const zoneTypeConfig = {
                restricted : { color: '#ef4444', fill: '#ef4444', fillOpacity: 0.18, dashArray: '6 4', label: '🚫 Restricted' },
                safe       : { color: '#22c55e', fill: '#22c55e', fillOpacity: 0.12, dashArray: null,  label: '✅ Safe Zone'  },
                warning    : { color: '#f59e0b', fill: '#f59e0b', fillOpacity: 0.14, dashArray: '4 4', label: '⚠️ Warning'    },
            };

            (geofences || []).forEach(gf => {
                try {
                    if (!gf.coordinates || gf.coordinates.length < 3) return;
                    const latlngs = gf.coordinates.map(c => {
                        const lat = parseFloat(c.lat !== undefined ? c.lat : c[0]);
                        const lng = parseFloat(c.lng !== undefined ? c.lng : c[1]);
                        return [lat, lng];
                    }).filter(c => isFinite(c[0]) && isFinite(c[1]));
                    if (latlngs.length < 3) return;
                    const type = gf.geofence_type || 'warning';
                    const cfg  = zoneTypeConfig[type] ?? zoneTypeConfig.warning;
                    const polygon = L.polygon(latlngs, {
                        color: cfg.color, weight: 2.5, opacity: 0.9,
                        fillColor: cfg.fill, fillOpacity: cfg.fillOpacity,
                        dashArray: cfg.dashArray
                    }).bindPopup(
                        `<div class="p-2"><strong>${cfg.label}</strong><br><span class="text-sm">${gf.name}</span></div>`
                    );
                    polygon.addTo(tmpLayerGroup);
                } catch(e) { console.warn('TMP zone render error:', gf.name, e); }
            });

            // 2. Defined routes with directional flow arrows
            (routes || []).forEach(route => {
                try {
                    const waypoints = (route.waypoints || []).slice().sort((a, b) => a.sequence_order - b.sequence_order);
                    let coords = [];
                    if (route.route_geometry && route.route_geometry.length > 1) {
                        coords = route.route_geometry;
                    } else {
                        coords.push([route.start_latitude,  route.start_longitude]);
                        waypoints.forEach(wp => coords.push([wp.latitude, wp.longitude]));
                        coords.push([route.end_latitude, route.end_longitude]);
                    }
                    if (coords.length < 2) return;

                    // Route polyline (orange for TMP, distinct from regular violet routes)
                    const polyline = L.polyline(coords, {
                        color: '#f97316', weight: 5, opacity: 0.88,
                        lineJoin: 'round', lineCap: 'round'
                    });
                    const speedInfo = route.speed_limit ? ` • Max ${route.speed_limit} km/h` : '';
                    const dist = route.total_distance ? ` ${parseFloat(route.total_distance).toFixed(1)} km` : '';
                    polyline.bindPopup(
                        `<div class="p-2"><strong>🛣️ ${route.name}</strong><br>` +
                        `<span class="text-xs text-gray-600">TMP Route${dist}${speedInfo}</span></div>`
                    );
                    polyline.addTo(tmpLayerGroup);

                    // Directional flow arrows
                    addDirectionalArrows(coords, tmpLayerGroup, '#f97316');

                    // Speed limit badge at route midpoint
                    if (route.speed_limit) {
                        const midIdx = Math.floor(coords.length / 2);
                        const [mLat, mLng] = Array.isArray(coords[midIdx]) ? coords[midIdx] : [coords[midIdx].lat, coords[midIdx].lng];
                        if (isFinite(mLat) && isFinite(mLng)) {
                            const badge = `<div style="background:#1d4ed8;color:#fff;font-size:10px;font-weight:700;padding:2px 5px;border-radius:4px;border:1.5px solid #fff;white-space:nowrap;box-shadow:0 1px 4px rgba(0,0,0,.4)">${route.speed_limit} km/h</div>`;
                            L.marker([mLat, mLng], {
                                icon: L.divIcon({ html: badge, className: '', iconAnchor: [22, 10] }),
                                zIndexOffset: 500, interactive: false
                            }).addTo(tmpLayerGroup);
                        }
                    }

                    // Start (S) and Finish (F) markers
                    [
                        { lat: route.start_latitude, lng: route.start_longitude, label: 'S', bg: '#22c55e', title: `Start — ${route.name}` },
                        { lat: route.end_latitude,   lng: route.end_longitude,   label: 'F', bg: '#ef4444', title: `Finish — ${route.name}` },
                    ].forEach(pt => {
                        if (!isFinite(pt.lat) || !isFinite(pt.lng)) return;
                        L.marker([pt.lat, pt.lng], {
                            icon: L.divIcon({
                                html: `<div style="width:22px;height:22px;background:${pt.bg};border:2px solid #fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;box-shadow:0 2px 5px rgba(0,0,0,.5)">${pt.label}</div>`,
                                className: '', iconSize: [22, 22], iconAnchor: [11, 11]
                            }),
                            zIndexOffset: 800
                        }).bindPopup(`<strong>${pt.title}</strong>`).addTo(tmpLayerGroup);
                    });
                } catch(e) { console.warn('TMP route render error:', route.name, e); }
            });

            debugLog('TMP layer rendered — zones:', (geofences||[]).length, 'routes:', (routes||[]).length);
        }

        function clearTMPLayer() {
            if (tmpLayerGroup && map && map.hasLayer(tmpLayerGroup)) {
                map.removeLayer(tmpLayerGroup);
            }
            tmpLayerGroup = null;
        }

        // ─── Map Events layer ─────────────────────────────────────────────────

        /**
         * Build an SVG event-pin icon for a given event type.
         *
         * @param {string} color     Hex colour
         * @param {string} emoji     Emoji character
         * @param {boolean} pulse    Whether to animate (new real-time event)
         */
        function buildEventIcon(color, emoji, pulse) {
            const ring = pulse
                ? `<circle cx="16" cy="16" r="14" fill="none" stroke="${color}" stroke-width="2" opacity="0.5">
                       <animate attributeName="r"       values="12;20;12" dur="1.8s" repeatCount="3"/>
                       <animate attributeName="opacity" values="0.6;0;0.6" dur="1.8s" repeatCount="3"/>
                   </circle>`
                : '';

            const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="42" viewBox="0 0 32 42">
                ${ring}
                <defs>
                    <filter id="shadow-ev" x="-30%" y="-30%" width="160%" height="160%">
                        <feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="#000" flood-opacity="0.45"/>
                    </filter>
                </defs>
                <path d="M16 0C7.16 0 0 7.16 0 16c0 11.2 16 26 16 26s16-14.8 16-26C32 7.16 24.84 0 16 0z"
                      fill="${color}" filter="url(#shadow-ev)"/>
                <circle cx="16" cy="16" r="10" fill="rgba(255,255,255,0.2)"/>
                <text x="16" y="21" text-anchor="middle" font-size="13">${emoji}</text>
            </svg>`;

            return L.divIcon({
                html: svg,
                className: '',
                iconSize: [32, 42],
                iconAnchor: [16, 42],
                popupAnchor: [0, -44],
            });
        }

        /**
         * Render a detailed popup for an event marker.
         */
        function buildEventPopup(ev) {
            const cfg      = eventTypeConfig[ev.event_type] ?? { label: 'Event', color: '#94a3b8', emoji: '📍' };
            const when     = ev.occurred_human || new Date(ev.occurred_at).toLocaleString();
            const meta     = ev.metadata && typeof ev.metadata === 'object' ? ev.metadata : {};
            const metaRows = Object.entries(meta)
                .map(([k, v]) => `<tr><td class="pr-2 text-gray-400 capitalize">${k.replace(/_/g,' ')}</td><td class="font-medium text-gray-200">${v}</td></tr>`)
                .join('');

            return `<div style="font-family:system-ui,sans-serif;min-width:200px;max-width:260px;padding:2px 0">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #374151">
                    <span style="font-size:20px">${cfg.emoji}</span>
                    <div>
                        <strong style="font-size:13px;color:#f3f4f6;display:block">${ev.title}</strong>
                        <span style="font-size:10px;background:${cfg.color};color:#fff;
                              padding:1px 7px;border-radius:9999px;font-weight:700">
                            ${cfg.label}
                        </span>
                    </div>
                </div>
                <table style="width:100%;font-size:11px;border-collapse:collapse">
                    <tr>
                        <td style="padding-right:8px;color:#9ca3af">Machine</td>
                        <td style="font-weight:600;color:#e5e7eb">${ev.machine_name || '—'}</td>
                    </tr>
                    <tr>
                        <td style="padding-right:8px;color:#9ca3af">Area</td>
                        <td style="color:#e5e7eb">${ev.mine_area || '—'}</td>
                    </tr>
                    <tr>
                        <td style="padding-right:8px;color:#9ca3af">When</td>
                        <td style="color:#e5e7eb">${when}</td>
                    </tr>
                    ${metaRows}
                </table>
                ${ev.notes ? `<p style="margin-top:6px;font-size:11px;color:#d1d5db;border-top:1px solid #374151;padding-top:5px">${ev.notes}</p>` : ''}
            </div>`;
        }

        /**
         * Add a single event marker to the map.
         *
         * @param {object}  ev     Event data object
         * @param {boolean} pulse  Animate for real-time arrivals
         */
        function addSingleEventMarker(ev, pulse = false) {
            if (!map) return;
            const lat = parseFloat(ev.latitude);
            const lng = parseFloat(ev.longitude);
            if (!isFinite(lat) || !isFinite(lng)) return;

            // Remove stale marker with same id if re-rendering
            if (eventMarkers[ev.id]) {
                try { map.removeLayer(eventMarkers[ev.id]); } catch (_) {}
            }

            const cfg    = eventTypeConfig[ev.event_type] ?? { color: '#94a3b8', emoji: '📍' };
            const marker = L.marker([lat, lng], {
                icon: buildEventIcon(cfg.color, cfg.emoji, pulse),
                zIndexOffset: 200,
            })
            .bindPopup(buildEventPopup(ev), {
                className: 'map-event-popup',
                maxWidth: 260,
            });

            if (!eventLayerGroup) {
                eventLayerGroup = L.layerGroup().addTo(map);
            }
            marker.addTo(eventLayerGroup);
            eventMarkers[ev.id] = marker;
        }

        /**
         * Render all event markers at once.
         */
        function addEventMarkers(events) {
            if (!map) return;
            clearEventMarkers();
            eventLayerGroup = L.layerGroup().addTo(map);
            events.forEach(ev => addSingleEventMarker(ev, false));
            debugLog('[Events] rendered', events.length, 'event markers');
        }

        function clearEventMarkers() {
            if (eventLayerGroup && map && map.hasLayer(eventLayerGroup)) {
                map.removeLayer(eventLayerGroup);
            }
            eventLayerGroup = null;
            eventMarkers    = {};
        }

        /**
         * Pan map to and open popup for a specific event.
         * Called from the inline event feed (Blade side).
         */
        window.mapFocusEvent = function (id) {
            const marker = eventMarkers[id];
            if (!marker || !map) return;
            map.flyTo(marker.getLatLng(), 16, { duration: 0.7 });
            marker.openPopup();
        };

        // ─── Heat Map layer ───────────────────────────────────────────────────────

        /**
         * Render a Leaflet.heat layer from weighted lat/lng points.
         *
         * @param {Array<[number, number, number]>} points  [[lat, lng, weight], ...]
         */
        function addHeatMap(points) {
            if (!map) return;
            clearHeatMap();

            if (typeof L.heatLayer === 'undefined') {
                console.warn('[LiveMap] Leaflet.heat plugin not loaded — heat map unavailable.');
                showToast('Heat map plugin not available.');
                return;
            }

            // Validate & normalise points
            const validPoints = points
                .map(p => [parseFloat(p[0]), parseFloat(p[1]), parseFloat(p[2] ?? 0.5)])
                .filter(p => isFinite(p[0]) && isFinite(p[1]));

            if (validPoints.length === 0) {
                showToast('No location data available for heat map.');
                return;
            }

            heatLayer = L.heatLayer(validPoints, {
                radius: 35,          // influence radius per point (px)
                blur: 25,            // gaussian blur (px)
                maxZoom: 18,
                max: 1.0,
                minOpacity: 0.35,
                gradient: {          // blue → green → yellow → orange → red
                    0.0:  '#0000ff',
                    0.25: '#00aaff',
                    0.5:  '#00ff88',
                    0.7:  '#ffff00',
                    0.85: '#ff8000',
                    1.0:  '#ff0000',
                },
            }).addTo(map);

            debugLog('[HeatMap] rendered', validPoints.length, 'points');
        }

        function clearHeatMap() {
            if (heatLayer && map && map.hasLayer(heatLayer)) {
                map.removeLayer(heatLayer);
            }
            heatLayer = null;
        }

        // ─────────────────────────────────────────────────────────────────────────

        function centerToMineArea(areaId) {
            debugLog('centerToMineArea called with', areaId);

            if (!map) return;

            if (!areaId) {
                // restore full view and all machines
                machinesData = JSON.parse(JSON.stringify(originalMachinesData));
                clearMarkers();
                addMachineMarkers();
                // fit to all markers if any
                const allBounds = L.latLngBounds(Object.values(markers).map(m => m.getLatLng()));
                if (allBounds.isValid()) map.fitBounds(allBounds.pad(0.2));
                return;
            }

            const area = mineAreasData.find(a => String(a.id) === String(areaId));
            if (area && area.coordinates && area.coordinates.length >= 1) {
                const latlngs = area.coordinates.map(coord => {
                    const lat = parseFloat(coord.lat !== undefined ? coord.lat : coord[0]);
                    const lng = parseFloat(coord.lng !== undefined ? coord.lng : coord[1]);
                    return [lat, lng];
                }).filter(c => !isNaN(c[0]) && !isNaN(c[1]));

                if (latlngs.length >= 1) {
                    const polygon = L.polygon(latlngs);
                    const bounds = polygon.getBounds();
                    if (bounds.isValid()) {
                        map.fitBounds(bounds.pad(0.15));
                        showToast('Centered to ' + (area.name || 'selected area'));
                    }
                }
            }

            // Filter machines client-side by mine_area_id if present
            try {
                const filtered = originalMachinesData.filter(m => String(m.mine_area_id) === String(areaId));
                machinesData = filtered;
                clearMarkers();
                addMachineMarkers();

                // If no machines in area, but polygon exists, show polygon only
                if (filtered.length === 0 && area && area.coordinates) {
                    const latlngs = area.coordinates.map(coord => {
                        const lat = parseFloat(coord.lat !== undefined ? coord.lat : coord[0]);
                        const lng = parseFloat(coord.lng !== undefined ? coord.lng : coord[1]);
                        return [lat, lng];
                    }).filter(c => !isNaN(c[0]) && !isNaN(c[1]));
                    if (latlngs.length) {
                        const bounds = L.polygon(latlngs).getBounds();
                        if (bounds.isValid()) map.fitBounds(bounds.pad(0.15));
                    }
                }
            } catch (err) {
                console.error('Error filtering machines by mine area:', err);
            }
        }

        /**
         * Smoothly animate a Leaflet marker from its current position to targetLatLng.
         *
         * Uses requestAnimationFrame with an ease-in-out cubic curve so movement
         * looks natural rather than teleporting.  The animation duration should be
         * set to roughly 80% of the polling interval so each transition completes
         * before the next GPS update arrives.
         *
         * @param {L.Marker} marker      - Leaflet marker to animate.
         * @param {L.LatLng} targetLatLng - Destination coordinates.
         * @param {number}   durationMs  - Animation length in milliseconds (default 4000ms).
         */
        function animateMarkerTo(marker, targetLatLng, durationMs) {
            durationMs = durationMs || 4000;
            const startLatLng = marker.getLatLng();

            // Skip trivial moves (< ~1 metre) to avoid jitter from GPS noise.
            if (
                Math.abs(startLatLng.lat - targetLatLng.lat) < 0.000009 &&
                Math.abs(startLatLng.lng - targetLatLng.lng) < 0.000009
            ) {
                return;
            }

            const startTime = performance.now();

            // Cancel any in-progress animation on this marker.
            if (marker._animRaf) {
                cancelAnimationFrame(marker._animRaf);
                marker._animRaf = null;
            }

            function step(now) {
                const elapsed = now - startTime;
                const t = Math.min(elapsed / durationMs, 1);
                // Ease-in-out cubic: smooth start and end, linear through middle.
                const ease = t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;

                marker.setLatLng([
                    startLatLng.lat + (targetLatLng.lat - startLatLng.lat) * ease,
                    startLatLng.lng + (targetLatLng.lng - startLatLng.lng) * ease,
                ]);

                if (t < 1) {
                    marker._animRaf = requestAnimationFrame(step);
                } else {
                    marker._animRaf = null;
                }
            }

            marker._animRaf = requestAnimationFrame(step);
        }

        /**
         * Incrementally update machine markers from a fresh data array.
         *
         * Unlike clearMarkers+addMachineMarkers (which destroys and re-creates
         * everything), this function:
         *  - Smoothly animates existing markers to new positions.
         *  - Adds markers for newly-appeared machines.
         *  - Removes markers for machines that have gone offline / out of scope.
         *
         * This eliminates the visual flicker of a full rebuild on every wire:poll cycle.
         *
         * @param {Array} newMachinesArray - Array of machine objects from the server.
         * @param {number} animDurationMs  - Animation duration in ms for position updates.
         */
        function updateMachinePositions(newMachinesArray, animDurationMs) {
            animDurationMs = animDurationMs || 4000;
            if (!map || !Array.isArray(newMachinesArray)) return;

            const newIds = new Set(newMachinesArray.map(m => m.id));
            const oldIds = new Set(Object.keys(markers).map(Number));

            // 1. Animate existing markers to their new positions.
            newMachinesArray.forEach(updatedMachine => {
                const id = updatedMachine.id;
                const newLat = parseFloat(updatedMachine.last_location_latitude);
                const newLng = parseFloat(updatedMachine.last_location_longitude);

                if (isNaN(newLat) || isNaN(newLng)) return;

                if (markers[id]) {
                    animateMarkerTo(markers[id], L.latLng(newLat, newLng), animDurationMs);
                }
            });

            // 2. Add markers for machines that weren't in the previous set.
            const addedMachines = newMachinesArray.filter(m => !oldIds.has(m.id));
            if (addedMachines.length > 0) {
                machinesData = [...machinesData, ...addedMachines];
                // Temporarily swap machinesData to only the new ones, render, then restore.
                const saved = machinesData;
                machinesData = addedMachines;
                addMachineMarkers();
                machinesData = saved;
            }

            // 3. Remove markers for machines that disappeared.
            oldIds.forEach(id => {
                if (!newIds.has(id) && markers[id]) {
                    try {
                        if (map.hasLayer(markers[id])) map.removeLayer(markers[id]);
                    } catch (_) {}
                    delete markers[id];
                }
            });

            // 4. Update the data store with the latest values.
            machinesData = newMachinesArray;
        }

        function clearMarkers() {
            if (!map) return;
            Object.values(markers).forEach(marker => {
                try {
                    // Cancel any in-progress animation before removing.
                    if (marker._animRaf) {
                        cancelAnimationFrame(marker._animRaf);
                        marker._animRaf = null;
                    }
                    if (map.hasLayer(marker)) {
                        map.removeLayer(marker);
                    }
                } catch (error) {
                    console.error('Error removing marker:', error);
                }
            });
            markers = {};
        }

        function clearGeofences() {
            if (!map) return;
            Object.values(geofencePolygons).forEach(polygon => {
                try {
                    if (map.hasLayer(polygon)) {
                        map.removeLayer(polygon);
                    }
                } catch (error) {
                    console.error('Error removing geofence:', error);
                }
            });
            geofencePolygons = {};
        }

        function updateMap(data) {
            debugLog('updateMap called with data:', data);
            
            if (!map) {
                debugLog('Map not initialized yet, skipping update');
                return;
            }
            
            // Handle map style changes
            if (data.mapStyle && data.mapStyle !== currentLayer) {
                if (layers[data.mapStyle] && layers[currentLayer]) {
                    debugLog('Switching map style from', currentLayer, 'to', data.mapStyle);
                    try {
                        // Remove current layer if it exists on the map
                        if (map.hasLayer(layers[currentLayer])) {
                            map.removeLayer(layers[currentLayer]);
                        }
                        
                        // Add new layer if it's not already on the map
                        if (!map.hasLayer(layers[data.mapStyle])) {
                            layers[data.mapStyle].addTo(map);
                        }
                        
                        currentLayer = data.mapStyle;
                        tileErrorCount = 0; // Reset error count on manual switch
                        showToast('Map style changed to ' + (data.mapStyle === 'satellite' ? 'Satellite' : 'Standard'));
                    } catch (error) {
                        console.error('Error changing map style:', error);
                        showToast('Failed to change map style');
                        // Try to restore a working layer
                        if (!map.hasLayer(layers['osm']) && !map.hasLayer(layers['satellite'])) {
                            layers['osm'].addTo(map);
                            currentLayer = 'osm';
                        }
                    }
                } else {
                    console.warn('Map style change requested but layer not available, keeping current layer.');
                }
            }
            
            // Handle machines update — use incremental animation instead of clear+rebuild
            // so markers glide smoothly to new positions on every wire:poll cycle.
            if (data.machines !== undefined) {
                try {
                    if (Array.isArray(data.machines) && data.machines.length > 0) {
                        showMachinesData = true;
                        // Animate over 80% of the UI poll interval so transitions
                        // complete before the next server refresh arrives.
                        const pollMs = @js(config('integrations.bell_polling.ui_poll_seconds', 30)) * 800;
                        updateMachinePositions(data.machines, pollMs);
                    } else {
                        clearMarkers();
                        machinesData = [];
                        showMachinesData = false;
                    }
                } catch (error) {
                    console.error('Error updating machines:', error);
                }
            }
            
            // Handle geofences update
            if (data.geofences !== undefined) {
                try {
                    clearGeofences();
                    if (Array.isArray(data.geofences) && data.geofences.length > 0) {
                        geofencesData = data.geofences;
                        showGeofencesData = true;
                        addGeofences();
                    } else {
                        geofencesData = [];
                        showGeofencesData = false;
                    }
                } catch (error) {
                    console.error('Error updating geofences:', error);
                }
            }

                // If server indicated a selected mine area, center the map to it

                // Handle routes update
                if (data.routes !== undefined) {
                    try {
                        clearRoutes();
                        if (Array.isArray(data.routes) && data.routes.length > 0) {
                            routesData = data.routes;
                            showRoutesData = true;
                            addRoutes();
                        } else {
                            routesData = [];
                            showRoutesData = false;
                        }
                    } catch (error) {
                        console.error('Error updating routes:', error);
                    }
                }

                // If server indicated a selected mine area, center the map to it
            if (data.selectedMineAreaId !== undefined && data.selectedMineAreaId !== null) {
                try {
                    centerToMineArea(data.selectedMineAreaId);
                } catch (err) {
                    console.error('Error centering to selected mine area from update:', err);
                }
            }
        }

        // Bind select change to center action (avoid inline handlers so function is resolvable
        // within this closure and to be CSP-friendlier). Added here so `centerToMineArea` is defined.
        const mineAreaSelectEl = document.getElementById('mineAreaSelect');
        if (mineAreaSelectEl) {
            mineAreaSelectEl.addEventListener('change', function(e) {
                try {
                    centerToMineArea(e.target.value);
                } catch (err) {
                    console.error('Error calling centerToMineArea from select change:', err);
                }
            });
        }

        initMap();
    });
    </script>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
    (function injectLiveMapStyles() {
        if (document.getElementById('live-map-styles')) return;
        var s = document.createElement('style');
        s.id = 'live-map-styles';
        s.textContent = [
            '#map{width:100%;height:100%;min-height:50rem;border-radius:0.5rem;box-shadow:0 4px 12px rgba(0,0,0,0.15);background:#111827}',
            '.leaflet-popup-content{font-family:inherit;margin:0;padding:0}',
            '.leaflet-popup-content-wrapper,.leaflet-popup-tip{background-color:white;box-shadow:0 4px 12px rgba(0,0,0,0.15)}',
            '.machine-marker{filter:drop-shadow(0 0 8px rgba(0,0,0,0.5))}',
            '.machine-status-icon{display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:9999px;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.5);padding:4px}',
            '.machine-emoji{width:28px;height:28px;object-fit:contain}',
            '.machine-status-active{background-color:#10b981}.machine-status-idle{background-color:#3b82f6}.machine-status-maintenance{background-color:#ef4444}.machine-status-default{background-color:#6b7280}',
            '.route-popup{min-width:180px}.route-popup-meta{font-size:12px}',
            '.route-marker{border-radius:9999px;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.5)}',
            '.route-marker-start{width:16px;height:16px;background:#22c55e}.route-marker-end{width:16px;height:16px;background:#ef4444}',
            '.route-marker-waypoint{width:26px;height:26px;background:#8b5cf6;box-shadow:0 2px 6px rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:700}',
            '.route-label-start{color:#16a34a}.route-label-end{color:#dc2626}',
            '.geofence-polygon:hover{fill-opacity:0.2!important}',
            '.map-event-popup .leaflet-popup-content-wrapper{background:#1f2937!important;color:#f3f4f6!important;border:1px solid #374151!important;border-radius:8px!important;box-shadow:0 8px 24px rgba(0,0,0,0.5)!important;padding:0!important}',
            '.map-event-popup .leaflet-popup-content{margin:10px 12px!important}',
            '.map-event-popup .leaflet-popup-tip{background:#1f2937!important}',
            '.map-event-popup .leaflet-popup-close-button{color:#9ca3af!important;top:6px!important;right:8px!important}',
            '.map-events-feed::-webkit-scrollbar{width:4px}.map-events-feed::-webkit-scrollbar-track{background:transparent}.map-events-feed::-webkit-scrollbar-thumb{background:#374151;border-radius:9999px}',
            '.leaflet-control-zoom{background-color:rgba(31,41,55,0.9)!important;border:1px solid rgba(107,114,128,0.5)!important;border-radius:8px!important}',
            '.leaflet-control-zoom a{color:white!important;background-color:rgba(55,65,81,0.8)!important;border-bottom:1px solid rgba(107,114,128,0.5)!important}',
            '.leaflet-control-zoom a:hover{background-color:rgba(75,85,99,0.9)!important}',
            '.leaflet-control-zoom a.leaflet-disabled{background-color:rgba(31,41,55,0.5)!important;color:rgba(156,163,175,0.5)!important}',
        ].join('');
        document.head.appendChild(s);
    })();
    </script>
</div>
</div>
