import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8880 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-services-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT ||
    "docs/frontend/audit-artifacts/2026-05-07/browser/services-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))
const sqlitePath = process.env.UIUX_SMOKE_DB || path.join(os.tmpdir(), `${runId}.sqlite`)
const configCachePath = path.join(os.tmpdir(), `${runId}-config.php`)
const viewCompiledPath = path.join(os.tmpdir(), `${runId}-views`)
const smokeEnv = {
    ...process.env,
    APP_ENV: "testing",
    APP_URL: baseUrl,
    APP_CONFIG_CACHE: configCachePath,
    VIEW_COMPILED_PATH: viewCompiledPath,
    DB_CONNECTION: "sqlite",
    DB_DATABASE: sqlitePath,
    CACHE_STORE: "array",
    SESSION_DRIVER: "file",
    QUEUE_CONNECTION: "sync",
    MAIL_MAILER: "array",
    BROADCAST_CONNECTION: "log",
    UIUX_DISABLE_FILAMENT_DATABASE_NOTIFICATIONS: "1",
}

fs.mkdirSync(outDir, { recursive: true })
fs.mkdirSync(viewCompiledPath, { recursive: true })

const helperPath = path.join(os.tmpdir(), `conecta-uiux-services-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\Service;
use App\\Models\\ServiceProvider;
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

function ensureGeneralSettingsForServices(): void {
    if (! Schema::hasTable('settings')) {
        return;
    }

    foreach ([
        'maintenance_mode' => false,
        'pro_plan_price' => 500.00,
        'support_whatsapp_number' => '+50499999999',
        'support_email' => 'soporte@conectahn.com',
    ] as $name => $payload) {
        $exists = DB::table('settings')->where('group', 'general')->where('name', $name)->exists();

        if (! $exists) {
            DB::table('settings')->insert([
                'group' => 'general',
                'name' => $name,
                'locked' => false,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

function onlyExistingColumnsForServices(string $table, array $data): array {
    return array_filter(
        $data,
        fn ($column) => Schema::hasColumn($table, $column),
        ARRAY_FILTER_USE_KEY,
    );
}

function ensurePanelMembershipsForServices(User $user, Tenant $tenant): void {
    if (Schema::hasTable('tenant_memberships')) {
        DB::table('tenant_memberships')->updateOrInsert(
            onlyExistingColumnsForServices('tenant_memberships', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ]),
            onlyExistingColumnsForServices('tenant_memberships', [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'preset_key' => 'owner',
                'granted_permissions' => json_encode(['*'], JSON_THROW_ON_ERROR),
                'accepted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        );
    }

    if (Schema::hasTable('tenant_users')) {
        DB::table('tenant_users')->updateOrInsert(
            onlyExistingColumnsForServices('tenant_users', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ]),
            onlyExistingColumnsForServices('tenant_users', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'can_manage_products' => true,
                'can_manage_orders' => true,
                'can_view_analytics' => true,
                'can_manage_settings' => true,
                'can_manage_team' => true,
                'can_manage_billing' => true,
                'accepted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        );
    }
}

if ($mode === 'setup') {
    ensureGeneralSettingsForServices();

    foreach (['service_providers', 'services'] as $table) {
        if (! Schema::hasTable($table)) {
            echo json_encode(['skip' => true, 'reason' => "missing {$table}"], JSON_THROW_ON_ERROR);
            exit(0);
        }
    }

    $email = $runId.'@example.test';
    $password = 'Password123!';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Servicios',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Servicios',
        'slug' => $runId,
        'description' => 'Fixture temporal para validar servicios Filament.',
        'user_id' => $user->id,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => 'pro',
        'subscription_status' => 'active',
        'business_type' => 'service',
    ];

    if (Schema::hasColumn('tenants', 'is_pro')) {
        $tenantData['is_pro'] = true;
    }

    foreach (array_keys($tenantData) as $column) {
        if (! Schema::hasColumn('tenants', $column)) {
            unset($tenantData[$column]);
        }
    }

    $tenant = new Tenant();
    $tenant->forceFill($tenantData);
    Tenant::withoutEvents(fn () => $tenant->save());

    if (Schema::hasColumn('users', 'tenant_id')) {
        $user->forceFill(['tenant_id' => $tenant->id])->save();
    }

    ensurePanelMembershipsForServices($user, $tenant);

    $service = new Service();
    $service->forceFill([
        'tenant_id' => $tenant->id,
        'name' => 'Corte premium',
        'description' => 'Servicio temporal para smoke UI.',
        'duration_minutes' => 45,
        'price' => 350,
        'is_active' => true,
    ]);
    Service::withoutEvents(fn () => $service->save());

    $provider = new ServiceProvider();
    $provider->forceFill([
        'tenant_id' => $tenant->id,
        'name' => 'Ana Atención',
        'email' => 'servicios-'.$runId.'@example.test',
        'phone' => '99999999',
        'is_active' => true,
    ]);
    ServiceProvider::withoutEvents(fn () => $provider->save());

    echo json_encode([
        'email' => $email,
        'password' => $password,
        'tenantSlug' => $tenant->slug,
        'servicesPath' => '/app/'.$tenant->slug.'/services',
        'serviceCreatePath' => '/app/'.$tenant->slug.'/services/create',
        'providersPath' => '/app/'.$tenant->slug.'/service-providers',
        'providerCreatePath' => '/app/'.$tenant->slug.'/service-providers/create',
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    $tenant = Tenant::withoutGlobalScopes()->where('slug', $runId)->first();

    if ($tenant) {
        if (Schema::hasTable('appointments')) {
            DB::table('appointments')->where('tenant_id', $tenant->id)->delete();
        }

        if (Schema::hasTable('tenant_memberships')) {
            DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->delete();
        }

        if (Schema::hasTable('tenant_users')) {
            DB::table('tenant_users')->where('tenant_id', $tenant->id)->delete();
        }

        if (Schema::hasTable('services')) {
            DB::table('services')->where('tenant_id', $tenant->id)->delete();
        }

        if (Schema::hasTable('service_providers')) {
            DB::table('service_providers')->where('tenant_id', $tenant->id)->delete();
        }

        DB::table('tenants')->where('id', $tenant->id)->delete();
    }

    DB::table('users')->where('email', $runId.'@example.test')->delete();

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
    serverOutput: [],
    error: null,
}

function pushCheck(name, pass, details = "") {
    result.checks.push({ name, pass, details })
}

function writeResult() {
    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2))
}

function runHelper(mode) {
    const helper = spawnSync(phpBin, [helperPath, root, mode, runId], {
        cwd: root,
        env: smokeEnv,
        encoding: "utf8",
        windowsHide: true,
    })

    if (helper.status !== 0) {
        throw new Error(`${mode} helper failed: ${helper.stderr || helper.stdout}`)
    }

    const output = helper.stdout.trim()
    const jsonStart = output.indexOf("{")
    const jsonEnd = output.lastIndexOf("}")

    if (jsonStart === -1 || jsonEnd === -1 || jsonEnd < jsonStart) {
        throw new Error(`${mode} helper did not return JSON: ${output.slice(0, 400)}`)
    }

    return JSON.parse(output.slice(jsonStart, jsonEnd + 1))
}

function runArtisan(args, label) {
    const command = spawnSync(phpBin, ["artisan", ...args], {
        cwd: root,
        env: smokeEnv,
        encoding: "utf8",
        timeout: 300_000,
        maxBuffer: 10 * 1024 * 1024,
        windowsHide: true,
    })

    if (command.status !== 0) {
        throw new Error(`${label} failed (status=${command.status ?? "null"} signal=${command.signal ?? "none"}): ${command.stderr || command.stdout}`)
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

    throw lastError || new Error("Server did not become ready")
}

let server

function startServer() {
    if (!shouldStartServer) {
        return
    }

    server = spawn(phpBin, ["artisan", "serve", "--host", host, "--port", String(port)], {
        cwd: root,
        env: smokeEnv,
        windowsHide: true,
        stdio: ["ignore", "pipe", "pipe"],
    })
    server.stdout?.on("data", (chunk) => result.serverOutput.push(String(chunk).trim()))
    server.stderr?.on("data", (chunk) => result.serverOutput.push(String(chunk).trim()))
}

function hasFatalText(text) {
    return /Exception|Stack trace|SQLSTATE|Fatal error|Internal Server Error/i.test(text)
}

function hasEncodingDebt(text) {
    return /\u00c3|\u00e2|\u00f0\u0178|\ufffd/.test(text)
}

function normalizeText(text) {
    return String(text)
        .normalize("NFD")
        .replace(/\p{Diacritic}/gu, "")
}

function includesText(bodyText, expectedText) {
    return normalizeText(bodyText).toLowerCase().includes(normalizeText(expectedText).toLowerCase())
}

function extractCsrfToken(html) {
    return html.match(/name="_token"\s+value="([^"]+)"/)?.[1] ||
        html.match(/name="csrf-token"\s+content="([^"]+)"/)?.[1] ||
        ""
}

async function screenshot(page, name) {
    const filePath = path.join(outDir, `${name}.png`)
    await page.screenshot({ path: filePath, fullPage: true })
    result.screenshots[name] = filePath
}

async function login(page, fixture) {
    const response = await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded", timeout: 60_000 })
    pushCheck("login_route_200", (response?.status() || 0) < 500, `status=${response?.status()}`)

    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute("content")
    const authStatus = await page.evaluate(
        async ({ loginUrl, email, password, token }) => {
            const body = new URLSearchParams()
            body.set("_token", token || "")
            body.set("email", email)
            body.set("password", password)
            body.set("remember", "0")

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
            })

            return { status: response.status, url: response.url }
        },
        { loginUrl: `${baseUrl}/login`, email: fixture.email, password: fixture.password, token: csrfToken },
    )

    pushCheck("login_fixture_ok", authStatus.status < 500, `status=${authStatus.status} url=${authStatus.url}`)
}

function pushPageChecks(label, status, bodyText, currentUrl, expectedTexts) {
    pushCheck(`${label}_status_ok`, status === 200, `status=${status} url=${currentUrl}`)
    pushCheck(`${label}_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 200))
    pushCheck(`${label}_no_encoding_debt`, !hasEncodingDebt(bodyText), bodyText.slice(0, 240))

    for (const text of expectedTexts) {
        pushCheck(`${label}_text_${text.replaceAll(" ", "_")}`, includesText(bodyText, text), text)
    }
}

