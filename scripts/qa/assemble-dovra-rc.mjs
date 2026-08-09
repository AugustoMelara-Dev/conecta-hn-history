import {
    closeSync,
    constants as fsConstants,
    existsSync,
    fstatSync,
    lstatSync,
    mkdirSync,
    openSync,
    readdirSync,
    realpathSync,
    readFileSync,
    writeFileSync,
} from "node:fs";
import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import { dirname, isAbsolute, relative, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

export const RELEASE_CANDIDATE_SCHEMA = 1;
export const STATUS_VALUES = Object.freeze(["PASS", "BLOCKED", "NOT_RUN", "EXTERNAL_BLOCKED"]);

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
const strictRequirements = [
    "PHP 8.4/Laravel backend bootstrap and dependency certification",
    "Staging deployment and routed browser/observability evidence",
    "Production release, managed restore, and provider credentials",
    "External legal, market, customer, traction, revenue, and investment evidence",
];

function sha256(value) {
    return createHash("sha256").update(value).digest("hex");
}

function hashFile(path) {
    return sha256(readFileSync(path));
}

function canonicalJson(value) {
    return JSON.stringify(value);
}

function toPosixPath(path) {
    return path.split(sep).join("/");
}

function parseArguments(argv) {
    const options = {
        source: scriptRoot,
        output: null,
        strict: false,
        json: false,
        help: false,
    };

    for (let index = 0; index < argv.length; index += 1) {
        const argument = argv[index];
        if (argument === "--strict") {
            options.strict = true;
        } else if (argument === "--json") {
            options.json = true;
        } else if (argument === "--help" || argument === "-h") {
            options.help = true;
        } else if (argument === "--source" || argument === "--root") {
            options.source = argv[++index] ?? "";
        } else if (argument.startsWith("--source=") || argument.startsWith("--root=")) {
            options.source = argument.slice(argument.indexOf("=") + 1);
        } else if (argument === "--output" || argument === "--bundle") {
            options.output = argv[++index] ?? "";
        } else if (argument.startsWith("--output=") || argument.startsWith("--bundle=")) {
            options.output = argument.slice(argument.indexOf("=") + 1);
        } else {
            throw new Error(`Unknown argument: ${argument}`);
        }
    }

    if (!options.output && !options.help) {
        throw new Error("--output is required; use an isolated directory outside the source checkout.");
    }

    return options;
}

function runGit(source, args, fallback = null) {
    try {
        return execFileSync("git", args, {
            cwd: source,
            encoding: "buffer",
            stdio: ["ignore", "pipe", "pipe"],
        });
    } catch (error) {
        if (fallback !== null) {
            return fallback;
        }
        const detail = error instanceof Error ? error.message : String(error);
        throw new Error(`Unable to run git ${args.join(" ")} in ${source}: ${detail}`);
    }
}

function decodeNullSeparated(buffer) {
    return buffer
        .toString("utf8")
        .split("\0")
        .filter((path) => path.length > 0);
}

function readGitSnapshot(source) {
    const visible = decodeNullSeparated(runGit(source, ["ls-files", "-co", "--exclude-standard", "-z"]));
    const statusBytes = runGit(source, ["status", "--porcelain=v1", "-z"], Buffer.alloc(0));
    const statusRecords = decodeNullSeparated(statusBytes);
    const statuses = [];
    const deletedPaths = [];

    for (let index = 0; index < statusRecords.length; index += 1) {
        const record = statusRecords[index];
        if (record.length < 3) {
            continue;
        }
        const indexStatus = record[0];
        const worktreeStatus = record[1];
        const path = record.slice(3);
        const status = { index: indexStatus, worktree: worktreeStatus, path };
        if (indexStatus === "R" || indexStatus === "C" || worktreeStatus === "R" || worktreeStatus === "C") {
            const nextPath = statusRecords[index + 1];
            if (nextPath) {
                status.original_path = path;
                status.path = nextPath;
                index += 1;
            }
        }
        statuses.push(status);
        if (indexStatus === "D" || worktreeStatus === "D") {
            deletedPaths.push(path);
        }
    }

    const head = runGit(source, ["rev-parse", "HEAD"], Buffer.from(""))
        .toString("utf8")
        .trim() || null;
    const branch = runGit(source, ["branch", "--show-current"], Buffer.from(""))
        .toString("utf8")
        .trim() || null;

    return {
        visible,
        git: {
            head,
            branch,
            dirty: statuses.length > 0,
            dirty_paths: statuses.length,
            statuses,
            deleted_paths: [...new Set(deletedPaths)].sort(),
        },
    };
}

function isInside(parent, child) {
    const fromParent = relative(parent, child);
    return fromParent === "" || (!fromParent.startsWith("..") && !isAbsolute(fromParent));
}

function hasSensitivePath(relativePath) {
    const normalized = toPosixPath(relativePath);
    const segments = normalized.split("/").filter(Boolean);
    const basename = segments.at(-1)?.toLowerCase() ?? "";
    if (basename === ".env" || (basename.startsWith(".env.") && basename !== ".env.example")) {
        return { excluded: true, reason: "environment-secret" };
    }
    if (segments.some((segment) => excludedDirectoryNames.has(segment.toLowerCase()))) {
        return { excluded: true, reason: "sensitive-or-runtime-directory" };
    }
    for (let index = 0; index < segments.length - 1; index += 1) {
        if (segments[index].toLowerCase() === "artifacts" && segments[index + 1].toLowerCase() === "releases") {
            return { excluded: true, reason: "release-artifact" };
        }
    }
    if (segments.some((segment) => generatedDirectoryNames.has(segment.toLowerCase()))) {
        return { excluded: true, reason: "generated-output" };
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
        return { excluded: true, reason: "secret-or-private-key" };
    }
    return { excluded: false, reason: null };
}

function normalizeAssignmentName(name) {
    return name
        .replace(/([A-Z]+)([A-Z][a-z])/gu, "$1_$2")
        .replace(/([a-z0-9])([A-Z])/gu, "$1_$2")
        .replace(/[^A-Za-z0-9]+/gu, "_")
        .replace(/^_+|_+$/gu, "")
        .toUpperCase();
}

function isSensitiveAssignmentName(name) {
    const normalized = normalizeAssignmentName(name);
    const parts = normalized.split("_").filter(Boolean);
    if (parts.includes("SECRET") || parts.includes("TOKEN") || parts.includes("PASSWORD")) {
        return true;
    }
    for (let index = 0; index < parts.length - 1; index += 1) {
        const pair = `${parts[index]}_${parts[index + 1]}`;
        if (["API_KEY", "PRIVATE_KEY", "ACCESS_KEY"].includes(pair)) {
            return true;
        }
    }
    return false;
}

function staticAssignmentValue(rawValue, delimiter, configSurface) {
    const candidate = rawValue.trim();
    if (candidate.length === 0) {
        return "";
    }
    const quote = candidate[0];
    let value;
    if (quote === "\"" || quote === "'") {
        let escaped = false;
        let closingIndex = -1;
        for (let index = 1; index < candidate.length; index += 1) {
            const character = candidate[index];
            if (!escaped && character === quote) {
                closingIndex = index;
                break;
            }
            if (!escaped && character === "\\") {
                escaped = true;
            } else {
                escaped = false;
            }
        }
        if (closingIndex < 0 || !/^\s*[,;]?\s*(?:(?:#|\/\/).*)?$/u.test(candidate.slice(closingIndex + 1))) {
            return null;
        }
        value = candidate.slice(1, closingIndex);
    } else {
        if (!configSurface || delimiter === "=>") {
            return null;
        }
        value = candidate.replace(/\s+(?:#|\/\/).*$/u, "").replace(/[,;]\s*$/u, "").trim();
        if (value.length === 0) {
            return "";
        }
        if (/\s/u.test(value)) {
            return null;
        }
    }
    if (/\$|\{\{|\}\}|\(|\)|::|->|=>|\[|\]|\{|\}/u.test(value)
        || /^(?:env|getenv)\b|^process\.env\b/iu.test(value)
        || /^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)+$/u.test(value)) {
        return null;
    }
    return value;
}

function isKnownLocalDatabaseSentinel(name, value) {
    const sentinels = new Map([
        ["PGPASSWORD", new Set(["postgres"])],
        ["POSTGRES_PASSWORD", new Set(["postgres"])],
        ["DB_PASSWORD", new Set(["postgres", "rls-test-password"])],
        ["DB_PLATFORM_PASSWORD", new Set(["rls-platform-password"])],
        ["MYSQL_PASSWORD", new Set(["conecta"])],
        ["MYSQL_ROOT_PASSWORD", new Set(["root"])],
    ]);
    return sentinels.get(name)?.has(value) ?? false;
}

function isCredentialConfigSurface(path) {
    const normalized = toPosixPath(path).toLowerCase();
    return normalized.endsWith("/.env.example")
        || normalized === ".env.example"
        || /\.(?:ya?ml|toml|ini|properties)$/u.test(normalized);
}

function shannonEntropy(value) {
    const counts = new Map();
    for (const character of value) {
        counts.set(character, (counts.get(character) ?? 0) + 1);
    }
    let entropy = 0;
    for (const count of counts.values()) {
        const probability = count / value.length;
        entropy -= probability * Math.log2(probability);
    }
    return entropy;
}

function characterClassCount(value) {
    return [/[a-z]/u, /[A-Z]/u, /[0-9]/u, /[^A-Za-z0-9]/u]
        .filter((pattern) => pattern.test(value))
        .length;
}

function isHighEntropyCredential(value) {
    return value.length >= 24
        && !/\p{White_Space}/u.test(value)
        && characterClassCount(value) >= 2
        && shannonEntropy(value) >= 3.5;
}

function isFileLocatorLiteral(value) {
    if (/[\p{White_Space}\p{Cc}]/u.test(value) || /[?#]/u.test(value)) {
        return false;
    }
    const locator = value.replace(/\\\\/gu, "\\");
    if (!/[\\/]/u.test(locator)) {
        return false;
    }
    let remainder = locator;
    const drivePrefix = remainder.match(/^[A-Za-z]:[\\/]/u);
    if (drivePrefix) {
        remainder = remainder.slice(drivePrefix[0].length);
    } else if (/^[A-Za-z][A-Za-z0-9+.-]*:/u.test(remainder)) {
        return false;
    } else if (/^\.\.[\\/]/u.test(remainder)) {
        remainder = remainder.slice(3);
    } else if (/^\.[\\/]/u.test(remainder)) {
        remainder = remainder.slice(2);
    } else if (/^[\\/]/u.test(remainder)) {
        remainder = remainder.slice(1);
    }
    const segments = remainder.split(/[\\/]/u);
    if (segments.length === 0 || segments.some((segment) => segment.length === 0
        || segment === "."
        || segment === ".."
        || !/^[A-Za-z0-9._-]+$/u.test(segment))) {
        return false;
    }
    const basename = segments.at(-1);
    const extension = basename.match(/^(.+)\.([A-Za-z0-9]{1,10})$/u);
    return extension !== null && extension[1].length > 0;
}

function hasProductionEntropy(value) {
    return characterClassCount(value) >= 2 && shannonEntropy(value) >= 3.5;
}

function lineNumberAt(text, index) {
    return text.slice(0, index).split("\n").length;
}

function decodesJsonObject(value) {
    try {
        const decoded = JSON.parse(Buffer.from(value, "base64url").toString("utf8"));
        return decoded !== null && typeof decoded === "object" && !Array.isArray(decoded);
    } catch {
        return false;
    }
}

function isKnownProviderExample(value) {
    return value === "AKIAIOSFODNN7EXAMPLE" || value === "whsec_dummy_secret_for_local_dev_only";
}

function valueSignatureDetections(text) {
    const detections = [];
    const signatures = [
        ["aws-access-key", /\b(?:AKIA|ASIA)([A-Z0-9]{16})\b/gu],
        ["github-token", /\b(?:gh[pousr]_([A-Za-z0-9]{20,})|github_pat_([A-Za-z0-9_]{20,}))\b/gu],
        ["openai-key", /\bsk-(proj-)?([A-Za-z0-9_-]+)\b/gu],
        ["stripe-key", /\b(sk_live_|rk_live_|whsec_)([A-Za-z0-9_-]+)\b/gu],
        ["slack-token", /\bxox[a-z]-([A-Za-z0-9-]{16,})\b/giu],
        ["google-api-key", /\bAIza([0-9A-Za-z_-]{35})\b/gu],
    ];
    for (const [type, pattern] of signatures) {
        for (const match of text.matchAll(pattern)) {
            const full = match[0];
            if (isKnownProviderExample(full)) {
                continue;
            }
            let body = match.at(-1);
            let minimum = 0;
            if (type === "aws-access-key") minimum = 16;
            if (type === "github-token") body = match[1] ?? match[2];
            if (type === "openai-key") minimum = match[1] === "proj-" ? 40 : 32;
            if (type === "stripe-key") minimum = match[1] === "whsec_" ? 32 : 24;
            if (type === "slack-token") minimum = 20;
            if (type === "google-api-key") minimum = 35;
            if (body.length >= minimum && hasProductionEntropy(body)) {
                detections.push({ line: lineNumberAt(text, match.index), name: type });
            }
        }
    }
    const jwtPattern = /\b([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)\b/gu;
    for (const match of text.matchAll(jwtPattern)) {
        if (match[3].length >= 32 && hasProductionEntropy(match[3]) && decodesJsonObject(match[1]) && decodesJsonObject(match[2])) {
            detections.push({ line: lineNumberAt(text, match.index), name: "jwt" });
        }
    }
    const pemPattern = /-----BEGIN ([A-Z0-9 ]*PRIVATE KEY)-----([\s\S]*?)-----END \1-----/gu;
    for (const match of text.matchAll(pemPattern)) {
        const material = match[2].replace(/\s+/gu, "");
        if (material.length >= 64 && /^[A-Za-z0-9+/=]+$/u.test(material)) {
            detections.push({ line: lineNumberAt(text, match.index), name: "pem-private-key" });
        }
    }
    return detections;
}

function isConfigLiteralExempt(name, value) {
    if (value.length === 0 || /^(?:null|none)$/iu.test(value)) {
        return true;
    }
    if (name === "MYSQL_ALLOW_EMPTY_PASSWORD" || isKnownLocalDatabaseSentinel(name, value) || isKnownProviderExample(value)) {
        return true;
    }
    if (/(?:_REFERENCE|_REF)$/u.test(name)) {
        return true;
    }
    return /(?:_(?:TTL|TIMEOUT)(?:_(?:DAYS|HOURS|MINUTES|SECONDS))?|_(?:DAYS|HOURS|MINUTES|SECONDS))$/u.test(name)
        && /^\d+(?:\.\d+)?$/u.test(value);
}

function secretSignatures(content, path) {
    const text = content.toString("utf8");
    const detections = valueSignatureDetections(text);
    const configSurface = isCredentialConfigSurface(path);
    for (const [index, line] of text.split(/\r?\n/u).entries()) {
        const match = line.match(/^[ \t]*(?:(?:export[ \t]+)?(?:const|let|var)[ \t]+)?(?:(['"])([A-Za-z_][A-Za-z0-9_]*)\1|([A-Za-z_][A-Za-z0-9_]*))[ \t]*(=>|:|=)[ \t]*(.*)$/u);
        if (!match) {
            continue;
        }
        const name = normalizeAssignmentName(match[2] ?? match[3]);
        if (!isSensitiveAssignmentName(name)) {
            continue;
        }
        const value = staticAssignmentValue(match[5], match[4], configSurface);
        if (value === null) {
            continue;
        }
        if (configSurface && isConfigLiteralExempt(name, value)) {
            continue;
        }
        const quotedName = match[1] !== undefined;
        if (configSurface || (!quotedName && isHighEntropyCredential(value) && !isFileLocatorLiteral(value))) {
            detections.push({ line: index + 1, name: "sensitive-literal" });
        }
    }
    return detections.filter((detection, index, all) => all.findIndex((candidate) => candidate.line === detection.line && candidate.name === detection.name) === index);
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

function pathExists(path) {
    try {
        lstatSync(path);
        return true;
    } catch {
        return false;
    }
}

function assertNoSymlinkChain(path, label) {
    let current = resolve(path);
    const components = [];
    while (true) {
        components.push(current);
        const parent = dirname(current);
        if (parent === current) {
            break;
        }
        current = parent;
    }
    for (const component of components.reverse()) {
        if (!pathExists(component)) {
            continue;
        }
        const stats = lstatSync(component);
        if (stats.isSymbolicLink()) {
            throw new Error(`${label} path contains a symlink/junction: ${component}`);
        }
    }
}

function isSafeRelativePath(path) {
    return Boolean(path) && !isAbsolute(path) && !path.split(/[\\/]/u).includes("..") && !path.includes("\0") && toPosixPath(path) === path;
}

function sameFileIdentity(left, right) {
    if (!left.isFile() || !right.isFile()) {
        return false;
    }
    if (left.dev && right.dev && left.dev !== right.dev) {
        return false;
    }
    if (left.ino && right.ino && left.ino !== right.ino) {
        return false;
    }
    return true;
}

function prepareOutput(output) {
    if (pathExists(output)) {
        throw new Error(`Output must be a new exclusive directory; refusing to reuse ${output}.`);
    }
    const parent = dirname(output);
    if (!pathExists(parent) || !lstatSync(parent).isDirectory()) {
        throw new Error(`Output parent directory does not exist: ${parent}`);
    }
    mkdirSync(output, { recursive: false });
    assertNoSymlinkChain(output, "Output");
    const sourceOutput = resolve(output, "source");
    mkdirSync(sourceOutput, { recursive: false });
    if (!lstatSync(sourceOutput).isDirectory() || lstatSync(sourceOutput).isSymbolicLink()) {
        throw new Error("Output source component is not a regular directory.");
    }
    return sourceOutput;
}

function assertSafePaths(source, output) {
    if (!existsSync(source) || !lstatSync(source).isDirectory()) {
        throw new Error(`Source directory does not exist or is not a directory: ${source}`);
    }
    const sourceResolved = resolve(source);
    const outputResolved = resolve(output);
    if (sourceResolved === outputResolved || isInside(sourceResolved, outputResolved)) {
        throw new Error("Output must be an isolated directory outside the source checkout.");
    }
    assertNoSymlinkChain(sourceResolved, "Source");
    assertNoSymlinkChain(outputResolved, "Output");
}

function copyVisibleFiles(source, sourceOutput, visiblePaths) {
    const entries = [];
    const excludedCounts = new Map();
    const envNamesSet = new Set();
    const secretDetections = [];

    for (const candidate of visiblePaths.map(toPosixPath).sort()) {
        if (!isSafeRelativePath(candidate)) {
            throw new Error(`Unsafe Git path cannot be copied: ${candidate}`);
        }
        const pathDecision = hasSensitivePath(candidate);
        if (pathDecision.excluded) {
            excludedCounts.set(pathDecision.reason, (excludedCounts.get(pathDecision.reason) ?? 0) + 1);
            continue;
        }

        const sourcePath = resolve(source, candidate);
        assertNoSymlinkChain(sourcePath, `Source file ${candidate}`);
        let descriptor;
        let content;
        try {
            const initialStats = lstatSync(sourcePath);
            if (!initialStats.isFile() || initialStats.isSymbolicLink()) {
                excludedCounts.set(initialStats.isSymbolicLink() ? "symbolic-link" : "non-regular-file", (excludedCounts.get(initialStats.isSymbolicLink() ? "symbolic-link" : "non-regular-file") ?? 0) + 1);
                continue;
            }
            descriptor = openSync(sourcePath, fsConstants.O_RDONLY | (fsConstants.O_NOFOLLOW ?? 0));
            const openedStats = fstatSync(descriptor);
            if (!sameFileIdentity(initialStats, openedStats)) {
                throw new Error(`Source changed identity while reading: ${candidate}`);
            }
            content = readFileSync(descriptor);
        } catch (error) {
            if (descriptor !== undefined) {
                closeSync(descriptor);
            }
            if (error instanceof Error && error.message.includes("Source changed")) {
                throw error;
            }
            excludedCounts.set("missing-at-copy", (excludedCounts.get("missing-at-copy") ?? 0) + 1);
            continue;
        } finally {
            if (descriptor !== undefined) {
                try {
                    closeSync(descriptor);
                } catch {
                    // The descriptor may already have been closed in the error path.
                }
            }
        }
        const detected = secretSignatures(content, candidate);
        if (detected.length > 0) {
            secretDetections.push(...detected.map(({ line, name }) => ({ path: candidate, line, name })));
            continue;
        }

        const destination = resolve(sourceOutput, candidate);
        mkdirSync(dirname(destination), { recursive: true });
        writeFileSync(destination, content, { flag: "wx" });
        const entry = {
            path: candidate,
            size: content.byteLength,
            sha256: sha256(content),
        };
        entries.push(entry);
        if (candidate.split("/").at(-1) === ".env.example") {
            for (const name of envNamesFromContent(content)) {
                envNamesSet.add(name);
            }
        }
    }

    if (secretDetections.length > 0) {
        const diagnostics = secretDetections
            .map(({ path, line, name }) => `${path}:${line}:${name}`)
            .join("\n");
        throw new Error(`Static literal secrets detected; refusing to assemble:\n${diagnostics}`);
    }

    entries.sort((left, right) => left.path < right.path ? -1 : left.path > right.path ? 1 : 0);
    const missingCritical = criticalFiles.filter((path) => !entries.some((entry) => entry.path === path));
    if (missingCritical.length > 0) {
        throw new Error(`Mandatory critical files are missing or excluded: ${missingCritical.join(", ")}`);
    }
    return {
        entries,
        envNames: [...envNamesSet].sort(),
        excludedCounts: Object.fromEntries([...excludedCounts.entries()].sort(([left], [right]) => left < right ? -1 : left > right ? 1 : 0)),
    };
}

function evidence(status, detail) {
    if (!STATUS_VALUES.includes(status)) {
        throw new Error(`Invalid evidence status: ${status}`);
    }
    return { status, detail };
}

function createManifest({ index, indexBytes, git, envNames, excludedCounts, strict }) {
    const criticalHashes = {};
    for (const path of criticalFiles) {
        const entry = index.entries.find((candidate) => candidate.path === path);
        if (entry) {
            criticalHashes[path] = entry.sha256;
        }
    }
    const manifest = {
        schema: RELEASE_CANDIDATE_SCHEMA,
        kind: "dovra-release-candidate-manifest",
        release_decision: "LOCALLY_VERIFIED/NO_GO",
        snapshot_sha256: index.snapshot_sha256,
        index_sha256: sha256(indexBytes),
        source_file_count: index.entries.length,
        critical_hashes: criticalHashes,
        env_example_names: envNames,
        visible_file_count: git.visible_paths.length,
        git,
        excluded_counts: excludedCounts,
        assembly: {
            strict_requested: strict,
            source_copy: "byte-for-byte regular files selected from git ls-files -co --exclude-standard -z",
            output_layout: "source/ plus index.json and manifest.json",
        },
        evidence: {
            code: evidence("BLOCKED", "Assembler completed; verifier has not yet validated this manifest."),
            local: evidence("NOT_RUN", "Verifier integrity result is not written back into the immutable manifest."),
            staging: evidence("NOT_RUN", "Staging deployment, smoke journeys, and staging observability were not run."),
            production: evidence("NOT_RUN", "Production deployment, secrets, providers, DNS, payments, and restore were not run."),
            market: evidence("EXTERNAL_BLOCKED", "Market, customer, traction, revenue, and investment evidence is external and absent."),
        },
        strict_requirements: [...strictRequirements],
        claims: {
            public_production: false,
            customers_or_pilots: false,
            traction_or_revenue: false,
            investment_readiness: false,
        },
        safety: {
            database_mutations: false,
            external_provider_calls: false,
            production_deployment: false,
            secret_values_copied: false,
        },
    };
    return manifest;
}

export function assembleReleaseCandidate({ source = scriptRoot, output, strict = false } = {}) {
    if (!output) {
        throw new Error("An isolated output directory is required.");
    }
    const sourceResolved = resolve(source);
    const outputResolved = resolve(output);
    assertSafePaths(sourceResolved, outputResolved);
    const outputSource = prepareOutput(outputResolved);
    const gitSnapshot = readGitSnapshot(sourceResolved);
    const copied = copyVisibleFiles(sourceResolved, outputSource, gitSnapshot.visible);
    const index = {
        schema: RELEASE_CANDIDATE_SCHEMA,
        kind: "dovra-release-candidate-index",
        canonicalization: "UTF-8 JSON.stringify(entries), entries sorted by path then size then sha256",
        entries: copied.entries,
    };
    index.snapshot_sha256 = sha256(canonicalJson(index.entries));
    const indexBytes = Buffer.from(`${JSON.stringify(index, null, 2)}\n`, "utf8");
    writeFileSync(resolve(outputResolved, "index.json"), indexBytes);
    const manifest = createManifest({
        index,
        indexBytes,
        git: {
            ...gitSnapshot.git,
            visible_paths: [...gitSnapshot.visible].map(toPosixPath).sort(),
        },
        envNames: copied.envNames,
        excludedCounts: copied.excludedCounts,
        strict,
    });
    const manifestBytes = Buffer.from(`${JSON.stringify(manifest, null, 2)}\n`, "utf8");
    writeFileSync(resolve(outputResolved, "manifest.json"), manifestBytes);

    return {
        output: outputResolved,
        index,
        manifest,
        strictPass: false,
    };
}

function printUsage() {
    console.log("Usage: node scripts/qa/assemble-dovra-rc.mjs --output <isolated-dir> [--source <checkout>] [--strict] [--json]");
    console.log("Copies the visible Git checkout into source/ and writes deterministic index.json and evidence-bound manifest.json.");
}

if (process.argv[1] && resolve(process.argv[1]) === resolve(fileURLToPath(import.meta.url))) {
    try {
        const options = parseArguments(process.argv.slice(2));
        if (options.help) {
            printUsage();
            process.exit(0);
        }
        const result = assembleReleaseCandidate(options);
        const summary = {
            status: "LOCALLY_VERIFIED/NO_GO",
            output: result.output,
            source_file_count: result.index.entries.length,
            snapshot_sha256: result.index.snapshot_sha256,
            strict: options.strict,
        };
        if (options.json) {
            console.log(JSON.stringify(summary, null, 2));
        } else {
            console.log(`status: ${summary.status}`);
            console.log(`output: ${summary.output}`);
            console.log(`source_file_count: ${summary.source_file_count}`);
            console.log(`snapshot_sha256: ${summary.snapshot_sha256}`);
            if (options.strict) {
                console.log("strict: blocked (runtime, staging, production, and external evidence are not certified)");
            }
        }
        if (options.strict) {
            process.exitCode = 1;
        }
    } catch (error) {
        console.error(error instanceof Error ? error.message : String(error));
        process.exitCode = 1;
    }
}
