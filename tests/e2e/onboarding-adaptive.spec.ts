import { expect, test } from '@playwright/test';

test.use({ trace: 'off' });

test('fresh owner sees mandatory adaptive onboarding and a real next-step route', async ({ page }) => {
    test.setTimeout(120_000);

    await page.route('**/*', async (route) => {
        const url = new URL(route.request().url());
        if (
            url.pathname.startsWith('/build/assets/')
            || url.pathname.startsWith('/css/')
            || url.pathname.startsWith('/js/')
            || url.pathname === '/livewire/livewire.min.js'
        ) {
            await route.abort();
            return;
        }

        await route.continue();
    });

    const loginResponse = await page.goto('/login', { waitUntil: 'domcontentloaded' });
    expect(loginResponse?.status()).toBe(200);
    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');

    const auth = await page.evaluate(async ({ token }) => {
        const body = new URLSearchParams();
        body.set('email', 'owner-demo-ai-guide-hn@conectahn.test');
        body.set('password', 'ChangeMe123!');
        body.set('_token', token || '');

        const response = await fetch('/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-TOKEN': token || '',
                Accept: 'text/html, application/xhtml+xml',
            },
            body,
            credentials: 'same-origin',
            redirect: 'follow',
        });

        return { status: response.status, path: new URL(response.url).pathname };
    }, { token: csrfToken });

    expect(auth.status).toBeLessThan(500);
    expect(auth.path).not.toBe('/login');

    const dashboardResponse = await page.goto('/app/demo-ai-guide-hn', {
        waitUntil: 'domcontentloaded',
        timeout: 90_000,
    });
    expect(dashboardResponse?.status()).toBe(200);

    const wizard = page.getByTestId('first-run-wizard');
    await expect(wizard).toBeVisible();
    await expect(wizard.locator('[aria-current="step"]').first()).toBeVisible();
    await expect(page.getByTestId('launch-readiness-card')).toBeVisible();

    const openStep = page.getByTestId('first-run-open-step');
    await expect(openStep).toBeVisible();
    const href = await openStep.getAttribute('href');
    expect(href).toBeTruthy();
    expect(href).not.toBe('#');

    const nextResponse = await page.goto(new URL(href!, page.url()).toString(), {
        waitUntil: 'domcontentloaded',
        timeout: 90_000,
    });
    expect(nextResponse?.status()).toBeLessThan(500);
});
