<div
    x-data="haulDispatchMap()"
    x-init="init()"
    @haul-dispatch:map-data.window="updateMapData($event.detail.dispatches)"
    @haul-dispatch:select.window="focusDispatch($event.detail.id)"
    wire:poll.5s="loadDispatches"
    class="rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden mb-8"
>
    {{-- ── Header ── --}}
    <div class="px-5 py-4 flex items-center justify-between bg-gradient-to-r from-amber-500 to-yellow-600">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-white/20 rounded-lg backdrop-blur-sm">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-white leading-tight">Haul Dispatch Tracker</h2>
                <p class="text-xs text-yellow-100">Real-time haul truck dispatch monitoring</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            {{-- Live indicator --}}
            <span class="hidden sm:flex items-center gap-1.5 text-xs text-yellow-100 bg-white/10 px-2.5 py-1 rounded-full">
                <span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></span>
                Live · 5 s
            </span>

            {{-- Status filter tabs --}}
            <div class="flex items-center gap-0.5 bg-black/20 rounded-lg p-0.5">
                @foreach(['all', 'loading', 'hauling', 'dumping', 'returning'] as $filterStatus)
                    <button
                        wire:click="filterByStatus('{{ $filterStatus }}')"
                        class="px-2.5 py-1 text-xs rounded-md font-medium transition-all
                            {{ $statusFilter === $filterStatus
                                ? 'bg-white text-amber-700 shadow'
                                : 'text-white hover:bg-white/20' }}"
                    >
                        {{ ucfirst($filterStatus) }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Loading ── --}}
    @if ($isLoading)
        <div class="flex items-center justify-center h-64 bg-white dark:bg-gray-800">
            <svg class="animate-spin h-8 w-8 text-amber-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
        </div>

    {{-- ── Empty state ── --}}
    @elseif (count($activeDispatches) === 0)
        <div class="flex flex-col items-center justify-center py-16 bg-white dark:bg-gray-800 text-center px-6">
            <div class="w-16 h-16 bg-amber-100 dark:bg-amber-900/30 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">No Active Dispatches</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                @if ($statusFilter !== 'all')
                    No trucks with status <strong>{{ $statusFilter }}</strong> right now.
                    <button wire:click="filterByStatus('all')" class="text-amber-600 dark:text-amber-400 hover:underline ml-1">Clear filter</button>
                @else
                    No haul trucks are currently dispatched. Dispatch data will appear here in real time.
                @endif
            </p>
        </div>

    {{-- ── Main content ── --}}
    @else
        <div class="grid grid-cols-1 lg:grid-cols-5 bg-white dark:bg-gray-800" style="min-height:420px">

            {{-- ── Map Panel (3/5) ── --}}
            <div class="lg:col-span-3 relative border-b lg:border-b-0 lg:border-r border-gray-200 dark:border-gray-700"
                 style="min-height:420px" wire:ignore>
                <div id="haul-dispatch-map" class="absolute inset-0 w-full h-full"></div>

                {{-- Map legend overlay --}}
                <div class="absolute bottom-3 left-3 z-[1000] bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm
                            rounded-lg px-3 py-2 shadow text-xs space-y-1.5 pointer-events-none">
                    <p class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Legend</p>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-amber-500 border-2 border-white shadow-sm"></span>
                        <span class="text-gray-600 dark:text-gray-300">Haul Truck</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-green-500 border-2 border-white shadow-sm"></span>
                        <span class="text-gray-600 dark:text-gray-300">Loading Point</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-red-500 border-2 border-white shadow-sm"></span>
                        <span class="text-gray-600 dark:text-gray-300">Dump Point</span>
                    </div>
                    <div class="flex items-center gap-1.5 pt-0.5">
                        <span class="w-5 h-0 border-t-2 border-dashed border-amber-400"></span>
                        <span class="text-gray-600 dark:text-gray-300">Route</span>
                    </div>
                </div>

                {{-- Truck count badge --}}
                <div class="absolute top-3 right-3 z-[1000] bg-amber-500 text-white text-xs font-bold
                            px-2.5 py-1 rounded-full shadow pointer-events-none">
                    {{ count($activeDispatches) }} truck{{ count($activeDispatches) !== 1 ? 's' : '' }}
                </div>
            </div>

            {{-- ── Truck List Panel (2/5) ── --}}
            <div class="lg:col-span-2 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700"
                 style="max-height:420px">

                @foreach ($activeDispatches as $d)
                    @php
                        $statusColors = [
                            'hauling'   => ['bg' => 'bg-amber-500', 'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400', 'border' => 'border-l-amber-500'],
                            'loading'   => ['bg' => 'bg-green-500',  'badge' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',   'border' => 'border-l-green-500'],
                            'dumping'   => ['bg' => 'bg-red-500',    'badge' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',           'border' => 'border-l-red-500'],
                            'returning' => ['bg' => 'bg-blue-500',   'badge' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',       'border' => 'border-l-blue-500'],
                        ];
                        $sc     = $statusColors[$d['status']] ?? ['bg' => 'bg-gray-400', 'badge' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400', 'border' => 'border-l-gray-400'];
                        $tonPct = $d['machine_capacity'] > 0
                            ? min(100, ($d['current_tonnage'] / $d['machine_capacity']) * 100)
                            : 0;
                        $fuelPct = min(100, $d['fuel_percentage']);
                        $fuelCls = $fuelPct < 20 ? 'bg-red-500' : ($fuelPct < 50 ? 'bg-yellow-500' : 'bg-green-500');
                        $fuelTextCls = $fuelPct < 20 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-700 dark:text-gray-300';
                        $selected = $selectedDispatchId === $d['id'];
                    @endphp

                    <button
                        wire:click="selectDispatch({{ $d['id'] }})"
                        class="w-full text-left px-4 py-3.5 transition-colors
                            hover:bg-gray-50 dark:hover:bg-gray-700/40
                            {{ $selected ? 'bg-amber-50 dark:bg-amber-900/20 border-l-4 ' . $sc['border'] : 'border-l-4 border-transparent' }}"
                    >
                        {{-- Truck header row --}}
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2.5">
                                {{-- Status dot + emoji --}}
                                <div class="w-8 h-8 {{ $sc['bg'] }} rounded-lg flex items-center justify-center text-sm shadow-sm shrink-0">
                                    🚛
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate leading-tight">
                                        {{ $d['machine_name'] }}
                                    </p>
                                    <span class="inline-block text-xs px-1.5 py-0.5 rounded-full font-medium {{ $sc['badge'] }}">
                                        {{ ucfirst($d['status']) }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right shrink-0 ml-2">
                                <p class="text-xs font-bold text-gray-900 dark:text-white">
                                    ETA: {{ $d['eta_formatted'] }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ number_format($d['current_speed_kmh'], 0) }} km/h
                                </p>
                            </div>
                        </div>

                        {{-- Route line --}}
                        <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 mb-2.5">
                            <span class="w-2 h-2 bg-green-500 rounded-full shrink-0"></span>
                            <span class="truncate max-w-[80px]">{{ $d['origin_name'] }}</span>
                            <svg class="w-3 h-3 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                            <span class="w-2 h-2 bg-red-500 rounded-full shrink-0"></span>
                            <span class="truncate max-w-[80px]">{{ $d['destination_name'] }}</span>
                        </div>

                        {{-- Distance row --}}
                        @if ($d['total_distance_km'] > 0)
                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-2.5">
                            <span>{{ number_format($d['distance_remaining_km'], 1) }} km remaining</span>
                            <span class="text-gray-400"> / {{ number_format($d['total_distance_km'], 1) }} km total</span>
                        </div>
                        @endif

                        {{-- Tonnage + Fuel bars --}}
                        <div class="grid grid-cols-2 gap-3">
                            {{-- Tonnage --}}
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-gray-500 dark:text-gray-400">Load</span>
                                    <span class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ number_format($d['current_tonnage'], 1) }}t
                                        @if ($d['machine_capacity'] > 0)
                                            <span class="font-normal text-gray-400">/{{ number_format($d['machine_capacity'], 0) }}t</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                    <div class="bg-amber-500 h-1.5 rounded-full transition-all duration-500"
                                         style="width: {{ number_format($tonPct, 1) }}%"></div>
                                </div>
                            </div>

                            {{-- Fuel --}}
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-gray-500 dark:text-gray-400">Fuel</span>
                                    <span class="{{ $fuelTextCls }}">{{ number_format($fuelPct, 0) }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                    <div class="{{ $fuelCls }} h-1.5 rounded-full transition-all duration-500"
                                         style="width: {{ number_format($fuelPct, 1) }}%"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Mine area tag --}}
                        @if ($d['mine_area'] !== '—')
                        <div class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                            📍 {{ $d['mine_area'] }} · {{ $d['started_at'] }}
                        </div>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- ── Summary Footer ── --}}
        @php
            $haulingCount  = collect($activeDispatches)->where('status', 'hauling')->count();
            $loadingCount  = collect($activeDispatches)->where('status', 'loading')->count();
            $dumpingCount  = collect($activeDispatches)->where('status', 'dumping')->count();
            $returningCount= collect($activeDispatches)->where('status', 'returning')->count();
            $totalTonnage  = collect($activeDispatches)->sum('current_tonnage');
            $avgFuel       = collect($activeDispatches)->avg('fuel_percentage') ?? 0;
        @endphp
        <div class="border-t border-gray-200 dark:border-gray-700 px-5 py-2.5
                    bg-gray-50 dark:bg-gray-900/40 flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-3 text-xs flex-wrap">
                @if ($haulingCount)  <span class="flex items-center gap-1.5"><span class="w-2 h-2 bg-amber-500 rounded-full"></span><span class="text-gray-600 dark:text-gray-400">Hauling <strong class="text-gray-900 dark:text-white">{{ $haulingCount }}</strong></span></span> @endif
                @if ($loadingCount)  <span class="flex items-center gap-1.5"><span class="w-2 h-2 bg-green-500 rounded-full"></span><span class="text-gray-600 dark:text-gray-400">Loading <strong class="text-gray-900 dark:text-white">{{ $loadingCount }}</strong></span></span> @endif
                @if ($dumpingCount)  <span class="flex items-center gap-1.5"><span class="w-2 h-2 bg-red-500 rounded-full"></span><span class="text-gray-600 dark:text-gray-400">Dumping <strong class="text-gray-900 dark:text-white">{{ $dumpingCount }}</strong></span></span> @endif
                @if ($returningCount)<span class="flex items-center gap-1.5"><span class="w-2 h-2 bg-blue-500 rounded-full"></span><span class="text-gray-600 dark:text-gray-400">Returning <strong class="text-gray-900 dark:text-white">{{ $returningCount }}</strong></span></span> @endif
            </div>
            <div class="flex items-center gap-4 text-xs">
                <span class="text-gray-500 dark:text-gray-400">
                    Total load: <strong class="text-amber-600 dark:text-amber-400">{{ number_format($totalTonnage, 1) }} t</strong>
                </span>
                <span class="text-gray-500 dark:text-gray-400">
                    Avg fuel:
                    <strong class="{{ $avgFuel < 25 ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-gray-200' }}">
                        {{ number_format($avgFuel, 0) }}%
                    </strong>
                </span>
                <span class="text-gray-400 dark:text-gray-500 hidden sm:inline" x-text="'Updated ' + lastUpdate"></span>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════════
         Alpine.js Leaflet Map Controller
         ══════════════════════════════════════════════════════════════════════════ --}}
    <script>
