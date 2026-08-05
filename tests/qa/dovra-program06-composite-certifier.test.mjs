import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { join, resolve } from "node:path";
import { tmpdir } from "node:os";
import {
    classifyResult,
    implementationInventory,
    parseArgs,
    repositoryWriteAllowlist,
    validateCompositeFacts,
} from "../../scripts/qa/certify-dovra-program06-composite.mjs";

const repositoryRoot = resolve(import.meta.dirname, "../..");
const certifierPath = resolve(repositoryRoot, "scripts/qa/certify-dovra-program06-composite.mjs");

const acceptedFacts = Object.freeze({
    schema: 1,
    program: "06",
    status: "LOCALLY_VERIFIED",
    decision: "BOUNDED_LOCAL_ONLY",
    launch: "NO_GO",
    scope: "disposable_sqlite",
    sequence: ["guard", "definition", "policy", "action", "runtime", "history", "contracts", "outcome", "limitations"],
    definition: { published: true, version: "v1", immutable: true },
    policy: { deny_by_default: true, denied_side_effects: 0, read_action_evaluated: true, tool_confirmation_actor_binding: true },
    action: { executed_once: true, audit_count: 1, run_count: 1, replay_rejected: true, replay_mutations: 0 },
    runtime: { wait_persisted: true, early_resume_read_only: true, authorized_resume_completed: true, completed_start_replayed: true, tenant_isolation_non_vacuous: true, actor_isolation_non_vacuous: true },
    history: { retry_attempts: 2, retry_keys_distinct: true, provider_tool_key_stable: true, compensation_attempts: 1, compensation_completed: true, terminal_replay_mutations: 0 },
    contracts: { default_fail_closed: true, missing_actor_fail_closed: true, invalid_signature_side_effects: 0, synthetic_api_replay_rejected: true, synthetic_mcp_replay_rejected: true, synthetic_webhook_replay_rejected: true },
    outcome: { recorded_once: true, audit_linked: true, verified_reference_linked: true, sanitized: true, attribution_event_count: 1, foreign_audit_rejected: true },
    safety: { foreign_tenant_sentinel_rows: 5, same_tenant_actor_distinct: true, foreign_tenant_attempted: true, foreign_actor_attempted: true, foreign_tenant_mutations: 0, foreign_actor_mutations: 0, provider_calls: 0, network_calls: 0, payment_mutations: 0, billing_mutations: 0, credit_mutations: 0, channel_delivery_mutations: 0, canonical_business_mutations: 0, config_restored: true },
    limitations: { workforce_actor_bound_confirmation_proven: false, canonical_writer_routing_proven: false, durable_tool_outbox_proven: false, api_mcp_route_binding_proven: false, postgresql_rls_runtime_proven: false },
    production_gaps: ["PRODUCTION_GAP_AGENT_CONFIRMATION_ACTOR_BINDING", "PRODUCTION_GAP_API_MCP_ROUTE_BINDING", "PRODUCTION_GAP_CANONICAL_WRITER_ROUTING", "PRODUCTION_GAP_DURABLE_TOOL_OUTBOX", "PRODUCTION_GAP_RLS_RUNTIME_CERTIFICATION"],
    external: ["clean_exact_artifact", "independent_security_review", "live_channel_and_integration_providers", "mysql_portability", "postgresql_restricted_role_rls", "production_operations", "staging_runtime"],
});

