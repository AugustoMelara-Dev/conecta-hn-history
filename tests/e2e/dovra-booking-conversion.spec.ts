import { execFile } from 'node:child_process';
import { existsSync } from 'node:fs';
import { isAbsolute, resolve } from 'node:path';
import { promisify } from 'node:util';

import { expect, test } from '@playwright/test';

const tenantSlug = 'dovra-booking-conversion-e2e';
const tenantName = 'Dovra Booking Conversion E2E';
const serviceName = 'Servicio Booking E2E';
const providerName = 'Provider Booking E2E';
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
const baseOrigin = new URL(baseUrl).origin;
const hondurasTimezone = 'America/Tegucigalpa';
const tenantOriginUrl = new URL(baseUrl);
tenantOriginUrl.hostname = `${tenantSlug}.${process.env.APP_DOMAIN || 'localhost'}`;
const tenantOrigin = tenantOriginUrl.origin;
const passiveExternalAssetRules = new Map([
    ['images.unsplash.com', new Set(['image'])],
    ['ui-avatars.com', new Set(['image'])],
    ['via.placeholder.com', new Set(['image'])],
    ['fonts.bunny.net', new Set(['stylesheet', 'font'])],
]);
const forbiddenSameOriginOperationalPath = /(?:^|\/)(?:api\/v1\/(?:orders?|payments?|billing|checkout|providers?)|(?:billing|checkout|payments?|orders?)(?:\/|$))/iu;
const repositoryRoot = resolve(import.meta.dirname, '../..');
const inspectBookingConversionHelper = resolve(
    repositoryRoot,
    'tests/e2e/support/inspect-booking-conversion.php',
);
const execFileAsync = promisify(execFile);

