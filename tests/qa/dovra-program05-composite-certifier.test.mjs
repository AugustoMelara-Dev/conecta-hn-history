import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { join, resolve } from "node:path";
import { tmpdir } from "node:os";
import {
    classifyResult,
    parseArgs,
    validateCompositeFacts,
} from "../../scripts/qa/certify-dovra-program05-composite.mjs";

const repositoryRoot = resolve(import.meta.dirname, "../..");
const certifierPath = resolve(repositoryRoot, "scripts/qa/certify-dovra-program05-composite.mjs");
const fixturePath = resolve(repositoryRoot, "tests/Support/AI/Program05CompositeCertificateFixture.php");

const acceptedFacts = Object.freeze({
    schema: 1,
    status: "LOCALLY_VERIFIED",
    decision: "NO_CONTRACT",
    scope: "disposable_sqlite",
    sequence: ["expand", "backfill", "validate", "shadow", "canary", "rollback", "contract"],
    expand: { phase: "expand", validated: true, has_more: false, gaps: 0, conflicts: 0, contract_status: "expand_ready" },
    backfill: {
        batch_count: 4,
        resumed: true,
        idempotent: true,
        cumulative_gaps: 0,
        cumulative_conflicts: 0,
        cumulative_legacy_unresolved: 0,
        source_snapshots_identical: true,
        no_duplicate_execution_processing: true,
        canonical_lineage_count: 7,
        retention_classified_count: 7,
    },
    validate: { batch_count: 4, resumed: true, validated: true, read_only: true, cumulative_gaps: 0, cumulative_conflicts: 0, cumulative_legacy_unresolved: 0 },
    shadow: { read_only: true, selection_matches_legacy_and_canonical: true },
    canary: { rollout_percentage: 25, canary_selects_candidate: true, control_selects_root: true, sticky_paths_idempotent: true, open_candidate_activation_count: 1 },
    rollback: { new_conversations_select_root: true, control_pin_resolves_root: true, candidate_pin_fails_closed: true, rollback_rollout_percentage: 100 },
    contract: { validated: false, contract_status: "deferred_until_consumers_proven", legacy_preserved: true },
    safety: { provider_calls: 0, handoffs: 0, usage_rows: 0, billing_rows: 0, payment_rows: 0, credit_rows: 0, foreign_tenant_mutations: 0 },
    external: ["clean_exact_artifact", "independent_security_review", "live_ai_and_channel_providers", "mysql_portability", "postgresql_restricted_role_rls", "production_operations", "staging_runtime"],
});

function cloneFacts() {
    return JSON.parse(JSON.stringify(acceptedFacts));
}

function assertInvalid(mutate) {
    const facts = cloneFacts();
    mutate(facts);
    assert.throws(() => validateCompositeFacts(facts), /COMPOSITE_FACT_INVALID/u);
}

function runCli(args) {
    return spawnSync(process.execPath, [certifierPath, ...args], {
        cwd: repositoryRoot,
        encoding: "utf8",
        shell: false,
    });
}

function assertNoWorkspaceCacheCommands(source) {
    const childCommands = [...source.matchAll(/spawnSync\([^;]+?\);/gs)]
        .map((match) => match[0]);
    for (const command of childCommands) {
        assert.doesNotMatch(command, /(?:config|optimize)\s*:\s*(?:clear|cache)/iu);
    }
    // Check command-shaped invocations, not a bare token blacklist: a future
    // mutation that routes a cache-clearing command through a helper must fail
    // even when the literal appears in an argument array or shell string.
    assert.doesNotMatch(source, /(?:vendor\/bin\/pest|php\s+artisan|artisan)\b[^\n;]*\b(?:config|optimize)\s*:\s*(?:clear|cache)\b/iu);
}

