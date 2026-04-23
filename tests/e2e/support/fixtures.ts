const env = process.env;

function asRoute(slug: string): string {
    return `/t/${slug}`;
}

export const officialFixtures = {
    owner: {
        email: env.BUSINESS_OWNER_EMAIL || 'owner-demo-ai-guide-hn@conectahn.app',
        password: env.BUSINESS_OWNER_PASSWORD || 'ChangeMe123!',
    },
    product: {
        slug: env.E2E_PRODUCT_TENANT_SLUG || 'demo-ai-guide-hn',
        expectedPanelText: env.E2E_PRODUCT_PANEL_TEXT || 'Combo Presupuesto 120 HNL',
    },
    service: {
        slug: env.E2E_SERVICE_TENANT_SLUG || 'demo-ai-booking-hn',
        expectedPanelText: env.E2E_SERVICE_PANEL_TEXT || 'Corte y barba express',
        expectedProviderName: env.E2E_SERVICE_PROVIDER_NAME || 'Laura Rivera',
    },
    crossTenantSlug: env.E2E_CROSS_TENANT_SLUG || env.E2E_SERVICE_TENANT_SLUG || 'demo-ai-booking-hn',
};

export const officialRoutes = {
    productMicrosite: asRoute(officialFixtures.product.slug),
    serviceMicrosite: asRoute(officialFixtures.service.slug),
    serviceBooking: `${asRoute(officialFixtures.service.slug)}/book`,
};
