import { expect, test } from '@playwright/test';
import { officialFixtures } from './support/fixtures';

const scenarios = [
    {
        slug: officialFixtures.product.slug,
        panelTestId: 'product-panel',
        panelText: officialFixtures.product.expectedPanelText,
    },
    {
        slug: officialFixtures.service.slug,
        panelTestId: 'service-panel',
        panelText: officialFixtures.service.expectedPanelText,
    },
];

test.describe('Micrositios oficiales release', () => {
    for (const scenario of scenarios) {
        test(`${scenario.slug} renders a specialized storefront`, async ({ page }) => {
            await page.goto(`/t/${scenario.slug}`);

            await expect(page.getByTestId('tenant-microsite')).toBeVisible();
            await expect(page.getByTestId(scenario.panelTestId)).toContainText(scenario.panelText);
        });
    }
});
