/**
 * Reverb WebSocket Service
 * Handles real-time event subscriptions and listeners
 *
 * This service manages connections to Laravel Reverb for real-time updates
 * including machine locations, alerts, geofence events, and machine status changes.
 *
 * IMPORTANT: every channel.listen() call below must use the event's
 * broadcastAs() name (e.g. '.machine.location.updated'), not its PHP class
 * name. Laravel Echo's default EventFormatter prefixes a bare event name
 * with the "App.Events" namespace and listens for that -- which never
 * matches these events' overridden, short broadcastAs() names. A leading
 * "." tells Echo to use the name exactly as given, un-namespaced.
 */

class ReverbService {
    constructor() {
        this.subscriptions = new Map();
        this.listeners = new Map();
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 3000; // 3 seconds
    }

    /**
     * Initialize Reverb connection
     * @param {string} userId - Current user ID
     * @param {string} teamId - Current team ID
     * @returns {Promise<void>}
     */
    async init(userId, teamId) {
        try {
            // Check if Echo is already loaded
            if (typeof window.Echo === 'undefined') {
                console.error('Laravel Echo not found. Ensure it is loaded before ReverbService.');
                return;
            }

            this.userId = userId;
            this.teamId = teamId;
            this.isConnected = true;
            this.reconnectAttempts = 0;

        } catch (error) {
            console.error('❌ Failed to initialize Reverb service:', error);
            this.handleReconnection();
        }
    }

    /**
     * Subscribe to machine location updates
     * @param {string} machineId - Machine ID to subscribe to
     * @param {Function} callback - Function called when location updates
     */
    subscribeMachineLocation(machineId, callback) {
        const channelName = `machine.${machineId}`;

        if (this.subscriptions.has(channelName)) {
            console.warn(`Already subscribed to ${channelName}`);
            return;
        }

        try {
            const channel = window.Echo.private(channelName);

            channel.listen('.machine.location.updated', (data) => {
                callback(data);
            });

            this.subscriptions.set(channelName, channel);
        } catch (error) {
            console.error(`❌ Failed to subscribe to ${channelName}:`, error);
        }
    }

    /**
     * Subscribe to all team machine location updates
     * @param {Function} callback - Function called when any location updates
     */
    subscribeTeamLocations(callback) {
        const channelName = `team.${this.teamId}`;

        if (this.subscriptions.has(channelName)) {
            console.warn(`Already subscribed to ${channelName}`);
            return;
        }

        try {
            const channel = window.Echo.private(channelName);

            channel.listen('.machine.location.updated', (data) => {
                callback(data);
            });

            this.subscriptions.set(channelName, channel);
        } catch (error) {
            console.error(`❌ Failed to subscribe to ${channelName}:`, error);
        }
    }

    /**
     * Subscribe to team alerts
     * @param {Function} callback - Function called when alerts are triggered
     */
    subscribeTeamAlerts(callback) {
        const channelName = `alerts.team.${this.teamId}`;

        if (this.subscriptions.has(channelName)) {
            console.warn(`Already subscribed to ${channelName}`);
            return;
        }

        try {
            const channel = window.Echo.private(channelName);

            channel.listen('.alert.triggered', (data) => {
                callback(data);
            });

            this.subscriptions.set(channelName, channel);
        } catch (error) {
            console.error(`❌ Failed to subscribe to ${channelName}:`, error);
        }
    }

    /**
     * Subscribe to predictive maintenance alerts
     * @param {Function} callback - Function called when a maintenance alert fires
     */
    subscribeMaintenanceAlerts(callback) {
        const channelName = `team.${this.teamId}.alerts`;

        if (this.subscriptions.has(channelName)) {
            console.warn(`Already subscribed to ${channelName}`);
            return;
        }

        try {
            const channel = window.Echo.private(channelName);

            channel.listen('.maintenance.alert', (data) => {
                callback(data);
            });

            this.subscriptions.set(channelName, channel);
        } catch (error) {
            console.error(`❌ Failed to subscribe to ${channelName}:`, error);
        }
    }

    /**
     * Subscribe to compliance violations
     * @param {Function} callback - Function called when a violation is detected
     */
    subscribeComplianceViolations(callback) {
        const channelName = `team.${this.teamId}.compliance`;

        if (this.subscriptions.has(channelName)) {
            console.warn(`Already subscribed to ${channelName}`);
            return;
        }

        try {
            const channel = window.Echo.private(channelName);

            channel.listen('.compliance.violation', (data) => {
                callback(data);
            });

            this.subscriptions.set(channelName, channel);
        } catch (error) {
            console.error(`❌ Failed to subscribe to ${channelName}:`, error);
        }
    }

    /**
     * Subscribe to geofence events (entries and exits)
     * @param {string} geofenceId - Geofence ID to subscribe to
     * @param {Function} entryCallback - Called on geofence entry
     * @param {Function} exitCallback - Called on geofence exit
     */
    subscribeGeofenceEvents(geofenceId, entryCallback, exitCallback) {
        const channelName = `geofence.${geofenceId}`;

        if (this.subscriptions.has(channelName)) {
            console.warn(`Already subscribed to ${channelName}`);
            return;
        }

        try {
            const channel = window.Echo.private(channelName);

            channel.listen('.geofence.entry.detected', (data) => {
                entryCallback(data);
            });

            channel.listen('.geofence.exit.detected', (data) => {
                exitCallback(data);
            });

            this.subscriptions.set(channelName, channel);
        } catch (error) {
            console.error(`❌ Failed to subscribe to ${channelName}:`, error);
        }
    }

