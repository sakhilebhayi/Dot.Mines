<div>
<div class="h-screen flex flex-col bg-[var(--ink)]" 
     data-path-coords="{{ json_encode($pathCoordinates ?? []) }}"
     data-geofences="{{ json_encode($geofences ?? []) }}"
     data-routes="{{ json_encode($routes ?? []) }}"
     data-machine-type="{{ $selectedMachineDetails->machine_type ?? '' }}">
    <!-- Leaflet CSS - loaded directly in component -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        /* Map specific styles */
        #replay-map {
            background: #1f2937;
        }
        
        #replay-map .leaflet-container {
            background: #1f2937;
            height: 100%;
            width: 100%;
        }
    </style>
    
    <!-- Header -->
    <div class="bg-[var(--ink-soft)] border-b border-[var(--line)] p-6">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-[var(--stone)]">Fleet Movement Replay</h1>
                    <p class="text-[var(--sand)] mt-1">Review historical vehicle movements and routes</p>
                </div>
                <a href="{{ route('fleet') }}" class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded-lg transition-colors">
                    Back to Fleet
                </a>
            </div>
        </div>
    </div>

    <div class="flex-1 flex flex-col md:flex-row overflow-hidden">
        <!-- Left Sidebar - Controls -->
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
            <!-- Machine Selection -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-[var(--sand)] mb-2">Select Machine</label>
                <select wire:model.live="selectedMachine" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)]">
                    <option value="">-- Choose a Machine --</option>
                    @foreach($machines as $machineType => $machineGroup)
                        <optgroup label="{{ strtoupper(str_replace('_', ' ', $machineType)) }}">
                            @foreach($machineGroup as $machine)
                                <option value="{{ $machine->id }}">{{ $machine->name }} ({{ $machine->manufacturer }})</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <!-- Date Range -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-[var(--sand)] mb-2">Start Date</label>
                <input type="date" wire:model="startDate" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)]">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-[var(--sand)] mb-2">End Date</label>
                <input type="date" wire:model="endDate" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)]">
            </div>

            <!-- Action Buttons -->
            <div class="grid grid-cols-2 gap-2 mb-6">
                <button wire:click="loadReplay" class="px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-colors font-display font-semibold flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Load Replay
                </button>
                <button wire:click="showRecentActivities" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-[var(--stone)] rounded-lg transition-colors font-medium flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Recent
                </button>
                <button wire:click="exportReplayData" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-[var(--stone)] rounded-lg transition-colors font-medium flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Export
                </button>
                <button wire:click="showRoutes" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-[var(--stone)] rounded-lg transition-all font-medium flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    Routes
                </button>
            </div>

            <!-- Recent Activities (for selected machine / date range) -->
            @if($showActivities)
                <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-4 mb-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-semibold text-[var(--stone)]">Recent Activities</h4>
                        <button wire:click="hideRecentActivities" class="text-xs text-[var(--sand)] hover:text-[var(--stone)]">Close</button>
                    </div>
                    @if(count($machineActivities) > 0)
                        <ul class="space-y-2 text-sm text-[var(--sand)] max-h-64 overflow-y-auto">
                            @foreach($machineActivities as $act)
                                <li>
                                    <div class="text-xs text-[var(--sand)]">{{ $act['created_at'] }} — {{ $act['user'] }}</div>
                                    <div class="font-medium">{{ $act['action'] }}</div>
                                    <div class="text-[var(--sand)] text-sm">{{ $act['description'] }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-[var(--sand)]">No activities found for the selected machine/date range.</p>
                    @endif
                </div>
            @endif

            <!-- Enhanced Playback Player -->
            <div class="bg-gradient-to-br from-[var(--ink-soft)] to-[var(--ink)] rounded-xl p-6 shadow-2xl border border-[var(--line)] mb-6">
                @if($selectedMachine && $totalPositions > 0)
                    <!-- Player Header -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-[var(--gold)] to-[var(--gold-soft)] rounded-lg flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-[var(--ink)]" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                                    <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v6h16V7a2 2 0 00-2-2H4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-[var(--stone)]">Movement Replay</h3>
                                <p class="text-sm text-[var(--sand)]">{{ $totalPositions }} recorded positions</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-[var(--sand)] mb-1">Current Time</div>
                            <div class="text-sm font-mono text-[var(--gold)]" id="current-timestamp">--:--:--</div>
                        </div>
                    </div>

                    <!-- Progress Bar with Timeline -->
                    <div class="mb-6">
                        <div class="relative h-3 bg-white/10 rounded-full overflow-hidden mb-2">
                            <!-- Background progress -->
                            <div class="absolute inset-0 bg-gradient-to-r from-[var(--gold)]/20 to-[var(--gold-soft)]/20"></div>
                            <!-- Active progress -->
                            <div class="absolute inset-y-0 left-0 bg-gradient-to-r from-[var(--gold)] to-[var(--gold-soft)] transition-all duration-300" 
                                 style="width: {{ $totalPositions > 0 ? (($currentPosition + 1) / $totalPositions * 100) : 0 }}%">
                            </div>
                            <!-- Glow effect -->
                            <div class="absolute inset-y-0 bg-gradient-to-r from-transparent via-white/30 to-transparent animate-shimmer" 
                                 style="width: {{ $totalPositions > 0 ? (($currentPosition + 1) / $totalPositions * 100) : 0 }}%; left: 0;">
                            </div>
                        </div>
                        
                        <!-- Seekbar -->
                        <input type="range" 
                               id="replay-slider"
                               min="0" 
                               max="{{ max(0, $totalPositions - 1) }}" 
                               value="{{ $currentPosition }}" 
                               class="w-full h-2 bg-transparent rounded-lg appearance-none cursor-pointer slider-thumb"
                               style="margin-top: -10px;">
                        
                        <!-- Timeline markers -->
                        <div class="flex justify-between text-xs text-[var(--sand)]/70 mt-1 px-1">
                            <span id="start-time">{{ $startDate }}</span>
                            <span class="text-[var(--gold)] font-mono">{{ $currentPosition + 1 }} / {{ $totalPositions }}</span>
                            <span id="end-time">{{ $endDate }}</span>
                        </div>
                    </div>

                    <!-- Main Controls -->
                    <div class="flex items-center justify-center gap-3 mb-6">
                        <!-- Previous Frame -->
                        <button wire:click="previousFrame" 
                                class="p-3 bg-white/10 hover:bg-white/20 rounded-lg transition-all transform hover:scale-105 group"
                                title="Previous Frame">
                            <svg class="w-5 h-5 text-[var(--sand)] group-hover:text-[var(--stone)]" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8.445 14.832A1 1 0 0010 14v-2.798l5.445 3.63A1 1 0 0017 14V6a1 1 0 00-1.555-.832L10 8.798V6a1 1 0 00-1.555-.832l-6 4a1 1 0 000 1.664l6 4z"/>
                            </svg>
                        </button>

                        <!-- Play/Pause Button -->
                        @if($isPlaying)
                            <button wire:click="pause"
                                    class="p-3 bg-gradient-to-br from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 rounded-lg transition-all transform hover:scale-105 group"
                                    title="Pause">
                                <svg class="w-5 h-5 text-[var(--stone)]" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M5 4a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V4zm8 0a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2h-2a2 2 0 01-2-2V4z"/>
                                </svg>
                            </button>
                        @else
                            <button wire:click="play" data-speed="{{ $playbackSpeed }}"
                                    class="p-3 bg-gradient-to-br from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 rounded-lg transition-all transform hover:scale-105 group"
                                    title="Play">
                                <svg class="w-5 h-5 text-[var(--stone)]" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M6.3 2.84A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.27l9.34-5.89a1.5 1.5 0 000-2.54L6.3 2.84z"/>
                                </svg>
                            </button>
                        @endif

                        <!-- Stop Button -->
                        <button wire:click="stop"
                                class="p-3 bg-red-600 hover:bg-red-700 rounded-lg transition-all transform hover:scale-105 group"
                                title="Stop & Reset">
                            <svg class="w-5 h-5 text-[var(--stone)]" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V8a1 1 0 00-1-1H8z" clip-rule="evenodd"/>
                            </svg>
                        </button>

                        <!-- Next Frame -->
                        <button wire:click="nextFrame" 
                                class="p-3 bg-white/10 hover:bg-white/20 rounded-lg transition-all transform hover:scale-105 group"
                                title="Next Frame">
                            <svg class="w-5 h-5 text-[var(--sand)] group-hover:text-[var(--stone)]" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M4.555 5.168A1 1 0 003 6v8a1 1 0 001.555.832L10 11.202V14a1 1 0 001.555.832l6-4a1 1 0 000-1.664l-6-4A1 1 0 0010 6v2.798l-5.445-3.63z"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Speed Control & Additional Options -->
                    <div class="space-y-4">
                        <!-- Playback Speed -->
                        <div class="bg-[var(--ink-soft)]/50 rounded-lg p-4 border border-[var(--line)]">
                            <div class="flex items-center justify-between mb-3">
                                <label class="text-sm font-medium text-[var(--sand)] flex items-center gap-2">
                                    <svg class="w-4 h-4 text-[var(--gold)]" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                    </svg>
                                    Playback Speed
                                </label>
                                <span class="text-[var(--gold)] font-bold text-lg">{{ $playbackSpeed }}x</span>
                            </div>
                            <div class="flex gap-2">
                                @foreach([0.25, 0.5, 1, 2, 4, 8] as $speed)
                                    <button wire:click="setSpeed({{ $speed }})" 
                                            class="flex-1 px-2 py-2 rounded-lg text-sm font-medium transition-all {{ $playbackSpeed == $speed ? 'bg-[var(--gold)] text-[var(--ink)] shadow-lg' : 'bg-white/10 text-[var(--sand)] hover:bg-white/20' }}">
                                        {{ $speed }}x
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Playback Options -->
                        <div class="bg-[var(--ink-soft)]/50 rounded-lg p-4 border border-[var(--line)]">
                            <label class="text-sm font-medium text-[var(--sand)] mb-3 block flex items-center gap-2">
                                <svg class="w-4 h-4 text-[var(--gold)]" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                                </svg>
                                Options
                            </label>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox" wire:model="autoReplay" class="w-4 h-4 rounded bg-white/10 border-[var(--line)] text-[var(--gold)] focus:ring-[var(--gold)]">
                                    <span class="text-sm text-[var(--sand)] group-hover:text-[var(--sand)]">Loop replay</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox" wire:model="showTrail" checked class="w-4 h-4 rounded bg-white/10 border-[var(--line)] text-[var(--gold)] focus:ring-[var(--gold)]">
                                    <span class="text-sm text-[var(--sand)] group-hover:text-[var(--sand)]">Show trail</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox" wire:model="smoothPan" checked class="w-4 h-4 rounded bg-white/10 border-[var(--line)] text-[var(--gold)] focus:ring-[var(--gold)]">
                                    <span class="text-sm text-[var(--sand)] group-hover:text-[var(--sand)]">Smooth camera</span>
                                </label>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Empty State - Waiting for Data -->
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-10 h-10 text-[var(--sand)]/60" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                                <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v6h16V7a2 2 0 00-2-2H4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-[var(--stone)] mb-2">Ready to Replay</h3>
                        <p class="text-[var(--sand)] text-sm">Select a machine, date range, and click "Load Replay" to view historical movements</p>
                    </div>
                @endif
            </div>

            @endif
        </div>

        <!-- Map Container -->
        <div class="flex-1 relative bg-[var(--ink-soft)]" style="min-height: 400px;" wire:ignore>
            <!-- Map always visible -->
            <div id="replay-map" class="w-full h-full absolute inset-0" style="min-height: 400px;"></div>
            
            <!-- Hover overlay when no data loaded -->
            @if(!$selectedMachine || $totalPositions == 0)
            <div class="absolute inset-0 bg-[var(--ink)]/90 backdrop-blur-sm flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity duration-300 pointer-events-none z-[400]" id="map-overlay">
                <div class="text-center p-8 pointer-events-auto">
                    @if(!$selectedMachine)
                        <div class="text-6xl mb-4">🚜</div>
                        <h3 class="text-xl font-semibold text-[var(--stone)] mb-2">Select a Machine</h3>
                        <p class="text-[var(--sand)] mb-2">Choose a machine and date range to replay its movement history.</p>
                    @else
                        <div class="text-6xl mb-4">📉</div>
                        <h3 class="text-xl font-semibold text-[var(--stone)] mb-2">No Data Available</h3>
                        <p class="text-[var(--sand)] mb-2">No movement data found for the selected time range.</p>
                    @endif
                </div>
            </div>
            @endif
            
            <script nonce="{{ request()->attributes->get('csp_nonce') }}">
                // Hide overlay when data is loaded
                function hideMapOverlay() {
                    const overlay = document.getElementById('map-overlay');
                    if (overlay) {
                        overlay.style.display = 'none';
                    }
                }
                
                // Show overlay when no data
                function showMapOverlay() {
                    const overlay = document.getElementById('map-overlay');
                    if (overlay) {
                        overlay.style.display = 'flex';
                    }
                }
            </script>
        </div>
    </div>

    <!-- Leaflet JS - loaded directly in component -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-providers/1.13.0/leaflet-providers.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        // Initialize with safe defaults - all at window scope
        window.replayState = {
            centerLat: {{ $centerLat ?? -26.2041 }},
            centerLng: {{ $centerLng ?? 28.0473 }},
            zoomLevel: {{ $zoomLevel ?? 10 }}
        };

        window.replayMap = null;
        window.pathCoordinates = [];
        window.geofences = [];
        window.routes = [];
        // sensible defaults for client-side options (used by centering/panning logic)
        // Disable smooth animation by default for automatic centering to avoid
        // Leaflet renderer errors during rapid DOM updates. Users can enable
        // smooth pan via the UI checkbox which is bound to Livewire.
        window.smoothPan = false;
        window.showTrail = true;
        // Throttle timestamp to avoid rapid repeated renders
        window._replayLastRenderAt = 0;
        window.initRetryCount = 0;
        const MAX_INIT_RETRIES = 50;
        
        // Map layers
        window.currentMarker = null;
        window.pathPolyline = null;
        window.geofencePolygons = [];
        window.routePolylines = [];
        window.trailPolyline = null;
        window.machineType = '';
        window._replayHasInvalidLayer = false;

        // Helper: normalize various coordinate formats to {lat, lng}
        function normalizeCoord(coord) {
            if (!coord) return null;
            // If already object with lat/lng
            if (typeof coord === 'object' && coord !== null) {
                if (typeof coord.lat === 'number' && typeof coord.lng === 'number') return { lat: coord.lat, lng: coord.lng };
                if (typeof coord.latitude === 'number' && typeof coord.longitude === 'number') return { lat: coord.latitude, lng: coord.longitude };
                // If array-like [lat, lng] or [lng, lat]
                    if (Array.isArray(coord) && coord.length >= 2) {
                        const a = Number(coord[0]);
                        const b = Number(coord[1]);
                        if (!Number.isNaN(a) && !Number.isNaN(b)) {
                            // Detect whether array is [lat, lng] or GeoJSON [lng, lat]
                            const isLatA = a >= -90 && a <= 90 && b >= -180 && b <= 180;
                            const isLatB = b >= -90 && b <= 90 && a >= -180 && a <= 180;
                            if (isLatA) return { lat: a, lng: b };
                            if (isLatB) return { lat: b, lng: a };
                            // Fallback: assume first is lat
                            return { lat: a, lng: b };
                        }
                    }
                // If object with nested coordinates (GeoJSON style)
                if (coord.coordinates) return normalizeCoord(coord.coordinates);
            }
            // If string, try parse
            if (typeof coord === 'string') {
                try {
                    const parsed = JSON.parse(coord);
                    return normalizeCoord(parsed);
                } catch (e) {
                    return null;
                }
            }
            return null;
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
            return emojiMap[machineType] || '/machine-emojis/excavator.svg';
        }
        
        // Load initial data from data attributes
        function loadDataFromAttributes() {
            const componentDiv = document.querySelector('[data-path-coords]');
            if (componentDiv) {
                try {
                    const pathCoordsStr = componentDiv.getAttribute('data-path-coords');
                    const geofencesStr = componentDiv.getAttribute('data-geofences');
                    const routesStr = componentDiv.getAttribute('data-routes');
                    const machineTypeStr = componentDiv.getAttribute('data-machine-type');
                    window.machineType = machineTypeStr || '';
                    // Parse raw strings and normalize shapes
                    const rawPath = pathCoordsStr ? JSON.parse(pathCoordsStr) : [];
                    const rawGeofences = geofencesStr ? JSON.parse(geofencesStr) : [];
                    const rawRoutes = routesStr ? JSON.parse(routesStr) : [];

                    // Normalize pathCoordinates to objects with numeric lat/lng
                    window.pathCoordinates = rawPath.map(p => {
                        const n = normalizeCoord(p);
                        return n ? Object.assign({}, p, { lat: Number(n.lat), lng: Number(n.lng) }) : null;
                    }).filter(Boolean);

                    // Normalize geofences: ensure coordinates is an array of [lat,lng] pairs
                    window.geofences = rawGeofences.map(g => {
                        try {
                            let coords = g.coordinates;
                            if (typeof coords === 'string') coords = JSON.parse(coords);
                            // GeoJSON "coordinates" might be [ [lng,lat], ... ] or [{lat,lng}, ...]
                            const latlngs = [];
                            if (Array.isArray(coords)) {
                                coords.forEach(c => {
                                    const nn = normalizeCoord(c);
                                    if (nn) latlngs.push([nn.lat, nn.lng]);
                                });
                            }
                            return Object.assign({}, g, { coordinates: latlngs });
                        } catch (e) {
                            return Object.assign({}, g, { coordinates: [] });
                        }
                    });

                    // Normalize routes: ensure waypoints become arrays of [lat,lng]
                    window.routes = rawRoutes.map(r => {
                        try {
                            const waypoints = (r.waypoints || []).map(wp => {
                                const nn = normalizeCoord(wp);
                                return nn ? [Number(nn.lat), Number(nn.lng)] : null;
                            }).filter(Boolean);
                            return Object.assign({}, r, { waypoints });
                        } catch (e) {
                            return Object.assign({}, r, { waypoints: [] });
                        }
                    });

                    if (window.routes && window.routes.length > 0) {
                    }
                    
                    // Clear the snapped coordinate cache when new data is loaded
                    window.snappedCoordinateCache = {};
                    
                    
                    // Log route details for debugging
                    if (window.routes?.length > 0) {
                        window.routes.forEach((route, idx) => {
                        });
                    }
                } catch (err) {
                    console.error('Error loading initial data:', err);
                }
            }
        }
        
        // Load data immediately
        loadDataFromAttributes();
        
        // Timer update function
        function updateTimerDisplay() {
            try {
                const timerElement = document.getElementById('current-timestamp');
                if (!timerElement) return;
                
                if (Array.isArray(window.pathCoordinates) && window.pathCoordinates.length > 0) {
                    const timestamp = window.pathCoordinates[0]?.timestamp;
                    if (timestamp) {
                        timerElement.textContent = timestamp;
                    }
                }
            } catch (err) {
                console.error('Error updating timer display:', err);
            }
        }

        function initReplayMap() {
            // Debug: Check what's available
            
            // Check if Leaflet is loaded (check both window.L and global L)
            if (typeof window.L === 'undefined' && typeof L === 'undefined') {
                window.initRetryCount++;
                if (window.initRetryCount > MAX_INIT_RETRIES) {
                    console.error('Leaflet failed to load after maximum retries');
                    return;
                }
                setTimeout(initReplayMap, 200);
                return;
            }
            
            // Check if map container exists
            const mapContainer = document.getElementById('replay-map');
            if (!mapContainer) {
                setTimeout(initReplayMap, 100);
                return;
            }
            
            // Check if map is already initialized
            if (window.replayMap) {
                return;
            }
            
            
            try {
                // Initialize map
                window.replayMap = L.map('replay-map').setView([window.replayState.centerLat, window.replayState.centerLng], window.replayState.zoomLevel);

                // Add tile layers
                const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                });

                const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19,
                    attribution: 'Esri, Maxar, Earthstar Geographics'
                });

                osmLayer.addTo(window.replayMap);

                // Layer control
                L.control.layers({
                    'Standard': osmLayer,
                    'Satellite': satelliteLayer
                }).addTo(window.replayMap);
                
                
                // Invalidate size after short delay
                setTimeout(() => {
                    if (window.replayMap) {
                        window.replayMap.invalidateSize();
                    }
                }, 100);
                
            } catch (error) {
                console.error('Error initializing map:', error);
            }
        }
        
        function renderPathOnMap() {
            try {
                if (!window.replayMap || !Array.isArray(window.pathCoordinates) || window.pathCoordinates.length === 0) {
                    return;
                }
                
                // Clear existing path
                if (window.pathPolyline) {
                    window.replayMap.removeLayer(window.pathPolyline);
                }
                // Create path polyline with snapped coordinates to stay on routes
                // Cache the snapped path to avoid recalculating on every render
                const pathLatLngs = [];
                let skipped = 0;
                for (let i = 0; i < window.pathCoordinates.length; i++) {
                    const coord = window.pathCoordinates[i];
                    const normalized = normalizeCoord(coord) || coord;
                    if (!normalized || typeof normalized.lat === 'undefined' || typeof normalized.lng === 'undefined') {
                        skipped++;
                        continue;
                    }
                    const snapped = snapCoordinateToRoute({ lat: Number(normalized.lat), lng: Number(normalized.lng) });
                    if (!snapped || typeof snapped.lat === 'undefined' || typeof snapped.lng === 'undefined' || !isFinite(snapped.lat) || !isFinite(snapped.lng)) {
                        skipped++;
                        continue;
                    }
                    pathLatLngs.push([Number(snapped.lat), Number(snapped.lng)]);
                }
                // Filter to ensure only finite numeric lat/lng pairs are passed to Leaflet
                const validPathLatLngs = pathLatLngs.filter(pt => Array.isArray(pt) && pt.length >= 2 && isFinite(Number(pt[0])) && isFinite(Number(pt[1]))).map(pt => [Number(pt[0]), Number(pt[1])]);
                if (validPathLatLngs.length < 2) {
                    console.warn('Not enough valid path points to render polyline');
                } else {
                    try {
                        // Convert to L.latLng objects and validate thoroughly before adding
                        const latLngObjs = validPathLatLngs.map(pt => L.latLng(Number(pt[0]), Number(pt[1])));
                        const anyInvalid = latLngObjs.some(ll => !ll || !isFinite(Number(ll.lat)) || !isFinite(Number(ll.lng)));
                        if (anyInvalid) {
                            console.error('Aborting path polyline: found invalid lat/lng after conversion', latLngObjs);
                        } else {
                            // Add when map is ready to avoid renderer race conditions
                            if (window.replayMap && typeof window.replayMap.whenReady === 'function') {
                                window.replayMap.whenReady(() => {
                                    try {
                                        const projected = latLngObjs.map(ll => {
                                            try { return window.replayMap.latLngToLayerPoint(ll); } catch (e) { return null; }
                                        });
                                        const validProjected = projected.filter(p => p && isFinite(Number(p.x)) && isFinite(Number(p.y)));
                                        if (validProjected.length < 2) {
                                            console.error('Aborting path polyline: insufficient valid projected points', {
                                                latLngObjs: latLngObjs.slice(0,20),
                                                projected: projected.slice(0,20)
                                            });
                                            window._replayHasInvalidLayer = true;
                                            return;
                                        }

                                        window.pathPolyline = L.polyline(latLngObjs, {
                                            color: '#fbbf24',
                                            weight: 3,
                                            opacity: 0.7,
                                            dashArray: '5, 5',
                                            className: 'replay-path',
                                            lineCap: 'round',
                                            lineJoin: 'round'
                                        }).addTo(window.replayMap);
                                    } catch (innerErr) {
                                        console.error('Failed to add path polyline inside whenReady:', innerErr, {
                                            latLngObjs: latLngObjs.slice(0,20)
                                        });
                                        window._replayHasInvalidLayer = true;
                                    }
                                });
                            } else {
                                try {
                                    const projected = latLngObjs.map(ll => {
                                        try { return window.replayMap.latLngToLayerPoint(ll); } catch (e) { return null; }
                                    });
                                    const validProjected = projected.filter(p => p && isFinite(Number(p.x)) && isFinite(Number(p.y)));
                                    if (validProjected.length < 2) {
                                        console.error('Aborting path polyline (no whenReady): insufficient projections', {
                                            latLngObjs: latLngObjs.slice(0,20),
                                            projected: projected.slice(0,20)
                                        });
                                        window._replayHasInvalidLayer = true;
                                    } else {
                                        window.pathPolyline = L.polyline(latLngObjs, {
                                            color: '#fbbf24',
                                            weight: 3,
                                            opacity: 0.7,
                                            dashArray: '5, 5',
                                            className: 'replay-path',
                                            lineCap: 'round',
                                            lineJoin: 'round'
                                        }).addTo(window.replayMap);
                                    }
                                } catch (innerErr) {
                                    console.error('Failed to add path polyline (no whenReady):', innerErr, {
                                        latLngObjs: latLngObjs.slice(0,20)
                                    });
                                    window._replayHasInvalidLayer = true;
                                }
                            }
                        }
                    } catch (e) {
                        console.error('Failed to prepare path polyline for map:', e);
                    }
                }
                
                
                // Log first and last points to verify snapping
                if (pathLatLngs.length > 0) {
                }
            } catch (err) {
                console.error('Error rendering path on map:', err);
            }
        }
        
        function renderGeofencesOnMap() {
            try {
                if (!window.replayMap || !Array.isArray(window.geofences) || window.geofences.length === 0) {
                    return;
                }
                
                // Clear existing geofences
                window.geofencePolygons.forEach(polygon => {
                    if (polygon instanceof L.Polygon) {
                        window.replayMap.removeLayer(polygon);
                    }
                });
                window.geofencePolygons = [];
                
                window.geofences.forEach(geofence => {
                    try {
                        const coords = geofence.coordinates || [];
                        const latlngs = [];
                        if (Array.isArray(coords)) {
                            coords.forEach(c => {
                                const nn = normalizeCoord(c);
                                if (nn) latlngs.push([Number(nn.lat), Number(nn.lng)]);
                            });
                        }

                        if (latlngs.length >= 2) {
                            const polygon = L.polygon(latlngs, {
                                color: geofence.color || '#3b82f6',
                                weight: 2,
                                opacity: 0.5,
                                fillOpacity: 0.1,
                                className: 'geofence-poly'
                            }).bindPopup(`<strong>${geofence.name}</strong><br>Type: ${geofence.type}`);

                            polygon.addTo(window.replayMap);
                            window.geofencePolygons.push(polygon);
                        }
                    } catch (e) {
                        console.warn('Skipping invalid geofence during render:', geofence, e);
                    }
                });
                
            } catch (err) {
                console.error('Error rendering geofences on map:', err);
            }
        }
        
        function renderRoutesOnMap() {
            try {
                if (!window.replayMap || !Array.isArray(window.routes) || window.routes.length === 0) {
                    return;
                }
                
                // Clear existing routes
                window.routePolylines.forEach(polyline => {
                    if (polyline instanceof L.Polyline) {
                        window.replayMap.removeLayer(polyline);
                    }
                });
                window.routePolylines = [];
                
                window.routes.forEach((route, routeIndex) => {
                    if (route.waypoints && route.waypoints.length > 0) {
                        // Normalize waypoints using normalizeCoord to handle array or object formats
                        const latlngs = route.waypoints.map(wp => {
                            const nn = normalizeCoord(wp);
                            return nn ? [Number(nn.lat), Number(nn.lng)] : null;
                        }).filter(coord => coord !== null);
                        
                        // Ensure route latlngs are numeric
                        const validRouteLatlngs = latlngs.filter(pt => Array.isArray(pt) && pt.length >= 2 && isFinite(Number(pt[0])) && isFinite(Number(pt[1]))).map(pt => [Number(pt[0]), Number(pt[1])]);
                        if (validRouteLatlngs.length >= 2) {
                            try {
                                const latLngObjs = validRouteLatlngs.map(pt => L.latLng(Number(pt[0]), Number(pt[1])));
                                const anyInvalid = latLngObjs.some(ll => !ll || !isFinite(Number(ll.lat)) || !isFinite(Number(ll.lng)));
                                if (anyInvalid) {
                                    console.error('Aborting route polyline: invalid lat/lngs', latLngObjs);
                                } else {
                                    const createAndAdd = () => {
                                        try {
                                            const projected = latLngObjs.map(ll => {
                                                try { return window.replayMap.latLngToLayerPoint(ll); } catch (e) { return null; }
                                            });
                                            const validProjected = projected.filter(p => p && isFinite(Number(p.x)) && isFinite(Number(p.y)));
                                            if (validProjected.length < 2) {
                                                console.error('Aborting route polyline: insufficient valid projected points', {
                                                    latLngObjs: latLngObjs.slice(0,20),
                                                    projected: projected.slice(0,20)
                                                });
                                                window._replayHasInvalidLayer = true;
                                                return;
                                            }

                                            const polyline = L.polyline(latLngObjs, {
                                                color: route.color || '#f59e0b',
                                                weight: 3,
                                                opacity: 0.9,
                                                lineCap: 'round',
                                                lineJoin: 'round',
                                                className: 'replay-route',
                                                dashArray: routeIndex === 0 && route.name === 'Auto-calculated Route' ? '8, 4' : 'none'
                                            }).bindPopup(`
                                                <div class="bg-white p-2 rounded">
                                                    <strong>${route.name}</strong><br>
                                                    ${route.waypoints?.length || 0} waypoints<br>
                                                    From: ${route.start_location}<br>
                                                    To: ${route.end_location}
                                                </div>
                                            `);

                                            polyline.addTo(window.replayMap);
                                            window.routePolylines.push(polyline);
                                        } catch (innerErr) {
                                            console.error('Failed to add route polyline inside createAndAdd:', innerErr, {
                                                latLngObjs: latLngObjs.slice(0,20)
                                            });
                                            window._replayHasInvalidLayer = true;
                                        }
                                    };

                                    if (window.replayMap && typeof window.replayMap.whenReady === 'function') {
                                        window.replayMap.whenReady(createAndAdd);
                                    } else {
                                        createAndAdd();
                                    }
                                }
                            } catch (e) {
                                console.error('Failed to prepare route polyline for map:', e);
                            }
                        }
                    }
                });
                
            } catch (err) {
                console.error('Error rendering routes on map:', err);
            }
        }
        
        // Calculate distance between two coordinates (in meters)
        function calculateDistance(lat1, lng1, lat2, lng2) {
            const R = 6371000; // Earth's radius in meters
            const φ1 = lat1 * Math.PI / 180;
            const φ2 = lat2 * Math.PI / 180;
            const Δφ = (lat2 - lat1) * Math.PI / 180;
            const Δλ = (lng2 - lng1) * Math.PI / 180;
            
            const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                      Math.cos(φ1) * Math.cos(φ2) *
                      Math.sin(Δλ/2) * Math.sin(Δλ/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            const distance = R * c;
            
            return distance;
        }
        
        // Get the closest point on a line segment to a point
        function getClosestPointOnLineSegment(lat, lng, lat1, lng1, lat2, lng2) {
            // Handle degenerate segment
            if ((lat1 === lat2) && (lng1 === lng2)) {
                return { lat: lat1, lng: lng1 };
            }

            const dx = lng2 - lng1;
            const dy = lat2 - lat1;

            // Project point onto the line, computing parameter t in [0,1]
            let t = ((lng - lng1) * dx + (lat - lat1) * dy) / (dx * dx + dy * dy);
            t = Math.max(0, Math.min(1, t));

            return {
                lat: lat1 + t * (lat2 - lat1),
                lng: lng1 + t * (lng2 - lng1)
            };
        }

        // Linear interpolation between two positions
        function interpolatePos(from, to, progress) {
            if (!from || !to) return from || to || null;
            const p = Math.max(0, Math.min(1, progress || 0));
            return {
                lat: from.lat + (to.lat - from.lat) * p,
                lng: from.lng + (to.lng - from.lng) * p,
                heading: (from.heading || 0) + ((to.heading || 0) - (from.heading || 0)) * p
            };
        }

        // Given a fractional index into the pathCoordinates (e.g. 3.4), return an interpolated snapped position
        function getInterpolatedPosition(fractionalIndex) {
            if (!Array.isArray(window.pathCoordinates) || window.pathCoordinates.length === 0) return null;
            const lowerIndex = Math.floor(fractionalIndex);
            const upperIndex = Math.ceil(fractionalIndex);
            const progress = fractionalIndex - lowerIndex;

            if (lowerIndex < 0) return snapCoordinateToRoute(window.pathCoordinates[0]);
            if (upperIndex >= window.pathCoordinates.length) return snapCoordinateToRoute(window.pathCoordinates[window.pathCoordinates.length - 1]);

            if (lowerIndex === upperIndex) {
                return snapCoordinateToRoute(window.pathCoordinates[lowerIndex]);
            }

            const from = snapCoordinateToRoute(window.pathCoordinates[lowerIndex]);
            const to = snapCoordinateToRoute(window.pathCoordinates[upperIndex]);
            return interpolatePos(from, to, progress);
        }
        
        // Cache for snapped coordinates to improve performance
        window.snappedCoordinateCache = {};
        
        // Generate cache key for a coordinate
        function generateCoordCacheKey(coord) {
            if (!coord || typeof coord.lat === 'undefined' || typeof coord.lng === 'undefined') {
                return null;
            }
            // Round to 5 decimal places for cache key (approximately 1 meter precision)
            const lat = Math.round(coord.lat * 100000) / 100000;
            const lng = Math.round(coord.lng * 100000) / 100000;
            return `${lat},${lng}`;
        }
        
        // Find the closest point on routes to the given coordinate
        function snapCoordinateToRoute(coord, forceRecalculate = false) {
            if (!coord || typeof coord.lat === 'undefined' || typeof coord.lng === 'undefined') {
                return coord;
            }
            
            // Check cache first
            if (!forceRecalculate) {
                const cacheKey = generateCoordCacheKey(coord);
                if (cacheKey && window.snappedCoordinateCache[cacheKey]) {
                    return window.snappedCoordinateCache[cacheKey];
                }
            }
            
            // If no routes defined, return original coordinate
            if (!Array.isArray(window.routes) || window.routes.length === 0) {
                return coord;
            }
            
            let closestPoint = null;
            let minDistance = Infinity;
            let snapSegment = null;
            const snapRadius = 1000; // Extended snap radius to 1 km for better coverage
            
            // Check each route
            window.routes.forEach(route => {
                if (!route.waypoints || route.waypoints.length < 2) {
                    return;
                }
                
                // Check each segment of the route
                for (let i = 0; i < route.waypoints.length - 1; i++) {
                    const wp1 = route.waypoints[i];
                    const wp2 = route.waypoints[i + 1];
                    
                    // Handle multiple waypoint formats: {latitude, longitude}, {lat, lng}, or [lat, lng]
                    const lat1 = wp1.latitude ?? wp1.lat ?? (Array.isArray(wp1) ? wp1[0] : undefined);
                    const lng1 = wp1.longitude ?? wp1.lng ?? (Array.isArray(wp1) ? wp1[1] : undefined);
                    const lat2 = wp2.latitude ?? wp2.lat ?? (Array.isArray(wp2) ? wp2[0] : undefined);
                    const lng2 = wp2.longitude ?? wp2.lng ?? (Array.isArray(wp2) ? wp2[1] : undefined);
                    
                    // Validate waypoints
                    if (typeof lat1 === 'undefined' || typeof lng1 === 'undefined' ||
                        typeof lat2 === 'undefined' || typeof lng2 === 'undefined') {
                        continue;
                    }
                    
                    // Find closest point on this line segment
                    const closestOnSegment = getClosestPointOnLineSegment(
                        coord.lat, coord.lng,
                        lat1, lng1,
                        lat2, lng2
                    );
                    
                    const distance = calculateDistance(
                        coord.lat, coord.lng,
                        closestOnSegment.lat, closestOnSegment.lng
                    );
                    
                    if (distance < minDistance) {
                        minDistance = distance;
                        snapSegment = { lat1, lng1, lat2, lng2 };
                        closestPoint = {
                            ...coord,
                            lat: closestOnSegment.lat,
                            lng: closestOnSegment.lng
                        };
                    }
                }
            });
            
            // Snap if we found a point (use extended radius)
            if (closestPoint) {
                // Cache the result
                const cacheKey = generateCoordCacheKey(coord);
                if (cacheKey) {
                    window.snappedCoordinateCache[cacheKey] = closestPoint;
                }
                
                if (minDistance <= snapRadius || minDistance <= 5000) { // Allow up to 5km if necessary
                    return closestPoint;
                }
            }
            
            // Fallback: return original if no route segments found
            return coord;
        }
        
        function zoomToRouteArea() {
            if (!window.replayMap) return;

            if (window._replayHasInvalidLayer) {
                console.warn('zoomToRouteArea: skipping because an invalid layer was detected');
                return;
            }

            // Build bounds from any valid coordinates we have
            try {
                const bounds = L.latLngBounds([]);
                let added = 0;

                if (Array.isArray(window.pathCoordinates)) {
                    window.pathCoordinates.forEach(coord => {
                        const n = normalizeCoord(coord);
                        if (n && typeof n.lat !== 'undefined' && typeof n.lng !== 'undefined' && isFinite(n.lat) && isFinite(n.lng)) {
                            bounds.extend([Number(n.lat), Number(n.lng)]);
                            added++;
                        }
                    });
                }

                if (Array.isArray(window.geofences)) {
                    window.geofences.forEach(geofence => {
                        const coords = geofence.coordinates || [];
                        coords.forEach(c => {
                            const nn = normalizeCoord(c);
                            if (nn && typeof nn.lat !== 'undefined' && typeof nn.lng !== 'undefined' && isFinite(nn.lat) && isFinite(nn.lng)) {
                                bounds.extend([Number(nn.lat), Number(nn.lng)]);
                                added++;
                            }
                        });
                    });
                }

                if (added === 0) {
                    console.warn('zoomToRouteArea: No valid coordinates to build bounds');
                    return;
                }

                try {
                    // Prefer using the existing path polyline bounds if available
                    let effectiveBounds = bounds;
                    try {
                        if (window.pathPolyline && typeof window.pathPolyline.getBounds === 'function') {
                            const pb = window.pathPolyline.getBounds();
                            if (pb && typeof pb.isValid === 'function' ? pb.isValid() : true) {
                                effectiveBounds = pb;
                            }
                        }
                    } catch (e) {
                        // ignore and fall back to computed bounds
                    }

                    const isValid = typeof effectiveBounds.isValid === 'function' ? effectiveBounds.isValid() : (added > 0);
                    if (isValid) {
                        // Defer fitBounds slightly to reduce collisions with layer add animations
                        setTimeout(() => {
                            try {
                                window.replayMap.fitBounds(effectiveBounds, { padding: [50, 50] });
                            } catch (fbErr) {
                                console.error('Error applying fitBounds (deferred):', fbErr);
                            }
                        }, 40);
                    } else {
                        console.warn('zoomToRouteArea: computed bounds are not valid');
                    }
                } catch (e) {
                    console.error('Error applying fitBounds:', e);
                }
            } catch (err) {
                console.error('Error building bounds for zoomToRouteArea:', err);
            }
        }

        // Center map on the first available path coordinate or on the first route waypoint
        function centerOnSelectedMachine() {
            try {
                if (!window.replayMap) return;

                if (window._replayHasInvalidLayer) {
                    console.warn('centerOnSelectedMachine: skipping because an invalid layer was detected');
                    return;
                }

                // Prefer first path coordinate
                if (Array.isArray(window.pathCoordinates) && window.pathCoordinates.length > 0) {
                    // Find first valid coordinate
                    const firstValid = window.pathCoordinates.map(c => normalizeCoord(c)).find(n => n && typeof n.lat !== 'undefined' && typeof n.lng !== 'undefined');
                    if (firstValid) {
                        const lat = Number(firstValid.lat);
                        const lng = Number(firstValid.lng);
                        // Defer centering slightly and use whenReady to avoid renderer collisions
                        try {
                            if (window.replayMap && typeof window.replayMap.whenReady === 'function') {
                                window.replayMap.whenReady(() => setTimeout(() => {
                                    try { window.replayMap.setView([lat, lng], 14, { animate: false }); } catch (e) { console.warn('setView failed during whenReady:', e); }
                                }, 40));
                            } else {
                                setTimeout(() => { try { window.replayMap.setView([lat, lng], 14, { animate: false }); } catch (e) { console.warn('setView failed:', e); } }, 40);
                            }
                        } catch (e) {
                            console.warn('Error scheduling centerOnSelectedMachine setView:', e);
                        }
                        return;
                    }
                }

                // Fallback: use first route waypoint
                if (Array.isArray(window.routes) && window.routes.length > 0) {
                    const firstRoute = window.routes[0];
                    if (firstRoute && firstRoute.waypoints && firstRoute.waypoints.length > 0) {
                        const wp = firstRoute.waypoints[0];
                        let lat = wp.latitude ?? wp.lat ?? (Array.isArray(wp) ? wp[0] : undefined);
                        let lng = wp.longitude ?? wp.lng ?? (Array.isArray(wp) ? wp[1] : undefined);
                        if (typeof lat !== 'undefined' && typeof lng !== 'undefined') {
                            window.replayMap.setView([lat, lng], 14, { animate: false });
                        }
                    }
                }
            } catch (err) {
                console.error('Error centering on selected machine:', err);
            }
        }
        
        function updateMachineMarker(position) {
            try {
                if (!window.replayMap || position < 0 || !Array.isArray(window.pathCoordinates) || position >= window.pathCoordinates.length) return;
                
                const coord = window.pathCoordinates[position];
                if (!coord || typeof coord.lat === 'undefined' || typeof coord.lng === 'undefined') return;
                
                // Snap the coordinate to the nearest route for realistic movement on roads
                const snappedCoord = snapCoordinateToRoute(coord);
                const latlng = [snappedCoord.lat, snappedCoord.lng];
            
                // Remove existing marker
                if (window.currentMarker) {
                    window.replayMap.removeLayer(window.currentMarker);
                }
                
                // Calculate direction based on movement between last snapped and current snapped positions
                let heading = coord.heading || 0;
                if (position > 0) {
                    const prevCoord = window.pathCoordinates[position - 1];
                    if (prevCoord && position > 0) {
                        const prevSnapped = snapCoordinateToRoute(prevCoord);
                        // Calculate bearing from previous to current position
                        const dy = snappedCoord.lat - prevSnapped.lat;
                        const dx = snappedCoord.lng - prevSnapped.lng;
                        heading = Math.atan2(dx, dy) * 180 / Math.PI;
                    }
                }
                
                const speed = coord.speed || 0;
                const emojiImageUrl = getMachineEmojiImage(window.machineType);
                
                const markerHtml = `
                    <div style="
                        background-color: #ef4444;
                        width: 40px;
                        height: 40px;
                        border-radius: 50%;
                        border: 3px solid white;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.5);
                        transform: rotate(${heading}deg);
                        padding: 4px;
                    ">
                        <img src="${emojiImageUrl}" 
                             style="width: 28px; height: 28px; object-fit: contain;" 
                             onerror="this.style.display='none'; this.parentElement.innerHTML='🚜';" 
                             alt="Machine" />
                    </div>
                `;
                
                window.currentMarker = L.marker(latlng, {
                    icon: L.divIcon({
                        html: markerHtml,
                        className: '',
                        iconSize: [40, 40],
                        iconAnchor: [20, 20]
                    })
                }).bindPopup(`
                    <strong>Machine Position</strong><br>
                    Lat: ${snappedCoord.lat.toFixed(6)}<br>
                    Lng: ${snappedCoord.lng.toFixed(6)}<br>
                    Speed: ${speed} km/h<br>
                    Heading: ${heading.toFixed(0)}°<br>
                    Time: ${coord.timestamp}
                `);
                
                window.currentMarker.addTo(window.replayMap);
                
                // Update trail with snapped coordinates combined with smart interpolation
                if (position > 0) {
                    // Build trail from cached snapped coordinates
                    const trailCoordinates = [];
                    for (let i = 0; i <= position; i++) {
                        const pathCoord = window.pathCoordinates[i];
                        const snapped = snapCoordinateToRoute(pathCoord);
                        
                        // Add interpolation waypoints if this is not the first point and there's significant movement
                        if (i > 0) {
                            const prevSnapped = snapCoordinateToRoute(window.pathCoordinates[i - 1]);
                            const distBetween = calculateDistance(prevSnapped.lat, prevSnapped.lng, snapped.lat, snapped.lng);
                            
                            // If there's significant movement between points, add intermediate marker
                            if (distBetween > 50) { // More than 50 meters
                                // Interpolate intermediate point (for smoother trails)
                                const midLat = (prevSnapped.lat + snapped.lat) / 2;
                                const midLng = (prevSnapped.lng + snapped.lng) / 2;
                                trailCoordinates.push([midLat, midLng]);
                            }
                        }
                        
                        trailCoordinates.push([snapped.lat, snapped.lng]);
                    }
                    
                    if (window.trailPolyline) {
                        window.replayMap.removeLayer(window.trailPolyline);
                    }
                    window.trailPolyline = L.polyline(trailCoordinates, {
                        color: '#10b981',
                        weight: 2,
                        opacity: 0.6,
                        className: 'replay-trail',
                        lineCap: 'round',
                        lineJoin: 'round'
                    }).addTo(window.replayMap);
                }
            } catch (err) {
                console.error('Error updating machine marker:', err);
            }
        }

        // Render map elements when data is loaded (throttled)
        function renderMapElements() {
            var now = Date.now();
            if (now - (window._replayLastRenderAt || 0) < 180) {
                // Prevent spamming Leaflet with rapid updates which can trigger
                // renderer exceptions during animations.
                return;
            }
            window._replayLastRenderAt = now;

            renderPathOnMap();
            renderGeofencesOnMap();
            renderRoutesOnMap();
            zoomToRouteArea();

            if (Array.isArray(window.pathCoordinates) && window.pathCoordinates.length > 0) {
                updateMachineMarker(0);
            }

        }
        
        // Listen for Livewire component updates
        function initializeLivewireListeners() {
            document.addEventListener('livewire:updated', (e) => {
                try {
                    // Re-run the attribute parsing/normalization so we always have
                    // consistent `{lat, lng}` objects regardless of raw JSON shape
                    loadDataFromAttributes();

                    // Wait a moment for Livewire to re-render, then load new data
                    setTimeout(() => {
                        try {
                            loadDataFromAttributes();
                            if (Array.isArray(window.pathCoordinates) && window.pathCoordinates.length > 0) {
                                renderMapElements();
                                // Center map on selected machine's first position
                                centerOnSelectedMachine();
                                hideMapOverlay();
                                updateTimerDisplay();
                            } else {
                                showMapOverlay();
                            }
                        } catch (err) {
                            console.error('Error processing livewire:updated timeout:', err);
                        }
                    }, 150);
                } catch (err) {
                    console.error('Error handling livewire:updated:', err);
                }
            });

            // Register global Livewire event handlers (use Livewire.on when available)
            if (typeof Livewire !== 'undefined' && typeof Livewire.on === 'function') {
                let playbackInterval = null;

                Livewire.on('replay-loaded', () => {
                    setTimeout(() => {
                        try {
                            loadDataFromAttributes();

                            if (Array.isArray(window.pathCoordinates) && window.pathCoordinates.length > 0) {
                                renderMapElements();
                                centerOnSelectedMachine();
                                hideMapOverlay();
                                updateTimerDisplay();
                            } else {
                                showMapOverlay();
                            }
                        } catch (err) {
                            console.error('Error in replay-loaded handler:', err);
                        }
                    }, 150);
                });

                Livewire.on('show-routes', () => {
                    setTimeout(() => {
                        try {
                            loadDataFromAttributes();
                            if (Array.isArray(window.routes) && window.routes.length > 0) {
                                renderRoutesOnMap();
                                zoomToRouteArea();
                                hideMapOverlay();
                            } else {
                            }
                        } catch (err) {
                            console.error('Error showing routes:', err);
                        }
                    }, 120);
                });

                Livewire.on('replay-seek', (data) => {
                    try {
                        const position = typeof data?.position === 'number' ? data.position : 0;
                        if (position >= 0 && Array.isArray(window.pathCoordinates) && position < window.pathCoordinates.length) {
                            updateMachineMarker(position);
                            updateTimerDisplay();
                        }
                    } catch (err) {
                        console.error('Error during seek:', err);
                    }
                });

                Livewire.on('replay-play', (payload) => {
                    if (playbackInterval) clearInterval(playbackInterval);

                    // Playback driven by client but authoritative state stored server-side.
                    // We rely on the nearest Livewire component reference (set when a machine was selected)
                    // to update `currentPosition` on the server via `set`.
                    const speed = (payload && payload.speed) ? Number(payload.speed) : 1;
                    const delay = Math.max(100, Math.round(1000 / Math.max(0.1, speed)));

                    playbackInterval = setInterval(() => {
                        try {
                            const slider = document.getElementById('replay-slider');
                            let currentPos = slider ? parseInt(slider.value, 10) : NaN;
                            if (Number.isNaN(currentPos)) currentPos = 0;
                            const totalPositions = (window.pathCoordinates || []).length || 0;

                            updateTimerDisplay();
                            updateMachineMarker(currentPos);

                            if (currentPos < totalPositions - 1) {
                                const nextPos = currentPos + 1;
                                if (window.currentLivewireComponentRef && typeof window.currentLivewireComponentRef.set === 'function') {
                                    try {
                                        window.currentLivewireComponentRef.set('currentPosition', nextPos);
                                    } catch (e) {
                                        // fallback: update slider and marker locally
                                        if (slider) slider.value = nextPos;
                                        updateMachineMarker(nextPos);
                                    }
                                } else {
                                    if (slider) slider.value = nextPos;
                                    updateMachineMarker(nextPos);
                                }
                            } else {
                                // Reached end, attempt to pause server playback
                                if (window.currentLivewireComponentRef && typeof window.currentLivewireComponentRef.call === 'function') {
                                    try { window.currentLivewireComponentRef.call('pause'); } catch (e) {}
                                }
                            }
                        } catch (err) {
                            console.error('Error during playback:', err);
                        }
                    }, delay);
                });

                Livewire.on('replay-pause', () => {
                    try {
                        if (playbackInterval) {
                            clearInterval(playbackInterval);
                            playbackInterval = null;
                        }
                    } catch (e) {}
                });

                Livewire.on('replay-stop', () => {
                    try {
                        if (playbackInterval) {
                            clearInterval(playbackInterval);
                            playbackInterval = null;
                        }
                        updateMachineMarker(0);
                        updateTimerDisplay();
                    } catch (err) {
                        console.error('Error during stop:', err);
                    }
                });
            }
        }
        
        // Call initialization when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeLivewireListeners);
        } else {
            initializeLivewireListeners();
        }

        // When a machine is selected in the sidebar, automatically center the map
        // and hide the overlay prompt. Use event delegation so listeners survive
        // Livewire DOM updates.
        // Find the Livewire component instance associated with an element
        function findLivewireComponentForElement(el) {
            try {
                if (!el || typeof el.closest !== 'function' || typeof Livewire === 'undefined') return null;
                const compEl = el.closest('[wire\\:id]');
                if (!compEl) return null;
                const compId = compEl.getAttribute('wire:id');
                if (!compId || typeof Livewire.find !== 'function') return null;
                return Livewire.find(compId);
            } catch (e) {
                return null;
            }
        }

        // Request the server to load replay data for the selected machine, scoped to the
        // Livewire component instance nearest to the provided element.
        function requestLoadReplayForElement(el, machineId) {
            try {
                const comp = findLivewireComponentForElement(el);
                if (comp) {
                    // remember for playback controls
                    window.currentLivewireComponentRef = comp;
                    try {
                        // Ensure server property is set
                        if (typeof comp.set === 'function') {
                            comp.set('selectedMachine', machineId);
                        }
                        // Call the loadReplay method on that component
                        if (typeof comp.call === 'function') {
                            comp.call('loadReplay');
                            // After requesting the server to load, poll attributes and
                            // center the map once path/route data becomes available.
                            waitForPathAndCenter(el, 12, 150);
                            return;
                        }
                    } catch (err) {
                        console.warn('Component-specific loadReplay failed, falling back to emit', err);
                    }
                }

                // Fallback: global emit
                if (typeof Livewire !== 'undefined' && typeof Livewire.emit === 'function') {
                    Livewire.emit('loadReplay');
                    waitForPathAndCenter(el, 12, 150);
                }
            } catch (e) {
                console.warn('requestLoadReplayForElement error', e);
            }
        }

        // Poll the DOM attributes for path/route data after requesting a server
        // load. This helps us center the map as soon as data is present instead
        // of waiting for the exact timing of Livewire lifecycle events.
        function waitForPathAndCenter(el, maxAttempts, intervalMs) {
            maxAttempts = typeof maxAttempts === 'number' ? maxAttempts : 10;
            intervalMs = typeof intervalMs === 'number' ? intervalMs : 150;
            let attempts = 0;
            const iv = setInterval(() => {
                attempts++;
                try {
                    loadDataFromAttributes();
                    // If path coords or routes are present, render & center
                    if ((window.pathCoordinates && window.pathCoordinates.length > 0) || (window.routes && window.routes.length > 0)) {
                        clearInterval(iv);
                        try {
                            renderMapElements();
                        } catch (e) {
                            console.warn('Deferred render failed:', e);
                        }
                        try {
                            centerOnSelectedMachine();
                        } catch (e) {
                            console.warn('Deferred center failed:', e);
                        }
                        return;
                    }
                    if (attempts >= maxAttempts) {
                        clearInterval(iv);
                        // As a last resort, attempt to center optimistically
                        try { centerOnSelectedMachine(); } catch (e) {}
                    }
                } catch (err) {
                    console.warn('waitForPathAndCenter polling error:', err);
                }
            }, intervalMs);
            return iv;
        }

        document.addEventListener('change', (e) => {
            try {
                const target = e.target;
                if (!target) return;
            // Prefer checking the element id (stable) and fall back to attribute check
            const isMachineSelect = (target.id === 'machine-select') || (target.getAttribute && target.getAttribute('wire:model.live') === 'selectedMachine');
                if (!isMachineSelect) return;

                // If a machine was chosen (non-empty value), hide the overlay and
                // attempt to center the map if we have coordinates already.
                const val = target.value;
                if (val && val !== '') {
                    // Allow Livewire a moment to update any data attributes
                    setTimeout(() => {
                        try {
                            // Ask Livewire to load replay data for the selected machine (scoped to nearest component).
                            requestLoadReplayForElement(target, val);

                            // Also re-read attributes in case data was preloaded and render immediately.
                            loadDataFromAttributes();
                            if (Array.isArray(window.pathCoordinates) && window.pathCoordinates.length > 0) {
                                renderMapElements();
                                centerOnSelectedMachine();
                                hideMapOverlay();
                                updateTimerDisplay();
                            } else {
                                // No path data yet — still remove the instruction overlay so user can click Load Replay
                                hideMapOverlay();
                            }
                        } catch (err) {
                            console.error('Error handling machine select change:', err);
                        }
                    }, 120);
                } else {
                    // If user cleared selection, show overlay again
                    showMapOverlay();
                }
            } catch (err) {
                console.error('Error in machine select change listener:', err);
            }
        });
        
        // Initialize map
        initReplayMap();
        
        // Render on initial load if data exists
        window.addEventListener('load', () => {
            if (Array.isArray(window.pathCoordinates) && window.pathCoordinates.length > 0) {
                setTimeout(renderMapElements, 500);
            }
            
            // Setup slider event listener
            const slider = document.getElementById('replay-slider');
            if (slider) {
                slider.addEventListener('input', (e) => {
                    try {
                        const position = parseInt(e.target.value, 10);
                        if (typeof position === 'number' && position >= 0) {
                            // Make sure we have the latest data
                            loadDataFromAttributes();
                            if (typeof Livewire !== 'undefined') {
                                if (typeof Livewire.emit === 'function') {
                                    Livewire.emit('replay-seek', { position: position });
                                } else if (typeof Livewire.dispatch === 'function') {
                                    Livewire.dispatch('replay-seek', { position: position });
                                }
                            }
                        }
                    } catch (err) {
                        console.error('Error in slider event:', err);
                    }
                });
            }
        });
    </script>
    
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        @keyframes pulse-marker {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.6);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 6px 16px rgba(59, 130, 246, 0.8);
            }
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }
            100% {
                transform: translateX(100%);
            }
        }

        .animate-shimmer {
            animation: shimmer 3s infinite;
        }

        .replay-marker {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        /* Custom Range Slider Styling */
        input[type="range"].slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            background: transparent;
            cursor: pointer;
        }

        input[type="range"].slider-thumb::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold) 0%, var(--umber) 100%);
            cursor: grab;
            box-shadow: 0 4px 12px rgba(217, 158, 43, 0.6);
            border: 3px solid white;
            transition: all 0.2s ease;
        }

        input[type="range"].slider-thumb::-webkit-slider-thumb:hover {
            transform: scale(1.2);
            box-shadow: 0 6px 16px rgba(217, 158, 43, 0.8);
        }

        input[type="range"].slider-thumb::-webkit-slider-thumb:active {
            cursor: grabbing;
            transform: scale(1.1);
        }

        input[type="range"].slider-thumb::-moz-range-thumb {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold) 0%, var(--umber) 100%);
            cursor: grab;
            box-shadow: 0 4px 12px rgba(217, 158, 43, 0.6);
            border: 3px solid white;
            transition: all 0.2s ease;
        }

        input[type="range"].slider-thumb::-moz-range-thumb:hover {
            transform: scale(1.2);
            box-shadow: 0 6px 16px rgba(217, 158, 43, 0.8);
        }

        input[type="range"].slider-thumb::-moz-range-thumb:active {
            cursor: grabbing;
            transform: scale(1.1);
        }
    </style>
</div>
</div>
