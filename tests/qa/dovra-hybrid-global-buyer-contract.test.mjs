import test from "node:test";
import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import { resolve } from "node:path";

const repositoryRoot = resolve(import.meta.dirname, "../..");

const contractFiles = Object.freeze([
    "tests/Support/HybridGlobalBuyerFixture.php",
    "tests/Feature/Infrastructure/DovraHybridGlobalBuyerContinuityTest.php",
    "tests/e2e/support/seed-hybrid-global-buyer.php",
    "tests/e2e/support/inspect-hybrid-global-buyer.php",
    "tests/e2e/dovra-hybrid-global-buyer.spec.ts",
]);

function source(relativePath) {
    const absolutePath = resolve(repositoryRoot, relativePath);
    assert.equal(existsSync(absolutePath), true, `${relativePath} is missing`);

    return readFileSync(absolutePath, "utf8");
}

test("Canada hybrid global buyer contract owns only the bounded test slice", () => {
    for (const relativePath of contractFiles) source(relativePath);

    const fixture = source("tests/Support/HybridGlobalBuyerFixture.php");
    assert.match(fixture, /GlobalMarketFixture::assignCanada/u);
    assert.match(fixture, /DOVRA_E2E_ALLOW_SYNTHETIC_HYBRID_GLOBAL_BUYER/u);
    assert.match(fixture, /business_type.*service/su);
    assert.match(fixture, /commercial_mode.*hybrid/su);
    assert.match(fixture, /rubro_key.*hybrid-core/su);
    assert.match(fixture, /CA/iu);
    assert.match(fixture, /en-CA/iu);
    assert.match(fixture, /CAD/iu);
    assert.match(fixture, /America\/Toronto/u);
    assert.match(fixture, /AvailabilityRuleWriter/u);
    assert.match(fixture, /updateOrInsert|firstOrCreate/u);

    const phpTest = source("tests/Feature/Infrastructure/DovraHybridGlobalBuyerContinuityTest.php");
    assert.match(phpTest, /DatabaseTransactions/u);
    assert.match(phpTest, /Idempotency|idempotency/u);
    assert.match(phpTest, /GlobalMarketFixture|HybridGlobalBuyerFixture/u);
    assert.match(phpTest, /order.created/u);
    assert.match(phpTest, /reservation.created/u);
    assert.match(phpTest, /billing|payment|credit/iu);

    const seed = source("tests/e2e/support/seed-hybrid-global-buyer.php");
    const inspector = source("tests/e2e/support/inspect-hybrid-global-buyer.php");
    for (const helper of [seed, inspector]) {
        assert.match(helper, /APP_KEY/u);
        assert.match(helper, /DB_CONNECTION.*sqlite/su);
        assert.match(helper, /DOVRA_E2E_DATABASE_PATH/u);
        assert.match(helper, /DOVRA_E2E_ALLOW_SYNTHETIC_HYBRID_GLOBAL_BUYER/u);
        assert.match(helper, /local.*testing|testing.*local/su);
        assert.match(helper, /:memory:|file-backed|is_file/u);
    }
    assert.match(inspector, /Schema::hasTable/u);
    assert.match(inspector, /tenant_id/u);
    assert.match(inspector, /foreign/iu);
    assert.match(inspector, /billing|payment|subscription|credit/iu);

    const playwright = source("tests/e2e/dovra-hybrid-global-buyer.spec.ts");
    assert.match(playwright, /browser\.newContext|newContext\(/u);
    assert.ok(playwright.includes('/directorio'));
    assert.ok(playwright.includes('/t/dovra-hybrid-global-buyer-e2e'));
    assert.ok(playwright.includes('/api/v1/orders'));
    assert.ok(playwright.includes('/book'));
    assert.match(playwright, /idempotency/iu);
    assert.match(playwright, /external|popup|serverErrors|consoleErrors/iu);
});

test("hybrid contract does not authorize production or established HN journey edits", () => {
    const owned = new Set([
        ...contractFiles,
        "tests/qa/dovra-hybrid-global-buyer-contract.test.mjs",
        "tests/qa/dovra-program14-node-command-contract.test.mjs",
    ]);
    const forbidden = [
        "app/",
        "routes/",
        "resources/js/",
        "database/migrations/",
        "config/",
        "tests/e2e/dovra-buyer-conversion.spec.ts",
        "tests/e2e/dovra-booking-conversion.spec.ts",
    ];

    for (const relativePath of owned) {
        assert.equal(
            forbidden.some((prefix) => relativePath.startsWith(prefix)),
            false,
            `${relativePath} is outside the test-only contract`,
        );
    }
});
