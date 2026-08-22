import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Reverb speaks the Pusher protocol, so laravel-echo still needs a Pusher
// client available globally even though we no longer use Pusher's cloud.
window.Pusher = Pusher;

/**
 * Laravel Echo Configuration
 * Sets up the WebSocket connection to this app's own Laravel Reverb server.
 */

const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = import.meta.env.VITE_REVERB_HOST;

// The build is committed, so whatever VITE_REVERB_HOST was at build time is
// baked in. A dev-only host (0.0.0.0/localhost) reaching a real deployment
// would make Echo retry wss://0.0.0.0 forever in every visitor's console --
// skip realtime entirely unless the configured host is plausible for the
// page actually being served.
const devHosts = ['0.0.0.0', '127.0.0.1', 'localhost', '', undefined, null];
const pageIsLocal = ['localhost', '127.0.0.1', '0.0.0.0'].includes(window.location.hostname);
const reverbHostUsable = !devHosts.includes(reverbHost) || pageIsLocal;

// Managed Pusher-protocol service first (hybrid Slice 3): shared hosting
// cannot run a Reverb process, so production realtime rides a hosted
// websocket service. The key is a public client identifier -- safe in the
// committed build. Reverb remains the local-dev transport.
if (pusherKey) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'eu',
        forceTLS: true,
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        },
    });
} else if (reverbKey && reverbHostUsable) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        // Private-channel auth hits /broadcasting/auth with the session
        // cookie; only the CSRF header is needed alongside it (this is not
        // bearer-token auth).
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        },
    });
} else if (!reverbKey) {
    console.info('Realtime disabled: no VITE_PUSHER_APP_KEY or VITE_REVERB_APP_KEY in this build. Polling covers freshness.');
} else {
    console.info('Realtime disabled: the built assets carry a dev-only Reverb host (' + reverbHost + ') that does not apply to ' + window.location.hostname + '.');
}
