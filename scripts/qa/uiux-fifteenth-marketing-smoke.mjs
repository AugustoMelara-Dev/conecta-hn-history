import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8120 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-marketing-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/marketing-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))
const sqlitePath = process.env.UIUX_SMOKE_DB || path.join(os.tmpdir(), `${runId}.sqlite`)
const configCachePath = path.join(os.tmpdir(), `${runId}-config.php`)
const smokeEnv = {
    ...process.env,
    APP_ENV: "testing",
    APP_URL: baseUrl,
    APP_CONFIG_CACHE: configCachePath,
    DB_CONNECTION: "sqlite",
    DB_DATABASE: sqlitePath,
    CACHE_STORE: "array",
    SESSION_DRIVER: "file",
    QUEUE_CONNECTION: "sync",
    MAIL_MAILER: "array",
    BROADCAST_CONNECTION: "log",
}

fs.mkdirSync(outDir, { recursive: true })

const helperPath = path.join(os.tmpdir(), `conecta-uiux-fifteenth-marketing-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\MenuSection;
use App\\Models\\Tenant;
use App\\Models\\User;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\Schema;
use Illuminate\\Support\\Str;

$root = $argv[1];
$mode = $argv[2];
$runId = $argv[3];

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
$kernel->bootstrap();

function onlyExisting(string $table, array $data): array {
    return array_filter(
        $data,
        fn ($value, string $key): bool => Schema::hasColumn($table, $key),
        ARRAY_FILTER_USE_BOTH
    );
}

function ensureTenantMembership(User $user, Tenant $tenant): void {
    if (! Schema::hasTable('tenant_memberships')) {
        return;
    }

    DB::table('tenant_memberships')->updateOrInsert(
        onlyExisting('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]),
        onlyExisting('tenant_memberships', [
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'preset_key' => 'owner',
            'granted_permissions' => json_encode(['*'], JSON_THROW_ON_ERROR),
            'accepted_at' => now(),
            'can_manage_products' => true,
            'can_manage_orders' => true,
            'can_manage_marketing' => true,
            'can_manage_billing' => true,
            'can_manage_settings' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ])
    );
}

function makeMarketingFixture(string $runId, string $suffix, string $planType): array {
    $email = $runId.'-'.$suffix.'@example.test';
    $password = 'Password123!';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Marketing '.strtoupper($suffix),
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Marketing '.strtoupper($suffix),
        'slug' => $runId.'-'.$suffix,
        'description' => 'Fixture temporal para smoke de marketing.',
        'user_id' => $user->id,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => $planType,
        'subscription_status' => 'active',
        'phone' => '22223333',
        'whatsapp_number' => '98765432',
        'business_type' => 'retail',
    ];

    if (Schema::hasColumn('tenants', 'whatsapp_message')) {
        $tenantData['whatsapp_message'] = 'Hola, me interesa el producto {producto} de {precio}.';
    }

    if (Schema::hasColumn('tenants', 'is_pro')) {
        $tenantData['is_pro'] = $planType === 'pro';
    }

    $tenant = new Tenant();
    $tenant->forceFill(onlyExisting('tenants', $tenantData));
    Tenant::withoutEvents(fn () => $tenant->save());

    $user->forceFill(['tenant_id' => $tenant->id])->save();
    ensureTenantMembership($user, $tenant);

    if ($planType === 'pro') {
        $section = new MenuSection();
        $section->forceFill([
            'tenant_id' => $tenant->id,
            'name' => 'Promociones',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        MenuSection::withoutEvents(fn () => $section->save());
    }

    return [
        'email' => $email,
        'password' => $password,
        'tenantId' => $tenant->id,
        'tenantSlug' => $tenant->slug,
        'panelPath' => '/app/'.$tenant->slug,
        'marketingPath' => '/app/'.$tenant->slug.'/manage-marketing',
    ];
}

if ($mode === 'setup') {
    echo json_encode([
        'free' => makeMarketingFixture($runId, 'free', 'semilla'),
        'pro' => makeMarketingFixture($runId, 'pro', 'pro'),
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    $tenantIds = Schema::hasTable('tenants')
        ? Tenant::withoutGlobalScopes()->where('slug', 'like', $runId.'%')->pluck('id')
        : collect();

    if ($tenantIds->isNotEmpty() && Schema::hasTable('menu_sections')) {
        MenuSection::withoutGlobalScopes()->whereIn('tenant_id', $tenantIds)->delete();
    }

    if ($tenantIds->isNotEmpty() && Schema::hasTable('tenant_memberships')) {
        DB::table('tenant_memberships')->whereIn('tenant_id', $tenantIds)->delete();
    }

    if ($tenantIds->isNotEmpty() && Schema::hasTable('tenants')) {
        Tenant::withoutGlobalScopes()->whereIn('id', $tenantIds)->forceDelete();
    }

    if (Schema::hasTable('users')) {
        User::where('email', 'like', $runId.'%@example.test')->delete();
    }

    echo json_encode(['cleaned' => true], JSON_THROW_ON_ERROR);
    exit(0);
}

fwrite(STDERR, 'Unknown mode: '.$mode);
exit(1);
`,
    "utf8",
)

