import { execFile } from 'node:child_process';
import { existsSync } from 'node:fs';
import { isAbsolute, resolve } from 'node:path';
import { promisify } from 'node:util';

import { expect, test } from '@playwright/test';

const tenantSlug = 'dovra-buyer-conversion-e2e';
const tenantName = 'Dovra Buyer Conversion E2E';
const productName = 'Producto Buyer E2E';
const baseOrigin = new URL(process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000').origin;
const repositoryRoot = resolve(import.meta.dirname, '../..');
const inspectBuyerConversionHelper = resolve(
    repositoryRoot,
    'tests/e2e/support/inspect-buyer-conversion.php',
);
const execFileAsync = promisify(execFile);

test('buyer converts from the real directory to one pending pickup order', async ({ page }) => {
    test.setTimeout(120_000);

    expect(
        process.env.APP_KEY?.trim(),
        'APP_KEY must be explicitly provided to the buyer conversion E2E process',
    ).toBeTruthy();
    expect(
        process.env.DOVRA_E2E_ALLOW_SYNTHETIC_BUYER_CONVERSION,
        'synthetic buyer conversion requires an explicit local guard',
    ).toBe('true');

    const orderPosts: string[] = [];
    const orderResponses: number[] = [];
    const externalOperationalRequests: string[] = [];
    const externalStaticRequests: string[] = [];
    const serverErrors: string[] = [];
    const consoleErrors: string[] = [];
    const pageErrors: string[] = [];
    let popupCount = 0;

    page.on('request', (request) => {
        const requestUrl = new URL(request.url());
        if (requestUrl.origin !== baseOrigin) {
            const requestSummary = `${request.method()} ${request.url()}`;
            if (request.method() !== 'GET' || request.resourceType() === 'document') {
                externalOperationalRequests.push(requestSummary);
            } else {
                externalStaticRequests.push(requestSummary);
            }
        }

        if (request.method() === 'POST' && requestUrl.pathname === '/api/v1/orders') {
            orderPosts.push(`${request.method()} ${requestUrl.pathname}`);
        }
    });
    page.on('response', (response) => {
        if (response.status() >= 500) {
            serverErrors.push(`${response.status()} ${response.url()}`);
        }

        if (response.request().method() === 'POST'
            && new URL(response.url()).pathname === '/api/v1/orders') {
            orderResponses.push(response.status());
        }
    });
    page.on('popup', () => {
        popupCount += 1;
    });
    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') {
            consoleErrors.push(message.text());
        }
    });

    await page.goto('/directorio', { waitUntil: 'networkidle' });
    await expect(page.getByTestId('directory-page')).toBeVisible();

    await page.getByTestId('directory-search-control').fill(tenantName);
    await expect.poll(
        () => new URL(page.url()).searchParams.get('search'),
        { timeout: 15_000 },
    ).toBe(tenantName);

    const tenantCard = page
        .getByTestId('tenant-card')
        .filter({ hasText: tenantName })
        .first();
    await expect(tenantCard).toBeVisible();
    await tenantCard.getByTestId('primary-tenant-cta').click();
    await expect(page).toHaveURL(new RegExp(`/t/${tenantSlug}(?:\\?|$)`));

    const productCard = page
        .getByTestId('microsite-product-card')
        .filter({ hasText: productName })
        .first();
    await expect(productCard).toBeVisible();
    await productCard.getByTestId('microsite-add-to-cart').click();

    await expect(page.getByTestId('floating-cart-open')).toBeVisible();
    await page.getByTestId('floating-cart-open').click();
    await expect(page.getByTestId('floating-cart-sheet')).toBeVisible();
    await page.getByTestId('cart-continue-checkout').click();
    await expect(page.getByTestId('checkout-form')).toBeVisible();

    await page.getByTestId('checkout-name').fill('Buyer E2E');
    await page.getByTestId('checkout-phone').fill('9999-2026');
    await page.getByTestId('checkout-delivery-pickup').click();
    await expect(page.getByTestId('checkout-delivery-pickup')).toBeChecked();

    const orderResponsePromise = page.waitForResponse((response) =>
        response.request().method() === 'POST'
        && new URL(response.url()).pathname === '/api/v1/orders',
    );
    await page.getByTestId('checkout-submit').click();
    const orderResponse = await orderResponsePromise;
    const orderPayload = await orderResponse.json() as {
        success?: boolean;
        data?: {
            order_id?: number;
            order_number?: string;
            status?: string;
            whatsapp_url?: string | null;
        };
    };

    expect(orderResponse.status()).toBe(201);
    expect(orderPayload).toMatchObject({
        success: true,
        data: {
            status: 'pending',
            whatsapp_url: null,
        },
    });
    expect(typeof orderPayload.data?.order_id).toBe('number');
    expect(typeof orderPayload.data?.order_number).toBe('string');
    expect(orderPayload.data?.order_number).not.toBe('');

    // Accessible success contract: Pedido {number} creado, then pendiente.
    await expect(page.getByText(new RegExp(`Pedido ${orderPayload.data?.order_number} creado`, 'u'))).toBeVisible();
    await expect(page.getByText(/pendiente/i)).toBeVisible();
    await expect(page.getByTestId('floating-cart-open')).toHaveCount(0);
    await expect(page.getByTestId('floating-cart-sheet')).toHaveCount(0);

    const persistedCart = await page.evaluate(() => {
        const raw = window.localStorage.getItem('conecta-cart');
        return raw ? JSON.parse(raw) : null;
    }) as { state?: { items?: unknown[] } } | null;
    expect(persistedCart?.state?.items ?? []).toEqual([]);

    const phpBinary = process.env.DOVRA_PHP_BINARY;
    const databasePath = process.env.DOVRA_E2E_DATABASE_PATH;
    const configuredDatabase = process.env.DB_DATABASE;
    expect(phpBinary, 'DOVRA_PHP_BINARY is required for buyer conversion inspection').toBeTruthy();
    expect(isAbsolute(phpBinary ?? '')).toBe(true);
    expect(existsSync(phpBinary ?? '')).toBe(true);
    expect(existsSync(inspectBuyerConversionHelper)).toBe(true);
    expect(databasePath, 'DOVRA_E2E_DATABASE_PATH is required for buyer conversion inspection').toBeTruthy();
    expect(configuredDatabase, 'DB_DATABASE is required for buyer conversion inspection').toBeTruthy();
    expect(resolve(databasePath ?? '')).toBe(resolve(configuredDatabase ?? ''));

    const helperResult = await execFileAsync(phpBinary as string, [inspectBuyerConversionHelper], {
        cwd: repositoryRoot,
        env: {
            ...process.env,
            APP_ENV: process.env.APP_ENV || 'local',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: configuredDatabase,
            DOVRA_E2E_DATABASE_PATH: databasePath,
            DOVRA_E2E_ALLOW_SYNTHETIC_BUYER_CONVERSION: 'true',
        },
        timeout: 30_000,
        windowsHide: true,
        maxBuffer: 1024 * 1024,
    });
    expect(helperResult.stderr).toBe('');

    const inspection = JSON.parse(helperResult.stdout.trim()) as {
        status: string;
        order: {
            id: number;
            number: string;
            status: string;
        };
        inventory: {
            movements: number;
            delta: number;
            reason: string;
        };
        outbox: {
            business_operation_changed: number;
            event_name: string;
        };
    };
    expect(inspection).toMatchObject({
        status: 'ok',
        order: {
            id: orderPayload.data?.order_id,
            number: orderPayload.data?.order_number,
            status: 'pending',
        },
        inventory: {
            movements: 1,
            delta: -1,
            reason: 'order_consumed',
        },
        outbox: {
            business_operation_changed: 1,
            event_name: 'order.created',
        },
    });

    await expect.poll(() => orderPosts.length).toBe(1);
    expect(orderResponses).toEqual([201]);
    // Existing public fonts/images are passive assets. The conversion itself must
    // neither navigate away nor call an external operational/payment endpoint.
    expect(
        externalOperationalRequests,
        [
            ...externalOperationalRequests,
            ...externalStaticRequests.map((request) => `passive asset: ${request}`),
        ].join('\n'),
    ).toEqual([]);
    expect(popupCount).toBe(0);
    expect(serverErrors, serverErrors.join('\n')).toEqual([]);
    expect(pageErrors, pageErrors.join('\n')).toEqual([]);
    expect(consoleErrors, consoleErrors.join('\n')).toEqual([]);
});
