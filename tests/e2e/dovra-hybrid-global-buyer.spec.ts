import { execFile } from 'node:child_process';
import { existsSync } from 'node:fs';
import { isAbsolute, resolve } from 'node:path';
import { promisify } from 'node:util';

import { expect, test } from '@playwright/test';

const tenantSlug = 'dovra-hybrid-global-buyer-e2e';
const tenantMicrositePath = '/t/dovra-hybrid-global-buyer-e2e';
const tenantName = 'Dovra Hybrid Global Buyer E2E';
const productName = 'Canada Hybrid Product E2E';
const serviceName = 'Canada Hybrid Service E2E';
const providerName = 'Canada Hybrid Provider E2E';
const marketTimezone = 'America/Toronto';
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
const baseOrigin = new URL(baseUrl).origin;
const tenantOriginUrl = new URL(baseUrl);
tenantOriginUrl.hostname = `${tenantSlug}.${process.env.APP_DOMAIN || 'localhost'}`;
const tenantOrigin = tenantOriginUrl.origin;
const repositoryRoot = resolve(import.meta.dirname, '../..');
const inspectHelper = resolve(repositoryRoot, 'tests/e2e/support/inspect-hybrid-global-buyer.php');
const phpBinary = process.env.DOVRA_PHP_BINARY;
const execFileAsync = promisify(execFile);
const passiveExternalAssetRules = new Map([
    ['images.unsplash.com', new Set(['image'])],
    ['ui-avatars.com', new Set(['image'])],
    ['via.placeholder.com', new Set(['image'])],
    ['fonts.bunny.net', new Set(['stylesheet', 'font'])],
]);
const forbiddenOperationalPath = /(?:^|\/)(?:api\/v1\/(?:orders?|payments?|billing|checkout|providers?)|(?:billing|checkout|payments?|orders?)(?:\/|$))/iu;

function nextTorontoDate(): string {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: marketTimezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const today = Object.fromEntries(
        parts
            .filter(({ type }) => ['year', 'month', 'day'].includes(type))
            .map(({ type, value }) => [type, Number(value)]),
    ) as { year: number; month: number; day: number };
    const tomorrow = new Date(Date.UTC(today.year, today.month - 1, today.day + 1, 18));
    const tomorrowParts = new Intl.DateTimeFormat('en-US', {
        timeZone: marketTimezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(tomorrow);
    const localTomorrow = Object.fromEntries(
        tomorrowParts
            .filter(({ type }) => ['year', 'month', 'day'].includes(type))
            .map(({ type, value }) => [type, Number(value)]),
    ) as { year: number; month: number; day: number };

    return [
        localTomorrow.year,
        String(localTomorrow.month).padStart(2, '0'),
        String(localTomorrow.day).padStart(2, '0'),
    ].join('-');
}

function isAllowedPassiveExternalAsset(request: import('@playwright/test').Request, requestUrl: URL): boolean {
    const allowedResourceTypes = passiveExternalAssetRules.get(requestUrl.hostname.toLowerCase());

    return request.method() === 'GET'
        && allowedResourceTypes?.has(request.resourceType()) === true;
}

type Diagnostics = {
    externalOperationalRequests: string[];
    sameOriginOperationalRequests: string[];
    serverErrors: string[];
    consoleErrors: string[];
    pageErrors: string[];
    popups: number;
    orderPosts: string[];
    orderResponses: number[];
    bookingPosts: string[];
    bookingResponses: number[];
};

function attachDiagnostics(page: import('@playwright/test').Page, diagnostics: Diagnostics, allowOrder: boolean): void {
    const allowedOrigins = new Set([baseOrigin, tenantOrigin]);
    page.on('request', (request) => {
        const requestUrl = new URL(request.url());
        if (!allowedOrigins.has(requestUrl.origin)
            && !isAllowedPassiveExternalAsset(request, requestUrl)) {
            diagnostics.externalOperationalRequests.push(`${request.method()} ${request.url()}`);
        }
        if (allowedOrigins.has(requestUrl.origin) && forbiddenOperationalPath.test(requestUrl.pathname)
            && !(allowOrder && requestUrl.pathname === '/api/v1/orders')) {
            diagnostics.sameOriginOperationalRequests.push(`${request.method()} ${request.url()}`);
        }
        if (request.method() === 'POST' && requestUrl.pathname === '/api/v1/orders') {
            diagnostics.orderPosts.push(`${request.method()} ${requestUrl.origin}${requestUrl.pathname}`);
        }
        if (request.method() === 'POST' && requestUrl.pathname === '/book') {
            diagnostics.bookingPosts.push(`${request.method()} ${requestUrl.origin}${requestUrl.pathname}`);
        }
    });
    page.on('response', (response) => {
        if (response.status() >= 500) {
            diagnostics.serverErrors.push(`${response.status()} ${response.url()}`);
        }
        const request = response.request();
        const responseUrl = new URL(response.url());
        if (request.method() === 'POST' && responseUrl.pathname === '/api/v1/orders') {
            diagnostics.orderResponses.push(response.status());
        }
        if (request.method() === 'POST' && responseUrl.pathname === '/book') {
            diagnostics.bookingResponses.push(response.status());
        }
    });
    page.on('popup', () => { diagnostics.popups += 1; });
    page.on('pageerror', (error) => diagnostics.pageErrors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') {
            diagnostics.consoleErrors.push(message.text());
        }
    });
}

