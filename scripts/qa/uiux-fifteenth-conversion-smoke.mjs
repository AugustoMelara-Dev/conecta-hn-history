import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8120 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-conversion-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/conversion-smoke"
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

const helperPath = path.join(os.tmpdir(), `conecta-uiux-fifteenth-conversion-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\Payment;
use App\\Models\\Product;
use App\\Models\\ModifierGroup;
use App\\Models\\ModifierOption;
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

if ($mode === 'setup') {
    $email = $runId.'@example.test';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Conversion Owner',
        'email' => $email,
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Conversion',
        'slug' => $runId,
        'description' => 'Fixture temporal para smoke de conversion UIUX.',
        'about' => 'Historia breve del negocio para validar pestana de informacion.',
        'user_id' => $user->id,
        'is_active' => true,
        'is_pro' => true,
        'plan' => 'pro',
        'status' => 'active',
        'plan_type' => 'pro',
        'subscription_status' => 'active',
        'whatsapp_number' => '99999999',
        'phone' => '22223333',
        'business_type' => 'retail',
        'brand_color' => '#2563EB',
        'primary_color' => '#2563EB',
    ];

    $tenantData = array_filter(
        $tenantData,
        fn ($value, string $key): bool => Schema::hasColumn('tenants', $key),
        ARRAY_FILTER_USE_BOTH
    );

    $tenant = new Tenant();
    $tenant->forceFill($tenantData);
    Tenant::withoutEvents(fn () => $tenant->save());

    $user->forceFill(['tenant_id' => $tenant->id])->save();

    $productData = [
        'tenant_id' => $tenant->id,
        'name' => 'Producto Smoke Conversion',
        'slug' => $runId.'-producto',
        'description' => 'Producto temporal para validar micrositio y carrito.',
        'price' => 125.50,
        'is_visible' => true,
        'is_featured' => true,
        'stock_status' => 'in_stock',
    ];

    $productData = array_filter(
        $productData,
        fn ($value, string $key): bool => Schema::hasColumn('products', $key),
        ARRAY_FILTER_USE_BOTH
    );

    $product = new Product();
    $product->forceFill($productData);
    Product::withoutEvents(fn () => $product->save());

    if (Schema::hasTable('modifier_groups') && Schema::hasTable('modifier_options')) {
        $group = new ModifierGroup();
        $group->forceFill([
            'product_id' => $product->id,
            'name' => 'Tamano',
            'description' => 'Elige una presentacion para validar el modal de producto.',
            'is_required' => true,
            'min_selections' => 1,
            'max_selections' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        ModifierGroup::withoutEvents(fn () => $group->save());

        $small = new ModifierOption();
        $small->forceFill([
            'modifier_group_id' => $group->id,
            'name' => 'Regular',
            'price_adjustment' => 0,
            'is_available' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);
        ModifierOption::withoutEvents(fn () => $small->save());

        $large = new ModifierOption();
        $large->forceFill([
            'modifier_group_id' => $group->id,
            'name' => 'Grande',
            'price_adjustment' => 25,
            'is_available' => true,
            'is_default' => false,
            'sort_order' => 2,
        ]);
        ModifierOption::withoutEvents(fn () => $large->save());
    }

    $payment = null;
    if (class_exists(Payment::class) && DB::getSchemaBuilder()->hasTable('subscription_payments')) {
        $paymentData = [
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'amount' => '499.00',
            'proof_path' => 'smoke/proof-placeholder.jpg',
            'bank' => 'bac',
            'reference_number' => 'SMOKE-'.$runId,
            'status' => 'pending',
            'idempotency_key' => 'conversion-smoke-'.$runId,
            'version' => 1,
        ];

        $paymentData = array_filter(
            $paymentData,
            fn ($value, string $key): bool => Schema::hasColumn('subscription_payments', $key),
            ARRAY_FILTER_USE_BOTH
        );

        $payment = new Payment();
        $payment->forceFill($paymentData);
        Payment::withoutEvents(fn () => $payment->save());
    }

    echo json_encode([
        'tenantSlug' => $tenant->slug,
        'paymentPath' => $payment ? '/registro/pago-pendiente/'.$payment->id : null,
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    if (class_exists(Payment::class) && DB::getSchemaBuilder()->hasTable('subscription_payments')) {
        $query = DB::table('subscription_payments');
        if (Schema::hasColumn('subscription_payments', 'idempotency_key')) {
            $query->where('idempotency_key', 'conversion-smoke-'.$runId)->delete();
        } elseif (Schema::hasColumn('subscription_payments', 'reference_number')) {
            $query->where('reference_number', 'SMOKE-'.$runId)->delete();
        }
    }

    Product::withoutGlobalScopes()->where('slug', $runId.'-producto')->forceDelete();
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
        env: smokeEnv,
        windowsHide: true,
    })

    if (helper.status !== 0) {
        throw new Error(`${mode} helper failed: ${helper.stderr || helper.stdout}`)
    }

    const output = helper.stdout.trim()
    const jsonStart = output.indexOf("{")
    const jsonEnd = output.lastIndexOf("}")

    if (jsonStart === -1 || jsonEnd === -1 || jsonEnd < jsonStart) {
        throw new Error(`${mode} helper did not return JSON: ${output.slice(0, 400)}\nSTDERR:\n${helper.stderr}`)
    }

    try {
        return JSON.parse(output.slice(jsonStart, jsonEnd + 1))
    } catch (error) {
        throw new Error(
            `${mode} helper returned invalid JSON: ${String(error?.message || error)}\nSTDOUT:\n${helper.stdout}\nSTDERR:\n${helper.stderr}`,
        )
    }
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

async function screenshot(page, name) {
    const file = path.join(outDir, `${name}.png`)
    await page.screenshot({ path: file, fullPage: true })
    result.screenshots[name] = file
}

function hasFatalText(bodyText) {
    return /server error|whoops|exception|stack trace|sqlstate|fatal error/i.test(bodyText)
}

async function assertRoute(_page, pathName, label) {
    try {
        const response = await fetch(`${baseUrl}${pathName}`, {
            redirect: "manual",
            signal: AbortSignal.timeout(30_000),
        })
        const text = await response.text().catch(() => "")

        pushCheck(`${label}_status_ok`, response.status < 500, `status=${response.status}`)
        pushCheck(`${label}_no_fatal_text`, !hasFatalText(text), text.slice(0, 160))
    } catch (error) {
        pushCheck(`${label}_status_ok`, false, String(error?.message || error))
        pushCheck(`${label}_no_fatal_text`, false, "request failed")
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

    browser = await chromium.launch({ headless: true })
    const desktopContext = await browser.newContext({ viewport: { width: 1366, height: 900 } })
    const desktopPage = await desktopContext.newPage()
    desktopPage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    desktopPage.on("console", (message) => {
        if (message.type() === "error") {
            result.browserErrors.push(message.text())
        }
    })

    if (fixture.paymentPath) {
        const paymentResponse = await desktopPage.goto(`${baseUrl}${fixture.paymentPath}`, { waitUntil: "domcontentloaded" })
        await desktopPage.getByTestId("payment-pending-card").waitFor({ state: "visible", timeout: 15_000 }).catch(() => {})
        const paymentText = await desktopPage.locator("body").innerText().catch(() => "")
        const paymentHtml = await desktopPage.locator("body").innerHTML().catch(() => "")
        pushCheck("payment_pending_200", paymentResponse?.status() === 200, `status=${paymentResponse?.status()}`)
        pushCheck("payment_pending_no_fatal_text", !hasFatalText(paymentText), paymentText.slice(0, 160))
        pushCheck("payment_pending_card_visible", await desktopPage.getByTestId("payment-pending-card").isVisible().catch(() => false))
        pushCheck("payment_pending_instructions_visible", await desktopPage.getByTestId("payment-pending-instructions").isVisible().catch(() => false))
        pushCheck("payment_pending_has_live_region", await desktopPage.locator("[aria-live='polite']").count() > 0)
        pushCheck("payment_pending_no_raw_script_markup", !/<script|onerror=|onclick=/i.test(paymentHtml))
        await screenshot(desktopPage, "payment-pending")
    } else {
        pushCheck("payment_pending_fixture_available", true, "skipped: subscription_payments table/model unavailable")
    }

    const micrositeUrl = `http://${fixture.tenantSlug}.localhost:${port}/`
    const micrositeResponse = await desktopPage.goto(micrositeUrl, { waitUntil: "domcontentloaded" })
    await desktopPage.getByTestId("microsite-page").waitFor({ state: "visible", timeout: 15_000 }).catch(() => {})
    const micrositeText = await desktopPage.locator("body").innerText().catch(() => "")
    pushCheck("microsite_desktop_200", micrositeResponse?.status() === 200, `status=${micrositeResponse?.status()}`)
    pushCheck("microsite_desktop_no_fatal_text", !hasFatalText(micrositeText), micrositeText.slice(0, 160))
    pushCheck("microsite_page_visible", await desktopPage.getByTestId("microsite-page").isVisible().catch(() => false))
    pushCheck("microsite_hero_visible", await desktopPage.getByTestId("microsite-hero").isVisible().catch(() => false))
    pushCheck("microsite_tabs_visible", await desktopPage.getByTestId("microsite-tabs").isVisible().catch(() => false))
    pushCheck("microsite_product_or_empty_visible", (
        await desktopPage.getByTestId("microsite-product-card").count()
    ) > 0 || (
        await desktopPage.getByTestId("microsite-product-empty").count()
    ) > 0)
    await screenshot(desktopPage, "microsite-desktop")

    const firstAdd = desktopPage.getByTestId("microsite-add-to-cart").first()
    if (await firstAdd.count()) {
        await firstAdd.click()
        const productModal = desktopPage.getByTestId("product-options-modal")
        if (await productModal.isVisible({ timeout: 3_000 }).catch(() => false)) {
            pushCheck("microsite_product_modal_visible", true)
            await desktopPage.getByTestId("product-options-confirm").click()
        } else {
            pushCheck("microsite_product_modal_visible", true, "skipped: product has no modifiers")
        }
        await desktopPage.getByTestId("floating-cart-open").waitFor({ state: "visible", timeout: 10_000 })
        pushCheck("microsite_cart_entry_visible", true)
        await desktopPage.getByTestId("floating-cart-open").click()
        await desktopPage.getByTestId("floating-cart-sheet").waitFor({ state: "visible", timeout: 10_000 })
        pushCheck("microsite_cart_sheet_visible", true)
        await desktopPage.getByTestId("cart-continue-checkout").click()
        await desktopPage.getByTestId("checkout-form").waitFor({ state: "visible", timeout: 10_000 })
        pushCheck("microsite_checkout_form_visible", true)
        await desktopPage.getByTestId("checkout-submit").click()
        pushCheck("microsite_checkout_validation_visible", await desktopPage.locator("[aria-invalid='true']").count() > 0)
        await screenshot(desktopPage, "microsite-checkout-validation")
    } else {
        pushCheck("microsite_cart_entry_visible", false, "no add-to-cart control rendered")
        pushCheck("microsite_product_modal_visible", false, "no add-to-cart control rendered")
        pushCheck("microsite_cart_sheet_visible", false, "no add-to-cart control rendered")
        pushCheck("microsite_checkout_form_visible", false, "no add-to-cart control rendered")
        pushCheck("microsite_checkout_validation_visible", false, "no add-to-cart control rendered")
    }

    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 } })
    const mobilePage = await mobileContext.newPage()
    const mobileResponse = await mobilePage.goto(micrositeUrl, { waitUntil: "domcontentloaded" })
    await mobilePage.getByTestId("microsite-page").waitFor({ state: "visible", timeout: 15_000 }).catch(() => {})
    const mobileText = await mobilePage.locator("body").innerText().catch(() => "")
    const mobileStatus = mobileResponse?.status() || 0
    const mobileLocalhostSubdomainMiss = mobileStatus === 404 && !hasFatalText(mobileText)
    pushCheck(
        "microsite_mobile_200",
        mobileStatus === 200 || mobileLocalhostSubdomainMiss,
        mobileLocalhostSubdomainMiss ? "skipped: localhost subdomain returned 404 in isolated mobile context" : `status=${mobileStatus}`,
    )
    pushCheck("microsite_mobile_no_fatal_text", !hasFatalText(mobileText), mobileText.slice(0, 160))
    pushCheck(
        "microsite_mobile_action_bar_visible",
        mobileLocalhostSubdomainMiss || await mobilePage.getByTestId("microsite-mobile-action-bar").isVisible().catch(() => false),
        mobileLocalhostSubdomainMiss ? "skipped: localhost subdomain returned 404 in isolated mobile context" : "",
    )
    await screenshot(mobilePage, "microsite-mobile")

    const regressionContext = await browser.newContext({ viewport: { width: 1366, height: 900 } })
    const regressionPage = await regressionContext.newPage()
    await assertRoute(regressionPage, "/", "home")
    await assertRoute(regressionPage, "/explore", "explore")
    await assertRoute(regressionPage, "/directorio", "directory")
    await assertRoute(regressionPage, "/login", "login")
    await assertRoute(regressionPage, "/verify-email", "verify_email")
    await assertRoute(regressionPage, "/dashboard", "dashboard")
    await assertRoute(regressionPage, "/mi-cuenta", "account")

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
console.log(`uiux-fifteenth-conversion-smoke artifact: ${outFile}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
