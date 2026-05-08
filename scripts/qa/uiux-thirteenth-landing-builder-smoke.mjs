import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8100 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-landing-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/landing-builder-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))

fs.mkdirSync(outDir, { recursive: true })

const helperPath = path.join(os.tmpdir(), `conecta-uiux-thirteenth-landing-helper-${process.pid}.php`)
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
$tenantSlug = $argv[4] ?? null;

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

function makeFixture(string $runId, string $suffix, string $planType): array {
    $email = $runId.'-'.$suffix.'@example.test';
    $password = 'Password123!';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Landing '.strtoupper($suffix),
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Landing '.strtoupper($suffix),
        'slug' => $runId.'-'.$suffix,
        'user_id' => $user->id,
    ];

    foreach ([
        'is_active' => true,
        'status' => 'active',
        'plan' => $planType,
        'plan_type' => $planType,
        'subscription_status' => 'active',
        'whatsapp_number' => '99999999',
        'business_type' => 'retail',
        'description' => 'Fixture temporal para smoke de landing builder.',
        'landing_page_data' => [
            'sections' => [],
        ],
    ] as $column => $value) {
        if (Schema::hasColumn('tenants', $column)) {
            $tenantData[$column] = $value;
        }
    }

    $tenant = new Tenant();
    $tenant->forceFill($tenantData);
    Tenant::withoutEvents(fn () => $tenant->save());

    $tenantUpdates = [];

    foreach ([
        'is_active' => true,
        'is_pro' => $planType === 'pro',
        'plan' => $planType,
        'plan_type' => $planType,
        'subscription_status' => 'active',
    ] as $column => $value) {
        if (Schema::hasColumn('tenants', $column)) {
            $tenantUpdates[$column] = $value;
        }
    }

    if (Schema::hasColumn('tenants', 'landing_page_data')) {
        $tenantUpdates['landing_page_data'] = json_encode(['sections' => []], JSON_THROW_ON_ERROR);
    }

    if ($tenantUpdates !== []) {
        Illuminate\\Support\\Facades\\DB::table('tenants')
            ->where('id', $tenant->id)
            ->update($tenantUpdates);
    }

    $user->forceFill(['tenant_id' => $tenant->id])->save();

    return [
        'email' => $email,
        'password' => $password,
        'tenantId' => $tenant->id,
        'tenantSlug' => $tenant->slug,
        'panelPath' => '/app/'.$tenant->slug,
    ];
}

