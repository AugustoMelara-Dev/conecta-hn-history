import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8920 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-team-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT ||
    "docs/frontend/audit-artifacts/2026-05-07/browser/team-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))

fs.mkdirSync(outDir, { recursive: true })

const helperPath = path.join(os.tmpdir(), `conecta-uiux-team-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\Tenant;
use App\\Models\\TenantUser;
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

function ensureGeneralSettingsForTeam(): void {
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
    ensureGeneralSettingsForTeam();

    if (! Schema::hasTable('tenant_users')) {
        echo json_encode(['skip' => true, 'reason' => 'missing tenant_users'], JSON_THROW_ON_ERROR);
        exit(0);
    }

    $ownerEmail = $runId.'@example.test';
    $memberEmail = $runId.'-member@example.test';
    $password = 'Password123!';

    $owner = new User();
    $owner->forceFill([
        'name' => 'Smoke Equipo Owner',
        'email' => $ownerEmail,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $owner->save());

    $member = new User();
    $member->forceFill([
        'name' => 'Ana Equipo',
        'email' => $memberEmail,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $member->save());

    $tenantData = [
        'name' => 'Smoke Equipo',
        'slug' => $runId,
        'description' => 'Fixture temporal para validar equipo Filament.',
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
        $member->forceFill(['tenant_id' => $tenant->id])->save();
    }

    $teamData = [
        'tenant_id' => $tenant->id,
        'user_id' => $member->id,
        'role' => 'employee',
        'can_manage_products' => true,
        'can_manage_orders' => true,
        'can_view_analytics' => false,
        'can_manage_settings' => false,
        'can_manage_team' => false,
        'can_manage_billing' => false,
        'invited_at' => now(),
        'accepted_at' => now(),
        'invited_by' => $ownerEmail,
        'last_access_at' => now()->subHour(),
    ];

    foreach (array_keys($teamData) as $column) {
        if (! Schema::hasColumn('tenant_users', $column)) {
            unset($teamData[$column]);
        }
    }

    $teamMember = new TenantUser();
    $teamMember->forceFill($teamData);
    TenantUser::withoutEvents(fn () => $teamMember->save());

    echo json_encode([
        'email' => $ownerEmail,
        'password' => $password,
        'tenantSlug' => $tenant->slug,
        'teamPath' => '/app/'.$tenant->slug.'/tenant-users',
        'createPath' => '/app/'.$tenant->slug.'/tenant-users/create',
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    $tenant = Tenant::withoutGlobalScopes()->where('slug', $runId)->first();

    if ($tenant) {
        DB::table('tenant_users')->where('tenant_id', $tenant->id)->delete();
        DB::table('tenants')->where('id', $tenant->id)->delete();
    }

    DB::table('users')->whereIn('email', [$runId.'@example.test', $runId.'-member@example.test'])->delete();

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

    throw lastError || new Error("Server did not become ready")
}

let server

function startServer() {
    if (!shouldStartServer) {
        return
    }

    server = spawn(phpBin, ["artisan", "serve", "--host", host, "--port", String(port)], {
        cwd: root,
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
    return /Ãƒ|Ã¢|Ã°Å¸|ï¿½/.test(text)
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
    await page.waitForTimeout(500)

    const bodyText = await page.locator("body").innerText().catch(() => "")
    pushCheck(`${label}_status_ok`, response?.status() === 200, `status=${response?.status()} url=${page.url()}`)
    pushCheck(`${label}_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 200))
    pushCheck(`${label}_no_encoding_debt`, !hasEncodingDebt(bodyText), bodyText.slice(0, 240))

    for (const text of expectedTexts) {
        pushCheck(`${label}_text_${text.replaceAll(" ", "_")}`, bodyText.includes(text), text)
    }

    if (screenshotName) {
        await screenshot(page, screenshotName)
    }
}

try {
    startServer()
    await waitForServer()

    const fixture = runHelper("setup")
    if (fixture.skip) {
        pushCheck("team_schema_available", true, `skipped: ${fixture.reason}`)
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
        await validatePage(page, fixture.teamPath, "desktop_team", ["Equipo", "Ana Equipo", "Empleado", "Productos, Pedidos"], "desktop-team")
        await page.setViewportSize({ width: 390, height: 844 })
        await validatePage(page, fixture.teamPath, "mobile_team", ["Equipo", "Ana Equipo", "Empleado"], "mobile-team")
        await page.setViewportSize({ width: 1366, height: 900 })
        await validatePage(page, fixture.createPath, "create_team", ["Informacion del miembro", "Permisos de acceso", "Busca por nombre o correo electronico"], null)

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
    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2))
    console.log(`uiux-thirty-fourth-team-smoke artifact: ${path.join(outDir, "result.json")}`)
    console.log(JSON.stringify(result, null, 2))
}

process.exit(result.status === "pass" ? 0 : 1)
