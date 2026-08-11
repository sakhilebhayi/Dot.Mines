/**
 * Geofence Visualization Manager
 * Handles real-time geofence boundary visualization
 * 
 * Features:
 * - Draw geofence boundaries as polygons
 * - Animate entry/exit effects
 * - Color-code by status
 * - Show entry/exit events
 * - Heat map of geofence activity
 * - Entry/exit counters
 */

class GeofenceVisualizationManager {
    constructor() {
        this.map = null;
        this.geofences = new Map(); // geofenceId -> { polygon, metadata }
        this.entryExitMarkers = new Map(); // eventId -> marker
        this.geofenceStats = new Map(); // geofenceId -> { entries, exits }
        this.listeners = new Map();
        this.animationDuration = 600; // ms
        this.showActivityTrail = true;
    }

    /**
     * Initialize with Leaflet map
     * @param {L.Map} leafletMap
     */
    init(leafletMap) {
        this.map = leafletMap;
    }

    /**
     * Draw geofence on map
     * @param {object} geofenceData
     */
    drawGeofence(geofenceData) {
        if (!this.map) {
            console.warn('Map not initialized');
            return;
        }

        const {
            id,
            name,
            center_latitude,
            center_longitude,
            coordinates
        } = geofenceData;

        if (!coordinates || coordinates.length === 0) {
            console.warn(`No coordinates for geofence: ${name}`);
            return;
        }

        // Parse coordinates if string
        const coords = typeof coordinates === 'string' 
            ? JSON.parse(coordinates) 
            : coordinates;

        // Convert to Leaflet format [lat, lng]
        const latlngs = coords.map(c => [c.lat || c[0], c.lng || c[1]]);

        // Create polygon
        const polygon = L.polygon(latlngs, {
            color: '#8b5cf6',
            weight: 2,
            opacity: 0.7,
            fillColor: '#c4b5fd',
            fillOpacity: 0.1,
            className: `geofence-${id}`,
            interactive: true
        }).addTo(this.map);

        // Create popup
        const popupContent = this.createGeofencePopup(name, id);
        polygon.bindPopup(popupContent);

        // Store reference
        this.geofences.set(id, {
            polygon,
            metadata: geofenceData,
            color: '#8b5cf6',
            highlighted: false
        });

        // Initialize stats
        if (!this.geofenceStats.has(id)) {
            this.geofenceStats.set(id, {
                entries: 0,
                exits: 0,
                lastEntry: null,
                lastExit: null
            });
        }

        // Add click handler
        polygon.on('click', () => {
            this.emit('geofenceClicked', { geofenceId: id, name });
        });

        // Add hover effects
        polygon.on('mouseover', () => {
            this.highlightGeofence(id);
        });

        polygon.on('mouseout', () => {
            this.unhighlightGeofence(id);
        });

    }

    /**
     * Create popup content
     * @private
     */
    createGeofencePopup(name, id) {
        const stats = this.geofenceStats.get(id) || { entries: 0, exits: 0 };

        return `
            <div class="geofence-popup" style="font-size: 12px;">
                <div><strong>${name}</strong></div>
                <div>Entries: <strong>${stats.entries}</strong></div>
                <div>Exits: <strong>${stats.exits}</strong></div>
                ${stats.lastEntry ? `<div style="font-size: 10px; color: #666; margin-top: 4px;">
                    Last: ${new Date(stats.lastEntry).toLocaleTimeString()}
                </div>` : ''}
            </div>
        `;
    }

    /**
     * Handle geofence entry event
     * @param {object} data
     */
    onGeofenceEntry(data) {
        const { geofence_id: geofenceId, machine_id: machineId, machine_name: machineName } = data;

        // Update stats
        const stats = this.geofenceStats.get(geofenceId);
        if (stats) {
            stats.entries++;
            stats.lastEntry = new Date().toISOString();
        }

        // Update geofence highlighting
        this.pulseGeofence(geofenceId, 'entry');

        // Show marker at entry point
        if (data.latitude && data.longitude) {
            this.showEntryMarker(geofenceId, data.latitude, data.longitude, machineName, 'entry');
        }

        // Emit event
        this.emit('machineEnteredGeofence', {
            geofenceId,
            machineId,
            machineName
        });

    }

