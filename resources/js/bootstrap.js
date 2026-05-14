/**
 * bootstrap.js - Application Bootstrap (Axios + Laravel Echo)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TRANSPORT LAYERS:                                                       ║
 * ║  1. Axios: HTTP client for REST API calls                               ║
 * ║  2. Laravel Echo: WebSocket client for real-time events                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// ═══════════════════════════════════════════════════════════════════════════
// AXIOS CONFIGURATION (HTTP Layer)
// ═══════════════════════════════════════════════════════════════════════════
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

// ═══════════════════════════════════════════════════════════════════════════
// LARAVEL ECHO CONFIGURATION (WebSocket Layer)
// ═══════════════════════════════════════════════════════════════════════════
// Compatible with both Laravel Reverb and Pusher.
// Reverb uses Pusher protocol but with custom hosts.
//
// Required .env variables:
//   VITE_REVERB_APP_KEY=your-app-key
//   VITE_REVERB_HOST=localhost (or your domain)
//   VITE_REVERB_PORT=8080 (or 443 for production)
//   VITE_REVERB_SCHEME=http (or https for production)
// ═══════════════════════════════════════════════════════════════════════════
window.Pusher = Pusher;

// Defensive scheme normalization (handles "HTTPS", "https ", etc.)
const reverbScheme = (import.meta.env.VITE_REVERB_SCHEME ?? 'https').toLowerCase().trim();
const reverbEnabled = String(import.meta.env.VITE_REVERB_ENABLED ?? '').toLowerCase().trim() === 'true';
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = import.meta.env.VITE_REVERB_HOST;

window.Echo = undefined;

if (reverbEnabled && reverbKey && reverbHost) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
