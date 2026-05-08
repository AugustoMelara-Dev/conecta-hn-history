import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8823 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-appointments-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT ||
    "docs/frontend/audit-artifacts/2026-05-07/browser/appointments-smoke"
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

const helperPath = path.join(os.tmpdir(), `conecta-uiux-appointments-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\Appointment;
use App\\Models\\Service;
use App\\Models\\ServiceProvider;
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

function ensureGeneralSettingsForAppointments(): void {
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

function onlyExistingColumnsForAppointments(string $table, array $data): array {
    return array_filter(
        $data,
        fn ($column) => Schema::hasColumn($table, $column),
        ARRAY_FILTER_USE_KEY,
    );
}

function ensurePanelMembershipsForAppointments(User $user, Tenant $tenant): void {
    if (Schema::hasTable('tenant_memberships')) {
        DB::table('tenant_memberships')->updateOrInsert(
            onlyExistingColumnsForAppointments('tenant_memberships', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ]),
            onlyExistingColumnsForAppointments('tenant_memberships', [
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

    if (Schema::hasTable('tenant_users')) {
        DB::table('tenant_users')->updateOrInsert(
            onlyExistingColumnsForAppointments('tenant_users', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ]),
            onlyExistingColumnsForAppointments('tenant_users', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'can_manage_products' => true,
                'can_manage_orders' => true,
                'can_view_analytics' => true,
                'can_manage_settings' => true,
                'can_manage_team' => true,
                'can_manage_billing' => true,
                'accepted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        );
    }
}

if ($mode === 'setup') {
    ensureGeneralSettingsForAppointments();

    foreach (['service_providers', 'services', 'appointments'] as $table) {
        if (! Schema::hasTable($table)) {
            echo json_encode(['skip' => true, 'reason' => "missing {$table}"], JSON_THROW_ON_ERROR);
            exit(0);
        }
    }

    $email = $runId.'@example.test';
    $password = 'Password123!';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Agenda',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Agenda',
        'slug' => $runId,
        'description' => 'Fixture temporal para validar agenda Filament.',
        'user_id' => $user->id,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => 'pro',
        'subscription_status' => 'active',
        'business_type' => 'service',
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

    ensurePanelMembershipsForAppointments($user, $tenant);

    $service = new Service();
    $service->forceFill([
        'tenant_id' => $tenant->id,
        'name' => 'Corte premium',
        'description' => 'Servicio temporal para smoke UI.',
        'duration_minutes' => 45,
        'price' => 350,
        'is_active' => true,
    ]);
    Service::withoutEvents(fn () => $service->save());

    $provider = new ServiceProvider();
    $provider->forceFill([
        'tenant_id' => $tenant->id,
        'name' => 'Ana Agenda',
        'email' => 'agenda-'.$runId.'@example.test',
        'phone' => '99999999',
        'is_active' => true,
    ]);
    ServiceProvider::withoutEvents(fn () => $provider->save());

    $appointment = new Appointment();
    $appointment->forceFill([
        'tenant_id' => $tenant->id,
        'service_id' => $service->id,
        'service_provider_id' => $provider->id,
        'scheduled_at' => now()->addDay()->setTime(10, 0),
        'end_at' => now()->addDay()->setTime(10, 45),
        'status' => Appointment::STATUS_PENDING,
        'customer_name' => 'Cliente Smoke',
        'customer_phone' => '99999999',
        'staff_notes' => 'Preparar espacio antes de la cita.',
    ]);
    Appointment::withoutEvents(fn () => $appointment->save());

    echo json_encode([
        'email' => $email,
        'password' => $password,
        'tenantSlug' => $tenant->slug,
        'appointmentsPath' => '/app/'.$tenant->slug.'/appointments',
        'createPath' => '/app/'.$tenant->slug.'/appointments/create',
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
        if (Schema::hasTable('appointments')) {
            DB::table('appointments')->where('tenant_id', $tenant->id)->delete();
        }

        if (Schema::hasTable('services')) {
            DB::table('services')->where('tenant_id', $tenant->id)->delete();
        }

        if (Schema::hasTable('service_providers')) {
            DB::table('service_providers')->where('tenant_id', $tenant->id)->delete();
        }

        if (Schema::hasTable('tenant_memberships')) {
            DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->delete();
        }

        if (Schema::hasTable('tenant_users')) {
            DB::table('tenant_users')->where('tenant_id', $tenant->id)->delete();
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
    database: {
        connection: "sqlite",
        path: sqlitePath,
        isolated: !process.env.UIUX_SMOKE_DB,
    },
    status: "fail",
    checks: [],
    screenshots: {},
    browserErrors: [],
    serverOutput: [],
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
        timeout: 120_000,
        maxBuffer: 10 * 1024 * 1024,
        windowsHide: true,
    })

    if (helper.status !== 0 || helper.error) {
        throw new Error(`${mode} helper failed with status=${helper.status ?? "null"} signal=${helper.signal || "none"} error=${helper.error?.message || "none"}\nSTDOUT:\n${helper.stdout || ""}\nSTDERR:\n${helper.stderr || ""}`)
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
let serverExited = false

function startServer() {
    if (!shouldStartServer) {
        return
    }

    serverExited = false
    server = spawn(phpBin, ["artisan", "serve", "--host", host, "--port", String(port)], {
        cwd: root,
        env: smokeEnv,
        windowsHide: true,
        stdio: ["ignore", "pipe", "pipe"],
    })
    server.stdout?.on("data", (chunk) => result.serverOutput.push(String(chunk).trim()))
    server.stderr?.on("data", (chunk) => result.serverOutput.push(String(chunk).trim()))
    server.on("exit", (code, signal) => {
        serverExited = true
        result.serverOutput.push(`server exited code=${code ?? "null"} signal=${signal || "none"}`)
    })
}

async function ensureServerReady() {
    if (!shouldStartServer) {
        return
    }

    if (!server || serverExited) {
        startServer()
    }

    await waitForServer()
}

function runArtisan(args, label) {
    const command = spawnSync(phpBin, ["artisan", ...args], {
        cwd: root,
        encoding: "utf8",
        env: smokeEnv,
        windowsHide: true,
    })

    if (command.status !== 0) {
        throw new Error(`${label} failed: ${command.stderr || command.stdout}`)
    }

    return command
}

function hasFatalText(text) {
    return /Exception|Stack trace|SQLSTATE|Fatal error|Internal Server Error|Server Error/i.test(text)
}

function hasEncodingDebt(text) {
    return /\u00c3|\u00e2|\u00f0\u0178|\ufffd/.test(text)
}

function normalizeText(text) {
    return String(text)
        .normalize("NFD")
        .replace(/\p{Diacritic}/gu, "")
        .toLowerCase()
}

function includesText(bodyText, expectedText) {
    return normalizeText(bodyText).includes(normalizeText(expectedText))
}

function isGuardedPanelUrl(currentUrl) {
    try {
        const pathname = new URL(currentUrl).pathname

        return pathname === "/app/login" || pathname === "/login"
    } catch {
        return false
    }
}

function isAppointmentsIndexUrl(currentUrl, fixture) {
    try {
        return new URL(currentUrl).pathname === fixture.appointmentsPath
    } catch {
        return false
    }
}

async function screenshot(page, name) {
    const filePath = path.join(outDir, `${name}.png`)
    await page.screenshot({ path: filePath, fullPage: true })
    result.screenshots[name] = filePath
}

async function login(page, fixture) {
    await ensureServerReady()
    const response = await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded", timeout: 60_000 })
    pushCheck("login_route_200", (response?.status() || 0) < 500, `status=${response?.status()}`)

    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute("content")
    const authStatus = await page.evaluate(
        async ({ loginUrl, email, password, token }) => {
            const body = new URLSearchParams()
            body.set("_token", token || "")
            body.set("email", email)
            body.set("password", password)
            body.set("remember", "0")

            const loginResponse = await fetch(loginUrl, {
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

            return { status: loginResponse.status, url: loginResponse.url }
        },
        { loginUrl: `${baseUrl}/login`, email: fixture.email, password: fixture.password, token: csrfToken },
    )

    await ensureServerReady()
    await page.goto(`${baseUrl}${fixture.appointmentsPath}`, { waitUntil: "commit", timeout: 90_000 }).catch(() => {})
    await page.waitForLoadState("domcontentloaded", { timeout: 45_000 }).catch(() => {})
    const currentUrl = page.url()
    const bodyText = await page.locator("body").innerText().catch(() => "")

    pushCheck(
        "login_fixture_ok",
        authStatus.status < 500 && !isGuardedPanelUrl(currentUrl) && !hasFatalText(bodyText),
        `status=${authStatus.status} url=${currentUrl}`,
    )
}

async function fetchPanelRoute(context, urlPath) {
    try {
        const response = await context.request.get(`${baseUrl}${urlPath}`, {
            maxRedirects: 5,
            timeout: 60_000,
            headers: {
                Accept: "text/html, application/xhtml+xml",
                "X-Requested-With": "XMLHttpRequest",
            },
        })
        const bodyText = await response.text().catch(() => "")

        return { status: response.status(), url: response.url(), bodyText }
    } catch (error) {
        return {
            status: 0,
            url: `${baseUrl}${urlPath}`,
            bodyText: "",
            error: error instanceof Error ? error.message : String(error),
        }
    }
}

async function validateAppointments(page, context, fixture, label, options = {}) {
    const shouldNavigate = options.navigate !== false
    let response = null
    let bodyText = ""
    let currentUrl = page.url()

    try {
        if (!shouldNavigate && isAppointmentsIndexUrl(currentUrl, fixture)) {
            bodyText = await page.locator("body").innerText().catch(() => "")
        } else {
            await ensureServerReady()
            response = await page.goto(`${baseUrl}${fixture.appointmentsPath}`, { waitUntil: "commit", timeout: 90_000 })
            await page.waitForLoadState("domcontentloaded", { timeout: 45_000 }).catch(() => {})
            await page.getByText("Agenda", { exact: false }).first().waitFor({ state: "visible", timeout: 12_000 }).catch(() => {})
            bodyText = await page.locator("body").innerText().catch(() => "")
            currentUrl = page.url()
        }
    } catch (error) {
        pushCheck(`${label}_appointments_browser_fallback`, true, error instanceof Error ? error.message : String(error))
    }

    if (!bodyText || hasFatalText(bodyText) || !isAppointmentsIndexUrl(currentUrl, fixture)) {
        await ensureServerReady()
        const httpEvidence = await fetchPanelRoute(context, fixture.appointmentsPath)
        if (httpEvidence.error) {
            pushCheck(`${label}_appointments_http_fallback`, true, httpEvidence.error)
        }
        response = { status: () => httpEvidence.status }
        currentUrl = httpEvidence.url
        bodyText = httpEvidence.bodyText
    }

    pushCheck(
        `${label}_appointments_status_ok`,
        isAppointmentsIndexUrl(currentUrl, fixture) && (response?.status() || 200) === 200,
        `status=${response?.status() || "current"} url=${currentUrl}`,
    )
    pushCheck(`${label}_appointments_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 200))
    pushCheck(`${label}_appointments_no_encoding_debt`, !hasEncodingDebt(bodyText), bodyText.slice(0, 240))

    for (const text of ["Cliente Smoke", "Corte premium", "Ana Agenda", "Pendiente"]) {
        pushCheck(
            `${label}_appointments_text_${text.replaceAll(" ", "_")}`,
            includesText(bodyText, text),
            text,
        )
    }

    if (isAppointmentsIndexUrl(page.url(), fixture)) {
        await screenshot(page, `${label}-appointments`)
    }
}

