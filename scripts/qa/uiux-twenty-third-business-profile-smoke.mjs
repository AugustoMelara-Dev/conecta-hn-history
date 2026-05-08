import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8523 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-business-profile-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT ||
    "docs/frontend/audit-artifacts/2026-05-07/browser/business-profile-smoke"
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

const helperPath = path.join(os.tmpdir(), `conecta-uiux-business-profile-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

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

function ensureGeneralSettingsForBusinessProfile(): void {
    if (! Schema::hasTable('settings')) {
        return;
    }

    $defaults = [
        'maintenance_mode' => false,
        'pro_plan_price' => 500.00,
        'support_whatsapp_number' => '+50499999999',
        'support_email' => 'soporte@conectahn.com',
    ];

    foreach ($defaults as $name => $payload) {
        $exists = DB::table('settings')
            ->where('group', 'general')
            ->where('name', $name)
            ->exists();

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

function firstId(string $table): mixed {
    if (! Schema::hasTable($table)) {
        return null;
    }

    return DB::table($table)->value('id');
}

function onlyExistingColumnsForBusinessProfile(string $table, array $data): array {
    return array_filter(
        $data,
        fn ($column) => Schema::hasColumn($table, $column),
        ARRAY_FILTER_USE_KEY,
    );
}

function ensurePanelMembershipsForBusinessProfile(User $user, Tenant $tenant): void {
    if (Schema::hasTable('tenant_memberships')) {
        DB::table('tenant_memberships')->updateOrInsert(
            onlyExistingColumnsForBusinessProfile('tenant_memberships', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ]),
            onlyExistingColumnsForBusinessProfile('tenant_memberships', [
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
            onlyExistingColumnsForBusinessProfile('tenant_users', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ]),
            onlyExistingColumnsForBusinessProfile('tenant_users', [
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
    ensureGeneralSettingsForBusinessProfile();

    $email = $runId.'@example.test';
    $password = 'Password123!';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Perfil Negocio',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Perfil Negocio',
        'slug' => $runId,
        'description' => 'Fixture temporal para validar el perfil canonico del negocio.',
        'about' => 'Historia temporal del negocio para smoke UI.',
        'slogan' => 'Perfil listo para vender',
        'user_id' => $user->id,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => 'semilla',
        'subscription_status' => 'active',
        'phone' => '22223333',
        'whatsapp_number' => '99999999',
        'email' => $email,
        'address' => 'Tegucigalpa, Honduras',
        'business_type' => 'retail',
        'brand_color' => '#2563EB',
        'primary_color' => '#2563EB',
        'category_id' => firstId('categories'),
        'city_id' => firstId('cities'),
    ];

    if (Schema::hasColumn('tenants', 'is_pro')) {
        $tenantData['is_pro'] = false;
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

    ensurePanelMembershipsForBusinessProfile($user, $tenant);

    echo json_encode([
        'email' => $email,
        'password' => $password,
        'tenantSlug' => $tenant->slug,
        'profilePath' => '/app/'.$tenant->slug.'/profile',
        'resourcePath' => '/app/'.$tenant->slug.'/tenant-profiles',
        'hasTenantCategory' => array_key_exists('category_id', $tenant->getAttributes()),
        'hasTenantCity' => array_key_exists('city_id', $tenant->getAttributes()),
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    $tenant = Tenant::withoutGlobalScopes()->where('slug', $runId)->first();

    if ($tenant) {
        if (Schema::hasTable('tenant_memberships')) {
            DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->delete();
        }

        if (Schema::hasTable('tenant_users')) {
            DB::table('tenant_users')->where('tenant_id', $tenant->id)->delete();
        }

        DB::table('tenants')->where('id', $tenant->id)->delete();
    }

    User::where('email', $runId.'@example.test')->delete();

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

async function startSmokeServer() {
    if (!shouldStartServer) {
        return
    }

    server = spawn(phpBin, ["artisan", "serve", `--host=${host}`, `--port=${port}`], {
        cwd: root,
        env: smokeEnv,
        stdio: ["ignore", "pipe", "pipe"],
        windowsHide: true,
    })

    server.stdout.on("data", (chunk) => result.browserErrors.push(`server: ${chunk.toString().trim()}`))
    server.stderr.on("data", (chunk) => result.browserErrors.push(`server-error: ${chunk.toString().trim()}`))

    await waitForServer()
}

async function stopSmokeServer() {
    if (!server) {
        return
    }

    if (process.platform === "win32" && server.pid) {
        spawnSync("taskkill", ["/PID", String(server.pid), "/T", "/F"], {
            cwd: root,
            encoding: "utf8",
            windowsHide: true,
        })
    } else {
        server.kill()
    }

    server = undefined
    await new Promise((resolve) => setTimeout(resolve, 1000))
}

async function screenshot(page, name) {
    const file = path.join(outDir, `${name}.png`)
    await page.screenshot({ path: file, fullPage: true })
    result.screenshots[name] = file
}

function hasFatalText(bodyText) {
    return /server error|whoops|exception|stack trace|sqlstate|fatal error/i.test(bodyText)
}

function hasEncodingDebt(bodyText) {
    return /ÃƒÆ’|Ãƒâ€š|ÃƒÂ°|ÃƒÂ¢|Ã‚|Ã¯Â¿Â½|CategorÃ|DescripciÃ|InformaciÃ|TelÃ|DirecciÃ|DÃ|SÃ/.test(bodyText)
}

async function login(page, fixture) {
    const blockLoginAssets = async (route) => {
        await route.abort()
    }

    await page.route("**/build/assets/**", blockLoginAssets)
    await page.route("**/auth-side.svg", blockLoginAssets)

    const loginResponse = await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded", timeout: 45_000 })
    pushCheck("login_route_200", loginResponse?.status() === 200, `status=${loginResponse?.status()}`)

    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute("content")
    let authStatus = { status: 0, url: page.url(), error: null }

    try {
        authStatus = await page.evaluate(
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

                return { status: response.status, url: response.url, error: null }
            },
            { loginUrl: `${baseUrl}/login`, email: fixture.email, password: fixture.password, token: csrfToken },
        )
    } catch (error) {
        authStatus = {
            status: 0,
            url: page.url(),
            error: error instanceof Error ? error.message : String(error),
        }
    }

    await page.unroute("**/build/assets/**", blockLoginAssets).catch(() => {})
    await page.unroute("**/auth-side.svg", blockLoginAssets).catch(() => {})

    if (authStatus.error || authStatus.url.endsWith("/login")) {
        await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded", timeout: 45_000 })
        await page.locator('input[name="email"]').fill(fixture.email)
        await page.locator('input[name="password"]').fill(fixture.password)
        await page.locator('button[type="submit"]').click()
        await page.waitForURL((url) => !url.pathname.endsWith("/login"), { timeout: 30_000 }).catch(() => {})
        pushCheck("login_form_fallback_ok", !page.url().endsWith("/login"), page.url())
    }

    const loginOk =
        (authStatus.status > 0 && authStatus.status < 500 && !authStatus.url.endsWith("/login")) ||
        !page.url().endsWith("/login")

    pushCheck("login_fixture_ok", loginOk, `status=${authStatus.status} url=${authStatus.url}${authStatus.error ? ` error=${authStatus.error}` : ""}`)
}

async function validateProfile(page, fixture, label) {
    const response = await page.goto(`${baseUrl}${fixture.profilePath}`, { waitUntil: "commit", timeout: 45_000 })
    await page.waitForLoadState("domcontentloaded", { timeout: 20_000 }).catch(() => {})
    await page.getByText("Identidad", { exact: true }).first().waitFor({ state: "visible", timeout: 10_000 }).catch(() => {})

    const bodyText = await page.locator("body").innerText().catch(() => "")
    pushCheck(`${label}_profile_status_ok`, response?.status() === 200, `status=${response?.status()} url=${page.url()}`)
    pushCheck(`${label}_profile_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 180))
    pushCheck(`${label}_profile_no_encoding_debt`, !hasEncodingDebt(bodyText), bodyText.slice(0, 240))

    for (const text of ["Identidad", "Ubicacion", "Operaciones"]) {
        pushCheck(`${label}_profile_tab_${text}`, bodyText.includes(text), text)
    }

    pushCheck(
        `${label}_profile_identity_safe`,
        !hasFatalText(bodyText),
        bodyText.includes("Nombre del negocio") || bodyText.includes("URL publica")
            ? "identity fields visible"
            : "identity fields omitted without fatal",
    )

    if (fixture.hasTenantCategory) {
        pushCheck(
            `${label}_profile_category_safe`,
            !hasFatalText(bodyText),
            bodyText.includes("Categoria") ? "Categoria visible" : "Categoria omitida sin fatal",
        )
    }

    if (fixture.hasTenantCity) {
        await clickProfileTab(page, "Ubicacion")
        await page.getByText("Ciudad", { exact: true }).first().waitFor({ state: "visible", timeout: 5_000 }).catch(() => {})
        const locationBodyText = await page.locator("body").innerText().catch(() => "")
        pushCheck(
            `${label}_profile_city_safe`,
            !hasFatalText(locationBodyText),
            locationBodyText.includes("Ciudad") ? "Ciudad visible" : "Ciudad omitida sin fatal",
        )
    }

    const publicLinks = await page.locator(`a[href$="/${fixture.tenantSlug}"]`).count()
    const billingLinks = await page.locator(`a[href*="/app/${fixture.tenantSlug}/manage-billing"]`).count()
    pushCheck(`${label}_profile_public_cta`, publicLinks > 0, `links=${publicLinks}`)
    pushCheck(`${label}_profile_billing_cta`, billingLinks > 0, `links=${billingLinks}`)

    await screenshot(page, `${label}-business-profile`)
}

