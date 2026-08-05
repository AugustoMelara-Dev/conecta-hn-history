import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import AxeBuilder from "@axe-core/playwright"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.BILLING_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.BILLING_SMOKE_PORT || 8500 + Math.floor(Math.random() * 250))
const baseUrl = "http://" + host + ":" + port
const runId = "billing-" + Date.now() + "-" + Math.random().toString(16).slice(2)
const sqlitePath = process.env.BILLING_SMOKE_DB || path.join(os.tmpdir(), runId + ".sqlite")
const configCachePath = path.join(os.tmpdir(), runId + "-config.php")
const outDir = path.join(
    root,
    process.env.BILLING_SMOKE_ARTIFACT_ROOT || "artifacts/billing-entitlements-smoke",
    new Date().toISOString().replace(/[:.]/g, "-"),
)
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
const result = { baseUrl, runId, status: "fail", checks: [], screenshots: {}, error: null }
let server
let browser

fs.mkdirSync(outDir, { recursive: true })

function check(name, pass, details = "") {
    result.checks.push({ name, pass, details })
}

function helper() {
    const helperPath = path.join(root, "scripts/qa/billing-entitlements-smoke-helper.php")
    const child = spawnSync(phpBin, [helperPath, runId], {
        cwd: root,
        env: smokeEnv,
        encoding: "utf8",
        windowsHide: true,
    })
    if (child.status !== 0) {
        throw new Error("fixture helper failed:\n" + (child.stderr || child.stdout))
    }
    const output = child.stdout.trim()
    return JSON.parse(output.slice(output.indexOf("{"), output.lastIndexOf("}") + 1))
}

async function waitForServer() {
    const deadline = Date.now() + 45000
    while (Date.now() < deadline) {
        try {
            const response = await fetch(baseUrl + "/up")
            if (response.status < 500) return
        } catch {}
        await new Promise((resolve) => setTimeout(resolve, 500))
    }
    throw new Error("Laravel server unavailable at " + baseUrl)
}

async function capture(page, name) {
    const target = path.join(outDir, name + ".png")
    await page.screenshot({ path: target, fullPage: true })
    result.screenshots[name] = target
}

async function login(page, fixture) {
    const loginResponse = await page.goto(baseUrl + "/login", { waitUntil: "domcontentloaded", timeout: 90000 })
    check("login_route_200", loginResponse?.status() === 200, "status=" + loginResponse?.status())
    const token = await page.locator('meta[name="csrf-token"]').getAttribute("content")
    const auth = await page.evaluate(async (input) => {
        const body = new URLSearchParams()
        body.set("_token", input.token || "")
        body.set("email", input.email)
        body.set("password", input.password)
        const response = await fetch("/login", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                "X-CSRF-TOKEN": input.token || "",
                Accept: "text/html, application/xhtml+xml",
            },
            credentials: "same-origin",
            redirect: "follow",
            body,
        })
        return { status: response.status, path: new URL(response.url).pathname }
    }, { email: fixture.email, password: fixture.password, token })
    check("owner_authenticated", auth.status < 500 && auth.path !== "/login", JSON.stringify(auth))
}

async function overflowReport(page) {
    return page.evaluate(() => {
        const root = document.querySelector('[data-testid="billing-plan-and-usage"]')
        if (!root) return { overflow: true, offenders: [{ tag: "MISSING" }] }
        const offenders = Array.from(root.querySelectorAll("*"))
            .filter((element) => element.scrollWidth > element.clientWidth + 1)
            .slice(0, 8)
            .map((element) => ({
                tag: element.tagName,
                className: String(element.className || ""),
                text: String(element.textContent || "").trim().slice(0, 80),
                scrollWidth: element.scrollWidth,
                clientWidth: element.clientWidth,
            }))
        return {
            overflow: root.getBoundingClientRect().width > document.documentElement.clientWidth + 1 || offenders.length > 0,
            offenders,
        }
    })
}

