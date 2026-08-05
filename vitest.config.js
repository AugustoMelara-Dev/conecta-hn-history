import path from "node:path";
import { fileURLToPath } from "node:url";
import { configDefaults, defineConfig } from "vitest/config";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    esbuild: {
        jsxInject: "import React from 'react'",
    },
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "resources/js"),
        },
    },
    test: {
        globals: true,
        environment: "jsdom",
        setupFiles: ["resources/js/__tests__/vitest.setup.js"],
        exclude: [
            ...configDefaults.exclude,
            "**/.worktrees/**",
            "**/wt-pr*/**",
            "wt-pr*/**",
            "**/tmp/**",
            "C:/tmp/**",
            "tests/e2e/**",
            "tests/qa/**",
        ],
    },
});
