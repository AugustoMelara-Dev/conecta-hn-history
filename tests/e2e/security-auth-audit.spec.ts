import { expect, test } from '@playwright/test';
import { officialFixtures } from './support/fixtures';
import {
    forgotPasswordFeedback,
    forgotPasswordHeading,
    forgotPasswordSubmitButton,
    loginSubmitButton,
} from './support/auth-locators';

const SEEDED_OWNER = {
    email: officialFixtures.owner.email,
    password: officialFixtures.owner.password,
    tenantSlug: officialFixtures.product.slug,
};

test.describe('Security and auth audit', () => {
    test('password recovery request works without server errors', async ({ page }) => {
        const forgotPasswordResponse = await page.goto('/forgot-password', { waitUntil: 'domcontentloaded' });
        expect(forgotPasswordResponse?.status(), '/forgot-password must be reachable').toBeLessThan(500);

        await expect(forgotPasswordHeading(page)).toBeVisible({ timeout: 15_000 });
        await page.locator('#email').fill(SEEDED_OWNER.email);
        await forgotPasswordSubmitButton(page).click();
        await expect(forgotPasswordFeedback(page)).toBeVisible({ timeout: 20_000 });
    });

    test('reset-password endpoint handles invalid token gracefully', async ({ page }) => {
        const response = await page.goto(`/reset-password/invalid-token?email=${encodeURIComponent(SEEDED_OWNER.email)}`, {
            waitUntil: 'domcontentloaded',
        });
        expect(response?.status(), 'reset-password form should be reachable').toBeLessThan(500);

        await expect(
            page
                .getByRole('heading', { name: /reset your password|restablece tu contraseña|restablecer contraseña/i })
                .or(page.locator('h1').first()),
        ).toBeVisible({ timeout: 15_000 });
        const resetProbe = await page.request.post('/reset-password', {
            failOnStatusCode: false,
            form: {
                token: 'invalid-token',
                email: SEEDED_OWNER.email,
                password: 'NewPassword123!',
                password_confirmation: 'NewPassword123!',
            },
        });
        expect(resetProbe.status(), 'invalid token flow should not produce 5xx').toBeLessThan(500);
    });

    test('tenant isolation holds for authenticated panel and API context', async ({ page }) => {
        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        await page.locator('#email').fill(SEEDED_OWNER.email);
        await page.locator('#password').fill(SEEDED_OWNER.password);
        await loginSubmitButton(page).click();

        await page.waitForURL(/\/(app(?:\/|$)|verify-email|email\/verify)/, { timeout: 20_000 });
        const appProbe = await page.request.get('/app', { failOnStatusCode: false, maxRedirects: 0 });
        expect([200, 301, 302, 303, 307, 308].includes(appProbe.status())).toBeTruthy();

        const crossTenantPanelResponse = await page.request.get(`/app/${officialFixtures.crossTenantSlug}`, {
            failOnStatusCode: false,
            maxRedirects: 0,
        });

        expect(
            [301, 302, 303, 307, 308, 401, 403, 404].includes(crossTenantPanelResponse.status()),
            'cross-tenant panel URL should never return direct success for foreign tenant',
        ).toBeTruthy();

        const crossTenantWorkspaceResponse = await page.request.get('/workspace/orders/99999999', {
            failOnStatusCode: false,
            maxRedirects: 0,
        });

        expect(
            [301, 302, 303, 307, 308, 401, 403, 404].includes(crossTenantWorkspaceResponse.status()),
            'cross-tenant resource probing should not return direct success',
        ).toBeTruthy();
    });
});
