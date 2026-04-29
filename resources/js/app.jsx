import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import * as Sentry from '@sentry/react';
import { createRoot } from 'react-dom/client';

import { route } from 'ziggy-js';
import { initializeTheme } from '@/Stores/useThemeStore';

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

function safeStorage(name) {
    try {
        return window[name];
    } catch {
        return null;
    }
}

function readInitialPage() {
    try {
        return JSON.parse(document.getElementById('app')?.dataset?.page || '{}');
    } catch {
        return {};
    }
}

function currentInertiaUrl() {
    const current = `${window.location.pathname}${window.location.search}`;

    return current || '/';
}

function normalizeInertiaUrl(value) {
    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    try {
        const parsed = new URL(value, window.location.origin);

        return `${parsed.pathname}${parsed.search}` || '/';
    } catch {
        return value.startsWith('/') ? value : null;
    }
}

function clearBrowserRuntimeState(version, force = false) {
    const local = safeStorage('localStorage');
    const key = `conecta:runtime-cleanup:${version}`;

    if (!force && local?.getItem(key) === 'done') {
        return Promise.resolve();
    }

    const markDone = () => {
        local?.setItem(key, 'done');
    };

    const serviceWorkerCleanup = 'serviceWorker' in navigator
        ? navigator.serviceWorker.getRegistrations()
            .then((registrations) => Promise.all(
                registrations.map((registration) => registration.unregister()),
            ))
        : Promise.resolve();

    const cacheCleanup = 'caches' in window
        ? window.caches.keys().then((keys) => Promise.all(
            keys.map((keyName) => window.caches.delete(keyName)),
        ))
        : Promise.resolve();

    return Promise.allSettled([serviceWorkerCleanup, cacheCleanup]).then(markDone);
}

function recoverMismatchedInertiaPage(initialPage) {
    const expected = normalizeInertiaUrl(initialPage?.url);

    if (expected === null || expected === currentInertiaUrl()) {
        return false;
    }

    const version = initialPage?.version || 'unknown';
    const session = safeStorage('sessionStorage');
    const recoverKey = `conecta:inertia-url-recovery:${version}:${window.location.pathname}`;

    if (session?.getItem(recoverKey) === 'attempted') {
        return false;
    }

    session?.setItem(recoverKey, 'attempted');

    const next = new URL(window.location.href);
    next.searchParams.set('__client_recover', String(Date.now()));
    clearBrowserRuntimeState(version, true).finally(() => {
        window.location.replace(next.toString());
    });

    return true;
}

if (typeof window !== 'undefined') {
    const isLocalBrowser = ['localhost', '127.0.0.1'].includes(window.location.hostname);
    const initialPage = readInitialPage();
    const runtimeVersion = initialPage?.version || 'unknown';

    if (!recoverMismatchedInertiaPage(initialPage)) {
        window.addEventListener('load', () => {
            clearBrowserRuntimeState(runtimeVersion, isLocalBrowser);
        });
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Vito Business OS';
const pages = import.meta.glob('./Pages/**/*.jsx');
const sentryDsn = import.meta.env.VITE_SENTRY_DSN;

if (sentryDsn) {
    Sentry.init({
        dsn: sentryDsn,
        environment: import.meta.env.VITE_SENTRY_ENVIRONMENT || import.meta.env.MODE,
        release: import.meta.env.VITE_SENTRY_RELEASE,
        tracesSampleRate: Number(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE || 0),
        sendDefaultPii: false,
    });
}

initializeTheme();

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            pages,
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <Sentry.ErrorBoundary fallback={null}>
                <App {...props} />
                <ToastProvider />
            </Sentry.ErrorBoundary>
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
