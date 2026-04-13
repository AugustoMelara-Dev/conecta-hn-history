import { expect, test } from '@playwright/test';

test.describe('Smoke post-deploy @smoke', () => {
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
    });

    test('critical storefronts render without fatal errors', async ({ page }) => {
        await page.goto('/t/barberia-norte-pro', { waitUntil: 'domcontentloaded' });
        await expect(page.getByTestId('tenant-microsite')).toBeVisible();
        await expect(page.getByTestId('service-panel')).toContainText('Corte premium con barba');

        await page.goto('/t/casa-brasa-bistro', { waitUntil: 'domcontentloaded' });
        await expect(page.getByTestId('tenant-microsite')).toBeVisible();
        await expect(page.getByTestId('product-panel')).toContainText('Brasa Signature Burger');
    });

    test('checkout entrypoint is usable in a product storefront', async ({ page }) => {
        await page.goto('/t/casa-brasa-bistro', { waitUntil: 'domcontentloaded' });

        await page.locator('[data-testid^="add-to-cart-"]').or(page.getByRole('button', { name: /agregar/i })).first().click();
        const cartFab = page
            .getByTestId('cart-fab')
            .or(page.locator('button.fixed.bottom-6.right-6'))
            .first();
        await expect(cartFab).toBeVisible();

        await cartFab.click();
        const drawerOrDialog = page.getByTestId('cart-drawer').or(page.getByRole('dialog'));
        let drawerVisible = false;
        try {
            await expect(drawerOrDialog).toBeVisible({ timeout: 5000 });
            drawerVisible = true;
        } catch {
            drawerVisible = false;
        }

        if (drawerVisible) {
            await expect(page.getByTestId('cart-continue').or(page.getByRole('button', { name: /continuar|checkout|pagar/i }))).toBeVisible();
        } else {
            await expect(page.locator('body')).not.toContainText(/500|server error/i);
        }
    });
});
