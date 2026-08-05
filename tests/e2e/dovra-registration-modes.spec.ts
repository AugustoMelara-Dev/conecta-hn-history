import { execFile } from 'node:child_process';
import { existsSync } from 'node:fs';
import { isAbsolute, resolve } from 'node:path';
import { promisify } from 'node:util';

import { expect, test } from '@playwright/test';

const registrationPath = '/registrar-negocio';
const repositoryRoot = resolve(import.meta.dirname, '../..');
const createVerificationLinkHelper = resolve(
    repositoryRoot,
    'tests/e2e/support/create-registration-verification-link.php',
);
const execFileAsync = promisify(execFile);

const scenarios = [
    {
        key: 'appointments',
        slug: 'dovra-appointments-e2e',
        businessName: 'Dovra Appointments E2E',
        ownerName: 'Dovra Appointments Owner E2E',
        email: 'dovra.appointments.e2e@example.test',
        address: 'Avenida Appointments E2E 2026, Tegucigalpa',
        mode: 'appointments',
        businessType: 'service',
        rubroKey: 'service-core',
    },
    {
        key: 'hybrid',
        slug: 'dovra-hybrid-e2e',
        businessName: 'Dovra Hybrid E2E',
        ownerName: 'Dovra Hybrid Owner E2E',
        email: 'dovra.hybrid.e2e@example.test',
        address: 'Avenida Hybrid E2E 2026, Tegucigalpa',
        mode: 'hybrid',
        businessType: 'service',
        rubroKey: 'hybrid-core',
    },
] as const;

test.describe.configure({ mode: 'serial' });

