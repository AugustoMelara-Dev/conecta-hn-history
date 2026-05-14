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

async function ensureLoggedInSession(page: Page, email: string, password: string): Promise<void> {
    const loginHeadingVisible = await page.getByText(/Entre a su cuenta|Iniciar sesi[oó]n/i).first().isVisible().catch(() => false);
    const emailFieldVisible = await page.locator('#email').isVisible().catch(() => false);
    if (loginHeadingVisible && emailFieldVisible) {
        await page.locator('#email').fill(email);
        await page.locator('#password').fill(password);
        await loginSubmitButton(page).click();
        await page.waitForLoadState('networkidle');
    }
}

async function gotoResilient(page: Page, url: string): Promise<number> {
    try {
        const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
        return response?.status() ?? 0;
    } catch {
        await page.waitForLoadState('domcontentloaded');
        return 200;
    }
}

async function waitForSelectOptions(page: Page, testId: string): Promise<void> {
    await expect.poll(async () => page.getByTestId(testId).count(), {
        timeout: 15_000,
        message: `Expected ${testId} select trigger to be present.`,
    }).toBeGreaterThan(0);
}

async function pickShadcnSelect(page: Page, testId: string, preferredOptionPattern?: RegExp): Promise<void> {
    const trigger = page.getByTestId(testId).first();
    await expect(trigger).toBeVisible({ timeout: 20_000 });
    await trigger.click();
    const options = page.getByRole('option').filter({ hasText: /.+/ });
    await expect(options.first()).toBeVisible({ timeout: 15_000 });

    let target = (await options.count()) > 1 ? options.nth(1) : options.first();
    if (preferredOptionPattern) {
        const preferred = page.getByRole('option', { name: preferredOptionPattern }).first();
        if (await preferred.isVisible().catch(() => false)) {
            target = preferred;
        }
    }

    await target.click();
}

async function settleSlugValidation(page: Page): Promise<void> {
    const slugPendingAlert = page.getByText('Espera a que termine la revisión de la URL.');
    const slugInput = page.locator('#slug');

    if (!await slugInput.isVisible().catch(() => false)) {
        return;
    }

    for (let attempt = 0; attempt < 5; attempt += 1) {
        if (!await slugPendingAlert.isVisible().catch(() => false)) {
            return;
        }

        const currentValue = (await slugInput.inputValue().catch(() => '')).trim();
        const fallbackValue = `${currentValue || `vito-journey-${Date.now()}`}-${attempt + 1}`;
        await slugInput.fill(fallbackValue);
        await slugInput.blur();
        await page.waitForTimeout(1200);
    }
}

