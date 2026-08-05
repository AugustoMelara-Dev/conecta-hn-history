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
const dedicatedPestPath = "tests/Feature/Automation/Program06CompositeCertificationTest.php";
export const implementationInventory = Object.freeze([
    "tests/Support/Automation/Program06CompositeCertificateFixture.php",
    "tests/Support/Automation/Program06CompositeCertificateReport.php",
    "tests/Feature/Automation/Program06CompositeCertificationTest.php",
    "scripts/qa/certify-dovra-program06-composite.mjs",
    "tests/qa/dovra-program06-composite-certifier.test.mjs",
]);
export const repositoryWriteAllowlist = Object.freeze([]);
const expectedSequence = Object.freeze([
    "guard",
    "definition",
    "policy",
    "action",
    "runtime",
    "history",
    "contracts",
    "outcome",
    "limitations",
]);
const expectedProductionGaps = Object.freeze([
    "PRODUCTION_GAP_AGENT_CONFIRMATION_ACTOR_BINDING",
    "PRODUCTION_GAP_API_MCP_ROUTE_BINDING",
    "PRODUCTION_GAP_CANONICAL_WRITER_ROUTING",
    "PRODUCTION_GAP_DURABLE_TOOL_OUTBOX",
    "PRODUCTION_GAP_RLS_RUNTIME_CERTIFICATION",
]);
const expectedExternal = Object.freeze([
    "clean_exact_artifact",
    "independent_security_review",
    "live_channel_and_integration_providers",
    "mysql_portability",
    "postgresql_restricted_role_rls",
    "production_operations",
    "staging_runtime",
]);
const topLevelKeys = new Set([
    "schema",
    "program",
    "status",
    "decision",
    "launch",
    "scope",
    "sequence",
    "definition",
    "policy",
    "action",
    "runtime",
    "history",
    "contracts",
    "outcome",
    "safety",
    "limitations",
    "production_gaps",
    "external",
    "strict",
    "code",
]);
const requiredTopLevelKeys = new Set([...topLevelKeys].filter((key) => key !== "strict" && key !== "code"));
const stageKeys = Object.freeze({
    definition: new Set(["published", "version", "immutable"]),
    policy: new Set(["deny_by_default", "denied_side_effects", "read_action_evaluated", "tool_confirmation_actor_binding"]),
    action: new Set(["executed_once", "audit_count", "run_count", "replay_rejected", "replay_mutations"]),
    runtime: new Set(["wait_persisted", "early_resume_read_only", "authorized_resume_completed", "completed_start_replayed", "tenant_isolation_non_vacuous", "actor_isolation_non_vacuous"]),
    history: new Set(["retry_attempts", "retry_keys_distinct", "provider_tool_key_stable", "compensation_attempts", "compensation_completed", "terminal_replay_mutations"]),
    contracts: new Set(["default_fail_closed", "missing_actor_fail_closed", "invalid_signature_side_effects", "synthetic_api_replay_rejected", "synthetic_mcp_replay_rejected", "synthetic_webhook_replay_rejected"]),
    outcome: new Set(["recorded_once", "audit_linked", "verified_reference_linked", "sanitized", "attribution_event_count", "foreign_audit_rejected"]),
    safety: new Set(["foreign_tenant_sentinel_rows", "same_tenant_actor_distinct", "foreign_tenant_attempted", "foreign_actor_attempted", "foreign_tenant_mutations", "foreign_actor_mutations", "provider_calls", "network_calls", "payment_mutations", "billing_mutations", "credit_mutations", "channel_delivery_mutations", "canonical_business_mutations", "config_restored"]),
    limitations: new Set(["workforce_actor_bound_confirmation_proven", "canonical_writer_routing_proven", "durable_tool_outbox_proven", "api_mcp_route_binding_proven", "postgresql_rls_runtime_proven"]),
});

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