async function validateCreate(page, context, fixture) {
    let response = null
    let currentUrl = page.url()
    let bodyText = ""

    try {
        await ensureServerReady()
        response = await page.goto(`${baseUrl}${fixture.createPath}`, { waitUntil: "commit", timeout: 90_000 })
        await page.waitForLoadState("domcontentloaded", { timeout: 45_000 }).catch(() => {})
        bodyText = await page.locator("body").innerText().catch(() => "")
        currentUrl = page.url()
    } catch (error) {
        pushCheck("create_appointment_browser_fallback", true, error instanceof Error ? error.message : String(error))
    }

    if (!bodyText || hasFatalText(bodyText) || !currentUrl.includes(fixture.createPath)) {
        await ensureServerReady()
        const httpEvidence = await fetchPanelRoute(context, fixture.createPath)
        if (httpEvidence.error) {
            pushCheck("create_appointment_http_fallback", true, httpEvidence.error)
        }
        response = { status: () => httpEvidence.status }
        currentUrl = httpEvidence.url
        bodyText = httpEvidence.bodyText
    }

    pushCheck("create_appointment_status_ok", currentUrl.includes(fixture.createPath) && response?.status() === 200, `status=${response?.status()} url=${currentUrl}`)
    pushCheck("create_appointment_no_fatal_text", !hasFatalText(bodyText), bodyText.slice(0, 200))
    pushCheck("create_appointment_no_encoding_debt", !hasEncodingDebt(bodyText), bodyText.slice(0, 240))

    for (const text of ["Cliente registrado", "Nombre del cliente", "Teléfono", "Atendido por", "Notas internas"]) {
        pushCheck(
            `create_appointment_text_${text.replaceAll(" ", "_")}`,
            includesText(bodyText, text),
            text,
        )
    }
}

