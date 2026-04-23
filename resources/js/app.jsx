import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

import { route } from 'ziggy-js';

// Global Toast System
import { ToastProvider } from '@/Components/Toast/ToastProvider';

const routeFallbacks = {
    home: '/',
    login: '/login',
    'tenant.register': '/registrar-negocio',
    'tenant.register.store': '/registrar-negocio',
    'api.check-slug': '/api/check-slug',
    'check-slug': '/check-slug',
    'public.explore': '/explore',
    'directory.index': '/directorio',
    'mi-cuenta.edit': '/mi-cuenta',
};

function buildRouteFallback(name, params = {}) {
    if (Object.prototype.hasOwnProperty.call(routeFallbacks, name)) {
        return routeFallbacks[name];
    }

    if (name === 'tenant.microsite.show' && params?.tenant) {
        return `/t/${params.tenant}`;
    }

    if (name === 'filament.app.pages.dashboard' && params?.tenant) {
        return `/app/${params.tenant}`;
    }

    return '#';
}

function toRelativeUrl(value) {
    if (typeof value !== 'string') {
        return value;
    }

    const normalized = value.trim();
    if (normalized === '' || normalized === '#') {
        return normalized;
    }

    if (typeof window === 'undefined') {
        return normalized;
    }

    try {
        const parsed = new URL(normalized, window.location.origin);

        return `${parsed.pathname}${parsed.search}${parsed.hash}` || '/';
    } catch {
        return normalized;
    }
}

function safeRoute(...args) {
    if (args.length === 0) {
        try {
            return route();
        } catch {
            return {
                current: () => false,
                has: () => false,
                params: {},
            };
        }
    }

    try {
        const [name, params, absolute, config] = args;
        const resolved = args.length >= 3
            ? route(name, params, absolute, config)
            : route(name, params, false, config);

        return toRelativeUrl(resolved);
    } catch {
        return buildRouteFallback(args[0], args[1]);
    }
}

// Use the runtime Ziggy payload injected by Blade via @routes.
// This avoids a second stale route catalog living in resources/js/ziggy.js.
// If Ziggy is missing in a request, keep rendering with safe fallbacks.
window.route = safeRoute;

if (typeof window !== 'undefined') {
    const isLocalBrowser = ['localhost', '127.0.0.1'].includes(window.location.hostname);

    if (isLocalBrowser && 'serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.getRegistrations().then((registrations) => {
                registrations.forEach((registration) => {
                    registration.unregister();
                });
            });

            if ('caches' in window) {
                window.caches.keys().then((keys) => {
                    keys.forEach((key) => {
                        window.caches.delete(key);
                    });
                });
            }
        });
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Conecta HN';

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <>
                <App {...props} />
                <ToastProvider />
            </>
        );
    },
    progress: {
        // Dynamic color: Use tenant's primary_color or fallback to black (Corporate Minimalist)
        color: (() => {
            try {
                // Access initial page props for tenant branding
                const initialPage = JSON.parse(
                    document.getElementById('app')?.dataset?.page || '{}'
                );
                return initialPage?.props?.tenant?.primary_color || '#000000';
            } catch {
                return '#000000'; // Fallback: Corporate Black
            }
        })(),
        showSpinner: true,
    },
});
