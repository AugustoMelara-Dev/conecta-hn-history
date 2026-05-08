import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8013)
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-panel-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot = process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/panel-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))

fs.mkdirSync(outDir, { recursive: true })

const helperPath = path.join(os.tmpdir(), `conecta-uiux-eighth-panel-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\Payment;
use App\\Models\\Tenant;
use App\\Models\\User;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Str;

$root = $argv[1];
$mode = $argv[2];
$runId = $argv[3];

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
$kernel->bootstrap();

if ($mode === 'setup') {
    $email = $runId.'@example.test';
    $password = 'Password123!';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Panel Owner',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenant = new Tenant();
    $tenant->forceFill([
        'name' => 'Smoke Panel Canonico',
        'slug' => $runId,
        'description' => 'Fixture temporal para smoke de panel canonico.',
        'user_id' => $user->id,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => 'pro',
        'subscription_status' => 'active',
        'whatsapp_number' => '99999999',
        'business_type' => 'retail',
    ]);
    Tenant::withoutEvents(fn () => $tenant->save());

    $user->forceFill(['tenant_id' => $tenant->id])->save();

    $paymentId = null;
    if (class_exists(Payment::class) && DB::getSchemaBuilder()->hasTable('payments')) {
        $payment = new Payment();
        $payment->forceFill([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'amount' => '499.00',
            'proof_path' => 'smoke/proof-placeholder.jpg',
            'bank' => 'bac',
            'reference_number' => 'SMOKE-'.$runId,
            'status' => 'pending',
            'idempotency_key' => 'panel-smoke-'.$runId,
            'version' => 1,
        ]);
        Payment::withoutEvents(fn () => $payment->save());
        $paymentId = $payment->id;
    }

    echo json_encode([
        'email' => $email,
        'password' => $password,
        'tenantId' => $tenant->id,
        'tenantSlug' => $tenant->slug,
        'panelPath' => '/app/'.$tenant->slug,
        'paymentId' => $paymentId,
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    if (class_exists(Payment::class) && DB::getSchemaBuilder()->hasTable('payments')) {
        Payment::withoutGlobalScopes()->where('idempotency_key', 'panel-smoke-'.$runId)->delete();
    }

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

    try {
        return JSON.parse(helper.stdout)
    } catch (error) {
        throw new Error(
            `${mode} helper returned invalid JSON: ${String(error?.message || error)}\nSTDOUT:\n${helper.stdout}\nSTDERR:\n${helper.stderr}`,
        )
    }
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

async function assertPublicRoute(page, pathName, label) {
    const response = await page.goto(`${baseUrl}${pathName}`, { waitUntil: "domcontentloaded" })
    pushCheck(`${label}_status_ok`, (response?.status() || 0) < 500, `status=${response?.status()}`)
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

function hasFatalText(bodyText) {
    return /server error|whoops|exception|stack trace|sqlstate|fatal error/i.test(bodyText)
}

let server
let browser
let fixture

try {
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

    await login(desktopPage, fixture)

    const panelResponse = await desktopPage.goto(`${baseUrl}${fixture.panelPath}`, { waitUntil: "domcontentloaded" })
    const panelText = await desktopPage.locator("body").innerText().catch(() => "")
    pushCheck("panel_canonical_200", panelResponse?.status() === 200, `status=${panelResponse?.status()}`)
    pushCheck("panel_canonical_url", desktopPage.url().includes(fixture.panelPath), desktopPage.url())
    pushCheck("panel_no_fatal_text", !hasFatalText(panelText), panelText.slice(0, 160))
    await screenshot(desktopPage, "panel-canonical-desktop")

    const workspaceRedirected = desktopPage
        .waitForURL((url) => url.pathname === fixture.panelPath, { timeout: 15_000 })
        .then(() => true)
        .catch(() => false)
    await desktopPage.evaluate(() => {
        window.location.assign("/workspace/dashboard")
    })
    pushCheck("workspace_dashboard_browser_redirects_to_panel", await workspaceRedirected, desktopPage.url())

    await assertPublicRoute(desktopPage, "/directorio", "directory")
    await assertPublicRoute(desktopPage, "/login", "login_regression")
    await assertPublicRoute(desktopPage, "/verify-email", "verify_email_regression")

    if (fixture.paymentId) {
        await assertPublicRoute(desktopPage, `/registro/pago-pendiente/${fixture.paymentId}`, "payment_pending_fixture")
    }

    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })
    const mobilePage = await mobileContext.newPage()
    await login(mobilePage, fixture)
    const mobilePanelResponse = await mobilePage.goto(`${baseUrl}${fixture.panelPath}`, { waitUntil: "domcontentloaded" })
    pushCheck("panel_mobile_200", mobilePanelResponse?.status() === 200, `status=${mobilePanelResponse?.status()}`)
    await screenshot(mobilePage, "panel-canonical-mobile")

    result.status = result.checks.every((check) => check.pass) ? "pass" : "fail"
} catch (error) {
    result.error = String(error?.message || error)
    result.status = "fail"
} finally {
    if (browser) {
        await browser.close().catch(() => {})
    }

    if (fixture || result.error?.includes("setup helper")) {
        try {
            runHelper("cleanup")
            result.fixtureCleaned = true
        } catch (error) {
            result.fixtureCleaned = false
            result.cleanupError = String(error?.message || error)
        }
    }

    if (server) {
        server.kill()
    }

    fs.rmSync(helperPath, { force: true })
}

const outFile = path.join(outDir, "result.json")
fs.writeFileSync(outFile, JSON.stringify(result, null, 2), "utf8")
console.log(`uiux-eighth-panel-smoke artifact: ${outFile}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
