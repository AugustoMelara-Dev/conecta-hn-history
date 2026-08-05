import fs from "node:fs";
import path from "node:path";
import { chromium } from "@playwright/test";

const baseUrl = process.env.PLAYWRIGHT_BASE_URL || "http://127.0.0.1:8011";
const ownerEmail = process.env.SMOKE_OWNER_EMAIL || process.env.BUSINESS_OWNER_EMAIL || "owner-demo-ai-guide-hn@conectahn.test";
const ownerPassword = process.env.SMOKE_OWNER_PASSWORD || process.env.BUSINESS_OWNER_PASSWORD || "ChangeMe123!";
const tenantSlug = process.env.SMOKE_PRODUCT_TENANT_SLUG || process.env.E2E_PRODUCT_TENANT_SLUG || "demo-ai-guide-hn";
const outputRoot = process.env.ONBOARDING_FIRST_RUN_ARTIFACT_ROOT || "artifacts/onboarding-first-run-browser-check";
const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
const outDir = path.join(outputRoot, timestamp);
fs.mkdirSync(outDir, { recursive: true });

const result = {
  baseUrl,
  tenantSlug,
  status: "fail",
  checks: [],
  error: null,
  remediation: [],
};

const pushCheck = (name, pass, details = "") => {
  result.checks.push({ name, pass, details });
};

let browser;
try {
  browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  const loginResponse = await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded" });
  pushCheck("login_route_non_5xx", !!loginResponse && loginResponse.status() < 500, `status=${loginResponse?.status() ?? "none"}`);

  await page.locator("#email").fill(ownerEmail);
  await page.locator("#password").fill(ownerPassword);
  await page.getByRole("button", { name: /login|iniciar/i }).click();
  await page.waitForLoadState("domcontentloaded");

  const dashboardResponse = await page.goto(`${baseUrl}/app/${tenantSlug}`, { waitUntil: "domcontentloaded" });
  pushCheck("dashboard_route_non_5xx", !!dashboardResponse && dashboardResponse.status() < 500, `status=${dashboardResponse?.status() ?? "none"}`);

  const bodyText = await page.locator("body").innerText();
  pushCheck("no_fatal_server_text", !/500|server error|internal server error/i.test(bodyText));

  const firstRunWizard = page.getByTestId("first-run-wizard");
  const wizardVisible = await firstRunWizard.isVisible().catch(() => false);
  pushCheck("first_run_wizard_visible", wizardVisible);

  const activeStepVisible = await firstRunWizard.locator('[aria-current="step"]').first().isVisible().catch(() => false);
  pushCheck("first_run_active_step_visible", activeStepVisible);

  const openStepVisible = await page.getByTestId("first-run-open-step").isVisible().catch(() => false);
  const disabledOpenStepVisible = await page.getByTestId("first-run-open-step-disabled").isVisible().catch(() => false);
  pushCheck("first_run_cta_state_visible", openStepVisible || disabledOpenStepVisible, `open=${openStepVisible} disabled=${disabledOpenStepVisible}`);
  pushCheck(
    "launch_readiness_card_visible",
    await page.getByTestId("launch-readiness-card").isVisible().catch(() => false),
  );

  await page.screenshot({ path: path.join(outDir, "onboarding-first-run.png"), fullPage: true });

  result.status = result.checks.every((check) => check.pass) ? "pass" : "fail";
} catch (error) {
  result.error = String(error?.message || error);
  result.status = "blocked_env";
  if (result.error.includes("spawn EPERM")) {
    result.remediation = [
      "Run terminal as Administrator and retry.",
      "Temporarily disable or exclude Playwright browser directory from antivirus scanning.",
      "Ensure this workspace and Node process can execute child processes (ACL/AppLocker policy).",
      "Retry in CI/host runner where Chromium launch is permitted.",
    ];
  }
} finally {
  if (browser) {
    await browser.close().catch(() => {});
  }
}

const outFile = path.join(outDir, "result.json");
fs.writeFileSync(outFile, JSON.stringify(result, null, 2), "utf8");
console.log(`onboarding-first-run-browser-check artifact: ${outFile}`);
console.log(JSON.stringify(result, null, 2));

if (result.status !== "pass") {
  process.exit(1);
}
