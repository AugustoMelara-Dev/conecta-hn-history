import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import AxeBuilder from "@axe-core/playwright"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.ONBOARDING_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.ONBOARDING_SMOKE_PORT || 8200 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || "http://" + host + ":" + port
const runId = "onboarding-" + Date.now() + "-" + Math.random().toString(16).slice(2)
const sqlitePath = process.env.ONBOARDING_SMOKE_DB || path.join(os.tmpdir(), runId + ".sqlite")
const configCachePath = path.join(os.tmpdir(), runId + "-config.php")
const outDir = path.join(
    root,
    process.env.ONBOARDING_SMOKE_ARTIFACT_ROOT || "artifacts/onboarding-adaptive-smoke",
    new Date().toISOString().replace(/[:.]/g, "-"),
)
const helperPath = path.join(root, "scripts/qa/onboarding-adaptive-smoke-helper.php")
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

const result = {
    baseUrl,
    runId,
    status: "fail",
    checks: [],
    screenshots: {},
    error: null,
}

function check(name, pass, details = "") {
    result.checks.push({ name, pass, details })
}

function runHelper(mode) {
    const processResult = spawnSync(phpBin, [helperPath, mode, runId], {
        cwd: root,
        env: smokeEnv,
        encoding: "utf8",
        windowsHide: true,
    })
    if (processResult.status !== 0) {
        throw new Error(mode + " helper failed:\n" + (processResult.stderr || processResult.stdout))
    }

    const output = processResult.stdout.trim()
    const start = output.indexOf("{")
    const end = output.lastIndexOf("}")
    if (start === -1 || end < start) {
        throw new Error(mode + " helper returned invalid JSON: " + output)
    }

    return JSON.parse(output.slice(start, end + 1))
}

async function waitForServer() {
    const deadline = Date.now() + 45000
    let lastError = "no response"

    while (Date.now() < deadline) {
        try {
            const response = await fetch(baseUrl + "/up")
            if (response.status < 500) return
            lastError = "status=" + response.status
        } catch (error) {
            lastError = String(error?.message || error)
        }
        await new Promise((resolve) => setTimeout(resolve, 500))
    }

    throw new Error("Laravel server unavailable at " + baseUrl + ": " + lastError)
}

async function screenshot(page, name) {
    const file = path.join(outDir, name + ".png")
    await page.screenshot({ path: file, fullPage: true })
    result.screenshots[name] = file
}

let server
let browser

