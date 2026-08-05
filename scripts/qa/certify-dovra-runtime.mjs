import {
    closeSync,
    constants as fsConstants,
    existsSync,
    fstatSync,
    fsyncSync,
    lstatSync,
    openSync,
    readFileSync,
    realpathSync,
    unlinkSync,
    writeSync,
} from "node:fs";
import { spawnSync } from "node:child_process";
import { createHash } from "node:crypto";
import { dirname, basename, isAbsolute, relative, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

import { verifyReleaseCandidate } from "./verify-dovra-rc.mjs";

export const RUNTIME_CERTIFICATE_SCHEMA = 1;
export const DEFAULT_SNAPSHOT = "be6c73ae2a4d4e442994ce7acf689770c55981c79687e2915061ab9e99e0a50b";
export const DEFAULT_TAG = "dovra-rc:be6c73ae2a4d";
export const DEFAULT_DOCKER_TIMEOUT_MS = 120_000;
export const DEFAULT_DOCKER_MAX_BUFFER = 4 * 1024 * 1024;
export const MAX_CONTEXT_BYTES = 128 * 1024 * 1024;
export const MAX_FILE_BYTES = 32 * 1024 * 1024;

export const DOCKER_POLICIES = Object.freeze({
    daemon: Object.freeze({ timeout: 10_000, maxBuffer: 1 * 1024 * 1024 }),
    build: Object.freeze({ timeout: 30 * 60 * 1000, maxBuffer: 128 * 1024 * 1024 }),
    inspect: Object.freeze({ timeout: 120_000, maxBuffer: 16 * 1024 * 1024 }),
    runtime: Object.freeze({ timeout: 120_000, maxBuffer: 16 * 1024 * 1024 }),
});

const scriptRoot = resolve(dirname(fileURLToPath(import.meta.url)), "../..");
const DEFAULT_FILE_OPS = Object.freeze({ openSync, writeSync, fsyncSync, closeSync, unlinkSync });
const requiredRuntimeCommands = [
    { id: "php_version", label: "PHP 8.4.x", args: (tag) => ["run", "--rm", "--entrypoint", "php", tag, "-v"] },
    { id: "composer_validate", label: "Composer validate strict", args: (tag) => ["run", "--rm", "--entrypoint", "composer", tag, "validate", "--strict", "--no-check-publish"] },
    { id: "composer_platform", label: "Composer platform requirements", args: (tag) => ["run", "--rm", "--entrypoint", "composer", tag, "check-platform-reqs", "--no-dev"] },
    { id: "artisan_version", label: "Laravel 13 version", args: (tag) => ["run", "--rm", "--entrypoint", "php", tag, "artisan", "--version"] },
    { id: "artisan_about", label: "Laravel environment", args: (tag) => ["run", "--rm", "--entrypoint", "php", tag, "artisan", "about", "--only=environment"] },
];

function sha256(value) {
    return createHash("sha256").update(value).digest("hex");
}

function compareText(left, right) {
    return left < right ? -1 : left > right ? 1 : 0;
}

function pathExists(path) {
    try {
        lstatSync(path);
        return true;
    } catch {
        return false;
    }
}

function isInside(parent, child) {
    const fromParent = relative(parent, child);
    return fromParent === "" || (!fromParent.startsWith("..") && !isAbsolute(fromParent));
}

function isCheckout(path) {
    return pathExists(resolve(path, ".git"));
}

function assertNoLinkChain(path, label) {
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
        if (lstatSync(component).isSymbolicLink()) {
            throw new Error(`${label} contains a symlink or junction: ${component}`);
        }
    }
}

function parseArguments(argv) {
    const options = {
        bundle: null,
        expectedSnapshot: DEFAULT_SNAPSHOT,
        tag: DEFAULT_TAG,
        report: null,
        json: false,
        help: false,
    };
    for (let index = 0; index < argv.length; index += 1) {
        const argument = argv[index];
        if (argument === "--bundle" || argument === "--input") {
            options.bundle = argv[++index] ?? "";
        } else if (argument.startsWith("--bundle=") || argument.startsWith("--input=")) {
            options.bundle = argument.slice(argument.indexOf("=") + 1);
        } else if (argument === "--snapshot" || argument === "--expected-snapshot") {
            options.expectedSnapshot = argv[++index] ?? "";
        } else if (argument.startsWith("--snapshot=") || argument.startsWith("--expected-snapshot=")) {
            options.expectedSnapshot = argument.slice(argument.indexOf("=") + 1);
        } else if (argument === "--tag") {
            options.tag = argv[++index] ?? "";
        } else if (argument.startsWith("--tag=")) {
            options.tag = argument.slice("--tag=".length);
        } else if (argument === "--report") {
            options.report = argv[++index] ?? "";
        } else if (argument.startsWith("--report=")) {
            options.report = argument.slice("--report=".length);
        } else if (argument === "--json") {
            options.json = true;
        } else if (argument === "--help" || argument === "-h") {
            options.help = true;
        } else {
            throw new Error(`Unknown argument: ${argument}`);
        }
    }
    if (!options.bundle && !options.help) {
        throw new Error("--bundle is required.");
    }
    if (!options.report && !options.help) {
        throw new Error("--report is required and must be a new path outside the bundle.");
    }
    return options;
}