try {
    fs.writeFileSync(sqlitePath, "", { flag: "a" })
    if (process.env.BILLING_SMOKE_SKIP_MIGRATE !== "1") {
        const migrate = spawnSync(phpBin, ["artisan", "migrate", "--force", "--no-interaction"], {
            cwd: root,
            env: smokeEnv,
            encoding: "utf8",
            windowsHide: true,
        })
        if (migrate.status !== 0) {
            throw new Error("migration failed (status=" + migrate.status + "):\n" + migrate.stdout + "\n" + migrate.stderr)
        }
    }

    const router = path.join(root, "scripts/qa/onboarding-adaptive-smoke-server.php")
    server = spawn(phpBin, ["-d", "max_execution_time=0", "-d", "opcache.enable_cli=1", "-d", "realpath_cache_size=4096K", "-S", host + ":" + port, router], {
        cwd: path.join(root, "public"),
        env: smokeEnv,
        stdio: ["ignore", "pipe", "pipe"],
        windowsHide: true,
    })
    await waitForServer()
    const fixture = helper()

    browser = await chromium.launch({ headless: true })
    const context = await browser.newContext({ viewport: { width: 1366, height: 900 } })
    const page = await context.newPage()
    page.setDefaultTimeout(45000)
    await page.route("**/*", async (route) => {
        const url = new URL(route.request().url())
        if (url.host !== new URL(baseUrl).host) {
            await route.abort()
            return
        }
        if (["image", "font", "media"].includes(route.request().resourceType())) {
            await route.abort()
            return
        }
        await route.continue()
    })

    await login(page, fixture)
    const billingUrl = baseUrl + "/app/" + fixture.slug + "/manage-billing"
    const response = await page.goto(billingUrl, { waitUntil: "domcontentloaded", timeout: 300000 })
    check("billing_route_200", response?.status() === 200, "status=" + response?.status())

    const plan = page.getByTestId("billing-entitlements")
    const usage = page.getByTestId("billing-ai-usage")
    await plan.waitFor({ state: "visible" })
    await usage.waitFor({ state: "visible" })

    const pageText = await page.getByTestId("billing-plan-and-usage").innerText()
    const progress = usage.locator('[role="progressbar"]')
    check("pro_entitlements_visible", pageText.includes("Plan PRO") && pageText.includes("100") && pageText.includes("10.0 GB"), pageText.slice(0, 500))
    check("zero_commission_truth_visible", pageText.includes("0% de comision"), pageText.slice(0, 500))
    check("usage_is_91_percent", (await progress.getAttribute("aria-valuenow")) === "91", await progress.getAttribute("aria-valuenow"))
    check("threshold_warning_is_explicit", pageText.includes("90% consumido") && pageText.includes("cargos sorpresa"), pageText.slice(0, 700))
    check("provider_tokens_are_not_exposed", !pageText.toLowerCase().includes("token"), pageText.slice(0, 500))
    await capture(page, "01-billing-pro-desktop")

    const axe = await new AxeBuilder({ page })
        .include('[data-testid="billing-plan-and-usage"]')
        .withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"])
        .analyze()
    check("no_detected_wcag_a_aa_violations", axe.violations.length === 0, JSON.stringify(axe.violations))

    await page.setViewportSize({ width: 360, height: 800 })
    await page.waitForTimeout(500)
    const mobileOverflow = await overflowReport(page)
    check("billing_reflows_at_360px", !mobileOverflow.overflow, JSON.stringify(mobileOverflow.offenders))
    await capture(page, "02-billing-pro-360")

    await page.evaluate(() => {
        document.documentElement.style.fontSize = "200%"
    })
    await page.waitForTimeout(300)
    const zoomOverflow = await overflowReport(page)
    check("billing_supports_200_percent_text_zoom", !zoomOverflow.overflow, JSON.stringify(zoomOverflow.offenders))
    await capture(page, "03-billing-pro-360-text-200")

    result.status = result.checks.every((item) => item.pass) ? "pass" : "fail"
} catch (error) {
    result.error = String(error?.stack || error?.message || error)
} finally {
    if (browser) await browser.close().catch(() => {})
    if (server) {
        if (process.platform === "win32" && server.pid) {
            spawnSync("taskkill", ["/PID", String(server.pid), "/T", "/F"], { windowsHide: true })
        } else {
            server.kill()
        }
    }
    fs.rmSync(configCachePath, { force: true })
    if (!process.env.BILLING_SMOKE_DB) fs.rmSync(sqlitePath, { force: true })
}

const artifact = path.join(outDir, "result.json")
fs.writeFileSync(artifact, JSON.stringify(result, null, 2), "utf8")
console.log("billing-entitlements-smoke artifact: " + artifact)
console.log(JSON.stringify(result, null, 2))
process.exit(result.status === "pass" ? 0 : 1)