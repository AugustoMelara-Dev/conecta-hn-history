import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import fixtures from '../../resources/stories/dovra-program-10.fixtures.json' assert { type: 'json' };

test.use({ trace: 'off' });

// HTML harness evidence only: routes/controllers are outside the parallel Task 5 write-set.
const HARNESS_CONTRACT = {
    evidenceMode: 'html-harness',
    applicationSurface: false,
    routeExposure: 'not-exposed',
};

async function assertHtmlHarnessEvidence(page) {
    expect(fixtures.evidence).toMatchObject({
        browserMode: 'html-harness',
        applicationSurface: false,
        routeExposure: 'not-exposed',
    });
    expect(HARNESS_CONTRACT).toMatchObject({
        evidenceMode: 'html-harness',
        applicationSurface: false,
        routeExposure: 'not-exposed',
    });

    await expect(page.locator('meta[name="dovra-evidence-mode"]')).toHaveAttribute('content', 'html-harness');
    await expect(page.locator('meta[name="dovra-application-surface"]')).toHaveAttribute('content', 'false');
    await expect(page.locator('[data-dovra-evidence-mode="html-harness"][data-dovra-application-surface="false"]')).toHaveCount(1);
    await expect(page.getByTestId('dovra-evidence-contract')).toContainText('HTML harness evidence only');
}

const escapeHtml = (value: string): string =>
    String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');

