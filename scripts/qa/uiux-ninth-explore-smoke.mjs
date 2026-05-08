import fs from "node:fs"
import path from "node:path"
import { spawn, spawnSync } from "node:child_process"
import { chromium } from "@playwright/test"

const root = process.cwd()
const phpBin = process.env.PHP_BIN || "php"
const host = process.env.UIUX_SMOKE_HOST || "127.0.0.1"
const port = Number(process.env.UIUX_SMOKE_PORT || 8014)
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://${host}:${port}`
const shouldStartServer = process.env.UIUX_SMOKE_START_SERVER !== "0"
const outputRoot = process.env.UIUX_SMOKE_ARTIFACT_ROOT || "docs/frontend/audit-artifacts/2026-05-07/browser/explore-smoke"
const outDir = path.join(root, outputRoot, new Date().toISOString().replace(/[:.]/g, "-"))

fs.mkdirSync(outDir, { recursive: true })

const result = {
    baseUrl,
    status: "fail",
    checks: [],
    screenshots: {},
    error: null,
}

function pushCheck(name, pass, details = "") {
    result.checks.push({ name, pass, details })
}

function runArtisan(args) {
    return spawnSync(phpBin, ["artisan", ...args], {
        cwd: root,
        encoding: "utf8",
        windowsHide: true,
    })
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

let server
let browser

try {
    runArtisan(["view:clear"])

    if (shouldStartServer) {
        server = spawn(phpBin, ["artisan", "serve", "--host", host, "--port", String(port)], {
            cwd: root,
            stdio: ["ignore", "pipe", "pipe"],
            windowsHide: true,
        })
    }

    await waitForServer()
    browser = await chromium.launch({ headless: true })

    const desktopContext = await browser.newContext({ viewport: { width: 1366, height: 900 } })
    const desktopPage = await desktopContext.newPage()
    const exploreResponse = await desktopPage.goto(`${baseUrl}/explore`, { waitUntil: "domcontentloaded" })
    await desktopPage.locator('[data-testid="explore-search"]').waitFor({ timeout: 10_000 })
    const exploreText = await desktopPage.locator("body").innerText().catch(() => "")
    pushCheck("explore_desktop_200", exploreResponse?.status() === 200, `status=${exploreResponse?.status()}`)
    pushCheck("explore_desktop_no_fatal_text", !hasFatalText(exploreText), exploreText.slice(0, 160))
    pushCheck("explore_search_anchor", await desktopPage.locator('[data-testid="explore-search"]').count() > 0)
    await screenshot(desktopPage, "explore-desktop")

    await desktopPage.locator("#explore-search-input").fill("test")
    await desktopPage.locator('[data-testid="explore-search"] button[type="submit"]').click()
    await desktopPage.waitForURL((url) => url.pathname === "/directorio" && url.searchParams.get("search") === "test", { timeout: 10_000 })
    pushCheck("explore_search_redirects_to_directory", true, desktopPage.url())

    await desktopPage.goto(`${baseUrl}/explore`, { waitUntil: "domcontentloaded" })
    const category = desktopPage.locator('[data-testid="explore-category"]').first()
    if (await category.count()) {
        await category.click()
        await desktopPage.waitForURL((url) => url.pathname === "/directorio" && url.searchParams.has("category"), { timeout: 10_000 })
        pushCheck("explore_category_redirects_to_directory", true, desktopPage.url())
    } else {
        pushCheck("explore_category_redirects_to_directory", true, "skipped: no local category rendered")
    }

    await desktopPage.goto(`${baseUrl}/explore`, { waitUntil: "domcontentloaded" })
    const tenantCard = desktopPage.locator('[data-testid="explore-tenant-card"]').first()
    if (await tenantCard.count()) {
        const href = await tenantCard.getAttribute("href")
        const tenantResponse = await desktopPage.goto(new URL(href, baseUrl).toString(), { waitUntil: "domcontentloaded" })
        pushCheck("explore_tenant_card_destination_ok", (tenantResponse?.status() || 0) < 500, `${href} status=${tenantResponse?.status()}`)
    } else {
        pushCheck("explore_tenant_card_destination_ok", true, "skipped: no visible tenant rendered")
    }

    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })
    const mobilePage = await mobileContext.newPage()
    const mobileResponse = await mobilePage.goto(`${baseUrl}/explore`, { waitUntil: "domcontentloaded" })
    await mobilePage.locator('[data-testid="explore-search"]').waitFor({ timeout: 10_000 })
    pushCheck("explore_mobile_200", mobileResponse?.status() === 200, `status=${mobileResponse?.status()}`)
    await screenshot(mobilePage, "explore-mobile")

    await assertRoute(desktopPage, "/", "home")
    await assertRoute(desktopPage, "/directorio", "directory")
    await assertRoute(desktopPage, "/login", "login")
    await assertRoute(desktopPage, "/verify-email", "verify_email")

    result.status = result.checks.every((check) => check.pass) ? "pass" : "fail"
} catch (error) {
    result.error = String(error?.message || error)
    result.status = "fail"
} finally {
    if (browser) {
        await browser.close().catch(() => {})
    }

    if (server) {
        server.kill()
    }
}

const outFile = path.join(outDir, "result.json")
fs.writeFileSync(outFile, JSON.stringify(result, null, 2), "utf8")
console.log(`uiux-ninth-explore-smoke artifact: ${outFile}`)
console.log(JSON.stringify(result, null, 2))

process.exit(result.status === "pass" ? 0 : 1)