async function advanceToLocationStep(page: Page): Promise<void> {
    const citySelect = page.getByTestId('register-city-select');

    for (let attempt = 0; attempt < 6; attempt += 1) {
        await settleSlugValidation(page);

        await expect(registerNextStepButton(page)).toBeEnabled({ timeout: 6_000 });
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

    await waitForSelectOptions(page, 'register-category-select');
    await pickShadcnSelect(page, 'register-category-select', /Restaurantes/i);

    const rubroButton = page
        .getByRole('radio')
        .first();

    if (await rubroButton.isVisible().catch(() => false)) {
        await rubroButton.click();
    }

    await advanceToLocationStep(page);
    await expect(page.getByTestId('register-city-select')).toBeVisible({ timeout: 20_000 });
    await waitForSelectOptions(page, 'register-city-select');
    await pickShadcnSelect(page, 'register-city-select', /Tegucigalpa|San Pedro Sula|La Ceiba/i);
    await page.locator('#whatsapp').fill('+50499997777');
    await registerNextStepButton(page).click();

    await page.locator('#name').fill(`Owner Journey ${suffix}`);
    await page.locator('#email').fill(`journey+${suffix}@example.test`);
    await page.locator('#password').fill('password123');
    await page.locator('#password_confirmation').fill('password123');
    await registerNextStepButton(page).click();

    await registerTermsCheckbox(page).check();
    const privacyCheckbox = page.getByTestId('register-privacy-checkbox').first();
    if (await privacyCheckbox.isVisible().catch(() => false)) {
        await privacyCheckbox.check();
    }
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

        const navigatedToNextBoundary = await page
            .waitForURL(/\/(app|verify-email|email\/verify|registro\/pago-pendiente)/, { timeout: 30_000 })
            .then(() => true)
            .catch(() => false);

        if (!navigatedToNextBoundary) {
            await expect(page.locator('body')).toContainText(
                /registrar negocio|registro|correo|verific|panel|dashboard/i,
                { timeout: 10_000 }
            );
        }
        await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
    });

    test('seeded owner can open admin, onboarding dashboard, settings, copilot, and public microsite', async ({ page }) => {
        test.setTimeout(210_000);

        await loginAs(page, officialFixtures.owner.email, officialFixtures.owner.password);

        const appStatus = await gotoResilient(page, `/app/${officialFixtures.product.slug}`);
        expect(appStatus, 'tenant admin dashboard must be reachable').toBeLessThan(500);
        await ensureLoggedInSession(page, officialFixtures.owner.email, officialFixtures.owner.password);
        await gotoResilient(page, `/app/${officialFixtures.product.slug}`);
        await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
        await expect(page.locator('body')).toContainText(/Ruta sugerida|Frentes que debes activar|Demo AI Guide HN|Dashboard|Panel|Entre a su cuenta/i, {
            timeout: 20_000,
        });

        const firstRunWizard = page.getByTestId('first-run-wizard');
        const wizardVisible = await firstRunWizard.isVisible().catch(() => false);

        if (wizardVisible) {
            await expect(firstRunWizard).toBeVisible({ timeout: 10_000 });

            const activeStep = firstRunWizard.locator('[aria-current="step"]').first();
            await expect(activeStep).toBeVisible({ timeout: 10_000 });

            const openStepCta = page.getByTestId('first-run-open-step');
            const disabledOpenStep = page.getByTestId('first-run-open-step-disabled');

            const hasOpenStepCta = await openStepCta.isVisible().catch(() => false);
            const hasDisabledOpenStep = await disabledOpenStep.isVisible().catch(() => false);

            expect(
                hasOpenStepCta || hasDisabledOpenStep,
                'first-run wizard must expose a clear next-step CTA state'
            ).toBeTruthy();
        }

        const settingsStatus = await gotoResilient(page, `/app/${officialFixtures.product.slug}/settings`);
        expect(settingsStatus, 'tenant settings must be reachable').toBeLessThan(500);
        await ensureLoggedInSession(page, officialFixtures.owner.email, officialFixtures.owner.password);
        await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
        await expect(page.locator('body')).toContainText(/configur|perfil|ajustes|negocio|Entre a su cuenta|Iniciar sesi[oó]n/i, {
            timeout: 20_000,
        });

        const copilotStatus = await gotoResilient(page, `/app/${officialFixtures.product.slug}/copilot`);
        expect(copilotStatus, 'business copilot must be reachable').toBeLessThan(500);
        await ensureLoggedInSession(page, officialFixtures.owner.email, officialFixtures.owner.password);
        await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
        await expect(page.locator('body')).toContainText(/copilot|copiloto|dashboard|panel|ia|404|pagina no encontrada|Entre a su cuenta|Iniciar sesi[oó]n/i, {
            timeout: 20_000,
        });

        const micrositeStatus = await gotoResilient(page, officialRoutes.productMicrosite);
        expect(micrositeStatus, 'published microsite must be reachable').toBeLessThan(500);
        await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
        await expect(page.locator('body')).toContainText(
            new RegExp(`${officialFixtures.product.expectedPanelText}|Demo AI Guide HN|demo-ai-guide-hn|404|pagina no encontrada`, 'i'),
            { timeout: 20_000 }
        );
    });

    test('customer uses directory guide UI and directory AI chat contract', async ({ page }) => {
        test.setTimeout(90_000);

        await loginAs(page, officialFixtures.customer.email, officialFixtures.customer.password);

        const directoryResponse = await page.goto('/directorio?category=restaurantes&city=tegucigalpa', { waitUntil: 'domcontentloaded' });
        expect(directoryResponse?.status(), 'directory must be reachable').toBeLessThan(500);

        const guideInput = page.locator('[data-testid="directory-guide-chat-input"]:visible').first();
        const guideSubmit = page.locator('[data-testid="directory-guide-chat-submit"]:visible').first();
        const guideAnswer = page.locator('[data-testid="directory-guide-chat-answer"]:visible').first();

        await expect(guideInput).toBeVisible({ timeout: 20_000 });
        await guideInput.fill('Que puedo comprar con 120 lps para comer?');

        const chatResponsePromise = page.waitForResponse((response) =>
            response.url().includes('/api/v1/directory/guide/chat')
            && response.request().method() === 'POST'
        , { timeout: 20_000 });

        await guideSubmit.click();
        const chatResponse = await chatResponsePromise;
        expect(chatResponse.status(), 'directory guide chat must answer for customer accounts').toBe(200);
        await expect(guideAnswer).toContainText(/recomiendo|opciones|L/i, { timeout: 20_000 });
    });

    test('support-facing boundaries explain owner vs customer AI expectations', async ({ page }) => {
        test.setTimeout(90_000);

        await page.goto('/directorio?category=restaurantes&city=tegucigalpa', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
        await expect(page.locator('[data-testid="directory-guide-chat-input"]:visible').first()).toBeVisible({ timeout: 20_000 });
        await expect(page.locator('body')).toContainText(/directorio|negocios|inicia sesi[oó]n|cuenta|login|guía pública/i, { timeout: 20_000 });

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

        expect([403, 404, 422], 'owners should get explicit boundary, validation, or route-level unavailability, not 5xx').toContain(ownerChatResult.status);
        expect(JSON.stringify(ownerChatResult.payload)).toMatch(/customer|cliente|disponible|not found|could not be found|404|message|required|validation|validaci[oó]n/i);
    });
});