export function runDockerCommand(args, options = {}) {
    const result = spawnSync("docker", args, {
        cwd: options.cwd,
        encoding: "utf8",
        shell: false,
        windowsHide: true,
        timeout: options.timeout ?? DEFAULT_DOCKER_TIMEOUT_MS,
        maxBuffer: options.maxBuffer ?? DEFAULT_DOCKER_MAX_BUFFER,
        ...(options.input === undefined ? {} : { input: options.input }),
    });
    return {
        status: result.status ?? 1,
        stdout: result.stdout ?? "",
        stderr: result.stderr ?? "",
        error: result.error ? { code: result.error.code ?? "DOCKER_EXECUTION_ERROR", message: result.error.message } : null,
    };
}

function assertDockerArguments(args) {
    for (const [index, argument] of args.entries()) {
        const lowered = String(argument).toLowerCase();
        if (["--mount", "--volume", "--env-file"].includes(lowered)) {
            throw new Error(`FORBIDDEN_DOCKER_ARGUMENT: ${argument}`);
        }
        if (lowered === "-v") {
            const entrypointIndex = args.indexOf("--entrypoint");
            const isPhpVersion = entrypointIndex >= 0
                && String(args[entrypointIndex + 1] ?? "").toLowerCase() === "php"
                && index === args.length - 1;
            if (!isPhpVersion) {
                throw new Error(`FORBIDDEN_DOCKER_ARGUMENT: ${argument}`);
            }
        }
        if (lowered.includes(".env") || /(?:^|[-_])(?:compose|secret|provider)(?:$|[-_])/u.test(lowered)) {
            throw new Error(`FORBIDDEN_DOCKER_ARGUMENT: ${argument}`);
        }
    }
}

function commandRecord(label, args, result, options = {}) {
    const error = result?.error ?? null;
    const status = Number.isInteger(result?.status) ? result.status : 1;
    return {
        label,
        args: [...args],
        status,
        outcome: status === 0 && error === null ? "PASS" : "FAIL",
        stdout: String(result?.stdout ?? "").trim(),
        stderr: String(error?.message ?? result?.stderr ?? "").trim(),
        error: error ? (error.code ?? "DOCKER_EXECUTION_ERROR") : null,
        timeout_ms: options.timeout,
        max_buffer: options.maxBuffer,
        stdin_sha256: options.input_sha256 ?? (options.input === undefined ? null : sha256(options.input)),
        stdin_bytes: options.input === undefined ? 0 : options.input.byteLength,
    };
}

function invokeDocker(runDocker, records, label, args, options = {}) {
    assertDockerArguments(args);
    const policy = options.policy ?? {};
    const executionOptions = {
        shell: false,
        encoding: "utf8",
        timeout: policy.timeout ?? options.timeout ?? DEFAULT_DOCKER_TIMEOUT_MS,
        maxBuffer: policy.maxBuffer ?? options.maxBuffer ?? DEFAULT_DOCKER_MAX_BUFFER,
        ...(options.input === undefined ? {} : { input: options.input }),
        ...(options.input_sha256 === undefined ? {} : { input_sha256: options.input_sha256 }),
    };
    try {
        const result = runDocker(args, executionOptions);
        const normalized = {
            status: Number.isInteger(result?.status) ? result.status : 1,
            stdout: String(result?.stdout ?? ""),
            stderr: String(result?.stderr ?? ""),
            error: result?.error ?? null,
        };
        records.push(commandRecord(label, args, normalized, executionOptions));
        return normalized;
    } catch (error) {
        const normalized = { status: 1, stdout: "", stderr: "", error: { code: error.code ?? "DOCKER_EXECUTION_ERROR", message: error.message } };
        records.push(commandRecord(label, args, normalized, executionOptions));
        return normalized;
    }
}

