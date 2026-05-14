import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8410 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const runId = `uiux-directory-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/public-directory-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))
const sqlitePath = process.env.UIUX_SMOKE_DB || path.join(os.tmpdir(), `${runId}.sqlite`)
const configCachePath = path.join(os.tmpdir(), `${runId}-config.php`)
const helperPath = path.join(os.tmpdir(), `conecta-uiux-twentieth-public-directory-helper-${process.pid}.php`)
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
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\Category;
use App\\Models\\City;
use App\\Models\\Product;
use App\\Models\\Tenant;
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

function onlyExisting(string $table, array $data): array {
    return array_filter(
        $data,
        fn ($value, string $key): bool => Schema::hasColumn($table, $key),
        ARRAY_FILTER_USE_BOTH
    );
}

function ensureGeneralSettings(): void {
    if (! Schema::hasTable('settings')) {
        return;
    }

    foreach ([
        'maintenance_mode' => false,
        'allow_registrations' => true,
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
    ensureGeneralSettings();

    $email = $runId.'@example.test';
    $password = 'Password123!';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Public Directory',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $city = null;
    if (Schema::hasTable('cities')) {
        $city = new City();
        $city->forceFill(onlyExisting('cities', [
            'name' => 'San Pedro Smoke',
            'slug' => $runId.'-city',
            'is_active' => true,
            'is_featured' => true,
        ]));
        City::withoutEvents(fn () => $city->save());
    }

    $category = null;
    if (Schema::hasTable('categories')) {
        $category = new Category();
        $category->forceFill(onlyExisting('categories', [
            'name' => 'Servicios Smoke',
            'slug' => $runId.'-category',
            'icon' => 'store',
            'description' => 'Categoria temporal para smoke publico.',
            'is_active' => true,
            'is_featured' => true,
        ]));
        Category::withoutEvents(fn () => $category->save());
    }

    $tenant = new Tenant();
    $tenant->forceFill(onlyExisting('tenants', [
        'name' => 'Smoke Directorio Publico',
        'slug' => $runId,
        'description' => 'Negocio temporal para validar directorio y explore publico.',
        'user_id' => $user->id,
        'city_id' => $city?->id,
        'category_id' => $category?->id,
        'is_active' => true,
        'is_visible' => true,
        'status' => 'active',
        'plan_type' => 'pro',
        'subscription_status' => 'active',
        'whatsapp_number' => '99999999',
        'phone' => '22223333',
        'business_type' => 'retail',
        'brand_color' => '#2563EB',
        'primary_color' => '#2563EB',
        'logo_path' => 'smoke/logo-placeholder.png',
        'cover_path' => 'smoke/cover-placeholder.png',
        'banner_path' => 'smoke/banner-placeholder.png',
        'tags' => json_encode(['smoke', 'publico'], JSON_THROW_ON_ERROR),
    ]));
    Tenant::withoutEvents(fn () => $tenant->save());
    $user->forceFill(['tenant_id' => $tenant->id])->save();

    if (Schema::hasTable('products')) {
        $product = new Product();
        $product->forceFill(onlyExisting('products', [
            'tenant_id' => $tenant->id,
            'city_id' => $city?->id,
            'category_id' => $category?->id,
            'name' => 'Producto Smoke Directorio',
            'slug' => $runId.'-producto',
            'description' => 'Producto temporal para validar fixture publico.',
            'price' => 99.50,
            'is_active' => true,
            'is_visible' => true,
            'is_featured' => true,
            'stock_status' => 'in_stock',
        ]));
        Product::withoutEvents(fn () => $product->save());
    }

    echo json_encode([
        'tenantSlug' => $tenant->slug,
        'tenantName' => $tenant->name,
        'citySlug' => $city?->slug,
        'categorySlug' => $category?->slug,
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    if (Schema::hasTable('products')) {
        Product::withoutGlobalScopes()->where('slug', $runId.'-producto')->forceDelete();
    }

    Tenant::withoutGlobalScopes()->where('slug', $runId)->forceDelete();

    if (Schema::hasTable('categories')) {
        Category::where('slug', $runId.'-category')->delete();
    }

    if (Schema::hasTable('cities')) {
        City::where('slug', $runId.'-city')->delete();
    }

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

function parseJsonOutput(mode, output) {
    const text = output.trim()
    const start = text.indexOf("{")
    const end = text.lastIndexOf("}")

    if (start === -1 || end === -1 || end < start) {
        throw new Error(`${mode} helper did not return JSON: ${output.slice(0, 400)}`)
    }

    return JSON.parse(text.slice(start, end + 1))
}

function runHelper(mode) {
    const helper = spawnSync(phpBin, [helperPath, root, mode, runId], {
        cwd: root,
        env: smokeEnv,
        encoding: "utf8",
        windowsHide: true,
    })

    if (helper.status !== 0) {
        throw new Error(`${mode} helper failed:\nSTDOUT:\n${helper.stdout}\nSTDERR:\n${helper.stderr}`)
    }

    return parseJsonOutput(mode, helper.stdout)
}

async function waitForServer() {
    const deadline = Date.now() + 30_000
    let lastError = null

    while (Date.now() < deadline) {
        try {
            const response = await fetch(`${baseUrl}/up`)
            if (response.status < 500) return
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

function hasBrokenEncoding(bodyText) {
    return /Ã|Â|�/.test(bodyText)
}

async function hasHorizontalOverflow(page) {
    return page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2)
}

async function validateDirectory(page, fixture, label, pathName = "/directorio") {
    const response = await page.goto(`${baseUrl}${pathName}`, { waitUntil: "domcontentloaded", timeout: 45_000 })

    pushCheck(`${label}_directory_status_ok`, (response?.status() || 0) < 500, `status=${response?.status()} url=${page.url()}`)
    await page.getByTestId("directory-page").waitFor({ state: "visible", timeout: 15_000 })
    const bodyText = await page.locator("body").innerText().catch(() => "")

    pushCheck(`${label}_directory_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 160))
    pushCheck(`${label}_directory_no_broken_encoding`, !hasBrokenEncoding(bodyText), bodyText.slice(0, 240))
    pushCheck(`${label}_directory_page_visible`, await page.getByTestId("directory-page").isVisible().catch(() => false))
    pushCheck(`${label}_directory_search_visible`, await page.getByTestId("directory-search-control").isVisible().catch(() => false))
    pushCheck(`${label}_directory_guide_input_visible`, await page.locator('[data-testid="directory-guide-chat-input"]:visible').first().isVisible().catch(() => false))
    pushCheck(`${label}_directory_guide_live_region`, await page.locator('[data-testid="directory-guide-chat-answer"]:visible').first().evaluate((node) => node.getAttribute("aria-live") === "polite" && ["status", "alert"].includes(node.getAttribute("role") || "")).catch(() => false))
    pushCheck(`${label}_tenant_card_visible`, await page.getByTestId("tenant-card").first().isVisible().catch(() => false))
    pushCheck(`${label}_primary_tenant_cta_visible`, await page.getByTestId("primary-tenant-cta").first().isVisible().catch(() => false))
    pushCheck(`${label}_tenant_copy_present`, bodyText.includes(fixture.tenantName), fixture.tenantName)
    pushCheck(`${label}_directory_no_horizontal_overflow`, !(await hasHorizontalOverflow(page)), `scrollWidth=${await page.evaluate(() => document.documentElement.scrollWidth)} viewport=${await page.evaluate(() => window.innerWidth)}`)
}

