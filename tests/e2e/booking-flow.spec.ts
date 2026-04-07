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
        await page.goto('/t/barberia-norte-pro/book');

        await expect(page.getByTestId('booking-wizard')).toBeVisible();

        await page.locator('[data-testid^="booking-service-"]').first().click();
        await expect(page.getByTestId('booking-step-providers')).toBeVisible();

        await page.locator('[data-testid^="booking-provider-"]').filter({ hasText: 'Marco Diaz' }).click();
        await expect(page.getByTestId('booking-step-slots')).toBeVisible();

        await page.getByTestId('booking-date-input').fill(nextDayIso());

        const slotOptions = page.locator('[data-testid^="booking-slot-"]');
        await expect(slotOptions.first()).toBeVisible();

        const selectedSlot = (await slotOptions.count()) > 2 ? slotOptions.nth(2) : slotOptions.first();
        await selectedSlot.click();

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
