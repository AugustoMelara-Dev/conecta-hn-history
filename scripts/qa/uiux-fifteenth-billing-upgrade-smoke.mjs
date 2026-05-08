import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8110 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-billing-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/panel-billing-upgrade-smoke"
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

const helperPath = path.join(os.tmpdir(), `conecta-uiux-fifteenth-billing-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\SubscriptionPayment;
use App\\Models\\Tenant;
use App\\Models\\User;
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

function makeBillingFixture(string $runId, string $suffix, string $planType, bool $withPending = false): array {
    $email = $runId.'-'.$suffix.'@example.test';
    $password = 'Password123!';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Billing '.strtoupper($suffix),
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Billing '.strtoupper($suffix),
        'slug' => $runId.'-'.$suffix,
        'user_id' => $user->id,
        'is_active' => true,
        'plan_type' => $planType,
        'subscription_status' => 'active',
        'whatsapp_number' => '99999999',
        'business_type' => 'retail',
    ];

    if (Schema::hasColumn('tenants', 'status')) {
        $tenantData['status'] = 'active';
    }

    if (Schema::hasColumn('tenants', 'subscription_ends_at')) {
        $tenantData['subscription_ends_at'] = $planType === 'pro' ? now()->addDays(21) : null;
    }

    if (Schema::hasColumn('tenants', 'is_pro')) {
        $tenantData['is_pro'] = $planType === 'pro';
    }

    foreach (['is_active', 'plan_type', 'subscription_status', 'whatsapp_number', 'business_type'] as $column) {
        if (! Schema::hasColumn('tenants', $column)) {
            unset($tenantData[$column]);
        }
    }

    $tenant = new Tenant();
    $tenant->forceFill($tenantData);
    Tenant::withoutEvents(fn () => $tenant->save());

    $user->forceFill(['tenant_id' => $tenant->id])->save();

    $supportsPayments = Schema::hasTable('subscription_payments');

    if ($withPending && $supportsPayments) {
        SubscriptionPayment::forceCreate([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'idempotency_key' => 'smoke-'.$suffix.'-'.$runId,
            'amount' => 99.00,
            'bank' => 'bank_transfer',
            'reference_number' => 'SMOKE-'.$suffix.'-'.substr($runId, -6),
            'proof_path' => 'payments/proofs/smoke-'.$suffix.'.png',
            'status' => SubscriptionPayment::STATUS_PENDING,
        ]);
    }

    if ($planType === 'pro' && $supportsPayments) {
        SubscriptionPayment::forceCreate([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'idempotency_key' => 'smoke-pro-'.$runId,
            'amount' => 99.00,
            'bank' => 'bank_transfer',
            'reference_number' => 'SMOKE-PRO-'.substr($runId, -6),
            'proof_path' => 'payments/proofs/smoke-pro.png',
            'status' => SubscriptionPayment::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    return [
        'email' => $email,
        'password' => $password,
        'tenantId' => $tenant->id,
        'tenantSlug' => $tenant->slug,
        'panelPath' => '/app/'.$tenant->slug,
        'settingsPath' => '/app/'.$tenant->slug.'/settings',
        'billingPath' => '/app/'.$tenant->slug.'/manage-billing',
        'upgradePath' => '/app/'.$tenant->slug.'/upgrade-to-pro',
        'supportsPayments' => $supportsPayments,
        'paths' => [
            'products' => '/app/'.$tenant->slug.'/products',
            'orders' => '/app/'.$tenant->slug.'/orders',
            'marketing' => '/app/'.$tenant->slug.'/manage-marketing',
            'billing' => '/app/'.$tenant->slug.'/manage-billing',
        ],
    ];
}

if ($mode === 'setup') {
    echo json_encode([
        'free' => makeBillingFixture($runId, 'free', 'semilla'),
        'pending' => makeBillingFixture($runId, 'pending', 'semilla', true),
        'pro' => makeBillingFixture($runId, 'pro', 'pro'),
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    $tenantIds = Schema::hasTable('tenants')
        ? Tenant::withoutGlobalScopes()->where('slug', 'like', $runId.'%')->pluck('id')
        : collect();

    if ($tenantIds->isNotEmpty() && Schema::hasTable('subscription_payments')) {
        SubscriptionPayment::withoutGlobalScopes()->whereIn('tenant_id', $tenantIds)->forceDelete();
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
        throw new Error(`${mode} helper did not return JSON:\nSTDOUT:\n${helper.stdout}\nSTDERR:\n${helper.stderr}`)
    }

    return JSON.parse(output.slice(jsonStart, jsonEnd + 1))
}

async function waitForServer() {
    const deadline = Date.now() + 30_000
    let lastError = null

    while (Date.now() < deadline) {
        try {
            const response = await fetch(`${baseUrl}/up`)
            if (response.status < 500) return
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

async function login(page, fixture, label) {
    const loginResponse = await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded" })
    pushCheck(`${label}_login_route_200`, loginResponse?.status() === 200, `status=${loginResponse?.status()}`)

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

    pushCheck(`${label}_login_fixture_fetch_ok`, authStatus.status < 500, `status=${authStatus.status} url=${authStatus.url}`)
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
    await new Promise((resolve) => setTimeout(resolve, 2_000))
}

async function validateCanonicalRedirect(page, sourcePath, expectedPath, label) {
    const result = await page.evaluate(async ({ url }) => {
        const response = await fetch(url, {
            credentials: "same-origin",
            redirect: "follow",
            headers: {
                Accept: "text/html, application/xhtml+xml",
                "X-Requested-With": "XMLHttpRequest",
            },
        })

        return { status: response.status, url: response.url }
    }, { url: `${baseUrl}${sourcePath}` })
    const finalUrl = new URL(result.url)

    pushCheck(`${label}_redirect_status_ok`, result.status < 500, `status=${result.status}`)
    pushCheck(`${label}_redirects_to_panel`, finalUrl.pathname === expectedPath, finalUrl.pathname)
}

async function validatePanelShell(page, pathName, label, testId, expectedText) {
    const response = await page.goto(`${baseUrl}${pathName}`, { waitUntil: "commit", timeout: 45_000 })
    await page.waitForLoadState("domcontentloaded", { timeout: 20_000 }).catch(() => {})
    const bodyText = await page.locator("body").innerText().catch(() => "")

    pushCheck(`${label}_status_ok`, (response?.status() || 0) < 500, `status=${response?.status()} url=${page.url()}`)
    pushCheck(`${label}_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 160))

    if (testId) {
        await page.getByTestId(testId).waitFor({ state: "visible", timeout: 15_000 })
        pushCheck(`${label}_${testId}_visible`, await page.getByTestId(testId).isVisible().catch(() => false))
    }

    pushCheck(`${label}_expected_copy`, bodyText.includes(expectedText), expectedText)
}

