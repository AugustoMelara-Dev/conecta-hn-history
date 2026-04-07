import { expect, test } from '@playwright/test';

function nextDayIso(): string {
    const date = new Date();
    date.setDate(date.getDate() + 1);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function dayOffsetIso(offset: number): string {
    const date = new Date();
    date.setDate(date.getDate() + offset);

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
        await page.goto('/t/barberia-norte-pro/book');

        await expect(page.getByTestId('booking-wizard')).toBeVisible();

        await page.locator('[data-testid^="booking-service-"]').first().click();
        await expect(page.getByTestId('booking-step-providers')).toBeVisible();

        await page.locator('[data-testid^="booking-provider-"]').filter({ hasText: 'Marco Diaz' }).click();
        await expect(page.getByTestId('booking-step-slots')).toBeVisible();

        await selectAvailableSlot(page);

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

async function selectAvailableSlot(page) {
    const slotOptions = page.locator('[data-testid^="booking-slot-"]');
    const dateInput = page.getByTestId('booking-date-input');

    for (let offset = 1; offset <= 7; offset += 1) {
        const date = offset === 1 ? nextDayIso() : dayOffsetIso(offset);
        const slotResponsePromise = page.waitForResponse((response) =>
            response.request().method() === 'GET'
            && response.url().includes('/book/slots')
            && response.url().includes(`date=${date}`)
        , { timeout: 8000 }).catch(() => null);

        await dateInput.fill(date);
        await expect(dateInput).toHaveValue(date);
        await dateInput.press('Tab');

        const slotResponse = await slotResponsePromise;
        if (!slotResponse || !slotResponse.ok()) {
            continue;
        }

        const payload = await slotResponse.json();
        const slots = Array.isArray(payload.slots) ? payload.slots : [];

        if (slots.length === 0) {
            continue;
        }

        const targetSlot = slots[Math.min(2, slots.length - 1)];
        const selectedSlot = page.getByTestId(`booking-slot-${targetSlot.time.replace(':', '-')}`);
        await expect(slotOptions.first()).toBeVisible();
        await expect(selectedSlot).toBeVisible();
        await selectedSlot.click();

        return;
    }

    throw new Error('No booking slots became available within the next 7 days.');
}

async function submitBookingWithFallback(page) {
    const successUrl = /\/t\/barberia-norte-pro\/book\/success\?appointment=/;

    await page.getByTestId('booking-submit').click();

    try {
        await page.waitForURL(successUrl, { timeout: 20000 });

        return;
    } catch {
        const slotUnavailableError = page.getByText('Este horario ya no esta disponible. Por favor selecciona otro.');
        await expect(slotUnavailableError).toBeVisible({ timeout: 5000 });

        await page.getByRole('button', { name: 'Cambiar horario' }).click();
        await expect(page.getByTestId('booking-step-slots')).toBeVisible();

        const slotOptions = page.locator('[data-testid^="booking-slot-"]');
        await expect(slotOptions.nth(1)).toBeVisible();
        await slotOptions.nth(1).click();

        await fillBookingDetails(page);
        await Promise.all([
            page.waitForURL(successUrl, { timeout: 20000 }),
            page.getByTestId('booking-submit').click(),
        ]);
    }
}
