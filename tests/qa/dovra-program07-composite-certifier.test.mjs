import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { existsSync, lstatSync, mkdtempSync, readFileSync, rmSync, symlinkSync, writeFileSync } from "node:fs";
import { join, resolve } from "node:path";
import { tmpdir } from "node:os";
import { pathToFileURL } from "node:url";
import {
    assertNewAbsoluteReport,
    assertNewOwnedDatabase,
    assertPhpBinary,
    certifyComposite,
    classifyResult,
    implementationInventory,
    parseArgs,
    repositoryWriteAllowlist,
    runDedicatedPest,
    validateCompositeFacts,
    validateImplementationInventory,
} from "../../scripts/qa/certify-dovra-program07-composite.mjs";

const repositoryRoot = resolve(import.meta.dirname, "../..");
const certifierPath = resolve(repositoryRoot, "scripts/qa/certify-dovra-program07-composite.mjs");

const expectedDelta = Object.freeze({
    channel_connections: 4,
    channel_capability_snapshots: 6,
    channel_provider_health: 4,
    channel_identities: 2,
    channel_consents: 2,
    channel_delivery_outbox: 4,
    channel_delivery_attempts: 4,
    channel_webhook_claims: 2,
    dovra_conversation_channel_links: 3,
    ai_conversations: 1,
    ai_messages: 2,
});

const productionGaps = Object.freeze([
    "PRODUCTION_GAP_ATTACHMENT_SECURITY_PIPELINE",
    "PRODUCTION_GAP_CHANNEL_INGRESS_ROUTES",
    "PRODUCTION_GAP_CHANNEL_RETENTION_ENFORCEMENT",
    "PRODUCTION_GAP_LIVE_PROVIDER_ADAPTERS",
    "PRODUCTION_GAP_LIVE_WEBHOOK_DELIVERY",
    "PRODUCTION_GAP_POSTGRES_RESTRICTED_ROLE_RLS",
    "PRODUCTION_GAP_PROVIDER_HEALTH_RECONCILIATION",
    "PRODUCTION_GAP_PROVIDER_OUTAGE_DRILL",
    "PRODUCTION_GAP_PRODUCTION_SECRETS",
]);

const acceptedFacts = Object.freeze({
    schema: "dovra.program07.composite.v1",
    status: "LOCALLY_VERIFIED",
    decision: "BOUNDED_LOCAL_ONLY",
    launch: "NO_GO",
    scope: "sqlite_disposable_local",
    subjects: {
        subject_created: true,
        foreign_created: true,
        tenant_ids_distinct: true,
        actor_ids_distinct: true,
    },
    connection: {
        email_active: true,
        email_capability_allowed: true,
        email_health: "available",
        secret_values_persisted: 0,
    },
    inbound: {
        invalid_signature_rejected: true,
        invalid_signature_mutations: 0,
        signed_email_normalized: true,
        attachment_classification_observed: true,
    },
    identity: { tenant_scoped: true },
    consent: { granted_with_channel_lineage: true },
    conversation: {
        claimed_by_canonical_store: true,
        active_tenant_private: true,
        cross_channel_continuity: true,
        message_count: 2,
        inbound_link_count: 2,
    },
    replay: {
        same_event_stable: true,
        same_event_mutations: 0,
        conflicting_payload_rejected: true,
        conflicting_payload_mutations: 0,
        conflicting_payload_exception: "RuntimeException",
    },
    outbound: {
        delivered: true,
        provider_side_effects: 1,
        exactly_once_observed: true,
        replay_capability_snapshot_delta: 1,
        replay_outbox_row_delta: 0,
        replay_attempt_row_delta: 0,
        replay_link_row_delta: 0,
        replay_provider_side_effect_delta: 0,
        replay_connection_refreshed: true,
        replay_health_refreshed: true,
        replay_link_refreshed: true,
        payload_conflict_rejected: true,
        conflict_capability_snapshot_delta: 1,
        conflict_outbox_row_delta: 0,
        conflict_attempt_row_delta: 0,
        conflict_link_row_delta: 0,
        conflict_provider_side_effect_delta: 0,
        conflict_connection_refreshed: true,
        conflict_health_refreshed: true,
        cost_amount: "0.01",
        cost_currency: "USD",
        conversation_linked: true,
    },
    providers: {
        whatsapp_state: "blocked",
        sms_state: "blocked",
        voice_state: "blocked",
        denial_reason: "market_capability_missing",
        live_provider_calls: 0,
        honduras_fallbacks: 0,
    },
    safety: {
        foreign_sentinel_rows: 9,
        foreign_tenant_attempted: true,
        foreign_tenant_mutations: 0,
        subject_mutations_from_foreign_attempt: 0,
    },
    deltas: {
        subject: expectedDelta,
        foreign_total: 0,
        payments: 0,
        subscription_payments: 0,
        webhook_logs: 0,
        outbox_messages: 0,
    },
    external: {
        network_calls: 0,
        live_provider_calls: 0,
        payment_calls: 0,
    },
    limitations: {
        route_binding_proven: false,
        live_provider_adapters_proven: false,
        retention_enforcement_proven: false,
        provider_health_reconciliation_proven: false,
        attachment_security_pipeline_proven: false,
        postgresql_restricted_role_rls_proven: false,
        live_webhook_delivery_proven: false,
        provider_outage_drill_proven: false,
        production_secrets_proven: false,
    },
    production_gaps: productionGaps,
    strict: false,
});

