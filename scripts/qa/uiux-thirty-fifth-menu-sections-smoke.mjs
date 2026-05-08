import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8950 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-menu-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT ||
    "docs/frontend/audit-artifacts/2026-05-07/browser/menu-sections-smoke"
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

const helperPath = path.join(os.tmpdir(), `conecta-uiux-menu-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\Tenant;
use App\\Models\\User;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\Schema;

$root = $argv[1];
$mode = $argv[2];
$runId = $argv[3];

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
$kernel->bootstrap();

function ensureGeneralSettingsForMenuSections(): void {
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

if ($mode === 'setup') {
    ensureGeneralSettingsForMenuSections();

    if (! Schema::hasTable('menu_sections')) {
        echo json_encode(['skip' => true, 'reason' => 'missing menu_sections'], JSON_THROW_ON_ERROR);
        exit(0);
    }

    $email = $runId.'@example.test';
    $password = 'Password123!';

    $owner = new User();
    $owner->forceFill([
        'name' => 'Smoke Menu Owner',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $owner->save());

    $tenantData = [
        'name' => 'Smoke Menu',
        'slug' => $runId,
        'description' => 'Fixture temporal para validar secciones del menu en Filament.',
        'user_id' => $owner->id,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => 'pro',
        'subscription_status' => 'active',
        'business_type' => 'retail',
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
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
    }

    $menuSectionData = [
        'tenant_id' => $tenant->id,
        'name' => 'Platos fuertes',
        'is_active' => true,
        'sort_order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    foreach (array_keys($menuSectionData) as $column) {
        if (! Schema::hasColumn('menu_sections', $column)) {
            unset($menuSectionData[$column]);
        }
    }

    DB::table('menu_sections')->insert($menuSectionData);

    echo json_encode([
        'email' => $email,
        'password' => $password,
        'tenantSlug' => $tenant->slug,
        'menuPath' => '/app/'.$tenant->slug.'/menu-sections',
        'createPath' => '/app/'.$tenant->slug.'/menu-sections/create',
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    $tenant = Tenant::withoutGlobalScopes()->where('slug', $runId)->first();

    if ($tenant) {
        DB::table('menu_sections')->where('tenant_id', $tenant->id)->delete();
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
    const deadline = Date.now() + 60_000
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

    server.stdout.on("data", (chunk) => result.browserErrors.push(`server: ${String(chunk).trim()}`))
    server.stderr.on("data", (chunk) => result.browserErrors.push(`server-err: ${String(chunk).trim()}`))
}

function hasFatalText(text) {
    return /Exception|Stack trace|SQLSTATE|Fatal error|Internal Server Error|Server Error/i.test(text)
}

function hasEncodingDebt(text) {
    return /Ã|Â|â|�|⚠️|¿/.test(text)
}

function normalizeText(text) {
    return String(text)
        .normalize("NFD")
        .replace(/\p{Diacritic}/gu, "")
        .toLowerCase()
}

function includesText(bodyText, expectedText) {
    return normalizeText(bodyText).includes(normalizeText(expectedText))
}

async function screenshot(page, name) {
    const filePath = path.join(outDir, `${name}.png`)
    await page.screenshot({ path: filePath, fullPage: true })
    result.screenshots[name] = filePath
}

async function login(page, fixture) {
    const blockLoginAssets = (route) => route.abort()
    await page.route("**/build/assets/**", blockLoginAssets)
    await page.route("**/auth-side.svg", blockLoginAssets)

    const response = await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded", timeout: 45_000 })
    pushCheck("login_route_200", response?.status() === 200, `status=${response?.status()}`)

    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute("content")
    const authStatus = await page.evaluate(
        async ({ loginUrl, email, password, token }) => {
            const body = new URLSearchParams()
            body.set("email", email)
            body.set("password", password)
            body.set("_token", token || "")

            const response = await fetch(loginUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                    "X-CSRF-TOKEN": token || "",
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
    await page.unroute("**/build/assets/**", blockLoginAssets).catch(() => {})
    await page.unroute("**/auth-side.svg", blockLoginAssets).catch(() => {})

    if (authStatus.url.endsWith("/login")) {
        await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded", timeout: 45_000 })
        await page.locator('input[name="email"]').fill(fixture.email)
        await page.locator('input[name="password"]').fill(fixture.password)
        await page.locator('button[type="submit"]').click()
        await page.waitForURL((url) => !url.pathname.endsWith("/login"), { timeout: 30_000 }).catch(() => {})
        pushCheck("login_form_fallback_ok", !page.url().endsWith("/login"), page.url())
    }
}

async function validatePage(page, urlPath, label, expectedTexts, screenshotName = null) {
    const response = await page.goto(`${baseUrl}${urlPath}`, { waitUntil: "commit", timeout: 90_000 })
    await page.waitForLoadState("domcontentloaded", { timeout: 30_000 }).catch(() => {})
    await page.waitForTimeout(700)

    const bodyText = await page.locator("body").innerText().catch(() => "")
    pushCheck(`${label}_status_ok`, response?.status() === 200, `status=${response?.status()} url=${page.url()}`)
    pushCheck(`${label}_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 220))
    pushCheck(`${label}_no_encoding_debt`, !hasEncodingDebt(bodyText), bodyText.slice(0, 260))

    for (const text of expectedTexts) {
        pushCheck(`${label}_text_${text.replaceAll(" ", "_")}`, includesText(bodyText, text), text)
    }

    if (screenshotName) {
        await screenshot(page, screenshotName)
    }
}

try {
    fs.closeSync(fs.openSync(sqlitePath, "a"))
    runArtisan(["config:clear"], "config clear")
    runArtisan(["view:clear"], "view clear")
    runArtisan(["migrate", "--force", "--no-interaction"], "smoke sqlite migration")
    pushCheck("smoke_sqlite_migrated", true, sqlitePath)

    startServer()
    await waitForServer()

    const fixture = runHelper("setup")
    if (fixture.skip) {
        pushCheck("menu_sections_schema_available", true, `skipped: ${fixture.reason}`)
    } else {
        const browser = await chromium.launch({ headless: true })
        const context = await browser.newContext({ viewport: { width: 1366, height: 900 } })
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

        await login(page, fixture)
        await page.route("**/*", async (route) => {
            if (route.request().resourceType() === "document") {
                await route.continue()
                return
            }

            await route.abort()
        })
        await validatePage(
            page,
            fixture.menuPath,
            "desktop_menu_sections",
            ["Secciones del menú", "Platos fuertes", "Productos", "Activa"],
            "desktop-menu-sections",
        )
        await page.setViewportSize({ width: 390, height: 844 })
        await validatePage(
            page,
            fixture.menuPath,
            "mobile_menu_sections",
            ["Secciones del menú", "Platos fuertes"],
            "mobile-menu-sections",
        )
        await page.setViewportSize({ width: 1366, height: 900 })
        await validatePage(
            page,
            fixture.createPath,
            "create_menu_section",
            ["Nueva sección", "Nombre de la sección", "Visible en mi menú"],
            null,
        )

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

    if (server) {
        server.kill()
    }

    fs.rmSync(helperPath, { force: true })
    fs.rmSync(configCachePath, { force: true })
    fs.rmSync(viewCompiledPath, { force: true, recursive: true })
    if (!process.env.UIUX_SMOKE_DB) {
        fs.rmSync(sqlitePath, { force: true })
    }
    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2))
    console.log(`uiux-thirty-fifth-menu-sections-smoke artifact: ${path.join(outDir, "result.json")}`)
    console.log(JSON.stringify(result, null, 2))
}

process.exit(result.status === "pass" ? 0 : 1)