try {
    fs.writeFileSync(sqlitePath, "", { flag: "a" })
    if (process.env.ONBOARDING_SMOKE_SKIP_MIGRATE !== "1") {
        const migrate = spawnSync(phpBin, ["artisan", "migrate", "--force", "--no-interaction"], {
            cwd: root,
            env: smokeEnv,
            encoding: "utf8",
            windowsHide: true,
        })
        if (migrate.status !== 0) {
            throw new Error("smoke migration failed:\n" + migrate.stdout + "\n" + migrate.stderr)
        }
    }

    const serverScript = path.join(root, "scripts/qa/onboarding-adaptive-smoke-server.php")
    server = spawn(phpBin, ["-d", "max_execution_time=0", "-S", host + ":" + port, serverScript], {
        cwd: path.join(root, "public"),
        env: smokeEnv,
        stdio: ["ignore", "pipe", "pipe"],
        windowsHide: true,
    })

    await waitForServer()
    const fixture = runHelper("setup")

    browser = await chromium.launch({ headless: true })
    const context = await browser.newContext({ viewport: { width: 1366, height: 900 } })
    const page = await context.newPage()
    page.setDefaultTimeout(45000)

    await page.route("**/*", async (route) => {
        const url = new URL(route.request().url())
        const type = route.request().resourceType()
        if (url.host !== new URL(baseUrl).host) {
            await route.abort()
            return
        }
        if (["image", "font", "media"].includes(type)) {
            await route.abort()
            return
        }
        if (url.pathname.startsWith("/js/") && url.pathname !== "/livewire/livewire.min.js") {
            await route.abort()
            return
        }
        await route.continue()
    })

    const loginResponse = await page.goto(baseUrl + "/login", { waitUntil: "commit", timeout: 90000 })
    check("login_route_200", loginResponse?.status() === 200, "status=" + loginResponse?.status())
    const loginHtml = await loginResponse.text()
    const csrfMatch = loginHtml.match(/<meta\s+name=["']csrf-token["']\s+content=["']([^"']+)["']/i)
    const csrfToken = csrfMatch?.[1] || ""
    fs.writeFileSync(path.join(outDir, "login-response.html"), loginHtml, "utf8")
    check("login_exposes_csrf_token", csrfToken !== "", "url=" + page.url())
    const auth = await page.evaluate(
        async ({ email, password, token }) => {
            const body = new URLSearchParams()
            body.set("email", email)
            body.set("password", password)
            body.set("_token", token || "")
            const response = await fetch("/login", {
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
            return { status: response.status, path: new URL(response.url).pathname }
        },
        { email: fixture.email, password: fixture.password, token: csrfToken },
    )
    check("owner_authenticated", auth.status < 500 && auth.path !== "/login", JSON.stringify(auth))

    const dashboardUrl = baseUrl + "/app/" + fixture.slug
    const initialResponse = await page.goto(dashboardUrl, { waitUntil: "domcontentloaded", timeout: 90000 })
    check("dashboard_initial_200", initialResponse?.status() === 200, "status=" + initialResponse?.status())

    const wizard = page.getByTestId("first-run-wizard")
    await wizard.waitFor({ state: "visible" })
    const initialProgress = Number(await wizard.locator('[role="progressbar"]').getAttribute("aria-valuenow"))
    const initialCurrentTitle = await wizard.locator('[aria-current="step"] .biz-checklist-title').innerText()
    const cta = page.getByTestId("first-run-open-step")
    const href = await cta.getAttribute("href")
    check("initial_wizard_and_real_cta", Boolean(href && href !== "#"), "progress=" + initialProgress + " href=" + href)
    check("persistent_readiness_initially_visible", await page.getByTestId("launch-readiness-card").isVisible())
    await screenshot(page, "01-initial-dashboard")

    const nextResponse = await page.goto(new URL(href, dashboardUrl).toString(), {
        waitUntil: "domcontentloaded",
        timeout: 90000,
    })
    check("recommended_step_opens", Boolean(nextResponse && nextResponse.status() < 500), "status=" + nextResponse?.status())

    await page.goto(dashboardUrl, { waitUntil: "domcontentloaded", timeout: 90000 })
    runHelper("advance")
    await page.reload({ waitUntil: "domcontentloaded", timeout: 90000 })

    const progressedWizard = page.getByTestId("first-run-wizard")
    await progressedWizard.waitFor({ state: "visible" })
    const progressed = Number(await progressedWizard.locator('[role="progressbar"]').getAttribute("aria-valuenow"))
    const progressedCurrentTitle = await progressedWizard.locator('[aria-current="step"] .biz-checklist-title').innerText()
    check("authoritative_progress_increases_after_return", progressed > initialProgress, initialProgress + "->" + progressed)
    check("current_step_advances_after_real_completion", progressedCurrentTitle !== initialCurrentTitle, initialCurrentTitle + " -> " + progressedCurrentTitle)
    await screenshot(page, "02-progressed-dashboard")

    await page.waitForFunction(() => Boolean(window.Livewire), null, { timeout: 45000 })
    await page.getByTestId("first-run-dismiss").click()
    await wizard.waitFor({ state: "hidden", timeout: 45000 })
    check("guidance_dismisses_via_livewire", true)
    check("readiness_persists_after_dismiss", await page.getByTestId("launch-readiness-card").isVisible())
    const stored = runHelper("inspect")
    check("dismissal_persists_server_side", stored.dismissed === true, JSON.stringify(stored))

    const axe = await new AxeBuilder({ page })
        .include('[data-testid="launch-readiness-card"]')
        .withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"])
        .analyze()
    check("no_detected_wcag_a_aa_violations", axe.violations.length === 0, JSON.stringify(axe.violations))

    await page.setViewportSize({ width: 360, height: 800 })
    await page.waitForTimeout(750)
    const overflowAt360 = await page.evaluate(() => {
        const card = document.querySelector('[data-testid="launch-readiness-card"]')
        const offenders = card
            ? Array.from(card.querySelectorAll("*"))
                .filter((element) => element.scrollWidth > element.clientWidth + 1)
                .slice(0, 5)
                .map((element) => ({
                    tag: element.tagName,
                    className: String(element.className || ""),
                    text: String(element.textContent || "").trim().slice(0, 80),
                    scrollWidth: element.scrollWidth,
                    clientWidth: element.clientWidth,
                }))
            : [{ tag: "MISSING_CARD" }]
        const cardTooWide = card ? card.getBoundingClientRect().width > document.documentElement.clientWidth + 1 : true
        return { overflow: offenders.length > 0 || cardTooWide, offenders, cardTooWide }
    })
    check("readiness_reflows_at_360px", !overflowAt360.overflow, JSON.stringify(overflowAt360.offenders))

    const overflowAtTextZoom = await page.evaluate(() => {
        document.documentElement.style.fontSize = "200%"
        const card = document.querySelector('[data-testid="launch-readiness-card"]')
        const offenders = card
            ? Array.from(card.querySelectorAll("*"))
                .filter((element) => element.scrollWidth > element.clientWidth + 1)
                .slice(0, 5)
                .map((element) => ({
                    tag: element.tagName,
                    className: String(element.className || ""),
                    text: String(element.textContent || "").trim().slice(0, 80),
                }))
            : [{ tag: "MISSING_CARD" }]
        const cardTooWide = card ? card.getBoundingClientRect().width > document.documentElement.clientWidth + 1 : true
        return { overflow: offenders.length > 0 || cardTooWide, offenders, cardTooWide }
    })
    check("readiness_supports_200_percent_text_zoom", !overflowAtTextZoom.overflow, JSON.stringify(overflowAtTextZoom.offenders))
    await screenshot(page, "03-dismissed-readiness-360")

    result.status = result.checks.every((item) => item.pass) ? "pass" : "fail"
} catch (error) {
    result.error = String(error?.stack || error?.message || error)
    result.status = "fail"
} finally {
    if (browser) await browser.close().catch(() => {})
    if (server) server.kill()
    fs.rmSync(configCachePath, { force: true })
    if (!process.env.ONBOARDING_SMOKE_DB) fs.rmSync(sqlitePath, { force: true })
}

const outFile = path.join(outDir, "result.json")
fs.writeFileSync(outFile, JSON.stringify(result, null, 2), "utf8")
console.log("onboarding-adaptive-smoke artifact: " + outFile)
console.log(JSON.stringify(result, null, 2))
process.exit(result.status === "pass" ? 0 : 1)