function futureLocalDate(): string {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: hondurasTimezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const today = Object.fromEntries(
        parts
            .filter(({ type }) => ['year', 'month', 'day'].includes(type))
            .map(({ type, value }) => [type, Number(value)]),
    ) as { year: number; month: number; day: number };
    // Build tomorrow at UTC noon-equivalent in Honduras, independent of the
    // machine process timezone and safe across month/year boundaries.
    const tomorrow = new Date(Date.UTC(today.year, today.month - 1, today.day + 1, 18));
    const tomorrowParts = new Intl.DateTimeFormat('en-US', {
        timeZone: hondurasTimezone,
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

test('buyer books one pending appointment from the real directory through the tenant subdomain', async ({ page }) => {
    test.setTimeout(120_000);

    expect(
        process.env.APP_KEY?.trim(),
        'APP_KEY must be explicitly provided to the booking conversion E2E process',
    ).toBeTruthy();
    expect(
        process.env.DOVRA_E2E_ALLOW_SYNTHETIC_BOOKING_CONVERSION,
        'synthetic booking conversion requires an explicit local guard',
    ).toBe('true');

    const allowedOrigins = new Set([baseOrigin, tenantOrigin]);
    const bookingPosts: string[] = [];
    const bookingResponses: number[] = [];
    const orderPosts: string[] = [];
    const externalOperationalRequests: string[] = [];
    const sameOriginOperationalRequests: string[] = [];
    const serverErrors: string[] = [];
    const consoleErrors: string[] = [];
    const pageErrors: string[] = [];
    let popupCount = 0;

    page.on('request', (request) => {
        const requestUrl = new URL(request.url());
        if (!allowedOrigins.has(requestUrl.origin)
            && !isAllowedPassiveExternalAsset(request, requestUrl)) {
            externalOperationalRequests.push(`${request.method()} ${request.url()}`);
        }
        if (allowedOrigins.has(requestUrl.origin)
            && forbiddenSameOriginOperationalPath.test(requestUrl.pathname)) {
            sameOriginOperationalRequests.push(`${request.method()} ${request.url()}`);
        }

        if (request.method() === 'POST' && requestUrl.pathname === '/book') {
            bookingPosts.push(`${request.method()} ${requestUrl.origin}${requestUrl.pathname}`);
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
            && new URL(response.url()).pathname === '/book') {
            bookingResponses.push(response.status());
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

    const bookingCta = page.getByTestId('microsite-booking-cta');
    await expect(bookingCta).toHaveCount(1);
    await expect(bookingCta).toHaveAttribute('href', new RegExp(`${tenantSlug}\\.localhost(?::\\d+)?/book`));
    await bookingCta.click();
    await expect(page).toHaveURL(new RegExp(`${tenantSlug}\\.localhost(?::\\d+)?/book(?:\\?|$)`));
    await expect(page.getByTestId('booking-step-service')).toBeVisible();

    await page
        .getByTestId('booking-service-option')
        .filter({ hasText: serviceName })
        .first()
        .click();
    await expect(page.getByTestId('booking-step-provider')).toBeVisible();
    await page
        .getByTestId('booking-provider-option')
        .filter({ hasText: providerName })
        .first()
        .click();
    await expect(page.getByTestId('booking-step-time')).toBeVisible();

    const requestedLocalDate = futureLocalDate();
    await page.getByTestId('booking-date-input').fill(requestedLocalDate);
    await expect(page.getByTestId('booking-slot-option').first()).toBeVisible();
    await page.getByTestId('booking-slot-option').first().click();
    await expect(page.getByTestId('booking-step-contact')).toBeVisible();

    await page.getByTestId('booking-customer-name').fill('Booking E2E');
    await page.getByTestId('booking-customer-email').fill('booking.customer.e2e@example.test');
    await page.getByTestId('booking-customer-phone').fill('9999-2027');
    await page.getByTestId('booking-customer-notes').fill('Solicitud sintetica local.');

    const bookingResponsePromise = page.waitForResponse((response) =>
        response.request().method() === 'POST'
        && new URL(response.url()).pathname === '/book',
    );
    await page.getByTestId('booking-submit').click();
    const bookingResponse = await bookingResponsePromise;
    expect([302, 303]).toContain(bookingResponse.status());
    await expect(page).toHaveURL(new RegExp(`${tenantSlug}\\.localhost(?::\\d+)?/book/success\\?appointment=\\d+`));
    await expect(page.getByTestId('booking-success')).toBeVisible();
    await expect(page.getByText('Solicitud de reserva recibida')).toBeVisible();
    await expect(page.getByTestId('booking-success-status')).toHaveText('Pendiente de confirmación');

    const appointmentId = Number(new URL(page.url()).searchParams.get('appointment'));
    expect(Number.isInteger(appointmentId)).toBe(true);
    expect(appointmentId).toBeGreaterThan(0);

    const phpBinary = process.env.DOVRA_PHP_BINARY;
    const databasePath = process.env.DOVRA_E2E_DATABASE_PATH;
    const configuredDatabase = process.env.DB_DATABASE;
    expect(phpBinary, 'DOVRA_PHP_BINARY is required for booking conversion inspection').toBeTruthy();
    expect(isAbsolute(phpBinary ?? '')).toBe(true);
    expect(existsSync(phpBinary ?? '')).toBe(true);
    expect(existsSync(inspectBookingConversionHelper)).toBe(true);
    expect(databasePath, 'DOVRA_E2E_DATABASE_PATH is required for booking conversion inspection').toBeTruthy();
    expect(configuredDatabase, 'DB_DATABASE is required for booking conversion inspection').toBeTruthy();
    expect(resolve(databasePath ?? '')).toBe(resolve(configuredDatabase ?? ''));

    const helperResult = await execFileAsync(phpBinary as string, [inspectBookingConversionHelper, String(appointmentId)], {
        cwd: repositoryRoot,
        env: {
            ...process.env,
            APP_ENV: process.env.APP_ENV || 'local',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: configuredDatabase,
            DOVRA_E2E_DATABASE_PATH: databasePath,
            DOVRA_E2E_ALLOW_SYNTHETIC_BOOKING_CONVERSION: 'true',
        },
        timeout: 30_000,
        windowsHide: true,
        maxBuffer: 1024 * 1024,
    });
    expect(helperResult.stderr).toBe('');

    const inspection = JSON.parse(helperResult.stdout.trim()) as {
        status: string;
        appointment: {
            id: number;
            status: string;
            source: string;
            currency: string;
            timezone: string;
            local_date: string;
            local_display_date: string;
            local_time: string;
        };
        outbox: {
            reservation_created: number;
            event_name: string;
        };
    };
    expect(inspection).toMatchObject({
        status: 'ok',
        appointment: {
            id: appointmentId,
            status: 'pending',
            source: 'public_booking',
            currency: 'HNL',
            timezone: hondurasTimezone,
        },
        outbox: {
            reservation_created: 1,
            event_name: 'reservation.created',
        },
    });
    expect(inspection.appointment.local_date).toBe(requestedLocalDate);
    await expect(page.getByTestId('booking-success-status')).toHaveText('Pendiente de confirmación');
    await expect(page.getByTestId('booking-success-date')).toHaveText(inspection.appointment.local_display_date);
    await expect(page.getByTestId('booking-success-local-date')).toHaveText(inspection.appointment.local_date);
    await expect(page.getByTestId('booking-success-time')).toHaveText(inspection.appointment.local_time);
    await expect(page.getByTestId('booking-success-timezone')).toHaveText(inspection.appointment.timezone);

    await expect.poll(() => bookingPosts.length).toBe(1);
    expect(bookingResponses).toEqual([bookingResponse.status()]);
    expect(orderPosts).toEqual([]);
    expect(externalOperationalRequests, externalOperationalRequests.join('\n')).toEqual([]);
    expect(sameOriginOperationalRequests, sameOriginOperationalRequests.join('\n')).toEqual([]);
    expect(popupCount).toBe(0);
    expect(serverErrors, serverErrors.join('\n')).toEqual([]);
    expect(pageErrors, pageErrors.join('\n')).toEqual([]);
    expect(consoleErrors, consoleErrors.join('\n')).toEqual([]);
});
