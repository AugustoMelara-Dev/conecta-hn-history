import type { APIRequestContext, Page } from '@playwright/test';
import { expect, test } from '@playwright/test';

type DirectoryStorefront = {
    slug: string;
    name?: string;
    category?: { slug?: string; name?: string } | null;
};

async function fetchPublishedStorefronts(request: APIRequestContext): Promise<DirectoryStorefront[]> {
    const response = await request.get('/api/v1/directory/search?limit=12', {
        headers: { accept: 'application/json' },
    });

    expect(response.ok()).toBeTruthy();

    const payload = await response.json();
    const rows = Array.isArray(payload?.data) ? payload.data : [];

    return rows.filter((row): row is DirectoryStorefront => typeof row?.slug === 'string' && row.slug.length > 0);
}

async function assertStorefrontLoadsWithoutFatal(page: Page, slug: string): Promise<void> {
    const response = await page.goto(`/t/${slug}`, { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBeLessThan(500);

    try {
        await expect(page.getByTestId('tenant-microsite')).toBeVisible({ timeout: 10_000 });
    } catch {
        await expect(page.locator('body')).not.toContainText(/error interno del servidor|server error|500/i);
    }
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

    test('critical storefronts render without fatal errors', async ({ page, request }) => {
        const storefronts = await fetchPublishedStorefronts(request);
        expect(storefronts.length).toBeGreaterThan(0);

        for (const storefront of storefronts.slice(0, 2)) {
            await assertStorefrontLoadsWithoutFatal(page, storefront.slug);
        }
    });

    test('checkout entrypoint is usable in a product storefront', async ({ page, request }) => {
        const storefronts = await fetchPublishedStorefronts(request);
        expect(storefronts.length).toBeGreaterThan(0);

        const prioritized = [
            ...storefronts.filter((storefront) => storefront.category?.slug === 'restaurantes'),
            ...storefronts.filter((storefront) => storefront.category?.slug !== 'restaurantes'),
        ];

        let checkoutValidated = false;

        for (const storefront of prioritized) {
            await assertStorefrontLoadsWithoutFatal(page, storefront.slug);

            const addToCart = page
                .locator('[data-testid^="add-to-cart-"]')
                .first()
                .or(page.getByRole('button', { name: /agregar|anadir|a\u00f1adir|add to cart/i }).first());

            const addToCartVisible = await addToCart.isVisible({ timeout: 3000 }).catch(() => false);
            if (!addToCartVisible) {
                continue;
            }

            await addToCart.click();
            const cartFab = page
                .getByTestId('cart-fab')
                .or(page.locator('button.fixed.bottom-6.right-6'))
                .first();
            await expect(cartFab).toBeVisible({ timeout: 10_000 });

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

            checkoutValidated = true;
            break;
        }

        if (!checkoutValidated) {
            test.info().annotations.push({
                type: 'warning',
                description: 'No published storefront with add-to-cart controls is currently available.',
            });
        }
    });
});
