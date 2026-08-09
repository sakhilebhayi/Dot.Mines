import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.Pusher = Pusher;

/**
 * Laravel Echo Configuration
 * Sets up WebSocket connections for real-time updates
 * Supports both Pusher (cloud) and Reverb (local) broadcasting
 */

// Get broadcasting driver from environment
const broadcastDriver = import.meta.env.VITE_BROADCAST_DRIVER || 'pusher';
const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';

if (pusherKey) {
    // Use Pusher for real-time broadcasting
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: pusherCluster,
        forceTLS: true,
        encrypted: true,
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        },
    });
    
} else {
    // Fallback to Reverb (local development)
    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
    const host = new URL(window.location.href).host;
    
    if (reverbKey) {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: host.split(':')[0],
            wsPort: 8080,
            wssPort: 443,
            forceTLS: false,
            enabledTransports: ['ws', 'wss'],
            auth: {
                headers: {
                    Authorization: `Bearer ${document.querySelector('meta[name="csrf-token"]')?.content || ''}`,
                },
            },
        });
        
    } else {
        console.warn('No broadcasting credentials configured. Real-time updates disabled.');
    }
}
