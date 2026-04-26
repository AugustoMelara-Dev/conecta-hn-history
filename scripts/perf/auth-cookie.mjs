import { chromium } from '@playwright/test';

const baseUrl = (process.env.PERF_BASE_URL || process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const email = process.env.BUSINESS_OWNER_EMAIL || '';
const password = process.env.BUSINESS_OWNER_PASSWORD || '';

if (!email || !password) {
    console.error('BUSINESS_OWNER_EMAIL and BUSINESS_OWNER_PASSWORD are required.');
    process.exit(1);
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();

try {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
    await page.getByTestId('login-email-input').fill(email);
    await page.getByTestId('login-password-input').fill(password);
    await Promise.all([
        page.waitForLoadState('networkidle').catch(() => null),
        page.getByTestId('login-submit').click(),
    ]);

    const cookies = await page.context().cookies(baseUrl);
    const cookieHeader = cookies
        .filter((cookie) => ['laravel_session', 'XSRF-TOKEN'].includes(cookie.name))
        .map((cookie) => `${cookie.name}=${cookie.value}`)
        .join('; ');

    if (!cookieHeader.includes('laravel_session=')) {
        console.error('Login did not produce a laravel_session cookie.');
        process.exit(1);
    }

    console.log(cookieHeader);
} finally {
    await browser.close();
}
