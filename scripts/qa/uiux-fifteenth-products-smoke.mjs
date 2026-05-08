import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8018 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-products-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/products-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))

fs.mkdirSync(outDir, { recursive: true })

const helperPath = path.join(os.tmpdir(), `conecta-uiux-fifteenth-products-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

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

if ($mode === 'setup') {
    $email = $runId.'@example.test';
    $password = 'Password123!';

    $user = new User();
    $user->forceFill([
        'name' => 'Smoke Products Owner',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'seller',
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenantData = [
        'name' => 'Smoke Products Canonico',
        'slug' => $runId,
        'user_id' => $user->id,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => 'pro',
        'subscription_status' => 'active',
        'whatsapp_number' => '99999999',
        'business_type' => 'retail',
    ];

    if (Schema::hasColumn('tenants', 'description')) {
        $tenantData['description'] = 'Fixture temporal para smoke de productos.';
    }

    if (Schema::hasColumn('tenants', 'phone')) {
        $tenantData['phone'] = '22223333';
    }

    if (Schema::hasColumn('tenants', 'is_pro')) {
        $tenantData['is_pro'] = true;
    }

    $tenantData = array_filter(
        $tenantData,
        fn ($value, string $key): bool => Schema::hasColumn('tenants', $key),
        ARRAY_FILTER_USE_BOTH
    );

    $tenant = new Tenant();
    $tenant->forceFill($tenantData);
    Tenant::withoutEvents(fn () => $tenant->save());

    $user->forceFill(['tenant_id' => $tenant->id])->save();

    foreach ([
        ['name' => 'Cafe artesanal smoke', 'slug' => $runId.'-cafe', 'price' => 125.50, 'is_visible' => true],
        ['name' => 'Combo desayuno smoke', 'slug' => $runId.'-combo', 'price' => 210.00, 'is_visible' => false],
    ] as $item) {
        $productData = [
            'tenant_id' => $tenant->id,
            'name' => $item['name'],
            'slug' => $item['slug'],
            'description' => 'Producto temporal para smoke UI/UX.',
            'price' => $item['price'],
            'is_active' => true,
            'is_visible' => $item['is_visible'],
        ];

        if (Schema::hasColumn('products', 'status')) {
            $productData['status'] = 'active';
        }

        $productData = array_filter(
            $productData,
            fn ($value, string $key): bool => Schema::hasColumn('products', $key),
            ARRAY_FILTER_USE_BOTH
        );

        $product = new Product();
        $product->forceFill($productData);
        Product::withoutEvents(fn () => $product->save());
    }

    echo json_encode([
        'email' => $email,
        'password' => $password,
        'tenantId' => $tenant->id,
        'tenantSlug' => $tenant->slug,
        'panelPath' => '/app/'.$tenant->slug,
        'productsPath' => '/app/'.$tenant->slug.'/products',
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    $tenant = Tenant::withoutGlobalScopes()->where('slug', $runId)->first();
    if ($tenant) {
        DB::table('products')->where('tenant_id', $tenant->id)->delete();
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
        throw new Error(`${mode} helper did not return JSON: ${output.slice(0, 400)}\nSTDERR:\n${helper.stderr}`)
    }

    try {
        return JSON.parse(output.slice(jsonStart, jsonEnd + 1))
    } catch (error) {
        throw new Error(`${mode} helper returned invalid JSON: ${error.message}\nSTDOUT:\n${helper.stdout}\nSTDERR:\n${helper.stderr}`)
    }
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

async function login(page, fixture) {
    const loginResponse = await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded" })
    pushCheck("login_route_200", loginResponse?.status() === 200, `status=${loginResponse?.status()}`)

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

    pushCheck("login_fixture_fetch_ok", authStatus.status < 500, `status=${authStatus.status} url=${authStatus.url}`)
}

async function validateProducts(page, fixture, label) {
    const redirectProbe = await page.evaluate(async () => {
        const response = await fetch("/workspace/products", {
            redirect: "manual",
            credentials: "same-origin",
            headers: { Accept: "text/html, application/xhtml+xml" },
        })

        return {
            status: response.status,
            location: response.headers.get("Location") || response.headers.get("location") || "",
        }
    })
    const location = new URL(redirectProbe.location || fixture.productsPath, baseUrl)

    pushCheck(`${label}_workspace_products_redirect_status`, [0, 302, 303].includes(redirectProbe.status), `status=${redirectProbe.status}`)
    pushCheck(`${label}_workspace_products_redirect_ok`, location.pathname === fixture.productsPath, location.pathname)
}

async function assertHttpRoute(pathName, label) {
    const response = await fetch(`${baseUrl}${pathName}`, { redirect: "follow" })
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
            stdio: "ignore",
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
    await validateProducts(desktopPage, fixture, "desktop")

    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })
    const mobilePage = await mobileContext.newPage()
    mobilePage.on("pageerror", (error) => result.browserErrors.push(String(error?.message || error)))
    mobilePage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await login(mobilePage, fixture)
    await validateProducts(mobilePage, fixture, "mobile")

    await assertHttpRoute("/dashboard", "dashboard")
    await assertHttpRoute("/mi-cuenta", "profile")
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

    if (fixture || result.status === "fail") {
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
                timeout: 10_000,
                windowsHide: true,
            })
        } else {
            server.kill()
        }
    }

    fs.writeFileSync(path.join(outDir, "result.json"), JSON.stringify(result, null, 2), "utf8")
    fs.rmSync(helperPath, { force: true })
}

console.log(`uiux-fifteenth-products-smoke artifact: ${path.join(outDir, "result.json")}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
