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
import { secretSignatureDetections } from "./dovra-rc-secret-policy.mjs";

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
        const detected = secretSignatureDetections(content, candidate);
        if (detected.length > 0) {
            secretDetections.push(...detected.map(({ line, type }) => ({ path: candidate, line, type })));
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
            .map(({ path, line, type }) => `${path}:${line}:${type}`)
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