const result = {
    baseUrl,
    runId,
    status: "fail",
    checks: [],
    screenshots: {},
    browserErrors: [],
    error: null,
}

function pushCheck(name, pass, details = "") {
    result.checks.push({ name, pass, details })
}

async function withTimeout(promise, label, timeoutMs = 120_000) {
    let timeout
    const timer = new Promise((_, reject) => {
        timeout = setTimeout(() => reject(new Error(`${label} timed out after ${timeoutMs}ms`)), timeoutMs)
    })

    try {
        return await Promise.race([promise, timer])
    } finally {
        clearTimeout(timeout)
    }
}

function safeRm(filePath, label) {
    let lastError = null

    for (let attempt = 0; attempt < 5; attempt += 1) {
        try {
            fs.rmSync(filePath, { force: true })
            return true
        } catch (error) {
            lastError = error
            Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 200)
        }
    }

    result.browserErrors.push(`${label} cleanup skipped: ${String(lastError?.message || lastError)}`)
    return false
}

function runHelper(mode) {
    const helper = spawnSync(phpBin, [helperPath, root, mode, runId], {
        cwd: root,
        encoding: "utf8",
        env: smokeEnv,
        windowsHide: true,
    })

    if (helper.status !== 0) {
        throw new Error(`${mode} helper failed: ${helper.stderr || helper.stdout}`)
    }

    const output = helper.stdout.trim()
    const candidates = output
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line.startsWith("{") && line.endsWith("}"))
        .reverse()

    for (const candidate of candidates) {
        try {
            return JSON.parse(candidate)
        } catch {
            // Try the next candidate; Laravel can print warnings before helper JSON.
        }
    }

    throw new Error(`${mode} helper did not return JSON: ${output.slice(0, 400)}`)
}

function runArtisan(args, label) {
    const command = spawnSync(phpBin, ["artisan", ...args], {
        cwd: root,
        encoding: "utf8",
        env: smokeEnv,
        windowsHide: true,
    })

    if (command.status !== 0) {
        throw new Error(`${label} failed: ${command.stderr || command.stdout}`)
    }

    return command
}

async function waitForServer() {
    const deadline = Date.now() + 30_000
    let lastError = null

    while (Date.now() < deadline) {
        try {
            const response = await fetch(`${baseUrl}/up`)
            if (response.status < 500) {
                return
            }
            lastError = new Error(`status ${response.status}`)
        } catch (error) {
            lastError = error
        }

        await new Promise((resolve) => setTimeout(resolve, 500))
    }

    throw new Error(`Laravel server did not become ready at ${baseUrl}: ${lastError?.message || "unknown error"}`)
}

async function screenshot(page, name) {
    const file = path.join(outDir, `${name}.png`)
    try {
        await page.screenshot({ path: file, fullPage: true, timeout: 20_000 })
    } catch {
        await page.screenshot({ path: file, fullPage: false, timeout: 20_000 })
    }
    result.screenshots[name] = file
}

function hasFatalText(bodyText) {
    return /server error|whoops|exception|stack trace|sqlstate|fatal error/i.test(bodyText)
}