function blankDiagnostics(): Diagnostics {
    return {
        externalOperationalRequests: [],
        sameOriginOperationalRequests: [],
        serverErrors: [],
        consoleErrors: [],
        pageErrors: [],
        popups: 0,
        orderPosts: [],
        orderResponses: [],
        bookingPosts: [],
        bookingResponses: [],
    };
}

async function inspect(stage: 'before-order' | 'after-order' | 'after-booking'): Promise<any> {
    expect(phpBinary, 'DOVRA_PHP_BINARY is required for hybrid inspector').toBeTruthy();
    expect(isAbsolute(phpBinary || '')).toBe(true);
    expect(existsSync(phpBinary || '')).toBe(true);
    expect(existsSync(inspectHelper)).toBe(true);
    const databasePath = process.env.DOVRA_E2E_DATABASE_PATH;
    const configuredDatabase = process.env.DB_DATABASE;
    expect(databasePath, 'DOVRA_E2E_DATABASE_PATH is required for hybrid inspector').toBeTruthy();
    expect(configuredDatabase, 'DB_DATABASE is required for hybrid inspector').toBeTruthy();
    expect(resolve(databasePath || '')).toBe(resolve(configuredDatabase || ''));

    const result = await execFileAsync(phpBinary as string, [inspectHelper, `--stage=${stage}`], {
        cwd: repositoryRoot,
        env: {
            ...process.env,
            APP_ENV: process.env.APP_ENV || 'testing',
            APP_KEY: process.env.APP_KEY,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: configuredDatabase,
            DOVRA_E2E_DATABASE_PATH: databasePath,
            DOVRA_E2E_ALLOW_SYNTHETIC_HYBRID_GLOBAL_BUYER: 'true',
        },
        timeout: 30_000,
        windowsHide: true,
        maxBuffer: 2 * 1024 * 1024,
    });
    expect(result.stderr).toBe('');
    const inspection = JSON.parse(result.stdout.trim());
    expect(inspection.status).toBe('ok');
    expect(inspection.stage).toBe(stage);

    return inspection;
}

function assertCleanDiagnostics(diagnostics: Diagnostics, label: string): void {
    expect(diagnostics.externalOperationalRequests, `${label} external requests`).toEqual([]);
    expect(diagnostics.sameOriginOperationalRequests, `${label} forbidden same-origin requests`).toEqual([]);
    expect(diagnostics.popups, `${label} popup count`).toBe(0);
    expect(diagnostics.serverErrors, `${label} server errors`).toEqual([]);
    expect(diagnostics.pageErrors, `${label} page errors`).toEqual([]);
    expect(diagnostics.consoleErrors, `${label} console errors`).toEqual([]);
}