async function validateBillingState(page, fixture, label, testId, expectedText) {
    const response = await page.goto(`${baseUrl}${fixture.billingPath}`, { waitUntil: "commit", timeout: 45_000 })
    await page.waitForLoadState("domcontentloaded", { timeout: 20_000 }).catch(() => {})
    const bodyText = await page.locator("body").innerText().catch(() => "")

    pushCheck(`${label}_billing_status_ok`, (response?.status() || 0) < 500, `status=${response?.status()} url=${page.url()}`)
    pushCheck(`${label}_billing_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 160))
    await page.getByTestId("billing-page").waitFor({ state: "visible", timeout: 15_000 })
    pushCheck(`${label}_billing_page_visible`, await page.getByTestId("billing-page").isVisible().catch(() => false))
    pushCheck(`${label}_${testId}_visible`, await page.getByTestId(testId).isVisible().catch(() => false))
    pushCheck(`${label}_billing_expected_copy`, bodyText.includes(expectedText), expectedText)
}

async function validateUpgrade(page, fixture, label) {
    const response = await page.goto(`${baseUrl}${fixture.upgradePath}`, { waitUntil: "commit", timeout: 45_000 })
    await page.waitForLoadState("domcontentloaded", { timeout: 20_000 }).catch(() => {})
    const bodyText = await page.locator("body").innerText().catch(() => "")

    pushCheck(`${label}_upgrade_status_ok`, (response?.status() || 0) < 500, `status=${response?.status()} url=${page.url()}`)
    pushCheck(`${label}_upgrade_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 160))
    await page.getByTestId("upgrade-page").waitFor({ state: "visible", timeout: 15_000 })
    pushCheck(`${label}_upgrade_page_visible`, await page.getByTestId("upgrade-page").isVisible().catch(() => false))
    pushCheck(`${label}_upgrade_comparison_visible`, await page.getByTestId("upgrade-comparison").isVisible().catch(() => false))

    const ctaHref = await page.getByTestId("upgrade-whatsapp-cta").getAttribute("href").catch(() => "")
    pushCheck(`${label}_upgrade_whatsapp_href`, Boolean(ctaHref?.startsWith("https://wa.me/504") && ctaHref.includes("Conecta")), ctaHref || "")
}

async function validateProDoesNotShowUpgradeCta(page, fixture) {
    const currentPath = new URL(page.url()).pathname
    let response = null

    if (currentPath !== fixture.billingPath) {
        response = await page.goto(`${baseUrl}${fixture.billingPath}`, { waitUntil: "domcontentloaded", timeout: 45_000 })
    } else {
        await page.waitForLoadState("domcontentloaded", { timeout: 20_000 }).catch(() => {})
    }

    const bodyText = await page.locator("body").innerText().catch(() => "")
    pushCheck("pro_billing_status_ok", response ? (response.status() || 0) < 500 : true, response ? `status=${response.status()}` : "already on billing page")
    pushCheck("pro_billing_no_upgrade_cta", !bodyText.includes("Contactar para activar PRO"), bodyText.slice(0, 160))
}

let server
let browser
let fixtures

