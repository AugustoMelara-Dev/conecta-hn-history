import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8320 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-panel-core-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/panel-core-smoke"
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

const helperPath = path.join(os.tmpdir(), `conecta-uiux-fifteenth-panel-core-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\MenuSection;
use App\\Models\\Tenant;
use App\\Models\\User;
use Illuminate\\Cookie\\CookieValuePrefix;
use Illuminate\\Support\\Facades\\Auth;
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

function onlyExisting(string $table, array $data): array {
    return array_filter(
        $data,
        fn ($value, string $key): bool => Schema::hasColumn($table, $key),
        ARRAY_FILTER_USE_BOTH
    );
}

function ensureGeneralSettings(array &$schemaNotes): void {
    if (! Schema::hasTable('settings')) {
        $schemaNotes[] = 'settings table missing; skipped GeneralSettings seed';
        return;
    }

    foreach ([
        'maintenance_mode' => false,
        'allow_registrations' => true,
        'support_email' => 'soporte@conectahn.com',
        'support_whatsapp_number' => '50499999999',
        'free_plan_product_limit' => 10,
        'pro_plan_product_limit' => 100,
        'pro_plan_price' => 99.00,
        'bank_transfer_instructions' => 'Transferencia temporal para smoke UI.',
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

function ensureTenantMembership(User $user, Tenant $tenant, array &$schemaNotes): void {
    if (! Schema::hasTable('tenant_memberships')) {
        $schemaNotes[] = 'tenant_memberships table missing; skipped membership seed';
        return;
    }

    $membershipData = onlyExisting('tenant_memberships', [
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'status' => 'active',
        'preset_key' => 'owner',
        'granted_permissions' => json_encode(['*'], JSON_THROW_ON_ERROR),
        'invited_by' => null,
        'accepted_at' => now(),
        'can_manage_products' => true,
        'can_manage_orders' => true,
        'can_manage_marketing' => true,
        'can_manage_billing' => true,
        'can_manage_settings' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('tenant_memberships')->updateOrInsert(
        onlyExisting('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]),
        $membershipData,
    );
}

if ($mode === 'setup') {
    $schemaNotes = [];
    ensureGeneralSettings($schemaNotes);

    $email = $runId.'@example.test';
    $password = 'Password123!';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Panel Core Owner',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Panel Core',
        'slug' => $runId,
        'description' => 'Fixture temporal para smoke de panel core.',
        'user_id' => $user->id,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => 'pro',
        'subscription_status' => 'active',
        'phone' => '22223333',
        'whatsapp_number' => '99999999',
        'business_type' => 'retail',
    ];

    if (Schema::hasColumn('tenants', 'is_pro')) {
        $tenantData['is_pro'] = true;
    }

    $tenant = new Tenant();
    $tenant->forceFill(onlyExisting('tenants', $tenantData));
    Tenant::withoutEvents(fn () => $tenant->save());

    $user->forceFill(['tenant_id' => $tenant->id])->save();
    ensureTenantMembership($user, $tenant, $schemaNotes);

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

    echo json_encode([
        'email' => $email,
        'password' => $password,
        'tenantId' => $tenant->id,
        'tenantSlug' => $tenant->slug,
        'panelPath' => '/app/'.$tenant->slug,
        'schemaNotes' => $schemaNotes,
        'paths' => [
            'products' => '/app/'.$tenant->slug.'/products',
            'orders' => '/app/'.$tenant->slug.'/orders',
            'marketing' => '/app/'.$tenant->slug.'/manage-marketing',
            'billing' => '/app/'.$tenant->slug.'/manage-billing',
        ],
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'session') {
    $user = User::where('email', $runId.'@example.test')->firstOrFail();
    $sessionId = Str::random(40);
    $session = app('session')->driver();
    $session->setId($sessionId);
    $session->start();

    Auth::guard('web')->login($user);
    $sessionId = $session->getId();
    $session->save();

    $cookieName = config('session.cookie');
    $cookiePayload = CookieValuePrefix::create($cookieName, app('encrypter')->getKey()).$sessionId;
    $cookieValue = app('encrypter')->encrypt($cookiePayload, false);

    echo json_encode([
        'name' => $cookieName,
        'value' => $cookieValue,
        'domain' => '127.0.0.1',
        'path' => '/',
        'httpOnly' => true,
        'sameSite' => 'Lax',
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    $tenantIds = Schema::hasTable('tenants')
        ? Tenant::withoutGlobalScopes()->where('slug', $runId)->pluck('id')
        : collect();

    if ($tenantIds->isNotEmpty() && Schema::hasTable('menu_sections')) {
        MenuSection::withoutGlobalScopes()->whereIn('tenant_id', $tenantIds)->delete();
    }

    if ($tenantIds->isNotEmpty() && Schema::hasTable('tenant_memberships')) {
        DB::table('tenant_memberships')->whereIn('tenant_id', $tenantIds)->delete();
    }

    if ($tenantIds->isNotEmpty() && Schema::hasTable('tenants')) {
        Tenant::withoutGlobalScopes()->whereIn('id', $tenantIds)->forceDelete();
    }

    if (Schema::hasTable('users')) {
        User::where('email', $runId.'@example.test')->delete();
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
    database: {
        connection: smokeEnv.DB_CONNECTION,
        path: sqlitePath,
        isolated: true,
    },
    status: "fail",
    checks: [],
    screenshots: {},
    httpEvidence: {},
    browserErrors: [],
    error: null,
}

function pushCheck(name, pass, details = "") {
    result.checks.push({ name, pass, details })
}

function safeRm(filePath, label) {
    try {
        fs.rmSync(filePath, { force: true, recursive: true })
        return true
    } catch (error) {
        result.browserErrors.push(`${label} cleanup skipped: ${String(error?.message || error)}`)
        return false
    }
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

function runArtisan(args, label) {
    const command = spawnSync(phpBin, ["artisan", ...args], {
        cwd: root,
        env: smokeEnv,
        encoding: "utf8",
        windowsHide: true,
    })

    if (command.status !== 0) {
        throw new Error(`${label} failed:\nSTDOUT:\n${command.stdout}\nSTDERR:\n${command.stderr}`)
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

    throw new Error(`Laravel server did not become ready at ${baseUrl}: ${lastError?.message || "unknown error"}`)
}

async function startSmokeServer() {
    if (!shouldStartServer) {
        await waitForServer()
        return
    }

    server = spawn(phpBin, ["artisan", "serve", "--host", host, "--port", String(port)], {
        cwd: root,
        env: smokeEnv,
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
    await new Promise((resolve) => setTimeout(resolve, 2_000))
}

function recordHttpEvidence(name, details) {
    result.httpEvidence[name] = details
}

function hasFatalText(bodyText) {
    return /server error|error del servidor|whoops|exception|stack trace|sqlstate|fatal error/i.test(bodyText)
}

function hasEncodingDebt(bodyText) {
    return /Ã|Â|ð|â|�/.test(bodyText)
}

function extractCsrfToken(html) {
    return html.match(/name="_token"\s+value="([^"]+)"/)?.[1] ||
        html.match(/name="csrf-token"\s+content="([^"]+)"/)?.[1] ||
        ""
}

async function loginContext(context, fixture, label = "login") {
    const loginResponse = await context.request.get(`${baseUrl}/login`, {
        maxRedirects: 0,
        timeout: 60_000,
        headers: { Accept: "text/html, application/xhtml+xml" },
    })
    const loginHtml = await loginResponse.text().catch(() => "")
    const csrfToken = extractCsrfToken(loginHtml)

    pushCheck(`${label}_route_200`, loginResponse.status() === 200, `status=${loginResponse.status()}`)

    const body = new URLSearchParams()
    body.set("_token", csrfToken)
    body.set("email", fixture.email)
    body.set("password", fixture.password)
    body.set("remember", "0")

    const authResponse = await context.request.post(`${baseUrl}/login`, {
        maxRedirects: 0,
        timeout: 60_000,
        headers: {
            "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
            "X-CSRF-TOKEN": csrfToken,
            "X-Requested-With": "XMLHttpRequest",
            Accept: "text/html, application/xhtml+xml",
        },
        data: body.toString(),
    })
    await authResponse.text().catch(() => "")

    pushCheck(`${label}_fixture_post_safe`, [0, 200, 302, 303, 419].includes(authResponse.status()), `status=${authResponse.status()} url=${authResponse.url()}`)
}

async function attachSessionCookie(context, label) {
    const sessionCookie = runHelper("session")
    sessionCookie.domain = host
    await context.addCookies([sessionCookie])
    pushCheck(`${label}_fixture_cookie_ok`, Boolean(sessionCookie?.name && sessionCookie?.value), `cookie=${sessionCookie?.name || "missing"}`)
}

async function newLoggedContext(browser, fixture, label, viewport, isMobile = false) {
    const context = await browser.newContext({ viewport, isMobile, serviceWorkers: "block" })
    await attachSessionCookie(context, label)

    return { context }
}

async function validatePanelRoute(context, pathName, label) {
    await attachSessionCookie(context, `${label}_session`)
    let response = await context.request.get(`${baseUrl}${pathName}`, {
        maxRedirects: 5,
        timeout: 60_000,
        headers: {
            Accept: "text/html, application/xhtml+xml",
            "X-Requested-With": "XMLHttpRequest",
        },
    })
    let bodyText = await response.text().catch(() => "")
    let currentUrl = response.url()

    if (!bodyText.trim() || response.status() >= 500) {
        await attachSessionCookie(context, `${label}_retry_session`)
        response = await context.request.get(`${baseUrl}${pathName}`, {
            maxRedirects: 5,
            timeout: 60_000,
            headers: {
                Accept: "text/html, application/xhtml+xml",
                "X-Requested-With": "XMLHttpRequest",
            },
        })
        bodyText = await response.text().catch(() => "")
        currentUrl = response.url()
    }

    const canonicalUrl = currentUrl.includes(pathName)

    pushCheck(`${label}_status_ok`, response.status() < 500, `status=${response.status()} url=${currentUrl}`)
    pushCheck(`${label}_canonical_url`, canonicalUrl, currentUrl)
    pushCheck(`${label}_has_body`, bodyText.trim().length > 0, bodyText.slice(0, 160))
    pushCheck(`${label}_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 160))
    pushCheck(`${label}_no_encoding_debt`, !hasEncodingDebt(bodyText), bodyText.slice(0, 240))
    recordHttpEvidence(label, { status: response.status(), url: currentUrl })
}