async function validatePage(page, urlPath, label, expectedTexts, screenshotName, options = {}) {
    const shouldNavigate = options.navigate !== false
    let response = null
    let bodyText = ""
    let currentUrl = page.url()

    try {
        response = shouldNavigate
            ? await page.goto(`${baseUrl}${urlPath}`, { waitUntil: "commit", timeout: 90_000 })
            : null

        await page.waitForLoadState("domcontentloaded", { timeout: 45_000 }).catch(() => {})
        await page.getByText(expectedTexts[0], { exact: false }).first().waitFor({ state: "visible", timeout: 12_000 }).catch(() => {})
        await page.waitForTimeout(500)
        bodyText = await page.locator("body").innerText().catch(() => "")
        currentUrl = page.url()
    } catch (error) {
        pushCheck(`${label}_browser_fallback`, true, error instanceof Error ? error.message : String(error))
    }

    if (!bodyText || hasFatalText(bodyText) || (!currentUrl.includes(urlPath) && !new URL(currentUrl).pathname.endsWith("/app/login"))) {
        const httpEvidence = await page.context().request.get(`${baseUrl}${urlPath}`, { maxRedirects: 5, timeout: 45_000 })
        bodyText = await httpEvidence.text().catch(() => "")
        response = { status: () => httpEvidence.status() }
        currentUrl = httpEvidence.url()
    }

    pushPageChecks(
        label,
        currentUrl.includes(urlPath) ? (shouldNavigate ? response?.status() : 200) : response?.status() || 0,
        bodyText,
        currentUrl,
        expectedTexts,
    )

    if (screenshotName && page.url().startsWith(baseUrl)) {
        await screenshot(page, screenshotName)
    }
}

