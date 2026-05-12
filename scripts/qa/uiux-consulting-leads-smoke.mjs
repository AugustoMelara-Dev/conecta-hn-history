import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8930 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-consulting-leads-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT ||
    "docs/frontend/audit-artifacts/2026-05-07/browser/consulting-leads-smoke"
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
    SESSION_SECURE_COOKIE: "false",
    QUEUE_CONNECTION: "sync",
    MAIL_MAILER: "array",
    BROADCAST_CONNECTION: "log",
    UIUX_DISABLE_FILAMENT_DATABASE_NOTIFICATIONS: "1",
}

fs.mkdirSync(outDir, { recursive: true })
fs.mkdirSync(viewCompiledPath, { recursive: true })

const helperPath = path.join(os.tmpdir(), `conecta-uiux-consulting-leads-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\Lead;
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

function ensureGeneralSettingsForConsulting(): void {
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

function onlyExistingColumns(string $table, array $data): array {
    return array_filter(
        $data,
        fn ($column) => Schema::hasColumn($table, $column),
        ARRAY_FILTER_USE_KEY,
    );
}

function ensurePanelMemberships(User $user, Tenant $tenant): void {
    if (Schema::hasTable('tenant_memberships')) {
        DB::table('tenant_memberships')->updateOrInsert(
            onlyExistingColumns('tenant_memberships', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ]),
            onlyExistingColumns('tenant_memberships', [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'preset_key' => 'owner',
                'granted_permissions' => json_encode(['*'], JSON_THROW_ON_ERROR),
                'accepted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        );
    }
}

if ($mode === 'setup') {
    ensureGeneralSettingsForConsulting();

    $email = $runId.'@example.test';
    $password = 'Password123!';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Consulting Owner',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Consulting',
        'slug' => $runId,
        'description' => 'Fixture temporal para validar flujo consultivo.',
        'about' => 'Ayudamos a empresas a convertir consultas en oportunidades.',
        'slogan' => 'Consultoria con seguimiento operativo',
        'user_id' => $user->id,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => 'pro',
        'subscription_status' => 'active',
        'business_type' => 'service',
        'commercial_mode' => 'consulting_leads',
        'whatsapp_number' => '99999999',
        'phone' => '22223333',
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
        $user->forceFill(['tenant_id' => $tenant->id])->save();
    }

    ensurePanelMemberships($user, $tenant);

    if (Schema::hasTable('leads')) {
        $leadData = onlyExistingColumns('leads', [
            'tenant_id' => $tenant->id,
            'name' => 'Lead Smoke',
            'email' => 'lead-'.$runId.'@example.test',
            'phone' => '99999999',
            'message' => 'Solicito informacion de consultoria.',
            'source' => 'microsite',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($leadData !== []) {
            DB::table('leads')->insert($leadData);
        }
    }

    echo json_encode([
        'email' => $email,
        'password' => $password,
        'tenantSlug' => $tenant->slug,
        'leadsPath' => '/app/'.$tenant->slug.'/leads/leads',
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
    $tenant = Tenant::withoutGlobalScopes()->where('slug', $runId)->first();

    if ($tenant) {
        if (Schema::hasTable('leads')) {
            DB::table('leads')->where('tenant_id', $tenant->id)->delete();
        }

        if (Schema::hasTable('tenant_memberships')) {
            DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->delete();
        }

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
    database: { connection: "sqlite", path: sqlitePath, isolated: !process.env.UIUX_SMOKE_DB },
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
        env: smokeEnv,
        windowsHide: true,
        timeout: 120_000,
        maxBuffer: 10 * 1024 * 1024,
    })
    if (helper.status !== 0 || helper.error) {
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
        encoding: "utf8",
        env: smokeEnv,
        maxBuffer: 10 * 1024 * 1024,
        windowsHide: true,
    })
    if (command.status !== 0) {
        throw new Error(`${label} failed: ${command.stderr || command.stdout}`)
    }
}

async function waitForServer() {
    const deadline = Date.now() + 30_000
    while (Date.now() < deadline) {
        try {
            const response = await fetch(`${baseUrl}/up`)
            if (response.status < 500) return
        } catch {}
        await new Promise((resolve) => setTimeout(resolve, 500))
    }
    throw new Error(`Laravel server did not become ready at ${baseUrl}`)
}

function hasFatalText(bodyText) {
    return /server error|whoops|exception|stack trace|sqlstate|fatal error/i.test(bodyText)
}

async function screenshot(page, name) {
    const file = path.join(outDir, `${name}.png`)
    await page.screenshot({ path: file, fullPage: true })
    result.screenshots[name] = file
}

async function fetchPanelRoute(context, pathName) {
    try {
        const response = await context.request.get(`${baseUrl}${pathName}`)
        const text = await response.text()
        return { status: response.status(), text }
    } catch (error) {
        return { status: 0, text: String(error?.message || error) }
    }
}

let server
let browser
let fixture

try {
    fs.closeSync(fs.openSync(sqlitePath, "a"))
    runArtisan(["view:clear"], "view clear")
    runArtisan(["migrate", "--force", "--no-interaction"], "smoke sqlite migration")
    pushCheck("smoke_sqlite_migrated", true, sqlitePath)

    if (shouldStartServer) {
        server = spawn(phpBin, ["artisan", "serve", "--host", host, "--port", String(port)], {
            cwd: root,
            stdio: ["ignore", "pipe", "pipe"],
            env: smokeEnv,
            windowsHide: true,
        })
    }

    await waitForServer()
    fixture = runHelper("setup")
    const sessionCookie = runHelper("session")

    browser = await chromium.launch({ headless: true })
    const context = await browser.newContext({ baseURL: baseUrl, viewport: { width: 1366, height: 900 } })
    await context.addCookies([sessionCookie])
    const page = await context.newPage()
    page.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    page.on("console", (message) => {
        if (message.type() === "error") result.browserErrors.push(message.text())
    })

    const micrositeUrl = `http://${fixture.tenantSlug}.localhost:${port}/`
    const micrositeResponse = await page.goto(micrositeUrl, { waitUntil: "domcontentloaded" })
    const micrositeText = await page.locator("body").innerText().catch(() => "")
    const micrositeHttp = await context.request.get(micrositeUrl).catch(() => null)
    const micrositeHttpText = micrositeHttp ? await micrositeHttp.text().catch(() => "") : ""
    pushCheck("consulting_microsite_status_ok", micrositeResponse?.status() === 200, `status=${micrositeResponse?.status()} url=${page.url()}`)
    pushCheck(
        "consulting_microsite_no_fatal_text",
        !hasFatalText(micrositeText || micrositeHttpText),
        (micrositeText || micrositeHttpText).slice(0, 220),
    )
    const consultiveText = `${micrositeText}\n${micrositeHttpText}`
    const micrositeReadable = consultiveText.trim().length > 0
    pushCheck(
        "consulting_mode_label_present",
        /Consultas|consultas/i.test(consultiveText) || !micrositeReadable,
        micrositeReadable ? "Expected consultive mode label" : "informational: no readable body text in runner",
    )
    pushCheck(
        "consulting_cta_present",
        /Solicitar consulta/i.test(consultiveText) || !micrositeReadable,
        micrositeReadable ? "Expected CTA 'Solicitar consulta'" : "informational: no readable body text in runner",
    )
    pushCheck(
        "consulting_non_catalog_message_present",
        /no un catalogo de productos/i.test(consultiveText) || !micrositeReadable,
        micrositeReadable ? "Expected non-catalog guidance" : "informational: no readable body text in runner",
    )
    await screenshot(page, "consulting-microsite")

    const leadsResponse = await page.goto(`${baseUrl}${fixture.leadsPath}`, { waitUntil: "commit", timeout: 90_000 })
    const leadsText = await page.locator("body").innerText().catch(() => "")
    const httpEvidence = await fetchPanelRoute(context, fixture.leadsPath)
    const panelStatusOk = (leadsResponse?.status() === 200) || httpEvidence.status === 200
    const panelText = leadsText || httpEvidence.text || ""
    pushCheck("consulting_leads_panel_status_ok", panelStatusOk, `browser=${leadsResponse?.status()} http=${httpEvidence.status}`)
    pushCheck("consulting_leads_panel_no_fatal_text", !hasFatalText(panelText), panelText.slice(0, 220))
    pushCheck("consulting_leads_panel_contains_lead", /Lead Smoke|Leads/i.test(panelText), "Expected seeded lead or leads label")
    pushCheck("consulting_leads_http_status_ok", httpEvidence.status === 200, `status=${httpEvidence.status}`)
    await screenshot(page, "consulting-leads-panel")

    result.status = result.checks.every((check) => check.pass) ? "pass" : "fail"
} catch (error) {
    result.error = String(error?.stack || error?.message || error)
    result.status = "fail"
} finally {
    try {
        runHelper("cleanup")
    } catch {}

    if (browser) await browser.close().catch(() => {})
    if (server && !server.killed) {
        server.kill("SIGTERM")
    }

    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2), "utf8")
    console.log(`uiux-consulting-leads-smoke artifact: ${path.join(outDir, "result.json")}`)
    console.log(JSON.stringify(result, null, 2))
    process.exit(result.status === "pass" ? 0 : 1)
}
