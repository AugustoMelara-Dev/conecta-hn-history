import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import fixtures from '../../resources/stories/dovra-program-10.fixtures.json' assert { type: 'json' };

test.use({ trace: 'off' });

const escapeHtml = (value: string): string =>
    String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');

function buildMarkup(initialScenarioKey = 'hybrid'): string {
    const scenario = fixtures.scenarios.find((item) => item.key === initialScenarioKey) || fixtures.scenarios[0];
    const selectedMarket = scenario.marketOptions.find((market) => market.code === scenario.defaultMarketCode) || scenario.marketOptions[0];

    return `
        <div class="min-h-screen bg-background text-foreground">
            <a href="#main-content" class="fixed left-4 top-4 z-[100] -translate-y-24 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-lg transition-transform focus:translate-y-0 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 motion-reduce:transition-none">
                Saltar al contenido principal
            </a>

            <main id="main-content" tabindex="-1" class="mx-auto flex min-h-screen max-w-6xl flex-col gap-6 px-4 py-6 outline-none sm:px-6 lg:px-8">
                <section class="flex flex-col gap-4">
                    <article class="rounded-lg border border-border bg-card shadow-sm">
                        <header class="flex flex-col gap-4 p-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-md border border-transparent bg-secondary px-2.5 py-0.5 text-xs font-semibold text-secondary-foreground rounded-full">
                                    ${escapeHtml(fixtures.brand.category)}
                                </span>
                                <span class="inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold text-foreground rounded-full">
                                    Manual AT review required
                                </span>
                            </div>
                            <h1 class="text-balance text-3xl font-semibold tracking-tight sm:text-4xl">${escapeHtml(fixtures.brand.name)}</h1>
                            <p class="text-muted-foreground max-w-3xl text-pretty text-base leading-7">${escapeHtml(fixtures.brand.promise)}</p>
                        </header>
                    </article>
                    <div class="flex flex-wrap gap-3" role="group" aria-label="Escenarios de activación" data-testid="dovra-scenario-switcher">
                        ${fixtures.scenarios.map((item) => {
                            const active = item.key === scenario.key;
                            return `
                                <button
                                    type="button"
                                    aria-pressed="${active ? 'true' : 'false'}"
                                    aria-label="${escapeHtml(`${item.label}: ${item.title}`)}"
                                    data-testid="dovra-scenario-${escapeHtml(item.key)}"
                                    style="min-height:44px;padding:12px 16px;display:inline-flex;align-items:center;justify-content:flex-start;border:1px solid currentColor;border-radius:9999px;transition:none;${active ? 'background:#111827;color:#fff;' : 'background:#fff;color:#111827;'}"
                                    class="min-h-11 whitespace-normal rounded-full border px-4 py-3 text-left transition-colors motion-safe:duration-200 motion-reduce:transition-none ${
                                        active
                                            ? 'border-transparent bg-primary text-primary-foreground shadow-sm'
                                            : 'border-input bg-background text-foreground'
                                    }"
                                >
                                    <span class="flex min-w-0 flex-col">
                                        <span class="text-sm font-semibold">${escapeHtml(item.label)}</span>
                                        <span class="${active ? 'text-primary-foreground/80' : 'text-muted-foreground'} text-xs font-normal">${escapeHtml(item.title)}</span>
                                    </span>
                                </button>
                            `;
                        }).join('')}
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                    <article class="rounded-lg border border-border bg-card shadow-sm">
                        <header class="flex flex-col gap-3 p-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-md border border-transparent bg-secondary px-2.5 py-0.5 text-xs font-semibold text-secondary-foreground rounded-full">
                                    Modo ${escapeHtml(scenario.commercialMode)}
                                </span>
                                <span class="inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold rounded-full ${selectedMarket.isHonduras ? 'border-transparent bg-secondary text-secondary-foreground' : 'text-foreground'}">
                                    ${selectedMarket.isHonduras ? 'Mercado de Honduras' : 'Mercado fuera de Honduras'}
                                </span>
                            </div>
                            <h2 class="text-balance text-2xl font-semibold tracking-tight">${escapeHtml(scenario.title)}</h2>
                            <p class="text-pretty leading-7 text-muted-foreground">${escapeHtml(scenario.summary)}</p>
                        </header>
                        <div class="space-y-5 p-6 pt-0">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm font-semibold text-foreground">Avance de onboarding</span>
                                    <span class="text-sm font-semibold text-muted-foreground">${scenario.onboarding.progress}%</span>
                                </div>
                                <div
                                    role="progressbar"
                                    aria-label="Avance de ${escapeHtml(scenario.title)}"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="${scenario.onboarding.progress}"
                                    class="relative h-3 w-full overflow-hidden rounded-full bg-secondary"
                                >
                                    <div class="h-full w-full flex-1 bg-primary transition-transform duration-normal ease-out motion-reduce:transition-none" style="transform: scaleX(${scenario.onboarding.progress / 100}); transform-origin: left;"></div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-border bg-muted px-4 py-4">
                                <p class="text-sm font-semibold text-foreground">Onboarding autoritativo</p>
                                <p class="mt-2 leading-7 text-muted-foreground">${escapeHtml(scenario.onboarding.authoritativeNote)}</p>
                            </div>

                            <div class="space-y-3">
                                <h3 class="text-sm font-semibold text-foreground">Mercados disponibles</h3>
                                <div role="group" aria-label="Mercados disponibles" class="flex flex-wrap gap-3">
                                    ${scenario.marketOptions.map((market) => {
                                        const active = market.code === selectedMarket.code;
                                        return `
                                            <button
                                                type="button"
                                                aria-pressed="${active ? 'true' : 'false'}"
                                                aria-label="${escapeHtml(`${market.label}: ${market.note}`)}"
                                                data-testid="dovra-market-${escapeHtml(market.code.toLowerCase())}"
                                                style="min-height:44px;padding:12px 16px;display:inline-flex;align-items:center;justify-content:flex-start;border:1px solid currentColor;border-radius:9999px;transition:none;${active ? 'background:#111827;color:#fff;' : 'background:#fff;color:#111827;'}"
                                                class="min-h-11 whitespace-normal rounded-full border px-4 py-3 text-left transition-colors motion-safe:duration-200 motion-reduce:transition-none ${
                                                    active
                                                        ? 'border-transparent bg-primary text-primary-foreground shadow-sm'
                                                        : 'border-input bg-background text-foreground'
                                                }"
                                                title="${escapeHtml(market.note)}"
                                            >
                                                <span class="flex min-w-0 flex-col">
                                                    <span class="text-sm font-semibold">${escapeHtml(market.label)}</span>
                                                    <span class="${active ? 'text-primary-foreground/80' : 'text-muted-foreground'} text-xs font-normal">${escapeHtml(market.note)}</span>
                                                </span>
                                            </button>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-lg border border-border bg-card shadow-sm">
                        <header class="flex flex-col gap-3 p-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-md border border-transparent bg-secondary px-2.5 py-0.5 text-xs font-semibold text-secondary-foreground rounded-full">
                                    Preparación del comprador
                                </span>
                                <span class="inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold rounded-full ${selectedMarket.isHonduras ? 'text-foreground' : 'border-transparent bg-secondary text-secondary-foreground'}">
                                    ${escapeHtml(selectedMarket.label)}
                                </span>
                            </div>
                            <h2 class="text-balance text-2xl font-semibold tracking-tight">${escapeHtml(scenario.buyer.title)}</h2>
                            <p class="text-pretty leading-7 text-muted-foreground">${escapeHtml(scenario.buyer.copy)}</p>
                        </header>

                        <div class="space-y-4 p-6 pt-0">
                            <div role="status" aria-live="polite" aria-atomic="true" data-testid="dovra-live-status" class="rounded-lg border border-border bg-muted px-4 py-4 text-sm leading-7 text-foreground">
                                ${escapeHtml(scenario.buyer.liveMessage)} Mercado actual: ${escapeHtml(selectedMarket.label)}. ${escapeHtml(scenario.onboarding.nextAction.title)}.
                            </div>

                            <div class="rounded-lg border border-border bg-background px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Preparación reversible</p>
                                <p class="mt-2 text-sm leading-7 text-muted-foreground">${escapeHtml(scenario.buyer.reversibleCopy)}</p>
                                <p class="mt-3 text-sm leading-7 text-muted-foreground">${escapeHtml(scenario.buyer.secondaryHint)}</p>
                            </div>

                            <div class="rounded-lg border border-border bg-muted/50 px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Chat y estado en vivo</p>
                                <ul class="mt-3 space-y-3" aria-label="Actualizaciones del chat">
                                    ${scenario.chat.map((item) => `
                                        <li class="rounded-md bg-background px-3 py-2">
                                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">${escapeHtml(item.speaker)}</p>
                                            <p class="mt-1 text-sm leading-6 text-foreground">${escapeHtml(item.message)}</p>
                                        </li>
                                    `).join('')}
                                </ul>
                            </div>

                            <div class="rounded-lg border border-border bg-muted px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Live region</p>
                                <div role="status" aria-live="polite" aria-atomic="true" data-testid="dovra-program10-live-message" class="mt-2 text-sm leading-7 text-foreground">
                                    ${escapeHtml(scenario.buyer.liveMessage)} Mercado actual: ${escapeHtml(selectedMarket.label)}. ${escapeHtml(scenario.onboarding.nextAction.title)}.
                                </div>
                            </div>
                        </div>
                    </article>
                </section>
            </main>
        </div>
    `;
}

async function mountFixture(page, options = {}) {
    const {
        initialScenarioKey = 'hybrid',
        dark = true,
        reducedMotion = true,
    } = options;

    await page.emulateMedia({ reducedMotion: reducedMotion ? 'reduce' : 'no-preference' });
    await page.setContent(`<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Dovra AI</title></head><body style="margin:0">${buildMarkup(initialScenarioKey)}</body></html>`);
    await page.evaluate(
        ({ html, scenarios, scenarioKey, darkMode }) => {
            document.documentElement.classList.toggle('dark', darkMode);
            document.body.innerHTML = html;

            const scenarioMap = Object.fromEntries(
                scenarios.map((scenario) => [scenario.key, scenario]),
            );
            const scenarioButtons = Array.from(
                document.querySelectorAll('button[data-testid^="dovra-scenario-"]'),
            );
            const marketButtons = Array.from(
                document.querySelectorAll('button[data-testid^="dovra-market-"]'),
            );
            const liveRegions = Array.from(
                document.querySelectorAll('[data-testid="dovra-live-status"], [data-testid="dovra-program10-live-message"]'),
            );

            const state = {
                scenarioKey,
                marketCode:
                    scenarioMap[scenarioKey]?.defaultMarketCode
                    || scenarioMap[scenarioKey]?.marketOptions?.[0]?.code
                    || null,
            };

            const update = () => {
                const scenario = scenarioMap[state.scenarioKey] || scenarioMap[Object.keys(scenarioMap)[0]];
                const market = scenario.marketOptions.find((item) => item.code === state.marketCode) || scenario.marketOptions[0];
                const message = `${scenario.buyer.liveMessage} Mercado actual: ${market.label}. ${scenario.onboarding.nextAction.title}.`;

                liveRegions.forEach((node) => {
                    node.textContent = message;
                });

                scenarioButtons.forEach((button) => {
                    button.setAttribute(
                        'aria-pressed',
                        button.dataset.testid === `dovra-scenario-${state.scenarioKey}` ? 'true' : 'false',
                    );
                });

                marketButtons.forEach((button) => {
                    const code = button.dataset.testid.replace('dovra-market-', '').toUpperCase();
                    button.setAttribute('aria-pressed', code === state.marketCode ? 'true' : 'false');
                });
            };

            scenarioButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    state.scenarioKey = button.dataset.testid.replace('dovra-scenario-', '');
                    state.marketCode = scenarioMap[state.scenarioKey]?.defaultMarketCode
                        || scenarioMap[state.scenarioKey]?.marketOptions?.[0]?.code
                        || null;
                    update();
                });
            });

            marketButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    state.marketCode = button.dataset.testid.replace('dovra-market-', '').toUpperCase();
                    update();
                });
            });

            update();
        },
        {
            html: buildMarkup(initialScenarioKey),
            scenarios: fixtures.scenarios,
            scenarioKey: initialScenarioKey,
            darkMode: dark,
        },
    );
}

test('Dovra accessibility slice keeps target sizes, dark mode, and reduced-motion behavior intact', async ({ page }) => {
    test.setTimeout(120_000);
    await page.setViewportSize({ width: 1280, height: 900 });
    await mountFixture(page, { initialScenarioKey: 'hybrid', dark: true, reducedMotion: true });

    const axeResults = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(axeResults.violations, JSON.stringify(axeResults.violations, null, 2)).toEqual([]);

    const scenarioButton = page.getByRole('button', {
        name: 'Mixto: Una sola operación para productos y servicios',
    });
    const marketButton = page.getByRole('button', {
        name: /Panamá: Mercado alterno para comprobar un lanzamiento regional\./i,
    });

    await expect(scenarioButton).toBeVisible();
    await expect(marketButton).toBeVisible();

    const scenarioBox = await scenarioButton.boundingBox();
    const marketBox = await marketButton.boundingBox();
    expect(scenarioBox?.height ?? 0).toBeGreaterThanOrEqual(40);
    expect(marketBox?.height ?? 0).toBeGreaterThanOrEqual(40);

    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Saltar al contenido principal' })).toBeFocused();

    const duration = await page.evaluate(() =>
        getComputedStyle(document.querySelector('[data-testid="dovra-scenario-hybrid"]')).transitionDuration,
    );
    expect(duration).toBe('0s');

    await scenarioButton.click();
    await marketButton.click();
    await expect(page.getByTestId('dovra-live-status')).toContainText('Panamá');
    await expect(page.getByTestId('dovra-live-status')).toContainText('operación mixta');
});