function buildMarkup(initialScenarioKey = 'products'): string {
    const scenario = fixtures.scenarios.find((item) => item.key === initialScenarioKey) || fixtures.scenarios[0];
    const selectedMarket = scenario.marketOptions.find((market) => market.code === scenario.defaultMarketCode) || scenario.marketOptions[0];

    return `
        <div
            class="min-h-screen bg-background text-foreground"
            data-dovra-evidence-mode="${HARNESS_CONTRACT.evidenceMode}"
            data-dovra-application-surface="${String(HARNESS_CONTRACT.applicationSurface)}"
            data-dovra-route-exposure="${HARNESS_CONTRACT.routeExposure}"
        >
            <a href="#main-content" style="position:fixed;left:16px;top:16px;z-index:100;padding:8px 16px;border:1px solid #111827;border-radius:8px;background:#111827;color:#fff;font-size:14px;font-weight:600;text-decoration:none;">
                Saltar al contenido principal
            </a>

            <main id="main-content" tabindex="-1" style="max-width:1152px;margin:0 auto;padding:24px 16px;outline:none;display:flex;flex-direction:column;gap:24px;">
                <section class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                        <span class="inline-flex items-center gap-2 rounded-full border border-border bg-muted px-3 py-2">Programa 10</span>
                        <span class="inline-flex items-center gap-2 rounded-full border border-border bg-muted px-3 py-2">UX / accesibilidad</span>
                    </div>

                    <article class="rounded-lg border border-border bg-card shadow-sm">
                        <header class="flex flex-col gap-4 p-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-md border border-transparent bg-secondary px-2.5 py-0.5 text-xs font-semibold text-secondary-foreground rounded-full">
                                    ${escapeHtml(fixtures.brand.category)}
                                </span>
                                <span class="inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold text-foreground rounded-full">
                                    HTML harness evidence only; not application surface
                                </span>
                            </div>
                            <h1 class="text-balance text-3xl font-semibold tracking-tight sm:text-4xl">${escapeHtml(fixtures.brand.name)}</h1>
                            <p class="text-muted-foreground max-w-3xl text-pretty text-base leading-7">${escapeHtml(fixtures.brand.promise)}</p>
                        </header>
                        <div class="grid gap-3 p-6 pt-0 sm:grid-cols-3">
                            <div class="rounded-lg border border-border bg-muted px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Onboarding</p>
                                <p class="mt-2 text-sm leading-7 text-foreground">Autoritativo, reversible y visible.</p>
                            </div>
                            <div class="rounded-lg border border-border bg-muted px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Comprador</p>
                                <p class="mt-2 text-sm leading-7 text-foreground">Preparación explícita antes de actuar.</p>
                            </div>
                            <div class="rounded-lg border border-border bg-muted px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Accesibilidad</p>
                                <p class="mt-2 text-sm leading-7 text-foreground">Teclado, foco, live regions y reflow.</p>
                            </div>
                        </div>
                    </article>

                    <div role="status" data-testid="dovra-evidence-contract" class="rounded-lg border border-border bg-muted/60 px-4 py-3 text-sm leading-7 text-foreground">
                        HTML harness evidence only; no es una superficie de aplicación navegable. El slice prueba la interacción del markup, no la integración de rutas/controller.
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
                    <div class="min-w-0 space-y-6">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-foreground">Escenarios de activación</p>
                                    <p class="text-sm leading-6 text-muted-foreground">${escapeHtml(scenario.summary)}</p>
                                </div>
                                <span class="inline-flex items-center rounded-md border border-transparent bg-secondary px-2.5 py-0.5 text-xs font-semibold text-secondary-foreground rounded-full">
                                    ${escapeHtml(scenario.buyer.status)}
                                </span>
                            </div>

                            <div role="group" aria-label="Escenarios de activación" class="flex flex-wrap gap-3" data-testid="dovra-scenario-switcher">
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
                        </div>

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

                            <div class="flex flex-col gap-5 p-6 pt-0">
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

                                <div class="rounded-lg border border-border bg-muted/60 px-4 py-4">
                                    <p class="text-sm font-semibold text-foreground">Fuente esperada del onboarding</p>
                                    <p class="mt-2 leading-7 text-muted-foreground">${escapeHtml(scenario.onboarding.authoritativeNote)}</p>
                                </div>

                                <div class="space-y-3">
                                    <div class="flex items-center justify-between gap-4">
                                        <h3 class="text-sm font-semibold text-foreground">Hitos visibles</h3>
                                        <span class="text-xs font-medium uppercase tracking-[0.18em] text-muted-foreground">${escapeHtml(scenario.onboarding.nextAction.title)}</span>
                                    </div>

                                    <ul class="grid gap-3">
                                        ${scenario.onboarding.milestones.map((milestone) => `
                                            <li class="rounded-lg border border-border bg-background p-4">
                                                <div class="flex items-start justify-between gap-4">
                                                    <div class="min-w-0 space-y-1">
                                                        <p class="font-semibold text-foreground">${escapeHtml(milestone.title)}</p>
                                                        <p class="text-sm leading-6 text-muted-foreground">${escapeHtml(milestone.copy)}</p>
                                                    </div>
                                                    <span class="inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold rounded-full ${
                                                        milestone.completed
                                                            ? 'border-transparent bg-secondary text-secondary-foreground'
                                                            : milestone.status === 'blocked_by_plan'
                                                                ? 'border-transparent bg-destructive text-destructive-foreground'
                                                                : 'text-foreground'
                                                    }">
                                                        ${milestone.completed ? 'Listo' : milestone.status === 'blocked_by_plan' ? 'Bloqueado' : 'Pendiente'}
                                                    </span>
                                                </div>
                                            </li>
                                        `).join('')}
                                    </ul>
                                </div>

                                <div class="space-y-3">
                                    <h3 class="text-sm font-semibold text-foreground">Mercado seleccionado</h3>
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
                    </div>

                    <div class="min-w-0 space-y-6">
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

                            <div class="flex flex-col gap-4 p-6 pt-0">
                                <div role="status" aria-live="polite" aria-atomic="true" data-testid="dovra-live-status" class="rounded-lg border border-border bg-muted px-4 py-4 text-sm leading-7 text-foreground">
                                    ${escapeHtml(scenario.buyer.liveMessage)} Mercado actual: ${escapeHtml(selectedMarket.label)}. ${escapeHtml(scenario.onboarding.nextAction.title)}.
                                </div>

                                <div class="rounded-lg border border-border bg-background px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Preparación reversible</p>
                                    <p class="mt-2 text-sm leading-7 text-muted-foreground">${escapeHtml(scenario.buyer.reversibleCopy)}</p>
                                    <p class="mt-3 text-sm leading-7 text-muted-foreground">${escapeHtml(scenario.buyer.secondaryHint)}</p>
                                </div>

                                <ul class="grid gap-2 text-sm leading-7 text-muted-foreground">
                                    <li class="rounded-md bg-muted px-3 py-2"><span class="font-semibold text-foreground">Estado:</span> ${escapeHtml(scenario.buyer.status)}</li>
                                    <li class="rounded-md bg-muted px-3 py-2"><span class="font-semibold text-foreground">Mercado:</span> ${escapeHtml(selectedMarket.note)}</li>
                                </ul>

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
                            </div>

                            <footer class="flex flex-col gap-3 px-6 pb-6 sm:flex-row sm:items-center sm:justify-between">
                                <a href="${escapeHtml(scenario.buyer.ctaHref)}" style="min-height:44px;padding:12px 16px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #111827;border-radius:8px;background:#111827;color:#fff;text-decoration:none;font-size:14px;font-weight:600;">
                                    ${escapeHtml(scenario.buyer.ctaLabel)}
                                </a>
                                <button type="button" aria-label="${escapeHtml(scenario.buyer.secondaryLabel)}" style="min-height:44px;padding:12px 16px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#111827;font-size:14px;font-weight:600;transition:none;">
                                    ${escapeHtml(scenario.buyer.secondaryLabel)}
                                </button>
                            </footer>
                        </article>

                        <article class="rounded-lg border border-border bg-card shadow-sm">
                            <header class="flex flex-col gap-3 p-6">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-md border border-transparent bg-secondary px-2.5 py-0.5 text-xs font-semibold text-secondary-foreground rounded-full">Estado en vivo</span>
                                    <span class="inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold rounded-full ${selectedMarket.isHonduras ? 'border-transparent bg-secondary text-secondary-foreground' : 'text-foreground'}">
                                        ${escapeHtml(selectedMarket.label)}
                                    </span>
                                </div>
                                <h2 class="text-xl font-semibold tracking-tight">Lista de lectura para teclado y zoom</h2>
                                <p class="text-pretty leading-7 text-muted-foreground">El contenido no depende de hover y se refluye en una sola columna en pantallas estrechas.</p>
                            </header>
                            <div class="space-y-4 p-6 pt-0">
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-sm font-semibold text-foreground">Progreso actual</span>
                                        <span class="text-sm font-semibold text-muted-foreground">${scenario.onboarding.progress}%</span>
                                    </div>
                                    <div
                                        role="progressbar"
                                        aria-label="Progreso actual de lectura"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-valuenow="${scenario.onboarding.progress}"
                                        class="relative h-3 w-full overflow-hidden rounded-full bg-secondary"
                                    >
                                        <div class="h-full w-full flex-1 bg-primary transition-transform duration-normal ease-out motion-reduce:transition-none" style="transform: scaleX(${scenario.onboarding.progress / 100}); transform-origin: left;"></div>
                                    </div>
                                </div>

                                <div class="rounded-lg border border-border bg-muted px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Live region</p>
                                    <div role="status" aria-live="polite" aria-atomic="true" data-testid="dovra-program10-live-message" class="mt-2 text-sm leading-7 text-foreground">
                                        ${escapeHtml(scenario.buyer.liveMessage)} Mercado actual: ${escapeHtml(selectedMarket.label)}. ${escapeHtml(scenario.onboarding.nextAction.title)}.
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <a href="${escapeHtml(scenario.onboarding.nextAction.href)}" style="min-height:44px;padding:12px 16px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #111827;border-radius:8px;background:#111827;color:#fff;text-decoration:none;font-size:14px;font-weight:600;">
                                        ${escapeHtml(scenario.onboarding.nextAction.title)}
                                    </a>
                                    <button type="button" style="min-height:44px;padding:12px 16px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#111827;font-size:14px;font-weight:600;transition:none;">
                                        Ver el siguiente paso
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </main>
        </div>
    `;
}

