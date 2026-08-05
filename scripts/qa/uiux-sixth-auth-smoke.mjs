import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import AxeBuilder from "@axe-core/playwright"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const defaultPort = 8012 + Math.floor(Math.random() * 300)
const port = Number(process.env.UIUX_SMOKE_PORT || defaultPort)
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot = process.env.UIUX_SMOKE_ARTIFACT_ROOT || "artifacts/uiux-sixth-auth-smoke"
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

const helperPath = path.join(os.tmpdir(), `conecta-uiux-sixth-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\Payment;
use App\\Models\\Tenant;
use App\\Models\\User;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\Password;
use Illuminate\\Support\\Facades\\Schema;
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
        'name' => 'Smoke UI Auth',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => null,
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke UI Pago',
        'slug' => $runId,
        'description' => 'Fixture temporal para smoke UI/UX.',
        'user_id' => $user->id,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => 'semilla',
        'subscription_status' => 'pending',
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

    $paymentData = [
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'amount' => '499.00',
        'proof_path' => 'smoke/proof-placeholder.jpg',
        'bank' => 'bac',
        'reference_number' => 'SMOKE-'.$runId,
        'status' => 'pending',
        'idempotency_key' => 'smoke-'.$runId,
        'version' => 1,
    ];

    $payment = null;
    if (Schema::hasTable('subscription_payments')) {
        $payment = new Payment();
        $payment->forceFill(array_filter(
            $paymentData,
            fn ($value, string $column): bool => Schema::hasColumn('subscription_payments', $column),
            ARRAY_FILTER_USE_BOTH
        ));
        Payment::withoutEvents(fn () => $payment->save());
    }

    $token = Password::broker()->createToken($user);

    echo json_encode([
        'email' => $email,
        'password' => $password,
        'token' => $token,
        'userId' => $user->id,
        'tenantId' => $tenant->id,
        'paymentId' => $payment?->id,
        'supportsPayments' => $payment !== null,
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    DB::table('password_reset_tokens')->where('email', $runId.'@example.test')->delete();

    if (Schema::hasTable('subscription_payments')) {
        if (Schema::hasColumn('subscription_payments', 'idempotency_key')) {
            Payment::withoutGlobalScopes()->where('idempotency_key', 'smoke-'.$runId)->delete();
        } elseif (Schema::hasColumn('subscription_payments', 'reference_number')) {
            Payment::withoutGlobalScopes()->where('reference_number', 'SMOKE-'.$runId)->delete();
        }
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
        env: smokeEnv,
        encoding: "utf8",
        windowsHide: true,
    })

    if (helper.status !== 0) {
        throw new Error(`${mode} helper failed: ${helper.stderr || helper.stdout}`)
    }

    try {
        const output = helper.stdout.trim()
        const jsonStart = output.indexOf("{")
        const jsonEnd = output.lastIndexOf("}")

        if (jsonStart === -1 || jsonEnd === -1 || jsonEnd < jsonStart) {
            throw new Error("missing JSON object")
        }

        return JSON.parse(output.slice(jsonStart, jsonEnd + 1))
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

async function visible(page, testId, timeout = 10_000) {
    await page.getByTestId(testId).waitFor({ state: "visible", timeout })

    return true
}

let server
let browser
let fixture

try {
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
    fixture = runHelper("setup")

    browser = await chromium.launch({ headless: true })
    const context = await browser.newContext({ viewport: { width: 1366, height: 900 } })
    let page = await context.newPage()

    const resetResponse = await page.goto(
        `${baseUrl}/reset-password/${fixture.token}?email=${encodeURIComponent(fixture.email)}`,
        { waitUntil: "domcontentloaded" },
    )
    pushCheck("reset_password_fixture_route_200", resetResponse?.status() === 200, `status=${resetResponse?.status()}`)
    pushCheck("reset_password_form_visible", await visible(page, "reset-password-submit").catch(() => false))
    await screenshot(page, "reset-password-fixture")

    await page.close().catch(() => {})
    page = await context.newPage()

    const loginResponse = await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded" })
    pushCheck("login_route_200", loginResponse?.status() === 200, `status=${loginResponse?.status()}`)
    await visible(page, "login-email-input")
    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute("content")
    const authResponse = await page.evaluate(
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
    pushCheck(
        "login_fixture_post_redirects",
        authResponse.status < 500 && !new URL(authResponse.url).pathname.endsWith("/login"),
        `status=${authResponse.status} url=${authResponse.url}`,
    )

    const verifyResponse = await page.goto(`${baseUrl}/verify-email`, { waitUntil: "domcontentloaded" })
    pushCheck("verify_email_authenticated_200", verifyResponse?.status() === 200, `status=${verifyResponse?.status()}`)
    pushCheck("verify_email_content_visible", await visible(page, "verify-email-resend").catch(() => false))
    await screenshot(page, "verify-email-authenticated")

    const confirmResponse = await page.goto(`${baseUrl}/confirm-password`, { waitUntil: "domcontentloaded" })
    pushCheck("confirm_password_authenticated_200", confirmResponse?.status() === 200, `status=${confirmResponse?.status()}`)
    pushCheck("confirm_password_form_visible", await visible(page, "confirm-password-submit").catch(() => false))
    await screenshot(page, "confirm-password-authenticated")

    if (fixture.supportsPayments && fixture.paymentId) {
        const paymentResponse = await page.goto(`${baseUrl}/registro/pago-pendiente/${fixture.paymentId}`, {
            waitUntil: "domcontentloaded",
        })
        pushCheck("payment_pending_fixture_200", paymentResponse?.status() === 200, `status=${paymentResponse?.status()}`)
        pushCheck("payment_pending_summary_visible", await visible(page, "payment-pending-summary").catch(() => false))

        const paymentAxe = await new AxeBuilder({ page })
            .withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"])
            .analyze()
        pushCheck(
            "payment_pending_no_detected_wcag_a_aa_violations",
            paymentAxe.violations.length === 0,
            JSON.stringify(paymentAxe.violations),
        )

        await page.setViewportSize({ width: 360, height: 800 })
        const paymentHasHorizontalOverflow = await page.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
        )
        pushCheck("payment_pending_reflows_at_360px", !paymentHasHorizontalOverflow)
        await screenshot(page, "payment-pending-fixture")
    } else {
        pushCheck("payment_pending_fixture_available", true, "skipped: subscription_payments table unavailable")
    }

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
    fs.rmSync(configCachePath, { force: true })
    if (!process.env.UIUX_SMOKE_DB) {
        fs.rmSync(sqlitePath, { force: true })
    }
}

const outFile = path.join(outDir, "result.json")
fs.writeFileSync(outFile, JSON.stringify(result, null, 2), "utf8")
console.log(`uiux-sixth-auth-smoke artifact: ${outFile}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
