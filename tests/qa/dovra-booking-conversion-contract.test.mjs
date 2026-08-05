import test from "node:test"
import assert from "node:assert/strict"
import { existsSync, readFileSync } from "node:fs"
import { resolve } from "node:path"

const repositoryRoot = resolve(import.meta.dirname, "../..")

function readRepositoryFile(relativePath) {
    const path = resolve(repositoryRoot, relativePath)
    assert.equal(existsSync(path), true, `${relativePath} is missing`)
    return readFileSync(path, "utf8")
}

test("booking conversion fixture is guarded, canonical, idempotent, and billing-free", () => {
    const source = readRepositoryFile("database/seeders/DovraBookingConversionE2ESeeder.php")
    const seederMutationSurface = source
        .replace(/\/\*[\s\S]*?\*\//gu, "")
        .replace(/\/\/[^\r\n]*/gu, "")
    const tenantAttributableCommercialTables = [
        "orders",
        "payments",
        "subscription_payments",
        "subscription_intents",
        "tenant_billing_references",
        "global_billing_entitlements",
        "global_billing_credit_accounts",
        "global_billing_credit_reservations",
        "global_billing_credit_ledger_entries",
        "global_billing_credit_alerts",
        "global_billing_credit_reconciliations",
        "global_billing_lifecycle_events",
        "ai_credit_reservations",
        "ai_credit_ledger_entries",
        "ai_credit_usage_alerts",
        "ai_credit_grants",
    ]
    const forbiddenCommercialTableMutation = new RegExp(
        `DB::table\\(\\s*["'](?:${tenantAttributableCommercialTables.join("|")})["']\\s*\\)\\s*->\\s*(?:insert|updateOrInsert|upsert|update|delete)\\s*\\(`,
        "iu",
    )

    assert.match(source, /final class DovraBookingConversionE2ESeeder\b/u)
    assert.match(source, /environment\(\[['"]local['"],\s*['"]testing['"]\]\)/u)
    assert.match(source, /DOVRA_E2E_ALLOW_SYNTHETIC_BOOKING_CONVERSION/u)
    assert.match(source, /!==\s*['"]true['"]/u)
    assert.match(source, /dovra-booking-conversion-e2e/u)
    assert.match(source, /updateOrInsert\(/u)
    assert.match(source, /is_active['"]\s*=>\s*true/u)
    assert.match(source, /publication_paused_at['"]\s*=>\s*null/u)
    assert.match(source, /is_pro['"]\s*=>\s*true/u)
    assert.match(source, /commercial_mode['"]\s*=>\s*['"]appointments['"]/u)
    assert.match(source, /business_type['"]\s*=>\s*['"]service['"]/u)
    assert.match(source, /dovra\.booking\.conversion\.e2e@example\.test/u)
    assert.match(source, /HN/u)
    assert.match(source, /es-HN/u)
    assert.match(source, /HNL/u)
    assert.match(source, /America\/Tegucigalpa/u)
    assert.match(source, /AvailabilityRuleWriter/u)
    assert.match(source, /ReplaceAvailabilityRulesCommand/u)
    assert.match(source, /service_locations/u)
    assert.match(source, /service_provider_locations/u)
    assert.match(source, /publicly_bookable|bookable|is_active/u)
    assert.match(source, /billing|subscription_payments|payments/iu)
    assert.doesNotMatch(
        seederMutationSurface,
        forbiddenCommercialTableMutation,
        "the booking fixture must not mutate tenant-attributable billing, credit, payment, or order tables",
    )
    assert.doesNotMatch(
        seederMutationSurface,
        /\b(?:Order|Payment|SubscriptionPayment|Billing)[A-Za-z]*\s*::\s*(?:create|insert|update|upsert|delete|save)\s*\(/u,
        "the booking fixture must not use commercial model writes",
    )
})

test("microsite booking CTA is bounded to appointment and hybrid tenants", () => {
    const source = readRepositoryFile("resources/js/Pages/Tenant/Show.jsx")

    assert.match(source, /microsite-booking-cta/u)
    assert.match(source, /conversion_profile/u)
    assert.match(source, /public\.booking\.index/u)
    assert.match(source, /tenant:\s*tenant\.slug/u)
    assert.match(source, /<a\s*[\s\S]*href=\{safeRoute\(/u)
    assert.doesNotMatch(source, /<Link\s+href=\{safeRoute\(\s*['"]public\.booking\.index/u)
    assert.match(source, /appointments/u)
    assert.match(source, /hybrid/u)
    assert.match(source, /Consultar disponibilidad|conversion_profile\?\.cta|conversion_profile\.cta/u)
    assert.match(source, /Reserva|reservar|agenda/iu)
})

test("public booking responder delegates the canonical availability and reservation seams", () => {
    const source = readRepositoryFile("app/Infrastructure/Booking/HttpPublicBookingResponder.php")

    assert.match(source, /HttpPublicBookingResponder/u)
    assert.match(source, /AvailabilityQuery/u)
    assert.match(source, /CanonicalReservationWriter/u)
    assert.match(source, /->slots\(/u)
    assert.match(source, /->create\(/u)
    assert.match(source, /source:\s*['"]public_booking['"]/u)
    assert.match(source, /publiclyBookableOnly:\s*true/u)
    assert.match(source, /starts_at_utc/u)
    assert.match(source, /status/u)
    assert.match(source, /timezone/u)
    assert.match(source, /setTimezone\(/u)
    assert.match(source, /['"]status['"]\s*=>\s*\(string\)\s*\$appointment->status/u)
    assert.match(source, /getRawOriginal\(['"]scheduled_at['"]\)/u)
    assert.match(source, /['"]UTC['"]/u)
    assert.match(source, /->setTimezone\(\$timezone\)/u)
    assert.doesNotMatch(source, /(?:orders?|payments?|subscription_payments|billing)[\s\S]{0,160}(?:insert|update|upsert|save|create)\s*\(/iu)
    assert.doesNotMatch(source, /new\s+Appointment\s*\(/u, "the responder must not own reservation persistence")
})

test("success page communicates pending appointment truth and preserves canonical links", () => {
    const source = readRepositoryFile("resources/js/Pages/Public/Book/Success.jsx")

    assert.match(source, /Solicitud de reserva recibida/u)
    assert.match(source, /Pendiente de confirmaci[óo]n/u)
    assert.doesNotMatch(source, /Reserva confirmada/u)
    assert.match(source, /booking-success-home/u)
    assert.match(source, /booking-success-new/u)
    assert.match(source, /booking-success-status/u)
    assert.match(source, /booking-success-date/u)
    assert.match(source, /booking-success-local-date/u)
    assert.match(source, /booking-success-time/u)
    assert.match(source, /booking-success-timezone/u)
    assert.match(source, /appointment\.local_date/u)
    assert.match(source, /appointment\.timezone/u)
    assert.match(source, /href=["']\/["']/u)
    assert.match(source, /href=["']\/book["']/u)
})

test("routed browser journey and inspector are real, single-submit, and read-only", () => {
    const browser = readRepositoryFile("tests/e2e/dovra-booking-conversion.spec.ts")
    const inspector = readRepositoryFile("tests/e2e/support/inspect-booking-conversion.php")

    assert.doesNotMatch(browser, /page\.setContent\s*\(/u)
    assert.match(browser, /\/directorio/u)
    assert.match(browser, /microsite-booking-cta/u)
    assert.match(browser, /\.localhost|APP_DOMAIN|tenantSlug/u)
    assert.match(browser, /\/book/u)
    assert.match(browser, /booking-service-option/u)
    assert.match(browser, /booking-provider-option/u)
    assert.match(browser, /booking-date-input/u)
    assert.match(browser, /booking-slot-option/u)
    assert.match(browser, /booking-customer-name/u)
    assert.match(browser, /booking-customer-email/u)
    assert.match(browser, /booking-customer-phone/u)
    assert.match(browser, /booking-submit/u)
    assert.match(browser, /POST/u)
    assert.match(browser, /reservation|appointment/iu)
    assert.match(browser, /5xx|status\(\)\s*>=\s*500/u)
    assert.match(browser, /pageerror|consoleErrors/u)
    assert.match(browser, /popup|window\.open/u)
    assert.match(browser, /inspect-booking-conversion\.php/u)
    assert.match(browser, /JSON\.parse/u)
    assert.match(browser, /passiveExternalAssetRules/u)
    assert.match(browser, /isAllowedPassiveExternalAsset/u)
    assert.match(browser, /resourceType\(\)/u)
    assert.match(browser, /images\.unsplash\.com/u)
    assert.match(browser, /fonts\.bunny\.net/u)
    assert.match(browser, /new Set\(\[['"]stylesheet['"],\s*['"]font['"]\]\)/u)
    assert.match(browser, /forbiddenSameOriginOperationalPath/u)
    assert.match(browser, /sameOriginOperationalRequests/u)
    assert.match(browser, /Intl\.DateTimeFormat/u)
    assert.match(browser, /America\/Tegucigalpa/u)
    assert.match(browser, /booking-success-local-date/u)
    assert.match(browser, /inspection\.appointment\.local_date/u)
    assert.match(browser, /inspection\.appointment\.local_time/u)

    assert.match(inspector, /DOVRA_E2E_ALLOW_SYNTHETIC_BOOKING_CONVERSION/u)
    assert.match(inspector, /DOVRA_E2E_DATABASE_PATH/u)
    assert.match(inspector, /dovra-booking-conversion-e2e/u)
    for (const table of ["appointments", "services", "service_providers", "locations", "outbox_messages"]) {
        assert.match(inspector, new RegExp(`["']${table}["']`, "u"), `${table} evidence is missing`)
    }
    for (const table of [
        "orders",
        "payments",
        "subscription_payments",
        "subscription_intents",
        "tenant_billing_references",
        "global_billing_entitlements",
        "global_billing_credit_accounts",
        "global_billing_credit_reservations",
        "global_billing_credit_ledger_entries",
        "global_billing_credit_alerts",
        "global_billing_credit_reconciliations",
        "global_billing_lifecycle_events",
        "ai_credit_reservations",
        "ai_credit_ledger_entries",
        "ai_credit_usage_alerts",
        "ai_credit_grants",
    ]) {
        assert.match(inspector, new RegExp(`["']${table}["']`, "u"), `${table} negative evidence is missing`)
    }
    assert.match(inspector, /reservation\.created/u)
    assert.match(inspector, /pending/u)
    assert.match(inspector, /public_booking/u)
    assert.match(inspector, /America\/Tegucigalpa/u)
    assert.match(inspector, /HNL/u)
    assert.match(inspector, /billing|subscription_payments|payments/iu)
    assert.match(inspector, /reservation|appointment.*id/iu)
    assert.match(inspector, /zeroEvidenceTables/u)
    assert.match(inspector, /Schema::hasTable\(\$table\)/u)
    assert.match(inspector, /Schema::hasColumn\(\$table,\s*['"]tenant_id['"]\)/u)
    assert.match(inspector, /canonical tenant-attributable negative table is missing/u)
    assert.match(inspector, /canonical tenant-attributable negative table lacks tenant_id/u)
    assert.match(inspector, /is_string\(\$persistedLocalDateRaw\)/u)
    assert.match(inspector, /createFromFormat\(/u)
    assert.match(inspector, /!Y-m-d H:i:s/u)
    assert.match(inspector, /format\(['"]Y-m-d['"]\)/u)
    assert.match(inspector, /catch \(\\Throwable\) \{\s*continue;/u)
    assert.match(inspector, /local_date snapshot is not a strict canonical date/u)
    assert.doesNotMatch(inspector, /Schema::hasTable\(\$table\)[\s\S]{0,120}continue/u)
    assert.doesNotMatch(inspector, /\b(?:insert|updateOrInsert|update|upsert|delete|forceFill|save)\s*\(/u, "booking inspection helper must not mutate state")
    assert.doesNotMatch(inspector, /Route::|Http::|curl_|fetch\(/u, "booking inspection helper must not call external services")
    assert.match(inspector, /JSON_THROW_ON_ERROR\s*\|\s*JSON_UNESCAPED_SLASHES/u)
})