function assertA1SourceContract(source) {
    const environmentMatch = source.match(/const childEnvironment\s*=\s*\{([\s\S]*?)\n\s*\};/u);
    assert.ok(environmentMatch, "child environment must be an explicit object");
    const environment = environmentMatch[1];
    const cacheAssignment = environment.match(/APP_CONFIG_CACHE\s*:\s*([^,\n]+)/u);
    assert.ok(cacheAssignment, "APP_CONFIG_CACHE must be explicitly assigned");
    assert.equal(cacheAssignment[1].trim(), "cachePath", "APP_CONFIG_CACHE must be owned by the unique temp path");
    assert.match(environment, /CACHE_STORE\s*:\s*["']array["']/u);
    assert.match(environment, /SESSION_DRIVER\s*:\s*["']array["']/u);
    assert.match(source, /assertInside\(tempDirectory,\s*cachePath,\s*["']APP_CONFIG_CACHE["']\)/u);

    const cacheChecks = [...source.matchAll(/existsSync\(cachePath\)/gu)];
    assert.ok(cacheChecks.length >= 4, "cache must be checked before/after child and immediately before cleanup");
    const childStart = source.indexOf("const result = spawnSync(phpPath");
    assert.ok(childStart >= 0, "dedicated child spawn must exist");
    const beforeChild = source.slice(0, childStart);
    const afterChild = source.slice(childStart);
    assert.ok(beforeChild.includes("if (existsSync(cachePath))"), "cache absence must be checked before child");
    assert.ok(afterChild.includes("if (existsSync(cachePath))"), "cache absence must be checked after child");
    assert.match(source, /finally\s*\{[\s\S]*?const cacheAbsentBeforeCleanup\s*=\s*existsSync\(cachePath\)\s*===\s*false[\s\S]*?rmSync\(tempDirectory/u);
    assertNoWorkspaceCacheCommands(source);
}

function assertProviderBindingContract(source) {
    const guardIndex = source.indexOf("self::assertGuard();");
    const providerIndex = source.indexOf("new FakeAiProvider(");
    const fixtureIndex = source.indexOf("AiEligibleRegistryFixture::create(");
    assert.ok(guardIndex >= 0 && providerIndex > guardIndex && fixtureIndex > providerIndex, "provider must bind before registry fixture creation");
    assert.equal([...source.matchAll(/new FakeAiProvider\(/gu)].length, 1, "exactly one fake provider instance is allowed");
    assert.equal([...source.matchAll(/app\(\)->instance\(AiProviderPool::class/gu)].length, 1, "exactly one provider-pool binding is allowed");
    assert.match(source, /new ConfiguredAiProviderPool\(\[[\s\S]*?fake_structured_text[\s\S]*?fake\.primary[\s\S]*?\$provider/u);
}

function assertFixtureDatabaseGuardContract(source) {
    const guardStart = source.indexOf("private static function assertGuard");
    assert.ok(guardStart >= 0, "fixture guard must exist");
    const guard = source.slice(guardStart);
    assert.match(guard, /getenv\(['"]DOVRA_PROGRAM05_COMPOSITE_DATABASE['"]\)/u);
    assert.match(guard, /getenv\(['"]DB_DATABASE['"]\)/u);
    assert.match(guard, /config\(['"]database\.connections\.sqlite\.database['"]\)/u);
    assert.match(guard, /\$environmentDatabase\s*!==\s*\$database/u);
    assert.match(guard, /\$configuredDatabase\s*!==\s*\$database/u);
    assert.match(guard, /assertNoDatabaseAlias\(\$database\)/u);
    assert.match(guard, /is_link\(/u);
    assert.match(guard, /realpath\(/u);
}

function assertEffectiveDatabaseGuardRejects({ environmentDatabase, configuredDatabase, compositeDatabase }) {
    if (environmentDatabase !== compositeDatabase || configuredDatabase !== compositeDatabase) {
        throw new Error("effective database mismatch");
    }
}

test("accepted schema-1 facts validate exactly", () => {
    assert.equal(validateCompositeFacts(cloneFacts()), true);
});

test("every sequence omission and reordering fails", () => {
    for (const index of acceptedFacts.sequence.keys()) {
        assertInvalid((facts) => facts.sequence.splice(index, 1));
        assertInvalid((facts) => {
            const next = (index + 1) % facts.sequence.length;
            [facts.sequence[index], facts.sequence[next]] = [facts.sequence[next], facts.sequence[index]];
        });
    }
});

test("false expand/backfill/validate/shadow/canary/rollback/contract facts fail independently", () => {
    const mutations = [
        (facts) => { facts.expand.validated = false; },
        (facts) => { facts.expand.has_more = true; },
        (facts) => { facts.expand.contract_status = "no_contract_requested"; },
        (facts) => { facts.backfill.resumed = false; },
        (facts) => { facts.backfill.idempotent = false; },
        (facts) => { facts.backfill.source_snapshots_identical = false; },
        (facts) => { facts.validate.validated = false; },
        (facts) => { facts.validate.read_only = false; },
        (facts) => { facts.shadow.read_only = false; },
        (facts) => { facts.shadow.selection_matches_legacy_and_canonical = false; },
        (facts) => { facts.canary.canary_selects_candidate = false; },
        (facts) => { facts.canary.control_selects_root = false; },
        (facts) => { facts.canary.sticky_paths_idempotent = false; },
        (facts) => { facts.rollback.new_conversations_select_root = false; },
        (facts) => { facts.rollback.control_pin_resolves_root = false; },
        (facts) => { facts.rollback.candidate_pin_fails_closed = false; },
        (facts) => { facts.contract.validated = true; },
        (facts) => { facts.contract.contract_status = "contract_ready"; },
        (facts) => { facts.contract.legacy_preserved = false; },
    ];
    for (const mutate of mutations) assertInvalid(mutate);
});

test("each nonzero safety count fails independently", () => {
    for (const key of Object.keys(acceptedFacts.safety)) {
        assertInvalid((facts) => { facts.safety[key] = 1; });
    }
});

test("runtime UUID/path/secret/prompt/timestamp fields are rejected", () => {
    for (const mutate of [
        (facts) => { facts.shadow.runtime_uuid = "00000000-0000-4000-8000-000000000000"; },
        (facts) => { facts.shadow.database_path = "C:\\temp\\scenario.sqlite"; },
        (facts) => { facts.safety.secret_reference = "fake.primary"; },
        (facts) => { facts.contract.raw_prompt = "Use authorized evidence only."; },
        (facts) => { facts.backfill.timestamp = "2026-08-03T00:00:00Z"; },
        (facts) => { facts.external.push("future_external_class"); },
    ]) assertInvalid(mutate);
});

test("missing, extra, duplicate, and unsorted external classes fail", () => {
    assertInvalid((facts) => { facts.external.shift(); });
    assertInvalid((facts) => { facts.external.push("extra_class"); });
    assertInvalid((facts) => { facts.external.push(facts.external[0]); });
    assertInvalid((facts) => { [facts.external[0], facts.external[1]] = [facts.external[1], facts.external[0]]; });
});

test("normal classification is local NO_CONTRACT/NO_GO with exit 0", () => {
    assert.deepEqual(classifyResult({ facts: cloneFacts(), strict: false }), {
        status: "LOCALLY_VERIFIED",
        decision: "NO_CONTRACT",
        launch: "NO_GO",
        exit_code: 0,
    });
});

test("strict classification is contract_deferred with exit 1", () => {
    assert.deepEqual(classifyResult({ facts: cloneFacts(), strict: true }), {
        status: "LOCALLY_VERIFIED",
        decision: "NO_CONTRACT",
        launch: "NO_GO",
        code: "contract_deferred",
        exit_code: 1,
    });
});

test("CLI rejects relative/missing PHP, PHP below 8.4, existing report, non-absolute report and unknown arguments", (t) => {
    const root = mkdtempSync(join(tmpdir(), "dovra-program05-cli-"));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    const newReport = join(root, "new-report.json");
    const relative = runCli(["--php", process.execPath, "--report", "relative-report.json", "--json"]);
    assert.notEqual(relative.status, 0);
    assert.match(relative.stdout + relative.stderr, /REPORT_INVALID|absolute/u);
    const missingPhp = runCli(["--php", join(root, "missing-php"), "--report", newReport, "--json"]);
    assert.notEqual(missingPhp.status, 0);
    assert.match(missingPhp.stdout + missingPhp.stderr, /PHP_INVALID/u);
    const belowPhp = runCli(["--php", process.execPath, "--report", join(root, "below-report.json"), "--json"]);
    assert.notEqual(belowPhp.status, 0);
    assert.match(belowPhp.stdout + belowPhp.stderr, /PHP_INVALID/u);
    writeFileSync(newReport, "existing\n");
    const existing = runCli(["--php", process.execPath, "--report", newReport, "--json"]);
    assert.notEqual(existing.status, 0);
    assert.match(existing.stdout + existing.stderr, /REPORT_EXISTS/u);
    const unknown = runCli(["--unknown", "--json"]);
    assert.notEqual(unknown.status, 0);
    assert.match(unknown.stdout + unknown.stderr, /Unknown argument/u);
    assert.throws(() => parseArgs(["--unknown"]), /Unknown argument/u);
});

test("source contract pins the dedicated Pest path, cache isolation, and disposable database guard", () => {
    const source = readFileSync(certifierPath, "utf8");
    assert.match(source, /tests\/Feature\/AI\/Program05CompositeCertificationTest\.php/u);
    assert.match(source, /APP_CONFIG_CACHE/u);
    assert.match(source, /CACHE_STORE/u);
    assert.match(source, /SESSION_DRIVER/u);
    assert.match(source, /DOVRA_PROGRAM05_COMPOSITE_DATABASE/u);
    assert.match(source, /DOVRA_PROGRAM05_COMPOSITE_ALLOW/u);
    assert.match(source, /REPORT_DATABASE_ALIAS/u);
    assert.doesNotMatch(source, /\b(?:docker|composer|npm|curl|wget|fetch|axios)\b/iu);
    assert.doesNotMatch(source, /(?:production|staging)[_-]?database/iu);
});

test("A1 source mutations cannot omit or replace the owned cache boundary", () => {
    const source = readFileSync(certifierPath, "utf8");
    assertA1SourceContract(source);

    const omitted = source.replace(/\s*APP_CONFIG_CACHE:\s*cachePath,\r?\n/u, "\n");
    assert.throws(() => assertA1SourceContract(omitted));
    const workspaceCache = source.replace(/APP_CONFIG_CACHE:\s*cachePath/u, 'APP_CONFIG_CACHE: join(repositoryRoot, "bootstrap/cache/config.php")');
    assert.throws(() => assertA1SourceContract(workspaceCache));
    const missingFinalCheck = source.replace(/\s*const cacheAbsentBeforeCleanup = existsSync\(cachePath\) === false;[\s\S]*?if \(!cacheAbsentBeforeCleanup\) throw new Error\("APP_CONFIG_CACHE_CREATED: cache path must remain absent before disposable cleanup\."\);/u, "");
    assert.throws(() => assertA1SourceContract(missingFinalCheck));
    assert.throws(() => assertA1SourceContract(`${source}\nspawnSync(phpPath, ["config:clear"]);`));
});

test("A1/A2/C3 source contracts reject provider-order and effective-database guard mutations", () => {
    const fixture = readFileSync(fixturePath, "utf8");
    assertProviderBindingContract(fixture);
    assertFixtureDatabaseGuardContract(fixture);

    const providerStart = fixture.indexOf("$provider = new FakeAiProvider(");
    const providerEnd = fixture.indexOf("\n\n        $tenant", providerStart);
    const providerBlock = fixture.slice(providerStart, providerEnd);
    const providerMoved = `${fixture.slice(0, providerStart)}${fixture.slice(providerEnd)}${providerBlock}`;
    assert.throws(() => assertProviderBindingContract(providerMoved));
    assert.throws(() => assertProviderBindingContract(fixture.replace(/new FakeAiProvider\(/u, "new OtherProvider(")));

    const withoutEnvironmentDb = fixture.replace(/\s*\$environmentDatabase = \(string\) getenv\('DB_DATABASE'\);/u, "");
    assert.throws(() => assertFixtureDatabaseGuardContract(withoutEnvironmentDb));
    const staleConfig = fixture.replace(/config\('database\.connections\.sqlite\.database'\)/u, "config('database.default')");
    assert.throws(() => assertFixtureDatabaseGuardContract(staleConfig));
    const withoutAliasCheck = fixture.replace(/\s*self::assertNoDatabaseAlias\(\$database\);/u, "");
    assert.throws(() => assertFixtureDatabaseGuardContract(withoutAliasCheck));

    assert.doesNotThrow(() => assertEffectiveDatabaseGuardRejects({
        environmentDatabase: "C:\\tmp\\scenario.sqlite",
        configuredDatabase: "C:\\tmp\\scenario.sqlite",
        compositeDatabase: "C:\\tmp\\scenario.sqlite",
    }));
    for (const mismatch of [
        { environmentDatabase: "C:\\tmp\\stale.sqlite", configuredDatabase: "C:\\tmp\\scenario.sqlite", compositeDatabase: "C:\\tmp\\scenario.sqlite" },
        { environmentDatabase: "C:\\tmp\\scenario.sqlite", configuredDatabase: "C:\\tmp\\stale.sqlite", compositeDatabase: "C:\\tmp\\scenario.sqlite" },
        { environmentDatabase: "C:\\tmp\\scenario.sqlite", configuredDatabase: "C:\\tmp\\scenario.sqlite", compositeDatabase: "C:\\tmp\\stale.sqlite" },
    ]) assert.throws(() => assertEffectiveDatabaseGuardRejects(mismatch));
});
