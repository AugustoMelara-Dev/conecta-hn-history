import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

// Ziggy for route() function
import { Ziggy } from './ziggy';
import { route } from 'ziggy-js';

// Global shadcn/sonner toast mount.
import { ToastProvider } from '@/Components/Toast/ToastProvider';

// Make route() globally available
window.route = (name, params, absolute) => route(name, params, absolute, Ziggy);

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
