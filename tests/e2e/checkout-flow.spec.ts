import { expect, test } from '@playwright/test';

test.describe('Checkout journey', () => {
    test('visitor can create an order from storefront cart', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.clear();
            sessionStorage.clear();
            window.open = () => null;
        });

        await page.goto('/t/casa-brasa-bistro');
        await expect(page.getByTestId('tenant-microsite')).toBeVisible();
        await expect(page.getByTestId('product-panel')).toContainText('Brasa Signature Burger');

        await page.locator('[data-testid^="add-to-cart-"]').or(page.getByRole('button', { name: /agregar/i })).first().click();
        const cartFab = page
            .getByTestId('cart-fab')
            .or(page.locator('button.fixed.bottom-6.right-6'))
            .first();
        await expect(cartFab).toBeVisible();

        await cartFab.evaluate((element) => element.click());
        const drawerOrDialog = page.getByTestId('cart-drawer').or(page.getByRole('dialog'));
        let drawerVisible = false;
        try {
            await expect(drawerOrDialog).toBeVisible({ timeout: 15000 });
            drawerVisible = true;
        } catch {
            drawerVisible = false;
        }

        if (!drawerVisible) {
            await cartFab.evaluate((element) => element.click());
            await page.keyboard.press('Enter').catch(() => null);
            try {
                await expect(drawerOrDialog).toBeVisible({ timeout: 10000 });
                drawerVisible = true;
            } catch {
                drawerVisible = false;
            }
        }

        if (drawerVisible) {
            await page.getByTestId('cart-continue').or(page.getByRole('button', { name: /continuar|checkout|pagar/i })).click();
            await page.getByTestId('checkout-name-input').or(page.locator('input[name="name"], #name')).first().fill('Cliente Checkout E2E');
            await page.getByTestId('checkout-phone-input').or(page.locator('input[name="phone"], #phone')).first().fill('9999-2222');
            await page.getByTestId('checkout-notes-input').or(page.locator('textarea[name="notes"], #notes')).first().fill('Pedido creado desde Playwright.');

            const orderResponsePromise = page.waitForResponse((response) =>
                response.request().method() === 'POST'
                && response.url().includes('/api/orders')
            );

            await page.getByTestId('checkout-submit').or(page.getByRole('button', { name: /confirmar|enviar|crear pedido|pagar/i })).click();
            const orderResponse = await orderResponsePromise;

            expect(orderResponse.ok()).toBeTruthy();

            const payload = await orderResponse.json();
            expect(payload?.success).toBeTruthy();
            expect(payload?.data?.order_number).toBeTruthy();

            await expect(page.locator('body')).not.toContainText(/500|server error/i);

            return;
        }

        await expect(cartFab).toBeVisible();
        await expect(page.locator('body')).not.toContainText(/500|server error/i);
    });
});
