import { expect, test, type Page } from '@playwright/test';
import { officialFixtures, officialRoutes } from './support/fixtures';
import {
    loginSubmitButton,
    registerNextStepButton,
    registerSubmitButton,
    registerTermsCheckbox,
} from './support/auth-locators';

async function loginAs(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await loginSubmitButton(page).click();
    await page.waitForLoadState('networkidle');
}

async function waitForSelectOptions(page: Page, selector: string): Promise<void> {
    await expect.poll(async () => page.locator(selector).locator('option').count(), {
        timeout: 15_000,
        message: `Expected ${selector} to have selectable options.`,
    }).toBeGreaterThan(1);
}

async function advanceToLocationStep(page: Page): Promise<void> {
    const citySelect = page.locator('#city_id');

    for (let attempt = 0; attempt < 6; attempt += 1) {
        await registerNextStepButton(page).click();

        if (await citySelect.isVisible({ timeout: 3_000 }).catch(() => false)) {
            return;
        }

        await page.waitForTimeout(900);
    }

    throw new Error('Registration wizard did not advance to the location step.');
}

async function fillMinimalBusinessRegistration(page: Page): Promise<void> {
    const suffix = Date.now();

    await page.goto('/registrar-negocio', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#business_name')).toBeVisible({ timeout: 20_000 });

    await page.locator('#business_name').fill(`Vito Journey ${suffix}`);

    const slugInput = page.locator('#slug');
    if (await slugInput.isVisible().catch(() => false)) {
        await slugInput.fill(`vito-journey-${suffix}`);
        await slugInput.blur();
        await page.waitForTimeout(800);
    }

    await waitForSelectOptions(page, '#category_id');
    await page.locator('#category_id').selectOption({ index: 1 });

    const rubroButton = page
        .getByRole('button', { name: /Tienda y cat[aá]logo|Restaurante y comida|Servicios y citas|Mixto/i })
        .first();

    if (await rubroButton.isVisible().catch(() => false)) {
        await rubroButton.click();
    }

    await advanceToLocationStep(page);
    await expect(page.locator('#city_id')).toBeVisible({ timeout: 20_000 });

    await waitForSelectOptions(page, '#city_id');
    await page.locator('#city_id').selectOption({ index: 1 });
    await page.locator('#whatsapp').fill('+50499997777');
    await registerNextStepButton(page).click();

    await page.locator('#name').fill(`Owner Journey ${suffix}`);
    await page.locator('#email').fill(`journey+${suffix}@example.test`);
    await page.locator('#password').fill('password123');
    await page.locator('#password_confirmation').fill('password123');
    await registerNextStepButton(page).click();

    await registerTermsCheckbox(page).check();
    await expect(registerSubmitButton(page)).toBeEnabled({ timeout: 10_000 });
}

test.describe('Vito Business OS critical journey', () => {
    test('visitor registration reaches the owner onboarding boundary without server errors', async ({ page }) => {
        test.setTimeout(120_000);

        await fillMinimalBusinessRegistration(page);

        const submitResponsePromise = page
            .waitForResponse((response) =>
                response.request().method() === 'POST'
                && response.url().includes('/registrar-negocio')
            , { timeout: 20_000 })
            .catch(() => null);

        await registerSubmitButton(page).click();
        const submitResponse = await submitResponsePromise;

        if (submitResponse) {
            expect(submitResponse.status(), 'business registration submit must not 5xx').toBeLessThan(500);
        }

        await page.waitForURL(/\/(app|verify-email|email\/verify|registro\/pago-pendiente)/, { timeout: 30_000 });
        await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
    });

    test('seeded owner can open admin, onboarding dashboard, settings, copilot, and public microsite', async ({ page }) => {
        test.setTimeout(90_000);

        await loginAs(page, officialFixtures.owner.email, officialFixtures.owner.password);

        const appResponse = await page.goto(`/app/${officialFixtures.product.slug}`, { waitUntil: 'domcontentloaded' });
        expect(appResponse?.status(), 'tenant admin dashboard must be reachable').toBeLessThan(500);
        await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
        await expect(page.locator('body')).toContainText(/Ruta sugerida|Frentes que debes activar|Demo AI Guide HN|Dashboard|Panel/i, {
            timeout: 20_000,
        });

        const settingsResponse = await page.goto(`/app/${officialFixtures.product.slug}/settings`, { waitUntil: 'domcontentloaded' });
        expect(settingsResponse?.status(), 'tenant settings must be reachable').toBeLessThan(500);
        await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
        await expect(page.locator('body')).toContainText(/configur|perfil|ajustes|negocio/i, {
            timeout: 20_000,
        });

        const copilotResponse = await page.goto(`/app/${officialFixtures.product.slug}/copilot`, { waitUntil: 'domcontentloaded' });
        expect(copilotResponse?.status(), 'business copilot must be reachable').toBeLessThan(500);
        await expect(page.getByTestId('business-copilot-diagnostic')).toBeVisible({ timeout: 20_000 });
        await expect(page.locator('body')).toContainText(/copiloto|autodiagnostico|ia privada/i, {
            timeout: 20_000,
        });

        const micrositeResponse = await page.goto(officialRoutes.productMicrosite, { waitUntil: 'domcontentloaded' });
        expect(micrositeResponse?.status(), 'published microsite must be reachable').toBeLessThan(500);
        await expect(page.getByTestId('tenant-microsite')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByTestId('product-panel')).toContainText(officialFixtures.product.expectedPanelText);
    });

    test('customer uses directory guide UI and directory AI chat contract', async ({ page }) => {
        test.setTimeout(90_000);

        await loginAs(page, officialFixtures.customer.email, officialFixtures.customer.password);

        const directoryResponse = await page.goto('/directorio?category=restaurantes&city=tegucigalpa', { waitUntil: 'domcontentloaded' });
        expect(directoryResponse?.status(), 'directory must be reachable').toBeLessThan(500);

        await expect(page.getByTestId('directory-guide-budget-input')).toBeVisible({ timeout: 20_000 });
        await page.getByTestId('directory-guide-budget-input').fill('120');

        const guideResponsePromise = page.waitForResponse((response) =>
            response.url().includes('/api/v1/directory/guide')
            && response.request().method() === 'GET'
        , { timeout: 20_000 });

        await page.getByTestId('directory-guide-submit').click();
        const guideResponse = await guideResponsePromise;
        expect(guideResponse.status(), 'directory guide UI request must not 5xx').toBeLessThan(500);

        if (guideResponse.status() === 200) {
            await expect(page.getByTestId('directory-guide-result').first()).toBeVisible({ timeout: 20_000 });
        }

        await page.getByTestId('directory-guide-chat-input').fill('Que puedo comprar con 120 lps para comer?');

        const chatResponsePromise = page.waitForResponse((response) =>
            response.url().includes('/api/v1/directory/guide/chat')
            && response.request().method() === 'POST'
        , { timeout: 20_000 });

        await page.getByTestId('directory-guide-chat-submit').click();
        const chatResponse = await chatResponsePromise;
        expect(chatResponse.status(), 'directory guide chat must answer for customer accounts').toBe(200);
        await expect(page.getByTestId('directory-guide-chat-answer')).toContainText(/recomiendo|opciones|L/i, { timeout: 20_000 });
    });

    test('support-facing boundaries explain owner vs customer AI expectations', async ({ page }) => {
        test.setTimeout(90_000);

        await page.goto('/directorio?category=restaurantes&city=tegucigalpa', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
        await expect(page.getByTestId('directory-guide-budget-input')).toHaveCount(0);
        await expect(page.locator('body')).toContainText(/directorio|negocios|inicia sesi[oÃ³]n|cuenta|login/i, { timeout: 20_000 });

        await loginAs(page, officialFixtures.owner.email, officialFixtures.owner.password);
        await page.goto('/directorio?category=restaurantes&city=tegucigalpa', { waitUntil: 'domcontentloaded' });

        const ownerChatResult = await page.evaluate(async () => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            const response = await fetch('/api/v1/directory/guide/chat', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'x-trace-id': 'e2e-owner-directory-chat-boundary',
                },
                body: JSON.stringify({
                    query: 'Que puedo comprar con 120 lps?',
                    budget_amount: 120,
                    currency: 'HNL',
                    city: 'tegucigalpa',
                    category: 'restaurantes',
                    limit: 5,
                }),
            });

            return {
                status: response.status,
                payload: await response.json().catch(() => ({})),
            };
        });

        expect(ownerChatResult.status, 'owners should get an explicit customer-only boundary, not a silent failure').toBe(403);
        expect(JSON.stringify(ownerChatResult.payload)).toMatch(/customer|cliente|Disponible solo/i);
    });
});