try {
    fs.closeSync(fs.openSync(sqlitePath, "a"))
    runArtisan(["config:clear"], "config clear")
    runArtisan(["view:clear"], "view clear")
    runArtisan(["migrate", "--force", "--no-interaction"], "smoke sqlite migration")
    pushCheck("smoke_sqlite_migrated", true, sqlitePath)

    startServer()
    await waitForServer()

    const fixture = runHelper("setup")
    if (fixture.skip) {
        pushCheck("appointments_schema_available", false, fixture.reason)
        result.skipped = true
    } else {
        const browser = await chromium.launch({ headless: true })
        const loginContext = await browser.newContext({ viewport: { width: 1366, height: 900 } })
        const loginPage = await loginContext.newPage()
        await login(loginPage, fixture)
        const storageState = await loginContext.storageState()
        await loginPage.close().catch(() => {})
        await loginContext.close().catch(() => {})

        const context = await browser.newContext({
            viewport: { width: 1366, height: 900 },
            storageState,
            javaScriptEnabled: false,
        })

        await context.route("**/livewire/update", async (route) => {
            await route.fulfill({ status: 204, body: "" })
        })

        const page = await context.newPage()
        page.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
        page.on("console", (message) => {
            if (["error", "warning"].includes(message.type())) {
                result.browserErrors.push(`${message.type()}: ${message.text()}`)
            }
        })
        await validateAppointments(page, context, fixture, "desktop")
        await validateCreate(page, context, fixture)
        await page.setViewportSize({ width: 390, height: 844 })
        await validateAppointments(page, context, fixture, "mobile")
        await page.close().catch(() => {})

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

    fs.rmSync(helperPath, { force: true })
    fs.rmSync(configCachePath, { force: true })
    fs.rmSync(viewCompiledPath, { force: true, recursive: true })
    if (!process.env.UIUX_SMOKE_DB) {
        fs.rmSync(sqlitePath, { force: true })
    }
    if (result.fixtureCleaned === false) {
        result.status = "fail"
    } else if (!result.error) {
        result.status = result.checks.every((check) => check.pass) ? "pass" : "fail"
    }
    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2))
    console.log(`uiux-thirtieth-appointments-smoke artifact: ${path.join(outDir, "result.json")}`)
    console.log(JSON.stringify(result, null, 2))

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

process.exit(result.status === "pass" ? 0 : 1)
