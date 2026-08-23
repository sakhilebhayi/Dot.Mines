// Fleet Movement Replay page bundle (R7 decompose).
//
// This is the former ~1,240-line inline <script> from
// fleet-movement-replay.blade.php, moved verbatim into a dedicated Vite
// entry (same pattern as live-map.js). Leaflet arrives on window.L via the
// app.js bundle; replay data still rides the component's data-* attributes
// (loadDataFromAttributes) so Livewire re-renders keep working unchanged.
import '../css/fleet-movement-replay.css';

                window.hideMapOverlay = function () {
                    const overlay = document.getElementById('map-overlay');
                    if (overlay) {
                        overlay.style.display = 'none';
                    }
                }
                
                // Show overlay when no data
                window.showMapOverlay = function () {
                    const overlay = document.getElementById('map-overlay');
                    if (overlay) {
                        overlay.style.display = 'flex';
                    }
                }

        // Initial view state comes from data attributes on the component root
        // (this file is a shared Vite bundle, so no Blade interpolation).
        const replayRoot = document.querySelector('[data-replay-center-lat]');
        window.replayState = {
            centerLat: parseFloat(replayRoot?.dataset.replayCenterLat ?? '-26.2041'),
            centerLng: parseFloat(replayRoot?.dataset.replayCenterLng ?? '28.0473'),
            zoomLevel: parseInt(replayRoot?.dataset.replayZoomLevel ?? '10', 10)
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