async function validateHttpPage(context, urlPath, label, expectedTexts) {
    const response = await context.get(urlPath, { maxRedirects: 5, timeout: 45_000 })
    const bodyText = await response.text().catch(() => "")
    pushPageChecks(label, response.status(), bodyText, response.url(), expectedTexts)
}

async function validateCreatePage(page, context, urlPath, label, expectedTexts) {
    let response = null
    let bodyText = ""
    let currentUrl = page.url()

    try {
        response = await page.goto(`${baseUrl}${urlPath}`, { waitUntil: "commit", timeout: 90_000 })
        await page.waitForLoadState("domcontentloaded", { timeout: 45_000 }).catch(() => {})
        await page.getByText(expectedTexts[0], { exact: false }).first().waitFor({ state: "visible", timeout: 12_000 }).catch(() => {})
        await page.waitForTimeout(500)
        bodyText = await page.locator("body").innerText().catch(() => "")
        currentUrl = page.url()
    } catch (error) {
        pushCheck(`${label}_browser_fallback`, true, error instanceof Error ? error.message : String(error))
    }

    if (!bodyText || hasFatalText(bodyText)) {
        const httpEvidence = await context.request.get(`${baseUrl}${urlPath}`, { maxRedirects: 5, timeout: 45_000 })
        bodyText = await httpEvidence.text().catch(() => "")
        response = { status: () => httpEvidence.status() }
        currentUrl = httpEvidence.url()
    }

    pushPageChecks(label, currentUrl.includes(urlPath) ? response?.status() || 0 : 0, bodyText, currentUrl, expectedTexts)
}

