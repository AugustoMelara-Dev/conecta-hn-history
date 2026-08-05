import test from "node:test"
import assert from "node:assert/strict"
import { existsSync, readFileSync } from "node:fs"
import { resolve } from "node:path"

const repositoryRoot = resolve(import.meta.dirname, "../..")
const helperPath = resolve(repositoryRoot, "tests/e2e/support/create-registration-verification-link.php")

test("registration verification helper is a read-only, synthetic, fail-closed contract", () => {
    assert.equal(existsSync(helperPath), true, "registration verification helper is missing")
    const source = readFileSync(helperPath, "utf8")

    assert.match(source, /DOVRA_REGISTRATION_EMAIL\s*=\s*['"]dovra\.registration\.e2e@example\.test['"]/u)
    assert.match(source, /DOVRA_REGISTRATION_SLUG\s*=\s*['"]dovra-registration-e2e['"]/u)
    assert.match(source, /dovra\.appointments\.e2e@example\.test/u)
    assert.match(source, /dovra-appointments-e2e/u)
    assert.match(source, /dovra\.hybrid\.e2e@example\.test/u)
    assert.match(source, /dovra-hybrid-e2e/u)
    assert.match(source, /DOVRA_E2E_REGISTRATION_SCENARIO/u, "registration scenario must be explicit")
    for (const scenario of ["products", "appointments", "hybrid"]) {
        assert.ok(
            source.includes(`'${scenario}'`) || source.includes(`"${scenario}"`),
            `${scenario} scenario is missing from the closed allowlist`,
        )
    }
    assert.match(source, /array_key_exists\(\$scenario\s*,\s*\$scenarios\)/u, "unknown registration scenarios must fail closed")
    assert.match(source, /\$argc\s*!==\s*1/u, "identity must not be accepted through CLI arguments")
    assert.match(source, /getenv\(['"]APP_KEY['"]\)/u, "signing key must be explicit in the helper process")
    assert.match(source, /providedAppKey/u, "signing key must be captured before Laravel loads .env")
    assert.match(source, /environment\(\[['"]local['"],\s*['"]testing['"]\]\)/u)
    assert.match(source, /DOVRA_E2E_ALLOW_SYNTHETIC_EMAIL_VERIFICATION/u)
    assert.match(source, /!==\s*['"]true['"]/u)

    assert.match(source, /config\(['"]database\.default['"]\)[\s\S]*?['"]sqlite['"]/u)
    assert.match(source, /DOVRA_E2E_DATABASE_PATH/u)
    assert.match(source, /['"]:memory:['"]/u)
    assert.match(source, /realpath\(\$configuredDatabase\)/u)
    assert.match(source, /realpath\(\$expectedDatabase\)/u)
    assert.match(source, /filesize\(\$configuredDatabase\)/u)
    assert.match(source, /filesize\(\$expectedDatabase\)/u)

    assert.match(source, /->where\(['"]email['"]/u)
    assert.match(source, /->where\(['"]slug['"]/u)
    assert.match(source, /->where\(['"]tenant_id['"]/u)
    assert.match(source, /->where\(['"]is_primary['"]/u)
    assert.match(source, /DB::table\(['"]locations['"]\)/u, "the helper must verify the primary location")
    assert.match(source, /->where\(['"]commercial_mode['"]|\$tenant->commercial_mode/u, "commercial mode invariant is missing")
    assert.match(source, /business_type/u, "business type invariant is missing")
    assert.match(source, /rubro_key/u, "rubro invariant is missing")
    assert.match(source, /plan_type/u, "semilla plan invariant is missing")
    assert.match(source, /is_pro/u, "free entitlement invariant is missing")
    assert.match(source, /DB::table\(['"]payments['"]\)[\s\S]*?count\(\)/u, "legacy payments must remain empty")
    assert.match(source, /DB::table\(['"]subscription_payments['"]\)[\s\S]*?count\(\)/u, "subscription payments must remain empty")
    assert.match(source, /lockForUpdate\(\)/gu)
    assert.match(source, /->count\(\)\s*!==\s*1/u)
    assert.match(source, /email_verified_at\s*!==\s*null/u)
    assert.match(source, /getPanel\(['"]app['"]\)/u)
    assert.match(source, /getVerifyEmailUrl\(\$user\)/u)
    assert.match(source, /['"]verification_url['"]\s*=>\s*\$verificationUrl/u)

    assert.doesNotMatch(source, /\b(?:update|updateOrInsert|insert|upsert|delete|forceFill|save|markEmailAsVerified)\s*\(/u, "helper must not mutate identity or tenant state")
    assert.doesNotMatch(source, /Route::|Http::|curl_|fetch\(/u, "helper must not add an app route or call an external service")
    assert.match(source, /JSON_THROW_ON_ERROR\s*\|\s*JSON_UNESCAPED_SLASHES/u)
})
