import { performance } from 'node:perf_hooks';

const baseUrl = (process.env.PERF_BASE_URL || process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const tenant = process.env.PERF_TENANT_SLUG || process.env.E2E_PRODUCT_TENANT_SLUG || 'demo-ai-guide-hn';
const samples = Number.parseInt(process.env.PERF_SAMPLES || '30', 10);
const concurrency = Number.parseInt(process.env.PERF_CONCURRENCY || '4', 10);
const bearer = process.env.PERF_BEARER_TOKEN || '';
const cookie = process.env.PERF_AUTH_COOKIE || '';

const commonHeaders = {
    accept: 'application/json, text/html;q=0.9',
    'user-agent': 'conecta-p95-smoke/1.0',
};

if (bearer) {
    commonHeaders.authorization = `Bearer ${bearer}`;
}

if (cookie) {
    commonHeaders.cookie = cookie;
}

const surfaces = [
    { surface: 'dashboard', method: 'GET', path: `/app/${tenant}` },
    { surface: 'reports', method: 'GET', path: `/app/${tenant}/growth-center` },
    { surface: 'campaigns', method: 'GET', path: `/app/${tenant}/campaigns` },
    { surface: 'business_copilot', method: 'GET', path: `/app/${tenant}/business-copilot` },
    {
        surface: 'directory_chat',
        method: 'POST',
        path: '/api/v1/directory/guide/chat',
        body: {
            query: 'Que puedo comprar con 100 HNL cerca de mi?',
            budget: 100,
            city: 'tegucigalpa',
            limit: 5,
        },
    },
    {
        surface: 'business_copilot_chat',
        method: 'POST',
        path: '/api/v1/ai/business/chat',
        body: {
            query: 'Resume ventas, productos y oportunidades principales.',
            limit: 5,
        },
    },
];

function percentile(values, ratio) {
    if (values.length === 0) {
        return 0;
    }

    const sorted = [...values].sort((a, b) => a - b);
    const index = Math.max(0, Math.ceil(sorted.length * ratio) - 1);

    return Math.round(sorted[index]);
}

async function measure(surface) {
    const started = performance.now();
    let status = 0;
    let ok = false;
    let error = null;

    try {
        const response = await fetch(`${baseUrl}${surface.path}`, {
            method: surface.method,
            headers: {
                ...commonHeaders,
                ...(surface.body ? { 'content-type': 'application/json' } : {}),
            },
            body: surface.body ? JSON.stringify(surface.body) : undefined,
            redirect: 'manual',
        });

        status = response.status;
        ok = response.status < 400 || response.status === 302;
        await response.arrayBuffer();
    } catch (exception) {
        error = exception instanceof Error ? exception.message : String(exception);
    }

    return {
        duration_ms: Math.round(performance.now() - started),
        status,
        ok,
        error,
    };
}

async function pool(items, workerCount, worker) {
    const results = [];
    let next = 0;

    await Promise.all(Array.from({ length: workerCount }, async () => {
        while (next < items.length) {
            const index = next;
            next += 1;
            results[index] = await worker(items[index], index);
        }
    }));

    return results;
}

const startedAt = new Date();
const report = {
    generated_at: startedAt.toISOString(),
    base_url: baseUrl,
    tenant,
    samples_per_surface: samples,
    concurrency,
    auth_mode: bearer ? 'bearer' : cookie ? 'cookie' : 'none',
    surfaces: [],
};

for (const surface of surfaces) {
    const attempts = Array.from({ length: samples }, () => surface);
    const results = await pool(attempts, concurrency, measure);
    const successful = results.filter((result) => result.ok);
    const durations = successful.map((result) => result.duration_ms);

    report.surfaces.push({
        surface: surface.surface,
        method: surface.method,
        path: surface.path,
        count: results.length,
        success_count: successful.length,
        error_count: results.length - successful.length,
        p50_ms: percentile(durations, 0.50),
        p95_ms: percentile(durations, 0.95),
        p99_ms: percentile(durations, 0.99),
        max_ms: durations.length > 0 ? Math.max(...durations) : 0,
        statuses: results.reduce((acc, result) => {
            const key = String(result.status || 'network_error');
            acc[key] = (acc[key] || 0) + 1;

            return acc;
        }, {}),
    });
}

console.log(JSON.stringify(report, null, 2));
