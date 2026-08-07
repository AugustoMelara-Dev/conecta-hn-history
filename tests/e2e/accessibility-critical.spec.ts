import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test.use({ trace: 'off' });

const publicPages = [
    { name: 'landing', path: '/' },
    { name: 'login', path: '/login' },
    { name: 'registration', path: '/registrar-negocio' },
];

for (const { name, path } of publicPages) {
    test(`${name} has no detected WCAG A/AA violations`, async ({ page }) => {
        await page.goto(path, { waitUntil: 'networkidle' });

        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
            .analyze();
        expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
    });

    test(`${name} exposes a working skip link and reflows at 360px`, async ({ page }) => {
        await page.setViewportSize({ width: 360, height: 800 });
        await page.goto(path, { waitUntil: 'networkidle' });

        await page.keyboard.press('Tab');
        const skipLink = page.getByRole('link', { name: 'Saltar al contenido principal' });
        await expect(skipLink).toBeFocused();
        await expect(skipLink).toBeVisible();
        await page.keyboard.press('Enter');
        await expect(page.locator('#main-content')).toBeFocused();

        const hasHorizontalOverflow = await page.evaluate(() =>
            document.documentElement.scrollWidth > document.documentElement.clientWidth
        );
        expect(hasHorizontalOverflow).toBe(false);

        await page.evaluate(() => {
            document.documentElement.style.fontSize = '200%';
        });
        const textZoomOverflow = await page.evaluate(() => {
            const viewportWidth = document.documentElement.clientWidth;
            const offenders = Array.from(document.querySelectorAll('body *'))
                .filter((element) => {
                    const rect = element.getBoundingClientRect();
                    return rect.right > viewportWidth + 1 || rect.left < -1;
                })
                .slice(0, 12)
                .map((element) => {
                    const rect = element.getBoundingClientRect();
                    return {
                        tag: element.tagName,
                        className: String(element.className || ''),
                        text: String(element.textContent || '').trim().slice(0, 100),
                        left: Math.round(rect.left),
                        right: Math.round(rect.right),
                        width: Math.round(rect.width),
                        computedWidth: getComputedStyle(element).width,
                        minWidth: getComputedStyle(element).minWidth,
                        display: getComputedStyle(element).display,
                        parent: element.parentElement
                            ? {
                                tag: element.parentElement.tagName,
                                className: String(element.parentElement.className || ''),
                                width: Math.round(element.parentElement.getBoundingClientRect().width),
                                computedWidth: getComputedStyle(element.parentElement).width,
                                minWidth: getComputedStyle(element.parentElement).minWidth,
                            }
                            : null,
                    };
                });

            const allInternalOverflows = Array.from(document.querySelectorAll('body *'))
                .filter((element) => element.scrollWidth > element.clientWidth + 1)
                .map((element) => ({
                    tag: element.tagName,
                    className: String(element.className || ''),
                    text: String(element.textContent || '').trim().slice(0, 100),
                    scrollWidth: element.scrollWidth,
                    clientWidth: element.clientWidth,
                    overflowX: getComputedStyle(element).overflowX,
                }));
            const internalOverflows = allInternalOverflows.slice(0, 20);
            const relevantInternalOverflows = allInternalOverflows
                .filter((overflow) =>
                    overflow.clientWidth > 1
                    && overflow.overflowX === 'visible'
                    && overflow.text.length > 0
                )
                .slice(0, 20);

            return {
                overflow: document.documentElement.scrollWidth > viewportWidth,
                documentWidth: document.documentElement.scrollWidth,
                bodyWidth: document.body.scrollWidth,
                viewportWidth,
                offenders,
                internalOverflows,
                relevantInternalOverflows,
            };
        });
        expect(textZoomOverflow, JSON.stringify(textZoomOverflow, null, 2)).toMatchObject({
            overflow: false,
            relevantInternalOverflows: [],
        });

        if (name === 'landing') {
            const menuButton = page.getByRole('button', { name: 'Abrir menú' });
            await menuButton.click();
            await expect(page.getByRole('button', { name: 'Cerrar menú' })).toHaveAttribute('aria-expanded', 'true');
        }
    });
}

test('landing dark theme has no detected WCAG A/AA violations', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Usar tema oscuro' }).click();
    await expect(page.locator('div.dark').first()).toBeVisible();

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
});
test('reduced-motion preference collapses nonessential CSS motion', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/', { waitUntil: 'networkidle' });

    expect(await page.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches)).toBe(true);
    const motionDurations = await page.evaluate(() => {
        const probe = document.createElement('div');
        probe.className = 'animate-spin transition-all';
        probe.setAttribute('aria-hidden', 'true');
        document.body.appendChild(probe);
        const styles = getComputedStyle(probe);
        const toMilliseconds = (value) => value.endsWith('ms')
            ? Number.parseFloat(value)
            : Number.parseFloat(value) * 1000;
        const result = {
            animation: toMilliseconds(styles.animationDuration.split(',')[0]?.trim() || '0s'),
            transition: toMilliseconds(styles.transitionDuration.split(',')[0]?.trim() || '0s'),
        };
        probe.remove();
        return result;
    });

    expect(motionDurations.animation).toBeLessThanOrEqual(1);
    expect(motionDurations.transition).toBeLessThanOrEqual(1);

});
