import { expect, test } from '@playwright/test';

test.describe('post-deploy smoke', () => {
    test('critical public routes respond without server errors', async ({ request }) => {
        const paths = ['/', '/up', '/login', '/register', '/explore', '/registrar-negocio'];

        for (const path of paths) {
            const response = await request.get(path, { failOnStatusCode: false });
            expect(response.status(), `${path} should not return 5xx`).toBeLessThan(500);
        }
    });

    test('explore page renders visible discovery shell', async ({ page }) => {
        const response = await page.goto('/explore', { waitUntil: 'domcontentloaded' });
        expect(response?.status(), '/explore should be reachable').toBeLessThan(500);
        await expect(page).toHaveURL(/\/explore$/);
        await expect(page.locator('#explore-search')).toBeVisible();
    });
});
