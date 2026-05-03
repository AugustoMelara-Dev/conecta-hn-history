import { expect, test, type Page } from '@playwright/test';
import { officialRoutes } from './support/fixtures';

async function assertNoDeadCtas(page: Page): Promise<void> {
    const deadAnchors = await page.locator('a[href="#"]').count();
    expect(deadAnchors, 'Dead CTA anchors (href="#") are not allowed.').toBe(0);
}

async function assertMainHeading(page: Page): Promise<void> {
    const headings = page.locator('h1, h2');
    if (await headings.count()) {
        await expect(headings.first()).toBeVisible();
        return;
    }

    const checkoutSubmit = page.getByTestId('checkout-submit').first();
    if (await checkoutSubmit.count()) {
        await expect(checkoutSubmit).toBeVisible();
        return;
    }

    await expect(page.locator('main, [role="main"]').first()).toBeVisible();
}

async function assertFocusableUi(page: Page): Promise<void> {
    await page.keyboard.press('Tab');
    const activeTag = await page.evaluate(() => document.activeElement?.tagName ?? '');
    expect(activeTag, 'Expected focus to move into an interactive element.').not.toBe('BODY');
}

async function assertNamedButtons(page: Page): Promise<void> {
    const unnamedVisibleButtons = await page.evaluate(() => {
        const buttons = Array.from(document.querySelectorAll('button'));

        return buttons.filter((button) => {
            const style = window.getComputedStyle(button);
            const visible = style.display !== 'none' && style.visibility !== 'hidden';
            const hasName = Boolean(
                button.getAttribute('aria-label')
                || button.getAttribute('aria-labelledby')
                || button.textContent?.trim(),
            );
            const role = (button.getAttribute('role') || '').toLowerCase();
            const labelledByContainer = Boolean(button.closest('label'));
            const isFormPrimitiveControl = ['radio', 'checkbox', 'switch'].includes(role);

            return visible && !hasName && !(isFormPrimitiveControl && labelledByContainer);
        }).length;
    });

    expect(unnamedVisibleButtons, 'Visible buttons must expose an accessible name.').toBe(0);
}

async function assertNoDeadCtaText(page: Page): Promise<void> {
    const deadCtaCount = await page
        .getByRole('link')
        .filter({ hasText: /saber m[aá]s|click aqu[ií]|ver m[aá]s|m[aá]s info/i })
        .count();
    expect(deadCtaCount, 'Dead CTA copy should not remain in runtime routes.').toBe(0);
}

async function assertNoHorizontalOverflow(page: Page): Promise<void> {
    const overflows = await page.evaluate(() => {
        const viewport = window.innerWidth;
        const candidates = Array.from(document.querySelectorAll('body *'));

        return candidates.filter((node) => {
            if (!(node instanceof HTMLElement)) {
                return false;
            }

            const rect = node.getBoundingClientRect();
            return rect.width > 0 && rect.right - rect.left > viewport + 2;
        }).length;
    });

    expect(overflows, 'Unexpected horizontal overflow detected.').toBe(0);
}

async function assertFormControlsHaveProgrammaticNames(page: Page): Promise<void> {
    const unnamedControls = await page.evaluate(() => {
        const controls = Array.from(document.querySelectorAll('input, select, textarea'));

        return controls.filter((control) => {
            const element = control as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;
            const type = 'type' in element ? (element.type ?? '').toLowerCase() : '';
            const style = window.getComputedStyle(element);

            if (type === 'hidden' || type === 'radio' || type === 'checkbox') {
                return false;
            }

            if (element.disabled || element.getAttribute('aria-hidden') === 'true') {
                return false;
            }

            if (style.display === 'none' || style.visibility === 'hidden') {
                return false;
            }

            const isVisible = ((element.offsetParent !== null) || element.getClientRects().length > 0)
                && element.getBoundingClientRect().width > 1
                && element.getBoundingClientRect().height > 1;
            if (!isVisible) {
                return false;
            }

            const id = element.getAttribute('id');
            const hasLabelFor = id ? document.querySelector(`label[for="${id}"]`) : null;
            const hasAria = Boolean(element.getAttribute('aria-label') || element.getAttribute('aria-labelledby'));

            return !hasLabelFor && !hasAria;
        }).length;
    });

    expect(unnamedControls, 'Visible form controls must have a label or aria name.').toBe(0);
}

