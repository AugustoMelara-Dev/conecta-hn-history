import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";
import { VitePWA } from "vite-plugin-pwa";
import { fileURLToPath, URL } from "node:url";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");
    const isLocalRuntime =
        mode === "development" ||
        env.APP_ENV === "local" ||
        env.APP_URL?.includes("127.0.0.1") ||
        env.APP_URL?.includes("localhost");

    const plugins = [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/filament/app-panel.css",
                "resources/css/filament/admin-panel.css",
                "resources/js/app.js",
                "resources/js/app.jsx",
            ],
            refresh: true,
        }),
        react({
            jsxRuntime: "automatic",
        }),
    ];

    if (!isLocalRuntime) {
        plugins.push(
            VitePWA({
                registerType: "autoUpdate",
                selfDestroying: true,
                outDir: "public/build",
                buildBase: "/build/",
                scope: "/",
                workbox: {
                    globPatterns: ["**/*.{js,css,ico,png,svg,woff2}"],
                    cleanupOutdatedCaches: true,
                    navigateFallback: null,
                    runtimeCaching: [
                        {
                            urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp)$/,
                            handler: "CacheFirst",
                            options: {
                                cacheName: "images-cache",
                                expiration: {
                                    maxEntries: 50,
                                    maxAgeSeconds: 60 * 60 * 24 * 30,
                                },
                            },
                        },
                    ],
                },
                manifest: {
                    name: "Vito Business OS",
                    short_name: "Vito OS",
                    description: "Infraestructura Digital para PyMEs de Honduras",
                    theme_color: "#000000",
                    background_color: "#ffffff",
                    display: "standalone",
                    orientation: "portrait",
                    scope: "/",
                    start_url: "/",
                    icons: [
                        {
                            src: "/icon-192x192.png",
                            sizes: "192x192",
                            type: "image/png",
                        },
                        {
                            src: "/icon-512x512.png",
                            sizes: "512x512",
                            type: "image/png",
                        },
                        {
                            src: "/icon-512x512.png",
                            sizes: "512x512",
                            type: "image/png",
                            purpose: "maskable",
                        },
                    ],
                },
            }),
        );
    }

    return {
        plugins,
        resolve: {
            alias: {
                "@": fileURLToPath(new URL("./resources/js", import.meta.url)),
            },
        },
        test: {
            environment: "jsdom",
            globals: true,
            setupFiles: ["resources/js/__tests__/setup.js"],
        },
        build: {
            rollupOptions: {
                output: {
                    manualChunks: {
                        react: ["react", "react-dom", "@inertiajs/react"],
                        observability: ["@sentry/react"],
                        charts: ["recharts"],
                        motion: ["framer-motion"],
                        ui: [
                            "@radix-ui/react-alert-dialog",
                            "@radix-ui/react-dialog",
                            "@radix-ui/react-dropdown-menu",
                            "@radix-ui/react-select",
                            "@radix-ui/react-tabs",
                            "@radix-ui/react-tooltip",
                        ],
                    },
                },
            },
        },
        server: {
            host: "localhost",
            port: 5173,
            strictPort: true,
            cors: {
                origin: ["http://localhost:8000", "http://127.0.0.1:8000"],
            },
        },
    };
});