try {
    spawnSync(phpBin, ["artisan", "view:clear"], {
        cwd: root,
        env: smokeEnv,
        encoding: "utf8",
        windowsHide: true,
    })

    if (shouldStartServer) {
        fs.writeFileSync(sqlitePath, "", { flag: "a" })

        const migrate = spawnSync(phpBin, ["artisan", "migrate", "--force", "--no-interaction"], {
            cwd: root,
            env: smokeEnv,
            encoding: "utf8",
            windowsHide: true,
        })

        if (migrate.status !== 0) {
            throw new Error(`smoke sqlite migration failed:\nSTDOUT:\n${migrate.stdout}\nSTDERR:\n${migrate.stderr}`)
        }

        server = spawn(phpBin, ["artisan", "serve", "--host", host, "--port", String(port)], {
            cwd: root,
            env: smokeEnv,
            stdio: ["ignore", "pipe", "pipe"],
            windowsHide: true,
        })
    }

    await waitForServer()
    fixtures = runHelper("setup")

    browser = await chromium.launch({ headless: true })

    const desktopContext = await browser.newContext({ viewport: { width: 1366, height: 900 } })
    const desktopPage = await desktopContext.newPage()
    desktopPage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    desktopPage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await login(desktopPage, fixtures.free, "free")
    await validateCanonicalRedirect(desktopPage, "/workspace/billing", fixtures.free.billingPath, "free_workspace_billing")
    await validateCanonicalRedirect(desktopPage, "/workspace/upgrade", fixtures.free.upgradePath, "free_workspace_upgrade")
    await validatePanelShell(desktopPage, fixtures.free.panelPath, "free_dashboard", null, "Escritorio")
    await screenshot(desktopPage, "dashboard-free-desktop")
    await validatePanelShell(desktopPage, fixtures.free.settingsPath, "free_settings", "panel-settings-page", "Configuracion general")
    await screenshot(desktopPage, "settings-free-desktop")
    await validateBillingState(desktopPage, fixtures.free, "free", "billing-free-payment-form", "Activa tu plan PRO")
    await screenshot(desktopPage, "billing-free-desktop")
    await validateUpgrade(desktopPage, fixtures.free, "free")
    await screenshot(desktopPage, "upgrade-free-desktop")
    await desktopContext.close().catch(() => {})

    if (fixtures.pending.supportsPayments) {
        const pendingContext = await browser.newContext({ viewport: { width: 1366, height: 900 } })
        const pendingPage = await pendingContext.newPage()
        pendingPage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
        pendingPage.on("console", (message) => {
            if (["error", "warning"].includes(message.type())) {
                result.browserErrors.push(`${message.type()}: ${message.text()}`)
            }
        })

        await login(pendingPage, fixtures.pending, "pending")
        await validateBillingState(pendingPage, fixtures.pending, "pending", "billing-pending-state", "Tu comprobante esta en revision")
        await screenshot(pendingPage, "billing-pending-desktop")
        await pendingContext.close().catch(() => {})
    } else {
        pushCheck("pending_payment_schema_skipped", true, "subscription_payments table missing in local database")
    }

    const proContext = await browser.newContext({ viewport: { width: 1366, height: 900 } })
    const proPage = await proContext.newPage()
    proPage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    proPage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await login(proPage, fixtures.pro, "pro")
    await validateBillingState(proPage, fixtures.pro, "pro", "billing-pro-state", "Suscripcion activa")
    await validateProDoesNotShowUpgradeCta(proPage, fixtures.pro)
    await screenshot(proPage, "billing-pro-desktop")
    await proContext.close().catch(() => {})

    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })
    const mobilePage = await mobileContext.newPage()
    mobilePage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    mobilePage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await login(mobilePage, fixtures.free, "mobile_free")
    await validatePanelShell(mobilePage, fixtures.free.panelPath, "mobile_free_dashboard", null, "Escritorio")
    await screenshot(mobilePage, "dashboard-free-mobile")
    await validatePanelShell(mobilePage, fixtures.free.settingsPath, "mobile_free_settings", "panel-settings-page", "Configuracion general")
    await screenshot(mobilePage, "settings-free-mobile")
    await validateBillingState(mobilePage, fixtures.free, "mobile_free", "billing-free-payment-form", "Activa tu plan PRO")
    await screenshot(mobilePage, "billing-free-mobile")
    await validateUpgrade(mobilePage, fixtures.free, "mobile_free")
    await screenshot(mobilePage, "upgrade-free-mobile")
    await mobileContext.close().catch(() => {})

    result.status = result.checks.every((check) => check.pass) ? "pass" : "fail"
} catch (error) {
    result.error = String(error?.message || error)
    result.status = "fail"
} finally {
    if (browser) {
        await browser.close().catch(() => {})
    }

    if (fixtures || result.status === "fail") {
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

    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2), "utf8")
    fs.rmSync(helperPath, { force: true })
    fs.rmSync(configCachePath, { force: true })

    if (!process.env.UIUX_SMOKE_DB) {
        fs.rmSync(sqlitePath, { force: true })
    }
}

console.log(`uiux-fifteenth-billing-upgrade-smoke artifact: ${path.join(outDir, "result.json")}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