function cloneFacts() {
    return structuredClone(acceptedFacts);
}

function assertInvalid(mutate) {
    const facts = cloneFacts();
    mutate(facts);
    assert.throws(() => validateCompositeFacts(facts), /COMPOSITE_FACT_INVALID/u);
}

function isolatedChildPaths() {
    const root = mkdtempSync(join(tmpdir(), "dovra-program07-child-"));
    const databasePath = join(root, "scenario.sqlite");
    const phpFactsPath = join(root, "php-facts.json");
    const cachePath = join(root, "config-cache.php");
    writeFileSync(databasePath, "");
    return { root, tempDirectory: root, databasePath, phpFactsPath, cachePath };
}

function validChildJson() {
    return `${JSON.stringify(cloneFacts())}\n`;
}

test("the complete normal Program 07 composite facts validate exactly", () => {
    assert.equal(validateCompositeFacts(cloneFacts()), true);
});

test("every required top-level object and every nested object is closed", () => {
    for (const key of [
        "subjects", "connection", "inbound", "identity", "consent", "conversation", "replay",
        "outbound", "providers", "safety", "deltas", "external", "limitations",
    ]) {
        assertInvalid((facts) => { delete facts[key]; });
    }
    assertInvalid((facts) => { facts.extra = true; });
    assertInvalid((facts) => { delete facts.strict; });
    assertInvalid((facts) => { facts.connection.extra = true; });
    assertInvalid((facts) => { facts.deltas.subject.extra = 1; });
});

test("status, decision, launch, scope, schema, and production gaps are immutable", () => {
    for (const mutate of [
        (facts) => { facts.schema = "dovra.program07.composite.v2"; },
        (facts) => { facts.status = "FAIL"; },
        (facts) => { facts.decision = "NO_GO"; },
        (facts) => { facts.launch = "GO"; },
        (facts) => { facts.scope = "workspace"; },
        (facts) => { facts.production_gaps.shift(); },
        (facts) => { facts.production_gaps.push("PRODUCTION_GAP_EXTRA"); },
        (facts) => { [facts.production_gaps[0], facts.production_gaps[1]] = [facts.production_gaps[1], facts.production_gaps[0]]; },
        (facts) => { facts.production_gaps.push(facts.production_gaps[0]); },
    ]) assertInvalid(mutate);
});

test("limitations remain false and non-vacuous subject/foreign facts remain true", () => {
    for (const key of Object.keys(acceptedFacts.limitations)) assertInvalid((facts) => { facts.limitations[key] = true; });
    for (const mutate of [
        (facts) => { facts.subjects.subject_created = false; },
        (facts) => { facts.subjects.foreign_created = false; },
        (facts) => { facts.subjects.tenant_ids_distinct = false; },
        (facts) => { facts.subjects.actor_ids_distinct = false; },
        (facts) => { facts.safety.foreign_sentinel_rows = 0; },
        (facts) => { facts.safety.foreign_tenant_attempted = false; },
    ]) assertInvalid(mutate);
});

