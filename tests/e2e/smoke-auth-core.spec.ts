import { expect, test } from '@playwright/test';

test.describe('PR smoke auth core', () => {
    test('health endpoint is up', async ({ page }) => {
        const response = await page.goto('/up', { waitUntil: 'domcontentloaded' });

        expect(response?.ok()).toBeTruthy();
        await expect(page.locator('body')).toContainText(/ok|application up|healthy/i);
    });

    test('login page remains reachable', async ({ page }) => {
        await page.goto('/login', { waitUntil: 'domcontentloaded' });

        await expect(page.getByTestId('login-form').or(page.locator('form').first())).toBeVisible();
        await expect(page.getByTestId('login-email-input').or(page.locator('#email'))).toBeVisible();
        await expect(page.getByTestId('login-password-input').or(page.locator('#password'))).toBeVisible();
        await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
    });
});
