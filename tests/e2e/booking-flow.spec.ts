import { expect, test } from '@playwright/test';

function nextBookingDates(daysAhead = 7): string[] {
    const base = new Date();
    // Use midday to avoid DST edge-case jumps when adding days.
    base.setHours(12, 0, 0, 0);

    return Array.from({ length: daysAhead }, (_, index) => {
        const date = new Date(base);
        date.setDate(base.getDate() + index + 1);

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    });
}

test.describe('Reservas publicas', () => {
    test('service CTA from the microsite preselects the chosen service', async ({ page }) => {
        await page.goto('/t/bufete-lex-hn');

        await expect(page.getByTestId('service-panel')).toBeVisible();
        await page.getByTestId('service-panel').getByRole('link', { name: 'Reservar' }).first().click();

        await expect(page).toHaveURL(/\/t\/bufete-lex-hn\/book\?service=/);
        await expect(page.getByTestId('booking-step-providers')).toBeVisible();
    });

    test('visitor can complete a booking flow for a service business', async ({ page }) => {
        await startBookingFlow(page);
        await selectAvailableSlot(page, 'middle');

        await fillBookingDetails(page);
        await submitBookingWithFallback(page);

        await expect(page.getByTestId('booking-success')).toBeVisible({ timeout: 20000 });
        await expect(page.getByTestId('booking-success')).toContainText('Corte premium con barba');
    });
});

async function fillBookingDetails(page) {
    await expect(page.getByTestId('booking-step-confirm')).toBeVisible();
    await page.getByTestId('booking-name-input').fill('Cliente E2E');
    await page.getByTestId('booking-phone-input').fill('9999-1111');
    await page.getByTestId('booking-email-input').fill('cliente.e2e@example.test');
    await page.getByTestId('booking-notes-input').fill('Reserva creada desde Playwright.');
}

async function startBookingFlow(page) {
    await page.goto('/t/barberia-norte-pro/book');

    await expect(page.getByTestId('booking-wizard')).toBeVisible();

    await page.locator('[data-testid^="booking-service-"]').first().click();
    await expect(page.getByTestId('booking-step-providers')).toBeVisible();

    await page.locator('[data-testid^="booking-provider-"]').filter({ hasText: 'Marco Diaz' }).click();
    await expect(page.getByTestId('booking-step-slots')).toBeVisible();
}

async function selectAvailableSlot(page, preference = 'middle') {
    const slotOptions = page.locator('[data-testid^="booking-slot-"]');
    const candidateDates = nextBookingDates(7);
    const attempts = [];

    for (const date of candidateDates) {
        const slotsResponsePromise = page.waitForResponse((response) => {
            if (response.request().method() !== 'GET' || !response.url().includes('/book/slots')) {
                return false;
            }

            try {
                return new URL(response.url()).searchParams.get('date') === date;
            } catch {
                return false;
            }
        }, { timeout: 12000 }).catch(() => null);

        await setBookingDate(page, date);
        await waitForSlotResults(page);

        const slotsResponse = await slotsResponsePromise;
        const count = await slotOptions.count();

        if (count > 0) {
            const selectedSlot = preference === 'last'
                ? slotOptions.last()
                : slotOptions.nth(Math.min(2, count - 1));

            await expect(selectedSlot).toBeVisible();
            await selectedSlot.click();

            return;
        }

        const slotPanelText = await page.getByTestId('booking-slots').innerText().catch(() => 'Panel unavailable');
        let responseBodySnippet = 'none';

        if (slotsResponse && !slotsResponse.ok()) {
            const rawBody = await slotsResponse.text().catch(() => '');
            responseBodySnippet = JSON.stringify(rawBody.replace(/\s+/g, ' ').slice(0, 200));
        }

        attempts.push([
            `date: ${date}`,
            `status: ${slotsResponse?.status() ?? 'none'}`,
            `panel: ${slotPanelText.replace(/\s+/g, ' ').trim()}`,
            `body: ${responseBodySnippet}`,
        ].join(' | '));
    }

    throw new Error(`No booking slots became available in the next ${candidateDates.length} days. Attempts: ${attempts.join(' || ')}`);
}

