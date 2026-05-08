import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const defaultPort = 8019 + Math.floor(Math.random() * 300)
const port = Number(process.env.UIUX_SMOKE_PORT || defaultPort)
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-settings-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/settings-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))

fs.mkdirSync(outDir, { recursive: true })

const helperPath = path.join(os.tmpdir(), `conecta-uiux-fourteenth-settings-helper-${process.pid}.php`)
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

function ensureGeneralSettings(): void {
    if (! Schema::hasTable('settings')) {
        return;
    }

    $defaults = [
        'pro_plan_price' => 500.00,
        'grace_period_days' => 3,
        'free_plan_product_limit' => 10,
        'pro_plan_product_limit' => 100,
        'bank_transfer_instructions' => '<p>Depositar a Cuenta BAC Honduras #123456789.</p>',
        'support_whatsapp_number' => '+50499999999',
        'support_email' => 'soporte@conectahn.com',
        'maintenance_mode' => false,
        'allow_registrations' => true,
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

if ($mode === 'setup') {
    ensureGeneralSettings();

    $email = $runId.'@example.test';
    $password = 'Password123!';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Settings Owner',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Settings Canonico',
        'slug' => $runId,
        'description' => 'Fixture temporal para smoke de settings.',
        'user_id' => $user->id,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => 'pro',
        'subscription_status' => 'active',
        'phone' => '22223333',
        'whatsapp_number' => '99999999',
        'business_type' => 'retail',
    ];
    $tenantData = array_filter(
        $tenantData,
        fn ($value, string $key): bool => Schema::hasColumn('tenants', $key),
        ARRAY_FILTER_USE_BOTH
    );

    if (Schema::hasColumn('tenants', 'about')) {
        $tenantData['about'] = 'Negocio temporal para validar la pantalla de configuracion.';
    }

    if (Schema::hasColumn('tenants', 'address')) {
        $tenantData['address'] = 'Tegucigalpa';
    }

    if (Schema::hasColumn('tenants', 'schedule')) {
        $tenantData['schedule'] = [
            'monday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
            'tuesday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
            'wednesday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
            'thursday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
            'friday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
            'saturday' => ['isOpen' => false, 'open' => null, 'close' => null],
            'sunday' => ['isOpen' => false, 'open' => null, 'close' => null],
        ];
    }

    if (Schema::hasColumn('tenants', 'settings')) {
        $tenantData['settings'] = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
                'tuesday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
                'wednesday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
                'thursday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
                'friday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
                'saturday' => ['isOpen' => false, 'open' => null, 'close' => null],
                'sunday' => ['isOpen' => false, 'open' => null, 'close' => null],
            ],
        ];
    }

    $tenant = new Tenant();
    $tenant->forceFill($tenantData);
    Tenant::withoutEvents(fn () => $tenant->save());

    $user->forceFill(['tenant_id' => $tenant->id])->save();

    echo json_encode([
        'email' => $email,
        'password' => $password,
        'tenantId' => $tenant->id,
        'tenantSlug' => $tenant->slug,
        'panelPath' => '/app/'.$tenant->slug,
        'panelSettingsPath' => '/app/'.$tenant->slug.'/settings',
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    Tenant::withoutGlobalScopes()->where('slug', $runId)->forceDelete();
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
    await page.screenshot({ path: file, fullPage: true })
    result.screenshots[name] = file
}

function hasFatalText(bodyText) {
    return /server error|whoops|exception|stack trace|sqlstate|fatal error/i.test(bodyText)
}

async function login(page, fixture) {
    const loginResponse = await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded" })
    pushCheck("login_route_200", loginResponse?.status() === 200, `status=${loginResponse?.status()}`)

    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute("content")
    const authStatus = await page.evaluate(
        async ({ loginUrl, email, password, token }) => {
            const body = new URLSearchParams()
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
                redirect: "manual",
            })

            return response.status
        },
        { loginUrl: `${baseUrl}/login`, email: fixture.email, password: fixture.password, token: csrfToken },
    )

    pushCheck("login_fixture_post_redirects", [0, 302, 303].includes(authStatus), `status=${authStatus}`)
}

