import fs from "node:fs"
import os from "node:os"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8420 + Math.floor(Math.random() * 300))
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const outputRoot =
    process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/landing-public-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))
const configCachePath = path.join(os.tmpdir(), `uiux-landing-public-${Date.now()}-${Math.random().toString(16).slice(2)}-config.php`)
const smokeEnv = {
    ...process.env,
    APP_ENV: "testing",
    APP_URL: baseUrl,
    ASSET_URL: "",
    APP_CONFIG_CACHE: configCachePath,
}

fs.mkdirSync(outDir, { recursive: true })

const result = {
    baseUrl,
    status: "fail",
    checks: [],
    screenshots: {},
    browserErrors: [],
    error: null,
}

function pushCheck(name, pass, details = "") {
    result.checks.push({ name, pass, details })
}

function hasFatalText(bodyText) {
    return /server error|whoops|exception|stack trace|sqlstate|fatal error/i.test(bodyText)
}

function hasBrokenEncoding(bodyText) {
    return /Ã|Â|�/.test(bodyText)
}

async function hasHorizontalOverflow(page) {
    return page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1)
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
    try {
        await page.screenshot({ path: file, fullPage: true, timeout: 20_000 })
    } catch {
        await page.screenshot({ path: file, fullPage: false, timeout: 20_000 })
    }
    result.screenshots[name] = file
}

async function validateLanding(page, label) {
    const response = await page.goto(`${baseUrl}/`, { waitUntil: "domcontentloaded", timeout: 45_000 })
    await page.getByTestId("landing-page").waitFor({ state: "visible", timeout: 20_000 })
    const bodyText = await page.locator("body").innerText().catch(() => "")

    pushCheck(`${label}_home_status_ok`, (response?.status() || 0) < 500, `status=${response?.status()}`)
    pushCheck(`${label}_home_no_fatal_text`, !hasFatalText(bodyText), bodyText.slice(0, 180))
    pushCheck(`${label}_home_no_broken_encoding`, !hasBrokenEncoding(bodyText), bodyText.slice(0, 220))
    pushCheck(`${label}_home_no_horizontal_overflow`, !(await hasHorizontalOverflow(page)), await page.evaluate(() => `${document.documentElement.clientWidth}/${document.documentElement.scrollWidth}`))
    pushCheck(`${label}_landing_page_visible`, await page.getByTestId("landing-page").isVisible().catch(() => false))
    pushCheck(`${label}_product_positioning_visible`, /SaaS|sistema comercial|panel privado/i.test(bodyText), bodyText.slice(0, 260))
    pushCheck(`${label}_search_demoted_from_landing`, await page.getByTestId("landing-search").count().catch(() => 0) === 0)
    pushCheck(`${label}_primary_cta_visible`, await page.getByTestId("landing-primary-cta").isVisible().catch(() => false))
    pushCheck(`${label}_directory_cta_demoted_visible`, await page.getByTestId("landing-directory-cta").isVisible().catch(() => false))
    pushCheck(`${label}_pricing_visible`, await page.getByTestId("landing-pricing").isVisible().catch(() => false))
    pushCheck(`${label}_faq_visible`, await page.getByTestId("landing-faq").isVisible().catch(() => false))
    pushCheck(`${label}_directory_is_not_primary`, !/Busca como cliente|Negocios listos para recibir visitas|Exploracion util para clientes finales/i.test(bodyText), bodyText.slice(0, 260))
    const categoryCount = await page.getByTestId("landing-category-card").count().catch(() => 0)
    pushCheck(`${label}_category_cards_removed`, categoryCount === 0, `cards=${categoryCount}`)
    const tenantCount = await page.getByTestId("landing-tenant-card").count().catch(() => 0)
    pushCheck(`${label}_tenant_cards_removed`, tenantCount === 0, `cards=${tenantCount}`)

    const primaryHref = await page.getByTestId("landing-primary-cta").first().getAttribute("href").catch(() => "")
    const directoryHref = await page.getByTestId("landing-directory-cta").first().getAttribute("href").catch(() => "")
    pushCheck(`${label}_primary_cta_points_to_registration`, /register|registrar-negocio|app\//.test(primaryHref || ""), primaryHref || "")
    pushCheck(`${label}_secondary_directory_cta_points_to_directory`, /directorio|directory/.test(directoryHref || ""), directoryHref || "")

    await screenshot(page, `landing-public-${label}`)
}

let server = null
let browser = null

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

    server = null
    await new Promise((resolve) => setTimeout(resolve, 500))
}

try {
    if (shouldStartServer) {
        server = spawn(phpBin, ["artisan", "serve", "--host", host, "--port", String(port)], {
            cwd: root,
            env: smokeEnv,
            stdio: ["ignore", "pipe", "pipe"],
            windowsHide: true,
        })

        server.stdout.on("data", (chunk) => {
            result.browserErrors.push(`server: ${chunk.toString().slice(0, 300)}`)
        })
        server.stderr.on("data", (chunk) => {
            result.browserErrors.push(`server: ${chunk.toString().slice(0, 300)}`)
        })

        await waitForServer()
    }

    browser = await chromium.launch()
    const desktop = await browser.newPage({ viewport: { width: 1440, height: 1100 } })
    desktop.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text().slice(0, 300)}`)
        }
    })
    await validateLanding(desktop, "desktop")

    const mobile = await browser.newPage({ viewport: { width: 390, height: 900 }, isMobile: true })
    mobile.on("console", (message) => {
        if (["error", "warning"].includes(message.type())) {
            result.browserErrors.push(`${message.type()}: ${message.text().slice(0, 300)}`)
        }
    })
    await validateLanding(mobile, "mobile")

    const failed = result.checks.filter((check) => !check.pass)
    result.status = failed.length ? "fail" : "pass"
} catch (error) {
    result.error = error?.stack || error?.message || String(error)
    result.status = "fail"
} finally {
    if (browser) {
        await browser.close().catch(() => {})
    }
    if (server) {
        await stopSmokeServer()
    }
    fs.rmSync(configCachePath, { force: true })

    const resultPath = path.join(outDir, "result.json")
    fs.writeFileSync(resultPath, JSON.stringify(result, null, 2))
    console.log(`uiux-landing-public-smoke artifact: ${resultPath}`)

    process.exit(result.status === "pass" ? 0 : 1)
}