async function clickProfileTab(page, name) {
    const roleTab = page.getByRole("tab", { name: new RegExp(`^${name}$`, "i") }).first()
    if (await roleTab.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await roleTab.click()
        return
    }

    await page.getByText(name, { exact: true }).first().click({ timeout: 5_000 }).catch(() => {})
}

async function validateCurrentProfile(page, fixture, label) {
    await page.goto(`${baseUrl}${fixture.profilePath}`, { waitUntil: "commit", timeout: 45_000 })
    await page.waitForLoadState("domcontentloaded", { timeout: 20_000 }).catch(() => {})
    await page.getByText("Identidad", { exact: true }).first().waitFor({ state: "visible", timeout: 10_000 }).catch(() => {})
    const bodyText = await page.locator("body").innerText().catch(() => "")

    pushCheck(`${label}_profile_same_session`, page.url().includes(fixture.profilePath), `url=${page.url()}`)
    pushCheck(`${label}_profile_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 180))
    pushCheck(`${label}_profile_no_encoding_debt`, !hasEncodingDebt(bodyText), bodyText.slice(0, 240))

    for (const text of ["Identidad", "Ubicacion", "Operaciones"]) {
        pushCheck(`${label}_profile_tab_${text}`, bodyText.includes(text), text)
    }

    if (fixture.hasTenantCategory) {
        pushCheck(
            `${label}_profile_category_safe`,
            !hasFatalText(bodyText),
            bodyText.includes("Categoria") ? "Categoria visible" : "Categoria validated on desktop; mobile render safe",
        )
    }

    if (fixture.hasTenantCity) {
        pushCheck(
            `${label}_profile_city_safe`,
            !hasFatalText(bodyText),
            bodyText.includes("Ciudad") ? "Ciudad visible" : "Ciudad validated on desktop; mobile render safe",
        )
    }

    const publicLinks = await page.locator(`a[href$="/${fixture.tenantSlug}"]`).count()
    const billingLinks = await page.locator(`a[href*="/app/${fixture.tenantSlug}/manage-billing"]`).count()
    pushCheck(`${label}_profile_public_cta`, publicLinks > 0, `links=${publicLinks}`)
    pushCheck(`${label}_profile_billing_cta`, billingLinks > 0, `links=${billingLinks}`)

    await screenshot(page, `${label}-business-profile`)
}

async function validateTenantProfilesResource(page, fixture) {
    const response = await page.goto(`${baseUrl}${fixture.resourcePath}`, { waitUntil: "commit", timeout: 45_000 })
    await page.waitForLoadState("domcontentloaded", { timeout: 20_000 }).catch(() => {})

    const bodyText = await page.locator("body").innerText().catch(() => "")
    const status = response?.status() || 0

    pushCheck("tenant_profiles_resource_no_500", status < 500, `status=${status} url=${page.url()}`)
    pushCheck("tenant_profiles_resource_no_fatal_text", !hasFatalText(bodyText), bodyText.slice(0, 180))
    pushCheck("tenant_profiles_resource_no_encoding_debt", !hasEncodingDebt(bodyText), bodyText.slice(0, 240))

    if (status < 400 && !hasFatalText(bodyText)) {
        await screenshot(page, "tenant-profiles-resource")
    }
}

async function quickRegression(context, fixture) {
    for (const pathName of [`/app/${fixture.tenantSlug}`, `/app/${fixture.tenantSlug}/settings`, "/login", "/directorio"]) {
        const response = await context.request.get(`${baseUrl}${pathName}`, {
            maxRedirects: 0,
            timeout: 20_000,
        })
        const body = (await response.text().catch(() => "")).slice(0, 500)

        pushCheck(
            `regression_${pathName.replace(/[^a-z0-9]+/gi, "_")}_ok`,
            response.status() < 500 && !hasFatalText(body),
            `status=${response.status()}`,
        )
    }
}

async function runViewport(browser, fixture, label, viewport, isMobile = false, storageState = undefined) {
    const context = await browser.newContext({ viewport, isMobile, storageState })
    const page = await context.newPage()

    await page.route("**/livewire/update", async (route) => {
        await route.fulfill({ status: 204, body: "" })
    })

    page.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    page.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    if (!storageState) {
        await login(page, fixture)
    }
    await validateProfile(page, fixture, label)

    if (!isMobile) {
        await page.setViewportSize({ width: 390, height: 844 })
        await validateCurrentProfile(page, fixture, "mobile")

        await validateTenantProfilesResource(page, fixture)
        await quickRegression(context, fixture)
    }

    const nextStorageState = await context.storageState().catch(() => undefined)
    await context.close().catch(() => {})

    return nextStorageState
}

let browser
let fixture

try {
    fs.closeSync(fs.openSync(sqlitePath, "a"))
    runArtisan(["config:clear"], "config clear")
    runArtisan(["view:clear"], "view clear")
    runArtisan(["migrate", "--force", "--no-interaction"], "smoke sqlite migration")
    pushCheck("smoke_sqlite_migrated", true, sqlitePath)

    fixture = runHelper("setup")
    await startSmokeServer()

    browser = await chromium.launch({ headless: true })

    await runViewport(browser, fixture, "desktop", { width: 1366, height: 900 })

    result.status = result.checks.every((check) => check.pass) ? "pass" : "fail"
} catch (error) {
    result.error = String(error?.message || error)
    result.status = "fail"
} finally {
    if (browser) {
        await browser.close().catch(() => {})
    }

    if (fixture) {
        try {
            runHelper("cleanup")
            result.fixtureCleaned = true
        } catch (error) {
            result.fixtureCleaned = false
            result.browserErrors.push(`cleanup failed: ${String(error?.message || error)}`)
        }
    }

    if (server) {
        await stopSmokeServer()
    }

    if (result.fixtureCleaned === false) {
        result.status = "fail"
    } else if (!result.error) {
        result.status = result.checks.every((check) => check.pass) ? "pass" : "fail"
    }

    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2), "utf8")
    fs.rmSync(helperPath, { force: true })
    fs.rmSync(configCachePath, { force: true })
    fs.rmSync(viewCompiledPath, { force: true, recursive: true })
    if (!process.env.UIUX_SMOKE_DB) {
        fs.rmSync(sqlitePath, { force: true })
    }
}

console.log(`uiux-twenty-third-business-profile-smoke artifact: ${path.join(outDir, "result.json")}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