async function mountFixture(page, initialScenarioKey = 'products') {
    await page.setContent(`<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="dovra-evidence-mode" content="html-harness"><meta name="dovra-application-surface" content="false"><title>Dovra AI evidence harness</title></head><body style="margin:0">${buildMarkup(initialScenarioKey)}</body></html>`);
    await page.evaluate(
        ({ markup, scenarios, scenarioKey }) => {
            document.body.innerHTML = markup;

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
            markup: buildMarkup(initialScenarioKey),
            scenarios: fixtures.scenarios,
            scenarioKey: initialScenarioKey,
        },
    );

    await assertHtmlHarnessEvidence(page);
}

test('HTML harness evidence only (not application surface): launch critical automated checks', async ({ page }) => {
    test.setTimeout(120_000);
    await page.setViewportSize({ width: 360, height: 800 });
    await mountFixture(page, 'products');

    const productsDefault = fixtures.scenarios.find((scenario) => scenario.key === 'products');
    const productsMarket = productsDefault?.marketOptions.find((market) => market.code === productsDefault.defaultMarketCode);
    expect(productsMarket?.isHonduras).toBe(false);
    await expect(page.getByTestId('dovra-market-gt')).toHaveAttribute('aria-pressed', 'true');

    const axeResults = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    // Automated axe output is evidence only; it does not establish WCAG conformance.
    expect(axeResults.violations, JSON.stringify(axeResults.violations, null, 2)).toEqual([]);

    await page.keyboard.press('Tab');
    const skipLink = page.getByRole('link', { name: 'Saltar al contenido principal' });
    await expect(skipLink).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();

    const serviceScenario = page.getByRole('button', {
        name: 'Servicios: Agenda confiable y reversible',
    });
    await serviceScenario.click();
    await expect(page.getByTestId('dovra-live-status')).toContainText('El Salvador');
    await expect(page.getByTestId('dovra-live-status')).not.toContainText('Mercado actual: Honduras');
    await expect(page.getByTestId('dovra-live-status')).toContainText('Abrir primera cita reservable');

    const hasHorizontalOverflow = await page.evaluate(() =>
        document.documentElement.scrollWidth > document.documentElement.clientWidth,
    );
    expect(hasHorizontalOverflow).toBe(false);

    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });

    const reflow = await page.evaluate(() => {
        const viewportWidth = document.documentElement.clientWidth;
        const offenders = Array.from(document.querySelectorAll('body *'))
            .filter((element) => {
                const rect = element.getBoundingClientRect();
                return rect.right > viewportWidth + 1 || rect.left < -1;
            })
            .map((element) => ({
                tag: element.tagName,
                text: String(element.textContent || '').trim().slice(0, 80),
                width: Math.round(element.getBoundingClientRect().width),
            }))
            .filter((entry) => entry.text.length > 0)
            .slice(0, 12);

        return {
            overflow: document.documentElement.scrollWidth > viewportWidth,
            offenders,
        };
    });

    expect(reflow, JSON.stringify(reflow, null, 2)).toMatchObject({
        overflow: false,
    });
    expect(reflow.offenders).toEqual([]);
});
