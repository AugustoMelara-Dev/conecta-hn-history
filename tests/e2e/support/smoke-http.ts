import { expect, type Page, type Response } from '@playwright/test';

const TRANSIENT_HTTP_STATUSES = new Set([429, 502, 503, 504]);

type RetryOptions = {
    attempts?: number;
    delayMs?: number;
    responseTimeoutMs?: number;
};

export async function gotoWithRetries(
    page: Page,
    path: string,
    options: RetryOptions = {},
): Promise<Response | null> {
    const attempts = Math.max(1, options.attempts ?? 3);
    const delayMs = Math.max(200, options.delayMs ?? 1200);
    const responseTimeoutMs = Math.max(1000, options.responseTimeoutMs ?? 12_000);

    let latestResponse: Response | null = null;

    for (let attempt = 1; attempt <= attempts; attempt += 1) {
        try {
            latestResponse = await page.goto(path, {
                waitUntil: 'domcontentloaded',
                timeout: responseTimeoutMs,
            });
        } catch {
            latestResponse = null;

            if (attempt === attempts) {
                break;
            }

            await page.waitForTimeout(delayMs);
            continue;
        }

        const status = latestResponse?.status() ?? 0;
        const shouldRetry = !latestResponse || TRANSIENT_HTTP_STATUSES.has(status);

        if (!shouldRetry || attempt === attempts) {
            break;
        }

        await page.waitForTimeout(delayMs);
    }

    return latestResponse;
}

export function expectNon5xx(response: Response | null, path: string): void {
    expect(response, `Expected "${path}" to return an HTTP response.`).toBeTruthy();
    const status = response?.status() ?? 0;
    expect(status, `Expected "${path}" to avoid 5xx status. Received ${status}.`).toBeLessThan(500);
}

export async function expectNoFatalServerText(page: Page): Promise<void> {
    await expect(page.locator('body')).not.toContainText(
        /500|internal server error|server error|error interno del servidor|bad gateway/i,
    );
}
