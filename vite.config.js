import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',      // Livewire/Alpine (Filament)
                'resources/js/app.jsx',     // Inertia/React (Public App + Breeze Auth)
            ],
            refresh: true,
        }),
        react({
            // Use automatic JSX runtime (React 17+)
            // This allows JSX without explicit React import
            jsxRuntime: 'automatic',
        }),
    ],
});
