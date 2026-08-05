import { execFile } from 'node:child_process';
import { existsSync } from 'node:fs';
import { promisify } from 'node:util';
import { isAbsolute, resolve } from 'node:path';

import { expect, test } from '@playwright/test';

const registrationPath = '/registrar-negocio';
const canonicalPanelPath = '/app/dovra-registration-e2e';
const repositoryRoot = resolve(import.meta.dirname, '../..');
const createVerificationLinkHelper = resolve(repositoryRoot, 'tests/e2e/support/create-registration-verification-link.php');
const execFileAsync = promisify(execFile);

test('registration creates one real tenant and lands in the canonical owner panel', async ({ page }) => {
    test.setTimeout(120_000);

    // Keep the helper from silently signing with `.env` when the server was
    // started with an explicit APP_KEY. The value is never logged or echoed.
    expect(process.env.APP_KEY?.trim(), 'APP_KEY must be explicitly provided to the E2E process').toBeTruthy();

    const postRequests: string[] = [];
    const postResponses: import('@playwright/test').Response[] = [];
    const livewireResponses: string[] = [];
    const serverErrors: string[] = [];
    const consoleErrors: string[] = [];
    const pageErrors: string[] = [];

    page.on('request', (request) => {
        if (request.method() === 'POST') {
            postRequests.push(`${request.method()} ${new URL(request.url()).pathname}`);
        }
    });
    page.on('response', (response) => {
        if (response.request().method() === 'POST' && new URL(response.url()).pathname === registrationPath) {
            postResponses.push(response);
        }
        if (response.request().method() === 'POST' && new URL(response.url()).pathname === '/livewire/update') {
            livewireResponses.push(`${response.status()} ${response.url()}`);
        }
        if (response.status() >= 500) {
            serverErrors.push(`${response.status()} ${response.url()}`);
        }
    });
    page.on('console', (message) => {
        if (message.type() === 'error') {
            const locationUrl = message.location().url;
            const locationPath = locationUrl ? new URL(locationUrl).pathname : '';
            const isExpectedInertiaLocationConflict = message.text().includes('status of 409')
                && (locationPath === '' || locationPath === registrationPath);

            if (!isExpectedInertiaLocationConflict) {
                consoleErrors.push(`${message.text()}${locationUrl ? ` @ ${locationUrl}` : ''}`);
            }
        }
    });
    page.on('pageerror', (error) => pageErrors.push(error.message));

    await page.goto(registrationPath, { waitUntil: 'networkidle' });
    await expect(page.getByRole('heading', { name: /Crea tu negocio/i })).toBeVisible();
    await expect(page.getByText('Datos incompletos')).toHaveCount(0);

    await page.getByTestId('register-business-name-input').fill('Dovra Registration E2E');
    await expect(page.getByTestId('register-slug-input')).toHaveValue('dovra-registration-e2e');

    await page.getByTestId('register-category-select').click();
    await page.getByRole('option', { name: 'Restaurantes', exact: true }).click();
    await expect(page.locator('#business-type-products')).toBeChecked();
    await page.getByTestId('register-next-step').click();

    await expect(page.getByRole('heading', { name: /Zona, contacto y cuenta/i })).toBeVisible();
    await page.getByTestId('register-country-select').click();
    await page.getByRole('option', { name: 'Honduras', exact: true }).click();
    await page.getByTestId('register-locality-select').click();
    await page.getByRole('option', { name: 'Tegucigalpa', exact: true }).click();
    await expect(page.getByTestId('register-locale-select')).toContainText('Español');
    await expect(page.getByTestId('register-currency-select')).toContainText('HNL');
    await expect(page.getByTestId('register-timezone-select')).toContainText('Honduras');
    await page.locator('#address').fill('Avenida E2E 2026, Tegucigalpa');
    await page.locator('#whatsapp').fill('+504 9999-2026');
    await page.locator('#name').fill('Dovra Owner E2E');
    await page.locator('#email').fill('dovra.registration.e2e@example.test');
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

    expect(postRequests, postRequests.join('\n')).toEqual([`POST ${registrationPath}`]);
    expect(postResponses).toHaveLength(1);
    expect(postResponse.status()).toBe(409);
    expect(postResponse.headers()['x-inertia-location']).toBe(canonicalPanelPath);
    expect(serverErrors, serverErrors.join('\n')).toEqual([]);
    expect(pageErrors, pageErrors.join('\n')).toEqual([]);

    await expect(page).toHaveURL(/\/app\/email-verification\/prompt(?:\?|$)/u);
    await expect(page.getByRole('heading', { name: /Verifique su dirección de correo electrónico/i })).toBeVisible();

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
            DOVRA_E2E_REGISTRATION_SCENARIO: 'products',
        },
        timeout: 30_000,
        windowsHide: true,
        maxBuffer: 1024 * 1024,
    });
    expect(helperResult.stderr).toBe('');
    const verificationPayload = JSON.parse(helperResult.stdout.trim()) as {
        status: string;
        slug: string;
        already_verified: boolean;
        verification_url: string;
    };
    expect(verificationPayload).toMatchObject({
        status: 'ok',
        slug: 'dovra-registration-e2e',
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
    await expect(page).toHaveURL(new RegExp(`${canonicalPanelPath.replaceAll('/', '\\/')}(?:\\?|$)`));
    await expect(page).not.toHaveURL(/email-verification|login/u);
    await expect(page.getByText(/Verifique su dirección de correo electrónico/i)).toHaveCount(0);
    await expect(page.locator('body')).toContainText('Dovra Registration E2E');
    await expect(page.locator('body')).toContainText('Dovra Owner E2E');
    await expect(page.locator('body')).toContainText('Dovra AI');
    expect(livewireResponses.length, 'the owner panel must complete at least one real Livewire update').toBeGreaterThan(0);
    expect(livewireResponses, livewireResponses.join('\n')).toEqual(
        livewireResponses.filter((entry) => /^2\d\d\s/u.test(entry)),
    );
    expect(serverErrors, serverErrors.join('\n')).toEqual([]);
    expect(pageErrors, pageErrors.join('\n')).toEqual([]);
    expect(consoleErrors, consoleErrors.join('\n')).toEqual([]);
});
