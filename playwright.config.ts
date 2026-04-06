import { defineConfig, devices } from '@playwright/test';

const e2eServerCommand =
    process.platform === 'win32'
        ? 'powershell -ExecutionPolicy Bypass -File scripts/e2e-server.ps1'
        : 'pwsh -ExecutionPolicy Bypass -File scripts/e2e-server.ps1';

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
        baseURL: 'http://127.0.0.1:8011',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    webServer: {
        command: e2eServerCommand,
        url: 'http://127.0.0.1:8011',
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
