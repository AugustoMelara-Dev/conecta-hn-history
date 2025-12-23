import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

// Ziggy for route() function
import { Ziggy } from './ziggy';
import { route } from 'ziggy-js';

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
        root.render(<App {...props} />);
    },
    progress: {
        color: '#0ea5e9', // Sky-500 color
        showSpinner: true,
    },
});
