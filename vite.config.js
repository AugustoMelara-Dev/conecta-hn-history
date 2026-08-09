import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";
import { VitePWA } from "vite-plugin-pwa";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/filament/app/business-components.css",
                "resources/js/app.js", // Livewire/Alpine (Filament)
                "resources/js/app.jsx", // Inertia/React (Public App + Breeze Auth)
            ],
            refresh: true,
        }),
        react({
            // Use automatic JSX runtime (React 17+)
            // This allows JSX without explicit React import
            jsxRuntime: "automatic",
        }),
        VitePWA({
            registerType: "autoUpdate",
            outDir: "public/build",
            buildBase: "/build/",
            scope: "/",
            workbox: {
                // SECURITY: Only cache static assets, never tenant data
                globPatterns: ["**/*.{js,css,ico,png,svg,woff2}"],
                cleanupOutdatedCaches: true,
                // Exclude API and HTML to prevent cross-tenant data leaks
                navigateFallback: null,
                runtimeCaching: [
                    {
                        urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp)$/,
                        handler: "CacheFirst",
                        options: {
                            cacheName: "images-cache",
                            expiration: {
                                maxEntries: 50,
                                maxAgeSeconds: 60 * 60 * 24 * 30, // 30 days
                            },
                        },
                    },
                ],
            },
            manifest: {
                name: "Conecta HN",
                short_name: "Conecta HN",
                description: "Publica tu negocio, atiende clientes y opera ventas, reservas y seguimiento en un solo lugar.",
                theme_color: "#000000",
                background_color: "#ffffff",
                display: "standalone",
                orientation: "portrait",
                scope: "/",
                start_url: "/",
                icons: [
                    {
                        src: "/favicon.svg",
                        sizes: "any",
                        type: "image/svg+xml",
                    },
                    {
                        src: "/favicon.ico",
                        sizes: "any",
                        type: "image/x-icon",
                    },
                    {
                        src: "/icon-192x192.png",
                        sizes: "192x192",
                        type: "image/png",
                    },
                ],
            },
        }),
    ],
    // Force Vite to bind to localhost to avoid IPv6 [::1] CSP issues
    server: {
        host: "localhost",
        port: 5173,
        strictPort: true,
        cors: {
            origin: ["http://localhost:8000", "http://127.0.0.1:8000"],
        },
    },
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "resources/js"),
        },
    },
});