async function validateDirectoryEmpty(page, label) {
    const response = await page.goto(`${baseUrl}/directorio?search=no-match-${runId}`, {
        waitUntil: "domcontentloaded",
        timeout: 45_000,
    })

    pushCheck(`${label}_empty_status_ok`, (response?.status() || 0) < 500, `status=${response?.status()}`)
    await page.getByTestId("directory-page").waitFor({ state: "visible", timeout: 15_000 })
    const bodyText = await page.locator("body").innerText().catch(() => "")

    pushCheck(`${label}_empty_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 160))
    pushCheck(`${label}_empty_no_broken_encoding`, !hasBrokenEncoding(bodyText), bodyText.slice(0, 240))
    pushCheck(`${label}_empty_state_visible`, await page.getByTestId("directory-empty-state").isVisible().catch(() => false))
    pushCheck(`${label}_empty_no_horizontal_overflow`, !(await hasHorizontalOverflow(page)), `scrollWidth=${await page.evaluate(() => document.documentElement.scrollWidth)} viewport=${await page.evaluate(() => window.innerWidth)}`)
}

async function validateExplore(page, fixture, label) {
    const response = await page.goto(`${baseUrl}/explore?search=${encodeURIComponent(fixture.tenantName)}`, {
        waitUntil: "domcontentloaded",
        timeout: 45_000,
    })

    pushCheck(`${label}_explore_status_ok`, (response?.status() || 0) < 500, `status=${response?.status()} url=${page.url()}`)
    pushCheck(`${label}_explore_redirects_to_directory`, new URL(page.url()).pathname === "/directorio", page.url())
    await page.getByTestId("directory-page").waitFor({ state: "visible", timeout: 15_000 })
    const bodyText = await page.locator("body").innerText().catch(() => "")

    pushCheck(`${label}_explore_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 160))
    pushCheck(`${label}_explore_no_broken_encoding`, !hasBrokenEncoding(bodyText), bodyText.slice(0, 240))
    pushCheck(`${label}_explore_page_visible`, await page.getByTestId("directory-page").isVisible().catch(() => false))
    pushCheck(`${label}_explore_search_visible`, await page.getByTestId("directory-search-control").isVisible().catch(() => false))
    pushCheck(`${label}_explore_tenant_card_visible`, await page.getByTestId("tenant-card").first().isVisible().catch(() => false))
    pushCheck(`${label}_explore_tenant_copy_present`, bodyText.includes(fixture.tenantName), fixture.tenantName)
    pushCheck(`${label}_explore_no_horizontal_overflow`, !(await hasHorizontalOverflow(page)), `scrollWidth=${await page.evaluate(() => document.documentElement.scrollWidth)} viewport=${await page.evaluate(() => window.innerWidth)}`)
}

