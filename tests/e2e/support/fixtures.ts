const env = process.env;

function asRoute(slug: string): string {
    return `/t/${slug}`;
}

export const officialFixtures = {
    owner: {
        email: env.SMOKE_OWNER_EMAIL || env.BUSINESS_OWNER_EMAIL || 'owner-demo-ai-guide-hn@vitobusinessos.app',
        password: env.SMOKE_OWNER_PASSWORD || env.BUSINESS_OWNER_PASSWORD || 'ChangeMe123!',
    },
    customer: {
        email: env.SMOKE_CUSTOMER_EMAIL || env.CUSTOMER_EMAIL || 'customer-e2e@vitobusinessos.app',
        password: env.SMOKE_CUSTOMER_PASSWORD || env.CUSTOMER_PASSWORD || 'ChangeMe123!',
    },
    product: {
        slug: env.SMOKE_PRODUCT_TENANT_SLUG || env.E2E_PRODUCT_TENANT_SLUG || 'demo-ai-guide-hn',
        expectedPanelText: env.SMOKE_PRODUCT_PANEL_TEXT || env.E2E_PRODUCT_PANEL_TEXT || 'Combo Presupuesto 120 HNL',
    },
    service: {
        slug: env.SMOKE_SERVICE_TENANT_SLUG || env.E2E_SERVICE_TENANT_SLUG || 'demo-ai-booking-hn',
        expectedPanelText: env.SMOKE_SERVICE_PANEL_TEXT || env.E2E_SERVICE_PANEL_TEXT || 'Corte y barba express',
        expectedProviderName: env.SMOKE_SERVICE_PROVIDER_NAME || env.E2E_SERVICE_PROVIDER_NAME || 'Laura Rivera',
    },
    crossTenantSlug: env.SMOKE_CROSS_TENANT_SLUG || env.E2E_CROSS_TENANT_SLUG || env.E2E_SERVICE_TENANT_SLUG || 'demo-ai-booking-hn',
};

export const officialRoutes = {
    productMicrosite: asRoute(officialFixtures.product.slug),
    serviceMicrosite: asRoute(officialFixtures.service.slug),
    serviceBooking: `${asRoute(officialFixtures.service.slug)}/book`,
};
