import { expect, test, type Page, type Request } from '@playwright/test';

function requiredEnvironment(name: string): string {
    const value = process.env[name]?.trim();
    if (!value) {
        throw new Error(`${name} is required for post-deploy smoke.`);
    }

    return value;
}

function mutationPath(request: Request): string | null {
    if (!['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method())) {
        return null;
    }

    return new URL(request.url()).pathname;
}

async function assertHealthyNavigation(page: Page, path: string): Promise<void> {
    const response = await page.goto(path, { waitUntil: 'domcontentloaded' });

    expect(response, `navigation to ${path} must return a response`).not.toBeNull();
    expect(response!.status(), `${path} must not redirect to an error`).toBeGreaterThanOrEqual(200);
    expect(response!.status(), `${path} must not redirect to an error`).toBeLessThan(400);
    await expect(page.locator('body')).not.toContainText(/500|server error|internal server error/i);
}

test.describe('Conecta HN non-mutating post-deploy smoke', () => {
    test('public launch surfaces are reachable without mutation', async ({ page }) => {
        const mutations: string[] = [];
        page.on('request', (request) => {
            const path = mutationPath(request);
            if (path) {
                mutations.push(`${request.method()} ${path}`);
            }
        });

        await assertHealthyNavigation(page, '/');
        await assertHealthyNavigation(page, '/directorio');
        await assertHealthyNavigation(page, requiredEnvironment('SMOKE_PRODUCT_MICROSITE_URL'));

        expect(mutations, 'public smoke must remain read-only').toEqual([]);
    });

    test('seeded owner can authenticate and read launch-critical tenant surfaces', async ({ page }) => {
        const email = requiredEnvironment('SMOKE_OWNER_EMAIL');
        const password = requiredEnvironment('SMOKE_OWNER_PASSWORD');
        const tenantSlug = requiredEnvironment('SMOKE_PRODUCT_TENANT_SLUG');
        const mutations: string[] = [];

        page.on('request', (request) => {
            const path = mutationPath(request);
            if (path && path !== '/login') {
                mutations.push(`${request.method()} ${path}`);
            }
        });

        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        await page.locator('#email').fill(email);
        await page.locator('#password').fill(password);
        await page.getByRole('button', { name: /iniciar sesi[oó]n|entrar/i }).click();
        await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 30_000 });

        await assertHealthyNavigation(page, `/app/${tenantSlug}`);
        await assertHealthyNavigation(page, `/app/${tenantSlug}/settings`);
        await assertHealthyNavigation(page, `/app/${tenantSlug}/copilot`);

        expect(mutations, 'post-login smoke must not mutate business or AI state').toEqual([]);
    });
});
