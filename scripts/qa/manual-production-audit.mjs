import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';

const baseURL = (process.env.PLAYWRIGHT_BASE_URL || 'https://vitobusinessos.vercel.app').replace(/\/$/, '');
const stamp = new Date().toISOString().replace(/[:.]/g, '-');
const outDir = path.resolve('artifacts', 'manual-audit', stamp);

const checks = [
  { id: 'home', route: '/', marker: null },
  { id: 'explore', route: '/explore', marker: { type: 'selector', value: '#explore-search' } },
  { id: 'register-business', route: '/registrar-negocio', marker: { type: 'selector', value: '#business_name' } },
  { id: 'login', route: '/login', marker: { type: 'selector', value: '#email' } },
  { id: 'admin-login', route: '/admin/login', marker: { type: 'text', value: 'Panel de superadministraci' } },
  { id: 'microsite-restaurant', route: '/t/casa-brasa-bistro', marker: { type: 'selector', value: '[data-testid="tenant-microsite"]' } },
  { id: 'microsite-legal', route: '/t/bufete-lex-hn', marker: { type: 'selector', value: '[data-testid="tenant-microsite"]' } },
  { id: 'booking-entry', route: '/t/barberia-norte-pro/book', marker: { type: 'selector', value: '[data-testid="booking-wizard"]' } },
  { id: 'not-found', route: '/__manual-audit-not-found-404', marker: { type: 'text', value: '404' } },
];

const defaultFlowRoute = '/t/casa-brasa-bistro';

async function ensureDir(dir) {
  await fs.mkdir(dir, { recursive: true });
}

function nowIso() {
  return new Date().toISOString();
}