function writeReportExclusive(path, report, config) {
    const lexicalParent = dirname(path);
    if (!pathExists(lexicalParent) || !lstatSync(lexicalParent).isDirectory()) {
        throw new Error(`REPORT_PARENT_MISSING: ${lexicalParent}`);
    }
    assertNoLinkChain(lexicalParent, "Report parent");
    const canonicalParent = realpathSync(lexicalParent);
    if (canonicalParent !== config.reportParentReal) {
        throw new Error("REPORT_PARENT_CHANGED: report parent changed after validation.");
    }
    assertNoLinkChain(canonicalParent, "Canonical report parent");
    const canonicalReport = resolve(canonicalParent, basename(path));
    if (canonicalReport !== config.report) {
        throw new Error("REPORT_PATH_CHANGED: canonical report path changed after validation.");
    }
    if (isInside(config.bundleReal, canonicalReport)) {
        throw new Error("REPORT_INSIDE_BUNDLE: report must be outside the bundle.");
    }
    const flags = fsConstants.O_CREAT | fsConstants.O_EXCL | fsConstants.O_WRONLY | (fsConstants.O_NOFOLLOW ?? 0);
    let descriptor;
    let created = false;
    const bytes = Buffer.from(`${JSON.stringify(report, null, 2)}\n`, "utf8");
    const fileOps = config.fileOps ?? DEFAULT_FILE_OPS;
    try {
        descriptor = fileOps.openSync(canonicalReport, flags, 0o600);
        created = true;
        let offset = 0;
        while (offset < bytes.byteLength) {
            const written = fileOps.writeSync(descriptor, bytes, offset, bytes.byteLength - offset, null);
            if (!Number.isInteger(written) || written <= 0) {
                throw new Error("REPORT_WRITE_NO_PROGRESS: writer returned zero bytes.");
            }
            offset += written;
        }
        fileOps.fsyncSync(descriptor);
    } catch (error) {
        try {
            if (descriptor !== undefined) {
                fileOps.closeSync(descriptor);
            }
        } catch {
            // Preserve the original write/fsync error.
        }
        if (created) {
            try {
                fileOps.unlinkSync(canonicalReport);
            } catch {
                // Preserve the original write/fsync error.
            }
        }
        throw error;
    }
    try {
        if (descriptor !== undefined) {
            fileOps.closeSync(descriptor);
        }
    } catch (error) {
        if (created) {
            try {
                fileOps.unlinkSync(canonicalReport);
            } catch {
                // Preserve the original close error.
            }
        }
        throw error;
    }
}

function baseReport({ bundle, source, report, expectedSnapshot, tag, status, verification = null }) {
    return {
        schema: RUNTIME_CERTIFICATE_SCHEMA,
        kind: "dovra-runtime-certificate",
        status,
        decision: "NO_GO",
        bundle: {
            path: bundle,
            source_context: source,
            context_mode: "stdin-tar",
            expected_snapshot_sha256: expectedSnapshot,
            observed_snapshot_sha256: verification?.snapshot_sha256 ?? null,
            verification_status: verification?.status ?? null,
            verification_findings: verification?.findings?.length ?? null,
        },
        report_path: report,
        image: {
            tag,
            id: null,
            php_version: null,
            laravel_version: null,
        },
        dockerfile_sha256: null,
        base_images: [],
        tar_context: null,
        commands: [],
        daemon: null,
        build: null,
        runtime_checks: undefined,
        evidence: {
            code: { status: "BLOCKED", detail: "Runtime certification did not yet prove the bundle and command contract." },
            local: { status: "BLOCKED", detail: "No local PHP/Laravel runtime claim has been certified." },
            staging: { status: "NOT_RUN", detail: "Staging was not deployed or exercised." },
            production: { status: "NOT_RUN", detail: "Production was not deployed or exercised." },
            external: { status: "EXTERNAL_BLOCKED", detail: "Docker daemon/providers/production dependencies are external to repository proof." },
        },
        safety: {
            mounts: false,
            env_file: false,
            compose: false,
            secrets: false,
            provider_calls: false,
            database_mutations: false,
        },
        risks: [],
    };
}

