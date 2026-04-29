import { performance } from 'node:perf_hooks';

const baseUrl = (process.env.PERF_BASE_URL || process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const tenant = process.env.PERF_TENANT_SLUG || process.env.E2E_PRODUCT_TENANT_SLUG || 'demo-ai-guide-hn';
const samples = Number.parseInt(process.env.PERF_SAMPLES || '30', 10);
const concurrency = Number.parseInt(process.env.PERF_CONCURRENCY || '4', 10);
const bearer = process.env.PERF_BEARER_TOKEN || '';
const cookie = process.env.PERF_AUTH_COOKIE || '';
const requireAuth = !/^(0|false|no|off)$/i.test(process.env.PERF_REQUIRE_AUTH || 'true');
const failOnErrors = !/^(0|false|no|off)$/i.test(process.env.PERF_FAIL_ON_ERRORS || 'true');

const commonHeaders = {
    accept: 'application/json, text/html;q=0.9',
    'user-agent': 'vito-p95-smoke/1.0',
};

if (bearer) {
    commonHeaders.authorization = `Bearer ${bearer}`;
}

if (cookie) {
    commonHeaders.cookie = cookie;
    const xsrfToken = cookie
        .split(';')
        .map((part) => part.trim())
        .find((part) => part.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    if (xsrfToken) {
        commonHeaders['x-xsrf-token'] = decodeURIComponent(xsrfToken);
    }
}

const surfaces = [
    { surface: 'dashboard', method: 'GET', path: `/app/${tenant}` },
    { surface: 'reports', method: 'GET', path: `/app/${tenant}/growth-center` },
    { surface: 'campaigns', method: 'GET', path: `/app/${tenant}/campaigns` },
    { surface: 'business_copilot', method: 'GET', path: `/app/${tenant}/copilot` },
    {
        surface: 'directory_chat',
        method: 'POST',
        path: '/api/v1/directory/guide/chat',
        body: {
            query: 'Que puedo comprar con 100 HNL cerca de mi?',
            budget_amount: 100,
            currency: 'HNL',
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
    let location = null;

    try {
        const response = await fetch(`${baseUrl}${surface.path}`, {
            method: surface.method,
            headers: {
                ...commonHeaders,
                ...(surface.surface === 'business_copilot_chat' ? { 'x-tenant-id': tenant } : {}),
                ...(surface.body ? { 'content-type': 'application/json' } : {}),
            },
            body: surface.body ? JSON.stringify(surface.body) : undefined,
            redirect: 'manual',
        });

        status = response.status;
        location = response.headers.get('location');
        const redirectsToLogin = response.status >= 300
            && response.status < 400
            && typeof location === 'string'
            && /\/(?:app\/)?login(?:\?|$|\/)/i.test(location);

        ok = response.status >= 200 && response.status < 400 && !redirectsToLogin;

        if (redirectsToLogin) {
            error = 'auth_redirect';
        } else if (response.status === 401 || response.status === 403) {
            error = 'auth_rejected';
        } else if (response.status >= 400) {
            error = `http_${response.status}`;
        }

        await response.arrayBuffer();
    } catch (exception) {
        error = exception instanceof Error ? exception.message : String(exception);
    }

    return {
        duration_ms: Math.round(performance.now() - started),
        status,
        ok,
        error,
        location,
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
    auth_required: requireAuth,
    surfaces: [],
    failures: [],
};

if (requireAuth && !bearer && !cookie) {
    report.failures.push({
        scope: 'auth',
        error: 'PERF_REQUIRE_AUTH=true but neither PERF_AUTH_COOKIE nor PERF_BEARER_TOKEN was provided.',
    });
}

for (const surface of surfaces) {
    const attempts = Array.from({ length: samples }, () => surface);
    const results = await pool(attempts, concurrency, measure);
    const successful = results.filter((result) => result.ok);
    const durations = successful.map((result) => result.duration_ms);

    const statusCounts = results.reduce((acc, result) => {
        const key = String(result.status || 'network_error');
        acc[key] = (acc[key] || 0) + 1;

        return acc;
    }, {});
    const errorCounts = results.reduce((acc, result) => {
        if (result.error) {
            acc[result.error] = (acc[result.error] || 0) + 1;
        }

        return acc;
    }, {});
    const surfaceReport = {
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
        statuses: statusCounts,
        errors: errorCounts,
    };

    report.surfaces.push(surfaceReport);

    if (surfaceReport.error_count > 0) {
        report.failures.push({
            scope: surface.surface,
            path: surface.path,
            success_count: surfaceReport.success_count,
            error_count: surfaceReport.error_count,
            statuses: statusCounts,
            errors: errorCounts,
        });
    }
}

console.log(JSON.stringify(report, null, 2));

if ((requireAuth && report.auth_mode === 'none') || (failOnErrors && report.failures.length > 0)) {
    process.exit(1);
}
