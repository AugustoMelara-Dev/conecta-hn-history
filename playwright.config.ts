import { defineConfig, devices } from '@playwright/test';

const isSmokeMode = process.env.E2E_MODE === 'smoke';
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8011';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    timeout: 60_000,
    expect: {
        timeout: 10_000,
    },
    reporter: [
        ['list'],
        ['html', { open: 'never' }],
    ],
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    webServer: isSmokeMode
        ? undefined
        : {
            command: 'node scripts/e2e-server.mjs',
            url: 'http://127.0.0.1:8011/up',
            reuseExistingServer: process.env.PLAYWRIGHT_REUSE_SERVER === '1',
            timeout: 180_000,
        },
    projects: [
        {
            name: 'desktop-chrome',
            use: {
                ...devices['Desktop Chrome'],
                browserName: 'chromium',
            },
        },
    ],
});