test("all exact counts, deltas, provider effects, and external zero boundaries are enforced", () => {
    for (const mutate of [
        (facts) => { facts.connection.secret_values_persisted = -1; },
        (facts) => { facts.inbound.invalid_signature_mutations = 1; },
        (facts) => { facts.conversation.message_count = 1; },
        (facts) => { facts.replay.same_event_mutations = 1; },
        (facts) => { facts.replay.conflicting_payload_mutations = 1; },
        (facts) => { facts.replay.conflicting_payload_exception = "ChannelConflictException"; },
        (facts) => { facts.outbound.provider_side_effects = 2; },
        (facts) => { facts.outbound.replay_capability_snapshot_delta = 2; },
        (facts) => { facts.outbound.replay_outbox_row_delta = 1; },
        (facts) => { facts.outbound.replay_attempt_row_delta = 1; },
        (facts) => { facts.outbound.replay_link_row_delta = 1; },
        (facts) => { facts.outbound.replay_provider_side_effect_delta = 1; },
        (facts) => { facts.outbound.replay_connection_refreshed = false; },
        (facts) => { facts.outbound.replay_health_refreshed = false; },
        (facts) => { facts.outbound.replay_link_refreshed = false; },
        (facts) => { facts.outbound.conflict_capability_snapshot_delta = 2; },
        (facts) => { facts.outbound.conflict_outbox_row_delta = 1; },
        (facts) => { facts.outbound.conflict_attempt_row_delta = 1; },
        (facts) => { facts.outbound.conflict_link_row_delta = 1; },
        (facts) => { facts.outbound.conflict_provider_side_effect_delta = 1; },
        (facts) => { facts.outbound.conflict_connection_refreshed = false; },
        (facts) => { facts.outbound.conflict_health_refreshed = false; },
        (facts) => { facts.providers.live_provider_calls = 1; },
        (facts) => { facts.providers.honduras_fallbacks = 1; },
        (facts) => { facts.safety.foreign_tenant_mutations = 1; },
        (facts) => { facts.safety.subject_mutations_from_foreign_attempt = 1; },
        (facts) => { facts.deltas.subject.ai_messages = 1; },
        (facts) => { facts.deltas.foreign_total = 1; },
        (facts) => { facts.deltas.payments = 1; },
        (facts) => { facts.external.network_calls = 1; },
        (facts) => { facts.external.payment_calls = 1; },
        (facts) => { facts.outbound.cost_amount = "1.00"; },
        (facts) => { facts.providers.denial_reason = "credentials_missing"; },
    ]) assertInvalid(mutate);
    for (const key of Object.keys(expectedDelta)) {
        assertInvalid((facts) => { facts.deltas.subject[key] += 1; });
    }
    for (const snapshotDelta of [5, 7]) {
        assertInvalid((facts) => { facts.deltas.subject.channel_capability_snapshots = snapshotDelta; });
    }
});

test("normal and strict classifications expose the fixed truthful gap boundary", () => {
    const normal = classifyResult({ facts: cloneFacts(), strict: false });
    const strict = classifyResult({ facts: cloneFacts(), strict: true });
    assert.deepEqual(normal, {
        status: "LOCALLY_VERIFIED", decision: "BOUNDED_LOCAL_ONLY", launch: "NO_GO", strict: false, exit_code: 0,
    });
    assert.deepEqual(strict, {
        status: "LOCALLY_VERIFIED", decision: "NO_GO", launch: "NO_GO", strict: true,
        code: "production_gaps_open", exit_code: 1,
    });
    const strictFacts = { ...cloneFacts(), strict: true, decision: "NO_GO", code: "production_gaps_open" };
    assert.equal(validateCompositeFacts(strictFacts), true);
});

test("argument parser rejects missing, repeated, unknown, and positional arguments", () => {
    assert.deepEqual(parseArgs(["--php", "C:\\php.exe", "--report", "C:\\report.json", "--json"]), {
        php: "C:\\php.exe", report: "C:\\report.json", strict: false, json: true, help: false,
    });
    for (const args of [
        [], ["--unknown"], ["positional"], ["--json", "--json"], ["--strict", "--strict"],
        ["--php", "a", "--php", "b"], ["--report", "a", "--report", "b"],
    ]) assert.throws(() => parseArgs(args), /(?:required|Unknown argument|Repeated argument)/u);
});

