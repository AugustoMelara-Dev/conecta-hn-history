import { expect, test } from '@playwright/test';
import { expectNoFatalServerText, expectNon5xx, gotoWithRetries } from './support/smoke-http';
import { officialFixtures } from './support/fixtures';

const isSmokeMode = process.env.E2E_MODE === 'smoke';

async function selectShadcnOption(page, triggerTestId, index = 1) {
    const trigger = page.getByTestId(triggerTestId);
    await expect(trigger).toBeVisible({ timeout: 10000 });
    await trigger.click();

    await expect.poll(async () => {
        return await page.getByRole('option').count();
    }, {
        timeout: 15000,
        message: 'Expected select options to be loaded.',
    }).toBeGreaterThan(1);

    await page.getByRole('option').nth(index).click();
}

async function waitForSlugCheckComplete(page) {
    const reviewingText = page.getByText(/revisando|validando url|espera a que termine/i);
    const statusText = page.getByText(/disponible|ocupada|pendiente/i);

    for (let attempt = 0; attempt < 12; attempt += 1) {
        const isReviewing = await reviewingText.first().isVisible().catch(() => false);
        const hasStatus = await statusText.first().isVisible().catch(() => false);

        if (!isReviewing && hasStatus) {
            return;
        }

        if (!isReviewing && !hasStatus) {
            await page.waitForTimeout(600);
            continue;
        }

        await page.waitForTimeout(800);
    }

    throw new Error('Slug validation did not complete in time.');
}

async function goToLocationStep(page) {
    const continueButton = page.getByRole('button', { name: /Continuar/i }).last();
    const citySelect = page.getByTestId('register-city-select');

    for (let attempt = 0; attempt < 4; attempt += 1) {
        await continueButton.click();

        if (await citySelect.isVisible({ timeout: 3000 }).catch(() => false)) {
            return citySelect;
        }

        const slugValidationInProgress = await page
            .locator('body')
            .getByText(/revisi|validando url|revisando/i)
            .isVisible()
            .catch(() => false);

        if (slugValidationInProgress) {
            await page.waitForTimeout(800);
        }
    }

    throw new Error('Registration wizard did not advance to location step.');
}