function assertSafeTarPath(path) {
    if (typeof path !== "string" || path.length === 0 || path.includes("\0") || path.includes("\\") || path.startsWith("/") || /^[A-Za-z]:/u.test(path)) {
        throw new Error(`TAR_PATH_INVALID: ${path}`);
    }
    const segments = path.split("/");
    if (segments.some((segment) => segment === "" || segment === "." || segment === "..")) {
        throw new Error(`TAR_PATH_INVALID: ${path}`);
    }
    if (Buffer.byteLength(path, "utf8") > 255) {
        throw new Error(`TAR_PATH_TOO_LONG: ${path}`);
    }
}

function writeTarString(header, offset, length, value) {
    const bytes = Buffer.from(value, "utf8");
    if (bytes.byteLength > length) {
        throw new Error(`TAR_FIELD_TOO_LONG: ${value}`);
    }
    bytes.copy(header, offset);
}

function writeTarOctal(header, offset, length, value) {
    const text = Math.max(0, Number(value)).toString(8).padStart(length - 1, "0");
    if (text.length > length - 1) {
        throw new Error("TAR_NUMERIC_FIELD_TOO_LARGE");
    }
    header.write(text, offset, length - 1, "ascii");
    header[offset + length - 1] = 0;
}

function tarHeader(path, size) {
    const header = Buffer.alloc(512);
    const pathBytes = Buffer.byteLength(path, "utf8");
    if (pathBytes <= 100) {
        writeTarString(header, 0, 100, path);
    } else {
        const split = path.lastIndexOf("/");
        const prefix = split > 0 ? path.slice(0, split) : "";
        const name = split > 0 ? path.slice(split + 1) : path;
        if (!prefix || Buffer.byteLength(prefix, "utf8") > 155 || Buffer.byteLength(name, "utf8") > 100) {
            throw new Error(`TAR_PATH_TOO_LONG: ${path}`);
        }
        writeTarString(header, 0, 100, name);
        writeTarString(header, 345, 155, prefix);
    }
    writeTarOctal(header, 100, 8, 0o644);
    writeTarOctal(header, 108, 8, 0);
    writeTarOctal(header, 116, 8, 0);
    writeTarOctal(header, 124, 12, size);
    writeTarOctal(header, 136, 12, 0);
    header.fill(0x20, 148, 156);
    header[156] = 0x30;
    writeTarString(header, 257, 6, "ustar");
    writeTarString(header, 263, 2, "00");
    const checksum = header.reduce((total, byte) => total + byte, 0);
    const checksumText = checksum.toString(8).padStart(6, "0");
    header.write(checksumText, 148, 6, "ascii");
    header[154] = 0;
    header[155] = 0x20;
    return header;
}

function paddedTarEntryBytes(size) {
    const padding = (512 - (size % 512)) % 512;
    return 512 + size + padding;
}

export function validateContextSizes(entries) {
    if (!Array.isArray(entries)) {
        throw new Error("TAR_ENTRIES_INVALID: entries must be an array.");
    }
    let total = 1024;
    for (const entry of entries) {
        const size = entry?.size;
        if (!Number.isSafeInteger(size) || size < 0) {
            throw new Error(`CONTEXT_SIZE_INVALID: ${entry?.path ?? "unknown"}`);
        }
        if (size > MAX_FILE_BYTES) {
            throw new Error(`CONTEXT_FILE_TOO_LARGE: ${entry.path}`);
        }
        const entryBytes = paddedTarEntryBytes(size);
        if (!Number.isSafeInteger(entryBytes) || entryBytes > MAX_CONTEXT_BYTES - total) {
            throw new Error(`CONTEXT_TOO_LARGE: ${entry?.path ?? "unknown"}`);
        }
        total += entryBytes;
    }
    return total;
}

export function buildDeterministicTar(entries) {
    if (!Array.isArray(entries)) {
        throw new Error("TAR_ENTRIES_INVALID: entries must be an array.");
    }
    const normalized = entries.map((entry) => {
        assertSafeTarPath(entry?.path);
        if (entry?.type !== undefined && entry.type !== "file") {
            throw new Error(`TAR_ENTRY_INVALID: ${entry.path}`);
        }
        const size = entry?.size ?? (Buffer.isBuffer(entry?.data) ? entry.data.byteLength : null);
        return { path: entry.path, size, data: entry?.data };
    }).sort((left, right) => compareText(left.path, right.path));
    const total = validateContextSizes(normalized);
    const seen = new Set();
    for (const entry of normalized) {
        if (seen.has(entry.path)) {
            throw new Error(`TAR_DUPLICATE_PATH: ${entry.path}`);
        }
        seen.add(entry.path);
        if (!Buffer.isBuffer(entry.data) || entry.data.byteLength !== entry.size) {
            throw new Error(`TAR_ENTRY_INVALID: ${entry.path}`);
        }
    }
    const tar = Buffer.alloc(total);
    let offset = 0;
    for (const entry of normalized) {
        tarHeader(entry.path, entry.size).copy(tar, offset);
        offset += 512;
        entry.data.copy(tar, offset);
        offset += entry.size;
        const padding = (512 - (entry.size % 512)) % 512;
        if (padding > 0) {
            tar.fill(0, offset, offset + padding);
            offset += padding;
        }
    }
    tar.fill(0, offset);
    return tar;
}

