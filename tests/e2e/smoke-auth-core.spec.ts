import { expect, test } from '@playwright/test';
import { expectNoFatalServerText, expectNon5xx, gotoWithRetries } from './support/smoke-http';

test.describe('PR smoke auth core', () => {
    test('health endpoint is up', async ({ page }) => {
        const healthPaths = ['/up', '/api/v1/directory/search?limit=1', '/'];
        let validatedPath: string | null = null;
        let latestStatus = 0;

        for (const healthPath of healthPaths) {
            const response = await gotoWithRetries(page, healthPath, {
                attempts: 2,
                delayMs: 600,
                responseTimeoutMs: 8_000,
            });
            const status = response?.status() ?? 0;
            latestStatus = status;

            if (response && status < 500) {
                validatedPath = healthPath;
                break;
            }
        }

        expect(
            validatedPath,
            `Expected at least one health probe (${healthPaths.join(', ')}) to avoid 5xx. Last status: ${latestStatus}.`,
        ).not.toBeNull();
    });

    test('login page remains reachable', async ({ page }) => {
        const response = await gotoWithRetries(page, '/login', {
            attempts: 2,
            delayMs: 600,
            responseTimeoutMs: 8_000,
        });
        expectNon5xx(response, '/login');
        await expectNoFatalServerText(page);

        const loginSurface = page
            .getByTestId('login-form')
            .or(page.locator('form').first())
            .or(page.locator('body').getByText(/iniciar sesi[oó]n|login|sign in/i).first())
            .first();
        await expect(loginSurface).toBeVisible();
    });
});