function haulDispatchMap() {
    return {
        map: null,
        initialized: false,
        truckMarkers:  {},   // id → L.Marker
        originMarkers: {},   // id → L.Marker
        destMarkers:   {},   // id → L.Marker
        routeLines:    {},   // id → L.Polyline (origin→current→dest dashed)
        trailLines:    {},   // id → L.Polyline (GPS breadcrumb)
        lastUpdate: 'just now',

        // ── Lifecycle ──────────────────────────────────────────────────────
        init() {
            this.$nextTick(() => this.initMap());
            setInterval(() => {
                this.lastUpdate = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }, 30_000);
        },

        initMap() {
            if (typeof L === 'undefined') return;
            const el = document.getElementById('haul-dispatch-map');
            // Reset if the element was removed/re-created by Livewire
            if (!el) { this.initialized = false; return; }
            if (this.initialized && this.map) return;
            // If a stale Leaflet instance exists on a new element, clean it up
            if (this.map) {
                try { this.map.remove(); } catch (_) {}
                this.map = null;
            }

            this.map = L.map('haul-dispatch-map', {
                center: [-28.4793, 24.6727],
                zoom: 13,
                zoomControl: true,
                attributionControl: false,
            });

            // Esri World Imagery (satellite) – same as LiveMap.php
            L.tileLayer(
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                { attribution: 'Esri', maxZoom: 20 }
            ).addTo(this.map);

            // Labels overlay
            L.tileLayer(
                'https://stamen-tiles.a.ssl.fastly.net/toner-hybrid/{z}/{x}/{y}.png',
                { opacity: 0.6, maxZoom: 20 }
            ).addTo(this.map);

            this.initialized = true;

            // Seed from server-side PHP variable on first paint
            const initial = @json($mapDispatches);
            if (Array.isArray(initial) && initial.length) {
                this.renderDispatches(initial);
            }
        },

        // ── Icon Factories ─────────────────────────────────────────────────
        truckIcon(status, heading) {
            const palette = {
                hauling:   '#f59e0b',
                loading:   '#10b981',
                dumping:   '#ef4444',
                returning: '#3b82f6',
                idle:      '#6b7280',
                parked:    '#9ca3af',
            };
            const color = palette[status] || '#6b7280';

            // Pulse ring for active hauling
            const pulse = status === 'hauling'
                ? `<circle cx="20" cy="20" r="18" fill="none" stroke="${color}" stroke-width="2" opacity="0.4">
                       <animate attributeName="r" values="14;22;14" dur="2s" repeatCount="indefinite"/>
                       <animate attributeName="opacity" values="0.6;0;0.6" dur="2s" repeatCount="indefinite"/>
                   </circle>`
                : '';

            const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40">
                ${pulse}
                <circle cx="20" cy="20" r="14" fill="${color}" stroke="white" stroke-width="2.5"/>
                <text x="20" y="25" text-anchor="middle" font-size="15" fill="white">🚛</text>
            </svg>`;

            return L.divIcon({
                html: `<div style="transform:rotate(${heading || 0}deg);width:40px;height:40px">${svg}</div>`,
                className: '',
                iconSize: [40, 40],
                iconAnchor: [20, 20],
                popupAnchor: [0, -22],
            });
        },

        pinIcon(type) {
            const color  = type === 'origin' ? '#10b981' : '#ef4444';
            const label  = type === 'origin' ? 'L'       : 'D';
            const shadow = type === 'origin' ? '#065f46' : '#7f1d1d';

            const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="30" height="40" viewBox="0 0 30 40">
                <defs>
                    <filter id="shadow-${type}" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="${shadow}" flood-opacity="0.4"/>
                    </filter>
                </defs>
                <path d="M15 0C6.72 0 0 6.72 0 15c0 10.5 15 25 15 25s15-14.5 15-25C30 6.72 23.28 0 15 0z"
                      fill="${color}" filter="url(#shadow-${type})"/>
                <circle cx="15" cy="15" r="8" fill="white" opacity="0.25"/>
                <text x="15" y="19" text-anchor="middle" font-size="11" font-weight="700"
                      font-family="sans-serif" fill="white">${label}</text>
            </svg>`;

            return L.divIcon({
                html: svg,
                className: '',
                iconSize: [30, 40],
                iconAnchor: [15, 40],
                popupAnchor: [0, -42],
            });
        },

        // ── Render All Dispatches ──────────────────────────────────────────
        renderDispatches(dispatches) {
            if (!this.map || !this.initialized) return;

            const allPositions = [];

            dispatches.forEach(d => {
                const id = d.id;

                // ── Truck marker ──────────────────────────────────────────
                if (d.current_lat != null && d.current_lng != null) {
                    const pos = [parseFloat(d.current_lat), parseFloat(d.current_lng)];
                    allPositions.push(pos);

                    if (this.truckMarkers[id]) {
                        this.truckMarkers[id]
                            .setLatLng(pos)
                            .setIcon(this.truckIcon(d.status, d.heading || 0))
                            .setPopupContent(this.buildPopup(d));
                    } else {
                        this.truckMarkers[id] = L.marker(pos, {
                            icon: this.truckIcon(d.status, d.heading || 0),
                            zIndexOffset: 300,
                        })
                        .bindPopup(this.buildPopup(d), { maxWidth: 220 })
                        .addTo(this.map);
                    }
                }

                // ── Origin marker (loading point) ─────────────────────────
                if (d.origin_lat != null && d.origin_lng != null) {
                    const pos = [parseFloat(d.origin_lat), parseFloat(d.origin_lng)];
                    allPositions.push(pos);

                    if (!this.originMarkers[id]) {
                        this.originMarkers[id] = L.marker(pos, {
                            icon: this.pinIcon('origin'),
                            zIndexOffset: 100,
                        })
                        .bindTooltip(
                            `<strong>${d.origin_name}</strong><br><small class="text-green-600">⬤ Loading Point</small>`,
                            { direction: 'top', className: 'haul-tooltip' }
                        )
                        .addTo(this.map);
                    }
                }

                // ── Destination marker (dump point) ───────────────────────
                if (d.dest_lat != null && d.dest_lng != null) {
                    const pos = [parseFloat(d.dest_lat), parseFloat(d.dest_lng)];
                    allPositions.push(pos);

                    if (!this.destMarkers[id]) {
                        this.destMarkers[id] = L.marker(pos, {
                            icon: this.pinIcon('destination'),
                            zIndexOffset: 100,
                        })
                        .bindTooltip(
                            `<strong>${d.dest_name}</strong><br><small class="text-red-600">⬤ Dump Point</small>`,
                            { direction: 'top', className: 'haul-tooltip' }
                        )
                        .addTo(this.map);
                    }
                }

                // ── Route line (dashed: origin → current pos → dest) ──────
                const routePts = [];
                if (d.origin_lat != null) routePts.push([parseFloat(d.origin_lat), parseFloat(d.origin_lng)]);

                // Insert last ~20 recorded path points between origin and current
                if (Array.isArray(d.path) && d.path.length > 1) {
                    d.path.slice(-20).forEach(p => routePts.push([parseFloat(p[0]), parseFloat(p[1])]));
                } else if (d.current_lat != null) {
                    routePts.push([parseFloat(d.current_lat), parseFloat(d.current_lng)]);
                }

                if (d.dest_lat != null) routePts.push([parseFloat(d.dest_lat), parseFloat(d.dest_lng)]);

                if (routePts.length >= 2) {
                    if (this.routeLines[id]) {
                        this.routeLines[id].setLatLngs(routePts);
                    } else {
                        this.routeLines[id] = L.polyline(routePts, {
                            color: '#f59e0b',
                            weight: 2.5,
                            opacity: 0.75,
                            dashArray: '7 5',
                        }).addTo(this.map);
                    }
                }

                // ── GPS trail (breadcrumb polyline) ────────────────────────
                if (Array.isArray(d.path) && d.path.length >= 2) {
                    const trail = d.path.map(p => [parseFloat(p[0]), parseFloat(p[1])]);
                    if (this.trailLines[id]) {
                        this.trailLines[id].setLatLngs(trail);
                    } else {
                        this.trailLines[id] = L.polyline(trail, {
                            color: '#fcd34d',
                            weight: 2,
                            opacity: 0.5,
                        }).addTo(this.map);
                    }
                }
            });

            // Fit bounds to show all active trucks & waypoints
            if (allPositions.length > 0) {
                try {
                    this.map.fitBounds(
                        L.latLngBounds(allPositions),
                        { padding: [48, 48], maxZoom: 16, animate: true }
                    );
                } catch (_) {}
            }
        },

        // ── Popup HTML ─────────────────────────────────────────────────────
        buildPopup(d) {
            const statusColor = {
                hauling: '#f59e0b', loading: '#10b981',
                dumping: '#ef4444', returning: '#3b82f6', idle: '#6b7280',
            }[d.status] || '#6b7280';

            return `<div style="font-family:system-ui,sans-serif;min-width:180px;padding:2px 0">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                    <span style="font-size:20px">🚛</span>
                    <div>
                        <strong style="font-size:13px;display:block">${d.machine_name}</strong>
                        <span style="font-size:11px;background:${statusColor};color:#fff;
                              padding:1px 6px;border-radius:9999px;font-weight:600">
                            ${d.status.charAt(0).toUpperCase() + d.status.slice(1)}
                        </span>
                    </div>
                </div>
                <div style="font-size:11px;color:#555;display:flex;align-items:center;gap:4px">
                    <span style="color:#10b981;font-weight:700">⬤</span>
                    <span>${d.origin_name}</span>
                    <span style="color:#999">→</span>
                    <span style="color:#ef4444;font-weight:700">⬤</span>
                    <span>${d.dest_name}</span>
                </div>
            </div>`;
        },

        // ── Event Handlers ─────────────────────────────────────────────────
        updateMapData(dispatches) {
            if (!this.initialized || !this.map) {
                this.$nextTick(() => {
                    this.initMap();
                    if (this.initialized && Array.isArray(dispatches) && dispatches.length) {
                        this.renderDispatches(dispatches);
                    }
                });
                return;
            }
            if (Array.isArray(dispatches) && dispatches.length) {
                this.renderDispatches(dispatches);
            }
            this.lastUpdate = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        focusDispatch(id) {
            if (!id || !this.map) return;
            const marker = this.truckMarkers[id];
            if (marker) {
                this.map.flyTo(marker.getLatLng(), 16, { duration: 0.8 });
                marker.openPopup();
            }
        },
    };
}

(function injectHaulTooltipStyles() {
    if (document.getElementById('haul-dispatch-styles')) return;
    const s = document.createElement('style');
    s.id = 'haul-dispatch-styles';
    s.textContent = [
        '.haul-tooltip{background:rgba(17,24,39,0.85)!important;color:#f3f4f6!important;',
        'border:none!important;border-radius:6px!important;font-size:11px!important;',
        'padding:5px 8px!important;backdrop-filter:blur(4px)}',
        '.haul-tooltip.leaflet-tooltip-top::before{border-top-color:rgba(17,24,39,0.85)!important}',
    ].join('');
    document.head.appendChild(s);
})();
    </script>
</div>