function cleanText(value) {
  return String(value || '')
    .replace(/\u001b\[[0-9;]*m/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

function toAbsUrl(route) {
  return `${baseURL}${route}`;
}

async function waitForMarker(page, marker) {
  if (!marker) return { ok: true, details: 'No marker required' };

  if (marker.type === 'selector') {
    const node = page.locator(marker.value).first();
    await node.waitFor({ state: 'visible', timeout: 15000 });
    return { ok: true, details: `Visible selector: ${marker.value}` };
  }

  if (marker.type === 'text') {
    const node = page.getByText(new RegExp(marker.value, 'i')).first();
    await node.waitFor({ state: 'visible', timeout: 15000 });
    return { ok: true, details: `Visible text: ${marker.value}` };
  }

  return { ok: false, details: `Unsupported marker type: ${marker.type}` };
}

async function runFlowAudit(page) {
  const discovered = await discoverExploreRoutes(page);
  const flowRoute = discovered.micrositeRoute || defaultFlowRoute;

  const result = {
    id: 'checkout-flow',
    route: flowRoute,
    startedAt: nowIso(),
    endedAt: null,
    status: null,
    ok: false,
    marker: 'N/A',
    finalUrl: null,
    screenshot: null,
    notes: '',
  };

  try {
    const url = toAbsUrl(flowRoute);
    const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    result.status = response?.status() ?? null;

    await page.locator('[data-testid="tenant-microsite"]').first().waitFor({ state: 'visible', timeout: 15000 });
    const addBtn = page.getByRole('button', { name: /^Agregar$/ }).first();
    await addBtn.waitFor({ state: 'visible', timeout: 15000 });
    await addBtn.click();

    const cartBtn = page.locator('button.fixed.bottom-6.right-6');
    await cartBtn.waitFor({ state: 'visible', timeout: 15000 });
    await cartBtn.click();

    await page.getByText(/Mi Carrito/i).first().waitFor({ state: 'visible', timeout: 10000 });

    const shot = path.join(outDir, 'checkout-flow.png');
    await page.screenshot({ path: shot, fullPage: true });

    result.ok = Boolean(result.status && result.status < 500);
    result.marker = 'Visible: carrito abierto';
    result.finalUrl = page.url();
    result.screenshot = shot;
    result.notes = 'Checkout abierto hasta carrito; no se envio orden para evitar side effects en produccion.';
    } catch (error) {
      const shot = path.join(outDir, 'checkout-flow-failure.png');
      await page.screenshot({ path: shot, fullPage: true }).catch(() => null);
      result.ok = false;
      result.finalUrl = page.url();
      result.screenshot = shot;
      result.notes = cleanText(error?.message || error);
    } finally {
      result.endedAt = nowIso();
    }

  return result;
}

async function discoverExploreRoutes(page) {
  try {
    await page.goto(toAbsUrl('/explore'), { waitUntil: 'domcontentloaded', timeout: 30000 });

    const payloadRaw = await page.locator('#app').getAttribute('data-page');
    if (!payloadRaw) {
      return { micrositeRoute: null };
    }

    const payload = JSON.parse(payloadRaw);
    const featured = payload?.props?.featuredTenants ?? [];
    const first = Array.isArray(featured) && featured.length > 0 ? featured[0] : null;
    const slug = first?.slug ? String(first.slug).trim() : null;

    if (!slug) {
      return { micrositeRoute: null };
    }

    return { micrositeRoute: `/t/${slug}` };
  } catch {
    return { micrositeRoute: null };
  }
}

function toMarkdown(base, records, consoleEvents) {
  const lines = [];
  lines.push('# Auditoria manual guiada en produccion');
  lines.push('');
  lines.push(`- Fecha UTC: ${nowIso()}`);
  lines.push(`- Base URL: ${base}`);
  lines.push(`- Total verificaciones: ${records.length}`);
  lines.push(`- Exitosas: ${records.filter((r) => r.ok).length}`);
  lines.push(`- Con hallazgo: ${records.filter((r) => !r.ok).length}`);
  lines.push('');
  lines.push('| Check | Ruta | HTTP | Estado | Evidencia | Notas |');
  lines.push('| --- | --- | --- | --- | --- | --- |');

  for (const record of records) {
    const statusText = record.status ?? 'n/a';
    const okText = record.ok ? 'OK' : 'FALLA';
    const shot = record.screenshot ? path.relative(process.cwd(), record.screenshot).replaceAll('\\', '/') : 'n/a';
    const note = cleanText(record.notes || record.marker || '').replace(/\|/g, '/');
    lines.push(`| ${record.id} | ${record.route} | ${statusText} | ${okText} | ${shot} | ${note} |`);
  }

  lines.push('');
  lines.push('## Eventos de consola (warn/error)');
  lines.push('');
  if (consoleEvents.length === 0) {
    lines.push('- Sin eventos warn/error durante la corrida.');
  } else {
    for (const event of consoleEvents) {
      lines.push(`- [${event.type}] ${event.url} :: ${cleanText(event.text)}`);
    }
  }

  return `${lines.join('\n')}\n`;
}

async function main() {
  await ensureDir(outDir);

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    locale: 'es-HN',
  });
  const page = await context.newPage();
  const consoleEvents = [];

  page.on('console', (message) => {
    const type = message.type();
    if (type === 'warning' || type === 'error') {
      consoleEvents.push({
        type,
        text: message.text(),
        url: page.url(),
      });
    }
  });

  const records = [];
  const discovered = await discoverExploreRoutes(page);
  const dynamicChecks = [...checks];

  if (discovered.micrositeRoute) {
    dynamicChecks.push({
      id: 'microsite-discovered',
      route: discovered.micrositeRoute,
      marker: { type: 'selector', value: '[data-testid="tenant-microsite"]' },
    });
  }

  for (const check of dynamicChecks) {
    const row = {
      id: check.id,
      route: check.route,
      startedAt: nowIso(),
      endedAt: null,
      status: null,
      ok: false,
      marker: '',
      finalUrl: null,
      screenshot: null,
      notes: '',
    };

    try {
      const response = await page.goto(toAbsUrl(check.route), { waitUntil: 'domcontentloaded', timeout: 30000 });
      row.status = response?.status() ?? null;

      const markerResult = await waitForMarker(page, check.marker);
      row.marker = markerResult.details;

      const screenshotPath = path.join(outDir, `${check.id}.png`);
      await page.screenshot({ path: screenshotPath, fullPage: true });

      row.ok = Boolean(row.status && row.status < 500 && markerResult.ok);
      row.finalUrl = page.url();
      row.screenshot = screenshotPath;
      row.notes = markerResult.ok ? 'Ruta validada con evidencia visual.' : markerResult.details;
    } catch (error) {
      const screenshotPath = path.join(outDir, `${check.id}-failure.png`);
      await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => null);
      row.ok = false;
      row.finalUrl = page.url();
      row.screenshot = screenshotPath;
      row.notes = cleanText(error?.message || error);
    } finally {
      row.endedAt = nowIso();
      records.push(row);
    }
  }

  records.push(await runFlowAudit(page));

  await browser.close();

  const report = toMarkdown(baseURL, records, consoleEvents);
  const reportPath = path.join(outDir, 'report.md');
  await fs.writeFile(reportPath, report, 'utf8');

  process.stdout.write(`Manual production audit completed.\nReport: ${reportPath}\n`);
}

main().catch((error) => {
  process.stderr.write(`${String(error?.stack || error)}\n`);
  process.exit(1);
});