async function login(page, fixture, label) {
    page.setDefaultTimeout(15_000)
    page.setDefaultNavigationTimeout(45_000)

    const loginResponse = await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded", timeout: 45_000 })
    await page.waitForLoadState("domcontentloaded", { timeout: 10_000 }).catch(() => {})
    pushCheck(`${label}_login_route_200`, loginResponse?.status() === 200, `status=${loginResponse?.status()}`)

    const csrfToken =
        (await page.locator('meta[name="csrf-token"]').getAttribute("content", { timeout: 1_000 }).catch(() => null)) ||
        (await page.locator('input[name="_token"]').first().getAttribute("value", { timeout: 1_000 }).catch(() => null)) ||
        ""
    const authStatus = await page.evaluate(
        async ({ loginUrl, email, password, token }) => {
            const controller = new AbortController()
            const timeout = setTimeout(() => controller.abort(), 20_000)
            const body = new URLSearchParams()
            body.set("_token", token || "")
            body.set("email", email)
            body.set("password", password)
            body.set("remember", "0")

            try {
                const response = await fetch(loginUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                        "X-CSRF-TOKEN": token || "",
                        "X-Requested-With": "XMLHttpRequest",
                        Accept: "text/html, application/xhtml+xml",
                    },
                    body,
                    credentials: "same-origin",
                    redirect: "follow",
                    signal: controller.signal,
                })

                return { status: response.status, url: response.url }
            } finally {
                clearTimeout(timeout)
            }
        },
        { loginUrl: `${baseUrl}/login`, email: fixture.email, password: fixture.password, token: csrfToken },
    )
    pushCheck(`${label}_login_fetch_ok`, authStatus.status < 500, `status=${authStatus.status} url=${authStatus.url}`)

    let probe = await page.goto(`${baseUrl}${fixture.marketingPath}`, { waitUntil: "commit", timeout: 45_000 })
    if ((probe?.status() || 0) >= 500) {
        await page.waitForTimeout(750)
        probe = await page.goto(`${baseUrl}${fixture.marketingPath}`, { waitUntil: "commit", timeout: 45_000 })
    }
    await page.waitForLoadState("domcontentloaded", { timeout: 10_000 }).catch(() => {})
    const authenticated = (probe?.status() || 0) < 500 && page.url().includes(fixture.marketingPath)

    pushCheck(`${label}_login_authenticated`, authenticated, `${page.url()} status=${probe?.status()}`)
}

async function validateWorkspaceRedirect(page, fixture, label) {
    let response = null
    try {
        response = await page.goto(`${baseUrl}/workspace/marketing`, { waitUntil: "commit", timeout: 45_000 })
    } catch {
        await page.waitForTimeout(750)
        response = await page.goto(`${baseUrl}/workspace/marketing`, { waitUntil: "commit", timeout: 45_000 })
    }
    await page.waitForLoadState("domcontentloaded", { timeout: 10_000 }).catch(() => {})
    const currentUrl = page.url()

    pushCheck(`${label}_workspace_marketing_no_server_error`, (response?.status() || 0) < 500, `status=${response?.status()} url=${currentUrl}`)
    pushCheck(`${label}_workspace_marketing_redirects_canonical`, currentUrl.includes(fixture.marketingPath), currentUrl)
}

