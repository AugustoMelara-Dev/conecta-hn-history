import { chromium } from 'playwright';

const baseUrl = (process.env.PLAYWRIGHT_BASE_URL || 'https://vitobusinessos.vercel.app').replace(/\/$/, '');
const email = process.env.BUSINESS_OWNER_EMAIL || 'owner-demo-ai-guide-hn@conectahn.app';
const password = process.env.BUSINESS_OWNER_PASSWORD || 'ChangeMe123!';

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ locale: 'es-HN' });
const page = await context.newPage();

const result = {
  base_url: baseUrl,
  email,
  ok: false,
  final_url: null,
  note: null,
};

try {
  await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.getByRole('button', { name: /iniciar sesion|login|entrar/i }).first().click();

  await page.waitForTimeout(5000);
  const finalUrl = page.url();
  result.final_url = finalUrl;
  result.ok = /\/app(?:\/|$)/.test(new URL(finalUrl).pathname);
  result.note = result.ok
    ? 'Owner redirected to business app surface.'
    : 'Owner did not land on /app business surface.';
} catch (error) {
  result.note = String(error?.message || error);
} finally {
  await context.close();
  await browser.close();
}

process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