    /**
     * Handle geofence exit event
     * @param {object} data
     */
    onGeofenceExit(data) {
        const { geofence_id: geofenceId, machine_id: machineId, machine_name: machineName } = data;

        // Update stats
        const stats = this.geofenceStats.get(geofenceId);
        if (stats) {
            stats.exits++;
            stats.lastExit = new Date().toISOString();
        }

        // Update geofence highlighting
        this.pulseGeofence(geofenceId, 'exit');

        // Show marker at exit point
        if (data.latitude && data.longitude) {
            this.showEntryMarker(geofenceId, data.latitude, data.longitude, machineName, 'exit');
        }

        // Emit event
        this.emit('machineExitedGeofence', {
            geofenceId,
            machineId,
            machineName
        });

    }

    /**
     * Pulse geofence on entry/exit
     * @private
     */
    pulseGeofence(geofenceId, type) {
        const geofenceData = this.geofences.get(geofenceId);
        if (!geofenceData) return;

        const polygon = geofenceData.polygon;
        const originalColor = polygon.options.color;
        const pulseColor = type === 'entry' ? '#10b981' : '#ef4444';

        // Pulse animation
        for (let i = 0; i < 3; i++) {
            setTimeout(() => {
                polygon.setStyle({ color: pulseColor });
            }, i * 200);

            setTimeout(() => {
                polygon.setStyle({ color: originalColor });
            }, i * 200 + 100);
        }
    }

    /**
     * Show entry/exit marker
     * @private
     */
    showEntryMarker(geofenceId, lat, lng, machineName, type) {
        const icon = type === 'entry' ? '🟢' : '🔴';
        const color = type === 'entry' ? '#10b981' : '#ef4444';

        const marker = L.marker([lat, lng], {
            icon: L.divIcon({
                html: `<div style="font-size: 24px;">${icon}</div>`,
                iconSize: [24, 24],
                className: `entry-exit-marker-${type}`
            })
        }).addTo(this.map);

        const popupText = type === 'entry'
            ? `${machineName} entered`
            : `${machineName} exited`;

        marker.bindPopup(popupText);

        // Auto-remove after delay
        const eventId = `${geofenceId}-${type}-${Date.now()}`;
        this.entryExitMarkers.set(eventId, marker);

        setTimeout(() => {
            if (this.map && marker) {
                this.map.removeLayer(marker);
                this.entryExitMarkers.delete(eventId);
            }
        }, 10000); // Remove after 10 seconds
    }

    /**
     * Highlight geofence
     * @private
     */
    highlightGeofence(geofenceId) {
        const geofenceData = this.geofences.get(geofenceId);
        if (!geofenceData) return;

        const polygon = geofenceData.polygon;
        polygon.setStyle({
            weight: 4,
            opacity: 1.0,
            fillOpacity: 0.3
        });

        geofenceData.highlighted = true;
    }

    /**
     * Unhighlight geofence
     * @private
     */
    unhighlightGeofence(geofenceId) {
        const geofenceData = this.geofences.get(geofenceId);
        if (!geofenceData) return;

        const polygon = geofenceData.polygon;
        polygon.setStyle({
            weight: 2,
            opacity: 0.7,
            fillOpacity: 0.1
        });

        geofenceData.highlighted = false;
    }

    /**
     * Update geofence styling
     * @param {string} geofenceId
     * @param {object} style
     */
    updateGeofenceStyle(geofenceId, style) {
        const geofenceData = this.geofences.get(geofenceId);
        if (geofenceData) {
            geofenceData.polygon.setStyle(style);
        }
    }

    /**
     * Get geofence by ID
     * @param {string} geofenceId
     */
    getGeofence(geofenceId) {
        return this.geofences.get(geofenceId);
    }

    /**
     * Get all geofences
     */
    getAllGeofences() {
        return new Map(this.geofences);
    }

    /**
     * Remove geofence
     * @param {string} geofenceId
     */
    removeGeofence(geofenceId) {
        const geofenceData = this.geofences.get(geofenceId);
        if (geofenceData && this.map) {
            this.map.removeLayer(geofenceData.polygon);
            this.geofences.delete(geofenceId);
        }
    }

    /**
     * Clear all geofences
     */
    clearAll() {
        for (const [id] of this.geofences) {
            this.removeGeofence(id);
        }
        this.entryExitMarkers.clear();
    }

    /**
     * Get geofence statistics
     */
    getStats(geofenceId) {
        return this.geofenceStats.get(geofenceId) || null;
    }

    /**
     * Get all statistics
     */
    getAllStats() {
        return new Map(this.geofenceStats);
    }

    /**
     * Register event listener
     */
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);
    }

    /**
     * Emit event
     */
    emit(event, data) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Error in listener for event ${event}:`, error);
                }
            });
        }
    }
}

// Export singleton instance
export default new GeofenceVisualizationManager();