function scanForLeaks(value, path = "facts") {
    if (Array.isArray(value)) {
        for (const [index, item] of value.entries()) scanForLeaks(item, `${path}[${index}]`);
        return;
    }
    if (value !== null && typeof value === "object") {
        for (const [key, item] of Object.entries(value)) {
            if (/(?:uuid|tenant_(?:id|uuid|path)|(?:^|_)actor(?:_id|_uuid)$|database|absolute|secret|prompt|message|timestamp|created_at|updated_at|email|payload|token|marker|raw)/iu.test(key)) {
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

export function validateCompositeFacts(facts) {
    requireObject(facts, "facts");
    requireExactKeys(facts, topLevelKeys, "facts", requiredTopLevelKeys);
    scanForLeaks(facts);
    if (facts.schema !== 1) throw issue("facts.schema", "must equal 1");
    if (facts.program !== "06") throw issue("facts.program", "must equal 06");
    if (facts.status !== "LOCALLY_VERIFIED") throw issue("facts.status", "must be LOCALLY_VERIFIED");
    const strictReport = Object.hasOwn(facts, "strict") ? facts.strict : false;
    requireBoolean(strictReport, "facts.strict");
    if (strictReport) {
        if (facts.decision !== "NO_GO") throw issue("facts.decision", "must be NO_GO for strict reports");
        if (facts.code !== "production_gaps_open") throw issue("facts.code", "must be production_gaps_open for strict reports");
    } else {
        if (facts.decision !== "BOUNDED_LOCAL_ONLY") throw issue("facts.decision", "must be BOUNDED_LOCAL_ONLY");
        if (Object.hasOwn(facts, "code")) throw issue("facts.code", "must be absent for normal reports");
    }
    if (facts.launch !== "NO_GO") throw issue("facts.launch", "must be NO_GO");
    if (facts.scope !== "disposable_sqlite") throw issue("facts.scope", "must be disposable_sqlite");
    if (!Array.isArray(facts.sequence) || JSON.stringify(facts.sequence) !== JSON.stringify(expectedSequence)) throw issue("facts.sequence", "must match the frozen sequence exactly");
    for (const stage of Object.keys(stageKeys)) {
        requireObject(facts[stage], `facts.${stage}`);
        requireExactKeys(facts[stage], stageKeys[stage], `facts.${stage}`);
    }

    const definition = facts.definition;
    requireTrue(definition.published, "facts.definition.published");
    if (definition.version !== "v1") throw issue("facts.definition.version", "must equal v1");
    requireTrue(definition.immutable, "facts.definition.immutable");

    const policy = facts.policy;
    requireTrue(policy.deny_by_default, "facts.policy.deny_by_default");
    requireZero(policy.denied_side_effects, "facts.policy.denied_side_effects");
    requireTrue(policy.read_action_evaluated, "facts.policy.read_action_evaluated");
    requireTrue(policy.tool_confirmation_actor_binding, "facts.policy.tool_confirmation_actor_binding");

    const action = facts.action;
    requireTrue(action.executed_once, "facts.action.executed_once");
    requireInteger(action.audit_count, "facts.action.audit_count", 1);
    if (action.audit_count !== 1) throw issue("facts.action.audit_count", "must equal 1");
    requireInteger(action.run_count, "facts.action.run_count", 1);
    if (action.run_count !== 1) throw issue("facts.action.run_count", "must equal 1");
    requireTrue(action.replay_rejected, "facts.action.replay_rejected");
    requireZero(action.replay_mutations, "facts.action.replay_mutations");

    for (const key of ["wait_persisted", "early_resume_read_only", "authorized_resume_completed", "completed_start_replayed", "tenant_isolation_non_vacuous", "actor_isolation_non_vacuous"]) {
        requireTrue(facts.runtime[key], `facts.runtime.${key}`);
    }

    const history = facts.history;
    requireInteger(history.retry_attempts, "facts.history.retry_attempts", 2);
    if (history.retry_attempts !== 2) throw issue("facts.history.retry_attempts", "must equal 2");
    requireTrue(history.retry_keys_distinct, "facts.history.retry_keys_distinct");
    requireTrue(history.provider_tool_key_stable, "facts.history.provider_tool_key_stable");
    requireInteger(history.compensation_attempts, "facts.history.compensation_attempts", 1);
    if (history.compensation_attempts !== 1) throw issue("facts.history.compensation_attempts", "must equal 1");
    requireTrue(history.compensation_completed, "facts.history.compensation_completed");
    requireZero(history.terminal_replay_mutations, "facts.history.terminal_replay_mutations");

    for (const key of ["default_fail_closed", "missing_actor_fail_closed", "synthetic_api_replay_rejected", "synthetic_mcp_replay_rejected", "synthetic_webhook_replay_rejected"]) {
        requireTrue(facts.contracts[key], `facts.contracts.${key}`);
    }
    requireZero(facts.contracts.invalid_signature_side_effects, "facts.contracts.invalid_signature_side_effects");

    for (const key of ["recorded_once", "audit_linked", "verified_reference_linked", "sanitized", "foreign_audit_rejected"]) {
        requireTrue(facts.outcome[key], `facts.outcome.${key}`);
    }
    requireInteger(facts.outcome.attribution_event_count, "facts.outcome.attribution_event_count", 1);
    if (facts.outcome.attribution_event_count !== 1) throw issue("facts.outcome.attribution_event_count", "must equal 1");

    const safety = facts.safety;
    requireInteger(safety.foreign_tenant_sentinel_rows, "facts.safety.foreign_tenant_sentinel_rows", 1);
    for (const key of ["same_tenant_actor_distinct", "foreign_tenant_attempted", "foreign_actor_attempted"]) {
        requireTrue(safety[key], `facts.safety.${key}`);
    }
    for (const key of ["foreign_tenant_mutations", "foreign_actor_mutations", "provider_calls", "network_calls", "payment_mutations", "billing_mutations", "credit_mutations", "channel_delivery_mutations", "canonical_business_mutations"]) {
        requireZero(safety[key], `facts.safety.${key}`);
    }
    requireTrue(safety.config_restored, "facts.safety.config_restored");

    for (const key of ["workforce_actor_bound_confirmation_proven", "canonical_writer_routing_proven", "durable_tool_outbox_proven", "api_mcp_route_binding_proven", "postgresql_rls_runtime_proven"]) {
        requireFalse(facts.limitations[key], `facts.limitations.${key}`);
    }
    if (!Array.isArray(facts.production_gaps) || JSON.stringify(facts.production_gaps) !== JSON.stringify(expectedProductionGaps)) throw issue("facts.production_gaps", "must equal the exact sorted production gaps");
    if (!Array.isArray(facts.external) || JSON.stringify(facts.external) !== JSON.stringify(expectedExternal)) throw issue("facts.external", "must equal the exact sorted external boundary");

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
        } else if (argument === "--report" || argument.startsWith("--report=")) {
            if (seen.has("report")) throw new Error("Repeated argument: --report");
            seen.add("report");
            options.report = argument === "--report" ? argv[++index] ?? null : argument.slice("--report=".length);
        } else {
            throw new Error(`Unknown argument: ${argument}`);
        }
    }
    if (!options.help && (!options.php || !options.report)) throw new Error("--php and --report are required.");
    return options;
}

export function classifyResult({ facts, strict }) {
    validateCompositeFacts(facts);
    if (strict) {
        return {
            status: "LOCALLY_VERIFIED",
            decision: "NO_GO",
            launch: "NO_GO",
            strict: true,
            code: "production_gaps_open",
            exit_code: 1,
        };
    }
    return {
        status: "LOCALLY_VERIFIED",
        decision: "BOUNDED_LOCAL_ONLY",
        launch: "NO_GO",
        strict: false,
        exit_code: 0,
    };
}

function assertNoLinkChain(path, label) {
    let cursor = resolve(path);
    while (true) {
        if (existsSync(cursor)) {
            const metadata = lstatSync(cursor);
            if (metadata.isSymbolicLink()) throw new Error(`${label} must not use a path alias.`);
            const canonical = realpathSync(cursor);
            const comparable = (value) => process.platform === "win32" ? value.toLowerCase() : value;
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

function assertNewAbsoluteReport(reportPath) {
    if (!isAbsolute(reportPath)) throw new Error("REPORT_INVALID: report path must be absolute.");
    const parent = dirname(reportPath);
    if (!existsSync(parent) || !lstatSync(parent).isDirectory()) throw new Error("REPORT_PARENT_INVALID: report parent is missing.");
    assertNoLinkChain(parent, "Report parent");
    const reportResolved = resolve(reportPath);
    const repoResolved = resolve(realpathSync(repositoryRoot));
    const fromRepository = relative(repoResolved, reportResolved);
    if (!fromRepository.startsWith("..") && !isAbsolute(fromRepository)) throw new Error("REPORT_REPOSITORY_OWNED: report must be outside the repository.");
    if (existsSync(reportPath)) throw new Error("REPORT_EXISTS: report path must be new and exclusive.");
}

function assertPhpBinary(phpPath) {
    if (typeof phpPath !== "string" || !isAbsolute(phpPath)) throw new Error("PHP_INVALID: PHP path must be absolute.");
    if (!existsSync(phpPath) || !lstatSync(phpPath).isFile()) throw new Error("PHP_INVALID: PHP binary is missing or not a regular file.");
    assertNoLinkChain(phpPath, "PHP binary");
    const version = spawnSync(phpPath, ["-r", "echo PHP_VERSION_ID;"], { cwd: repositoryRoot, encoding: "utf8", shell: false });
    if (version.status !== 0) throw new Error("PHP_INVALID: PHP version probe failed.");
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

function readFacts(path) {
    const facts = JSON.parse(readFileSync(path, "utf8"));
    validateCompositeFacts(facts);
    return facts;
}

function runDedicatedPest({ phpPath, databasePath, phpFactsPath, cachePath, tempDirectory }) {
    if (existsSync(cachePath)) throw new Error("APP_CONFIG_CACHE_INVALID: cache path exists before child.");
    if (existsSync(phpFactsPath)) throw new Error("PHP_FACTS_EXISTS: child fact path must be new.");
    assertInside(tempDirectory, databasePath, "SQLite database");
    assertInside(tempDirectory, phpFactsPath, "PHP fact report");
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
        DOVRA_PROGRAM06_COMPOSITE_ALLOW: "true",
        DOVRA_PROGRAM06_COMPOSITE_DATABASE: databasePath,
        DOVRA_PROGRAM06_COMPOSITE_FACTS: phpFactsPath,
    };
    const result = spawnSync(phpPath, ["vendor/bin/pest", "-c", "phpunit.sqlite.xml", dedicatedPestPath, "--colors=never"], {
        cwd: repositoryRoot,
        env: childEnvironment,
        encoding: "utf8",
        shell: false,
        maxBuffer: 20 * 1024 * 1024,
    });
    if (existsSync(cachePath)) throw new Error("APP_CONFIG_CACHE_CREATED: child wrote the owned cache path.");
    const output = `${result.stdout}\n${result.stderr}`;
    if (result.status !== 0) throw new Error(`DEDICATED_PEST_FAILED: exit ${result.status}\n${output}`);
    if (!/\b1\s+passed\b/u.test(output)
        || /\b(?:[1-9]\d*)\s+(?:failed|skipped|incomplete|risky)\b/iu.test(output)) {
        throw new Error(`DEDICATED_PEST_COUNT_INVALID: expected one passing test.\n${output}`);
    }
    if (!existsSync(phpFactsPath)) throw new Error("PHP_FACTS_MISSING: child did not create facts.");
    return { result, facts: readFacts(phpFactsPath) };
}

export function certifyComposite({ php, report, strict = false } = {}) {
    if (resolve(repositoryRoot) !== resolve(realpathSync(repositoryRoot))) throw new Error("REPOSITORY_ROOT_INVALID: repository root is an alias.");
    assertNoLinkChain(repositoryRoot, "Repository root");
    if (typeof report !== "string" || !isAbsolute(report)) throw new Error("REPORT_INVALID: report path must be absolute.");
    const reportPath = resolve(report);
    assertNewAbsoluteReport(reportPath);
    assertPhpBinary(php);
    const tempDirectory = mkdtempSync(join(tmpdir(), "dovra-program06-composite-"));
    try {
        try { chmodSync(tempDirectory, 0o700); } catch { /* host may not expose mode bits */ }
        assertNoLinkChain(tempDirectory, "Composite temp directory");
        const databasePath = join(tempDirectory, "scenario.sqlite");
        const phpFactsPath = join(tempDirectory, "php-facts.json");
        const cachePath = join(tempDirectory, "config-cache.php");
        const descriptor = openSync(databasePath, "wx");
        closeSync(descriptor);
        assertNoLinkChain(databasePath, "SQLite database");
        if (reportPath === resolve(databasePath)) throw new Error("REPORT_DATABASE_ALIAS: report and SQLite database must differ.");
        if (existsSync(cachePath)) throw new Error("APP_CONFIG_CACHE_INVALID: cache path already exists.");
        const { facts } = runDedicatedPest({ phpPath: php, databasePath, phpFactsPath, cachePath, tempDirectory });
        const classification = classifyResult({ facts, strict });
        const persistedReport = { ...facts, strict: classification.strict };
        if (classification.decision !== facts.decision) persistedReport.decision = classification.decision;
        if (classification.code) persistedReport.code = classification.code;
        exclusiveWrite(reportPath, persistedReport);
        return { ...persistedReport, exit_code: classification.exit_code };
    } finally {
        const databasePath = join(tempDirectory, "scenario.sqlite");
        const phpFactsPath = join(tempDirectory, "php-facts.json");
        const cachePath = join(tempDirectory, "config-cache.php");
        for (const path of [databasePath, phpFactsPath, cachePath]) assertInside(tempDirectory, path, "Composite cleanup");
        const cacheAbsentBeforeCleanup = existsSync(cachePath) === false;
        if (!cacheAbsentBeforeCleanup) throw new Error("APP_CONFIG_CACHE_CREATED: cache path must remain absent before cleanup.");
        rmSync(tempDirectory, { recursive: true, force: true });
    }
}

function printUsage() {
    console.log("Usage: node scripts/qa/certify-dovra-program06-composite.mjs --php <absolute-php84> --report <absolute-new-json> [--strict] [--json]");
    console.log("Runs the guarded Program 06 composite proof inside one disposable SQLite boundary.");
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
