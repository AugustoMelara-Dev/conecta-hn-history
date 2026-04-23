import { expect, test } from '@playwright/test';
import { expectNoFatalServerText, expectNon5xx, gotoWithRetries } from './support/smoke-http';
import { officialFixtures } from './support/fixtures';

const isSmokeMode = process.env.E2E_MODE === 'smoke';

async function waitForSelectOptions(selectLocator) {
    await expect.poll(async () => {
        return await selectLocator.locator('option').count();
    }, {
        timeout: 15000,
        message: 'Expected select options to be loaded.',
    }).toBeGreaterThan(1);
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
    const citySelect = page.locator('#city_id');

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

        await page.waitForLoadState('networkidle');
        expect([200, 302, 303, 422]).toContain(submitResponse.status());

        const stillOnLogin = page.url().includes('/login');
        if (!stillOnLogin) {
            await expect(page.locator('body')).not.toContainText(/500|server error/i);
            return;
        }

        // Validation UX can be inline or generic; the critical gate is no 5xx regression.
        await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
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
        const categorySelect = page.locator('#category_id');
        await waitForSelectOptions(categorySelect);
        await categorySelect.selectOption({ index: 1 });

        await page.getByRole('button', { name: /Tienda y cat\u00e1logo|Restaurante y comida|Servicios y citas|Mixto/i }).first().click();
        await waitForSlugCheckComplete(page);

        await page.waitForTimeout(600);
        const citySelect = await goToLocationStep(page);
        await expect(citySelect).toBeVisible({ timeout: 10000 });
        await waitForSelectOptions(citySelect);
        await citySelect.selectOption({ index: 1 });
        await page.getByRole('button', { name: /Continuar/i }).last().click();

        await page.locator('#name').fill('Registro E2E Owner');
        await page.locator('#email').fill(email);
        await page.locator('#password').fill('password');
        await page.locator('#password_confirmation').fill('password');
        await page.getByRole('button', { name: /Continuar/i }).last().click();

        await page.locator('input[type="checkbox"]').first().check();
        await page.getByRole('button', { name: /Crear negocio|Crear cuenta/i }).click();

        await page.waitForURL((url) => !url.pathname.includes('/registrar-negocio'), { timeout: 30000 });
        expect(page.url().includes('/registrar-negocio')).toBeFalsy();
    });
});
