import { randomBytes } from "node:crypto";
import { spawnSync } from "node:child_process";
import {
    closeSync,
    existsSync,
    fstatSync,
    lstatSync,
    mkdirSync,
    mkdtempSync,
    openSync,
    readFileSync,
    realpathSync,
    rmSync,
    statSync,
    unlinkSync,
    writeFileSync,
} from "node:fs";
import { dirname, isAbsolute, join, relative, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { tmpdir } from "node:os";

const repositoryRoot = resolve(import.meta.dirname, "../..");
const dedicatedPestPath = "tests/Feature/AI/Program05CompositeCertificationTest.php";
const expectedSequence = Object.freeze(["expand", "backfill", "validate", "shadow", "canary", "rollback", "contract"]);
const expectedExternal = Object.freeze([
    "clean_exact_artifact",
    "independent_security_review",
    "live_ai_and_channel_providers",
    "mysql_portability",
    "postgresql_restricted_role_rls",
    "production_operations",
    "staging_runtime",
]);
const topLevelKeys = new Set(["schema", "status", "decision", "scope", "sequence", "expand", "backfill", "validate", "shadow", "canary", "rollback", "contract", "safety", "external", "strict"]);
const stageKeys = Object.freeze({
    expand: new Set(["phase", "validated", "has_more", "gaps", "conflicts", "contract_status"]),
    backfill: new Set(["batch_count", "resumed", "idempotent", "cumulative_gaps", "cumulative_conflicts", "cumulative_legacy_unresolved", "source_snapshots_identical", "no_duplicate_execution_processing", "canonical_lineage_count", "retention_classified_count"]),
    validate: new Set(["batch_count", "resumed", "validated", "read_only", "cumulative_gaps", "cumulative_conflicts", "cumulative_legacy_unresolved"]),
    shadow: new Set(["read_only", "selection_matches_legacy_and_canonical"]),
    canary: new Set(["rollout_percentage", "canary_selects_candidate", "control_selects_root", "sticky_paths_idempotent", "open_candidate_activation_count"]),
    rollback: new Set(["new_conversations_select_root", "control_pin_resolves_root", "candidate_pin_fails_closed", "rollback_rollout_percentage"]),
    contract: new Set(["validated", "contract_status", "legacy_preserved"]),
    safety: new Set(["provider_calls", "handoffs", "usage_rows", "billing_rows", "payment_rows", "credit_rows", "foreign_tenant_mutations"]),
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

function requireExactKeys(value, allowed, path) {
    for (const key of Object.keys(value)) {
        if (!allowed.has(key)) throw issue(`${path}.${key}`, "unknown field");
    }
}

function scanForLeaks(value, path = "facts") {
    if (Array.isArray(value)) {
        for (const [index, item] of value.entries()) scanForLeaks(item, `${path}[${index}]`);
        return;
    }
    if (value !== null && typeof value === "object") {
        for (const [key, item] of Object.entries(value)) {
            if (/(?:uuid|tenant_(?:id|uuid|path)|actor(?:_id)?|database|absolute|secret|prompt|message|timestamp|created_at|updated_at|environment|raw)/iu.test(key)) {
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
        || /(?:system\s+prompt|raw\s+prompt|ciphertext|secret\s+reference)/iu.test(value)) {
        throw issue(path, "runtime or sensitive value is forbidden");
    }
}

export function validateCompositeFacts(facts) {
    requireObject(facts, "facts");
    requireExactKeys(facts, topLevelKeys, "facts");
    scanForLeaks(facts);
    if (facts.schema !== 1) throw issue("facts.schema", "must equal 1");
    if (facts.status !== "LOCALLY_VERIFIED") throw issue("facts.status", "must be LOCALLY_VERIFIED");
    if (facts.decision !== "NO_CONTRACT") throw issue("facts.decision", "must be NO_CONTRACT");
    if (facts.scope !== "disposable_sqlite") throw issue("facts.scope", "must be disposable_sqlite");
    if (Object.hasOwn(facts, "strict") && facts.strict !== true) throw issue("facts.strict", "must be true when present");
    if (!Array.isArray(facts.sequence) || JSON.stringify(facts.sequence) !== JSON.stringify(expectedSequence)) throw issue("facts.sequence", "must match the frozen sequence exactly");
    for (const stage of ["expand", "backfill", "validate", "shadow", "canary", "rollback", "contract", "safety"]) {
        requireObject(facts[stage], `facts.${stage}`);
        requireExactKeys(facts[stage], stageKeys[stage], `facts.${stage}`);
    }

    const expand = facts.expand;
    if (expand.phase !== "expand") throw issue("facts.expand.phase", "must equal expand");
    requireBoolean(expand.validated, "facts.expand.validated");
    if (expand.validated !== true) throw issue("facts.expand.validated", "must be true");
    requireBoolean(expand.has_more, "facts.expand.has_more");
    if (expand.has_more !== false) throw issue("facts.expand.has_more", "must be false");
    for (const key of ["gaps", "conflicts"]) {
        requireInteger(expand[key], `facts.expand.${key}`);
        if (expand[key] !== 0) throw issue(`facts.expand.${key}`, "must be zero");
    }
    if (expand.contract_status !== "expand_ready") throw issue("facts.expand.contract_status", "must equal expand_ready");

    const backfill = facts.backfill;
    requireInteger(backfill.batch_count, "facts.backfill.batch_count", 3);
    for (const key of ["resumed", "idempotent", "source_snapshots_identical", "no_duplicate_execution_processing"]) {
        requireBoolean(backfill[key], `facts.backfill.${key}`);
        if (backfill[key] !== true) throw issue(`facts.backfill.${key}`, "must be true");
    }
    for (const key of ["cumulative_gaps", "cumulative_conflicts", "cumulative_legacy_unresolved"]) {
        requireInteger(backfill[key], `facts.backfill.${key}`);
        if (backfill[key] !== 0) throw issue(`facts.backfill.${key}`, "must be zero");
    }
    requireInteger(backfill.canonical_lineage_count, "facts.backfill.canonical_lineage_count", 5);
    requireInteger(backfill.retention_classified_count, "facts.backfill.retention_classified_count", 5);

    const validate = facts.validate;
    requireInteger(validate.batch_count, "facts.validate.batch_count", 3);
    for (const key of ["resumed", "validated", "read_only"]) {
        requireBoolean(validate[key], `facts.validate.${key}`);
        if (validate[key] !== true) throw issue(`facts.validate.${key}`, "must be true");
    }
    for (const key of ["cumulative_gaps", "cumulative_conflicts", "cumulative_legacy_unresolved"]) {
        requireInteger(validate[key], `facts.validate.${key}`);
        if (validate[key] !== 0) throw issue(`facts.validate.${key}`, "must be zero");
    }

    for (const key of ["read_only", "selection_matches_legacy_and_canonical"]) {
        requireBoolean(facts.shadow[key], `facts.shadow.${key}`);
        if (facts.shadow[key] !== true) throw issue(`facts.shadow.${key}`, "must be true");
    }
    if (facts.canary.rollout_percentage !== 25) throw issue("facts.canary.rollout_percentage", "must equal 25");
    requireInteger(facts.canary.open_candidate_activation_count, "facts.canary.open_candidate_activation_count", 1);
    if (facts.canary.open_candidate_activation_count !== 1) throw issue("facts.canary.open_candidate_activation_count", "must equal 1");
    for (const key of ["canary_selects_candidate", "control_selects_root", "sticky_paths_idempotent"]) {
        requireBoolean(facts.canary[key], `facts.canary.${key}`);
        if (facts.canary[key] !== true) throw issue(`facts.canary.${key}`, "must be true");
    }
    for (const key of ["new_conversations_select_root", "control_pin_resolves_root", "candidate_pin_fails_closed"]) {
        requireBoolean(facts.rollback[key], `facts.rollback.${key}`);
        if (facts.rollback[key] !== true) throw issue(`facts.rollback.${key}`, "must be true");
    }
    if (facts.rollback.rollback_rollout_percentage !== 100) throw issue("facts.rollback.rollback_rollout_percentage", "must equal 100");
    requireBoolean(facts.contract.validated, "facts.contract.validated");
    if (facts.contract.validated !== false) throw issue("facts.contract.validated", "must be false");
    if (facts.contract.contract_status !== "deferred_until_consumers_proven") throw issue("facts.contract.contract_status", "must remain deferred");
    requireBoolean(facts.contract.legacy_preserved, "facts.contract.legacy_preserved");
    if (facts.contract.legacy_preserved !== true) throw issue("facts.contract.legacy_preserved", "must be true");
    for (const key of stageKeys.safety) {
        requireInteger(facts.safety[key], `facts.safety.${key}`);
        if (facts.safety[key] !== 0) throw issue(`facts.safety.${key}`, "must be zero");
    }
    if (!Array.isArray(facts.external) || JSON.stringify(facts.external) !== JSON.stringify(expectedExternal)) throw issue("facts.external", "must equal the sorted external boundary");

    return true;
}

export function parseArgs(argv) {
    const options = { php: null, report: null, strict: false, json: false, help: false };
    for (let index = 0; index < argv.length; index += 1) {
        const argument = argv[index];
        if (argument === "--json") options.json = true;
        else if (argument === "--strict") options.strict = true;
        else if (argument === "--help" || argument === "-h") options.help = true;
        else if (argument === "--php") options.php = argv[++index] ?? null;
        else if (argument.startsWith("--php=")) options.php = argument.slice("--php=".length);
        else if (argument === "--report") options.report = argv[++index] ?? null;
        else if (argument.startsWith("--report=")) options.report = argument.slice("--report=".length);
        else throw new Error(`Unknown argument: ${argument}`);
    }
    if (!options.help && (!options.php || !options.report)) throw new Error("--php and --report are required.");
    return options;
}

export function classifyResult({ facts, strict }) {
    validateCompositeFacts(facts);
    if (strict) return { status: "LOCALLY_VERIFIED", decision: "NO_CONTRACT", launch: "NO_GO", code: "contract_deferred", exit_code: 1 };
    return { status: "LOCALLY_VERIFIED", decision: "NO_CONTRACT", launch: "NO_GO", exit_code: 0 };
}

function assertNoLinkChain(path, label) {
    const absolute = resolve(path);
    let cursor = absolute;
    while (true) {
        if (existsSync(cursor)) {
            const metadata = lstatSync(cursor);
            if (metadata.isSymbolicLink()) throw new Error(`${label} must not use a symlink or junction alias.`);
            // A Windows junction/reparse point can report as a directory
            // rather than a POSIX symlink. Comparing the canonical identity
            // catches that alias whenever the host exposes it.
            const canonical = realpathSync(cursor);
            const comparable = (value) => process.platform === "win32" ? value.toLowerCase() : value;
            if (comparable(canonical) !== comparable(resolve(cursor))) throw new Error(`${label} must not use a reparse-point or canonical-path alias.`);
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
    if (relativePath.startsWith("..") || isAbsolute(relativePath)) throw new Error(`${label} escaped the disposable directory.`);
}

function assertNewAbsoluteReport(reportPath) {
    if (!isAbsolute(reportPath)) throw new Error("REPORT_INVALID: report path must be absolute.");
    const parent = dirname(reportPath);
    if (!existsSync(parent) || !lstatSync(parent).isDirectory()) throw new Error("REPORT_PARENT_INVALID: report parent directory is missing.");
    assertNoLinkChain(parent, "Report parent");
    if (existsSync(reportPath)) throw new Error("REPORT_EXISTS: report path must be new and exclusive.");
}

function assertPhpBinary(phpPath) {
    if (!isAbsolute(phpPath)) throw new Error("PHP_INVALID: PHP path must be absolute.");
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
    if (existsSync(cachePath)) throw new Error("APP_CONFIG_CACHE_INVALID: cache path must be absent before child Pest.");
    if (existsSync(phpFactsPath)) throw new Error("PHP_FACTS_EXISTS: child fact report must be new.");
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
        DOVRA_PROGRAM05_COMPOSITE_DATABASE: databasePath,
        DOVRA_PROGRAM05_COMPOSITE_ALLOW: "true",
        DOVRA_PROGRAM05_COMPOSITE_REPORT: phpFactsPath,
        QUEUE_CONNECTION: "sync",
    };
    const result = spawnSync(phpPath, ["vendor/bin/pest", "-c", "phpunit.sqlite.xml", dedicatedPestPath, "--colors=never"], {
        cwd: repositoryRoot,
        env: childEnvironment,
        encoding: "utf8",
        shell: false,
        maxBuffer: 20 * 1024 * 1024,
    });
    // A child must never leave a generated workspace config cache behind.
    // Keep this check directly adjacent to the child process boundary.
    if (existsSync(cachePath)) throw new Error("APP_CONFIG_CACHE_CREATED: child Pest wrote the forbidden cache path.");
    if (result.status !== 0) throw new Error(`DEDICATED_PEST_FAILED: exit ${result.status}\n${result.stdout}\n${result.stderr}`);
    if (!/\b1\s+passed\b/u.test(result.stdout) || /\b(?:[1-9]\d*)\s+skipped\b/u.test(result.stdout)) throw new Error(`DEDICATED_PEST_COUNT_INVALID: expected exactly one passed test.\n${result.stdout}`);
    if (!existsSync(phpFactsPath)) throw new Error("PHP_FACTS_MISSING: dedicated Pest did not create its fact report.");
    return { result, facts: readFacts(phpFactsPath) };
}

export function certifyComposite({ php, report, strict = false } = {}) {
    if (resolve(repositoryRoot) !== resolve(realpathSync(repositoryRoot))) throw new Error("REPOSITORY_ROOT_INVALID: repository root is an alias.");
    assertNoLinkChain(repositoryRoot, "Repository root");
    if (!isAbsolute(report)) throw new Error("REPORT_INVALID: report path must be absolute.");
    const reportPath = resolve(report);
    assertNewAbsoluteReport(reportPath);
    assertPhpBinary(php);
    const tempDirectory = mkdtempSync(join(tmpdir(), "dovra-program05-composite-"));
    assertNoLinkChain(tempDirectory, "Composite temp directory");
    const databasePath = join(tempDirectory, "scenario.sqlite");
    const phpFactsPath = join(tempDirectory, "php-facts.json");
    const cachePath = join(tempDirectory, "config-cache.php");
    let facts;
    try {
        const descriptor = openSync(databasePath, "wx");
        closeSync(descriptor);
        assertNoLinkChain(databasePath, "SQLite database");
        if (resolve(reportPath) === resolve(databasePath)) throw new Error("REPORT_DATABASE_ALIAS: report and SQLite database paths must differ.");
        if (existsSync(cachePath)) throw new Error("APP_CONFIG_CACHE_INVALID: unique cache path already exists.");
        ({ facts } = runDedicatedPest({ phpPath: php, databasePath, phpFactsPath, cachePath, tempDirectory }));
        const classification = classifyResult({ facts, strict });
        const reportFacts = strict ? { ...facts, strict: true } : facts;
        exclusiveWrite(reportPath, reportFacts);
        const output = { ...facts, launch: classification.launch, strict: Boolean(strict) };
        if (classification.code) output.code = classification.code;
        return { ...output, exit_code: classification.exit_code };
    } finally {
        for (const path of [databasePath, phpFactsPath, cachePath]) assertInside(tempDirectory, path, "Composite cleanup");
        const cacheAbsentBeforeCleanup = existsSync(cachePath) === false;
        if (!cacheAbsentBeforeCleanup) throw new Error("APP_CONFIG_CACHE_CREATED: cache path must remain absent before disposable cleanup.");
        rmSync(tempDirectory, { recursive: true, force: true });
    }
}

function printUsage() {
    console.log("Usage: node scripts/qa/certify-dovra-program05-composite.mjs --php <absolute-php84> --report <absolute-new-json> [--strict] [--json]");
    console.log("Runs the guarded Program 05 composite proof on one disposable SQLite file and emits a local NO_CONTRACT certificate.");
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