async function setBookingDate(page, date) {
    const dateInput = page.getByTestId('booking-date-input');

    await dateInput.evaluate((element, value) => {
        const input = element;
        const descriptor = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
        descriptor?.set?.call(input, value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }, date);

    await expect(dateInput).toHaveValue(date);
}

async function waitForSlotResults(page) {
    await page.waitForFunction(() => {
        const slotPanel = document.querySelector('[data-testid="booking-slots"]');
        if (!slotPanel) {
            return false;
        }

        const text = slotPanel.textContent ?? '';
        if (text.includes('Cargando horarios disponibles...')) {
            return false;
        }

        return slotPanel.querySelector('[data-testid^="booking-slot-"]')
            || text.includes('No hay horarios disponibles para esta fecha. Prueba con otro dia.');
    }, { timeout: 10000 });
}

async function submitBookingWithFallback(page) {
    const successUrl = /\/t\/barberia-norte-pro\/book\/success\?appointment=/;

    const firstAttempt = await submitBookingRequest(page);
    if (await finalizeBookingSuccess(page, firstAttempt, successUrl)) {
        return;
    }

    await startBookingFlow(page);
    await selectAvailableSlot(page, 'last');
    await fillBookingDetails(page);

    const retryAttempt = await submitBookingRequest(page);
    if (await finalizeBookingSuccess(page, retryAttempt, successUrl)) {
        return;
    }

    const failureDetails = await describeBookingFailure(page, retryAttempt);
    const visibleErrors = await page.locator('.text-rose-600, .text-rose-700').allInnerTexts();
    throw new Error(`Booking flow did not reach success after retry. Visible errors: ${visibleErrors.join(' | ') || 'none'}. ${failureDetails}`);
}

async function submitBookingRequest(page) {
    const bookingPostResponsePromise = page.waitForResponse((response) =>
        response.request().method() === 'POST'
        && response.url().includes('/t/barberia-norte-pro/book')
    , { timeout: 20000 }).catch(() => null);
    const successPageResponsePromise = page.waitForResponse((response) =>
        response.request().method() === 'GET'
        && /\/t\/barberia-norte-pro\/book\/success\?appointment=/.test(response.url())
    , { timeout: 12000 }).catch(() => null);

    await page.getByTestId('booking-submit').click();

    const [bookingResponse, successPageResponse] = await Promise.all([
        bookingPostResponsePromise,
        successPageResponsePromise,
    ]);

    return { bookingResponse, successPageResponse };
}

async function finalizeBookingSuccess(page, attempt, successUrl) {
    if (successUrl.test(page.url())) {
        return true;
    }

    if (attempt.successPageResponse?.ok()) {
        await page.waitForURL(successUrl, { timeout: 10000 }).catch(() => null);
        await expect(page.getByTestId('booking-success')).toBeVisible({ timeout: 15000 });

        return true;
    }

    try {
        await page.waitForFunction(
            (pattern) => new RegExp(pattern).test(window.location.href),
            successUrl.source,
            { timeout: 12000 },
        );

        await expect(page.getByTestId('booking-success')).toBeVisible({ timeout: 15000 });

        return true;
    } catch {
        const responseUrl = attempt.bookingResponse?.url() ?? '';
        const locationHeader = attempt.bookingResponse?.headers()?.location ?? '';
        const inertiaLocationHeader = attempt.bookingResponse?.headers()?.['x-inertia-location'] ?? '';

        if (successUrl.test(responseUrl)) {
            await page.goto(responseUrl);
            await expect(page.getByTestId('booking-success')).toBeVisible({ timeout: 15000 });

            return true;
        }

        if (successUrl.test(locationHeader)) {
            await page.goto(locationHeader);
            await expect(page.getByTestId('booking-success')).toBeVisible({ timeout: 15000 });

            return true;
        }

        if (successUrl.test(inertiaLocationHeader)) {
            await page.goto(inertiaLocationHeader);
            await expect(page.getByTestId('booking-success')).toBeVisible({ timeout: 15000 });

            return true;
        }

        if (successUrl.test(page.url())) {
            return true;
        }

        return false;
    }
}

async function describeBookingFailure(page, attempt) {
    const serverErrorVisible = await page.getByRole('heading', { name: '500' }).isVisible().catch(() => false);
    const bodyText = await page.locator('body').innerText().catch(() => '');

    return [
        `current URL: ${page.url()}`,
        `POST status: ${attempt.bookingResponse?.status() ?? 'none'}`,
        `POST location: ${attempt.bookingResponse?.headers()?.location ?? 'none'}`,
        `POST inertia location: ${attempt.bookingResponse?.headers()?.['x-inertia-location'] ?? 'none'}`,
        `success GET status: ${attempt.successPageResponse?.status() ?? 'none'}`,
        `success GET url: ${attempt.successPageResponse?.url() ?? 'none'}`,
        serverErrorVisible ? 'server error page visible: yes' : 'server error page visible: no',
        `body snippet: ${JSON.stringify(bodyText.replace(/\s+/g, ' ').slice(0, 240))}`,
    ].join(' | ');
}
