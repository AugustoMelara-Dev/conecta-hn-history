import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const defaultPort = 8017 + Math.floor(Math.random() * 300)
const port = Number(process.env.UIUX_SMOKE_PORT || defaultPort)
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-reports-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/reports-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))

fs.mkdirSync(outDir, { recursive: true })

const helperPath = path.join(os.tmpdir(), `conecta-uiux-twelfth-reports-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\Tenant;
use App\\Models\\User;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\Schema;

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
        'name' => 'Smoke Reports Owner',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Reports Canonico',
        'slug' => $runId,
        'description' => 'Fixture temporal para smoke de reportes.',
        'user_id' => $user->id,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => 'pro',
        'subscription_status' => 'active',
        'whatsapp_number' => '99999999',
        'business_type' => 'retail',
    ];

    $tenant = new Tenant();
    $tenant->forceFill(array_filter(
        $tenantData,
        fn ($value, string $column): bool => Schema::hasColumn('tenants', $column),
        ARRAY_FILTER_USE_BOTH
    ));
    Tenant::withoutEvents(fn () => $tenant->save());

    $user->forceFill(['tenant_id' => $tenant->id])->save();

    echo json_encode([
        'email' => $email,
        'password' => $password,
        'tenantId' => $tenant->id,
        'tenantSlug' => $tenant->slug,
        'panelPath' => '/app/'.$tenant->slug,
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
        throw new Error(`missing JSON object\nSTDOUT:\n${helper.stdout}\nSTDERR:\n${helper.stderr}`)
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

async function validateReports(page, label) {
    const response = await page.goto(`${baseUrl}/workspace/reports`, { waitUntil: "domcontentloaded" })
    const bodyText = await page.locator("body").innerText().catch(() => "")
    pushCheck(`${label}_reports_200`, response?.status() === 200, `status=${response?.status()}`)
    pushCheck(`${label}_reports_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 160))

    await page.getByTestId("reports-page").waitFor({ state: "visible", timeout: 10_000 })
    pushCheck(`${label}_reports_page_visible`, await page.getByTestId("reports-page").isVisible().catch(() => false))
    pushCheck(
        `${label}_reports_date_filter_visible`,
        await page.getByTestId("reports-date-filter").isVisible().catch(() => false),
    )
    pushCheck(
        `${label}_reports_sales_chart_visible`,
        await page.getByTestId("reports-sales-chart").isVisible().catch(() => false),
    )

    const startInput = page.locator("#reports-start-date")
    const endInput = page.locator("#reports-end-date")
    await startInput.fill("2026-05-01")
    await endInput.fill("2026-05-07")
    await page.waitForURL((url) => url.searchParams.get("start_date") === "2026-05-01", { timeout: 10_000 })
        .catch(() => {})

    pushCheck(`${label}_date_start_value`, await startInput.inputValue().catch(() => "") === "2026-05-01")
    pushCheck(`${label}_date_end_value`, await endInput.inputValue().catch(() => "") === "2026-05-07")

    const salesButton = page.getByTestId("reports-sales-pdf")
    const inventoryButton = page.getByTestId("reports-inventory-pdf")
    const salesUrl = await salesButton.getAttribute("data-download-url")
    const inventoryUrl = await inventoryButton.getAttribute("data-download-url")

    pushCheck(`${label}_sales_pdf_anchor_visible`, await salesButton.isVisible().catch(() => false))
    pushCheck(
        `${label}_sales_pdf_url_has_dates`,
        Boolean(salesUrl?.includes("start_date=2026-05-01") && salesUrl?.includes("end_date=2026-05-07")),
        salesUrl || "",
    )
    pushCheck(`${label}_inventory_pdf_anchor_visible`, await inventoryButton.isVisible().catch(() => false))
    pushCheck(
        `${label}_inventory_pdf_url_present`,
        Boolean(inventoryUrl?.includes("/workspace/reports/inventory-pdf")),
        inventoryUrl || "",
    )
}

async function assertBrowserRoute(page, pathName, label) {
    const response = await page.goto(`${baseUrl}${pathName}`, { waitUntil: "domcontentloaded" })
    const text = await page.locator("body").innerText().catch(() => "")
    pushCheck(`${label}_status_ok`, (response?.status() || 0) < 500, `status=${response?.status()}`)
    pushCheck(`${label}_no_fatal_text`, !hasFatalText(text), text.slice(0, 160))
}

async function assertHttpRoute(pathName, label) {
    const response = await fetch(`${baseUrl}${pathName}`, { redirect: "follow" })
    const text = await response.text().catch(() => "")
    pushCheck(`${label}_status_ok`, response.status < 500, `status=${response.status}`)
    pushCheck(`${label}_no_fatal_text`, !hasFatalText(text), text.slice(0, 160))
}

async function assertAuthenticatedHttpRoute(context, pathName, label) {
    const cookies = await context.cookies(baseUrl)
    const cookieHeader = cookies.map((cookie) => `${cookie.name}=${cookie.value}`).join("; ")
    const response = await fetch(`${baseUrl}${pathName}`, {
        redirect: "follow",
        headers: cookieHeader ? { Cookie: cookieHeader } : {},
    })
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
    await validateReports(desktopPage, "desktop")
    await screenshot(desktopPage, "reports-desktop")

    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })
    const mobilePage = await mobileContext.newPage()
    mobilePage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    mobilePage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await login(mobilePage, fixture)
    await validateReports(mobilePage, "mobile")
    await screenshot(mobilePage, "reports-mobile")

    await assertBrowserRoute(desktopPage, "/dashboard", "dashboard")
    await assertBrowserRoute(desktopPage, "/mi-cuenta", "profile")
    await assertAuthenticatedHttpRoute(desktopContext, fixture.panelPath, "panel_fixture")
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

console.log(`uiux-twelfth-reports-smoke artifact: ${path.join(outDir, "result.json")}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