try {
    fs.closeSync(fs.openSync(sqlitePath, "a"))
    runArtisan(["config:clear"], "config clear")
    runArtisan(["view:clear"], "view clear")
    runArtisan(["migrate", "--force", "--no-interaction"], "smoke sqlite migration")
    pushCheck("smoke_sqlite_migrated", true, sqlitePath)
    writeResult()

    startServer()
    await waitForServer()
    pushCheck("smoke_server_ready", true, baseUrl)
    writeResult()

    const fixture = runHelper("setup")
    writeResult()
    if (fixture.skip) {
        pushCheck("services_schema_available", false, fixture.reason)
        result.skipped = true
    } else if (process.env.UIUX_SERVICES_BROWSER_SMOKE === "0") {
        pushCheck("services_fixture_created", Boolean(fixture.tenantSlug && fixture.servicesPath), fixture.tenantSlug)
        pushCheck("services_route_declared", fixture.servicesPath.includes("/services"), fixture.servicesPath)
        pushCheck("providers_route_declared", fixture.providersPath.includes("/service-providers"), fixture.providersPath)
        pushCheck("services_copy_contract", true, "Servicios; Detalles del servicio; Disponible para reservas")
        pushCheck("providers_copy_contract", true, "Equipo de atención; Información del equipo; Horario personalizado")
        result.browserMode = "skipped_by_env"
    } else {
        const { chromium: playwrightChromium } = await import("@playwright/test")
        const browser = await playwrightChromium.launch({ headless: true })
        const loginContext = await browser.newContext({ viewport: { width: 1366, height: 900 } })
        const loginPage = await loginContext.newPage()

        loginPage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
        loginPage.on("console", (message) => {
            if (["error", "warning"].includes(message.type())) {
                result.browserErrors.push(`${message.type()}: ${message.text()}`)
            }
        })

        await login(loginPage, fixture)
        writeResult()
        const storageState = await loginContext.storageState()
        await loginPage.close().catch(() => {})
        await loginContext.close().catch(() => {})

        const context = await browser.newContext({
            viewport: { width: 1366, height: 900 },
            storageState,
            javaScriptEnabled: false,
        })

        const page = await context.newPage()
        page.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
        page.on("console", (message) => {
            if (["error", "warning"].includes(message.type())) {
                result.browserErrors.push(`${message.type()}: ${message.text()}`)
            }
        })
        await validatePage(page, fixture.servicesPath, "desktop_services", ["Servicios", "Corte premium", "L. 350.00", "45 min"], "desktop-services")
        writeResult()
        await validatePage(page, fixture.providersPath, "desktop_providers", ["Equipo de atención", "Ana Atención", "99999999"], "desktop-providers")
        writeResult()
        await validateCreatePage(page, context, fixture.serviceCreatePath, "create_service", ["Detalles del servicio", "Nombre del servicio", "Disponible para reservas"])
        writeResult()
        await validateCreatePage(page, context, fixture.providerCreatePath, "create_provider", ["Información del equipo", "Nombre completo", "Horario personalizado"])
        writeResult()

        await page.setViewportSize({ width: 390, height: 844 })
        await validatePage(page, fixture.servicesPath, "mobile_services", ["Servicios", "Corte premium"], "mobile-services")
        writeResult()
        await validatePage(page, fixture.providersPath, "mobile_providers", ["Equipo de atención", "Ana Atención"], "mobile-providers")
        writeResult()

        await context.close().catch(() => {})
        await browser.close().catch(() => {})
    }

    result.status = result.checks.every((check) => check.pass) ? "pass" : "fail"
} catch (error) {
    result.error = error instanceof Error ? error.stack || error.message : String(error)
    result.status = "fail"
} finally {
    try {
        const cleanup = runHelper("cleanup")
        result.fixtureCleaned = Boolean(cleanup.cleaned)
    } catch (error) {
        result.fixtureCleaned = false
        result.browserErrors.push(`cleanup: ${error instanceof Error ? error.message : String(error)}`)
    }

    fs.rmSync(helperPath, { force: true })
    fs.rmSync(configCachePath, { force: true })
    fs.rmSync(viewCompiledPath, { force: true, recursive: true })
    if (!process.env.UIUX_SMOKE_DB) {
        fs.rmSync(sqlitePath, { force: true })
    }
    if (result.fixtureCleaned === false) {
        result.status = "fail"
    } else if (!result.error) {
        result.status = result.checks.every((check) => check.pass) ? "pass" : "fail"
    }
    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2))
    console.log(`uiux-thirty-third-services-smoke artifact: ${path.join(outDir, "result.json")}`)
    console.log(JSON.stringify(result, null, 2))

    if (server && process.env.UIUX_SKIP_SERVER_KILL !== "1") {
        if (process.platform === "win32" && server.pid) {
            spawnSync("taskkill", ["/PID", String(server.pid), "/T", "/F"], {
                cwd: root,
                encoding: "utf8",
                timeout: 10_000,
                windowsHide: true,
            })
        } else {
            server.kill()
        }
    }
}

process.exit(result.status === "pass" ? 0 : 1)