async function validateMarketingPage(page, fixture, label, expectedState) {
    let response = null
    if (!page.url().includes(fixture.marketingPath)) {
        response = await page.goto(`${baseUrl}${fixture.marketingPath}`, { waitUntil: "commit", timeout: 45_000 })
    }

    await page.waitForLoadState("domcontentloaded", { timeout: 20_000 }).catch(() => {})
    const bodyText = await page.locator("body").innerText().catch(() => "")

    pushCheck(`${label}_marketing_status_ok`, (response?.status() || 200) < 500, `status=${response?.status() || 'current'} url=${page.url()}`)
    pushCheck(`${label}_marketing_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 160))

    await page.getByTestId("marketing-page").waitFor({ state: "visible", timeout: 15_000 })
    pushCheck(`${label}_marketing_page_visible`, await page.getByTestId("marketing-page").isVisible().catch(() => false))

    if (expectedState === "free") {
        pushCheck(`${label}_free_paywall_visible`, await page.getByTestId("marketing-free-paywall").isVisible().catch(() => false))
        pushCheck(`${label}_free_billing_cta_visible`, await page.getByRole("link", { name: /ver planes/i }).isVisible().catch(() => false))
        return
    }

    pushCheck(`${label}_pro_tools_visible`, await page.getByTestId("marketing-pro-tools").isVisible().catch(() => false))
    pushCheck(`${label}_whatsapp_preview_visible`, await page.getByTestId("marketing-whatsapp-preview").isVisible().catch(() => false))
    pushCheck(`${label}_qr_card_visible`, await page.getByTestId("marketing-qr-card").isVisible().catch(() => false))
    pushCheck(`${label}_menu_sections_visible`, await page.getByTestId("marketing-menu-sections").isVisible().catch(() => false))
    pushCheck(`${label}_tips_visible`, await page.getByTestId("marketing-usage-tips").isVisible().catch(() => false))
    pushCheck(`${label}_poster_button_visible`, await page.getByTestId("marketing-download-poster").isVisible().catch(() => false))
    pushCheck(`${label}_table_or_section_text_present`, /Promociones|Secciones del menu|Nueva seccion/i.test(bodyText), bodyText.slice(0, 220))
}

async function validateViewport(fixture, label, viewport, expectedState) {
    const context = await browser.newContext({ ...viewport, serviceWorkers: "block" })
    const page = await context.newPage()
    page.setDefaultTimeout(15_000)
    page.setDefaultNavigationTimeout(45_000)
    page.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    page.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    try {
        await login(page, fixture, label)
        await validateWorkspaceRedirect(page, fixture, label)
        await validateMarketingPage(page, fixture, label, expectedState)
        await screenshot(page, `marketing-${expectedState}-${label}`)
    } finally {
        await context.close().catch(() => {})
    }
}

let server
let browser
let fixtures

try {
    runArtisan(["view:clear"], "view clear")
    runArtisan(["migrate", "--force", "--no-interaction"], "smoke sqlite migration")
    pushCheck("smoke_sqlite_migrated", true, sqlitePath)

    if (shouldStartServer) {
        server = spawn(phpBin, ["artisan", "serve", "--host", host, "--port", String(port)], {
            cwd: root,
            stdio: ["ignore", "pipe", "pipe"],
            env: smokeEnv,
            windowsHide: true,
        })
    }

    await waitForServer()
    fixtures = runHelper("setup")
    browser = await chromium.launch({ headless: true })

    await withTimeout(
        validateViewport(fixtures.free, "desktop_free", { viewport: { width: 1366, height: 900 } }, "free"),
        "desktop_free marketing viewport",
    )
    await withTimeout(
        validateViewport(fixtures.pro, "desktop_pro", { viewport: { width: 1366, height: 900 } }, "pro"),
        "desktop_pro marketing viewport",
    )
    await withTimeout(
        validateViewport(fixtures.pro, "mobile_pro", { viewport: { width: 390, height: 844 }, isMobile: true }, "pro"),
        "mobile_pro marketing viewport",
    )

    result.status = result.checks.every((check) => check.pass) ? "pass" : "fail"
} catch (error) {
    result.error = String(error?.message || error)
    result.status = "fail"
} finally {
    if (browser) {
        await browser.close().catch(() => {})
    }

    if (fixtures) {
        try {
            runHelper("cleanup")
            result.fixtureCleaned = true
        } catch (error) {
            result.fixtureCleaned = false
            result.browserErrors.push(`cleanup failed: ${String(error?.message || error)}`)
        }
    }

    if (server) {
        if (process.platform === "win32" && server.pid) {
            spawnSync("taskkill", ["/PID", String(server.pid), "/T", "/F"], {
                cwd: root,
                encoding: "utf8",
                windowsHide: true,
            })
        } else {
            server.kill()
        }
    }

    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2), "utf8")
    safeRm(helperPath, "helper")
    safeRm(configCachePath, "config cache")
    if (!process.env.UIUX_SMOKE_DB) {
        result.sqliteCleaned = safeRm(sqlitePath, "sqlite")
    }
}

console.log(`uiux-fifteenth-marketing-smoke artifact: ${path.join(outDir, "result.json")}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
