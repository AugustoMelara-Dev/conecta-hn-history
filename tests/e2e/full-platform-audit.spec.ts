import { expect, test } from '@playwright/test';
import { officialFixtures, officialRoutes } from './support/fixtures';
import {
    loginSubmitButton,
    registerNextStepButton,
    registerSubmitButton,
    registerTermsCheckbox,
} from './support/auth-locators';

async function pickShadcnSelect(page, triggerTestId, optionPattern) {
    const trigger = page.getByTestId(triggerTestId).first();
    await expect(trigger).toBeVisible({ timeout: 15_000 });
    await trigger.click();
    const options = page.getByRole('option', { name: optionPattern });
    await expect(options.first()).toBeVisible({ timeout: 15_000 });
    const option = (await options.count()) > 1 ? options.nth(1) : options.first();
    await option.click();
}

async function advanceToLocationStep(page) {
    const citySelect = page.getByTestId('register-city-select').first();
    const nextButton = registerNextStepButton(page);

    for (let attempt = 0; attempt < 6; attempt += 1) {
        await nextButton.click();
        if (await citySelect.isVisible({ timeout: 2_500 }).catch(() => false)) {
            return;
        }
        await page.waitForTimeout(700);
    }

    throw new Error('Registration wizard did not advance to location step.');
}

const SEEDED_OWNER = {
    email: officialFixtures.owner.email,
    password: officialFixtures.owner.password,
    microsite: officialRoutes.productMicrosite,
};

function uniqueRegistrationIdentity() {
    const stamp = Date.now();

    return {
        businessName: `Vito Audit ${stamp}`,
        ownerName: `Owner Audit ${stamp}`,
        email: `audit+${stamp}@example.test`,
        password: 'password123',
    };
}

