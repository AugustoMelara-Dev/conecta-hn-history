import { expect, test } from '@playwright/test';

function nextDayIso(): string {
    const date = new Date();
    date.setDate(date.getDate() + 1);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
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
    const date = nextDayIso();

    await setBookingDate(page, date);
    await waitForSlotResults(page);

    const count = await slotOptions.count();
    if (count === 0) {
        const slotPanelText = await page.getByTestId('booking-slots').innerText();
        throw new Error(`No booking slots became available for ${date}. Panel text: ${slotPanelText}`);
    }

    const selectedSlot = preference === 'last'
        ? slotOptions.last()
        : slotOptions.nth(Math.min(2, count - 1));

    await expect(selectedSlot).toBeVisible();
    await selectedSlot.click();
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

    const firstResponse = await submitBookingRequest(page);
    if (await finalizeBookingSuccess(page, firstResponse, successUrl)) {
        return;
    }

    await startBookingFlow(page);
    await selectAvailableSlot(page, 'last');
    await fillBookingDetails(page);

    const retryResponse = await submitBookingRequest(page);
    if (await finalizeBookingSuccess(page, retryResponse, successUrl)) {
        return;
    }

    const visibleErrors = await page.locator('.text-rose-600, .text-rose-700').allInnerTexts();
    throw new Error(`Booking flow did not reach success after retry. Visible errors: ${visibleErrors.join(' | ') || 'none'}`);
}

async function submitBookingRequest(page) {
    const bookingPostResponsePromise = page.waitForResponse((response) =>
        response.request().method() === 'POST'
        && response.url().includes('/t/barberia-norte-pro/book')
    , { timeout: 20000 }).catch(() => null);

    await page.getByTestId('booking-submit').click();

    return bookingPostResponsePromise;
}

async function finalizeBookingSuccess(page, bookingResponse, successUrl) {
    if (successUrl.test(page.url())) {
        return true;
    }

    try {
        await page.waitForFunction(
            (pattern) => new RegExp(pattern).test(window.location.href),
            successUrl.source,
            { timeout: 5000 },
        );

        return true;
    } catch {
        const responseUrl = bookingResponse?.url() ?? '';
        const locationHeader = bookingResponse?.headers()?.location ?? '';
        const inertiaLocationHeader = bookingResponse?.headers()?.['x-inertia-location'] ?? '';

        if (successUrl.test(responseUrl)) {
            await page.goto(responseUrl);

            return true;
        }

        if (successUrl.test(locationHeader)) {
            await page.goto(locationHeader);

            return true;
        }

        if (successUrl.test(inertiaLocationHeader)) {
            await page.goto(inertiaLocationHeader);

            return true;
        }

        if (successUrl.test(page.url())) {
            return true;
        }

        return false;
    }
}
