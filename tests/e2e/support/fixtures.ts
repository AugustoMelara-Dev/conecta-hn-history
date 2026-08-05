const requirePublishedFixtures = process.env.SMOKE_REQUIRE_PUBLISHED_FIXTURES === 'true';

function fixture(name: string, value: string | undefined, fallback: string): string {
  const normalized = value?.trim();
  if (normalized) {
    return normalized;
  }
  if (requirePublishedFixtures) {
    throw new Error(`${name} is required when SMOKE_REQUIRE_PUBLISHED_FIXTURES=true.`);
  }
  return fallback;
}

export const officialFixtures = {
  owner: {
    email: fixture('SMOKE_OWNER_EMAIL', process.env.SMOKE_OWNER_EMAIL || process.env.BUSINESS_OWNER_EMAIL, 'owner-demo-ai-guide-hn@conectahn.test'),
    password: fixture('SMOKE_OWNER_PASSWORD', process.env.SMOKE_OWNER_PASSWORD || process.env.BUSINESS_OWNER_PASSWORD, 'ChangeMe123!'),
  },
  customer: {
    email: fixture('SMOKE_CUSTOMER_EMAIL', process.env.SMOKE_CUSTOMER_EMAIL || process.env.BUYER_CUSTOMER_EMAIL, 'customer-demo-ai-guide-hn@conectahn.test'),
    password: fixture('SMOKE_CUSTOMER_PASSWORD', process.env.SMOKE_CUSTOMER_PASSWORD || process.env.BUYER_CUSTOMER_PASSWORD, 'ChangeMe123!'),
  },
  product: {
    slug: fixture('SMOKE_PRODUCT_TENANT_SLUG', process.env.SMOKE_PRODUCT_TENANT_SLUG || process.env.E2E_PRODUCT_TENANT_SLUG, 'demo-ai-guide-hn'),
    expectedPanelText: process.env.SMOKE_PRODUCT_PANEL_TEXT || 'Demo AI Guide HN',
  },
};

export const officialRoutes = {
  productMicrosite: fixture('SMOKE_PRODUCT_MICROSITE_URL', process.env.SMOKE_PRODUCT_MICROSITE_URL, `/t/${officialFixtures.product.slug}`),
};