test.describe('UIUX visual + accessibility smoke @smoke', () => {
    const desktopPaths = [
        { name: 'landing', path: '/' },
        { name: 'login', path: '/login' },
        { name: 'register', path: '/registrar-negocio' },
        { name: 'products', path: officialRoutes.productMicrosite },
        { name: 'checkout', path: `${officialRoutes.productMicrosite}/checkout` },
        { name: 'microsite', path: officialRoutes.serviceMicrosite },
        { name: 'payment-pending-public', path: '/payment/pending' },
    ];

    for (const target of desktopPaths) {
        test(`desktop smoke: ${target.name}`, async ({ page }) => {
            const response = await page.goto(target.path, { waitUntil: 'domcontentloaded' });
            expect(response?.status(), `${target.path} should not fail with 5xx`).toBeLessThan(500);

            if (response?.status() === 404) {
                test.skip(true, `Route ${target.path} is not available in this environment.`);
            }

            await assertNoDeadCtas(page);
            await assertNoDeadCtaText(page);
            await assertMainHeading(page);
            await assertNamedButtons(page);
            await assertFocusableUi(page);
            await assertFormControlsHaveProgrammaticNames(page);
            await page.screenshot({ path: `test-results/uiux-${target.name}-desktop.png`, fullPage: true });
        });
    }

    test('mobile viewport baseline: landing', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        const response = await page.goto('/', { waitUntil: 'domcontentloaded' });
        expect(response?.status()).toBeLessThan(500);

        await assertNoDeadCtas(page);
        await assertMainHeading(page);
        await assertNoHorizontalOverflow(page);
        await page.screenshot({ path: 'test-results/uiux-landing-mobile.png', fullPage: true });
    });

    test('growth center auth smoke (when fixture auth is viable)', async ({ page }) => {
        await page.goto('/login', { waitUntil: 'domcontentloaded' });

        const emailInput = page.getByTestId('login-email-input').or(page.locator('#email')).first();
        const passwordInput = page.getByTestId('login-password-input').or(page.locator('#password')).first();
        const submitButton = page.getByTestId('login-submit').or(page.getByRole('button', { name: /login|iniciar/i })).first();

        if ((await emailInput.count()) === 0 || (await passwordInput.count()) === 0 || (await submitButton.count()) === 0) {
            test.skip(true, 'Login fixture selectors are not available in this environment.');
        }

        await emailInput.fill(process.env.SMOKE_OWNER_EMAIL || 'owner-demo-ai-guide-hn@vitobusinessos.app');
        await passwordInput.fill(process.env.SMOKE_OWNER_PASSWORD || 'ChangeMe123!');
        await submitButton.click();
        await page.waitForURL(/\/(app|admin|login)/, { timeout: 20000 });

        if (page.url().includes('/login')) {
            test.skip(true, 'Auth fixture did not login in this environment; skipping Growth Center screenshot.');
        }

        const growthResponse = await page.goto('/app/growth-center', { waitUntil: 'domcontentloaded' });
        expect(growthResponse?.status()).toBeLessThan(500);
        await page.screenshot({ path: 'test-results/uiux-growth-center-desktop.png', fullPage: true });
    });

    test('payment pending exposes live region when route is available', async ({ page }) => {
        const response = await page.goto('/payment/pending', { waitUntil: 'domcontentloaded' });
        expect(response?.status()).toBeLessThan(500);

        if (response?.status() === 404) {
            test.skip(true, 'Payment pending route is not available in this environment.');
        }

        const liveRegions = await page.locator('[aria-live]').count();
        expect(liveRegions, 'Payment pending should expose at least one live region.').toBeGreaterThan(0);
    });
});
