<div class="h-screen flex flex-col bg-slate-900 animate-fade-in">
    <!-- Leaflet CSS - loaded directly in component -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    
    <script>
    (function injectRoutePlanningStyles() {
        if (document.getElementById('route-planning-styles')) return;
        var s = document.createElement('style');
        s.id = 'route-planning-styles';
        s.textContent = [
            '#route-planning-map{background:#1f2937}',
            '#route-planning-map.clickable-mode{cursor:crosshair!important}',
            '#route-planning-map .leaflet-container{background:#1f2937}',
            '.route-marker{border-radius:50%;box-shadow:0 4px 6px rgba(0,0,0,0.3)}',
        ].join('');
        document.head.appendChild(s);
    })();
    </script>
    <script>
    // Fallback: ensure the calculate form calls the Livewire method if submit interception fails
    (function(){
        try {
            const componentId = @json($this->id ?? null);
            if (!componentId) return;
            const livewireComponent = Livewire.find(componentId);
            const calcForm = document.querySelector('form[wire\:submit\.prevent="calculateRoute"]');
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
    <div class="bg-gray-800 border-b border-gray-700 p-6">
        <div class="max-w-7xl mx-auto space-y-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-white">Optimal Route Planning</h1>
                    <p class="text-gray-400 mt-1">Plan efficient routes with fuel and time optimization</p>
                </div>
                <div class="flex gap-2">
                    @if($viewMode === 'view')
                        <button wire:click="switchToCreateMode" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors">
                            Create New Route
                        </button>
                    @endif
                    <a href="{{ route('fleet') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">
                        Back to Fleet
                    </a>
                </div>
            </div>

            <!-- Mode Toggle -->
            <div class="flex gap-2">
                <button wire:click="$set('viewMode', 'create')" 
                    class="px-4 py-2 rounded-lg transition-colors {{ $viewMode === 'create' ? 'bg-amber-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}">
                    Create Route
                </button>
                <button wire:click="$set('viewMode', 'list')" 
                    class="px-4 py-2 rounded-lg transition-colors {{ $viewMode === 'list' ? 'bg-amber-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}">
                    Saved Routes ({{ count($routes) }})
                </button>
            </div>

            <!-- Flash Messages -->
            @if (session()->has('success'))
                <div class="bg-green-600 text-white px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif
            @if (session()->has('error'))
                <div class="bg-red-600 text-white px-4 py-3 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif
        </div>
    </div>

    <div class="flex-1 flex flex-col md:flex-row overflow-hidden">
        <!-- Left Sidebar - Route Form / List -->
        <div class="w-full md:w-96 bg-gray-800 border-b md:border-b-0 md:border-r border-gray-700 overflow-y-auto p-4 md:p-6">
            <!-- Loading Spinner -->
            @if ($isLoading)
                <div class="flex justify-center items-center h-96">
                    <svg class="animate-spin h-12 w-12 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                </div>
                <script nonce="{{ request()->attributes->get('csp_nonce') }}">window.scrollTo(0,0);</script>
            @else
            @if($viewMode === 'create')
                <!-- Create Route Form -->
                <div wire:key="create-form-{{ $viewMode }}">
                <h2 class="text-xl font-bold text-white mb-4">Route Details</h2>
                
                <form wire:submit.prevent="calculateRoute" class="space-y-4">
                    <!-- Route Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Route Name *</label>
                        <input type="text" wire:model="name" 
                            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-amber-500"
                            placeholder="e.g., Loading Zone to Dump Site A">
                        @error('name') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                        <textarea wire:model="description" rows="3"
                            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-amber-500"
                            placeholder="Optional route description"></textarea>
                    </div>

                    <!-- Machine Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Assign to Machine (Optional)</label>
                        <select wire:model="machineId" 
                            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-amber-500">
                            <option value="">-- Select Machine --</option>
                            @foreach($machines as $machine)
                                <option value="{{ $machine->id }}">{{ $machine->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Mine Area Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Mine Area (Optional)</label>
                        <select wire:model="mineAreaId" 
                            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-amber-500">
                            <option value="">-- Select Mine Area --</option>
                            @foreach($mineAreas as $area)
                                <option value="{{ $area->id }}">{{ $area->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Route Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Route Type</label>
                        <select wire:model="routeType" 
                            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-amber-500">
                            <option value="optimal">Optimal (Balanced)</option>
                            <option value="shortest">Shortest Distance</option>
                            <option value="safest">Safest (Avoid Hazards)</option>
                        </select>
                    </div>

                    <!-- Speed Limit -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Speed Limit (km/h)</label>
                        <input type="number" wire:model="speedLimit" min="1" max="200" step="1"
                            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-amber-500"
                            placeholder="e.g., 40">
                        <p class="text-xs text-gray-400 mt-1">Set a speed limit for this route. Alerts will trigger when machines exceed this limit.</p>
                        @error('speedLimit') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Traffic Management Plan -->
                    <div class="border border-gray-700 rounded-lg p-3 bg-gray-700/30 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-white">Traffic Management Plan</h3>
                            <label class="inline-flex items-center gap-2 text-xs text-gray-300">
                                <input type="checkbox" wire:model="tmpEnable" class="rounded border-gray-500 bg-gray-800 text-amber-500 focus:ring-amber-500">
                                Enable TMP
                            </label>
                        </div>

                        @if($tmpEnable)
                            <div class="grid grid-cols-1 gap-2 text-xs text-gray-300">
                                <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="tmpAvoidRestricted" class="rounded border-gray-500 bg-gray-800 text-amber-500 focus:ring-amber-500"> Avoid restricted geofences</label>
                                <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="tmpPreferSafe" class="rounded border-gray-500 bg-gray-800 text-amber-500 focus:ring-amber-500"> Prefer safe corridors</label>
                                <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="tmpEnforceOneWay" class="rounded border-gray-500 bg-gray-800 text-amber-500 focus:ring-amber-500"> Enforce one-way traffic flow</label>
                                <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="tmpApplyAreaSpeedLimits" class="rounded border-gray-500 bg-gray-800 text-amber-500 focus:ring-amber-500"> Apply area speed limits</label>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-[11px] text-gray-400 mb-1">Haul Road</label>
                                    <input type="number" min="1" max="120" wire:model="tmpHaulRoadSpeedLimit"
                                        class="w-full px-2 py-1.5 bg-gray-700 border border-gray-600 rounded text-white text-xs focus:ring-1 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] text-gray-400 mb-1">Loading Zone</label>
                                    <input type="number" min="1" max="80" wire:model="tmpLoadingZoneSpeedLimit"
                                        class="w-full px-2 py-1.5 bg-gray-700 border border-gray-600 rounded text-white text-xs focus:ring-1 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] text-gray-400 mb-1">Shared Zone</label>
                                    <input type="number" min="1" max="80" wire:model="tmpSharedZoneSpeedLimit"
                                        class="w-full px-2 py-1.5 bg-gray-700 border border-gray-600 rounded text-white text-xs focus:ring-1 focus:ring-amber-500">
                                </div>
                            </div>
                            <p class="text-[11px] text-gray-400">These rules are saved in route metadata and can be used by operations monitoring.</p>
                        @endif
                    </div>

                    <div class="border-t border-gray-700 pt-4">
                        <h3 class="text-lg font-semibold text-white mb-3">Start & End Points</h3>
                        <p class="text-sm text-gray-400 mb-3">Click on the map to set start and end points</p>

                        <!-- Start Point -->
                        <div class="bg-gray-700/50 rounded-lg p-3 mb-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-white flex items-center gap-2">
                                    <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                                    Start Point
                                </span>
                            </div>
                            @if($startLat && $startLon)
                                <div class="text-xs text-gray-400">
                                    {{ number_format($startLat, 6) }}, {{ number_format($startLon, 6) }}
                                </div>
                            @else
                                <div class="text-xs text-gray-500">Not set</div>
                            @endif
                        </div>

                        <!-- End Point -->
                        <div class="bg-gray-700/50 rounded-lg p-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-white flex items-center gap-2">
                                    <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                                    End Point
                                </span>
                            </div>
                            @if($endLat && $endLon)
                                <div class="text-xs text-gray-400">
                                    {{ number_format($endLat, 6) }}, {{ number_format($endLon, 6) }}
                                </div>
                            @else
                                <div class="text-xs text-gray-500">Not set</div>
                            @endif
                        </div>
                    </div>

                    <!-- Calculate Button -->
                    <button type="submit" 
                        @if($isCalculating) disabled @endif
                        class="w-full px-4 py-3 bg-amber-600 hover:bg-amber-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium">
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
                            class="w-full px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors font-medium">
                            Clear Points
                        </button>
                    @endif
                </form>

                <!-- Calculated Route Results -->
                @if($showCalculatedRoute && $calculatedRoute)
                    <div class="mt-6 border-t border-gray-700 pt-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Route Summary</h3>
                        
                        <div class="space-y-3">
                            <!-- Distance -->
                            <div class="bg-blue-600/20 border border-blue-600/50 rounded-lg p-3">
                                <div class="text-sm text-blue-300 mb-1">Total Distance</div>
                                <div class="text-2xl font-bold text-white">{{ number_format($calculatedRoute['total_distance'], 2) }} km</div>
                            </div>

                            <!-- Time -->
                            <div class="bg-green-600/20 border border-green-600/50 rounded-lg p-3">
                                <div class="text-sm text-green-300 mb-1">Estimated Time</div>
                                <div class="text-2xl font-bold text-white">
                                    {{ floor($calculatedRoute['estimated_time'] / 60) }}h {{ $calculatedRoute['estimated_time'] % 60 }}m
                                </div>
                            </div>

                            <!-- Fuel -->
                            <div class="bg-yellow-600/20 border border-yellow-600/50 rounded-lg p-3">
                                <div class="text-sm text-yellow-300 mb-1">Estimated Fuel</div>
                                <div class="text-2xl font-bold text-white">{{ number_format($calculatedRoute['estimated_fuel'], 2) }} L</div>
                            </div>

                            <!-- Waypoints -->
                            @if(count($calculatedRoute['waypoints']) > 0)
                                <div class="bg-gray-700/50 rounded-lg p-3">
                                    <div class="text-sm text-gray-300 mb-2">{{ count($calculatedRoute['waypoints']) }} Waypoints</div>
                                    <div class="text-xs text-gray-400">Route optimized to avoid restricted zones</div>
                                </div>
                            @endif

                            @if(!empty($calculatedRoute['traffic_plan']))
                                <div class="bg-indigo-600/20 border border-indigo-600/40 rounded-lg p-3">
                                    <div class="text-sm text-indigo-200 mb-2">Traffic Management Plan</div>
                                    <div class="text-xs text-indigo-100 space-y-1">
                                        <div>Restricted zones: {{ data_get($calculatedRoute, 'traffic_plan.network_context.restricted_zones', 0) }}</div>
                                        <div>Safe zones: {{ data_get($calculatedRoute, 'traffic_plan.network_context.safe_zones', 0) }}</div>
                                        <div>Speed limits (km/h): haul {{ data_get($calculatedRoute, 'traffic_plan.speed_limits.haul_road', 'N/A') }}, loading {{ data_get($calculatedRoute, 'traffic_plan.speed_limits.loading_zone', 'N/A') }}, shared {{ data_get($calculatedRoute, 'traffic_plan.speed_limits.shared_zone', 'N/A') }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Save Button -->
                        @if(!$routeSaved)
                            <button wire:click="saveRoute" 
                                class="w-full mt-4 px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors font-medium">
                                Save This Route
                            </button>
                        @else
                            <div class="mt-4 bg-green-600 text-white px-4 py-3 rounded-lg text-center font-medium">
                                ✓ Route Saved Successfully
                            </div>
                        @endif
                    </div>
                @endif
                </div><!-- End create form wrapper -->

            @elseif($viewMode === 'list')
                <!-- Saved Routes List -->
                <div wire:key="routes-list-{{ count($routes) }}">
                <h2 class="text-xl font-bold text-white mb-4">Saved Routes</h2>
                
                @if(count($routes) === 0)
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">🗺️</div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No routes saved yet</h3>
                        <p class="text-gray-400 mb-2">Start planning your first optimal route to improve efficiency and save fuel.</p>
                        <ul class="text-sm text-gray-500 mb-4 space-y-1">
                            <li>• Click <span class="font-bold text-amber-600">Plan New Route</span> to begin</li>
                            <li>• Set start and end points on the map</li>
                            <li>• Save and view your planned routes here</li>
                        </ul>
                        <button wire:click="switchToCreateMode" class="mt-4 px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white rounded-lg transition-all font-medium">
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
                            <div wire:key="route-{{ $route['id'] }}" class="rounded-lg p-4 transition-colors {{ $isSelected ? 'bg-gray-700 ring-2 ring-amber-400' : 'bg-gray-700 hover:bg-gray-700/80' }}">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="font-semibold text-white">{{ $route['name'] }}</h3>
                                    <span class="px-2 py-1 text-xs rounded {{ $route['status'] === 'active' ? 'bg-green-600' : 'bg-gray-600' }} text-white">
                                        {{ ucfirst($route['status']) }}
                                    </span>
                                </div>
                                
                                @if($route['description'])
                                    <p class="text-sm text-gray-400 mb-3">{{ $route['description'] }}</p>
                                @endif

                                <div class="grid grid-cols-3 gap-2 text-xs text-gray-400 mb-3">
                                    <div>
                                        <span class="block text-gray-500">Distance</span>
                                        <span class="text-white font-medium">{{ number_format($route['total_distance'], 1) }} km</span>
                                    </div>
                                    <div>
                                        <span class="block text-gray-500">Time</span>
                                        <span class="text-white font-medium">{{ floor($route['estimated_time'] / 60) }}h {{ $route['estimated_time'] % 60 }}m</span>
                                    </div>
                                    <div>
                                        <span class="block text-gray-500">Fuel</span>
                                        <span class="text-white font-medium">{{ number_format($route['estimated_fuel'], 1) }} L</span>
                                    </div>
                                </div>

                                @if(data_get($route, 'metadata.traffic_plan.enabled'))
                                    <div class="mb-3 text-[11px] inline-flex items-center px-2 py-1 rounded bg-indigo-900/40 border border-indigo-700 text-indigo-200">
                                        TMP Enabled
                                    </div>
                                @endif

                                <div class="flex gap-2">
                                    <button wire:click="viewRoute({{ $route['id'] }})" 
                                        class="flex-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded transition-colors">
                                        View on Map
                                    </button>
                                    <button wire:click="deleteRoute({{ $route['id'] }})" 
                                        wire:confirm="Are you sure you want to delete this route?"
                                        class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded transition-colors">
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
        <div class="flex-1 relative min-h-[300px] md:min-h-0 bg-gray-800" wire:ignore>
            <!-- Loading indicator -->
            <div id="map-loading" class="absolute inset-0 flex items-center justify-center bg-gray-800 z-[999]" wire:ignore>
                <div class="text-center">
                    <svg class="animate-spin h-12 w-12 text-amber-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-gray-300 text-sm">Loading map...</p>
                </div>
            </div>

            <!-- TMP Layer toggle button (floating, top-right of map) -->
            <div class="absolute top-3 right-3 z-[1000]" wire:ignore>
                <button id="tmp-layer-btn" onclick="window.toggleTMPLayer()"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg shadow-lg transition-all bg-gray-800/90 border border-gray-600 text-gray-300 hover:bg-orange-600 hover:text-white hover:border-orange-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    <span id="tmp-layer-label">TMP Layer: Off</span>
                </button>
            </div>
            
            <div id="route-planning-map" class="w-full h-[300px] md:h-full" style="min-height: 400px;"></div>
                </div>
            </div>
            
            <!-- Map Instructions Overlay -->
            @if($viewMode === 'create' && !$startLat)
                <div class="absolute top-4 left-1/2 transform -translate-x-1/2 bg-gray-800/95 border border-gray-700 rounded-lg px-6 py-3 shadow-lg z-[1000]">
                    <p class="text-white text-sm">
                        <span class="inline-block w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                        Click on the map to set <strong>Start Point</strong>
                    </p>
                </div>
            @elseif($viewMode === 'create' && $startLat && !$endLat)
                <div class="absolute top-4 left-1/2 transform -translate-x-1/2 bg-gray-800/95 border border-gray-700 rounded-lg px-6 py-3 shadow-lg z-[1000]">
                    <p class="text-white text-sm">
                        <span class="inline-block w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                        Click on the map to set <strong>End Point</strong>
                    </p>
                </div>
            @endif
        </div>
    </div>

    <!-- Leaflet JS - loaded directly in component -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-providers/1.13.0/leaflet-providers.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script>
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

        // TMP layer state (client-side toggle, no Livewire roundtrip)
        let tmpActiveRoutes = [];
        let tmpLayerGroup = null;
        let tmpLayerVisible = false;
        try {
            tmpActiveRoutes = @json($tmpActiveRoutes ?? []);
        } catch(e) { tmpActiveRoutes = []; }
        
        // Load geofences data - using inline script to avoid DOMContentLoaded race condition
        try {
            geofences = @json($geofences ?? []);
            console.log('Geofences loaded:', geofences.length);
        } catch(e) {
            console.error('Error loading geofences:', e);
            geofences = [];
        }

        function initializeRoutePlanningMap() {
            // Debug: Check what's available
            console.log('Checking for Leaflet... window.L:', typeof window.L, 'L:', typeof L);
            console.log('Scripts in DOM:', document.querySelectorAll('script[src*="leaflet"]').length);
            
            // Check if Leaflet is loaded (check both window.L and global L)
            if (typeof window.L === 'undefined' && typeof L === 'undefined') {
                initRetryCount++;
                if (initRetryCount > MAX_INIT_RETRIES) {
                    console.error('Leaflet failed to load after maximum retries');
                    console.error('Leaflet script tags found:', document.querySelectorAll('script[src*="leaflet"]'));
                    const loadingEl = document.getElementById('map-loading');
                    if (loadingEl) {
                        loadingEl.innerHTML = '<div class="text-center"><p class="text-red-400 mb-2">Map library failed to load</p><p class="text-gray-400 text-sm">Leaflet library could not be loaded from CDN</p><button onclick="location.reload()" class="mt-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded">Retry</button></div>';
                    }
                    return;
                }
                console.log('Leaflet not loaded yet, retry', initRetryCount);
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
                console.log('Map container not found, retrying...');
                setTimeout(initializeRoutePlanningMap, 100);
                return;
            }
            
            // Check if map is already initialized
            if (map) {
                console.log('Map already initialized');
                return;
            }
            
            console.log('Initializing route planning map...');
            
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
                
                console.log('Map initialized successfully');
            } catch (error) {
                console.error('Error initializing map:', error);
                const loadingEl = document.getElementById('map-loading');
                if (loadingEl) {
                    loadingEl.innerHTML = '<div class="text-center"><p class="text-red-400 mb-2">Failed to load map</p><p class="text-gray-400 text-sm">Please refresh the page</p></div>';
                }
            }

            // Map click handler for setting start/end points
            map.on('click', function(e) {
                console.log('Map clicked at:', e.latlng);
                
                // Get current state from global object
                const viewMode = window.routePlanningState.viewMode;
                const startLat = window.routePlanningState.startLat;
                const endLat = window.routePlanningState.endLat;
                
                console.log('View mode:', viewMode);
                
                if (viewMode === 'create') {
                    console.log('Current startLat:', startLat);
                    
                    if (!startLat) {
                        // Set start point
                        console.log('Setting start point');
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
                        console.log('Current endLat:', endLat);
                        
                        if (!endLat) {
                            // Set end point
                            console.log('Setting end point');
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
                            console.log('Both start and end points are already set');
                        }
                    }
                }
            });

            // Listen for route calculated event
            // Livewire event listener
            Livewire.on('routeCalculated', (routeData) => {
                console.log('Route calculated Livewire event received');
                renderCalculatedRoute(routeData[0] || routeData);
            });
            // DOM event fallback (dispatchBrowserEvent)
            window.addEventListener('routeCalculated', (e) => {
                try {
                    console.log('Route calculated DOM event received');
                    const detail = e.detail || e?.detail || e;
                    renderCalculatedRoute(detail[0] || detail);
                } catch (err) {
                    console.warn('routeCalculated DOM event handler error', err);
                }
            });

            // Listen for view route event
            Livewire.on('viewRoute', (routeData) => {
                console.log('View route Livewire event received');
                renderCalculatedRoute(routeData[0] || routeData);
            });
            window.addEventListener('viewRoute', (e) => {
                try {
                    console.log('View route DOM event received');
                    const detail = e.detail || e?.detail || e;
                    renderCalculatedRoute(detail[0] || detail);
                } catch (err) {
                    console.warn('viewRoute DOM event handler error', err);
                }
            });
            
            // Listen for clear markers event
            function clearMapMarkersHandler() {
                console.log('Clearing map markers');
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
                    console.log('Map size invalidated');
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

        // Initialize map - Leaflet is loaded inline above, so it should be available
        console.log('Checking Leaflet availability:', typeof L);
        if (typeof L !== 'undefined') {
            console.log('Leaflet loaded successfully, initializing map');
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeRoutePlanningMap);
            } else {
                initializeRoutePlanningMap();
            }
        } else {
            console.error('Leaflet not loaded despite inline script tags');
            // Fallback: try waiting a bit
            setTimeout(function() {
                if (typeof L !== 'undefined') {
                    console.log('Leaflet loaded after delay');
                    initializeRoutePlanningMap();
                } else {
                    console.error('Leaflet still not available after delay');
                }
            }, 1000);
        }

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

        // ─── Traffic Management Plan layer (route-planning page) ─────────────────

        function tmpCalcBearing(lat1, lng1, lat2, lng2) {
            const r = d => d * Math.PI / 180;
            const dL = r(lng2 - lng1);
            const y = Math.sin(dL) * Math.cos(r(lat2));
            const x = Math.cos(r(lat1)) * Math.sin(r(lat2)) - Math.sin(r(lat1)) * Math.cos(r(lat2)) * Math.cos(dL);
            return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
        }

        function renderTMPLayer() {
            if (!map) return;
            clearTMPLayer();
            tmpLayerGroup = L.layerGroup().addTo(map);

            // 1. Zone overlays (restricted / safe / warning)
            const zoneCfg = {
                restricted: { color: '#ef4444', fill: '#ef4444', fillOpacity: 0.18, dashArray: '6 4', icon: '🚫' },
                safe:       { color: '#22c55e', fill: '#22c55e', fillOpacity: 0.12, dashArray: null,  icon: '✅' },
                warning:    { color: '#f59e0b', fill: '#f59e0b', fillOpacity: 0.14, dashArray: '4 4', icon: '⚠️' },
            };
            geofences.forEach(gf => {
                try {
                    let coords = typeof gf.coordinates === 'string' ? JSON.parse(gf.coordinates) : gf.coordinates;
                    if (!coords || coords.length < 3) return;
                    const latlngs = coords.map(c => [parseFloat(c.lat ?? c[0]), parseFloat(c.lng ?? c[1])])
                                         .filter(c => isFinite(c[0]) && isFinite(c[1]));
                    if (latlngs.length < 3) return;
                    const type = gf.geofence_type || 'warning';
                    const cfg  = zoneCfg[type] ?? zoneCfg.warning;
                    L.polygon(latlngs, {
                        color: cfg.color, weight: 2.5, opacity: 0.9,
                        fillColor: cfg.fill, fillOpacity: cfg.fillOpacity,
                        dashArray: cfg.dashArray
                    }).bindPopup(`<div class="p-2"><strong>${cfg.icon} ${gf.name}</strong><br><span style="font-size:11px">${type}</span></div>`)
                      .addTo(tmpLayerGroup);
                } catch(e) { console.warn('TMP zone error:', gf.name, e); }
            });

            // 2. Active routes with directional arrows
            tmpActiveRoutes.forEach(route => {
                try {
                    const wps = (route.waypoints || []).slice().sort((a, b) => a.sequence_order - b.sequence_order);
                    let coords = [];
                    if (route.route_geometry && route.route_geometry.length > 1) {
                        coords = route.route_geometry;
                    } else {
                        coords.push([route.start_latitude,  route.start_longitude]);
                        wps.forEach(w => coords.push([w.latitude, w.longitude]));
                        coords.push([route.end_latitude, route.end_longitude]);
                    }
                    if (coords.length < 2) return;

                    // Route polyline (orange = TMP)
                    const polyline = L.polyline(coords, {
                        color: '#f97316', weight: 5, opacity: 0.88,
                        lineJoin: 'round', lineCap: 'round'
                    });
                    const dist      = route.total_distance ? ` ${parseFloat(route.total_distance).toFixed(1)} km` : '';
                    const speedInfo = route.speed_limit    ? ` · Max ${route.speed_limit} km/h` : '';
                    polyline.bindPopup(`<div class="p-2"><strong>🛣️ ${route.name}</strong><br><span style="font-size:11px">TMP Route${dist}${speedInfo}</span></div>`);
                    polyline.addTo(tmpLayerGroup);

                    // Directional arrows along route
                    const step = Math.max(1, Math.floor(coords.length / Math.max(1, Math.floor(coords.length / 6))));
                    for (let i = step; i < coords.length - 1; i += step) {
                        const [lat1, lng1] = Array.isArray(coords[i-1]) ? coords[i-1] : [coords[i-1].lat, coords[i-1].lng];
                        const [lat2, lng2] = Array.isArray(coords[i])   ? coords[i]   : [coords[i].lat,   coords[i].lng];
                        if (!isFinite(lat1) || !isFinite(lng1) || !isFinite(lat2) || !isFinite(lng2)) continue;
                        const bearing = tmpCalcBearing(lat1, lng1, lat2, lng2);
                        const arrowSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 18" width="12" height="18"
                            style="transform:rotate(${bearing}deg);display:block;filter:drop-shadow(0 1px 2px rgba(0,0,0,.6))">
                            <polygon points="6,0 12,18 6,13 0,18" fill="#f97316" opacity="0.95"/></svg>`;
                        L.marker([lat2, lng2], {
                            icon: L.divIcon({ html: arrowSvg, className: '', iconSize: [12, 18], iconAnchor: [6, 9] }),
                            zIndexOffset: 300, interactive: false
                        }).addTo(tmpLayerGroup);
                    }

                    // Speed limit badge at midpoint
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

                    // S / F endpoint markers
                    [
                        { lat: route.start_latitude, lng: route.start_longitude, label: 'S', bg: '#22c55e', title: `Start — ${route.name}` },
                        { lat: route.end_latitude,   lng: route.end_longitude,   label: 'F', bg: '#ef4444', title: `Finish — ${route.name}` },
                    ].forEach(pt => {
                        if (!isFinite(pt.lat) || !isFinite(pt.lng)) return;
                        L.marker([pt.lat, pt.lng], {
                            icon: L.divIcon({
                                html: `<div style="width:22px;height:22px;background:${pt.bg};border:2px solid #fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;box-shadow:0 2px 5px rgba(0,0,0,.5)">${pt.label}</div>`,
                                className: '', iconSize: [22, 22], iconAnchor: [11, 11]
                            }), zIndexOffset: 800
                        }).bindPopup(`<strong>${pt.title}</strong>`).addTo(tmpLayerGroup);
                    });
                } catch(e) { console.warn('TMP route error:', route.name, e); }
            });

            console.log('TMP layer rendered — zones:', geofences.length, 'routes:', tmpActiveRoutes.length);
        }

        function clearTMPLayer() {
            if (tmpLayerGroup && map) {
                try { if (map.hasLayer(tmpLayerGroup)) map.removeLayer(tmpLayerGroup); } catch(e) {}
            }
            tmpLayerGroup = null;
        }

        window.toggleTMPLayer = function() {
            tmpLayerVisible = !tmpLayerVisible;
            if (tmpLayerVisible) {
                renderTMPLayer();
            } else {
                clearTMPLayer();
            }
            const btn   = document.getElementById('tmp-layer-btn');
            const label = document.getElementById('tmp-layer-label');
            if (label) label.textContent = tmpLayerVisible ? 'TMP Layer: On' : 'TMP Layer: Off';
            if (btn) {
                btn.classList.toggle('bg-orange-500',   tmpLayerVisible);
                btn.classList.toggle('text-white',      tmpLayerVisible);
                btn.classList.toggle('border-orange-400', tmpLayerVisible);
                btn.classList.toggle('bg-gray-800/90',  !tmpLayerVisible);
                btn.classList.toggle('text-gray-300',   !tmpLayerVisible);
                btn.classList.toggle('border-gray-600', !tmpLayerVisible);
            }
        };

        // ─────────────────────────────────────────────────────────────────────────

        function renderCalculatedRoute(routeData) {
            console.log('Rendering calculated route:', routeData);
            
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
                console.log('Using OSRM route geometry with', coordinates.length, 'points');
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
                console.log('Using waypoint-based route with', coordinates.length, 'points');
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
                        📏 Distance: <strong>${totalDistance}</strong><br>
                        ⏱️ Time: <strong>${totalTime}</strong><br>
                        ⛽ Fuel: <strong>${totalFuel}</strong>
                    </div>
                </div>
            `);

            // Fit map to route bounds with padding
            map.fitBounds(routeLayer.getBounds(), { padding: [50, 50] });
        }
    </script>
</div>


