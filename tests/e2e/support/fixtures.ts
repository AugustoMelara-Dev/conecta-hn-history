export const officialFixtures = {
  owner: {
    email: process.env.SMOKE_OWNER_EMAIL || process.env.BUSINESS_OWNER_EMAIL || 'owner-demo-ai-guide-hn@vitobusinessos.app',
    password: process.env.SMOKE_OWNER_PASSWORD || process.env.BUSINESS_OWNER_PASSWORD || 'ChangeMe123!',
  },
  customer: {
    email: process.env.SMOKE_CUSTOMER_EMAIL || process.env.BUYER_CUSTOMER_EMAIL || 'customer-demo-ai-guide-hn@vitobusinessos.app',
    password: process.env.SMOKE_CUSTOMER_PASSWORD || process.env.BUYER_CUSTOMER_PASSWORD || 'ChangeMe123!',
  },
  product: {
    slug: process.env.SMOKE_PRODUCT_TENANT_SLUG || process.env.E2E_PRODUCT_TENANT_SLUG || 'demo-ai-guide-hn',
    expectedPanelText: process.env.SMOKE_PRODUCT_PANEL_TEXT || 'Demo AI Guide HN',
  },
};

export const officialRoutes = {
  productMicrosite: process.env.SMOKE_PRODUCT_MICROSITE_URL || 'http://localhost:8000/t/demo-ai-guide-hn',
};