function assertIndexEntries(index, verification) {
    if (!index || index.kind !== "dovra-release-candidate-index" || !Array.isArray(index.entries)) {
        throw new Error("INDEX_INVALID: signed index entries are missing.");
    }
    if (index.snapshot_sha256 !== verification.snapshot_sha256 || sha256(JSON.stringify(index.entries)) !== index.snapshot_sha256) {
        throw new Error("INDEX_SNAPSHOT_MISMATCH: signed index changed after bundle verification.");
    }
    const sorted = [...index.entries].sort((left, right) => compareText(left.path, right.path) || left.size - right.size || compareText(left.sha256, right.sha256));
    if (JSON.stringify(sorted) !== JSON.stringify(index.entries)) {
        throw new Error("INDEX_ORDER_INVALID: signed index is not deterministically sorted.");
    }
    for (const entry of index.entries) {
        assertSafeTarPath(entry?.path);
        if (!Number.isSafeInteger(entry.size) || entry.size < 0 || !/^[a-f0-9]{64}$/u.test(entry.sha256 ?? "")) {
            throw new Error(`INDEX_ENTRY_INVALID: ${entry?.path ?? "unknown"}`);
        }
    }
    validateContextSizes(index.entries);
}

function sameFileIdentity(before, after) {
    return before.isFile() && after.isFile()
        && before.dev === after.dev
        && before.ino === after.ino
        && before.size === after.size;
}

function readVerifiedContext(bundle, source, verification) {
    const index = JSON.parse(readFileSync(resolve(bundle, "index.json"), "utf8"));
    assertIndexEntries(index, verification);
    const tar = Buffer.alloc(validateContextSizes(index.entries));
    let tarOffset = 0;
    let dockerfileText = null;
    let dockerfileSha256 = null;
    for (const entry of index.entries) {
        const absolutePath = resolve(source, ...entry.path.split("/"));
        if (!isInside(source, absolutePath)) {
            throw new Error(`INDEX_PATH_ESCAPE: ${entry.path}`);
        }
        assertNoLinkChain(absolutePath, `Bundle source file ${entry.path}`);
        let descriptor;
        try {
            descriptor = openSync(absolutePath, fsConstants.O_RDONLY | (fsConstants.O_NOFOLLOW ?? 0));
            const before = fstatSync(descriptor);
            if (!before.isFile()) {
                throw new Error(`INDEX_FILE_NOT_REGULAR: ${entry.path}`);
            }
            const data = readFileSync(descriptor);
            const after = fstatSync(descriptor);
            if (!sameFileIdentity(before, after) || data.byteLength !== entry.size || sha256(data) !== entry.sha256) {
                throw new Error(`INDEX_FILE_CHANGED: ${entry.path}`);
            }
            tarHeader(entry.path, entry.size).copy(tar, tarOffset);
            tarOffset += 512;
            data.copy(tar, tarOffset);
            tarOffset += entry.size;
            const padding = (512 - (entry.size % 512)) % 512;
            if (padding > 0) {
                tar.fill(0, tarOffset, tarOffset + padding);
                tarOffset += padding;
            }
            if (entry.path === "Dockerfile") {
                dockerfileText = data.toString("utf8");
                dockerfileSha256 = sha256(data);
            }
        } catch (error) {
            if (error?.code === "ELOOP") {
                throw new Error(`INDEX_LINK_REJECTED: ${entry.path}`);
            }
            throw error;
        } finally {
            if (descriptor !== undefined) {
                closeSync(descriptor);
            }
        }
    }
    tar.fill(0, tarOffset);
    if (dockerfileText === null) {
        throw new Error("DOCKERFILE_MISSING: signed context does not contain Dockerfile.");
    }
    return { index, tar, dockerfileText, dockerfileSha256, fileCount: index.entries.length };
}

