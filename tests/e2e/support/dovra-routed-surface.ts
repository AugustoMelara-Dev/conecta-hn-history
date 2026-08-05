import AxeBuilder from '@axe-core/playwright';
import { expect, type Page } from '@playwright/test';
import {
    isWithinReducedMotionThreshold,
    maxDurationMilliseconds,
    REDUCED_MOTION_MAX_DURATION_MS,
} from './reduced-motion-duration.mjs';

const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

export async function visitRoutedSurface(page: Page, path: string) {
    const response = await page.goto(path, { waitUntil: 'domcontentloaded' });

    expect(response, `No response while navigating to ${path}`).not.toBeNull();
    expect(response?.status(), `Server error while navigating to ${path}`).toBeLessThan(500);
    await expect(page.locator('body')).toBeVisible();
}

export async function assertAxe(page: Page) {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    // Automated axe output is evidence only; it does not establish WCAG conformance.
    expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
}

export async function assertNoHorizontalOverflow(page: Page) {
    const result = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
    }));

    expect(result.scrollWidth, JSON.stringify(result)).toBeLessThanOrEqual(result.viewport + 1);
}

export async function assertReflow(page: Page) {
    const result = await page.evaluate(() => {
        const viewportWidth = document.documentElement.clientWidth;
        const offenders = Array.from(document.querySelectorAll('body *'))
            .filter((element) => {
                const rect = element.getBoundingClientRect();
                return rect.right > viewportWidth + 1 || rect.left < -1;
            })
            .map((element) => ({
                tag: element.tagName,
                text: String(element.textContent || '').trim().slice(0, 80),
            }))
            .filter((entry) => entry.text.length > 0)
            .slice(0, 12);

        return {
            viewportWidth,
            scrollWidth: document.documentElement.scrollWidth,
            offenders,
        };
    });

    expect(result.scrollWidth, JSON.stringify(result)).toBeLessThanOrEqual(result.viewportWidth + 1);
    expect(result.offenders, JSON.stringify(result)).toEqual([]);
}

type MotionInspection = {
    selector: string;
    visible: boolean;
    required: boolean;
    transitionDuration?: string;
    animationDuration?: string;
};

function assertReducedMotionDuration(element: MotionInspection, property: 'transitionDuration' | 'animationDuration') {
    const rawDuration = element[property];
    const label = `${element.selector} ${property}=${String(rawDuration)}`;

    if (typeof rawDuration !== 'string') {
        expect(rawDuration, `${label} is unavailable`).toEqual(expect.any(String));

        return;
    }

    const maximum = maxDurationMilliseconds(rawDuration);
    expect(
        isWithinReducedMotionThreshold(rawDuration),
        `${label}; max=${maximum}ms, allowed<=${REDUCED_MOTION_MAX_DURATION_MS}ms`,
    ).toBe(true);
}

type ReducedMotionOptions = {
    requiredSelectors?: string[];
    optionalSelectors?: string[];
};

export async function assertReducedMotion(
    page: Page,
    { requiredSelectors = [], optionalSelectors = [] }: ReducedMotionOptions = {},
) {
    const computed = await page.evaluate(({ required, optional, reducedMotionQuery }) => {
        const inspect = (selector: string, requiredElement: boolean) => {
            const element = document.querySelector(selector);
            const visible = Boolean(element && element.getClientRects().length > 0);
            if (!element || (!requiredElement && !visible)) {
                return { selector, visible: false, required: requiredElement };
            }

            const styles = getComputedStyle(element);
            return {
                selector,
                visible,
                required: requiredElement,
                transitionDuration: styles.transitionDuration,
                animationDuration: styles.animationDuration,
            };
        };

        return {
            prefersReducedMotion: window.matchMedia(reducedMotionQuery).matches,
            required: required.map((selector) => inspect(selector, true)),
            optional: optional.map((selector) => inspect(selector, false)),
        };
    }, { required: requiredSelectors, optional: optionalSelectors, reducedMotionQuery: REDUCED_MOTION_QUERY });

    expect(computed.prefersReducedMotion).toBe(true);
    expect(computed.required.length + computed.optional.filter((item) => item.visible).length)
        .toBeGreaterThanOrEqual(1);

    for (const element of computed.required as MotionInspection[]) {
        expect(element.visible, `${element.selector} was not found or computed`).toBe(true);
        assertReducedMotionDuration(element, 'transitionDuration');
        assertReducedMotionDuration(element, 'animationDuration');
    }

    for (const element of computed.optional.filter((item) => item.visible) as MotionInspection[]) {
        assertReducedMotionDuration(element, 'transitionDuration');
        assertReducedMotionDuration(element, 'animationDuration');
    }
}
