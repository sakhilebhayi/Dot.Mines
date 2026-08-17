/**
 * Real-time Map Manager
 * Handles live updates to Leaflet map markers
 * 
 * Features:
 * - Update machine marker positions
 * - Animate marker movement
 * - Update marker colors based on status
 * - Show speed/heading indicators
 * - Draw location trails
 * - Handle machine offline
 */

class RealtimeMapManager {
    constructor() {
        this.map = null;
        this.markers = new Map(); // machineId -> L.Marker
        this.trails = new Map(); // machineId -> L.Polyline
        this.trailPoints = new Map(); // machineId -> Array of [lat, lng]
        this.maxTrailPoints = 100; // Limit trail history
        this.statusColors = {
            active: '#10b981', // Green
            idle: '#f59e0b', // Amber
            maintenance: '#ef4444', // Red
            offline: '#6b7280', // Gray
        };
        this.listeners = new Map();
    }

    /**
     * Initialize map manager with Leaflet map instance
     * @param {L.Map} leafletMap - Leaflet map instance
     */
    init(leafletMap) {
        this.map = leafletMap;
    }

    /**
     * Add or update machine marker on map
     * @param {object} data - Machine location data
     */
    updateMachineMarker(data) {
        if (!this.map) {
            console.warn('Map not initialized');
            return;
        }

        const { id, name, latitude, longitude, status, speed, bearing } = data;

        // Convert to numbers
        const lat = parseFloat(latitude);
        const lng = parseFloat(longitude);

        if (isNaN(lat) || isNaN(lng)) {
            console.warn('Invalid coordinates for machine:', id);
            return;
        }

        // Create or update marker
        if (this.markers.has(id)) {
            const marker = this.markers.get(id);
            
            // Animate movement
            this.animateMarker(marker, [lat, lng], 500);
            
            // Update marker popup with new info
            const popupContent = this.createMarkerPopup(name, status, speed, bearing);
            marker.setPopupContent(popupContent);
        } else {
            // Create new marker
            const marker = this.createMarker(id, name, lat, lng, status, speed, bearing);
            this.markers.set(id, marker);
            marker.addTo(this.map);
            
        }

        // Update marker color based on status
        this.updateMarkerColor(id, status);

        // Add to trail
        this.addToTrail(id, lat, lng);

        // Emit event
        this.emit('markerUpdated', { machineId: id, lat, lng, status });
    }

    /**
     * Create a new marker with custom icon
     * @private
     */
    createMarker(id, name, lat, lng, status, speed, bearing) {
        const color = this.statusColors[status] || this.statusColors.offline;
        
        // Create custom HTML for marker
        const markerHTML = `
            <div class="marker-icon" style="
                width: 32px;
                height: 32px;
                background-color: ${color};
                border: 2px solid white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                cursor: pointer;
                transition: all 0.3s ease;
            ">
                <div style="width: 8px; height: 8px; background: white; border-radius: 50%;"></div>
            </div>
        `;

        const marker = L.marker([lat, lng], {
            icon: L.divIcon({
                html: markerHTML,
                iconSize: [32, 32],
                className: 'realtime-marker',
                title: name
            })
        });

        // Add popup
        const popupContent = this.createMarkerPopup(name, status, speed, bearing);
        marker.bindPopup(popupContent);

        // Add rotation arrow if heading is available
        if (bearing !== undefined && bearing !== null) {
            this.addHeadingArrow(marker, bearing, color);
        }

        // Store metadata
        marker.data = {
            machineId: id,
            name: name,
            status: status,
            speed: speed,
            bearing: bearing
        };

        return marker;
    }

    /**
     * Create popup content for marker
     * @private
     */
    createMarkerPopup(name, status, speed, bearing) {
        const speedDisplay = speed ? `${speed.toFixed(1)} km/h` : 'N/A';
        const bearingDisplay = bearing ? `${bearing.toFixed(0)}°` : 'N/A';
        const statusLabel = this.getStatusLabel(status);

        return `
            <div class="marker-popup" style="font-size: 12px;">
                <div><strong>${name}</strong></div>
                <div>Status: <span style="color: ${this.statusColors[status]}">${statusLabel}</span></div>
                <div>Speed: ${speedDisplay}</div>
                <div>Heading: ${bearingDisplay}</div>
                <div style="font-size: 10px; color: #666; margin-top: 4px;">
                    Updated: ${new Date().toLocaleTimeString()}
                </div>
            </div>
        `;
    }

    /**
     * Get human-readable status label
     * @private
     */
    getStatusLabel(status) {
        const labels = {
            active: 'Active',
            idle: 'Idle',
            maintenance: 'Maintenance',
            offline: 'Offline'
        };
        return labels[status] || 'Unknown';
    }

    /**
     * Animate marker to new position
     * @private
     */
    animateMarker(marker, newPos, duration = 500) {
        const startPos = marker.getLatLng();
        const startTime = Date.now();

        const animate = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);

            const lat = startPos.lat + (newPos[0] - startPos.lat) * progress;
            const lng = startPos.lng + (newPos[1] - startPos.lng) * progress;