async function validateSettings(page, fixture, label) {
    const response = await page.goto(`${baseUrl}${fixture.panelSettingsPath}`, { waitUntil: "commit", timeout: 45_000 })
    await page.waitForLoadState("domcontentloaded", { timeout: 20_000 }).catch(() => {})
    const bodyText = await page.locator("body").innerText().catch(() => "")
    const currentUrl = page.url()

    pushCheck(`${label}_settings_status_ok`, (response?.status() || 0) < 500, `status=${response?.status()} url=${currentUrl}`)
    pushCheck(`${label}_settings_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 160))

    if (currentUrl.includes(fixture.panelSettingsPath)) {
        pushCheck(`${label}_settings_redirects_to_canonical_panel`, true, currentUrl)
        pushCheck(`${label}_settings_panel_active`, bodyText.length > 0, bodyText.slice(0, 160))
        return "panel"
    }

    if (currentUrl.includes("/app/login")) {
        pushCheck(`${label}_settings_redirects_to_canonical_panel_login`, true, currentUrl)
        pushCheck(`${label}_settings_panel_login_visible`, bodyText.length > 0, bodyText.slice(0, 160))
        return "panel"
    }

    await page.getByTestId("settings-page").waitFor({ state: "visible", timeout: 10_000 })
    pushCheck(`${label}_settings_page_visible`, await page.getByTestId("settings-page").isVisible().catch(() => false))
    pushCheck(`${label}_settings_profile_tab_visible`, await page.getByTestId("settings-profile-tab").isVisible().catch(() => false))
    pushCheck(`${label}_settings_schedule_tab_visible`, await page.getByTestId("settings-schedule-tab").isVisible().catch(() => false))
    pushCheck(`${label}_settings_security_tab_visible`, await page.getByTestId("settings-security-tab").isVisible().catch(() => false))
    pushCheck(`${label}_settings_profile_form_visible`, await page.getByTestId("settings-profile-form").isVisible().catch(() => false))

    await page.getByTestId("settings-schedule-tab").click()
    pushCheck(`${label}_settings_schedule_form_visible`, await page.getByTestId("settings-schedule-form").isVisible().catch(() => false))
    pushCheck(`${label}_settings_monday_switch_visible`, await page.getByTestId("settings-monday-switch").isVisible().catch(() => false))

    await page.getByTestId("settings-security-tab").click()
    pushCheck(`${label}_settings_security_form_visible`, await page.getByTestId("settings-security-form").isVisible().catch(() => false))

    return "legacy-react"
}

async function assertHttpRoute(pathName, label) {
    const response = await fetch(`${baseUrl}${pathName}`, { redirect: "follow" })
    const text = await response.text().catch(() => "")
    pushCheck(`${label}_status_ok`, response.status < 500, `status=${response.status}`)
    pushCheck(`${label}_no_fatal_text`, !hasFatalText(text), text.slice(0, 160))
}

let server
let browser
let fixture

try {
    spawnSync(phpBin, ["artisan", "view:clear"], { cwd: root, encoding: "utf8", windowsHide: true })

    if (shouldStartServer) {
        server = spawn(phpBin, ["artisan", "serve", "--host", host, "--port", String(port)], {
            cwd: root,
            stdio: ["ignore", "pipe", "pipe"],
            windowsHide: true,
        })
    }

    await waitForServer()
    fixture = runHelper("setup")

    browser = await chromium.launch({ headless: true })

    const desktopContext = await browser.newContext({ viewport: { width: 1366, height: 900 } })
    const desktopPage = await desktopContext.newPage()
    desktopPage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    desktopPage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await login(desktopPage, fixture)
    const desktopSurface = await validateSettings(desktopPage, fixture, "desktop")
    await screenshot(desktopPage, desktopSurface === "panel" ? "settings-panel-desktop" : "settings-desktop")

    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })
    const mobilePage = await mobileContext.newPage()
    mobilePage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    mobilePage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await login(mobilePage, fixture)
    const mobileSurface = await validateSettings(mobilePage, fixture, "mobile")
    await screenshot(mobilePage, mobileSurface === "panel" ? "settings-panel-mobile" : "settings-mobile")

    await assertHttpRoute("/dashboard", "dashboard")
    await assertHttpRoute("/mi-cuenta", "profile")
    await assertHttpRoute("/directorio", "directory")
    await assertHttpRoute("/explore", "explore")
    await assertHttpRoute("/login", "login_regression")
    await assertHttpRoute("/verify-email", "verify_email")

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
        server.kill()
    }

    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2), "utf8")
    fs.rmSync(helperPath, { force: true })
}

console.log(`uiux-fourteenth-settings-smoke artifact: ${path.join(outDir, "result.json")}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