test('hybrid global buyer converts through two isolated browser contexts with replay-safe HTTP branches', async ({ browser }) => {
    test.setTimeout(180_000);

    expect(process.env.APP_KEY?.trim(), 'APP_KEY must be explicitly provided').toBeTruthy();
    expect(
        process.env.DOVRA_E2E_ALLOW_SYNTHETIC_HYBRID_GLOBAL_BUYER,
        'synthetic hybrid global buyer requires an explicit local guard',
    ).toBe('true');

    const orderContext = await browser.newContext();
    const bookingContext = await browser.newContext();
    const orderPage = await orderContext.newPage();
    const bookingPage = await bookingContext.newPage();
    const orderDiagnostics = blankDiagnostics();
    const bookingDiagnostics = blankDiagnostics();
    attachDiagnostics(orderPage, orderDiagnostics, true);
    attachDiagnostics(bookingPage, bookingDiagnostics, false);

    try {
        const beforeOrder = await inspect('before-order');
        const availabilityBefore = beforeOrder.availability;

        let orderPayload: Record<string, unknown> | undefined;
        let orderIdempotencyKey = '';
        orderPage.on('request', (request) => {
            if (request.method() === 'POST' && new URL(request.url()).pathname === '/api/v1/orders') {
                orderPayload = request.postDataJSON() as Record<string, unknown>;
                orderIdempotencyKey = request.headers()['idempotency-key'] || '';
            }
        });

        await orderPage.goto('/directorio', { waitUntil: 'networkidle' });
        await expect(orderPage.getByTestId('directory-page')).toBeVisible();
        await orderPage.getByTestId('directory-search-control').fill(tenantName);
        await expect.poll(
            () => new URL(orderPage.url()).searchParams.get('search'),
            { timeout: 15_000 },
        ).toBe(tenantName);
        const orderTenantCard = orderPage.getByTestId('tenant-card').filter({ hasText: tenantName }).first();
        await expect(orderTenantCard).toBeVisible();
        await orderTenantCard.getByTestId('primary-tenant-cta').click();
        await expect(orderPage).toHaveURL(new RegExp(`${tenantMicrositePath}(?:\\?|$)`));

        const productCard = orderPage.getByTestId('microsite-product-card').filter({ hasText: productName }).first();
        await expect(productCard).toBeVisible();
        await productCard.getByTestId('microsite-add-to-cart').click();
        await orderPage.getByTestId('floating-cart-open').click();
        await expect(orderPage.getByTestId('floating-cart-sheet')).toBeVisible();
        await orderPage.getByTestId('cart-continue-checkout').click();
        await expect(orderPage.getByTestId('checkout-form')).toBeVisible();
        await orderPage.getByTestId('checkout-name').fill('Canada Buyer E2E');
        await orderPage.getByTestId('checkout-phone').fill('+1 416 555 0100');
        await orderPage.getByTestId('checkout-delivery-pickup').click();
        await expect(orderPage.getByTestId('checkout-delivery-pickup')).toBeChecked();

        const orderResponsePromise = orderPage.waitForResponse((response) =>
            response.request().method() === 'POST'
            && new URL(response.url()).pathname === '/api/v1/orders',
        );
        await orderPage.getByTestId('checkout-submit').click();
        const orderResponse = await orderResponsePromise;
        const orderJson = await orderResponse.json() as { success?: boolean; data?: Record<string, any> };
        expect(orderResponse.status()).toBe(201);
        expect(orderJson).toMatchObject({ success: true, data: { status: 'pending', whatsapp_url: null } });
        expect(typeof orderJson.data?.order_id).toBe('number');
        expect(typeof orderJson.data?.order_number).toBe('string');
        expect(orderIdempotencyKey).not.toBe('');
        expect(orderPayload).toBeDefined();
        expect(orderPayload?.payment_method).toBe('pending');
        expect(orderPayload?.delivery_type).toBe('pickup');
        await expect(orderPage.getByText(new RegExp(`Pedido ${orderJson.data?.order_number} creado`, 'u'))).toBeVisible();
        await expect(orderPage.getByText(/pendiente/i)).toBeVisible();

        const orderReplay = await orderContext.request.post(`${baseOrigin}/api/v1/orders`, {
            data: orderPayload,
            headers: { 'Idempotency-Key': orderIdempotencyKey },
        });
        expect(orderReplay.status()).toBe(200);
        const orderReplayJson = await orderReplay.json() as { data?: Record<string, any> };
        expect(orderReplayJson.data?.order_id).toBe(orderJson.data?.order_id);
        expect(orderReplayJson.data?.order_number).toBe(orderJson.data?.order_number);

        const afterOrder = await inspect('after-order');
        expect(afterOrder.availability).toEqual(availabilityBefore);
        expect(afterOrder.product.stock_quantity).toBe(9);
        expect(afterOrder.order).toMatchObject({
            id: orderJson.data?.order_id,
            status: 'pending',
            payment_method: 'pending',
            payment_status: 'pending',
        });

        let bookingRequestBody = '';
        let bookingRequestContentType = 'application/x-www-form-urlencoded';
        let bookingRequestCsrfToken = '';
        let bookingRequestHeadersPromise: Promise<Record<string, string>> | null = null;
        await bookingPage.goto('/directorio', { waitUntil: 'networkidle' });
        await expect(bookingPage.getByTestId('directory-page')).toBeVisible();
        await bookingPage.getByTestId('directory-search-control').fill(tenantName);
        await expect.poll(
            () => new URL(bookingPage.url()).searchParams.get('search'),
            { timeout: 15_000 },
        ).toBe(tenantName);
        const bookingTenantCard = bookingPage.getByTestId('tenant-card').filter({ hasText: tenantName }).first();
        await expect(bookingTenantCard).toBeVisible();
        await bookingTenantCard.getByTestId('primary-tenant-cta').click();
        await expect(bookingPage).toHaveURL(new RegExp(`${tenantMicrositePath}(?:\\?|$)`));
        const bookingCta = bookingPage.getByTestId('microsite-booking-cta');
        await expect(bookingCta).toHaveCount(1);
        await expect(bookingCta).toHaveAttribute('href', new RegExp(`${tenantSlug}\\.localhost(?::\\d+)?/book`));
        await bookingCta.click();
        await expect(bookingPage).toHaveURL(new RegExp(`${tenantSlug}\\.localhost(?::\\d+)?/book(?:\\?|$)`));
        await expect(bookingPage.getByTestId('booking-step-service')).toBeVisible();
        await bookingPage.getByTestId('booking-service-option').filter({ hasText: serviceName }).first().click();
        await expect(bookingPage.getByTestId('booking-step-provider')).toBeVisible();
        await bookingPage.getByTestId('booking-provider-option').filter({ hasText: providerName }).first().click();
        await expect(bookingPage.getByTestId('booking-step-time')).toBeVisible();
        const requestedLocalDate = nextTorontoDate();
        await bookingPage.getByTestId('booking-date-input').fill(requestedLocalDate);
        await expect(bookingPage.getByTestId('booking-slot-option').first()).toBeVisible();
        await bookingPage.getByTestId('booking-slot-option').first().click();
        await expect(bookingPage.getByTestId('booking-step-contact')).toBeVisible();
        await bookingPage.getByTestId('booking-customer-name').fill('Canada Booking E2E');
        await bookingPage.getByTestId('booking-customer-email').fill('canada.booking.e2e@example.test');
        await bookingPage.getByTestId('booking-customer-phone').fill('+1 416 555 0101');
        await bookingPage.getByTestId('booking-customer-notes').fill('Synthetic local booking.');
        bookingPage.on('request', (request) => {
            if (request.method() === 'POST' && new URL(request.url()).pathname === '/book') {
                bookingRequestBody = request.postData() || '';
                bookingRequestContentType = request.headers()['content-type'] || bookingRequestContentType;
                bookingRequestCsrfToken = request.headers()['x-csrf-token'] || '';
                bookingRequestHeadersPromise = request.allHeaders();
            }
        });
        const bookingResponsePromise = bookingPage.waitForResponse((response) =>
            response.request().method() === 'POST'
            && new URL(response.url()).pathname === '/book',
        );
        await bookingPage.getByTestId('booking-submit').click();
        const bookingResponse = await bookingResponsePromise;
        expect([302, 303]).toContain(bookingResponse.status());
        const firstBookingLocation = bookingResponse.headers()['location'] || '';
        expect(firstBookingLocation).toContain('/book/success?appointment=');
        await expect(bookingPage).toHaveURL(new RegExp(`${tenantSlug}\\.localhost(?::\\d+)?/book/success\\?appointment=\\d+`));
        await expect(bookingPage.getByTestId('booking-success')).toBeVisible();
        const appointmentId = Number(new URL(bookingPage.url()).searchParams.get('appointment'));
        expect(Number.isInteger(appointmentId)).toBe(true);
        expect(appointmentId).toBeGreaterThan(0);
        expect(bookingRequestBody).not.toBe('');
        expect(bookingRequestCsrfToken).not.toBe('');
        const bookingRequestAllHeaders = bookingRequestHeadersPromise ? await bookingRequestHeadersPromise : {};

        // Replay the exact captured UI request from the same tenant-origin page
        // so Chromium preserves the browser session and host-bound CSRF state.
        const bookingReplay = await bookingPage.evaluate(async ({ body, contentType, csrfToken, inertiaVersion }) => {
            const response = await fetch('/book', {
                method: 'POST',
                body,
                credentials: 'same-origin',
                redirect: 'follow',
                headers: {
                    'Content-Type': contentType,
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Inertia': 'true',
                    'X-Inertia-Version': inertiaVersion,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            return {
                status: response.status,
                location: response.url,
            };
        }, {
            body: bookingRequestBody,
            contentType: bookingRequestContentType,
            csrfToken: bookingRequestCsrfToken,
            inertiaVersion: bookingRequestAllHeaders['x-inertia-version'] || '',
        });
        expect(bookingReplay.status).toBe(200);
        expect(bookingReplay.location).toBe(new URL(firstBookingLocation, bookingPage.url()).toString());

        const afterBooking = await inspect('after-booking');
        expect(afterBooking.availability).toEqual(availabilityBefore);
        expect(afterBooking.selected_slot_unavailable).toBe(true);
        expect(afterBooking.product.stock_quantity).toBe(9);
        expect(afterBooking.order).toMatchObject({ id: orderJson.data?.order_id, status: 'pending' });
        expect(afterBooking.appointment).toMatchObject({ id: appointmentId, status: 'pending', source: 'public_booking', timezone: marketTimezone });
        expect(afterBooking.outbox.conversion_events).toHaveLength(2);
        expect(afterBooking.outbox.conversion_events).toEqual(expect.arrayContaining([
            expect.objectContaining({ aggregate_id: `order:${orderJson.data?.order_id}`, event_name: 'order.created' }),
            expect.objectContaining({ aggregate_id: `appointment:${appointmentId}`, event_name: 'reservation.created' }),
        ]));

        await expect.poll(() => orderDiagnostics.orderPosts.length).toBe(1);
        expect(orderDiagnostics.orderResponses).toEqual([201]);
        expect(orderDiagnostics.bookingPosts).toEqual([]);
        await expect.poll(() => bookingDiagnostics.bookingPosts.length).toBe(2);
        expect(bookingDiagnostics.bookingPosts).toEqual([`POST ${tenantOrigin}/book`, `POST ${tenantOrigin}/book`]);
        expect(bookingDiagnostics.bookingResponses[0]).toBe(bookingResponse.status());
        expect(bookingDiagnostics.bookingResponses).toHaveLength(2);
        expect([302, 303]).toContain(bookingDiagnostics.bookingResponses[1]);
        expect(bookingDiagnostics.orderPosts).toEqual([]);
        assertCleanDiagnostics(orderDiagnostics, 'order context');
        assertCleanDiagnostics(bookingDiagnostics, 'booking context');
    } finally {
        await orderContext.close();
        await bookingContext.close();
    }
});