            marker.setLatLng([lat, lng]);

            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };

        animate();
    }

    /**
     * Update marker color based on status
     * @private
     */
    updateMarkerColor(machineId, status) {
        const marker = this.markers.get(machineId);
        if (!marker) return;

        const color = this.statusColors[status] || this.statusColors.offline;
        const markerIcon = marker.getElement();
        
        if (markerIcon) {
            const iconDiv = markerIcon.querySelector('.marker-icon');
            if (iconDiv) {
                iconDiv.style.backgroundColor = color;
                iconDiv.style.transition = 'background-color 0.3s ease';
            }
        }

        // Update stored data
        if (marker.data) {
            marker.data.status = status;
        }
    }

    /**
     * Add heading arrow to marker
     * @private
     */
    addHeadingArrow(marker, bearing, color) {
        // This could be enhanced with a custom icon that rotates
        // For now, we'll add rotation to the marker element
        if (marker._icon) {
            marker._icon.style.transform = `rotate(${bearing}deg)`;
        }
    }

    /**
     * Add position to trail
     * @private
     */
    addToTrail(machineId, lat, lng) {
        if (!this.trailPoints.has(machineId)) {
            this.trailPoints.set(machineId, []);
        }

        const points = this.trailPoints.get(machineId);
        points.push([lat, lng]);

        // Limit trail length
        if (points.length > this.maxTrailPoints) {
            points.shift();
        }

        // Update or create polyline
        if (this.trails.has(machineId)) {
            const polyline = this.trails.get(machineId);
            polyline.setLatLngs(points);
        } else if (points.length > 1) {
            const polyline = L.polyline(points, {
                color: '#3b82f6',
                weight: 2,
                opacity: 0.6,
                dashArray: '5, 5',
                className: 'trail-polyline'
            }).addTo(this.map);

            this.trails.set(machineId, polyline);
        }
    }

    /**
     * Clear trail for machine
     * @param {string} machineId
     */
    clearTrail(machineId) {
        if (this.trails.has(machineId)) {
            this.map.removeLayer(this.trails.get(machineId));
            this.trails.delete(machineId);
        }
        this.trailPoints.delete(machineId);
    }

    /**
     * Clear all trails
     */
    clearAllTrails() {
        for (const [id] of this.trails) {
            this.clearTrail(id);
        }
    }

    /**
     * Handle machine going offline
     * @param {string} machineId
     */
    setMachineOffline(machineId) {
        this.updateMarkerColor(machineId, 'offline');
        
        const marker = this.markers.get(machineId);
        if (marker && marker.data) {
            marker.data.status = 'offline';
            const popupContent = this.createMarkerPopup(
                marker.data.name,
                'offline',
                marker.data.speed,
                marker.data.bearing
            );
            marker.setPopupContent(popupContent);
        }

        this.emit('machineOffline', { machineId });
    }

    /**
     * Remove machine marker
     * @param {string} machineId
     */
    removeMarker(machineId) {
        if (this.markers.has(machineId)) {
            const marker = this.markers.get(machineId);
            this.map.removeLayer(marker);
            this.markers.delete(machineId);
        }
        this.clearTrail(machineId);
    }

    /**
     * Get all markers
     * @returns {Map}
     */
    getMarkers() {
        return this.markers;
    }

    /**
     * Get marker for specific machine
     * @param {string} machineId
     * @returns {L.Marker|null}
     */
    getMarker(machineId) {
        return this.markers.get(machineId) || null;
    }

    /**
     * Center map on machine
     * @param {string} machineId
     */
    focusOnMachine(machineId) {
        const marker = this.markers.get(machineId);
        if (marker) {
            this.map.setView(marker.getLatLng(), 15);
            marker.openPopup();
            this.emit('machineFocused', { machineId });
        }
    }

    /**
     * Pan to fit all markers
     */
    fitAllMarkers() {
        if (this.markers.size === 0) return;

        const group = new L.featureGroup([...this.markers.values()]);
        this.map.fitBounds(group.getBounds().pad(0.1));
    }

    /**
     * Register event listener
     * @param {string} event
     * @param {Function} callback
     */
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);
    }

    /**
     * Emit event
     * @param {string} event
     * @param {*} data
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

    /**
     * Clear all markers and trails
     */
    clear() {
        for (const [id] of this.markers) {
            this.removeMarker(id);
        }
        this.markers.clear();
        this.trails.clear();
        this.trailPoints.clear();
    }

    /**
     * Get statistics
     */
    getStats() {
        return {
            totalMarkers: this.markers.size,
            totalTrails: this.trails.size,
            statusCounts: this.getStatusCounts()
        };
    }

    /**
     * Get count of machines by status
     * @private
     */
    getStatusCounts() {
        const counts = {
            active: 0,
            idle: 0,
            maintenance: 0,
            offline: 0
        };

        for (const [, marker] of this.markers) {
            if (marker.data && marker.data.status) {
                counts[marker.data.status]++;
            }
        }

        return counts;
    }
}

// Export singleton instance
export default new RealtimeMapManager();
