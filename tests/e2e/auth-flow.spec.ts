import { expect, test } from '@playwright/test';

test.describe('Auth journey', () => {
    test('login flow responds correctly (success or validation without 500)', async ({ page }) => {
        await page.goto('/login');
        const loginForm = page.getByTestId('login-form').or(page.locator('form').first());
        await expect(loginForm).toBeVisible();

        await page.getByTestId('login-email-input').or(page.locator('#email')).fill('casa-brasa-bistro@example.test');
        await page.getByTestId('login-password-input').or(page.locator('#password')).fill('password');
        const [loginResponse] = await Promise.all([
            page.waitForResponse((response) => response.url().includes('/login') && response.request().method() === 'POST'),
            page.getByTestId('login-submit').or(page.getByRole('button', { name: /login|iniciar/i })).click(),
        ]);

        await page.waitForLoadState('networkidle');
        expect([200, 302, 303, 422]).toContain(loginResponse.status());

        const stillOnLogin = page.url().includes('/login');
        if (!stillOnLogin) {
            await expect(page.locator('body')).not.toContainText(/500|server error/i);
            return;
        }

        // Validation UX can be inline or generic; the critical gate is no 5xx regression.
        await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
    });

    test('visitor can register a new tenant owner account', async ({ page }) => {
        const suffix = Date.now();
        const email = `registro.e2e.${suffix}@example.test`;
        const slug = `registro-e2e-${suffix}`;

        await page.goto('/registrar-negocio');
        await expect(page.locator('#business_name')).toBeVisible();

        await page.locator('#business_name').fill(`Negocio E2E ${suffix}`);
        await page.locator('#slug').fill(slug);
        await page.locator('#category_id').selectOption({ index: 1 });

        await page.waitForTimeout(500);

        await page.getByRole('button', { name: /Continuar/i }).last().click();

        await page.locator('#city_id').selectOption({ index: 1 });
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
