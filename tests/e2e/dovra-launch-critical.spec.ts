import { expect, test } from '@playwright/test';

import {
    assertAxe,
    assertNoHorizontalOverflow,
    assertReducedMotion,
    assertReflow,
    visitRoutedSurface,
} from './support/dovra-routed-surface';

test.use({ trace: 'off' });

const mutatingMethods = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

test('routed buyer and merchant critical journeys stay read-only', async ({ page }) => {
    test.setTimeout(120_000);

    const mutatingRequests: string[] = [];
    page.on('request', (request) => {
        if (mutatingMethods.has(request.method())) {
            mutatingRequests.push(`${request.method()} ${request.url()}`);
        }
    });

    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setViewportSize({ width: 1280, height: 900 });
    await visitRoutedSurface(page, '/directorio');
    await assertReducedMotion(page, {
        requiredSelectors: ['a[href="#main-content"]'],
        optionalSelectors: ['[data-testid="tenant-card"]'],
    });
    await expect(page).toHaveURL(/\/directorio(?:\?|$)/u);
    await expect(page.locator('#main-content')).toBeVisible();
    await expect(page.getByRole('heading', { name: /Directorio/i }).first()).toBeVisible();

    const search = page.locator('input[type="search"]').first();
    await expect(search).toBeVisible();

    const searchValue = `dovra-e2e-no-match-${Date.now()}`;
    await search.fill(searchValue);
    await expect.poll(
        () => new URL(page.url()).searchParams.get('search'),
        { timeout: 15_000 },
    ).toBe(searchValue);

    const clearSearch = page.getByRole('button', { name: /Quitar filtro/i }).first();
    if (await clearSearch.count()) {
        await clearSearch.click();
        await expect.poll(
            () => new URL(page.url()).searchParams.get('search'),
            { timeout: 15_000 },
        ).toBeNull();
    }

    expect(mutatingRequests, mutatingRequests.join('\n')).toEqual([]);

    await visitRoutedSurface(page, '/registrar-negocio');
    await assertReducedMotion(page, {
        requiredSelectors: ['a[href="#main-content"]'],
        optionalSelectors: ['button[data-testid^="register-step-"]'],
    });
    await expect(page).toHaveURL(/\/registrar-negocio(?:\?|$)/u);
    await expect(page.locator('#main-content')).toBeVisible();
    await expect(page.getByRole('heading', { name: /Crea tu negocio/i })).toBeVisible();

    const businessName = page.getByLabel('Nombre comercial');
    await expect(businessName).toBeVisible();
    await businessName.fill('Dovra E2E Read Only');

    await page.getByTestId('register-category-select').click();
    await page.getByRole('option', { name: 'Restaurantes', exact: true }).click();
    await expect(page.locator('#business-type-products')).toBeChecked();

    const nextStep = page.getByTestId('register-next-step');
    await expect(nextStep).toBeEnabled();
    await nextStep.click();
    await expect(page.getByRole('heading', { name: /Zona, contacto y cuenta/i })).toBeVisible();
    await expect(page.getByTestId('register-submit')).toHaveCount(0);

    expect(mutatingRequests, mutatingRequests.join('\n')).toEqual([]);
});

test('routed surfaces expose a bounded accessibility smoke at desktop and mobile widths', async ({ page }) => {
    test.setTimeout(120_000);
    await page.emulateMedia({ reducedMotion: 'reduce' });

    await page.setViewportSize({ width: 360, height: 800 });
    await visitRoutedSurface(page, '/directorio');
    await assertReducedMotion(page, {
        requiredSelectors: ['a[href="#main-content"]'],
        optionalSelectors: ['[data-testid="tenant-card"]'],
    });
    await expect(page.locator('#main-content')).toBeVisible();
    await assertAxe(page);

    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Saltar al contenido principal' }).first()).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();
    await assertNoHorizontalOverflow(page);
    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });
    await assertReflow(page);
    await page.evaluate(() => {
        document.documentElement.style.fontSize = '';
    });

    await page.setViewportSize({ width: 360, height: 800 });
    await visitRoutedSurface(page, '/registrar-negocio');
    await assertReducedMotion(page, {
        requiredSelectors: ['a[href="#main-content"]'],
        optionalSelectors: ['button[data-testid^="register-step-"]'],
    });
    await expect(page.locator('#main-content')).toHaveCount(1);
    await expect(page.locator('#main-content')).toBeVisible();
    await assertAxe(page);

    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Saltar al contenido principal' }).first()).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();
    await assertNoHorizontalOverflow(page);
    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });
    await assertReflow(page);
    await page.evaluate(() => {
        document.documentElement.style.fontSize = '';
    });
});