function parseBaseImages(dockerfile) {
    const lines = String(dockerfile).split(/\r?\n/u);
    const stageNames = new Set();
    const images = [];
    for (const line of lines) {
        const from = line.match(/^\s*FROM\s+([^\s]+)(?:\s+AS\s+([^\s]+))?\s*$/iu);
        if (from) {
            const reference = from[1];
            images.push({ reference, source: "FROM", digest_pinned: /@sha256:[a-f0-9]{64}$/iu.test(reference) });
            if (from[2]) stageNames.add(from[2].toLowerCase());
            continue;
        }
        const copy = line.match(/^\s*COPY\s+--from=([^\s]+)\s+/iu);
        if (copy) {
            const reference = copy[1];
            if (!stageNames.has(reference.toLowerCase()) && !/^\d+$/u.test(reference)) {
                images.push({ reference, source: "COPY --from", digest_pinned: /@sha256:[a-f0-9]{64}$/iu.test(reference) });
            }
        }
    }
    return images;
}

const EXTERNAL_DOCKER_FAILURE_CODES = new Set([
    "DOCKER_DAEMON_UNAVAILABLE",
    "DOCKER_CLI_UNAVAILABLE",
    "ENOENT",
    "ECONNREFUSED",
]);

const EXTERNAL_DOCKER_FAILURE_PATTERNS = [
    /cannot connect to (?:the )?docker (?:daemon|engine)/u,
    /(?:docker )?daemon (?:is )?not running/u,
    /connection refused/u,
    /(?:npipe:|unix:)[^\r\n]*(?:cannot find|not found|no such file|missing)/u,
];

function classifyDaemonFailure(result) {
    if (result.status === 0) {
        return "PASS";
    }
    const code = String(result.error?.code ?? "").toUpperCase();
    const detail = `${code} ${result.error?.message ?? ""} ${result.stderr ?? ""}`.toLowerCase();
    if (EXTERNAL_DOCKER_FAILURE_CODES.has(code) || EXTERNAL_DOCKER_FAILURE_PATTERNS.some((pattern) => pattern.test(detail))) {
        return "EXTERNAL_BLOCKED";
    }
    return "FAIL";
}

function runtimeCheck(id, label, result, predicate = () => true) {
    const output = `${result.stdout}\n${result.stderr}`;
    const passed = result.status === 0 && predicate(output);
    return {
        id,
        label,
        status: passed ? "PASS" : "FAIL",
        exit_code: result.status,
        observed: output.trim(),
        error: result.error?.code ?? null,
        php_version: id === "php_version" ? (output.match(/PHP\s+(\d+\.\d+\.\d+)/iu)?.[1] ?? null) : undefined,
        laravel_version: id === "artisan_version" ? (output.match(/Laravel Framework\s+(\d+\.\d+\.\d+)/iu)?.[1] ?? null) : undefined,
    };
}

function assertConfiguration({ bundle, report, expectedSnapshot, tag }) {
    const bundleResolved = resolve(bundle);
    const reportResolved = resolve(report);
    if (!pathExists(bundleResolved) || !lstatSync(bundleResolved).isDirectory()) {
        throw new Error("BUNDLE_INVALID: bundle directory is missing or not a directory.");
    }
    assertNoLinkChain(bundleResolved, "Bundle");
    if (isCheckout(bundleResolved)) {
        throw new Error("BUNDLE_CHECKOUT_REJECTED: bundle may not be a checkout.");
    }
    const bundleReal = realpathSync(bundleResolved);
    if (pathExists(reportResolved)) {
        throw new Error("REPORT_EXISTS: report path must be new and exclusive.");
    }
    const reportParent = dirname(reportResolved);
    if (!pathExists(reportParent) || !lstatSync(reportParent).isDirectory()) {
        throw new Error(`REPORT_PARENT_MISSING: ${reportParent}`);
    }
    assertNoLinkChain(reportParent, "Report parent");
    const reportParentReal = realpathSync(reportParent);
    const canonicalReport = resolve(reportParentReal, basename(reportResolved));
    if (isInside(bundleReal, canonicalReport)) {
        throw new Error("REPORT_INSIDE_BUNDLE: report must be outside the bundle.");
    }
    if (!/^[a-f0-9]{64}$/u.test(expectedSnapshot)) {
        throw new Error("SNAPSHOT_INVALID: expected snapshot must be a 64-character SHA-256.");
    }
    if (!/^[A-Za-z0-9][A-Za-z0-9_.-]*(?::[A-Za-z0-9][A-Za-z0-9_.-]*)?$/u.test(tag)) {
        throw new Error("TAG_INVALID: image tag is not valid.");
    }
    const source = resolve(bundleResolved, "source");
    assertNoLinkChain(source, "Bundle source");
    return { bundle: bundleResolved, bundleReal, report: canonicalReport, reportParentReal, source };
}

