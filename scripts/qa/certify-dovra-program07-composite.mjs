import { randomBytes } from "node:crypto";
import { spawnSync } from "node:child_process";
import {
    chmodSync,
    closeSync,
    existsSync,
    lstatSync,
    mkdtempSync,
    openSync,
    readFileSync,
    realpathSync,
    rmSync,
    writeFileSync,
} from "node:fs";
import { dirname, isAbsolute, join, relative, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { tmpdir } from "node:os";

const repositoryRoot = resolve(import.meta.dirname, "../..");
const dedicatedPestPath = "tests/Feature/Channels/Program07CompositeCertificationTest.php";

export const implementationInventory = Object.freeze([
    "tests/Support/Channels/Program07CompositeCertificateFixture.php",
    "tests/Support/Channels/Program07CompositeCertificateReport.php",
    "tests/Feature/Channels/Program07CompositeCertificationTest.php",
    "scripts/qa/certify-dovra-program07-composite.mjs",
    "tests/qa/dovra-program07-composite-certifier.test.mjs",
]);
export const repositoryWriteAllowlist = Object.freeze([]);

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

const topLevelKeys = new Set([
    "schema", "status", "decision", "launch", "scope", "subjects", "connection", "inbound", "identity",
    "consent", "conversation", "replay", "outbound", "providers", "safety", "deltas", "external",
    "limitations", "production_gaps", "strict", "code",
]);
const requiredTopLevelKeys = new Set([...topLevelKeys].filter((key) => key !== "code"));
const nestedKeys = Object.freeze({
    subjects: new Set(["subject_created", "foreign_created", "tenant_ids_distinct", "actor_ids_distinct"]),
    connection: new Set(["email_active", "email_capability_allowed", "email_health", "secret_values_persisted"]),
    inbound: new Set(["invalid_signature_rejected", "invalid_signature_mutations", "signed_email_normalized", "attachment_classification_observed"]),
    identity: new Set(["tenant_scoped"]),
    consent: new Set(["granted_with_channel_lineage"]),
    conversation: new Set(["claimed_by_canonical_store", "active_tenant_private", "cross_channel_continuity", "message_count", "inbound_link_count"]),
    replay: new Set(["same_event_stable", "same_event_mutations", "conflicting_payload_rejected", "conflicting_payload_mutations", "conflicting_payload_exception"]),
    outbound: new Set([
        "delivered", "provider_side_effects", "exactly_once_observed", "replay_capability_snapshot_delta", "replay_outbox_row_delta",
        "replay_attempt_row_delta", "replay_link_row_delta", "replay_provider_side_effect_delta", "replay_connection_refreshed",
        "replay_health_refreshed", "replay_link_refreshed", "payload_conflict_rejected", "conflict_capability_snapshot_delta",
        "conflict_outbox_row_delta", "conflict_attempt_row_delta", "conflict_link_row_delta", "conflict_provider_side_effect_delta",
        "conflict_connection_refreshed", "conflict_health_refreshed", "cost_amount", "cost_currency", "conversation_linked",
    ]),
    providers: new Set(["whatsapp_state", "sms_state", "voice_state", "denial_reason", "live_provider_calls", "honduras_fallbacks"]),
    safety: new Set(["foreign_sentinel_rows", "foreign_tenant_attempted", "foreign_tenant_mutations", "subject_mutations_from_foreign_attempt"]),
    deltas: new Set(["subject", "foreign_total", "payments", "subscription_payments", "webhook_logs", "outbox_messages"]),
    external: new Set(["network_calls", "live_provider_calls", "payment_calls"]),
    limitations: new Set([
        "route_binding_proven", "live_provider_adapters_proven", "retention_enforcement_proven", "provider_health_reconciliation_proven",
        "attachment_security_pipeline_proven", "postgresql_restricted_role_rls_proven", "live_webhook_delivery_proven",
        "provider_outage_drill_proven", "production_secrets_proven",
    ]),
});
const knownFactKeys = new Set([
    ...topLevelKeys,
    ...Object.values(nestedKeys).flatMap((keys) => [...keys]),
    ...Object.keys(expectedDelta),
]);

function issue(path, detail) {
    return new Error(`COMPOSITE_FACT_INVALID: ${path} ${detail}`);
}

function requireObject(value, path) {
    if (value === null || typeof value !== "object" || Array.isArray(value)) throw issue(path, "must be an object");
}

function requireBoolean(value, path) {
    if (typeof value !== "boolean") throw issue(path, "must be boolean");
}

function requireInteger(value, path, minimum = 0) {
    if (!Number.isSafeInteger(value) || value < minimum) throw issue(path, `must be an integer >= ${minimum}`);
}

function requireExactKeys(value, allowed, path, required = allowed) {
    for (const key of Object.keys(value)) {
        if (!allowed.has(key)) throw issue(`${path}.${key}`, "unknown field");
    }
    for (const key of required) {
        if (!Object.hasOwn(value, key)) throw issue(`${path}.${key}`, "required field missing");
    }
}

function requireTrue(value, path) {
    requireBoolean(value, path);
    if (value !== true) throw issue(path, "must be true");
}

function requireFalse(value, path) {
    requireBoolean(value, path);
    if (value !== false) throw issue(path, "must be false");
}

function requireZero(value, path) {
    requireInteger(value, path);
    if (value !== 0) throw issue(path, "must be zero");
}

function scanForLeaks(value, path = "facts") {
    if (Array.isArray(value)) {
        for (const [index, item] of value.entries()) scanForLeaks(item, `${path}[${index}]`);
        return;
    }
    if (value !== null && typeof value === "object") {
        for (const [key, item] of Object.entries(value)) {
            if (!knownFactKeys.has(key)
                && /(?:uuid|tenant_(?:id|uuid|path)|(?:^|_)actor(?:_id|_uuid)$|database|absolute|secret|prompt|message|timestamp|created_at|updated_at|email|payload|token|marker|raw)/iu.test(key)) {
                throw issue(`${path}.${key}`, "runtime or sensitive field is forbidden");
            }
            scanForLeaks(item, `${path}.${key}`);
        }
        return;
    }
    if (typeof value !== "string") return;
    if (/\b[0-9a-f]{8}-[0-9a-f-]{27,}\b/iu.test(value)
        || /(?:^|[\\/])(?:[A-Za-z]:)?[\\/]/u.test(value)
        || /(?:base64:|sk-[A-Za-z0-9_-]{8,}|APP_KEY|BEGIN [A-Z ]+ KEY)/u.test(value)
        || /(?:system\s+prompt|raw\s+prompt|ciphertext|secret\s+reference|must-not-survive)/iu.test(value)
        || /[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u.test(value)) {
        throw issue(path, "runtime or sensitive value is forbidden");
    }
}

function requireStageObjects(facts) {
    for (const [stage, allowed] of Object.entries(nestedKeys)) {
        requireObject(facts[stage], `facts.${stage}`);
        requireExactKeys(facts[stage], allowed, `facts.${stage}`);
    }
}

export function validateCompositeFacts(facts) {
    requireObject(facts, "facts");
    requireExactKeys(facts, topLevelKeys, "facts", requiredTopLevelKeys);
    requireStageObjects(facts);
    scanForLeaks(facts);

    if (facts.schema !== "dovra.program07.composite.v1") throw issue("facts.schema", "must equal dovra.program07.composite.v1");
    if (facts.status !== "LOCALLY_VERIFIED") throw issue("facts.status", "must be LOCALLY_VERIFIED");
    if (facts.launch !== "NO_GO") throw issue("facts.launch", "must be NO_GO");
    if (facts.scope !== "sqlite_disposable_local") throw issue("facts.scope", "must be sqlite_disposable_local");
    const strictReport = facts.strict;
    requireBoolean(strictReport, "facts.strict");
    if (strictReport) {
        if (facts.decision !== "NO_GO") throw issue("facts.decision", "must be NO_GO for strict reports");
        if (facts.code !== "production_gaps_open") throw issue("facts.code", "must be production_gaps_open for strict reports");
    } else {
        if (facts.decision !== "BOUNDED_LOCAL_ONLY") throw issue("facts.decision", "must be BOUNDED_LOCAL_ONLY");
        if (Object.hasOwn(facts, "code")) throw issue("facts.code", "must be absent for normal reports");
    }
    for (const key of ["subject_created", "foreign_created", "tenant_ids_distinct", "actor_ids_distinct"]) requireTrue(facts.subjects[key], `facts.subjects.${key}`);
    requireTrue(facts.connection.email_active, "facts.connection.email_active");
    requireTrue(facts.connection.email_capability_allowed, "facts.connection.email_capability_allowed");
    if (facts.connection.email_health !== "available") throw issue("facts.connection.email_health", "must be available");
    requireZero(facts.connection.secret_values_persisted, "facts.connection.secret_values_persisted");
    requireTrue(facts.inbound.invalid_signature_rejected, "facts.inbound.invalid_signature_rejected");
    requireZero(facts.inbound.invalid_signature_mutations, "facts.inbound.invalid_signature_mutations");
    requireTrue(facts.inbound.signed_email_normalized, "facts.inbound.signed_email_normalized");
    requireTrue(facts.inbound.attachment_classification_observed, "facts.inbound.attachment_classification_observed");
    requireTrue(facts.identity.tenant_scoped, "facts.identity.tenant_scoped");
    requireTrue(facts.consent.granted_with_channel_lineage, "facts.consent.granted_with_channel_lineage");
    for (const key of ["claimed_by_canonical_store", "active_tenant_private", "cross_channel_continuity"]) requireTrue(facts.conversation[key], `facts.conversation.${key}`);
    for (const key of ["message_count", "inbound_link_count"]) {
        requireInteger(facts.conversation[key], `facts.conversation.${key}`, 0);
    }
    if (facts.conversation.message_count !== 2) throw issue("facts.conversation.message_count", "must equal 2");
    if (facts.conversation.inbound_link_count !== 2) throw issue("facts.conversation.inbound_link_count", "must equal 2");

    for (const key of ["same_event_stable", "conflicting_payload_rejected"]) requireTrue(facts.replay[key], `facts.replay.${key}`);
    requireZero(facts.replay.same_event_mutations, "facts.replay.same_event_mutations");
    requireZero(facts.replay.conflicting_payload_mutations, "facts.replay.conflicting_payload_mutations");
    if (facts.replay.conflicting_payload_exception !== "RuntimeException") throw issue("facts.replay.conflicting_payload_exception", "must equal RuntimeException");

    const outbound = facts.outbound;
    for (const key of ["delivered", "exactly_once_observed", "payload_conflict_rejected", "conversation_linked", "replay_connection_refreshed", "replay_health_refreshed", "replay_link_refreshed", "conflict_connection_refreshed", "conflict_health_refreshed"]) requireTrue(outbound[key], `facts.outbound.${key}`);
    requireInteger(outbound.provider_side_effects, "facts.outbound.provider_side_effects", 0);
    if (outbound.provider_side_effects !== 1) throw issue("facts.outbound.provider_side_effects", "must equal 1");
    for (const key of ["replay_capability_snapshot_delta", "conflict_capability_snapshot_delta"]) {
        requireInteger(outbound[key], `facts.outbound.${key}`);
        if (outbound[key] !== 1) throw issue(`facts.outbound.${key}`, "must equal 1");
    }
    for (const key of [
        "replay_outbox_row_delta", "replay_attempt_row_delta", "replay_link_row_delta", "replay_provider_side_effect_delta",
        "conflict_outbox_row_delta", "conflict_attempt_row_delta", "conflict_link_row_delta", "conflict_provider_side_effect_delta",
    ]) requireZero(outbound[key], `facts.outbound.${key}`);
    if (outbound.cost_amount !== "0.01") throw issue("facts.outbound.cost_amount", "must equal 0.01");
    if (outbound.cost_currency !== "USD") throw issue("facts.outbound.cost_currency", "must equal USD");

    for (const key of ["whatsapp_state", "sms_state", "voice_state"]) if (facts.providers[key] !== "blocked") throw issue(`facts.providers.${key}`, "must be blocked");
    if (facts.providers.denial_reason !== "market_capability_missing") throw issue("facts.providers.denial_reason", "must equal market_capability_missing");
    requireZero(facts.providers.live_provider_calls, "facts.providers.live_provider_calls");
    requireZero(facts.providers.honduras_fallbacks, "facts.providers.honduras_fallbacks");
    requireInteger(facts.safety.foreign_sentinel_rows, "facts.safety.foreign_sentinel_rows", 1);
    requireTrue(facts.safety.foreign_tenant_attempted, "facts.safety.foreign_tenant_attempted");
    requireZero(facts.safety.foreign_tenant_mutations, "facts.safety.foreign_tenant_mutations");
    requireZero(facts.safety.subject_mutations_from_foreign_attempt, "facts.safety.subject_mutations_from_foreign_attempt");

    requireObject(facts.deltas.subject, "facts.deltas.subject");
    requireExactKeys(facts.deltas.subject, new Set(Object.keys(expectedDelta)), "facts.deltas.subject");
    for (const [key, expected] of Object.entries(expectedDelta)) {
        requireInteger(facts.deltas.subject[key], `facts.deltas.subject.${key}`);
        if (facts.deltas.subject[key] !== expected) throw issue(`facts.deltas.subject.${key}`, `must equal ${expected}`);
    }
    requireZero(facts.deltas.foreign_total, "facts.deltas.foreign_total");
    for (const key of ["payments", "subscription_payments", "webhook_logs", "outbox_messages"]) requireZero(facts.deltas[key], `facts.deltas.${key}`);
    for (const key of ["network_calls", "live_provider_calls", "payment_calls"]) requireZero(facts.external[key], `facts.external.${key}`);
    for (const key of Object.keys(facts.limitations)) requireFalse(facts.limitations[key], `facts.limitations.${key}`);
    if (!Array.isArray(facts.production_gaps) || JSON.stringify(facts.production_gaps) !== JSON.stringify(productionGaps)) throw issue("facts.production_gaps", "must equal the exact sorted production gaps");
    return true;
}

export function validateImplementationInventory(actual, expected = implementationInventory) {
    if (!Array.isArray(actual) || JSON.stringify(actual) !== JSON.stringify(expected)) throw new Error("IMPLEMENTATION_INVENTORY_INVALID: authorized five-file inventory changed.");
    return true;
}

export function parseArgs(argv) {
    const options = { php: null, report: null, strict: false, json: false, help: false };
    const seen = new Set();
    for (let index = 0; index < argv.length; index += 1) {
        const argument = argv[index];
        if (argument === "--help" || argument === "-h") {
            if (seen.has("help")) throw new Error("Repeated argument: --help");
            seen.add("help");
            options.help = true;
        } else if (argument === "--json") {
            if (seen.has("json")) throw new Error("Repeated argument: --json");
            seen.add("json");
            options.json = true;
        } else if (argument === "--strict") {
            if (seen.has("strict")) throw new Error("Repeated argument: --strict");
            seen.add("strict");
            options.strict = true;
        } else if (argument === "--php" || argument.startsWith("--php=")) {
            if (seen.has("php")) throw new Error("Repeated argument: --php");
            seen.add("php");
            options.php = argument === "--php" ? argv[++index] ?? null : argument.slice("--php=".length);
            if (!options.php || options.php.startsWith("--")) throw new Error("--php requires a value.");
        } else if (argument === "--report" || argument.startsWith("--report=")) {
            if (seen.has("report")) throw new Error("Repeated argument: --report");
            seen.add("report");
            options.report = argument === "--report" ? argv[++index] ?? null : argument.slice("--report=".length);
            if (!options.report || options.report.startsWith("--")) throw new Error("--report requires a value.");
        } else {
            throw new Error(`Unknown argument: ${argument}`);
        }
    }
    if (!options.help && (!options.php || !options.report)) throw new Error("--php and --report are required.");
    return options;
}

export function classifyResult({ facts, strict }) {
    validateCompositeFacts(facts);
    if (strict) return {
        status: "LOCALLY_VERIFIED", decision: "NO_GO", launch: "NO_GO", strict: true, code: "production_gaps_open", exit_code: 1,
    };
    return { status: "LOCALLY_VERIFIED", decision: "BOUNDED_LOCAL_ONLY", launch: "NO_GO", strict: false, exit_code: 0 };
}

function comparable(value) {
    return process.platform === "win32" ? value.toLowerCase() : value;
}

function assertNoLinkChain(path, label) {
    let cursor = resolve(path);
    while (true) {
        if (existsSync(cursor)) {
            const metadata = lstatSync(cursor);
            if (metadata.isSymbolicLink()) throw new Error(`${label} must not use a path alias.`);
            const canonical = realpathSync(cursor);
            if (comparable(canonical) !== comparable(resolve(cursor))) throw new Error(`${label} must not use a path alias.`);
        }
        const parent = dirname(cursor);
        if (parent === cursor) break;
        cursor = parent;
    }
}

function assertInside(root, target, label) {
    const rootReal = realpathSync(root);
    const targetResolved = resolve(target);
    const relativePath = relative(rootReal, targetResolved);
    if (relativePath.startsWith("..") || isAbsolute(relativePath)) throw new Error(`${label} escaped the owned temporary directory.`);
}

function pathEntryExists(path) {
    try {
        lstatSync(path);
        return true;
    } catch {
        return false;
    }
}

export function assertNewAbsoluteReport(reportPath) {
    if (typeof reportPath !== "string" || !isAbsolute(reportPath)) throw new Error("REPORT_INVALID: report path must be absolute.");
    const parent = dirname(reportPath);
    if (!existsSync(parent) || !lstatSync(parent).isDirectory()) throw new Error("REPORT_PARENT_INVALID: report parent is missing.");
    assertNoLinkChain(parent, "Report parent");
    const reportResolved = resolve(reportPath);
    const repoResolved = resolve(realpathSync(repositoryRoot));
    const fromRepository = relative(repoResolved, reportResolved);
    if (!fromRepository.startsWith("..") && !isAbsolute(fromRepository)) throw new Error("REPORT_REPOSITORY_OWNED: report must be outside the repository.");
    if (existsSync(reportPath) || pathEntryExists(reportPath)) throw new Error("REPORT_EXISTS: report path must be new and exclusive.");
}

export function assertNewOwnedDatabase(databasePath, tempDirectory) {
    if (typeof databasePath !== "string" || !isAbsolute(databasePath)) throw new Error("DATABASE_INVALID: database path must be absolute.");
    assertInside(tempDirectory, databasePath, "SQLite database");
    if (pathEntryExists(databasePath)) throw new Error("DATABASE_EXISTS: SQLite database path must be new and exclusive.");
    assertNoLinkChain(dirname(databasePath), "SQLite database parent");
}

export function assertPhpBinary(phpPath, { probe = spawnSync } = {}) {
    if (typeof phpPath !== "string" || !isAbsolute(phpPath)) throw new Error("PHP_INVALID: PHP path must be absolute.");
    if (!existsSync(phpPath) || !lstatSync(phpPath).isFile()) throw new Error("PHP_INVALID: PHP binary is missing or not a regular file.");
    assertNoLinkChain(phpPath, "PHP binary");
    const version = probe(phpPath, ["-r", "echo PHP_VERSION_ID;"], { cwd: repositoryRoot, encoding: "utf8", shell: false, maxBuffer: 1024 * 1024 });
    if (version.error || version.status !== 0) throw new Error("PHP_INVALID: PHP version probe failed.");
    const versionId = Number.parseInt(String(version.stdout).trim(), 10);
    if (!Number.isSafeInteger(versionId) || versionId < 80400) throw new Error("PHP_INVALID: PHP 8.4 or newer is required.");
    return versionId;
}

function exclusiveWrite(path, value) {
    const descriptor = openSync(path, "wx");
    try {
        writeFileSync(descriptor, `${JSON.stringify(value, null, 2)}\n`, { encoding: "utf8" });
    } finally {
        closeSync(descriptor);
    }
}

function sanitizeOutput(output, temporaryDirectory) {
    return String(output).replaceAll(temporaryDirectory, "<owned-temp>").replaceAll(repositoryRoot, "<repository>");
}

function readFacts(path) {
    const metadata = lstatSync(path);
    if (!metadata.isFile() || metadata.isSymbolicLink()) throw new Error("PHP_FACTS_INVALID: child report must be a regular non-link file.");
    const stats = readFileSync(path);
    if (stats.byteLength > 2 * 1024 * 1024) throw new Error("PHP_FACTS_INVALID: child report exceeds bounded size.");
    let facts;
    try { facts = JSON.parse(stats.toString("utf8")); } catch { throw new Error("PHP_FACTS_INVALID: child report is not valid JSON."); }
    validateCompositeFacts(facts);
    return facts;
}

export function runDedicatedPest({ phpPath, databasePath, phpFactsPath, cachePath, tempDirectory, spawn = spawnSync }) {
    if (pathEntryExists(cachePath)) throw new Error("APP_CONFIG_CACHE_INVALID: cache path exists before child.");
    if (pathEntryExists(phpFactsPath)) throw new Error("PHP_FACTS_EXISTS: child report path must be new.");
    assertInside(tempDirectory, databasePath, "SQLite database");
    assertInside(tempDirectory, phpFactsPath, "PHP report");
    assertInside(tempDirectory, cachePath, "APP_CONFIG_CACHE");
    const childEnvironment = {
        ...process.env,
        APP_ENV: "testing",
        APP_KEY: `base64:${randomBytes(32).toString("base64")}`,
        APP_CONFIG_CACHE: cachePath,
        CACHE_STORE: "array",
        SESSION_DRIVER: "array",
        DB_CONNECTION: "sqlite",
        DB_DATABASE: databasePath,
        DOVRA_PROGRAM07_COMPOSITE_ALLOW: "true",
        DOVRA_PROGRAM07_COMPOSITE_DATABASE: databasePath,
        DOVRA_PROGRAM07_COMPOSITE_REPORT: phpFactsPath,
    };
    const result = spawn(phpPath, ["vendor/bin/pest", "-c", "phpunit.sqlite.xml", dedicatedPestPath, "--colors=never"], {
        cwd: repositoryRoot,
        env: childEnvironment,
        encoding: "utf8",
        shell: false,
        maxBuffer: 20 * 1024 * 1024,
    });
    if (pathEntryExists(cachePath)) throw new Error("APP_CONFIG_CACHE_CREATED: child wrote the owned cache path.");
    const rawOutput = `${result.stdout ?? ""}\n${result.stderr ?? ""}`;
    if (rawOutput.length > 20 * 1024 * 1024) throw new Error("DEDICATED_PEST_OUTPUT_INVALID: child output exceeds bounded size.");
    const output = sanitizeOutput(rawOutput, tempDirectory);
    if (result.error || result.status !== 0) throw new Error(`DEDICATED_PEST_FAILED: exit ${result.status}\n${output}`);
    if (!/\b1\s+passed\b/u.test(output)
        || /\b(?:[1-9]\d*)\s+(?:failed|skipped|incomplete|risky)\b/iu.test(output)) {
        throw new Error(`DEDICATED_PEST_COUNT_INVALID: expected one passing test.\n${output}`);
    }
    if (!pathEntryExists(phpFactsPath)) throw new Error("PHP_FACTS_MISSING: child did not create report.");
    return { result, facts: readFacts(phpFactsPath) };
}

export function certifyComposite({ php, report, strict = false, runPest = runDedicatedPest, probe = spawnSync } = {}) {
    if (resolve(repositoryRoot) !== resolve(realpathSync(repositoryRoot))) throw new Error("REPOSITORY_ROOT_INVALID: repository root is an alias.");
    assertNoLinkChain(repositoryRoot, "Repository root");
    if (typeof report !== "string" || !isAbsolute(report)) throw new Error("REPORT_INVALID: report path must be absolute.");
    const reportPath = resolve(report);
    assertNewAbsoluteReport(reportPath);
    assertPhpBinary(php, { probe });
    const tempDirectory = mkdtempSync(join(tmpdir(), "dovra-program07-composite-"));
    try {
        try { chmodSync(tempDirectory, 0o700); } catch { /* host may not expose mode bits */ }
        assertNoLinkChain(tempDirectory, "Composite temp directory");
        const databasePath = join(tempDirectory, "scenario.sqlite");
        const phpFactsPath = join(tempDirectory, "php-facts.json");
        const cachePath = join(tempDirectory, "config-cache.php");
        assertNewOwnedDatabase(databasePath, tempDirectory);
        const descriptor = openSync(databasePath, "wx");
        closeSync(descriptor);
        assertNoLinkChain(databasePath, "SQLite database");
        if (reportPath === resolve(databasePath) || reportPath === resolve(phpFactsPath)) throw new Error("REPORT_DATABASE_ALIAS: report and child paths must differ.");
        if (pathEntryExists(cachePath)) throw new Error("APP_CONFIG_CACHE_INVALID: cache path already exists.");
        const { facts } = runPest({ phpPath: php, databasePath, phpFactsPath, cachePath, tempDirectory });
        const classification = classifyResult({ facts, strict });
        const persistedReport = { ...facts, strict: classification.strict };
        if (classification.decision !== facts.decision) persistedReport.decision = classification.decision;
        if (classification.code) persistedReport.code = classification.code;
        validateCompositeFacts(persistedReport);
        exclusiveWrite(reportPath, persistedReport);
        if (!lstatSync(reportPath).isFile() || lstatSync(reportPath).isSymbolicLink()) throw new Error("REPORT_WRITE_INVALID: user report must be regular and non-link.");
        return { ...persistedReport, exit_code: classification.exit_code };
    } finally {
        const databasePath = join(tempDirectory, "scenario.sqlite");
        const phpFactsPath = join(tempDirectory, "php-facts.json");
        const cachePath = join(tempDirectory, "config-cache.php");
        let cleanupFailure = null;
        try {
            assertInside(tempDirectory, databasePath, "Composite cleanup");
            assertInside(tempDirectory, phpFactsPath, "Composite cleanup");
            assertInside(tempDirectory, cachePath, "Composite cleanup");
        } catch (error) {
            cleanupFailure = error;
        }
        const cacheCreated = pathEntryExists(cachePath);
        if (cleanupFailure === null) {
            try {
                rmSync(tempDirectory, { recursive: true, force: true });
            } catch (error) {
                cleanupFailure = error;
            }
        }
        if (cacheCreated) throw new Error("APP_CONFIG_CACHE_CREATED: cache path must remain absent before cleanup.");
        if (cleanupFailure !== null) throw cleanupFailure;
    }
}

function printUsage() {
    console.log("Usage: node scripts/qa/certify-dovra-program07-composite.mjs --php <absolute-php84> --report <absolute-new-json> [--strict] [--json]");
    console.log("Runs the guarded Program 07 composite proof inside one disposable SQLite boundary.");
}

if (process.argv[1] && resolve(process.argv[1]) === resolve(fileURLToPath(import.meta.url))) {
    let options;
    try {
        options = parseArgs(process.argv.slice(2));
        if (options.help) {
            printUsage();
            process.exit(0);
        }
        const report = certifyComposite(options);
        if (options.json) console.log(JSON.stringify(report, null, 2));
        else {
            console.log(`status: ${report.status}`);
            console.log(`decision: ${report.decision}`);
            console.log(`launch: ${report.launch}`);
            if (report.code) console.log(`code: ${report.code}`);
            console.log(`report: ${options.report}`);
        }
        process.exitCode = report.exit_code;
    } catch (error) {
        if (process.argv.includes("--json")) console.log(JSON.stringify({ status: "FAIL", code: "CLI_INVALID", detail: error instanceof Error ? error.message : String(error) }, null, 2));
        else console.error(error instanceof Error ? error.message : String(error));
        process.exitCode = 1;
    }
}
