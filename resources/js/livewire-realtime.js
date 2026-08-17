/**
 * Livewire Realtime Event Listener
 * 
 * Integrates with the RealtimeUpdates trait to listen for component events
 * and manage real-time WebSocket subscriptions
 * 
 * Also integrates with UI services:
 * - RealtimeMapManager: Live map updates
 * - ToastNotificationService: Alert notifications
 * - GeofenceVisualizationManager: Geofence events
 */

import ReverbService from './services/ReverbService.js';

// Delay Livewire event listener setup until Livewire is available
function setupLivewireListeners() {
    if (typeof window.Livewire === 'undefined') {
        // Wait for Livewire to be ready
        setTimeout(setupLivewireListeners, 100);
        return;
    }

    // Get UI services (wait for them to be available)
    const getService = (serviceName) => {
        return new Promise((resolve) => {
            if (window[serviceName]) {
                resolve(window[serviceName]);
            } else {
                const checkInterval = setInterval(() => {
                    if (window[serviceName]) {
                        clearInterval(checkInterval);
                        resolve(window[serviceName]);
                    }
                }, 100);
            }
        });
    };

    /**
     * Initialize Reverb for the current user/team
     */
    window.Livewire.on('realtime:init', ({ userId, teamId }) => {
        ReverbService.init(userId, teamId);

        // Initialize toast service
        getService('ToastNotificationService').then(service => {
            service.init();
        });

        // Surface connection state as a window event so any UI (e.g. the
        // navbar's connection indicator) can react without depending on
        // ReverbService/Echo directly. On reconnect after a real drop,
        // refresh data that WebSocket delivery may have missed while
        // disconnected -- the backend database, not the socket, is the
        // source of truth.
        ReverbService.monitorConnection(
            (state) => {
                window.dispatchEvent(new CustomEvent('realtime-connection-changed', { detail: { state } }));
            },
            () => {
                window.Livewire.dispatch('alert-created');
                window.Livewire.dispatch('realtime-reconnected');
            }
        );
    });

    /**
     * Subscribe to machine location updates
     */
    window.Livewire.on('realtime:machine-location', ({ machineId }) => {
        ReverbService.subscribeMachineLocation(machineId, (data) => {
            ReverbService.emit('machineLocationUpdated', data);
            
            // Update map if available
            getService('RealtimeMapManager').then(mapManager => {
                if (mapManager.map) {
                    mapManager.updateMachineMarker(data);
                }
            });
        });
    });

    /**
     * Subscribe to team-wide location updates
     */
    window.Livewire.on('realtime:team-locations', () => {
        ReverbService.subscribeTeamLocations((data) => {
            ReverbService.emit('teamLocationUpdated', data);
            
            // Update map if available
            getService('RealtimeMapManager').then(mapManager => {
                if (mapManager.map) {
                    mapManager.updateMachineMarker(data);
                }
            });
        });
    });

    /**
     * Subscribe to team alerts
     */
    window.Livewire.on('realtime:team-alerts', () => {
        ReverbService.subscribeTeamAlerts((data) => {
            ReverbService.emit('alertTriggered', data);

            // Show toast notification
            getService('ToastNotificationService').then(toastService => {
                toastService.showAlert({
                    title: data.title || 'New Alert',
                    description: data.description,
                    priority: data.priority || 'medium',
                    type: 'alert',
                    duration: 0 // Don't auto-dismiss critical/high alerts
                });
            });

            // Refresh the notification bell (AINotifications.php listens
            // for this Livewire event).
            window.Livewire.dispatch('alert-created');
        });
    });

    /**
     * Subscribe to predictive maintenance alerts
     */
    window.Livewire.on('realtime:maintenance-alerts', () => {
        ReverbService.subscribeMaintenanceAlerts((data) => {
            ReverbService.emit('maintenanceAlertTriggered', data);

            getService('ToastNotificationService').then(toastService => {
                toastService.showAlert({
                    title: `Maintenance Alert: ${data.machine_name || 'Machine'}`,
                    description: data.predicted_date
                        ? `Predicted maintenance needed on ${data.predicted_date}`
                        : 'Predicted maintenance needed soon',
                    priority: data.severity || 'medium',
                    type: 'alert',
                    duration: 0
                });
            });

            window.Livewire.dispatch('alert-created');
        });
    });

    /**
     * Subscribe to compliance violations
     */
    window.Livewire.on('realtime:compliance-violations', () => {
        ReverbService.subscribeComplianceViolations((data) => {
            ReverbService.emit('complianceViolationDetected', data);

            getService('ToastNotificationService').then(toastService => {
                toastService.showAlert({
                    title: `Compliance Violation: ${data.violation_type || 'Unknown'}`,
                    description: data.description,
                    priority: data.severity || 'medium',
                    type: 'alert',
                    duration: 0
                });
            });

            window.Livewire.dispatch('alert-created');
        });
    });

    /**
     * Subscribe to geofence events
     */
    window.Livewire.on('realtime:geofence-events', ({ geofenceId }) => {
        ReverbService.subscribeGeofenceEvents(
            geofenceId,
            (data) => {
                ReverbService.emit('geofenceEntry', data);
                
                // Update geofence visualization
                getService('GeofenceVisualizationManager').then(geofenceManager => {
                    if (geofenceManager.map) {
                        geofenceManager.onGeofenceEntry(data);
                        
                        // Show toast
                        getService('ToastNotificationService').then(toastService => {
                            toastService.success(
                                `${data.machine_name} Entered`,
                                data.geofence_name,
                                5000
                            );
                        });
                    }
                });
            },
            (data) => {
                ReverbService.emit('geofenceExit', data);
                
                // Update geofence visualization
                getService('GeofenceVisualizationManager').then(geofenceManager => {
                    if (geofenceManager.map) {
                        geofenceManager.onGeofenceExit(data);
                        
                        // Show toast
                        getService('ToastNotificationService').then(toastService => {
                            toastService.warning(
                                `${data.machine_name} Exited`,
                                data.geofence_name,
                                5000
                            );
                        });
                    }
                });
            }
        );
    });

    /**
     * Subscribe to machine status changes
     */
    window.Livewire.on('realtime:machine-status', ({ machineId }) => {
        ReverbService.subscribeMachineStatus(machineId, (data) => {
            ReverbService.emit('machineStatusChanged', data);
            
            // Update map marker color
            getService('RealtimeMapManager').then(mapManager => {
                if (mapManager.map && data.type === 'offline') {
                    mapManager.setMachineOffline(machineId);
                }
            });
            
            // Show notification
            getService('ToastNotificationService').then(toastService => {
                toastService.error(
                    'Machine Offline',
                    data.machine_name || `Machine ${machineId}`,
                    10000
                );
            });
        });
    });

    /**
     * Subscribe to presence (active users)
     */
    window.Livewire.on('realtime:presence', () => {
        ReverbService.subscribePresence(
            (users) => ReverbService.emit('usersJoined', users),
            (user) => ReverbService.emit('userLeft', user)
        );
    });

    /**
     * Stop listening to a machine
     */
    window.Livewire.on('realtime:stop-machine', ({ machineId }) => {
        const channelName = `machine.${machineId}`;
        ReverbService.unsubscribe(channelName);
    });

    /**
     * Stop all listeners
     */
    window.Livewire.on('realtime:stop-all', () => {
        ReverbService.unsubscribeAll();
    });

    /**
     * Subscribe to the operations feed channel.
     * Handles live events and missed-post catch-up after reconnection.
     */
    window.Livewire.on('realtime:feed', ({ teamId }) => {
        console.log('📡 Subscribing to feed channel for team:', teamId);

        ReverbService.subscribeFeed(teamId, {
            onNewPost: (post) => {
                // Tell the Livewire Feed component to prepend the new post
                window.Livewire.dispatch('feed:new-post', { post });
            },
            onAcknowledgementUpdated: (data) => {
                window.Livewire.dispatch('feed:acknowledgement-updated', data);
            },
            onNewComment: (data) => {
                window.Livewire.dispatch('feed:new-comment', data);
            },
            onCommentUpdated: (data) => {
                window.Livewire.dispatch('feed:comment-updated', data);
            },
            onCommentDeleted: (data) => {
                window.Livewire.dispatch('feed:comment-deleted', data);
            },
            onPostLiked: (data) => {
                window.Livewire.dispatch('feed:post-liked', data);
            },
            onPostStatusChanged: (data) => {
                window.Livewire.dispatch('feed:post-status-changed', data);
            },
            onMissedPosts: (posts) => {
                // Ask the Livewire component to refresh so missed posts appear
                window.Livewire.dispatch('feed:reconnected', { count: posts.length });
            },
        });
    });

    /**
     * Custom event listener registration
     * Allows components to listen to real-time events
     */
    window.onRealtimeUpdate = (eventName, callback) => {
        ReverbService.on(eventName, callback);
    };

}

// Start setup when script loads
setupLivewireListeners();

export default { setupLivewireListeners };
