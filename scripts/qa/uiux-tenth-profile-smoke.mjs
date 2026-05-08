import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8015)
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const runId = `uiux-profile-${Date.now()}-${Math.random().toString(16).slice(2)}`
const outputRoot = process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/profile-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))

fs.mkdirSync(outDir, { recursive: true })

const helperPath = path.join(os.tmpdir(), `conecta-uiux-tenth-profile-helper-${process.pid}.php`)
fs.writeFileSync(
    helperPath,
    `<?php
declare(strict_types=1);

use App\\Models\\User;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;

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
        'name' => 'Smoke Perfil',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
    ]);
    User::withoutEvents(fn () => $user->save());

    echo json_encode([
        'email' => $email,
        'password' => $password,
        'userId' => $user->id,
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'cleanup') {
    DB::table('sessions')->where('user_id', DB::raw("'".$runId."'"))->delete();
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
        windowsHide: true,
    })

    if (helper.status !== 0) {
        throw new Error(`${mode} helper failed: ${helper.stderr || helper.stdout}`)
    }

    try {
        return JSON.parse(helper.stdout)
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

function hasFatalText(bodyText) {
    return /server error|whoops|exception|stack trace|sqlstate|fatal error/i.test(bodyText)
}

async function assertRoute(page, pathName, label) {
    const response = await page.goto(`${baseUrl}${pathName}`, { waitUntil: "domcontentloaded" })
    const text = await page.locator("body").innerText().catch(() => "")
    pushCheck(`${label}_status_ok`, (response?.status() || 0) < 500, `status=${response?.status()}`)
    pushCheck(`${label}_no_fatal_text`, !hasFatalText(text), text.slice(0, 160))
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

let server
let browser
let fixture

try {
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
    desktopPage.on("pageerror", (error) => {
        result.browserErrors.push(String(error?.message || error))
    })
    desktopPage.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text()}`)
        }
    })

    await login(desktopPage, fixture)

    const profileResponse = await desktopPage.goto(`${baseUrl}/mi-cuenta`, { waitUntil: "domcontentloaded" })
    const profileText = await desktopPage.locator("body").innerText().catch(() => "")
    pushCheck("profile_canonical_200", profileResponse?.status() === 200, `status=${profileResponse?.status()}`)
    pushCheck("profile_canonical_url", desktopPage.url().includes("/mi-cuenta"), desktopPage.url())
    pushCheck("profile_no_fatal_text", !hasFatalText(profileText), profileText.slice(0, 160))
    await screenshot(desktopPage, "profile-after-goto")
    await desktopPage.getByTestId("profile-page").waitFor({ state: "visible", timeout: 10_000 })
    pushCheck("profile_info_form_visible", await desktopPage.getByTestId("profile-info-form").isVisible().catch(() => false))
    pushCheck("profile_password_form_visible", await desktopPage.getByTestId("profile-password-form").isVisible().catch(() => false))
    await screenshot(desktopPage, "profile-desktop")

    await desktopPage.getByTestId("profile-password-save").click()
    await desktopPage.locator("#current-password-error").waitFor({ state: "visible", timeout: 10_000 })
    pushCheck("profile_password_required_error_visible", true)

    await desktopPage.getByRole("button", { name: /eliminar cuenta/i }).click()
    await desktopPage.getByTestId("profile-delete-dialog").waitFor({ state: "visible", timeout: 10_000 })
    pushCheck("profile_delete_dialog_visible", true)
    await screenshot(desktopPage, "profile-delete-dialog")
    await desktopPage.getByRole("button", { name: /eliminar definitivamente/i }).click()
    await desktopPage.locator("#delete-account-password-error").waitFor({ state: "visible", timeout: 10_000 })
    pushCheck("profile_delete_required_error_visible", true)
    await desktopPage.getByRole("button", { name: /cancelar/i }).click()

    const compatResponse = await desktopPage.goto(`${baseUrl}/profile`, { waitUntil: "domcontentloaded" })
    await desktopPage.getByTestId("profile-page").waitFor({ state: "visible", timeout: 10_000 })
    pushCheck("profile_compat_200", compatResponse?.status() === 200, `status=${compatResponse?.status()}`)

    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })
    const mobilePage = await mobileContext.newPage()
    await login(mobilePage, fixture)
    const mobileResponse = await mobilePage.goto(`${baseUrl}/mi-cuenta`, { waitUntil: "domcontentloaded" })
    await mobilePage.getByTestId("profile-page").waitFor({ state: "visible", timeout: 10_000 })
    pushCheck("profile_mobile_200", mobileResponse?.status() === 200, `status=${mobileResponse?.status()}`)
    await screenshot(mobilePage, "profile-mobile")

    await assertRoute(desktopPage, "/", "home")
    await assertRoute(desktopPage, "/explore", "explore")
    await assertRoute(desktopPage, "/directorio", "directory")
    await assertRoute(desktopPage, "/login", "login_regression")
    await assertRoute(desktopPage, "/verify-email", "verify_email")

    result.status = result.checks.every((check) => check.pass) ? "pass" : "fail"
} catch (error) {
    result.error = String(error?.message || error)
    result.status = "fail"
} finally {
    if (browser) {
        await browser.close().catch(() => {})
    }

    if (fixture) {
        runHelper("cleanup")
    }

    if (server) {
        server.kill()
    }

    fs.rmSync(helperPath, { force: true })
}

const outFile = path.join(outDir, "result.json")
fs.writeFileSync(outFile, JSON.stringify(result, null, 2), "utf8")
console.log(`uiux-tenth-profile-smoke artifact: ${outFile}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