test("PHP preflight and report path guards reject unsafe boundaries", (t) => {
    const owned = mkdtempSync(join(tmpdir(), "dovra-program07-node-"));
    t.after(() => rmSync(owned, { recursive: true, force: true }));
    assert.throws(() => assertPhpBinary(join(owned, "missing-php")), /PHP_INVALID/u);
    assert.doesNotThrow(() => assertNewAbsoluteReport(join(owned, "report.json")));
    const existing = join(owned, "existing.json");
    writeFileSync(existing, "{}\n");
    assert.throws(() => assertNewAbsoluteReport(existing), /REPORT_EXISTS/u);
    const repositoryReport = join(repositoryRoot, "README.md");
    assert.throws(() => assertNewAbsoluteReport(repositoryReport), /REPORT_(?:EXISTS|REPOSITORY_OWNED)/u);
    if (process.platform !== "win32") {
        const alias = join(owned, "alias");
        symlinkSync(owned, alias, "dir");
        assert.throws(() => assertNewAbsoluteReport(join(alias, "report.json")), /REPORT_PARENT|alias/u);
        assert.equal(lstatSync(alias).isSymbolicLink(), true);
    }
});

test("PHP preflight rejects below-8.4 and failed version probes through the test seam", () => {
    assert.throws(
        () => assertPhpBinary(certifierPath, { probe: () => ({ status: 0, stdout: "80399", stderr: "" }) }),
        /PHP 8\.4/u,
    );
    assert.throws(
        () => assertPhpBinary(certifierPath, { probe: () => ({ status: 7, stdout: "", stderr: "", error: new Error("probe") }) }),
        /version probe/u,
    );
});

test("owned database, report, cache, and alias boundaries reject pre-existing entries", (t) => {
    const owned = mkdtempSync(join(tmpdir(), "dovra-program07-boundary-"));
    t.after(() => rmSync(owned, { recursive: true, force: true }));
    const database = join(owned, "scenario.sqlite");
    writeFileSync(database, "existing");
    assert.throws(() => assertNewOwnedDatabase(database, owned), /DATABASE_EXISTS/u);
    const report = join(owned, "report.json");
    writeFileSync(report, "existing");
    assert.throws(() => assertNewAbsoluteReport(report), /REPORT_EXISTS/u);
    const cache = join(owned, "config-cache.php");
    writeFileSync(cache, "existing");
    const paths = isolatedChildPaths();
    t.after(() => rmSync(paths.root, { recursive: true, force: true }));
    writeFileSync(paths.cachePath, "existing");
    assert.throws(() => runDedicatedPest({
        phpPath: certifierPath,
        ...paths,
        spawn: () => { throw new Error("spawn must not run when cache exists"); },
    }), /APP_CONFIG_CACHE_INVALID/u);
    if (process.platform !== "win32") {
        const target = join(owned, "target.json");
        const alias = join(owned, "alias.json");
        symlinkSync(target, alias, "file");
        assert.throws(() => assertNewAbsoluteReport(alias), /REPORT_EXISTS/u);
        assert.equal(lstatSync(alias).isSymbolicLink(), true);
    }
});

test("dedicated orchestration rejects child failure, missing, malformed, oversized, and symlink reports", (t) => {
    const cases = [
        {
            name: "child failure",
            run: (paths) => ({ status: 23, stdout: "", stderr: "failed" }),
            expected: /DEDICATED_PEST_FAILED/u,
        },
        {
            name: "missing report",
            run: () => ({ status: 0, stdout: "1 passed", stderr: "" }),
            expected: /PHP_FACTS_MISSING/u,
        },
        {
            name: "malformed report",
            run: (paths) => { writeFileSync(paths.phpFactsPath, "not-json"); return { status: 0, stdout: "1 passed", stderr: "" }; },
            expected: /PHP_FACTS_INVALID/u,
        },
        {
            name: "oversized report",
            run: (paths) => { writeFileSync(paths.phpFactsPath, "x".repeat(2 * 1024 * 1024 + 1)); return { status: 0, stdout: "1 passed", stderr: "" }; },
            expected: /exceeds bounded size/u,
        },
    ];
    for (const scenario of cases) {
        const paths = isolatedChildPaths();
        t.after(() => rmSync(paths.root, { recursive: true, force: true }));
        assert.throws(() => runDedicatedPest({
            phpPath: certifierPath,
            ...paths,
            spawn: () => scenario.run(paths),
        }), scenario.expected, scenario.name);
    }
    if (process.platform !== "win32") {
        const paths = isolatedChildPaths();
        t.after(() => rmSync(paths.root, { recursive: true, force: true }));
        const target = join(paths.root, "facts-target.json");
        writeFileSync(target, validChildJson());
        symlinkSync(target, paths.phpFactsPath, "file");
        assert.throws(() => runDedicatedPest({
            phpPath: certifierPath,
            ...paths,
            spawn: () => ({ status: 0, stdout: "1 passed", stderr: "" }),
        }), /PHP_FACTS_INVALID/u, "symlink report");
    }
});