test.describe('Auth journey', () => {
    test('login flow responds correctly (success or validation without 500)', async ({ page }) => {
        const loginResponse = await gotoWithRetries(page, '/login', {
            attempts: 2,
            delayMs: 600,
            responseTimeoutMs: 8_000,
        });
        expectNon5xx(loginResponse, '/login');
        await expectNoFatalServerText(page);

        const loginForm = page
            .getByTestId('login-form')
            .or(page.locator('form').first())
            .or(page.locator('body').getByText(/iniciar sesi[oó]n|login|sign in/i).first())
            .first();
        await expect(loginForm).toBeVisible();

        if (isSmokeMode) {
            return;
        }

        await page.getByTestId('login-email-input').or(page.locator('#email')).fill(officialFixtures.owner.email);
        await page.getByTestId('login-password-input').or(page.locator('#password')).fill(officialFixtures.owner.password);
        const [submitResponse] = await Promise.all([
            page.waitForResponse((response) => response.url().includes('/login') && response.request().method() === 'POST'),
            page.getByTestId('login-submit').or(page.getByRole('button', { name: /login|iniciar/i })).click(),
        ]);

        expect([200, 302, 303, 409, 422]).toContain(submitResponse.status());
        await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 }).catch(() => null);

        const stillOnLogin = page.url().includes('/login');
        if (!stillOnLogin) {
            await page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => null);
            await expectNoFatalServerText(page);
            return;
        }

        // Validation UX can be inline or generic; the critical gate is no 5xx regression.
        await expectNoFatalServerText(page);
    });

    test('visitor can register a new tenant owner account', async ({ page }) => {
        test.setTimeout(120_000);

        const suffix = Date.now();
        const email = `registro.e2e.${suffix}@example.test`;
        const slug = `registro-e2e-${suffix}`;

        const registerResponse = await gotoWithRetries(page, '/registrar-negocio', {
            attempts: 2,
            delayMs: 600,
            responseTimeoutMs: 8_000,
        });
        expectNon5xx(registerResponse, '/registrar-negocio');
        await expectNoFatalServerText(page);

        const registrationSurface = page
            .locator('#business_name')
            .or(page.locator('body').getByText(/registrar negocio|crear negocio|crear cuenta/i).first())
            .first();
        await expect(registrationSurface).toBeVisible();

        if (isSmokeMode) {
            return;
        }

        await page.locator('#business_name').fill(`Negocio E2E ${suffix}`);
        const slugInput = page.locator('#slug');
        const slugCheckResponse = page.waitForResponse((response) => {
            const url = response.url();
            return (url.includes('/check-slug') || url.includes('/api/check-slug'))
                && response.request().method() === 'GET';
        }, { timeout: 20000 }).catch(() => null);
        await slugInput.fill(slug);
        await slugInput.blur();
        await slugCheckResponse;
        await selectShadcnOption(page, 'register-category-select');

        await page.getByRole('radio', { name: /Tienda y cat\u00e1logo|Tienda y catalogo|Restaurante y comida|Servicios y citas|Mixto/i }).first().click();
        await waitForSlugCheckComplete(page);

        await page.waitForTimeout(600);
        const citySelect = await goToLocationStep(page);
        await expect(citySelect).toBeVisible({ timeout: 10000 });
        await selectShadcnOption(page, 'register-city-select');
        await page.getByRole('button', { name: /Continuar/i }).last().click();

        await page.locator('#name').fill('Registro E2E Owner');
        await page.locator('#email').fill(email);
        await page.locator('#password').fill('password');
        await page.locator('#password_confirmation').fill('password');
        await page.getByRole('button', { name: /Continuar/i }).last().click();

        await page.getByTestId('register-terms-checkbox').click();
        await page.getByTestId('register-privacy-checkbox').click();
        await page.getByRole('button', { name: /Crear negocio|Crear cuenta/i }).click();

        await page.waitForURL((url) => !url.pathname.includes('/registrar-negocio'), { timeout: 30000 });
        expect(page.url().includes('/registrar-negocio')).toBeFalsy();
    });

    test('onboarding boundary preserves actionable recovery path (verify-email or billing)', async ({ page }) => {
        test.setTimeout(150_000);

        const suffix = Date.now();
        const email = `onboarding.boundary.${suffix}@example.test`;
        const slug = `onboarding-boundary-${suffix}`;

        const registerResponse = await gotoWithRetries(page, '/registrar-negocio', {
            attempts: 2,
            delayMs: 600,
            responseTimeoutMs: 8_000,
        });
        expectNon5xx(registerResponse, '/registrar-negocio');
        await expectNoFatalServerText(page);

        if (isSmokeMode) {
            return;
        }

        await page.locator('#business_name').fill(`Onboarding Boundary ${suffix}`);
        const slugInput = page.locator('#slug');
        await slugInput.fill(slug);
        await slugInput.blur();
        await selectShadcnOption(page, 'register-category-select');
        await page.getByRole('radio', { name: /Tienda y catálogo|Tienda y catalogo|Restaurante y comida|Servicios y citas|Mixto/i }).first().click();
        await waitForSlugCheckComplete(page);

        const citySelect = await goToLocationStep(page);
        await expect(citySelect).toBeVisible({ timeout: 10000 });
        await selectShadcnOption(page, 'register-city-select');
        await page.getByRole('button', { name: /Continuar/i }).last().click();

        await page.locator('#name').fill('Onboarding Boundary Owner');
        await page.locator('#email').fill(email);
        await page.locator('#password').fill('password');
        await page.locator('#password_confirmation').fill('password');
        await page.getByRole('button', { name: /Continuar/i }).last().click();

        await page.getByTestId('register-terms-checkbox').click();
        await page.getByTestId('register-privacy-checkbox').click();
        await page.getByRole('button', { name: /Crear negocio|Crear cuenta/i }).click();

        await page.waitForURL((url) => !url.pathname.includes('/registrar-negocio'), { timeout: 30000 });
        const path = new URL(page.url()).pathname;

        expect(
            /\/verify-email|\/registro\/pago-pendiente|\/app\//.test(path),
            `Unexpected onboarding boundary route: ${path}`
        ).toBeTruthy();

        if (path.includes('/verify-email')) {
            await expect(page.getByTestId('verify-email-resend')).toBeVisible({ timeout: 10000 });
            await expect(page.getByTestId('verify-email-onboarding-next')).toBeVisible({ timeout: 10000 });
        }

        if (path.includes('/registro/pago-pendiente')) {
            await expect(page.locator('body')).toContainText(/pago|billing|factur/i, { timeout: 10000 });
            await expect(page.locator('body')).toContainText(/siguiente|continuar|soporte|reintentar/i, { timeout: 10000 });
        }
    });
});