for (const scenario of scenarios) {
    test(`registration creates the real ${scenario.key} tenant contract`, async ({ page }) => {
        test.setTimeout(120_000);

        expect(process.env.APP_KEY?.trim(), 'APP_KEY must be explicitly provided to the E2E process').toBeTruthy();

        const registrationPosts: string[] = [];
        const livewireResponses: string[] = [];
        const filamentCssResponses = {
            forms: [] as number[],
            support: [] as number[],
        };
        const serverErrors: string[] = [];
        const consoleErrors: string[] = [];
        const pageErrors: string[] = [];

        page.on('request', (request) => {
            if (request.method() === 'POST' && new URL(request.url()).pathname === registrationPath) {
                registrationPosts.push(`${request.method()} ${new URL(request.url()).pathname}`);
            }
        });
        page.on('response', (response) => {
            const responsePath = new URL(response.url()).pathname;
            if (responsePath.endsWith('/css/filament/forms/forms.css')) {
                filamentCssResponses.forms.push(response.status());
            }
            if (responsePath.endsWith('/css/filament/support/support.css')) {
                filamentCssResponses.support.push(response.status());
            }
            if (response.request().method() === 'POST' && new URL(response.url()).pathname === '/livewire/update') {
                livewireResponses.push(`${response.status()} ${response.url()}`);
            }
            if (response.status() >= 500) {
                serverErrors.push(`${response.status()} ${response.url()}`);
            }
        });
        page.on('console', (message) => {
            if (message.type() !== 'error') return;

            const locationUrl = message.location().url;
            const locationPath = locationUrl ? new URL(locationUrl).pathname : '';
            const isExpectedInertiaLocationConflict = message.text().includes('status of 409')
                && (locationPath === '' || locationPath === registrationPath);

            if (!isExpectedInertiaLocationConflict) {
                consoleErrors.push(`${message.text()}${locationUrl ? ` @ ${locationUrl}` : ''}`);
            }
        });
        page.on('pageerror', (error) => pageErrors.push(error.message));

        await page.goto(registrationPath, { waitUntil: 'networkidle' });
        await expect(page.getByRole('heading', { name: /Crea tu negocio/i })).toBeVisible();
        await expect(page.getByText('Datos incompletos')).toHaveCount(0);

        await page.getByTestId('register-business-name-input').fill(scenario.businessName);
        await expect(page.getByTestId('register-slug-input')).toHaveValue(scenario.slug);

        await page.getByTestId('register-category-select').click();
        await page.getByRole('option', { name: 'Restaurantes', exact: true }).click();
        await page.locator(`#business-type-${scenario.mode}`).click();
        await expect(page.locator(`#business-type-${scenario.mode}`)).toBeChecked();

        await page.getByTestId('register-next-step').click();
        await expect(page.getByRole('heading', { name: /Zona, contacto y cuenta/i })).toBeVisible();

        await page.getByTestId('register-country-select').click();
        await page.getByRole('option', { name: 'Honduras', exact: true }).click();
        await page.getByTestId('register-locality-select').click();
        await page.getByRole('option', { name: 'Tegucigalpa', exact: true }).click();
        await page.locator('#address').fill(scenario.address);
        await page.locator('#whatsapp').fill('+504 9999-2026');
        await page.locator('#name').fill(scenario.ownerName);
        await page.locator('#email').fill(scenario.email);
        await page.locator('#password').fill('DovraE2E!2026');
        await page.locator('#password_confirmation').fill('DovraE2E!2026');
        await page.getByTestId('register-next-step').click();

        await expect(page.getByRole('heading', { name: /Plan y activaci/i })).toBeVisible();
        await expect(page.locator('#plan-free')).toBeChecked();
        await page.getByTestId('register-terms-checkbox').check();
        await page.getByTestId('register-privacy-checkbox').check();

        const postResponsePromise = page.waitForResponse((response) =>
            response.request().method() === 'POST'
            && new URL(response.url()).pathname === registrationPath,
        );
        await page.getByTestId('register-submit').click();
        const postResponse = await postResponsePromise;

        expect(registrationPosts, registrationPosts.join('\n')).toEqual([`POST ${registrationPath}`]);
        expect(postResponse.status()).toBe(409);
        expect(postResponse.headers()['x-inertia-location']).toBe(`/app/${scenario.slug}`);
        expect(serverErrors, serverErrors.join('\n')).toEqual([]);
        expect(pageErrors, pageErrors.join('\n')).toEqual([]);
        await expect(page).toHaveURL(/\/app\/email-verification\/prompt(?:\?|$)/u);

        const phpBinary = process.env.DOVRA_PHP_BINARY;
        const databasePath = process.env.DOVRA_E2E_DATABASE_PATH;
        const configuredDatabase = process.env.DB_DATABASE;
        expect(phpBinary, 'DOVRA_PHP_BINARY is required for the local verification-link helper').toBeTruthy();
        expect(isAbsolute(phpBinary ?? '')).toBe(true);
        expect(existsSync(phpBinary ?? '')).toBe(true);
        expect(existsSync(createVerificationLinkHelper)).toBe(true);
        expect(databasePath, 'DOVRA_E2E_DATABASE_PATH is required for the local verification-link helper').toBeTruthy();
        expect(configuredDatabase, 'DB_DATABASE is required for the local verification-link helper').toBeTruthy();
        expect(resolve(databasePath ?? '')).toBe(resolve(configuredDatabase ?? ''));

        const helperResult = await execFileAsync(phpBinary as string, [createVerificationLinkHelper], {
            cwd: repositoryRoot,
            env: {
                ...process.env,
                APP_ENV: process.env.APP_ENV || 'local',
                DB_CONNECTION: 'sqlite',
                DB_DATABASE: configuredDatabase,
                DOVRA_E2E_DATABASE_PATH: databasePath,
                DOVRA_E2E_ALLOW_SYNTHETIC_EMAIL_VERIFICATION: 'true',
                DOVRA_E2E_REGISTRATION_SCENARIO: scenario.key,
            },
            timeout: 30_000,
            windowsHide: true,
            maxBuffer: 1024 * 1024,
        });
        expect(helperResult.stderr).toBe('');

        const verificationPayload = JSON.parse(helperResult.stdout.trim()) as {
            status: string;
            scenario: string;
            slug: string;
            commercial_mode: string;
            business_type: string;
            rubro_key: string;
            already_verified: boolean;
            verification_url: string;
        };
        expect(verificationPayload).toMatchObject({
            status: 'ok',
            scenario: scenario.key,
            slug: scenario.slug,
            commercial_mode: scenario.mode,
            business_type: scenario.businessType,
            rubro_key: scenario.rubroKey,
            already_verified: false,
        });
        expect(verificationPayload.verification_url).toMatch(
            /\/app\/email-verification\/verify\/[^/]+\/[^/?]+(?:\?.*)?$/u,
        );

        const verificationUrl = new URL(verificationPayload.verification_url);
        const requestOrigin = new URL(page.url());
        expect(verificationUrl.protocol).toBe(requestOrigin.protocol);
        expect(verificationUrl.hostname).toBe(requestOrigin.hostname);
        expect(verificationUrl.port).toBe(requestOrigin.port);
        for (const configuredOrigin of [process.env.PLAYWRIGHT_BASE_URL, process.env.APP_URL]) {
            if (!configuredOrigin) continue;
            const expectedOrigin = new URL(configuredOrigin);
            expect(verificationUrl.protocol).toBe(expectedOrigin.protocol);
            expect(verificationUrl.hostname).toBe(expectedOrigin.hostname);
            expect(verificationUrl.port).toBe(expectedOrigin.port);
        }

        const verificationResponse = await page.goto(verificationUrl.toString(), { waitUntil: 'networkidle' });
        expect(
            verificationResponse?.status(),
            'verification route rejected the signed link; ensure the E2E APP_KEY matches the running server',
        ).toBeLessThan(400);
        await expect(page).toHaveURL(new RegExp(`/app/${scenario.slug}(?:\\?|$)`));
        await expect(page).not.toHaveURL(/email-verification|login/u);
        await expect(page.locator('body')).toContainText(scenario.businessName);
        await expect(page.locator('body')).toContainText(scenario.ownerName);
        await expect(page.locator('body')).toContainText('Dovra AI');

        const offerMilestoneTitle = scenario.key === 'appointments'
            ? 'Primer servicio reservable'
            : 'Primer flujo comercial combinado';

        if (scenario.key === 'appointments') {
            await expect(page.locator('body')).toContainText('Servicios y citas');
            await expect(page.locator('body')).toContainText(offerMilestoneTitle);
            await expect(
                page.locator(`a[href$="/app/${scenario.slug}/services"]`).filter({ hasText: 'Catálogo de servicios' }).first(),
            ).toBeVisible();
        } else {
            await expect(page.locator('body')).toContainText('Operacion comercial mixta');
            await expect(page.locator('body')).toContainText(offerMilestoneTitle);
            await expect(
                page.locator(`a[href$="/app/${scenario.slug}/products"]`).filter({ hasText: 'Productos' }).first(),
            ).toBeVisible();
        }
        await expect(
            page.getByTestId('readiness-status-pending').filter({ hasText: offerMilestoneTitle }).first(),
        ).toBeVisible();
        await expect(
            page.getByTestId('readiness-status-ready').filter({ hasText: offerMilestoneTitle }),
        ).toHaveCount(0);
        expect(filamentCssResponses.forms, 'Filament forms CSS must load with HTTP 200').toContain(200);
        expect(filamentCssResponses.support, 'Filament support CSS must load with HTTP 200').toContain(200);
        expect(livewireResponses.length, 'the owner panel must complete at least one real Livewire update').toBeGreaterThan(0);
        expect(livewireResponses, livewireResponses.join('\n')).toEqual(
            livewireResponses.filter((entry) => /^2\d\d\s/u.test(entry)),
        );
        expect(serverErrors, serverErrors.join('\n')).toEqual([]);
        expect(pageErrors, pageErrors.join('\n')).toEqual([]);
        expect(consoleErrors, consoleErrors.join('\n')).toEqual([]);
    });
}
