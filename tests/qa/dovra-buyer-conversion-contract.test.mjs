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

test("buyer conversion fixture is a guarded, idempotent local synthetic contract", () => {
    const source = readRepositoryFile("database/seeders/DovraBuyerConversionE2ESeeder.php")

    assert.match(source, /final class DovraBuyerConversionE2ESeeder\b/u)
    assert.match(source, /environment\(\[['"]local['"],\s*['"]testing['"]\]\)/u)
    assert.match(source, /DOVRA_E2E_ALLOW_SYNTHETIC_BUYER_CONVERSION/u)
    assert.match(source, /!==\s*['"]true['"]/u)
    assert.match(source, /dovra-buyer-conversion-e2e/u)
    assert.match(source, /updateOrInsert\(/u)
    assert.match(source, /is_active['"]\s*=>\s*true/u)
    assert.match(source, /publication_paused_at['"]\s*=>\s*null/u)
    assert.match(source, /is_pro['"]\s*=>\s*true/u)
    assert.match(source, /commercial_mode['"]\s*=>\s*['"]products['"]/u)
    assert.match(source, /phone['"]\s*=>\s*null/u)
    assert.match(source, /whatsapp(?:_number)?['"]\s*=>\s*null/u)
    assert.match(source, /dovra\.buyer\.conversion\.e2e@example\.test/u)
    assert.match(source, /Producto Buyer E2E/u)
    assert.match(source, /125\.50/u)
    assert.match(source, /stock_quantity['"]\s*=>\s*10/u)
    assert.match(source, /is_visible['"]\s*=>\s*true/u)
    assert.match(source, /status['"]\s*=>\s*['"]published['"]/u)
    assert.match(source, /HN/u)
    assert.match(source, /es-HN/u)
    assert.match(source, /HNL/u)
    assert.match(source, /America\/Tegucigalpa/u)
    assert.match(source, /pickup|recogida/iu)
    assert.match(source, /delivery/iu)
    assert.match(source, /billing|subscription_payments|payments/iu)
})

test("buyer conversion browser spec exercises the real read-only-to-order boundary", () => {
    const source = readRepositoryFile("tests/e2e/dovra-buyer-conversion.spec.ts")

    assert.doesNotMatch(source, /page\.setContent\s*\(/u)
    assert.doesNotMatch(source, /mountFixture|HARNESS_CONTRACT|applicationSurface\s*:\s*false/u)
    assert.match(source, /\/directorio/u)
    assert.match(source, /\/t\/|tenantSlug/u)
    assert.match(source, /Producto Buyer E2E/u)
    assert.match(source, /Buyer E2E/u)
    assert.match(source, /9999-2026/u)
    assert.match(source, /pickup|recogida/iu)
    assert.match(source, /\/api\/v1\/orders/u)
    assert.match(source, /POST/u)
    assert.match(source, /201/u)
    assert.match(source, /pending/u)
    assert.match(source, /Pedido\s+\{[^}]+\}\s+creado/u)
    assert.match(source, /pendiente/iu)
    assert.match(source, /popup|window\.open/u)
    assert.match(source, /5xx|status\(\)\s*>=\s*500/u)
    assert.match(source, /pageerror|consoleErrors/u)
    assert.match(source, /cart|carrito/iu)
    assert.match(source, /inspect-buyer-conversion\.php/u)
    assert.match(source, /execFile(?:Async)?/u)
    assert.match(source, /DOVRA_E2E_DATABASE_PATH/u)
    assert.match(source, /JSON\.parse/u)
    assert.match(source, /order_id/u)
    assert.match(source, /order_number/u)
})

test("buyer conversion helper is read-only and verifies canonical order evidence", () => {
    const source = readRepositoryFile("tests/e2e/support/inspect-buyer-conversion.php")

    assert.match(source, /DOVRA_E2E_ALLOW_SYNTHETIC_BUYER_CONVERSION/u)
    assert.match(source, /!==\s*['"]true['"]/u)
    assert.match(source, /dovra-buyer-conversion-e2e/u)
    for (const table of ["orders", "order_items", "inventory_movements", "outbox_messages"]) {
        assert.match(source, new RegExp(`['"]${table}['"]`, "u"), `${table} evidence is missing`)
    }
    assert.match(source, /order_consumed/u)
    assert.match(source, /BusinessOperationChanged/u)
    assert.match(source, /125\.50|251\.00/u)
    assert.match(source, /HNL/u)
    assert.match(source, /pending/u)
    assert.match(source, /pickup|recogida/iu)
    assert.match(source, /subscription_payments|payments|billing/iu)
    assert.doesNotMatch(source, /\b(?:insert|updateOrInsert|update|upsert|delete|forceFill|save)\s*\(/u, "buyer inspection helper must not mutate state")
    assert.doesNotMatch(source, /Route::|Http::|curl_|fetch\(/u, "buyer inspection helper must not call external services")
    assert.match(source, /JSON_THROW_ON_ERROR\s*\|\s*JSON_UNESCAPED_SLASHES/u)
})

test("floating cart keeps successful order feedback and closes after the Sonner toast", () => {
    const source = readRepositoryFile("resources/js/Components/Tenant/FloatingCart.jsx")

    assert.match(source, /toast\.success\(/u)
    assert.match(source, /Pedido\s+\$\{[^}]+\}\s+creado/u)
    assert.match(source, /setIsOpen\s*\(\s*false\s*\)/u)
    assert.match(source, /setStep\s*\(\s*STEPS\.CART\s*\)/u)
    assert.doesNotMatch(source, /billing|subscription_payments|payments/iu)
})
