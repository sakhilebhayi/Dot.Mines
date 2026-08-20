{{-- relative: the map-instruction overlays are absolute descendants with no
     other positioned ancestor; without this they anchored to the viewport
     and floated over the app topbar. --}}
<div class="h-screen flex flex-col relative bg-[var(--ink)] animate-fade-in">
    <!-- Leaflet CSS - loaded directly in component -->
    
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        /* Map specific styles */
        #route-planning-map {
            background: #1f2937;
        }

        #route-planning-map.clickable-mode {
            cursor: crosshair !important;
        }

        #route-planning-map .leaflet-container {
            background: #1f2937;
        }

        /* Custom marker styles */
        .route-marker {
            border-radius: 50%;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }
    </style>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        // Fallback: ensure the calculate form calls the Livewire method if submit interception fails.
        // This was previously nested inside the <style> tag above, where browsers parse it as inert
        // CSS text and never execute it -- moved out so it actually runs.
        (function(){
            try {
                const componentId = @json($this->id ?? null);
                if (!componentId) return;
                const livewireComponent = Livewire.find(componentId);
                const calcForm = document.querySelector('form[wire\\:submit\\.prevent="calculateRoute"]');
                if (calcForm && livewireComponent) {
                    calcForm.addEventListener('submit', function(e){
                        e.preventDefault();
                        livewireComponent.call('calculateRoute');
                    });
                }
            } catch (e) {
                console.warn('RoutePlanning fallback binding failed', e);
            }
        })();
    </script>

    <!-- Header & Controls -->
    <div class="bg-[var(--ink-soft)] border-b border-[var(--line)] p-6">
        <div class="max-w-7xl mx-auto space-y-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-[var(--stone)]">Optimal Route Planning</h1>
                    <p class="text-[var(--sand)] mt-1">Plan efficient routes with fuel and time optimization</p>
                </div>
                <div class="flex gap-2">
                    @if($viewMode === 'view')
                        <button wire:click="switchToCreateMode" class="px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-colors font-display font-semibold">
                            Create New Route
                        </button>
                    @endif
                    <a href="{{ route('fleet') }}" class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded-lg transition-colors">
                        Back to Fleet
                    </a>
                </div>
            </div>

            <!-- Mode Toggle -->
            <div class="flex gap-2">
                <button wire:click="$set('viewMode', 'create')" 
                    class="px-4 py-2 rounded-lg transition-colors {{ $viewMode === 'create' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 text-[var(--sand)] hover:bg-white/10' }}">
                    Create Route
                </button>
                <button wire:click="$set('viewMode', 'list')" 
                    class="px-4 py-2 rounded-lg transition-colors {{ $viewMode === 'list' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 text-[var(--sand)] hover:bg-white/10' }}">
                    Saved Routes ({{ count($routes) }})
                </button>
            </div>

            <!-- Flash Messages -->
            @if (session()->has('success'))
                <div class="bg-green-600 text-[var(--stone)] px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif
            @if (session()->has('error'))
                <div class="bg-red-600 text-[var(--stone)] px-4 py-3 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif
        </div>
    </div>

    <div class="flex-1 flex flex-col md:flex-row overflow-hidden">
        <!-- Left Sidebar - Route Form / List -->
        <div class="w-full md:w-96 bg-[var(--ink-soft)] border-b md:border-b-0 md:border-r border-[var(--line)] overflow-y-auto p-4 md:p-6">
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
            @if($viewMode === 'create')
                <!-- Create Route Form -->
                <div wire:key="create-form-{{ $viewMode }}">
                <h2 class="text-xl font-bold text-[var(--stone)] mb-4">Route Details</h2>
                
                <form wire:submit.prevent="calculateRoute" class="space-y-4">
                    <!-- Route Name -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Route Name *</label>
                        <input type="text" wire:model="name" 
                            class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)]"
                            placeholder="e.g., Loading Zone to Dump Site A">
                        @error('name') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Description</label>
                        <textarea wire:model="description" rows="3"
                            class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)]"
                            placeholder="Optional route description"></textarea>
                    </div>

                    <!-- Machine Selection -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Assign to Machine (Optional)</label>
                        <select wire:model="machineId" 
                            class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)]">
                            <option value="">-- Select Machine --</option>
                            @foreach($machines as $machine)
                                <option value="{{ $machine->id }}">{{ $machine->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Mine Area Selection -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Mine Area (Optional)</label>
                        <select wire:model="mineAreaId" 
                            class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)]">
                            <option value="">-- Select Mine Area --</option>
                            @foreach($mineAreas as $area)
                                <option value="{{ $area->id }}">{{ $area->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Route Type -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Route Type</label>
                        <select wire:model="routeType" 
                            class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)]">
                            <option value="optimal">Optimal (Balanced)</option>
                            <option value="shortest">Shortest Distance</option>
                            <option value="safest">Safest (Avoid Hazards)</option>
                        </select>
                    </div>

                    <!-- Speed Limit -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Speed Limit (km/h)</label>
                        <input type="number" wire:model="speedLimit" min="1" max="200" step="1"
                            class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)]"
                            placeholder="e.g., 40">
                        <p class="text-xs text-[var(--sand)] mt-1">Set a speed limit for this route. Alerts will trigger when machines exceed this limit.</p>
                        @error('speedLimit') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="border-t border-[var(--line)] pt-4">
                        <h3 class="text-lg font-semibold text-[var(--stone)] mb-3">Start & End Points</h3>
                        <p class="text-sm text-[var(--sand)] mb-3">Click on the map to set start and end points</p>

                        <!-- Start Point -->
                        <div class="bg-white/5 rounded-lg p-3 mb-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-[var(--stone)] flex items-center gap-2">
                                    <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                                    Start Point
                                </span>
                            </div>
                            @if($startLat && $startLon)
                                <div class="text-xs text-[var(--sand)]">
                                    {{ number_format($startLat, 6) }}, {{ number_format($startLon, 6) }}
                                </div>
                            @else
                                <div class="text-xs text-[var(--sand)]">Not set</div>
                            @endif
                        </div>

                        <!-- End Point -->
                        <div class="bg-white/5 rounded-lg p-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-[var(--stone)] flex items-center gap-2">
                                    <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                                    End Point
                                </span>
                            </div>
                            @if($endLat && $endLon)
                                <div class="text-xs text-[var(--sand)]">
                                    {{ number_format($endLat, 6) }}, {{ number_format($endLon, 6) }}
                                </div>
                            @else
                                <div class="text-xs text-[var(--sand)]">Not set</div>
                            @endif
                        </div>
                    </div>

                    <!-- Calculate Button -->
                    <button type="submit" 
                        @if($isCalculating) disabled @endif
                        class="w-full px-4 py-3 bg-[var(--gold)] hover:bg-[var(--gold-soft)] disabled:bg-white/10 disabled:cursor-not-allowed text-[var(--ink)] rounded-lg transition-colors font-display font-semibold">
                        @if($isCalculating)
                            <span class="flex items-center justify-center gap-2">
                                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Calculating...
                            </span>
                        @else
                            Calculate Optimal Route
                        @endif
                    </button>
                    
                    @if($startLat || $endLat)
                        <button type="button" wire:click="clearPoints"
                            class="w-full px-4 py-2 bg-white/10 hover:bg-white/15 border border-[var(--line)] text-[var(--stone)] rounded-lg transition-colors font-medium">
                            Clear Points
                        </button>
                    @endif
                </form>

                <!-- Calculated Route Results -->
                @if($showCalculatedRoute && $calculatedRoute)
                    <div class="mt-6 border-t border-[var(--line)] pt-6">
                        <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">Route Summary</h3>
                        
                        <div class="space-y-3">
                            <!-- Distance -->
                            <div class="bg-blue-600/20 border border-blue-600/50 rounded-lg p-3">
                                <div class="text-sm text-blue-300 mb-1">Total Distance</div>
                                <div class="text-2xl font-bold text-[var(--stone)]">{{ number_format($calculatedRoute['total_distance'], 2) }} km</div>
                            </div>

                            <!-- Time -->
                            <div class="bg-green-600/20 border border-green-600/50 rounded-lg p-3">
                                <div class="text-sm text-green-300 mb-1">Estimated Time</div>
                                <div class="text-2xl font-bold text-[var(--stone)]">
                                    {{ floor($calculatedRoute['estimated_time'] / 60) }}h {{ $calculatedRoute['estimated_time'] % 60 }}m
                                </div>
                            </div>

                            <!-- Fuel -->
                            <div class="bg-yellow-600/20 border border-yellow-600/50 rounded-lg p-3">
                                <div class="text-sm text-yellow-300 mb-1">Estimated Fuel</div>
                                <div class="text-2xl font-bold text-[var(--stone)]">{{ number_format($calculatedRoute['estimated_fuel'], 2) }} L</div>
                            </div>

                            <!-- Waypoints -->
                            @if(count($calculatedRoute['waypoints']) > 0)
                                <div class="bg-white/5 rounded-lg p-3">
                                    <div class="text-sm text-[var(--sand)] mb-2">{{ count($calculatedRoute['waypoints']) }} Waypoints</div>
                                    <div class="text-xs text-[var(--sand)]">Route optimized to avoid restricted zones</div>
                                </div>
                            @endif
                        </div>

                        <!-- Save Button -->
                        @if(!$routeSaved)
                            <button wire:click="saveRoute" 
                                class="w-full mt-4 px-4 py-3 bg-green-600 hover:bg-green-700 text-[var(--stone)] rounded-lg transition-colors font-medium">
                                Save This Route
                            </button>
                        @else
                            <div class="mt-4 bg-green-600 text-[var(--stone)] px-4 py-3 rounded-lg text-center font-medium">
                                ✓ Route Saved Successfully
                            </div>
                        @endif
                    </div>
                @endif
                </div><!-- End create form wrapper -->

            @elseif($viewMode === 'list')
                <!-- Saved Routes List -->
                <div wire:key="routes-list-{{ count($routes) }}">
                <h2 class="text-xl font-bold text-[var(--stone)] mb-4">Saved Routes</h2>
                
                @if(count($routes) === 0)
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">🗺️</div>
                        <h3 class="text-lg font-semibold text-[var(--stone)] mb-2">No routes saved yet</h3>
                        <p class="text-[var(--sand)] mb-2">Start planning your first optimal route to improve efficiency and save fuel.</p>
                        <ul class="text-sm text-[var(--sand)] mb-4 space-y-1">
                            <li>• Click <span class="font-bold text-[var(--gold)]">Plan New Route</span> to begin</li>
                            <li>• Set start and end points on the map</li>
                            <li>• Save and view your planned routes here</li>
                        </ul>
                        <button wire:click="switchToCreateMode" class="mt-4 px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-all font-display font-semibold">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Create Your First Route
                            </span>
                        </button>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($routes as $route)
                            @php $isSelected = isset($selectedRouteId) && $selectedRouteId == $route['id']; @endphp
                            <div wire:key="route-{{ $route['id'] }}" class="rounded-lg p-4 transition-colors {{ $isSelected ? 'bg-white/10 ring-2 ring-[var(--gold)]' : 'bg-white/5 hover:bg-white/10' }}">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="font-semibold text-[var(--stone)]">{{ $route['name'] }}</h3>
                                    <span class="px-2 py-1 text-xs rounded {{ $route['status'] === 'active' ? 'bg-green-600' : 'bg-white/10' }} text-[var(--stone)]">
                                        {{ ucfirst($route['status']) }}
                                    </span>
                                </div>
                                
                                @if($route['description'])
                                    <p class="text-sm text-[var(--sand)] mb-3">{{ $route['description'] }}</p>
                                @endif

                                <div class="grid grid-cols-3 gap-2 text-xs text-[var(--sand)] mb-3">
                                    <div>
                                        <span class="block text-[var(--sand)]">Distance</span>
                                        <span class="text-[var(--stone)] font-medium">{{ number_format($route['total_distance'], 1) }} km</span>
                                    </div>
                                    <div>
                                        <span class="block text-[var(--sand)]">Time</span>
                                        <span class="text-[var(--stone)] font-medium">{{ floor($route['estimated_time'] / 60) }}h {{ $route['estimated_time'] % 60 }}m</span>
                                    </div>
                                    <div>
                                        <span class="block text-[var(--sand)]">Fuel</span>
                                        <span class="text-[var(--stone)] font-medium">{{ number_format($route['estimated_fuel'], 1) }} L</span>
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <button wire:click="viewRoute({{ $route['id'] }})" 
                                        class="flex-1 px-3 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] text-sm rounded transition-colors font-medium">
                                        View on Map
                                    </button>
                                    <button wire:click="deleteRoute({{ $route['id'] }})" 
                                        wire:confirm="Are you sure you want to delete this route?"
                                        class="px-3 py-2 bg-red-600 hover:bg-red-700 text-[var(--stone)] text-sm rounded transition-colors">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                </div><!-- End routes list wrapper -->
            @endif
            @endif
        </div>

        <!-- Map Container -->
        <div class="flex-1 relative min-h-[300px] md:min-h-0 bg-[var(--ink-soft)]" wire:ignore>
            <!-- Loading indicator -->
            <div id="map-loading" class="absolute inset-0 flex items-center justify-center bg-[var(--ink-soft)] z-[999]" wire:ignore>
                <div class="text-center">
                    <svg class="animate-spin h-12 w-12 text-[var(--gold)] mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-[var(--sand)] text-sm">Loading map...</p>
                </div>
            </div>
            
            <div id="route-planning-map" class="w-full h-[300px] md:h-full" style="min-height: 400px;"></div>
                </div>
            </div>
            
            <!-- Map Instructions Overlay -->
            @if($viewMode === 'create' && !$startLat)
                <div class="absolute top-4 left-1/2 transform -translate-x-1/2 bg-[var(--ink-soft)]/95 border border-[var(--line)] rounded-lg px-6 py-3 shadow-lg z-[1000]">
                    <p class="text-[var(--stone)] text-sm">
                        <span class="inline-block w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                        Click on the map to set <strong>Start Point</strong>
                    </p>
                </div>
            @elseif($viewMode === 'create' && $startLat && !$endLat)
                <div class="absolute top-4 left-1/2 transform -translate-x-1/2 bg-[var(--ink-soft)]/95 border border-[var(--line)] rounded-lg px-6 py-3 shadow-lg z-[1000]">
                    <p class="text-[var(--stone)] text-sm">
                        <span class="inline-block w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                        Click on the map to set <strong>End Point</strong>
                    </p>
                </div>
            @endif
        </div>
    </div>

    <!-- Leaflet JS - loaded directly in component -->

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        // Initialize with safe defaults
        window.routePlanningState = {
            viewMode: 'create',
            startLat: null,
            endLat: null,
            centerLat: -26.2041,
            centerLng: 28.0473,
            zoomLevel: 10
        };

        let map, startMarker, endMarker, routeLayer, waypointsLayer;
        let geofences = [];
        let geofenceLayerGroup;
        let initRetryCount = 0;
        const MAX_INIT_RETRIES = 50;
        
        // Load geofences data - using inline script to avoid DOMContentLoaded race condition
        try {
            geofences = @json($geofences ?? []);
        } catch(e) {
            console.error('Error loading geofences:', e);
            geofences = [];
        }

        function initializeRoutePlanningMap() {
            // Debug: Check what's available
            
            // Check if Leaflet is loaded (check both window.L and global L)
            if (typeof window.L === 'undefined' && typeof L === 'undefined') {
                initRetryCount++;
                if (initRetryCount > MAX_INIT_RETRIES) {
                    console.error('Leaflet failed to load after maximum retries');
                    console.error('Leaflet script tags found:', document.querySelectorAll('script[src*="leaflet"]'));
                    const loadingEl = document.getElementById('map-loading');
                    if (loadingEl) {
                        loadingEl.innerHTML = '<div class="text-center"><p class="text-red-400 mb-2">Map library failed to load</p><p class="text-[var(--sand)] text-sm">Leaflet library could not be loaded from CDN</p><button onclick="location.reload()" class="mt-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-[var(--stone)] rounded">Retry</button></div>';
                    }
                    return;
                }
                setTimeout(initializeRoutePlanningMap, 200);
                return;
            }
            
            // Use window.L to ensure we have the right reference
            if (typeof L === 'undefined' && typeof window.L !== 'undefined') {
                window.L = window.L; // Make sure L is available globally
            }
            
            // Check if map container exists
            const mapContainer = document.getElementById('route-planning-map');
            if (!mapContainer) {
                setTimeout(initializeRoutePlanningMap, 100);
                return;
            }
            
            // Check if map is already initialized
            if (map) {
                return;
            }
            
            
            try {
                // Initialize map
                map = L.map('route-planning-map').setView([window.routePlanningState.centerLat, window.routePlanningState.centerLng], window.routePlanningState.zoomLevel);

                // Add tile layers
                const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                });

                const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19,
                    attribution: 'Esri, Maxar, Earthstar Geographics'
                });

                osmLayer.addTo(map);

                // Layer control
                L.control.layers({
                    'Standard': osmLayer,
                    'Satellite': satelliteLayer
                }).addTo(map);

                // Add geofences to map
                geofenceLayerGroup = L.layerGroup().addTo(map);
                renderGeofences();
                
                // Hide loading indicator
                const loadingEl = document.getElementById('map-loading');
                if (loadingEl) {
                    loadingEl.style.display = 'none';
                }
                
            } catch (error) {
                console.error('Error initializing map:', error);
                const loadingEl = document.getElementById('map-loading');
                if (loadingEl) {
                    loadingEl.innerHTML = '<div class="text-center"><p class="text-red-400 mb-2">Failed to load map</p><p class="text-[var(--sand)] text-sm">Please refresh the page</p></div>';
                }
            }

            // Map click handler for setting start/end points
            map.on('click', function(e) {
                
                // Get current state from global object
                const viewMode = window.routePlanningState.viewMode;
                const startLat = window.routePlanningState.startLat;
                const endLat = window.routePlanningState.endLat;
                
                
                if (viewMode === 'create') {
                    
                    if (!startLat) {
                        // Set start point
                        @this.set('startLat', e.latlng.lat);
                        @this.set('startLon', e.latlng.lng);
                        
                        // Update global state
                        window.routePlanningState.startLat = e.latlng.lat;
                        
                        if (startMarker) {
                            map.removeLayer(startMarker);
                        }
                        
                        startMarker = L.marker([e.latlng.lat, e.latlng.lng], {
                            icon: L.divIcon({
                                html: '<div style="background-color: #22c55e; width: 24px; height: 24px; border-radius: 50%; border: 4px solid white; box-shadow: 0 4px 6px rgba(0,0,0,0.3);"></div>',
                                className: '',
                                iconSize: [24, 24],
                                iconAnchor: [12, 12]
                            }),
                            title: 'Start Point'
                        }).addTo(map);
                        
                        startMarker.bindPopup('<strong>Start Point</strong><br>Lat: ' + e.latlng.lat.toFixed(6) + '<br>Lng: ' + e.latlng.lng.toFixed(6)).openPopup();
                    } else {
                        
                        if (!endLat) {
                            // Set end point
                            @this.set('endLat', e.latlng.lat);
                            @this.set('endLon', e.latlng.lng);
                            
                            // Update global state
                            window.routePlanningState.endLat = e.latlng.lat;
                            
                            if (endMarker) {
                                map.removeLayer(endMarker);
                            }
                            
                            endMarker = L.marker([e.latlng.lat, e.latlng.lng], {
                                icon: L.divIcon({
                                    html: '<div style="background-color: #ef4444; width: 24px; height: 24px; border-radius: 50%; border: 4px solid white; box-shadow: 0 4px 6px rgba(0,0,0,0.3);"></div>',
                                    className: '',
                                    iconSize: [24, 24],
                                    iconAnchor: [12, 12]
                                }),
                                title: 'End Point'
                            }).addTo(map);
                            
                            endMarker.bindPopup('<strong>End Point</strong><br>Lat: ' + e.latlng.lat.toFixed(6) + '<br>Lng: ' + e.latlng.lng.toFixed(6)).openPopup();
                        } else {
                        }
                    }
                }
            });

            // Listen for route calculated event
            // Livewire event listener
            Livewire.on('routeCalculated', (routeData) => {
                renderCalculatedRoute(routeData[0] || routeData);
            });
            // DOM event fallback (dispatchBrowserEvent)
            window.addEventListener('routeCalculated', (e) => {
                try {
                    const detail = e.detail || e?.detail || e;
                    renderCalculatedRoute(detail[0] || detail);
                } catch (err) {
                    console.warn('routeCalculated DOM event handler error', err);
                }
            });

            // Listen for view route event
            Livewire.on('viewRoute', (routeData) => {
                renderCalculatedRoute(routeData[0] || routeData);
            });
            window.addEventListener('viewRoute', (e) => {
                try {
                    const detail = e.detail || e?.detail || e;
                    renderCalculatedRoute(detail[0] || detail);
                } catch (err) {
                    console.warn('viewRoute DOM event handler error', err);
                }
            });
            
            // Listen for clear markers event
            function clearMapMarkersHandler() {
                if (startMarker) {
                    map.removeLayer(startMarker);
                    startMarker = null;
                }
                if (endMarker) {
                    map.removeLayer(endMarker);
                    endMarker = null;
                }
                if (routeLayer) {
                    map.removeLayer(routeLayer);
                    routeLayer = null;
                }
                if (waypointsLayer) {
                    map.removeLayer(waypointsLayer);
                    waypointsLayer = null;
                }
                // Reset global state
                window.routePlanningState.startLat = null;
                window.routePlanningState.endLat = null;
            }
            Livewire.on('clearMapMarkers', clearMapMarkersHandler);
            window.addEventListener('clearMapMarkers', clearMapMarkersHandler);
            
            // Trigger map resize after a short delay to ensure proper rendering
            setTimeout(() => {
                if (map) {
                    map.invalidateSize();
                }
            }, 250);
            
            // Listen for view mode changes to update cursor style
            Livewire.on('viewModeChanged', (mode) => {
                const mapContainer = document.getElementById('route-planning-map');
                if (mapContainer) {
                    if (mode === 'create') {
                        mapContainer.classList.add('clickable-mode');
                    } else {
                        mapContainer.classList.remove('clickable-mode');
                    }
                }
            });
        }

        // Leaflet ships in the app's module bundle (window.L), and module
        // scripts execute AFTER this inline script parses -- so waiting is
        // the normal path, not an error. Poll briefly; only report a real
        // failure if the bundle never arrives.
        (function waitForLeaflet(attempt) {
            if (typeof L !== 'undefined') {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initializeRoutePlanningMap);
                } else {
                    initializeRoutePlanningMap();
                }

                return;
            }

            if (attempt >= 100) {
                console.error('Leaflet bundle never became available; map cannot initialize.');

                return;
            }

            setTimeout(function () { waitForLeaflet(attempt + 1); }, 100);
        })(0);

        function renderGeofences() {
            if (!geofenceLayerGroup) return;
            
            geofenceLayerGroup.clearLayers();
            
            geofences.forEach(geofence => {
                try {
                    // Handle coordinates - might already be parsed or need parsing
                    let coords;
                    if (typeof geofence.coordinates === 'string') {
                        coords = JSON.parse(geofence.coordinates);
                    } else {
                        coords = geofence.coordinates;
                    }
                    
                    if (coords && coords.length > 0) {
                        const latlngs = coords.map(c => [c.lat, c.lng]);
                        
                        const color = geofence.geofence_type === 'restricted' ? '#ef4444' : 
                                      geofence.geofence_type === 'safe' ? '#22c55e' : '#f59e0b';
                        
                        const polygon = L.polygon(latlngs, {
                            color: color,
                            fillColor: color,
                            fillOpacity: 0.2,
                            weight: 2
                        }).addTo(geofenceLayerGroup);
                        
                        polygon.bindPopup(`<strong>${geofence.name}</strong><br>${geofence.geofence_type}`);
                    }
                } catch (e) {
                    console.error('Error rendering geofence:', geofence.name, e);
                }
            });
        }

        function renderCalculatedRoute(routeData) {
            
            // Clear existing route
            if (routeLayer) {
                map.removeLayer(routeLayer);
            }
            if (waypointsLayer) {
                map.removeLayer(waypointsLayer);
            }

            // Build coordinates array from route geometry if available (road-following route)
            let coordinates = [];
            
            if (routeData.route_geometry && routeData.route_geometry.length > 0) {
                // Use full route geometry from OSRM for road-following paths
                coordinates = routeData.route_geometry;
            } else {
                // Fallback: Build coordinates from start, waypoints, and end
                coordinates = [[routeData.start_latitude, routeData.start_longitude]];

                // Add waypoints
                if (routeData.waypoints && routeData.waypoints.length > 0) {
                    routeData.waypoints.forEach(wp => {
                        coordinates.push([wp.latitude, wp.longitude]);
                    });
                }

                // Add end point
                coordinates.push([routeData.end_latitude, routeData.end_longitude]);
            }

            // Draw route polyline with smooth curves
            routeLayer = L.polyline(coordinates, {
                color: '#fbbf24',
                weight: 5,
                opacity: 0.9,
                lineJoin: 'round',
                lineCap: 'round',
                smoothFactor: 1.0
            }).addTo(map);

            // Add waypoint markers (only for sampled waypoints, not every route point)
            waypointsLayer = L.layerGroup().addTo(map);
            
            if (routeData.waypoints && routeData.waypoints.length > 0) {
                routeData.waypoints.forEach((wp, index) => {
                    const marker = L.marker([wp.latitude, wp.longitude], {
                        icon: L.divIcon({
                            html: `<div style="background-color: #3b82f6; width: 28px; height: 28px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white; font-size: 11px; font-weight: bold;">${index + 1}</div>`,
                            className: '',
                            iconSize: [28, 28],
                            iconAnchor: [14, 14]
                        })
                    }).addTo(waypointsLayer);
                    
                    const distance = wp.distance_from_previous ? wp.distance_from_previous.toFixed(1) + ' km' : '';
                    const time = wp.estimated_time_from_previous ? wp.estimated_time_from_previous + ' min' : '';
                    const details = [distance, time].filter(d => d).join(' • ');
                    
                    marker.bindPopup(`<strong>${wp.name || 'Waypoint ' + (index + 1)}</strong><br>Type: ${wp.waypoint_type || 'standard'}${details ? '<br>' + details : ''}`);
                });
            }

            // Add distance/time info popup to route
            const totalDistance = routeData.total_distance ? routeData.total_distance.toFixed(1) + ' km' : '';
            const totalTime = routeData.estimated_time ? Math.floor(routeData.estimated_time / 60) + 'h ' + (routeData.estimated_time % 60) + 'm' : '';
            const totalFuel = routeData.estimated_fuel ? routeData.estimated_fuel.toFixed(1) + ' L' : '';
            
            routeLayer.bindPopup(`
                <div style="min-width: 200px;">
                    <strong>Route Summary</strong><br>
                    <div style="margin-top: 8px; font-size: 13px;">
                        Distance: <strong>${totalDistance}</strong><br>
                        ⏱️ Time: <strong>${totalTime}</strong><br>
                        Fuel: <strong>${totalFuel}</strong>
                    </div>
                </div>
            `);

            // Fit map to route bounds with padding
            map.fitBounds(routeLayer.getBounds(), { padding: [50, 50] });
        }
    </script>
</div>