async function assertHttpRoute(context, pathName, label, expectedStatus = 200) {
    await attachSessionCookie(context, `${label}_session`)
    const response = await context.request.get(`${baseUrl}${pathName}`, { maxRedirects: 5, timeout: 60_000 })
    const text = await response.text().catch(() => "")
    const status = response.status()
    pushCheck(`${label}_status_ok`, status === expectedStatus || status < 500, `status=${status}`)
    pushCheck(`${label}_no_fatal_text`, !hasFatalText(text), text.slice(0, 160))
}

async function assertWorkspaceRedirect(context, pathName, expectedPath, label) {
    await attachSessionCookie(context, `${label}_session`)
    const response = await context.request.get(`${baseUrl}${pathName}`, {
        maxRedirects: 0,
        timeout: 60_000,
        headers: {
            Accept: "text/html, application/xhtml+xml",
            "X-Requested-With": "XMLHttpRequest",
        },
    })
    await response.text().catch(() => "")
    const location = response.headers().location || ""
    const finalUrl = new URL(location, baseUrl)
    const redirectsToExpected = finalUrl.pathname === expectedPath

    pushCheck(`${label}_redirect_status_ok`, [302, 303, 307, 308].includes(response.status()), `status=${response.status()}`)
    pushCheck(`${label}_redirects_to_panel`, redirectsToExpected, finalUrl.pathname)
}

