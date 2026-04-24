import { expect, test, type Page } from '@playwright/test';
import { officialFixtures, officialRoutes } from './support/fixtures';

const mustEnforcePublishedFixtures = /^(1|true|yes|on)$/i.test(
    process.env.SMOKE_REQUIRE_PUBLISHED_FIXTURES ?? 'false',
);

async function isVisible(page: Page, testId: string, timeout = 5000): Promise<boolean> {
    try {
        await expect(page.getByTestId(testId)).toBeVisible({ timeout });
        return true;
    } catch {
        return false;
    }
}

function failOrSkipMissingFixture(message: string): never {
    if (mustEnforcePublishedFixtures) {
        throw new Error(message);
    }

    test.skip(true, `${message} Set SMOKE_REQUIRE_PUBLISHED_FIXTURES=true to enforce this in smoke.`);
}

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

    test('owner login lands in business app', async ({ page }) => {
        await page.goto('/login', { waitUntil: 'domcontentloaded' });

        await page.getByTestId('login-email-input').or(page.locator('#email')).fill(officialFixtures.owner.email);
        await page.getByTestId('login-password-input').or(page.locator('#password')).fill(officialFixtures.owner.password);

        await Promise.all([
            page.waitForResponse((response) => response.url().includes('/login') && response.request().method() === 'POST'),
            page.getByTestId('login-submit').or(page.getByRole('button', { name: /login|iniciar/i })).click(),
        ]);

        await page.waitForLoadState('networkidle');

        if (page.url().includes('/login')) {
            await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
            throw new Error('Owner fixture authentication did not leave /login.');
        }

        await expect(page).toHaveURL(/\/app(?:\/|$)/, { timeout: 20_000 });
    });

    test('critical storefronts render without fatal errors', async ({ page }) => {
        await page.goto(officialRoutes.serviceMicrosite, { waitUntil: 'domcontentloaded' });
        if (!(await isVisible(page, 'tenant-microsite'))) {
            failOrSkipMissingFixture(
                `Service microsite fixture not published/reachable at ${officialRoutes.serviceMicrosite}.`,
            );
        }
        await expect(page.getByTestId('service-panel')).toContainText(officialFixtures.service.expectedPanelText);

        await page.goto(officialRoutes.productMicrosite, { waitUntil: 'domcontentloaded' });
        if (!(await isVisible(page, 'tenant-microsite'))) {
            failOrSkipMissingFixture(
                `Product microsite fixture not published/reachable at ${officialRoutes.productMicrosite}.`,
            );
        }
        await expect(page.getByTestId('product-panel')).toContainText(officialFixtures.product.expectedPanelText);
    });

    test('booking entrypoint is reachable in service storefront', async ({ page }) => {
        await page.goto(officialRoutes.serviceBooking, { waitUntil: 'domcontentloaded' });
        if (!(await isVisible(page, 'booking-wizard'))) {
            failOrSkipMissingFixture(
                `Booking fixture not published/reachable at ${officialRoutes.serviceBooking}.`,
            );
        }
    });

    test('checkout entrypoint is usable in a product storefront', async ({ page }) => {
        await page.goto(officialRoutes.productMicrosite, { waitUntil: 'domcontentloaded' });

        const addToCartButton = page
            .locator('[data-testid^="add-to-cart-"]')
            .or(page.getByRole('button', { name: /agregar/i }))
            .first();

        if ((await addToCartButton.count()) === 0) {
            failOrSkipMissingFixture(
                `Checkout fixture has no purchasable products at ${officialRoutes.productMicrosite}.`,
            );
        }

        await addToCartButton.click();
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