test.describe('Auditoria funcional E2E', () => {
    test('explore -> micrositio -> checkout crea orden sin error de servidor', async ({ page }) => {
        const exploreResponse = await page.goto('/explore', { waitUntil: 'domcontentloaded' });
        expect(exploreResponse?.status(), '/explore must be reachable').toBeLessThan(500);

        const exploreReadyMarker = page
            .locator('#explore-search')
            .or(page.getByRole('heading', { name: /Descubre negocios locales/i }))
            .or(page.getByRole('link', { name: /Registrar negocio/i }));
        await expect(exploreReadyMarker.first()).toBeVisible({ timeout: 30_000 });

        const candidateMicrositeLink = page.locator('a[href^="/t/"]').first();
        const micrositeLinksCount = await page.locator('a[href^="/t/"]').count();

        if (micrositeLinksCount > 0) {
            await expect(candidateMicrositeLink).toBeVisible({ timeout: 15_000 });
            await candidateMicrositeLink.click();
        } else {
            await page.goto(SEEDED_OWNER.microsite, { waitUntil: 'domcontentloaded' });
        }

        await expect(page).toHaveURL(/\/t\/[a-z0-9-]+/);
        await expect(page.getByTestId('tenant-microsite')).toBeVisible({ timeout: 20_000 });

        const panelMarker = page.getByTestId('product-panel').or(page.getByTestId('service-panel'));
        await expect(panelMarker.first()).toBeVisible({ timeout: 20_000 });

        const addButton = page.getByRole('button', { name: /^Agregar$/ }).first();
        await expect(addButton).toBeVisible({ timeout: 15_000 });
        await addButton.click();

        const floatingCartButton = page.locator('button.fixed.bottom-6.right-6');
        await expect(floatingCartButton).toBeVisible({ timeout: 15_000 });
        await floatingCartButton.click();

        await expect(page.getByText(/Mi Carrito/i)).toBeVisible({ timeout: 10_000 });
        await page.getByRole('button', { name: /Continuar/i }).click();

        await page.getByTestId('checkout-name-input').fill('Cliente Auditor');
        await page.getByTestId('checkout-phone-input').fill('9999-1234');

        const orderResponsePromise = page.waitForResponse((response) => {
            if (response.request().method() !== 'POST') {
                return false;
            }

            return response.url().includes('/api/orders') || response.url().includes('/api/v1/orders');
        }, { timeout: 20_000 });

        await page.getByRole('button', { name: /Confirmar pedido en WhatsApp/i }).click();
        const orderResponse = await orderResponsePromise;

        expect(orderResponse.status(), 'checkout order endpoint should not fail').toBeLessThan(500);
    });

    test('registro real de cuenta y login funcional', async ({ page, browser }) => {
        const identity = uniqueRegistrationIdentity();

        const registerResponse = await page.goto('/registrar-negocio', { waitUntil: 'domcontentloaded' });
        expect(registerResponse?.status(), '/registrar-negocio must be reachable').toBeLessThan(500);

        await page.locator('#business_name').fill(identity.businessName);
        await pickShadcnSelect(page, 'register-category-select', /.+/);
        const businessTypeCard = page.getByRole('radio').first();
        await expect(businessTypeCard).toBeVisible({ timeout: 15_000 });
        await businessTypeCard.click();
        await advanceToLocationStep(page);
        await pickShadcnSelect(page, 'register-city-select', /.+/);
        await page.locator('#whatsapp').fill('+50499998888');
        await registerNextStepButton(page).click();

        await page.locator('#name').fill(identity.ownerName);
        await page.locator('#email').fill(identity.email);
        await page.locator('#password').fill(identity.password);
        await page.locator('#password_confirmation').fill(identity.password);
        await registerNextStepButton(page).click();

        await registerTermsCheckbox(page).check();
        const privacyCheckbox = page.getByTestId('register-privacy-checkbox').first();
        if (await privacyCheckbox.isVisible().catch(() => false)) {
            await privacyCheckbox.check();
        }
        const submitButton = registerSubmitButton(page);
        await expect(submitButton).toBeEnabled({ timeout: 10_000 });

        const registrationResponsePromise = page
            .waitForResponse((response) =>
                response.request().method() === 'POST'
                && response.url().includes('/registrar-negocio')
            , { timeout: 20_000 })
            .catch(() => null);

        await submitButton.click();
        const registrationResponse = await registrationResponsePromise;
        if (registrationResponse) {
            expect(registrationResponse.status(), 'register submit should not fail with server error').toBeLessThan(500);
        }

        const redirectedAfterSubmit = await page
            .waitForURL(/\/(app(?:\/|$)|verify-email|email\/verify|registro\/pago-pendiente)/, { timeout: 30_000 })
            .then(() => true)
            .catch(() => false);

        if (!redirectedAfterSubmit) {
            const appProbe = await page.request.get('/app', { failOnStatusCode: false, maxRedirects: 0 });
            expect(
                [200, 301, 302, 303, 307, 308].includes(appProbe.status()),
                'registration should end in app/verify flow or authenticated app access',
            ).toBeTruthy();
        }

        const runtimeBaseUrl = new URL(page.url()).origin;
        const isolatedContext = await browser.newContext({ baseURL: runtimeBaseUrl });
        const loginPage = await isolatedContext.newPage();

        await loginPage.goto('/login', { waitUntil: 'domcontentloaded' });
        await loginPage.locator('#email').fill(identity.email);
        await loginPage.locator('#password').fill(identity.password);
        await loginSubmitButton(loginPage).click();

        await expect(loginPage).toHaveURL(/\/(app(?:\/|$)|verify-email|email\/verify)/, { timeout: 20_000 });
        await isolatedContext.close();
    });

    test('panel admin abre y panel de negocio permite acceso con cuenta seeded', async ({ page }) => {
        const adminLoginResponse = await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
        expect(adminLoginResponse?.status(), '/admin/login must be reachable').toBeLessThan(500);
        await expect(page.getByText(/Panel de superadministraci/i)).toBeVisible({ timeout: 15_000 });

        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        await page.locator('#email').fill(SEEDED_OWNER.email);
        await page.locator('#password').fill(SEEDED_OWNER.password);
        await loginSubmitButton(page).click();

        await page.waitForURL(/\/(app(?:\/|$)|verify-email|email\/verify)/, { timeout: 20_000 });
        const appProbe = await page.request.get('/app', { failOnStatusCode: false, maxRedirects: 0 });
        expect([200, 301, 302, 303, 307, 308].includes(appProbe.status())).toBeTruthy();

        const micrositeResponse = await page.goto(SEEDED_OWNER.microsite, { waitUntil: 'domcontentloaded' });
        expect(micrositeResponse?.status(), 'seeded microsite should be reachable').toBeLessThan(500);
        await expect(page.getByTestId('tenant-microsite')).toBeVisible({ timeout: 15_000 });
    });
});
