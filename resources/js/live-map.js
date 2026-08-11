/**
 * Live Fleet Tracking map (Leaflet) for the /map page.
 *
 * This used to be an inline <script> block inside
 * resources/views/livewire/live-map.blade.php. PHP's libxml2-based HTML
 * parser (used by Livewire's SupportMultipleRootElementDetection whenever
 * config('app.debug') is true) does not reliably treat inline <script>
 * bodies as opaque raw text, and misparses the HTML-like markup inside this
 * file's JS template literals (marker icon / popup HTML) as real tags,
 * hoisting fragments out as siblings of the component's root element. That
 * false positive throws MultipleRootElementsDetectedException. Living in a
 * separate asset file sidesteps Blade/libxml parsing of this code entirely.
 *
 * All server-provided data is read from data-* attributes on the #map
 * element (see live-map.blade.php) instead of being interpolated here.
 */
document.addEventListener('DOMContentLoaded', function () {
    const mapEl = document.getElementById('map');
    if (!mapEl) {
        return;
    }

    let map = null;
    let markers = {};
    let geofencePolygons = {};
    let currentLayer = null;
    let layers = {};
    let initRetryCount = 0;
    const MAX_INIT_RETRIES = 100;

    let machinesData = JSON.parse(mapEl.dataset.machines || '[]');
    let geofencesData = JSON.parse(mapEl.dataset.geofences || '[]');
    let mineAreasData = JSON.parse(mapEl.dataset.mineAreas || '[]');
    // Keep a copy of the original machines list for client-side filtering
    let originalMachinesData = Array.isArray(machinesData) ? JSON.parse(JSON.stringify(machinesData)) : [];
    let mapStyleData = mapEl.dataset.mapStyle || 'osm';
    let showMachinesData = mapEl.dataset.showMachines === '1';
    let showGeofencesData = mapEl.dataset.showGeofences === '1';
    const centerLat = parseFloat(mapEl.dataset.centerLat);
    const centerLng = parseFloat(mapEl.dataset.centerLng);
    const zoomLevel = parseInt(mapEl.dataset.zoomLevel, 10);

    function debugLog(...args) {
        if (window && window.console) {
        }
    }

    function initMap() {
        try {
            debugLog('initMap called');

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
                }
                map = null;
            }

            // Clear any residual Leaflet state
            if (mapContainer._leaflet_id) {
                delete mapContainer._leaflet_id;
            }

            const loadingEl = document.getElementById('map-loading');
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }

            debugLog('Map center:', centerLat, centerLng, 'Zoom:', zoomLevel);

            // Initialize map with canvas renderer for better performance
            map = L.map('map', {
                preferCanvas: true,
                renderer: L.canvas()
            }).setView([centerLat, centerLng], zoomLevel);

            debugLog('Map initialized:', map);

            // Give the real-time map manager the live Leaflet instance so
            // WebSocket-driven marker updates (see livewire-realtime.js)
            // have somewhere to draw. Without this, RealtimeMapManager's
            // internal `this.map` stays null and updateMachineMarker() is a
            // silent no-op.
            if (window.RealtimeMapManager) {
                window.RealtimeMapManager.init(map);
            }

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

            // Listen for Livewire events
            window.addEventListener('map-updated', (event) => {
                debugLog('Livewire map-updated event received', event.detail);
                updateMap(event.detail[0] || event.detail);
            });

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
            loadingEl.innerHTML = '<div class="text-center"><svg class="w-16 h-16 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg><p class="text-white text-lg mb-2">Map Loading Error</p><p class="text-gray-400 text-sm">' + message + '</p><button onclick="location.reload()" class="mt-4 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg">Refresh Page</button></div>';
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

                const statusColor = {
                    'active': '#10b981',
                    'idle': '#3b82f6',
                    'maintenance': '#ef4444'
                }[machine.status] || '#6b7280';

                const emojiImageUrl = getMachineEmojiImage(machine.machine_type);

                const statusIcon = L.divIcon({
                    html: `
                        <div class="flex items-center justify-center w-10 h-10 rounded-full" style="background-color: ${statusColor}; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.5); padding: 4px;">
                            <img src="${emojiImageUrl}"
                                 style="width: 28px; height: 28px; object-fit: contain;"
                                 onerror="this.style.display='none';"
                                 alt="Machine" />
                        </div>
                    `,
                    iconSize: [40, 40],
                    className: 'machine-marker'
                });

                const marker = L.marker([lat, lng], { icon: statusIcon })
                    .bindPopup(`
                        <div class="p-3 min-w-max">
                            <p class="font-bold text-gray-900">${machine.name}</p>
                            <p class="text-sm text-gray-700">${machine.manufacturer || 'Unknown'} ${machine.model || ''}</p>
                            <p class="text-xs text-gray-600 mt-1">
                                <span class="inline-block px-2 py-1 rounded text-white" style="background-color: ${statusColor};">
                                    ${machine.status.toUpperCase()}
                                </span>
                            </p>
                            <p class="text-xs text-gray-600 mt-1">
                                Serial: ${machine.serial_number || 'N/A'}
                            </p>
                            <p class="text-xs text-gray-600">
                                Capacity: ${machine.capacity ? machine.capacity + ' tons' : 'N/A'}
                            </p>
                            <a href="/fleet/${machine.id}" class="text-blue-600 hover:underline text-xs mt-2 inline-block">
                                View Details →
                            </a>
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

    function clearMarkers() {
        if (!map) return;
        Object.values(markers).forEach(marker => {
            try {
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

        // Handle machines update
        if (data.machines !== undefined) {
            try {
                clearMarkers();
                if (Array.isArray(data.machines) && data.machines.length > 0) {
                    machinesData = data.machines;
                    showMachinesData = true;
                    addMachineMarkers();
                } else {
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
