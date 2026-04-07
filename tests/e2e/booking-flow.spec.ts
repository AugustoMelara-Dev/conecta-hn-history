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

        await page.locator('[data-testid^="booking-provider-"]').first().click();
        await expect(page.getByTestId('booking-step-slots')).toBeVisible();

        await page.getByTestId('booking-date-input').fill(nextDayIso());

        const firstSlot = page.locator('[data-testid^="booking-slot-"]').first();
        await expect(firstSlot).toBeVisible();
        await firstSlot.click();

        await expect(page.getByTestId('booking-step-confirm')).toBeVisible();

        await page.getByTestId('booking-name-input').fill('Cliente E2E');
        await page.getByTestId('booking-phone-input').fill('9999-1111');
        await page.getByTestId('booking-email-input').fill('cliente.e2e@example.test');
        await page.getByTestId('booking-notes-input').fill('Reserva creada desde Playwright.');
        await Promise.all([
            page.waitForURL(/\/t\/barberia-norte-pro\/book\/success\?appointment=/, { timeout: 20000 }),
            page.getByTestId('booking-submit').click(),
        ]);

        await expect(page.getByTestId('booking-success')).toBeVisible({ timeout: 20000 });
        await expect(page.getByTestId('booking-success')).toContainText('Corte premium con barba');
    });
});
