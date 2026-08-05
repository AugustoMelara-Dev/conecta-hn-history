import {
    existsSync,
    lstatSync,
    readFileSync,
    readdirSync,
} from "node:fs";
import { createHash } from "node:crypto";
import { dirname, isAbsolute, relative, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

export const RELEASE_CANDIDATE_SCHEMA = 1;

const scriptRoot = resolve(dirname(fileURLToPath(import.meta.url)), "../..");
const generatedDirectoryNames = new Set([
    "build",
    "coverage",
    "dist",
    "out",
    "playwright-report",
    "reports",
    "results",
    "test-results",
]);
const excludedDirectoryNames = new Set([
    ".git",
    ".worktrees",
    "node_modules",
    "storage",
    "vendor",
    "secrets",
    "keys",
    "private-keys",
    "credentials",
]);
const criticalFiles = ["composer.lock", "package-lock.json", "Dockerfile", ".env.example"];
const expectedEvidenceStatuses = Object.freeze({
    code: "BLOCKED",
    local: "NOT_RUN",
    staging: "NOT_RUN",
    production: "NOT_RUN",
    market: "EXTERNAL_BLOCKED",
});
const expectedStrictRequirements = Object.freeze([
    "PHP 8.4/Laravel backend bootstrap and dependency certification",
    "Staging deployment and routed browser/observability evidence",
    "Production release, managed restore, and provider credentials",
    "External legal, market, customer, traction, revenue, and investment evidence",
]);
const expectedClaims = Object.freeze({
    public_production: false,
    customers_or_pilots: false,
    traction_or_revenue: false,
    investment_readiness: false,
});
const expectedSafety = Object.freeze({
    database_mutations: false,
    external_provider_calls: false,
    production_deployment: false,
    secret_values_copied: false,
});

function sha256(value) {
    return createHash("sha256").update(value).digest("hex");
}

function toPosixPath(path) {
    return path.split(sep).join("/");
}

function parseArguments(argv) {
    const options = { input: null, strict: false, json: false, help: false };
    for (let index = 0; index < argv.length; index += 1) {
        const argument = argv[index];
        if (argument === "--strict") {
            options.strict = true;
        } else if (argument === "--json") {
            options.json = true;
        } else if (argument === "--help" || argument === "-h") {
            options.help = true;
        } else if (argument === "--input" || argument === "--output" || argument === "--bundle") {
            options.input = argv[++index] ?? "";
        } else if (argument.startsWith("--input=") || argument.startsWith("--output=") || argument.startsWith("--bundle=")) {
            options.input = argument.slice(argument.indexOf("=") + 1);
        } else {
            throw new Error(`Unknown argument: ${argument}`);
        }
    }
    if (!options.input && !options.help) {
        throw new Error("--input is required; point it at an assembled release-candidate directory.");
    }
    return options;
}

function readJson(path, findings, code) {
    if (!existsSync(path)) {
        findings.push({ code, path: path, detail: "Required JSON artifact is missing." });
        return null;
    }
    try {
        return JSON.parse(readFileSync(path, "utf8"));
    } catch (error) {
        findings.push({ code: `${code}_INVALID`, path, detail: error instanceof Error ? error.message : String(error) });
        return null;
    }
}

function hasSensitivePath(relativePath) {
    const normalized = toPosixPath(relativePath);
    const segments = normalized.split("/").filter(Boolean);
    const basename = segments.at(-1)?.toLowerCase() ?? "";
    if (basename === ".env" || (basename.startsWith(".env.") && basename !== ".env.example")) {
        return "environment-secret";
    }
    if (segments.some((segment) => excludedDirectoryNames.has(segment.toLowerCase()))) {
        return "sensitive-or-runtime-directory";
    }
    for (let index = 0; index < segments.length - 1; index += 1) {
        if (segments[index].toLowerCase() === "artifacts" && segments[index + 1].toLowerCase() === "releases") {
            return "release-artifact";
        }
    }
    if (segments.some((segment) => generatedDirectoryNames.has(segment.toLowerCase()))) {
        return "generated-output";
    }
    if (
        basename === "secret" ||
        basename === "secrets" ||
        basename === "credentials" ||
        basename === "credential" ||
        basename === "passwords" ||
        basename === "password" ||
        basename === "id_rsa" ||
        basename === "id_dsa" ||
        basename === "id_ed25519" ||
        /\.(?:pem|key|p12|pfx|jks|keystore|secret|secrets|credentials)$/iu.test(basename)
    ) {
        return "secret-or-private-key";
    }
    return null;
}

function envNamesFromContent(content) {
    const names = new Set();
    for (const line of content.toString("utf8").split(/\r?\n/u)) {
        const match = line.match(/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/u);
        if (match) {
            names.add(match[1]);
        }
    }
    return [...names].sort();
}

function hasPrivateKeySignature(content) {
    return /-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----/u.test(content.toString("utf8"));
}

function hasSecretSignature(content) {
    const text = content.toString("utf8");
    for (const line of text.split(/\r?\n/u)) {
        const match = line.match(/^[ \t]*([A-Za-z_][A-Za-z0-9_]*)[ \t]*[:=][ \t]*["']?([^#\r\n]*)/u);
        if (!match) {
            continue;
        }
        const name = match[1].toUpperCase();
        const value = match[2].trim().replace(/["'][ \t]*$/u, "").trim();
        if (!/(?:SECRET|TOKEN|API_KEY|PRIVATE_KEY|PASSWORD|ACCESS_KEY)/u.test(name)) {
            continue;
        }
        if (/(?:_TTL(?:_|$)|_DAYS(?:_|$)|_SECONDS(?:_|$)|_MINUTES(?:_|$)|_HOURS(?:_|$)|_REFERENCE$|_REF$)/u.test(name)) {
            continue;
        }
        if (value.length === 0 || /^\$\{[^}]+\}$/u.test(value) || /^\{\{[^}]+\}\}$/u.test(value) || /^<(?:[^>]+)>$/u.test(value) || /^(?:env|getenv)\s*\(/iu.test(value) || /^process\.env(?:\.|$)/iu.test(value) || /^(?:change|replace|example|dummy|test|your)[-_ ]?/iu.test(value)) {
            continue;
        }
        if (/(?:^|[_-])(?:dummy|placeholder|example|local|test)(?:$|[_-])/iu.test(value) || /^(?:null|none|false|true)$/iu.test(value)) {
            continue;
        }
        return true;
    }
    return false;
}

function isSafeRelativePath(path) {
    return Boolean(path) && !isAbsolute(path) && !path.split(/[\\/]/u).includes("..") && !path.includes("\0") && toPosixPath(path) === path;
}

function walkSource(root, relativeRoot = "", files = [], symlinks = []) {
    if (!existsSync(root)) {
        return { files, symlinks };
    }
    for (const entry of readdirSync(root, { withFileTypes: true }).sort((left, right) => left.name.localeCompare(right.name))) {
        const relativePath = relativeRoot ? `${relativeRoot}/${entry.name}` : entry.name;
        const absolutePath = resolve(root, entry.name);
        if (entry.isSymbolicLink()) {
            symlinks.push(relativePath);
        } else if (entry.isDirectory()) {
            walkSource(absolutePath, relativePath, files, symlinks);
        } else if (entry.isFile()) {
            files.push(relativePath);
        }
    }
    return { files, symlinks };
}

function finding(code, path, detail) {
    return { code, path, detail };
}

function pathExists(path) {
    try {
        lstatSync(path);
        return true;
    } catch {
        return false;
    }
}

function validateEvidence(manifest, findings) {
    const evidence = manifest?.evidence;
    if (!evidence || typeof evidence !== "object") {
        findings.push(finding("EVIDENCE_MISSING", "manifest.evidence", "Evidence separation is missing."));
        return;
    }
    const actualAreas = Object.keys(evidence).sort();
    const expectedAreas = Object.keys(expectedEvidenceStatuses).sort();
    if (JSON.stringify(actualAreas) !== JSON.stringify(expectedAreas)) {
        findings.push(finding("EVIDENCE_SHAPE_INVALID", "manifest.evidence", "Evidence areas must be exactly code, local, staging, production, and market."));
    }
    for (const area of expectedAreas) {
        const value = evidence[area];
        if (!value || typeof value !== "object" || value.status !== expectedEvidenceStatuses[area] || typeof value.detail !== "string") {
            findings.push(finding("EVIDENCE_STATUS_INVALID", `manifest.evidence.${area}`, `Evidence status must remain ${expectedEvidenceStatuses[area]} for schema ${RELEASE_CANDIDATE_SCHEMA}.`));
        }
    }
}

function validateManifestContract(manifest, findings) {
    if (!manifest || typeof manifest !== "object") {
        return;
    }
    if (manifest.kind !== "dovra-release-candidate-manifest") {
        findings.push(finding("MANIFEST_KIND_INVALID", "manifest.kind", "Unexpected release-candidate manifest kind."));
    }
    if (manifest.schema !== RELEASE_CANDIDATE_SCHEMA) {
        findings.push(finding("MANIFEST_SCHEMA_INVALID", "manifest.schema", "Unsupported release-candidate manifest schema."));
    }
    if (manifest.release_decision !== "LOCALLY_VERIFIED/NO_GO") {
        findings.push(finding("RELEASE_DECISION_INVALID", "manifest.release_decision", "Local assembly must remain explicitly NO_GO for release."));
    }
    if (JSON.stringify(manifest.strict_requirements) !== JSON.stringify(expectedStrictRequirements)) {
        findings.push(finding("STRICT_REQUIREMENTS_INVALID", "manifest.strict_requirements", "Strict requirements are immutable for this schema."));
    }
    if (JSON.stringify(manifest.claims) !== JSON.stringify(expectedClaims)) {
        findings.push(finding("MANIFEST_CLAIMS_INVALID", "manifest.claims", "All public/customer/traction/investment claim flags must remain false."));
    }
    if (JSON.stringify(manifest.safety) !== JSON.stringify(expectedSafety)) {
        findings.push(finding("MANIFEST_SAFETY_INVALID", "manifest.safety", "Safety controls must remain fail-closed and false."));
    }
}

function compareSortedEntries(index, findings) {
    if (!Array.isArray(index?.entries)) {
        findings.push(finding("INDEX_ENTRIES_MISSING", "index.entries", "Index entries must be an array."));
        return;
    }
    const expectedOrder = [...index.entries].sort((left, right) => {
        const leftKey = `${left.path}\u0000${left.size}\u0000${left.sha256}`;
        const rightKey = `${right.path}\u0000${right.size}\u0000${right.sha256}`;
        return leftKey < rightKey ? -1 : leftKey > rightKey ? 1 : 0;
    });
    if (JSON.stringify(index.entries) !== JSON.stringify(expectedOrder)) {
        findings.push(finding("INDEX_ORDER_INVALID", "index.entries", "Entries are not in deterministic path/size/sha256 order."));
    }
    const paths = new Set();
    for (const entry of index.entries) {
        if (!entry || typeof entry !== "object" || typeof entry.path !== "string" || typeof entry.size !== "number" || typeof entry.sha256 !== "string") {
            findings.push(finding("INDEX_ENTRY_INVALID", "index.entries", "Every entry must contain path, size, and sha256."));
            continue;
        }
        if (paths.has(entry.path)) {
            findings.push(finding("INDEX_DUPLICATE_PATH", entry.path, "The index contains a duplicate path."));
        }
        paths.add(entry.path);
        if (!isSafeRelativePath(entry.path)) {
            findings.push(finding("INDEX_PATH_INVALID", entry.path, "Index paths must be normalized relative POSIX paths."));
        }
        const sensitiveReason = hasSensitivePath(entry.path);
        if (sensitiveReason) {
            findings.push(finding("FORBIDDEN_INDEX_PATH", entry.path, `Sensitive/generated path is forbidden (${sensitiveReason}).`));
        }
    }
}

function verifySource(input, index, manifest, findings) {
    const sourceRoot = resolve(input, "source");
    const actual = walkSource(sourceRoot);
    for (const path of actual.symlinks) {
        findings.push(finding("SYMLINK_FORBIDDEN", path, "Release-candidate source may contain only regular files."));
    }
    const actualSet = new Set(actual.files);
    const expectedSet = new Set(Array.isArray(index?.entries) ? index.entries.map((entry) => entry.path) : []);

    for (const path of actual.files) {
        const sensitiveReason = hasSensitivePath(path);
        if (sensitiveReason) {
            findings.push(finding("FORBIDDEN_PATH", path, `Sensitive/generated path is present (${sensitiveReason}).`));
        }
        if (!expectedSet.has(path)) {
            findings.push(finding("UNEXPECTED_FILE", path, "Source file is not present in the signed index."));
        }
        const absolutePath = resolve(sourceRoot, path);
        const content = readFileSync(absolutePath);
        if (hasPrivateKeySignature(content)) {
            findings.push(finding("PRIVATE_KEY_SIGNATURE", path, "Private-key signature detected in source bundle."));
        } else if (hasSecretSignature(content)) {
            findings.push(finding("SECRET_SIGNATURE", path, "Secret assignment signature detected in source bundle."));
        }
    }
    for (const entry of index?.entries ?? []) {
        const path = entry.path;
        if (!actualSet.has(path)) {
            findings.push(finding("MISSING_FILE", path, "Indexed source file is missing from the bundle."));
            continue;
        }
        const absolutePath = resolve(sourceRoot, path);
        let stats;
        try {
            stats = lstatSync(absolutePath);
        } catch (error) {
            findings.push(finding("FILE_READ_ERROR", path, error instanceof Error ? error.message : String(error)));
            continue;
        }
        if (!stats.isFile()) {
            findings.push(finding("NON_REGULAR_FILE", path, "Indexed path is not a regular file."));
            continue;
        }
        if (stats.size !== entry.size) {
            findings.push(finding("SIZE_MISMATCH", path, `Expected ${entry.size} bytes, found ${stats.size} bytes.`));
        }
        const content = readFileSync(absolutePath);
        const digest = sha256(content);
        if (digest !== entry.sha256) {
            findings.push(finding("HASH_MISMATCH", path, `Expected ${entry.sha256}, found ${digest}.`));
        }
    }

    const criticalHashes = manifest?.critical_hashes;
    if (!criticalHashes || typeof criticalHashes !== "object") {
        findings.push(finding("CRITICAL_HASHES_MISSING", "manifest.critical_hashes", "Critical file hashes are missing."));
    } else {
        for (const path of criticalFiles) {
            const expectedHash = criticalHashes[path];
            if (typeof expectedHash !== "string" || !/^[a-f0-9]{64}$/u.test(expectedHash)) {
                findings.push(finding("CRITICAL_HASH_MISSING", path, "Every mandatory critical file must have a SHA-256 hash in the manifest."));
                continue;
            }
            const entry = index?.entries?.find((candidate) => candidate.path === path);
            if (!entry) {
                findings.push(finding("CRITICAL_FILE_MISSING", path, "Every mandatory critical file must be present in the signed index."));
            } else if (entry.sha256 !== expectedHash) {
                findings.push(finding("CRITICAL_HASH_MISMATCH", path, "Manifest critical hash diverges from the signed index."));
            }
        }
    }

    const envPath = resolve(sourceRoot, ".env.example");
    const envNames = existsSync(envPath) && lstatSync(envPath).isFile()
        ? envNamesFromContent(readFileSync(envPath, "utf8"))
        : [];
    const declaredEnvNames = Array.isArray(manifest?.env_example_names) ? manifest.env_example_names : null;
    if (!declaredEnvNames) {
        findings.push(finding("ENV_NAMES_MISSING", "manifest.env_example_names", "Only environment variable names must be recorded."));
    } else if (JSON.stringify([...declaredEnvNames].sort()) !== JSON.stringify(envNames)) {
        findings.push(finding("ENV_NAMES_MISMATCH", "manifest.env_example_names", "Manifest names diverge from the bundled .env.example."));
    }
}

function verifyBundleRoot(input, findings) {
    if (!pathExists(input) || !lstatSync(input).isDirectory() || lstatSync(input).isSymbolicLink()) {
        findings.push(finding("BUNDLE_MISSING", input, "Release-candidate directory does not exist."));
        return false;
    }
    let valid = true;
    const allowed = new Set(["source", "index.json", "manifest.json", "verification.json"]);
    for (const entry of readdirSync(input, { withFileTypes: true })) {
        if (!allowed.has(entry.name)) {
            findings.push(finding("UNEXPECTED_BUNDLE_PATH", entry.name, "Bundle root contains an unexpected artifact."));
        }
    }
    for (const [name, type] of [["source", "directory"], ["index.json", "file"], ["manifest.json", "file"], ["verification.json", "file"]]) {
        const component = resolve(input, name);
        if (!pathExists(component)) {
            if (name !== "verification.json") {
                findings.push(finding("BUNDLE_COMPONENT_MISSING", name, `Required bundle ${type} is missing.`));
                valid = false;
            }
            continue;
        }
        const stats = lstatSync(component);
        if (stats.isSymbolicLink()) {
            findings.push(finding("BUNDLE_COMPONENT_LINK", name, "Bundle root components may not be symlinks or junctions."));
            valid = false;
        } else if ((type === "directory" && !stats.isDirectory()) || (type === "file" && !stats.isFile())) {
            findings.push(finding("BUNDLE_COMPONENT_TYPE", name, `Bundle ${type} has the wrong filesystem type.`));
            valid = false;
        }
    }
    return valid;
}

export function verifyReleaseCandidate({ input, strict = false } = {}) {
    const findings = [];
    const inputResolved = input ? resolve(input) : null;
    if (!inputResolved) {
        findings.push(finding("INPUT_MISSING", "", "An input directory is required."));
        return { schema: RELEASE_CANDIDATE_SCHEMA, status: "FAIL", strict, findings, evidence: {} };
    }
    const bundleRootSafe = verifyBundleRoot(inputResolved, findings);
    if (!bundleRootSafe) {
        findings.sort((left, right) => {
            const leftKey = `${left.code}\u0000${left.path}`;
            const rightKey = `${right.code}\u0000${right.path}`;
            return leftKey < rightKey ? -1 : leftKey > rightKey ? 1 : 0;
        });
        return {
            schema: RELEASE_CANDIDATE_SCHEMA,
            kind: "dovra-release-candidate-verification",
            status: "FAIL",
            strict,
            release_decision: null,
            evidence: {},
            findings,
            source_file_count: null,
            snapshot_sha256: null,
            safety: {
                database_mutations: false,
                external_provider_calls: false,
                production_deployment: false,
                claims_promoted: false,
            },
        };
    }
    const manifestPath = resolve(inputResolved, "manifest.json");
    const indexPath = resolve(inputResolved, "index.json");
    const manifest = readJson(manifestPath, findings, "MANIFEST_MISSING");
    const index = readJson(indexPath, findings, "INDEX_MISSING");

    if (index && index.schema !== RELEASE_CANDIDATE_SCHEMA) {
        findings.push(finding("INDEX_SCHEMA_INVALID", "index.schema", "Unsupported release-candidate index schema."));
    }
    if (index && index.kind !== "dovra-release-candidate-index") {
        findings.push(finding("INDEX_KIND_INVALID", "index.kind", "Unexpected release-candidate index kind."));
    }
    validateManifestContract(manifest, findings);
    if (manifest && index) {
        const indexBytes = readFileSync(indexPath);
        if (manifest.index_sha256 !== sha256(indexBytes)) {
            findings.push(finding("INDEX_HASH_MISMATCH", "index.json", "Manifest index_sha256 does not match index.json bytes."));
        }
        if (manifest.snapshot_sha256 !== index.snapshot_sha256) {
            findings.push(finding("SNAPSHOT_MISMATCH", "snapshot_sha256", "Manifest and index snapshot hashes diverge."));
        }
        const recomputedSnapshot = sha256(JSON.stringify(index.entries ?? []));
        if (index.snapshot_sha256 !== recomputedSnapshot) {
            findings.push(finding("SNAPSHOT_MISMATCH", "index.entries", "Index snapshot hash does not match canonical sorted entries."));
        }
        compareSortedEntries(index, findings);
        verifySource(inputResolved, index, manifest, findings);
        validateEvidence(manifest, findings);
    }

    findings.sort((left, right) => {
        const leftKey = `${left.code}\u0000${left.path}`;
        const rightKey = `${right.code}\u0000${right.path}`;
        return leftKey < rightKey ? -1 : leftKey > rightKey ? 1 : 0;
    });
    const evidence = manifest?.evidence ?? {};
    const strictPending = strict && ["code", "local", "staging", "production", "market"].some((area) => evidence?.[area]?.status !== "PASS");
    const status = findings.length > 0 ? "FAIL" : strictPending ? "NO_GO" : "PASS";
    return {
        schema: RELEASE_CANDIDATE_SCHEMA,
        kind: "dovra-release-candidate-verification",
        status,
        strict,
        release_decision: manifest?.release_decision ?? null,
        evidence,
        findings,
        source_file_count: index?.entries?.length ?? null,
        snapshot_sha256: index?.snapshot_sha256 ?? null,
        safety: {
            database_mutations: false,
            external_provider_calls: false,
            production_deployment: false,
            claims_promoted: false,
        },
    };
}

function printUsage() {
    console.log("Usage: node scripts/qa/verify-dovra-rc.mjs --input <assembled-dir> [--strict] [--json]");
    console.log("Verifies index, snapshot, source hashes, exclusions, environment-name boundary, and evidence status.");
}

if (process.argv[1] && resolve(process.argv[1]) === resolve(fileURLToPath(import.meta.url))) {
    try {
        const options = parseArguments(process.argv.slice(2));
        if (options.help) {
            printUsage();
            process.exit(0);
        }
        const report = verifyReleaseCandidate(options);
        if (options.json) {
            console.log(JSON.stringify(report, null, 2));
        } else {
            console.log(`status: ${report.status}`);
            console.log(`findings: ${report.findings.length}`);
            console.log(`snapshot_sha256: ${report.snapshot_sha256 ?? "unknown"}`);
            for (const item of report.findings) {
                console.log(`[${item.code}] ${item.path}: ${item.detail}`);
            }
        }
        process.exitCode = report.status === "PASS" ? 0 : 1;
    } catch (error) {
        console.error(error instanceof Error ? error.message : String(error));
        process.exitCode = 1;
    }
}