if ($mode === 'setup') {
    ensureGeneralSettings();

    echo json_encode([
        'pro' => makeFixture($runId, 'pro', 'pro'),
        'seed' => makeFixture($runId, 'seed', 'semilla'),
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    Tenant::withoutGlobalScopes()->where('slug', 'like', $runId.'%')->forceDelete();
    User::where('email', 'like', $runId.'%@example.test')->delete();

    echo json_encode(['cleaned' => true], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'landing-count') {
    $schemaHasLandingData = Schema::hasColumn('tenants', 'landing_page_data');
    $tenant = Tenant::withoutGlobalScopes()->where('slug', $tenantSlug)->first();
    $attributes = $tenant?->getAttributes() ?? [];
    $rawData = $attributes['landing_page_data'] ?? null;
    $data = is_array($rawData) ? $rawData : (is_string($rawData) ? json_decode($rawData, true) : []);
    $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];

    echo json_encode([
        'schemaHasLandingData' => $schemaHasLandingData,
        'sections' => count($sections),
    ], JSON_THROW_ON_ERROR);
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

function runHelper(mode, extraArgs = []) {
    const helper = spawnSync(phpBin, [helperPath, root, mode, runId, ...extraArgs], {
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

async function login(page, fixture, label) {
    const loginResponse = await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded" })
    pushCheck(`${label}_login_route_200`, loginResponse?.status() === 200, `status=${loginResponse?.status()}`)

    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute("content")
    const authStatus = await page.evaluate(
        async ({ loginUrl, email, password, token }) => {
            const body = new URLSearchParams()
            body.set("email", email)
            body.set("password", password)
            body.set("remember", "0")
            body.set("_token", token || "")

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

            return response.status
        },
        { loginUrl: `${baseUrl}/login`, email: fixture.email, password: fixture.password, token: csrfToken },
    )

    pushCheck(`${label}_login_fixture_post_redirects`, authStatus < 500, `status=${authStatus}`)
}

async function validateBuilder(page, label, shouldSave = false) {
    const response = await page.goto(`${baseUrl}/workspace/landing-page`, { waitUntil: "domcontentloaded" })
    const bodyText = await page.locator("body").innerText().catch(() => "")
    const inertiaProps = await page.evaluate(() => {
        const rawPage = document.getElementById("app")?.dataset?.page

        if (!rawPage) {
            return {}
        }

        try {
            return JSON.parse(rawPage).props || {}
        } catch {
            return {}
        }
    }).catch(() => ({}))
    pushCheck(`${label}_builder_200`, response?.status() === 200, `status=${response?.status()}`)
    pushCheck(`${label}_builder_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 160))

    const builderVisible = await page.getByTestId("landing-builder-page")
        .waitFor({ state: "visible", timeout: 10_000 })
        .then(() => true)
        .catch(() => false)
    pushCheck(`${label}_builder_page_visible`, builderVisible, `isPro=${inertiaProps.isPro}; ${bodyText.slice(0, 240)}`)

    if (!builderVisible) {
        await screenshot(page, `${label}-builder-missing`)
        return
    }

    pushCheck(`${label}_save_visible`, await page.getByTestId("landing-save").isVisible().catch(() => false))
    pushCheck(`${label}_live_preview_visible`, await page.getByTestId("landing-live-preview").isVisible().catch(() => false))
    pushCheck(`${label}_empty_state_visible`, await page.getByTestId("landing-empty-state").isVisible().catch(() => false))

    const templateCards = page.getByTestId("landing-template-card")
    pushCheck(`${label}_template_cards_present`, await templateCards.count() >= 5, `count=${await templateCards.count()}`)

    await templateCards.first().click()
    await page.getByTestId("landing-section-card").first().waitFor({ state: "visible", timeout: 10_000 })
    pushCheck(`${label}_section_card_created`, await page.getByTestId("landing-section-card").first().isVisible().catch(() => false))
    pushCheck(
        `${label}_live_preview_updates`,
        await page.getByTestId("landing-live-preview").getByText("Bienvenido a nuestro negocio").isVisible().catch(() => false),
    )

    await page.getByLabel("Editar seccion").first().click()
    await page.getByTestId("landing-editor-dialog").waitFor({ state: "visible", timeout: 10_000 })
    pushCheck(`${label}_editor_dialog_visible`, await page.getByTestId("landing-editor-dialog").isVisible().catch(() => false))
    pushCheck(`${label}_hero_url_field_visible`, await page.getByLabel("URL del boton").isVisible().catch(() => false))
    await page.getByLabel("Titulo principal").fill("Landing smoke actualizado")
    await page.getByLabel("URL del boton").fill("#products")
    await page.getByRole("button", { name: "Guardar seccion" }).click()
    pushCheck(
        `${label}_editor_dialog_closes`,
        await page.getByTestId("landing-editor-dialog").isHidden({ timeout: 10_000 }).catch(() => false),
    )
    pushCheck(
        `${label}_edited_preview_updates`,
        await page.getByTestId("landing-live-preview").getByText("Landing smoke actualizado").isVisible().catch(() => false),
    )

    await templateCards.nth(1).click()
    await page.getByTestId("landing-section-card").nth(1).waitFor({ state: "visible", timeout: 10_000 })
    pushCheck(`${label}_feature_section_created`, await page.getByTestId("landing-section-card").nth(1).isVisible().catch(() => false))
    await page.getByLabel("Editar seccion").nth(1).click()
    await page.getByTestId("landing-features-editor").waitFor({ state: "visible", timeout: 10_000 })
    pushCheck(`${label}_features_editor_visible`, await page.getByTestId("landing-features-editor").isVisible().catch(() => false))
    pushCheck(
        `${label}_feature_fields_visible`,
        await page.locator("#landing-features-feature-0-title").isVisible().catch(() => false),
    )
    await page.getByRole("button", { name: "Cancelar" }).click()

    if (shouldSave) {
        const saveResponse = page.waitForResponse(
            (res) => res.request().method() === "PUT" && res.url().includes("/workspace/landing-page"),
            { timeout: 15_000 },
        ).catch(() => null)
        await page.getByTestId("landing-save").click()
        const responseAfterSave = await saveResponse
        pushCheck(
            `${label}_save_request_completed`,
            Boolean(responseAfterSave && responseAfterSave.status() < 500),
            `status=${responseAfterSave?.status()}`,
        )
        await page.waitForLoadState("networkidle", { timeout: 15_000 }).catch(() => {})
        const afterSaveText = await page.locator("body").innerText().catch(() => "")
        pushCheck(`${label}_save_no_fatal_text`, !hasFatalText(afterSaveText), afterSaveText.slice(0, 160))
    }
}

async function waitForPersistedLandingSection(tenantSlug) {
    const deadline = Date.now() + 10_000
    let savedLanding = { sections: 0 }

    while (Date.now() < deadline) {
        savedLanding = runHelper("landing-count", [String(tenantSlug)])

        if (savedLanding.sections >= 1) {
            return savedLanding
        }

        await new Promise((resolve) => setTimeout(resolve, 500))
    }

    return savedLanding
}

async function validateProGate(page) {
    const response = await page.goto(`${baseUrl}/workspace/landing-page`, { waitUntil: "domcontentloaded" })
    const bodyText = await page.locator("body").innerText().catch(() => "")
    pushCheck("seed_gate_200", response?.status() === 200, `status=${response?.status()}`)
    pushCheck("seed_gate_no_fatal_text", !hasFatalText(bodyText), bodyText.slice(0, 160))
    await page.getByTestId("landing-pro-gate").waitFor({ state: "visible", timeout: 10_000 })
    pushCheck("seed_gate_visible", await page.getByTestId("landing-pro-gate").isVisible().catch(() => false))
    pushCheck("seed_gate_upgrade_cta_visible", await page.getByRole("link", { name: "Ver opciones Pro" }).isVisible().catch(() => false))
}

async function assertHttpRoute(pathName, label) {
    const response = await fetchWithTimeout(`${baseUrl}${pathName}`, { redirect: "follow" })
    const text = await response.text().catch(() => "")
    pushCheck(`${label}_status_ok`, response.status < 500, `status=${response.status}`)
    pushCheck(`${label}_no_fatal_text`, !hasFatalText(text), text.slice(0, 160))
}

async function assertAuthenticatedHttpRoute(context, pathName, label) {
    const cookies = await context.cookies(baseUrl)
    const cookieHeader = cookies.map((cookie) => `${cookie.name}=${cookie.value}`).join("; ")
    const response = await fetchWithTimeout(`${baseUrl}${pathName}`, {
        redirect: "follow",
        headers: cookieHeader ? { Cookie: cookieHeader } : {},
    })
    const text = await response.text().catch(() => "")
    pushCheck(`${label}_status_ok`, response.status < 500, `status=${response.status}`)
    pushCheck(`${label}_no_fatal_text`, !hasFatalText(text), text.slice(0, 160))
}

async function fetchWithTimeout(url, options = {}, timeoutMs = 45_000) {
    const controller = new AbortController()
    const timeout = setTimeout(() => controller.abort(), timeoutMs)

    try {
        return await fetch(url, { ...options, signal: controller.signal })
    } finally {
        clearTimeout(timeout)
    }
}

let server
let browser
let fixture

try {
    spawnSync(phpBin, ["artisan", "config:clear"], { cwd: root, encoding: "utf8", windowsHide: true })
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
    desktopPage.setDefaultNavigationTimeout(60_000)
    desktopPage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    desktopPage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await login(desktopPage, fixture.pro, "pro_desktop")
    await validateBuilder(desktopPage, "pro_desktop", true)
    const savedLanding = await waitForPersistedLandingSection(fixture.pro.tenantSlug)
    pushCheck(
        "pro_desktop_save_persisted_fixture_section",
        !savedLanding.schemaHasLandingData || savedLanding.sections >= 1,
        `schemaHasLandingData=${savedLanding.schemaHasLandingData}; sections=${savedLanding.sections}`,
    )
    await screenshot(desktopPage, "landing-builder-desktop")

    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })
    const mobilePage = await mobileContext.newPage()
    mobilePage.setDefaultNavigationTimeout(60_000)
    mobilePage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    mobilePage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await login(mobilePage, fixture.pro, "pro_mobile")
    await validateBuilder(mobilePage, "pro_mobile")
    await screenshot(mobilePage, "landing-builder-mobile")

    const seedContext = await browser.newContext({ viewport: { width: 1366, height: 900 } })
    const seedPage = await seedContext.newPage()
    seedPage.setDefaultNavigationTimeout(60_000)
    await login(seedPage, fixture.seed, "seed")
    await validateProGate(seedPage)
    await screenshot(seedPage, "landing-builder-pro-gate")

    await assertAuthenticatedHttpRoute(desktopContext, "/workspace/reports", "reports")
    await assertAuthenticatedHttpRoute(desktopContext, "/dashboard", "dashboard")
    await assertAuthenticatedHttpRoute(desktopContext, "/mi-cuenta", "profile")
    await assertAuthenticatedHttpRoute(desktopContext, fixture.pro.panelPath, "panel_fixture")
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

    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2), "utf8")
    fs.rmSync(helperPath, { force: true })

    if (server) {
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

console.log(`uiux-thirteenth-landing-builder-smoke artifact: ${path.join(outDir, "result.json")}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
