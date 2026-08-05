import { expect, test } from '@playwright/test';

import {
    assertAxe,
    assertNoHorizontalOverflow,
    assertReducedMotion,
    assertReflow,
    visitRoutedSurface,
} from './support/dovra-routed-surface';

test.use({ trace: 'off' });

test('routed surfaces meet the local accessibility smoke boundary', async ({ page }) => {
    test.setTimeout(120_000);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect.poll(() => page.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches)).toBe(true);

    await page.setViewportSize({ width: 360, height: 800 });
    await visitRoutedSurface(page, '/directorio');
    await assertReducedMotion(page, {
        requiredSelectors: ['a[href="#main-content"]'],
        optionalSelectors: ['[data-testid="tenant-card"]'],
    });
    await expect(page.locator('#main-content')).toHaveCount(1);
    await expect(page.locator('#main-content')).toBeVisible();
    await assertAxe(page);

    await page.keyboard.press('Tab');
    const directorySkipLink = page.getByRole('link', { name: 'Saltar al contenido principal' });
    await expect(directorySkipLink.first()).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();
    await assertNoHorizontalOverflow(page);
    await assertReflow(page);
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
    const registrationSkipLink = page.getByRole('link', { name: 'Saltar al contenido principal' });
    await expect(registrationSkipLink.first()).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();
    await assertNoHorizontalOverflow(page);
    await assertReflow(page);
    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });
    await assertReflow(page);
    await page.evaluate(() => {
        document.documentElement.style.fontSize = '';
    });
});