    /**
     * Subscribe to machine status changes (online/offline)
     * @param {string} machineId - Machine ID to subscribe to
     * @param {Function} callback - Called when machine status changes
     */
    subscribeMachineStatus(machineId, callback) {
        const channelName = `machine.${machineId}`;

        try {
            const channel = window.Echo.private(channelName);

            channel.listen('.machine.offline', (data) => {
                callback({
                    type: 'offline',
                    ...data
                });
            });

            this.subscriptions.set(channelName, channel);
        } catch (error) {
            console.error(`❌ Failed to subscribe to ${channelName}:`, error);
        }
    }

    /**
     * Subscribe to user presence (active users in team)
     * @param {Function} joinCallback - Called when user joins
     * @param {Function} leaveCallback - Called when user leaves
     */
    subscribePresence(joinCallback, leaveCallback) {
        const channelName = `team.presence.${this.teamId}`;

        if (this.subscriptions.has(channelName)) {
            console.warn(`Already subscribed to ${channelName}`);
            return;
        }

        try {
            const channel = window.Echo.join(channelName)
                .here((users) => {
                    joinCallback(users);
                })
                .joining((user) => {
                    joinCallback([user]);
                })
                .leaving((user) => {
                    leaveCallback(user);
                });

            this.subscriptions.set(channelName, channel);
        } catch (error) {
            console.error(`❌ Failed to subscribe to ${channelName}:`, error);
        }
    }

    /**
     * Monitor the underlying Pusher-protocol connection and report a
     * simplified UI state: 'connected' | 'connecting' | 'reconnecting' |
     * 'disconnected'. pusher-js (which Reverb speaks) already handles the
     * actual reconnection attempts -- this only observes and reports state,
     * plus fires onReconnected() once when a connection is recovered after
     * having dropped, so callers can reconcile data that may have been
     * missed while disconnected.
     *
     * @param {Function} onStateChange - Called with the simplified state string
     * @param {Function} [onReconnected] - Called once when connection is regained after a drop
     */
    monitorConnection(onStateChange, onReconnected) {
        const connection = window.Echo?.connector?.pusher?.connection;

        if (!connection) {
            console.warn('Cannot monitor connection: Echo/Pusher connection not available.');
            return () => {};
        }

        let hadDropped = false;

        const report = (pusherState) => {
            let uiState;
            if (pusherState === 'connected') {
                uiState = 'connected';
                if (hadDropped && onReconnected) {
                    onReconnected();
                }
                hadDropped = false;
            } else if (pusherState === 'connecting') {
                uiState = hadDropped ? 'reconnecting' : 'connecting';
            } else {
                // 'unavailable', 'failed', 'disconnected'
                uiState = 'disconnected';
                hadDropped = true;
            }
            onStateChange(uiState);
        };

        const handler = ({ current }) => report(current);
        connection.bind('state_change', handler);

        // Report the current state immediately so UI doesn't start blank.
        report(connection.state);

        return () => connection.unbind('state_change', handler);
    }

    /**
     * Unsubscribe from a specific channel
     * @param {string} channelName - Channel to unsubscribe from
     */
    unsubscribe(channelName) {
        if (this.subscriptions.has(channelName)) {
            try {
                window.Echo.leave(channelName);
                this.subscriptions.delete(channelName);
            } catch (error) {
                console.error(`❌ Failed to unsubscribe from ${channelName}:`, error);
            }
        }
    }

    /**
     * Unsubscribe from all channels
     */
    unsubscribeAll() {
        for (const channelName of this.subscriptions.keys()) {
            this.unsubscribe(channelName);
        }
    }

    /**
     * Register a custom event listener
     * @param {string} eventName - Unique listener name
     * @param {Function} callback - Callback function
     */
    on(eventName, callback) {
        if (!this.listeners.has(eventName)) {
            this.listeners.set(eventName, []);
        }
        this.listeners.get(eventName).push(callback);
    }

    /**
     * Emit a custom event
     * @param {string} eventName - Event name
     * @param {*} data - Event data
     */
    emit(eventName, data) {
        if (this.listeners.has(eventName)) {
            this.listeners.get(eventName).forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Error in listener for ${eventName}:`, error);
                }
            });
        }
    }

    /**
     * Handle reconnection attempts
     */
    handleReconnection() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            setTimeout(() => {
                this.init(this.userId, this.teamId);
            }, this.reconnectDelay);
        } else {
            console.error('❌ Max reconnection attempts reached. Please refresh the page.');
            this.isConnected = false;
        }
    }

    /**
     * Check connection status
     * @returns {boolean}
     */
    getConnectionStatus() {
        return this.isConnected;
    }

    /**
     * Get active subscriptions count
     * @returns {number}
     */
    getSubscriptionCount() {
        return this.subscriptions.size;
    }

    /**
     * Dispose service and cleanup
     */
    dispose() {
        this.unsubscribeAll();
        this.listeners.clear();
        this.isConnected = false;
    }
}

// Export singleton instance
export default new ReverbService();