test("cache creation is reported after owned temporary cleanup", (t) => {
    const owned = mkdtempSync(join(tmpdir(), "dovra-program07-cleanup-"));
    t.after(() => rmSync(owned, { recursive: true, force: true }));
    const report = join(owned, "result.json");
    let childDirectory = null;
    assert.throws(() => certifyComposite({
        php: certifierPath,
        report,
        probe: () => ({ status: 0, stdout: "80400", stderr: "" }),
        runPest: ({ tempDirectory, cachePath, phpFactsPath }) => {
            childDirectory = tempDirectory;
            writeFileSync(cachePath, "malicious-cache");
            writeFileSync(phpFactsPath, validChildJson());
            return { facts: cloneFacts() };
        },
    }), /APP_CONFIG_CACHE_CREATED/u);
    assert.ok(childDirectory);
    assert.equal(existsSync(childDirectory), false);
});

test("implementation inventory is exactly the five authorized new files", () => {
    const expected = [
        "tests/Support/Channels/Program07CompositeCertificateFixture.php",
        "tests/Support/Channels/Program07CompositeCertificateReport.php",
        "tests/Feature/Channels/Program07CompositeCertificationTest.php",
        "scripts/qa/certify-dovra-program07-composite.mjs",
        "tests/qa/dovra-program07-composite-certifier.test.mjs",
    ];
    assert.equal(repositoryWriteAllowlist.length, 0);
    assert.deepEqual(implementationInventory, expected);
    assert.doesNotThrow(() => validateImplementationInventory(implementationInventory, expected));
    assert.throws(() => validateImplementationInventory([...expected, "app/Unexpected.php"], expected), /IMPLEMENTATION/u);
});

test("certifier source is process-safe, import-safe, and invokes only the dedicated feature test", () => {
    const source = readFileSync(certifierPath, "utf8");
    assert.match(source, /dedicatedPestPath\s*=\s*["']tests\/Feature\/Channels\/Program07CompositeCertificationTest\.php["']/u);
    assert.match(source, /APP_CONFIG_CACHE\s*:\s*cachePath/u);
    assert.match(source, /DOVRA_PROGRAM07_COMPOSITE_DATABASE\s*:\s*databasePath/u);
    assert.match(source, /DOVRA_PROGRAM07_COMPOSITE_REPORT\s*:\s*phpFactsPath/u);
    assert.match(source, /DOVRA_PROGRAM07_COMPOSITE_ALLOW\s*:\s*["']true["']/u);
    assert.match(source, /CACHE_STORE\s*:\s*["']array["']/u);
    assert.match(source, /SESSION_DRIVER\s*:\s*["']array["']/u);
    assert.match(source, /shell:\s*false/u);
    assert.doesNotMatch(source, /(?:config|optimize)\s*:\s*(?:clear|cache)/iu);
    assert.doesNotMatch(source, /(?:composer|npm|docker|curl|wget|fetch|axios|git)\b/iu);
    assert.doesNotMatch(source, /(?:production|staging)[_-]?database/iu);
    assert.match(source, /if \(process\.argv\[1\][\s\S]*?certifyComposite/u);
});

test("CLI module import does not execute orchestration", () => {
    const probe = spawnSync(process.execPath, ["--input-type=module", "-e", `import ${JSON.stringify(pathToFileURL(certifierPath).href)}; console.log("IMPORTED");`], {
        cwd: repositoryRoot,
        encoding: "utf8",
        shell: false,
    });
    assert.equal(probe.status, 0);
    assert.equal(probe.stdout.trim(), "IMPORTED");
});
