import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8016)
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-dashboard-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot = process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/dashboard-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))

fs.mkdirSync(outDir, { recursive: true })

const helperPath = path.join(os.tmpdir(), `conecta-uiux-eleventh-dashboard-helper-${process.pid}.php`)
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
        'name' => 'Smoke Dashboard',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Dashboard',
        'slug' => $runId,
        'user_id' => $user->id,
    ];

    foreach ([
        'is_active' => true,
        'status' => 'active',
        'plan_type' => 'semilla',
        'subscription_status' => 'active',
        'whatsapp_number' => '99999999',
        'business_type' => 'retail',
        'description' => 'Fixture temporal para smoke de dashboard.',
    ] as $column => $value) {
        if (Schema::hasColumn('tenants', $column)) {
            $tenantData[$column] = $value;
        }
    }

    $tenant = new Tenant();
    $tenant->forceFill($tenantData);
    Tenant::withoutEvents(fn () => $tenant->save());

    $user->forceFill(['tenant_id' => $tenant->id])->save();

    echo json_encode([
        'email' => $email,
        'password' => $password,
        'userId' => $user->id,
        'tenantSlug' => $tenant->slug,
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

    return JSON.parse(helper.stdout)
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

async function assertRoute(page, pathName, label) {
    const response = await page.goto(`${baseUrl}${pathName}`, { waitUntil: "domcontentloaded", timeout: 45_000 })
    const text = await page.locator("body").innerText().catch(() => "")
    pushCheck(`${label}_status_ok`, (response?.status() || 0) < 500, `status=${response?.status()}`)
    pushCheck(`${label}_no_fatal_text`, !hasFatalText(text), text.slice(0, 160))
}

async function login(page, fixture) {
    const loginResponse = await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded" })
    pushCheck("login_route_200", loginResponse?.status() === 200, `status=${loginResponse?.status()}`)

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

    pushCheck("login_fixture_fetch_ok", authStatus.status < 500, `status=${authStatus.status} url=${authStatus.url}`)
}

async function validateDashboard(page, label) {
    const response = await page.goto(`${baseUrl}/dashboard`, { waitUntil: "domcontentloaded" })
    const text = await page.locator("body").innerText().catch(() => "")
    pushCheck(`${label}_dashboard_200`, response?.status() === 200, `status=${response?.status()}`)
    pushCheck(`${label}_dashboard_no_fatal_text`, !hasFatalText(text), text.slice(0, 160))
    pushCheck(`${label}_placeholder_removed`, !/You're logged in!/i.test(text), text.slice(0, 160))
    await page.getByTestId("dashboard-page").waitFor({ state: "visible", timeout: 10_000 })
    pushCheck(`${label}_profile_cta_visible`, await page.getByTestId("dashboard-profile-cta").isVisible().catch(() => false))
    pushCheck(`${label}_directory_cta_visible`, await page.getByTestId("dashboard-directory-cta").isVisible().catch(() => false))
    pushCheck(`${label}_panel_cta_visible`, await page.getByTestId("dashboard-panel-cta").isVisible().catch(() => false))

    const profileHref = await page.getByTestId("dashboard-profile-cta").getAttribute("href")
    const directoryHref = await page.getByTestId("dashboard-directory-cta").getAttribute("href")
    const panelHref = await page.getByTestId("dashboard-panel-cta").getAttribute("href")
    pushCheck(`${label}_profile_cta_href`, profileHref === "/mi-cuenta", profileHref || "")
    pushCheck(`${label}_directory_cta_href`, directoryHref === "/directorio", directoryHref || "")
    pushCheck(`${label}_panel_cta_href`, Boolean(panelHref && panelHref !== "#"), panelHref || "")
}

let server
let browser
let fixture

try {
    spawnSync(phpBin, ["artisan", "config:clear"], { cwd: root, encoding: "utf8", windowsHide: true })
    spawnSync(phpBin, ["artisan", "view:clear"], { cwd: root, encoding: "utf8", windowsHide: true })
    spawnSync(phpBin, ["artisan", "cache:clear"], { cwd: root, encoding: "utf8", windowsHide: true })

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

    for (const page of [desktopPage]) {
        page.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
        page.on("console", (message) => {
            if (["error", "warning"].includes(message.type())) {
                result.browserErrors.push(`${message.type()}: ${message.text()}`)
            }
        })
    }

    await login(desktopPage, fixture)
    await validateDashboard(desktopPage, "desktop")
    await screenshot(desktopPage, "dashboard-desktop")

    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })
    const mobilePage = await mobileContext.newPage()
    mobilePage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    mobilePage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await login(mobilePage, fixture)
    await validateDashboard(mobilePage, "mobile")
    await screenshot(mobilePage, "dashboard-mobile")

    const publicContext = await browser.newContext({ viewport: { width: 1366, height: 900 } })
    const publicPage = await publicContext.newPage()
    publicPage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    publicPage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await assertRoute(publicPage, "/", "home")
    await assertRoute(publicPage, "/explore", "explore")
    await assertRoute(publicPage, "/directorio", "directory")
    await assertRoute(publicPage, "/login", "login_regression")
    await assertRoute(publicPage, "/verify-email", "verify_email")
    await assertRoute(desktopPage, "/mi-cuenta", "profile")

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
        } catch (error) {
            result.browserErrors.push(`cleanup failed: ${String(error?.message || error)}`)
        }
    }

    if (server) {
        server.kill()
    }

    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2))
    fs.rmSync(helperPath, { force: true })
}

process.exit(result.status === "pass" ? 0 : 1)