function writeFailure(config, reportData) {
    writeReportExclusive(config.report, reportData, config);
    return reportData;
}

export function certifyDovraRuntime(options = {}, dependencies = {}) {
    const config = assertConfiguration(options);
    config.fileOps = { ...DEFAULT_FILE_OPS, ...(dependencies.fileOps ?? {}) };
    const runDocker = dependencies.runDocker ?? runDockerCommand;
    const records = [];
    const verification = verifyReleaseCandidate({ input: config.bundle });
    const reportData = baseReport({
        bundle: config.bundle,
        source: config.source,
        report: config.report,
        expectedSnapshot: options.expectedSnapshot,
        tag: options.tag,
        status: "FAIL",
        verification,
    });
    reportData.commands = records;
    if (verification.status !== "PASS" || verification.findings.length !== 0) {
        reportData.evidence.code = { status: "FAIL", detail: "Bundle verifier did not return PASS with zero findings." };
        reportData.evidence.local = { status: "BLOCKED", detail: "Runtime execution was not attempted after bundle verification failure." };
        reportData.bundle.verification_findings_detail = verification.findings;
        return writeFailure(config, reportData);
    }
    if (verification.snapshot_sha256 !== options.expectedSnapshot) {
        reportData.evidence.code = { status: "FAIL", detail: "Observed bundle snapshot differs from the required expected snapshot." };
        return writeFailure(config, reportData);
    }

    let context;
    try {
        context = readVerifiedContext(config.bundle, config.source, verification);
    } catch (error) {
        reportData.evidence.code = { status: "FAIL", detail: `Verified stdin context could not be assembled: ${error.message}` };
        reportData.evidence.local = { status: "BLOCKED", detail: "Docker was not invoked after signed context verification failed." };
        reportData.status = "FAIL";
        return writeFailure(config, reportData);
    }
    const dockerfile = context.dockerfileText;
    const signedTarSha256 = sha256(context.tar);
    reportData.dockerfile_sha256 = context.dockerfileSha256;
    reportData.base_images = parseBaseImages(dockerfile);
    reportData.tar_context = {
        mode: "stdin",
        bytes: context.tar.byteLength,
        sha256: signedTarSha256,
        file_count: context.fileCount,
        snapshot_sha256: context.index.snapshot_sha256,
    };
    reportData.risks = reportData.base_images.filter((image) => !image.digest_pinned).map((image) => `Mutable image reference: ${image.reference}`);

    const daemon = invokeDocker(runDocker, records, "docker version", ["version", "--format", "{{.Server.Version}}"], { policy: DOCKER_POLICIES.daemon });
    const daemonClassification = classifyDaemonFailure(daemon);
    reportData.daemon = {
        status: daemonClassification,
        version: daemon.stdout.trim() || null,
        error: daemon.error?.code ?? null,
    };
    if (daemon.status !== 0) {
        if (daemonClassification === "FAIL") {
            reportData.status = "FAIL";
            reportData.evidence.code = { status: "FAIL", detail: "Docker daemon invocation failed under its bounded execution policy." };
            reportData.evidence.local = { status: "BLOCKED", detail: "Runtime execution was blocked by a local Docker executor failure." };
            reportData.evidence.external = { status: "BLOCKED", detail: "The Docker invocation failed before an external daemon-availability determination." };
            reportData.build = { status: "NOT_RUN", reason: "daemon_invocation_failed", context: "stdin-tar" };
            return writeFailure(config, reportData);
        }
        reportData.status = "EXTERNAL_BLOCKED";
        reportData.evidence.code = { status: "PASS", detail: "Bundle verification passed with zero findings and stdin tar bytes matched the signed index." };
        reportData.evidence.local = { status: "BLOCKED", detail: "Docker daemon was unavailable; no build or runtime command was attempted." };
        reportData.evidence.external = { status: "EXTERNAL_BLOCKED", detail: "Docker daemon preflight failed; runtime certification is externally blocked." };
        reportData.build = { status: "NOT_RUN", reason: "daemon_unavailable", context: "stdin-tar" };
        return writeFailure(config, reportData);
    }

    const buildInput = Buffer.from(context.tar);
    const build = invokeDocker(runDocker, records, "docker build", ["build", "--tag", options.tag, "-"], { policy: DOCKER_POLICIES.build, input: buildInput, input_sha256: signedTarSha256 });
    reportData.build = { status: build.status === 0 ? "PASS" : "FAIL", context: "stdin-tar", stdin_sha256: signedTarSha256 };
    if (sha256(buildInput) !== signedTarSha256) {
        reportData.build = { status: "FAIL", context: "stdin-tar", stdin_sha256: signedTarSha256, reason: "executor_mutated_stdin" };
        reportData.evidence.code = { status: "PASS", detail: "Bundle verification and signed stdin context assembly passed." };
        reportData.evidence.local = { status: "FAIL", detail: "Docker executor mutated the stdin tar buffer after invocation; image certification was stopped." };
        reportData.status = "FAIL";
        return writeFailure(config, reportData);
    }
    if (build.status !== 0) {
        reportData.evidence.code = { status: "PASS", detail: "Bundle verification and signed stdin context assembly passed." };
        reportData.evidence.local = { status: "FAIL", detail: "Docker image build failed." };
        reportData.status = "FAIL";
        return writeFailure(config, reportData);
    }

    const imageInspect = invokeDocker(runDocker, records, "docker image inspect", ["image", "inspect", options.tag, "--format", "{{.Id}}"], { policy: DOCKER_POLICIES.inspect });
    const imageId = imageInspect.stdout.trim();
    if (imageInspect.status !== 0 || !/^sha256:[a-f0-9]{64}$/u.test(imageId)) {
        reportData.evidence.code = { status: "PASS", detail: "Bundle verification and image build passed." };
        reportData.evidence.local = { status: "FAIL", detail: "Built image ID was missing or was not a canonical sha256 digest." };
        reportData.status = "FAIL";
        return writeFailure(config, reportData);
    }
    reportData.image.id = imageId;

    const checks = [];
    for (const command of requiredRuntimeCommands) {
        const result = invokeDocker(runDocker, records, command.label, command.args(options.tag), { policy: DOCKER_POLICIES.runtime });
        const predicate = command.id === "php_version"
            ? (output) => /PHP\s+8\.4\.\d+/iu.test(output)
            : command.id === "artisan_version"
                ? (output) => /Laravel Framework\s+13\.\d+/iu.test(output)
                : () => true;
        checks.push(runtimeCheck(command.id, command.label, result, predicate));
        if (command.id === "php_version") {
            reportData.image.php_version = checks.at(-1).php_version;
        }
        if (command.id === "artisan_version") {
            reportData.image.laravel_version = checks.at(-1).laravel_version;
        }
    }
    reportData.runtime_checks = checks;
    const localPass = checks.every((check) => check.status === "PASS");
    reportData.status = localPass ? "PASS" : "FAIL";
    reportData.evidence.code = { status: "PASS", detail: "Bundle verification and signed stdin context assembly passed." };
    reportData.evidence.local = { status: localPass ? "PASS" : "FAIL", detail: localPass ? "PHP 8.4.x/Laravel 13.x and required Composer/Artisan checks passed inside the tagged image." : "One or more PHP/Laravel/Composer runtime checks failed." };
    return writeFailure(config, reportData);
}

function printUsage() {
    console.log("Usage: node scripts/qa/certify-dovra-runtime.mjs --bundle <bundle> --snapshot <sha256> --tag <image-tag> --report <new-report.json> [--json]");
    console.log("Verifies a release bundle, assembles a deterministic stdin tar, preflights Docker, and (only when available) certifies an isolated PHP 8.4/Laravel 13 image without mounts or provider calls.");
}

if (process.argv[1] && resolve(process.argv[1]) === resolve(fileURLToPath(import.meta.url))) {
    try {
        const options = parseArguments(process.argv.slice(2));
        if (options.help) {
            printUsage();
            process.exit(0);
        }
        const report = certifyDovraRuntime(options);
        if (options.json) {
            console.log(JSON.stringify(report, null, 2));
        } else {
            console.log(`status: ${report.status}`);
            console.log(`decision: ${report.decision}`);
            console.log(`report: ${options.report}`);
            console.log(`snapshot: ${report.bundle.observed_snapshot_sha256 ?? "unknown"}`);
        }
        process.exitCode = report.status === "PASS" ? 0 : 1;
    } catch (error) {
        console.error(error instanceof Error ? error.message : String(error));
        process.exitCode = 1;
    }
}