let server
let browser
let fixture

try {
    fs.writeFileSync(sqlitePath, "", { flag: "a" })

    spawnSync(phpBin, ["artisan", "view:clear"], {
        cwd: root,
        env: smokeEnv,
        encoding: "utf8",
        windowsHide: true,
    })

    const migrate = spawnSync(phpBin, ["artisan", "migrate", "--force", "--no-interaction"], {
        cwd: root,
        env: smokeEnv,
        encoding: "utf8",
        windowsHide: true,
    })

    if (migrate.status !== 0) {
        throw new Error(`smoke sqlite migration failed:\nSTDOUT:\n${migrate.stdout}\nSTDERR:\n${migrate.stderr}`)
    }

    fixture = runHelper("setup")

    server = spawn(phpBin, ["artisan", "serve", "--host", host, "--port", String(port)], {
        cwd: root,
        env: smokeEnv,
        stdio: ["ignore", "pipe", "pipe"],
        windowsHide: true,
    })

    await waitForServer()

    browser = await chromium.launch({ headless: true })

    const desktopContext = await browser.newContext({ viewport: { width: 1366, height: 900 } })
    const desktopPage = await desktopContext.newPage()
    desktopPage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    desktopPage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await validateDirectory(desktopPage, fixture, "desktop", `/directorio?search=${encodeURIComponent(fixture.tenantName)}`)
    await screenshot(desktopPage, "directory-desktop")
    await validateDirectoryEmpty(desktopPage, "desktop")
    await screenshot(desktopPage, "directory-empty-desktop")
    await validateExplore(desktopPage, fixture, "desktop")
    await screenshot(desktopPage, "explore-desktop")
    await desktopContext.close().catch(() => {})

    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })
    const mobilePage = await mobileContext.newPage()
    mobilePage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    mobilePage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await validateDirectory(mobilePage, fixture, "mobile", `/directorio?search=${encodeURIComponent(fixture.tenantName)}`)
    await screenshot(mobilePage, "directory-mobile")
    await validateExplore(mobilePage, fixture, "mobile")
    await screenshot(mobilePage, "explore-mobile")
    await mobileContext.close().catch(() => {})

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
        if (process.platform === "win32" && server.pid) {
            spawnSync("taskkill", ["/PID", String(server.pid), "/T", "/F"], {
                cwd: root,
                encoding: "utf8",
                windowsHide: true,
            })
        } else {
            server.kill()
        }
    }

    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2), "utf8")
    fs.rmSync(helperPath, { force: true })
    fs.rmSync(configCachePath, { force: true })

    if (!process.env.UIUX_SMOKE_DB) {
        fs.rmSync(sqlitePath, { force: true })
    }
}

console.log(`uiux-twentieth-public-directory-smoke artifact: ${path.join(outDir, "result.json")}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
