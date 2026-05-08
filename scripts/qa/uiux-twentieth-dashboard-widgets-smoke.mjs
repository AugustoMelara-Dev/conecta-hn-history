import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8021)
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-dashboard-widgets-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT ||
    "docs/frontend/audit-artifacts/2026-05-07/browser/dashboard-widgets-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))

fs.mkdirSync(outDir, { recursive: true })

const helperPath = path.join(os.tmpdir(), `conecta-uiux-twentieth-dashboard-widgets-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\MenuSection;
use App\\Models\\Product;
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

function ensureGeneralSettingsForDashboardWidgets(): void {
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
        'allow_registrations' => true,
        'maintenance_mode' => false,
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

function makeUserAndTenant(string $runId, string $suffix, bool $isPro): array {
    $email = $runId.'-'.$suffix.'@example.test';
    $password = 'Password123!';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Dashboard Widgets '.ucfirst($suffix),
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Dashboard Widgets '.ucfirst($suffix),
        'slug' => $runId.'-'.$suffix,
        'description' => 'Fixture temporal para smoke de widgets del dashboard.',
        'user_id' => $user->id,
        'views' => $isPro ? 240 : 90,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => $isPro ? 'pro' : 'semilla',
        'subscription_status' => 'active',
        'phone' => '22223333',
        'whatsapp_number' => '99999999',
        'business_type' => 'retail',
    ];

    if (Schema::hasColumn('tenants', 'is_pro')) {
        $tenantData['is_pro'] = $isPro;
    }

    if (Schema::hasColumn('tenants', 'product_limit')) {
        $tenantData['product_limit'] = 10;
    }

    foreach (array_keys($tenantData) as $column) {
        if (! Schema::hasColumn('tenants', $column)) {
            unset($tenantData[$column]);
        }
    }

    $tenant = new Tenant();
    $tenant->forceFill($tenantData);
    Tenant::withoutEvents(fn () => $tenant->save());

    $user->forceFill(['tenant_id' => $tenant->id])->save();

    $section = null;
    if (class_exists(MenuSection::class) && Schema::hasTable('menu_sections')) {
        $section = new MenuSection();
        $section->forceFill([
            'tenant_id' => $tenant->id,
            'name' => 'Menu principal',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        MenuSection::withoutEvents(fn () => $section->save());
    }

    $productCount = $isPro ? 3 : 9;
    for ($index = 1; $index <= $productCount; $index++) {
        $productData = [
            'tenant_id' => $tenant->id,
            'menu_section_id' => $section?->id,
            'name' => 'Producto smoke '.$suffix.' '.$index,
            'slug' => $runId.'-'.$suffix.'-producto-'.$index.'-'.Str::random(6),
            'description' => 'Producto temporal para smoke de widgets.',
            'price' => 99.00 + $index,
            'is_active' => true,
            'is_visible' => true,
            'status' => 'published',
        ];

        foreach (array_keys($productData) as $column) {
            if (! Schema::hasColumn('products', $column)) {
                unset($productData[$column]);
            }
        }

        $product = new Product();
        $product->forceFill($productData);
        Product::withoutEvents(fn () => $product->save());
    }

    return [
        'email' => $email,
        'password' => $password,
        'tenantSlug' => $tenant->slug,
        'panelPath' => '/app/'.$tenant->slug,
        'isPro' => $isPro,
    ];
}

if ($mode === 'setup') {
    ensureGeneralSettingsForDashboardWidgets();

    $supportsPlanState = Schema::hasColumn('tenants', 'plan_type')
        && Schema::hasColumn('tenants', 'subscription_status');

    $payload = [
        'supportsPlanState' => $supportsPlanState,
        'semilla' => makeUserAndTenant($runId, 'semilla', false),
    ];

    if ($supportsPlanState) {
        $payload['pro'] = makeUserAndTenant($runId, 'pro', true);
    }

    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    Tenant::withoutGlobalScopes()->where('slug', 'like', $runId.'%')->forceDelete();
    User::where('email', 'like', $runId.'%@example.test')->delete();

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

async function startSmokeServer() {
    if (!shouldStartServer) {
        await waitForServer()
        return
    }

    server = spawn(phpBin, ["artisan", "serve", "--host", host, "--port", String(port)], {
        cwd: root,
        stdio: ["ignore", "pipe", "pipe"],
        windowsHide: true,
    })

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
    return /Ãƒ|Ã‚|Ã°|Ã¢|Â|ï¿½/.test(bodyText)
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

    pushCheck(`${label}_login_fixture_ok`, authStatus.status < 500, `status=${authStatus.status} url=${authStatus.url}`)
}

async function validateDashboardWidgets(page, fixture, label) {
    const response = await page.goto(`${baseUrl}${fixture.panelPath}`, { waitUntil: "commit", timeout: 45_000 })
    await page.waitForLoadState("domcontentloaded", { timeout: 20_000 }).catch(() => {})

    const bodyText = await page.locator("body").innerText().catch(() => "")
    pushCheck(`${label}_panel_status_ok`, (response?.status() || 0) < 500, `status=${response?.status()} url=${page.url()}`)
    pushCheck(`${label}_panel_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 180))
    pushCheck(`${label}_panel_no_encoding_debt`, !hasEncodingDebt(bodyText), bodyText.slice(0, 240))

    for (const testId of [
        "dashboard-product-limit-widget",
        "dashboard-tenant-stats-widget",
        "dashboard-plan-state",
        "dashboard-product-usage",
        "dashboard-traffic-metrics",
    ]) {
        pushCheck(`${label}_${testId}_visible`, await page.getByTestId(testId).first().isVisible().catch(() => false))
    }

    if (!fixture.isPro) {
        pushCheck(
            `${label}_dashboard_product_limit_warning_visible`,
            await page.getByTestId("dashboard-product-limit-warning").isVisible().catch(() => false),
        )
    }

    const billingLinks = await page.locator(`a[href*="/app/${fixture.tenantSlug}/manage-billing"]`).count()
    pushCheck(`${label}_billing_cta_real_href`, billingLinks > 0, `links=${billingLinks}`)

    await screenshot(page, `${label}-dashboard-widgets`)
}

async function runViewport(browser, fixture, label, viewport, isMobile = false) {
    const context = await browser.newContext({ viewport, isMobile })
    const page = await context.newPage()

    page.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    page.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await login(page, fixture, label)
    await validateDashboardWidgets(page, fixture, label)
    await context.close().catch(() => {})
}

let server
let browser
let fixture

try {
    spawnSync(phpBin, ["artisan", "view:clear"], { cwd: root, encoding: "utf8", windowsHide: true })
    fixture = runHelper("setup")
    await startSmokeServer()

    browser = await chromium.launch({ headless: true })

    await runViewport(browser, fixture.semilla, "semilla-desktop", { width: 1366, height: 900 })
    await runViewport(browser, fixture.semilla, "semilla-mobile", { width: 390, height: 844 }, true)

    if (fixture.pro) {
        await runViewport(browser, fixture.pro, "pro-desktop", { width: 1366, height: 900 })
        await runViewport(browser, fixture.pro, "pro-mobile", { width: 390, height: 844 }, true)
    } else {
        pushCheck(
            "pro_fixture_skipped_schema_drift",
            true,
            "Local tenants table does not expose plan_type/subscription_status, so PRO rendering cannot be exercised here.",
        )
    }

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

    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2), "utf8")
    fs.rmSync(helperPath, { force: true })
}

console.log(`uiux-twentieth-dashboard-widgets-smoke artifact: ${path.join(outDir, "result.json")}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