let server
let browser
let fixture

try {
    fs.writeFileSync(sqlitePath, "", { flag: "a" })
    runArtisan(["config:clear"], "smoke config clear")
    runArtisan(["view:clear"], "smoke view clear")
    runArtisan(["migrate", "--force", "--no-interaction"], "smoke sqlite migration")

    fixture = runHelper("setup")
    result.schemaNotes = fixture.schemaNotes || []
    pushCheck("smoke_sqlite_migrated", true, sqlitePath)
    pushCheck("smoke_fixture_created", Boolean(fixture?.tenantSlug && fixture?.email), fixture?.tenantSlug || "")
    await startSmokeServer()

    browser = await chromium.launch({ headless: true })

    const { context: desktopContext } = await newLoggedContext(
        browser,
        fixture,
        "desktop_panel_login",
        { width: 1366, height: 900 },
    )

    for (const [label, pathName] of Object.entries(fixture.paths)) {
        await validatePanelRoute(desktopContext, pathName, `desktop_${label}`)
    }

    await desktopContext.close().catch(() => {})

    const { context: mobileContext } = await newLoggedContext(
        browser,
        fixture,
        "mobile_panel_login",
        { width: 390, height: 844 },
        true,
    )

    for (const [label, pathName] of Object.entries(fixture.paths)) {
        await validatePanelRoute(mobileContext, pathName, `mobile_${label}`)
    }

    await mobileContext.close().catch(() => {})

    const { context: regressionContext } = await newLoggedContext(
        browser,
        fixture,
        "regression_login",
        { width: 1366, height: 900 },
    )

    await assertWorkspaceRedirect(regressionContext, "/workspace/products", fixture.paths.products, "workspace_products")
    await assertWorkspaceRedirect(regressionContext, "/workspace/orders", fixture.paths.orders, "workspace_orders")
    await assertWorkspaceRedirect(regressionContext, "/workspace/marketing", fixture.paths.marketing, "workspace_marketing")
    await assertWorkspaceRedirect(regressionContext, "/workspace/billing", fixture.paths.billing, "workspace_billing")

    await assertHttpRoute(regressionContext, "/", "home")
    await assertHttpRoute(regressionContext, "/explore", "explore")
    await assertHttpRoute(regressionContext, "/directorio", "directory")
    await assertHttpRoute(regressionContext, "/dashboard", "dashboard")
    await assertHttpRoute(regressionContext, "/mi-cuenta", "profile")
    await assertHttpRoute(regressionContext, "/workspace/reports", "reports")
    await assertHttpRoute(regressionContext, "/workspace/landing-page", "landing_builder")
    await assertHttpRoute(regressionContext, "/login", "login_regression")
    await assertHttpRoute(regressionContext, "/verify-email", "verify_email")
    await regressionContext.close().catch(() => {})
    await browser.close().catch(() => {})
    browser = undefined

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

    safeRm(helperPath, "helper")
    safeRm(configCachePath, "config cache")
    safeRm(viewCompiledPath, "compiled views")

    if (!process.env.UIUX_SMOKE_DB) {
        result.sqliteCleaned = safeRm(sqlitePath, "sqlite")
    }

    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2), "utf8")
}

console.log(`uiux-fifteenth-panel-core-smoke artifact: ${path.join(outDir, "result.json")}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