function cloneFacts() {
    return structuredClone(acceptedFacts);
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

test("accepted schema-1 Program 06 facts validate exactly", () => {
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

test("required definition, policy, action, runtime, history, contract, and outcome facts fail independently", () => {
    const mutations = [
        (facts) => { facts.definition.published = false; },
        (facts) => { facts.definition.version = "v2"; },
        (facts) => { facts.definition.immutable = false; },
        (facts) => { facts.policy.deny_by_default = false; },
        (facts) => { facts.policy.denied_side_effects = 1; },
        (facts) => { facts.policy.read_action_evaluated = false; },
        (facts) => { facts.policy.tool_confirmation_actor_binding = false; },
        (facts) => { facts.action.executed_once = false; },
        (facts) => { facts.action.audit_count = 2; },
        (facts) => { facts.action.run_count = 2; },
        (facts) => { facts.action.replay_rejected = false; },
        (facts) => { facts.action.replay_mutations = 1; },
        (facts) => { facts.runtime.wait_persisted = false; },
        (facts) => { facts.runtime.early_resume_read_only = false; },
        (facts) => { facts.runtime.authorized_resume_completed = false; },
        (facts) => { facts.runtime.completed_start_replayed = false; },
        (facts) => { facts.runtime.tenant_isolation_non_vacuous = false; },
        (facts) => { facts.runtime.actor_isolation_non_vacuous = false; },
        (facts) => { facts.history.retry_attempts = 1; },
        (facts) => { facts.history.retry_keys_distinct = false; },
        (facts) => { facts.history.provider_tool_key_stable = false; },
        (facts) => { facts.history.compensation_attempts = 0; },
        (facts) => { facts.history.compensation_completed = false; },
        (facts) => { facts.history.terminal_replay_mutations = 1; },
        (facts) => { facts.contracts.default_fail_closed = false; },
        (facts) => { facts.contracts.missing_actor_fail_closed = false; },
        (facts) => { facts.contracts.invalid_signature_side_effects = 1; },
        (facts) => { facts.contracts.synthetic_api_replay_rejected = false; },
        (facts) => { facts.contracts.synthetic_mcp_replay_rejected = false; },
        (facts) => { facts.contracts.synthetic_webhook_replay_rejected = false; },
        (facts) => { facts.outcome.recorded_once = false; },
        (facts) => { facts.outcome.audit_linked = false; },
        (facts) => { facts.outcome.verified_reference_linked = false; },
        (facts) => { facts.outcome.sanitized = false; },
        (facts) => { facts.outcome.attribution_event_count = 2; },
        (facts) => { facts.outcome.foreign_audit_rejected = false; },
    ];
    for (const mutate of mutations) assertInvalid(mutate);
});

test("vacuous topology and every nonzero safety mutation fail", () => {
    for (const mutate of [
        (facts) => { facts.safety.foreign_tenant_sentinel_rows = 0; },
        (facts) => { facts.safety.same_tenant_actor_distinct = false; },
        (facts) => { facts.safety.foreign_tenant_attempted = false; },
        (facts) => { facts.safety.foreign_actor_attempted = false; },
        (facts) => { facts.safety.config_restored = false; },
    ]) assertInvalid(mutate);
    for (const key of ["foreign_tenant_mutations", "foreign_actor_mutations", "provider_calls", "network_calls", "payment_mutations", "billing_mutations", "credit_mutations", "channel_delivery_mutations", "canonical_business_mutations"]) {
        assertInvalid((facts) => { facts.safety[key] = 1; });
    }
});

test("limitations are fixed to false and cannot be promoted", () => {
    for (const key of Object.keys(acceptedFacts.limitations)) assertInvalid((facts) => { facts.limitations[key] = true; });
});

test("production gaps and external boundaries require exact sorted membership", () => {
    for (const mutate of [
        (facts) => { facts.production_gaps.shift(); },
        (facts) => { facts.production_gaps.push("PRODUCTION_GAP_EXTRA"); },
        (facts) => { facts.production_gaps.push(facts.production_gaps[0]); },
        (facts) => { [facts.production_gaps[0], facts.production_gaps[1]] = [facts.production_gaps[1], facts.production_gaps[0]]; },
        (facts) => { facts.external.shift(); },
        (facts) => { facts.external.push("future_boundary"); },
        (facts) => { facts.external.push(facts.external[0]); },
        (facts) => { [facts.external[0], facts.external[1]] = [facts.external[1], facts.external[0]]; },
    ]) assertInvalid(mutate);
});

test("unknown fields and runtime/sensitive values are rejected", () => {
    for (const mutate of [
        (facts) => { facts.extra = true; },
        (facts) => { facts.runtime.runtime_uuid = "00000000-0000-4000-8000-000000000000"; },
        (facts) => { facts.runtime.database_path = "C:\\temp\\scenario.sqlite"; },
        (facts) => { facts.safety.secret_reference = "synthetic"; },
        (facts) => { facts.outcome.raw_prompt = "do not leak"; },
        (facts) => { facts.history.timestamp = "2026-08-03T00:00:00Z"; },
        (facts) => { facts.outcome.email = "person@example.test"; },
        (facts) => { facts.outcome.message = "must-not-survive"; },
        (facts) => { facts.outcome.value = "sk-abcdefgh12345678"; },
    ]) assertInvalid(mutate);
});

test("normal and strict classifications are exact", () => {
    const normalClassification = classifyResult({ facts: cloneFacts(), strict: false });
    const strictClassification = classifyResult({ facts: cloneFacts(), strict: true });
    assert.deepEqual(normalClassification, {
        status: "LOCALLY_VERIFIED",
        decision: "BOUNDED_LOCAL_ONLY",
        launch: "NO_GO",
        strict: false,
        exit_code: 0,
    });
    assert.deepEqual(strictClassification, {
        status: "LOCALLY_VERIFIED",
        decision: "NO_GO",
        launch: "NO_GO",
        strict: true,
        code: "production_gaps_open",
        exit_code: 1,
    });
    const normalPersisted = { ...cloneFacts(), strict: normalClassification.strict };
    const strictPersisted = { ...cloneFacts(), strict: strictClassification.strict, decision: strictClassification.decision, code: strictClassification.code };
    assert.equal(normalPersisted.strict, false);
    assert.equal(normalPersisted.decision, "BOUNDED_LOCAL_ONLY");
    assert.equal(strictPersisted.strict, true);
    assert.equal(strictPersisted.decision, "NO_GO");
    assert.equal(strictPersisted.code, "production_gaps_open");
    assert.notDeepEqual(normalPersisted, strictPersisted);
    assert.equal(validateCompositeFacts(normalPersisted), true);
    assert.equal(validateCompositeFacts(strictPersisted), true);
});

test("argument parser rejects unknown and repeated options", () => {
    assert.deepEqual(parseArgs(["--php", "C:\\php.exe", "--report", "C:\\report.json", "--json"]), { php: "C:\\php.exe", report: "C:\\report.json", strict: false, json: true, help: false });
    for (const args of [["--unknown"], ["--json", "--json"], ["--strict", "--strict"], ["--php", "a", "--php", "b"], ["--report", "a", "--report", "b"]]) {
        assert.throws(() => parseArgs(args), /(?:Unknown argument|Repeated argument)/u);
    }
});

test("CLI rejects invalid PHP/report boundaries before a child run", (t) => {
    const root = mkdtempSync(join(tmpdir(), "dovra-program06-cli-"));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    const relative = runCli(["--php", process.execPath, "--report", "relative-report.json", "--json"]);
    assert.notEqual(relative.status, 0);
    assert.match(relative.stdout + relative.stderr, /REPORT_INVALID|absolute/u);
    const missingPhp = runCli(["--php", join(root, "missing-php"), "--report", join(root, "missing-report.json"), "--json"]);
    assert.notEqual(missingPhp.status, 0);
    assert.match(missingPhp.stdout + missingPhp.stderr, /PHP_INVALID/u);
    const belowPhp = runCli(["--php", process.execPath, "--report", join(root, "below-report.json"), "--json"]);
    assert.notEqual(belowPhp.status, 0);
    assert.match(belowPhp.stdout + belowPhp.stderr, /PHP_INVALID/u);
    const existingReport = join(root, "existing-report.json");
    writeFileSync(existingReport, "existing\n");
    const existing = runCli(["--php", process.execPath, "--report", existingReport, "--json"]);
    assert.notEqual(existing.status, 0);
    assert.match(existing.stdout + existing.stderr, /REPORT_EXISTS/u);
    const repositoryReport = runCli(["--php", process.execPath, "--report", resolve(repositoryRoot, "README.md"), "--json"]);
    assert.notEqual(repositoryReport.status, 0);
    assert.match(repositoryReport.stdout + repositoryReport.stderr, /REPORT_(?:EXISTS|REPOSITORY_OWNED)/u);
});

test("source contract pins one dedicated child command and owned pre-bootstrap cache boundary", () => {
    const source = readFileSync(certifierPath, "utf8");
    assertSourceContract(source);
    assert.match(source, /tests\/Feature\/Automation\/Program06CompositeCertificationTest\.php/u);
    assert.match(source, /const childEnvironment\s*=\s*\{/u);
    assert.match(source, /APP_CONFIG_CACHE:\s*cachePath/u);
    const inheritedIndex = source.indexOf("...process.env");
    const overrideIndex = source.indexOf('APP_ENV: "testing"');
    assert.ok(inheritedIndex >= 0 && overrideIndex > inheritedIndex);
    assert.match(source, /CACHE_STORE:\s*["']array["']/u);
    assert.match(source, /SESSION_DRIVER:\s*["']array["']/u);
    assert.match(source, /DB_DATABASE:\s*databasePath/u);
    assert.match(source, /DOVRA_PROGRAM06_COMPOSITE_DATABASE:\s*databasePath/u);
    assert.match(source, /DOVRA_PROGRAM06_COMPOSITE_ALLOW:\s*["']true["']/u);
    assert.match(source, /DOVRA_PROGRAM06_COMPOSITE_FACTS:\s*phpFactsPath/u);
    assert.match(source, /dedicatedPestPath\s*=\s*["']tests\/Feature\/Automation\/Program06CompositeCertificationTest\.php["']/u);
    assert.ok([...source.matchAll(/existsSync\(cachePath\)/gu)].length >= 4);
    const childStart = source.indexOf("const result = spawnSync(phpPath");
    assert.ok(childStart >= 0);
    assert.match(source.slice(0, childStart), /if \(existsSync\(cachePath\)\)/u);
    assert.match(source.slice(childStart), /if \(existsSync\(cachePath\)\)/u);
    assert.match(source, /finally\s*\{[\s\S]*?existsSync\(cachePath\)[\s\S]*?rmSync\(tempDirectory/u);
    assert.match(source, /assertInside\(tempDirectory,\s*cachePath,\s*["']APP_CONFIG_CACHE["']\)/u);
    assert.match(source, /for \(const path of \[databasePath, phpFactsPath, cachePath\]\) assertInside\(tempDirectory, path/u);
    assert.match(source, /vendor\/bin\/pest/u);
    assert.match(source, /dedicatedPestPath.*--colors=never|dedicatedPestPath\]/u);
    assert.doesNotMatch(source, /(?:config|optimize)\s*:\s*(?:clear|cache)/iu);
    assert.doesNotMatch(source, /(?:composer|npm|docker|curl|wget|fetch|axios|git)\b/iu);
    assert.doesNotMatch(source, /(?:production|staging)[_-]?database/iu);

    const missingCacheAssignment = source.replace(/\s*APP_CONFIG_CACHE:\s*cachePath,\r?\n/u, "\n");
    assert.throws(() => assertSourceContract(missingCacheAssignment));
    const workspaceCache = source.replace(/APP_CONFIG_CACHE:\s*cachePath/u, 'APP_CONFIG_CACHE: join(repositoryRoot, "bootstrap/cache/config.php")');
    assert.throws(() => assertSourceContract(workspaceCache));
    const inheritedDatabase = source.replace(/\s*DB_DATABASE:\s*databasePath,\r?\n/u, "\n");
    assert.throws(() => assertSourceContract(inheritedDatabase));
    const inheritedSpreadAfterOverrides = source.replace(/\s*\.\.\.process\.env,\r?\n/u, "\n").replace(/(DOVRA_PROGRAM06_COMPOSITE_FACTS:\s*phpFactsPath,\r?\n)/u, "$1        ...process.env,\n");
    assert.throws(() => assertSourceContract(inheritedSpreadAfterOverrides));
    const broaderPest = source.replace(/tests\/Feature\/Automation\/Program06CompositeCertificationTest\.php/u, "tests/Feature/Automation");
    assert.throws(() => assertSourceContract(broaderPest));
    const escapedCleanup = source.replace(/for \(const path of \[databasePath, phpFactsPath, cachePath\]\) assertInside\(tempDirectory, path, "Composite cleanup"\);/u, "for (const path of [databasePath]) void path;");
    assert.throws(() => assertSourceContract(escapedCleanup));
});

function assertSourceContract(source) {
    assert.match(source, /dedicatedPestPath\s*=\s*["']tests\/Feature\/Automation\/Program06CompositeCertificationTest\.php["']/u);
    const inheritedIndex = source.indexOf("...process.env");
    const overrideIndex = source.indexOf('APP_ENV: "testing"');
    if (inheritedIndex < 0 || overrideIndex <= inheritedIndex) throw new Error("CHILD_ENVIRONMENT_ORDER_INVALID");
    assert.match(source, /APP_CONFIG_CACHE:\s*cachePath/u);
    assert.match(source, /APP_ENV:\s*["']testing["']/u);
    assert.match(source, /APP_KEY:\s*`base64:/u);
    assert.match(source, /CACHE_STORE:\s*["']array["']/u);
    assert.match(source, /SESSION_DRIVER:\s*["']array["']/u);
    assert.match(source, /DB_DATABASE:\s*databasePath/u);
    assert.match(source, /DOVRA_PROGRAM06_COMPOSITE_DATABASE:\s*databasePath/u);
    assert.match(source, /DOVRA_PROGRAM06_COMPOSITE_ALLOW:\s*["']true["']/u);
    assert.match(source, /DOVRA_PROGRAM06_COMPOSITE_FACTS:\s*phpFactsPath/u);
    assert.ok([...source.matchAll(/existsSync\(cachePath\)/gu)].length >= 4);
    assert.match(source, /for \(const path of \[databasePath, phpFactsPath, cachePath\]\) assertInside\(tempDirectory, path, "Composite cleanup"\);/u);
}

test("implementation boundary remains exactly the five authorized new files", () => {
    const expectedInventory = Object.freeze([
        "tests/Support/Automation/Program06CompositeCertificateFixture.php",
        "tests/Support/Automation/Program06CompositeCertificateReport.php",
        "tests/Feature/Automation/Program06CompositeCertificationTest.php",
        "scripts/qa/certify-dovra-program06-composite.mjs",
        "tests/qa/dovra-program06-composite-certifier.test.mjs",
    ]);
    assert.equal(repositoryWriteAllowlist.length, 0);
    assert.deepEqual(implementationInventory, expectedInventory);
    assert.deepEqual(new Set(implementationInventory).size, 5);
    assert.equal(implementationInventory.filter((path) => path.includes("app/") || path.includes("database/") || path.includes("routes/") || path.includes("config/")).length, 0);
    assert.doesNotThrow(() => validateImplementationInventory(implementationInventory, expectedInventory));
    assertInvalidInventoryMutation((value) => value.splice(0, 1));
    assertInvalidInventoryMutation((value) => value.push("app/Unexpected.php"));
});

function assertInvalidInventoryMutation(mutate) {
    const value = [...implementationInventory];
    mutate(value);
    assert.throws(() => validateImplementationInventory(value, implementationInventory));
}

function validateImplementationInventory(value, expected) {
    if (JSON.stringify(value) !== JSON.stringify(expected)) throw new Error("IMPLEMENTATION_INVENTORY_INVALID");
    if (value.some((path) => path.startsWith("app/") || path.startsWith("database/") || path.startsWith("routes/") || path.startsWith("config/"))) throw new Error("IMPLEMENTATION_PRODUCTION_PATH");
}
