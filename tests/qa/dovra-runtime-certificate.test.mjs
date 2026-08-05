import test from "node:test";
import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import {
    closeSync as fsCloseSync,
    existsSync,
    fsyncSync,
    mkdtempSync,
    mkdirSync,
    openSync as fsOpenSync,
    readFileSync,
    rmSync,
    unlinkSync,
    writeFileSync,
    writeSync as fsWriteSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join, resolve } from "node:path";

import { assembleReleaseCandidate } from "../../scripts/qa/assemble-dovra-rc.mjs";
import {
    buildDeterministicTar,
    certifyDovraRuntime,
    MAX_CONTEXT_BYTES,
    MAX_FILE_BYTES,
} from "../../scripts/qa/certify-dovra-runtime.mjs";

const repositoryRoot = resolve(import.meta.dirname, "../..");
const expectedSnapshot = "be6c73ae2a4d4e442994ce7acf689770c55981c79687e2915061ab9e99e0a50b";
const expectedTag = "dovra-rc:be6c73ae2a4d";

function git(cwd, args) {
    return execFileSync("git", args, { cwd, encoding: "utf8", stdio: ["ignore", "pipe", "pipe"] });
}

function writeFixtureFile(root, relativePath, content) {
    const target = join(root, relativePath);
    mkdirSync(dirname(target), { recursive: true });
    writeFileSync(target, content);
}

function createBundle(t) {
    const source = mkdtempSync(join(tmpdir(), "dovra-runtime-source-"));
    writeFixtureFile(source, "app/main.php", "<?php return ['ok' => true];\n");
    writeFixtureFile(source, "composer.lock", "{\"packages\":[]}");
    writeFixtureFile(source, "package-lock.json", "{\"lockfileVersion\":3}");
    writeFixtureFile(source, "Dockerfile", [
        "FROM node:22.13-bookworm-slim AS frontend",
        "FROM dunglas/frankenphp:1-php8.4-bookworm AS runtime",
        "COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer",
        "COPY --from=frontend /app/public/build ./public/build",
        "",
    ].join("\n"));
    writeFixtureFile(source, ".env.example", "APP_ENV=local\nAPP_KEY=\n");
    git(source, ["init", "-q"]);
    git(source, ["config", "user.email", "qa@example.invalid"]);
    git(source, ["config", "user.name", "Dovra QA"]);
    git(source, ["add", "."]);
    git(source, ["commit", "-qm", "runtime fixture"]);
    const outputParent = mkdtempSync(join(tmpdir(), "dovra-runtime-bundle-parent-"));
    const bundle = join(outputParent, "bundle");
    assembleReleaseCandidate({ source, output: bundle });
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(outputParent, { recursive: true, force: true });
    });
    return { source, bundle, outputParent };
}

function reportPath() {
    const parent = mkdtempSync(join(tmpdir(), "dovra-runtime-report-parent-"));
    return { parent, path: join(parent, "runtime-certificate.json") };
}

function fakeDocker({ daemon = true, php = "8.4.23", laravel = "13.23.0", platform = true } = {}) {
    const calls = [];
    const runDocker = (args, options) => {
        calls.push({ args: [...args], options: { ...options } });
        if (args[0] === "version") {
            if (!daemon) {
                const error = new Error("Cannot connect to the Docker daemon");
                error.code = "DOCKER_DAEMON_UNAVAILABLE";
                throw error;
            }
            return { status: 0, stdout: "27.5.1\n", stderr: "" };
        }
        if (args[0] === "build") {
            return { status: 0, stdout: "Successfully built fake-image\n", stderr: "" };
        }
        if (args[0] === "image" && args[1] === "inspect") {
            return { status: 0, stdout: `sha256:${"a".repeat(64)}\n`, stderr: "" };
        }
        if (args[0] === "run" && args.includes("php") && args.includes("-v")) {
            return { status: 0, stdout: `PHP ${php} (cli)\n`, stderr: "" };
        }
        if (args[0] === "run" && args.includes("artisan") && args.includes("--version")) {
            return { status: 0, stdout: `Laravel Framework ${laravel}\n`, stderr: "" };
        }
        if (args[0] === "run" && args.includes("artisan") && args.includes("about")) {
            return { status: 0, stdout: "Environment ................................ local\n", stderr: "" };
        }
        if (args[0] === "run" && args.includes("composer") && args.includes("validate")) {
            return { status: 0, stdout: "./composer.json is valid\n", stderr: "" };
        }
        if (args[0] === "run" && args.includes("composer") && args.includes("check-platform-reqs")) {
            return platform
                ? { status: 0, stdout: "php 8.4.23 success\n", stderr: "" }
                : { status: 1, stdout: "php 8.4.23 failed\n", stderr: "platform requirement failed\n" };
        }
        return { status: 1, stdout: "", stderr: `unexpected fake docker args: ${args.join(" ")}` };
    };
    return { calls, runDocker };
}

function cleanReport(report) {
    rmSync(report.parent, { recursive: true, force: true });
}

function bundleSnapshot(bundle) {
    return JSON.parse(readFileSync(join(bundle, "index.json"), "utf8")).snapshot_sha256;
}

test("rejects a wrong expected snapshot before invoking Docker", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    const docker = fakeDocker();
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: "0".repeat(64), tag: expectedTag, report: report.path }, { runDocker: docker.runDocker });
    assert.equal(result.status, "FAIL");
    assert.equal(docker.calls.length, 0);
    assert.equal(JSON.parse(readFileSync(report.path, "utf8")).status, "FAIL");
});

test("rejects a bundle verification failure before invoking Docker", (t) => {
    const { bundle } = createBundle(t);
    writeFixtureFile(bundle, "source/app/main.php", "<?php return ['tampered' => true];\n");
    const report = reportPath();
    t.after(() => cleanReport(report));
    const docker = fakeDocker();
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, { runDocker: docker.runDocker });
    assert.equal(result.status, "FAIL");
    assert.equal(docker.calls.length, 0);
});

test("rejects the removed --context CLI option before writing a report", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    assert.throws(() => execFileSync(process.execPath, [
        resolve(repositoryRoot, "scripts/qa/certify-dovra-runtime.mjs"),
        "--bundle", bundle,
        "--snapshot", bundleSnapshot(bundle),
        "--tag", expectedTag,
        "--report", report.path,
        "--context", bundle,
    ], { cwd: repositoryRoot, encoding: "utf8", stdio: ["ignore", "pipe", "pipe"] }), /Unknown argument: --context/u);
    assert.equal(existsSync(report.path), false);
});

test("never emits mounts, env-file, compose, secret, or provider Docker arguments", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    const docker = fakeDocker();
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, { runDocker: docker.runDocker });
    assert.equal(result.status, "PASS");
    const commandArgs = docker.calls.flatMap((call) => call.args).map((argument) => argument.toLowerCase());
    for (const forbidden of ["--mount", "--volume", "--env-file"]) {
        assert.equal(commandArgs.includes(forbidden), false, `forbidden Docker argument: ${forbidden}`);
    }
    const phpVersionCall = docker.calls.find((call) => call.args.includes("-v"));
    assert.deepEqual(phpVersionCall?.args, ["run", "--rm", "--entrypoint", "php", expectedTag, "-v"]);
    assert.equal(commandArgs.some((argument) => /(?:^|[-_])(?:compose|secret|provider)(?:$|[-_])|\.env/u.test(argument)), false);
    assert.ok(docker.calls.every((call) => call.options.shell === false));
});

test("uses explicit entrypoints and bounded timeout/maxBuffer for every runtime command", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    const docker = fakeDocker();
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, { runDocker: docker.runDocker });
    assert.equal(result.status, "PASS");
    const runtimeCalls = docker.calls.filter((call) => call.args[0] === "run");
    assert.deepEqual(runtimeCalls.map((call) => call.args), [
        ["run", "--rm", "--entrypoint", "php", expectedTag, "-v"],
        ["run", "--rm", "--entrypoint", "composer", expectedTag, "validate", "--strict", "--no-check-publish"],
        ["run", "--rm", "--entrypoint", "composer", expectedTag, "check-platform-reqs", "--no-dev"],
        ["run", "--rm", "--entrypoint", "php", expectedTag, "artisan", "--version"],
        ["run", "--rm", "--entrypoint", "php", expectedTag, "artisan", "about", "--only=environment"],
    ]);
    assert.ok(docker.calls.every((call) => call.options.timeout > 0));
    assert.ok(docker.calls.every((call) => call.options.maxBuffer >= 1024));
});

test("runtime command timeout is bounded and recorded as FAIL", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    const docker = fakeDocker();
    const original = docker.runDocker;
    docker.runDocker = (args, options) => {
        if (args[0] === "run" && args.includes("composer") && args.includes("validate")) {
            docker.calls.push({ args: [...args], options: { ...options } });
            return { status: null, stdout: "", stderr: "", error: { code: "ETIMEDOUT", message: "docker command timed out" } };
        }
        return original(args, options);
    };
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, { runDocker: docker.runDocker });
    assert.equal(result.status, "FAIL");
    const timedOut = JSON.parse(readFileSync(report.path, "utf8")).runtime_checks.find((check) => check.id === "composer_validate");
    assert.equal(timedOut.status, "FAIL");
    assert.equal(timedOut.error, "ETIMEDOUT");
    assert.equal(JSON.parse(readFileSync(report.path, "utf8")).commands.find((command) => command.label === "Composer validate strict").outcome, "FAIL");
});

test("normalizes ENOBUFS as an explicit build failure", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    const docker = fakeDocker();
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, {
        runDocker: (args, options) => args[0] === "build"
            ? { status: null, stdout: "", stderr: "", error: { code: "ENOBUFS", message: "spawnSync docker ENOBUFS" } }
            : docker.runDocker(args, options),
    });
    assert.equal(result.status, "FAIL");
    assert.equal(result.build.status, "FAIL");
    const buildCommand = JSON.parse(readFileSync(report.path, "utf8")).commands.find((command) => command.label === "docker build");
    assert.equal(buildCommand.outcome, "FAIL");
    assert.equal(buildCommand.error, "ENOBUFS");
});

test("writes complete JSON with partial writes, fsync, and canonical cleanup on error", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    let partial = true;
    let fsynced = false;
    let writeCalls = 0;
    const fileOps = {
        openSync: fsOpenSync,
        writeSync(fd, buffer, offset, length, position) {
            writeCalls += 1;
            if (partial && length > 1) {
                partial = false;
                const written = fsWriteSync(fd, buffer, offset, 1, position);
                return written;
            }
            return fsWriteSync(fd, buffer, offset, length, position);
        },
        fsyncSync(fd) {
            fsynced = true;
            return fsyncSync(fd);
        },
        closeSync: fsCloseSync,
        unlinkSync,
    };
    const first = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, { runDocker: fakeDocker({ daemon: false }).runDocker, fileOps });
    assert.equal(first.status, "EXTERNAL_BLOCKED");
    assert.equal(fsynced, true);
    assert.ok(writeCalls > 1);
    assert.equal(JSON.parse(readFileSync(report.path, "utf8")).status, "EXTERNAL_BLOCKED");

    const failingOps = {
        ...fileOps,
        writeSync() {
            throw Object.assign(new Error("simulated partial writer failure"), { code: "EIO" });
        },
    };
    const retryReport = reportPath();
    t.after(() => cleanReport(retryReport));
    assert.throws(() => certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: retryReport.path }, { runDocker: fakeDocker({ daemon: false }).runDocker, fileOps: failingOps }), /simulated partial writer failure/u);
    assert.equal(existsSync(retryReport.path), false);
    const retry = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: retryReport.path }, { runDocker: fakeDocker({ daemon: false }).runDocker });
    assert.equal(retry.status, "EXTERNAL_BLOCKED");
});

test("passes a copy of signed tar stdin and fails if an executor mutates that copy", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    const docker = fakeDocker();
    let signedInputHash = null;
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, {
        runDocker: (args, options) => {
            if (args[0] === "build") {
                signedInputHash = createHash("sha256").update(options.input).digest("hex");
                options.input[0] ^= 0xff;
            }
            return docker.runDocker(args, options);
        },
    });
    assert.equal(result.status, "FAIL");
    const reportJson = JSON.parse(readFileSync(report.path, "utf8"));
    assert.equal(reportJson.tar_context.sha256, signedInputHash);
    assert.equal(reportJson.build.status, "FAIL");
    assert.equal(reportJson.commands.find((command) => command.label === "docker build").stdin_sha256, signedInputHash);
});

test("classifies daemon timeout and ENOBUFS as FAIL, not EXTERNAL_BLOCKED", (t) => {
    for (const code of ["ETIMEDOUT", "ENOBUFS", "DOCKER_EXECUTION_ERROR"]) {
        const { bundle } = createBundle(t);
        const report = reportPath();
        t.after(() => cleanReport(report));
        const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, {
            runDocker: (args) => args[0] === "version"
                ? { status: null, stdout: "", stderr: "", error: { code, message: `executor ${code}` } }
                : fakeDocker().runDocker(args, {}),
        });
        assert.equal(result.status, "FAIL");
        assert.equal(result.daemon.status, "FAIL");
        assert.equal(result.evidence.code.status, "FAIL");
        assert.equal(result.evidence.local.status, "BLOCKED");
        assert.equal(result.evidence.external.status, "BLOCKED");
        assert.equal(result.build.status, "NOT_RUN");
    }
});

test("classifies malformed Docker API responses as FAIL while preserving npipe absence as external", (t) => {
    const cases = [
        {
            name: "malformed-api",
            response: { status: 1, stdout: "", stderr: "Docker API returned malformed response", error: null },
            status: "FAIL",
            evidenceCode: "FAIL",
            evidenceLocal: "BLOCKED",
            evidenceExternal: "BLOCKED",
        },
        {
            name: "windows-npipe-missing",
            response: { status: 1, stdout: "", stderr: "failed to connect to the docker API at npipe:////./pipe/dockerDesktopLinuxEngine; open //./pipe/dockerDesktopLinuxEngine: The system cannot find the file specified.", error: null },
            status: "EXTERNAL_BLOCKED",
            evidenceCode: "PASS",
            evidenceLocal: "BLOCKED",
            evidenceExternal: "EXTERNAL_BLOCKED",
        },
    ];
    for (const testCase of cases) {
        const { bundle } = createBundle(t);
        const report = reportPath();
        t.after(() => cleanReport(report));
        const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, {
            runDocker: (args) => args[0] === "version" ? testCase.response : fakeDocker().runDocker(args, {}),
        });
        assert.equal(result.status, testCase.status, testCase.name);
        assert.equal(result.evidence.code.status, testCase.evidenceCode, testCase.name);
        assert.equal(result.evidence.local.status, testCase.evidenceLocal, testCase.name);
        assert.equal(result.evidence.external.status, testCase.evidenceExternal, testCase.name);
        assert.equal(result.build.status, "NOT_RUN", testCase.name);
    }
});

test("builds deterministic tar stdin from signed files and never passes a filesystem context", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    const docker = fakeDocker();
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, { runDocker: docker.runDocker });
    assert.equal(result.status, "PASS");
    const build = docker.calls.find((call) => call.args[0] === "build");
    assert.ok(build);
    assert.deepEqual(build.args, ["build", "--tag", expectedTag, "-"]);
    assert.ok(Buffer.isBuffer(build.options.input));
    assert.equal(build.options.cwd, undefined);
    assert.equal(JSON.parse(readFileSync(report.path, "utf8")).build.context, "stdin-tar");
    const files = new Map();
    for (let offset = 0; offset + 512 <= build.options.input.length;) {
        const header = build.options.input.subarray(offset, offset + 512);
        if (header.every((byte) => byte === 0)) break;
        const name = header.subarray(0, 100).toString("utf8").replace(/\0.*$/u, "");
        const size = parseInt(header.subarray(124, 136).toString("ascii").replace(/\0.*$/u, "").trim() || "0", 8);
        files.set(name, build.options.input.subarray(offset + 512, offset + 512 + size));
        offset += 512 + Math.ceil(size / 512) * 512;
    }
    assert.ok(files.has("Dockerfile"));
    assert.ok(files.has("composer.lock"));
    const signedIndex = JSON.parse(readFileSync(join(bundle, "index.json"), "utf8"));
    for (const path of ["Dockerfile", "composer.lock"]) {
        const expectedHash = signedIndex.entries.find((entry) => entry.path === path).sha256;
        assert.equal(createHash("sha256").update(files.get(path)).digest("hex"), expectedHash);
    }
});

test("rejects unsafe paths and symlink entries before tar creation", () => {
    assert.throws(() => buildDeterministicTar([{ path: "../escape", data: Buffer.from("bad") }]), /TAR_PATH_INVALID/u);
    assert.throws(() => buildDeterministicTar([{ path: "link", type: "symlink", data: Buffer.from("target") }]), /TAR_ENTRY_INVALID/u);
});

test("rejects signed per-file and total tar size overflow before allocating buffers", () => {
    assert.throws(() => buildDeterministicTar([{ path: "large.bin", size: MAX_FILE_BYTES + 1 }]), /MAX_FILE_BYTES|CONTEXT_FILE_TOO_LARGE/u);
    const entries = Array.from({ length: 5 }, (_, index) => ({ path: `file-${index}.bin`, size: Math.floor(MAX_CONTEXT_BYTES / 4) }));
    assert.throws(() => buildDeterministicTar(entries), /MAX_CONTEXT_BYTES|CONTEXT_TOO_LARGE/u);
});

test("writes reports to the canonical parent path and records that actual path", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    const lexicalReport = join(report.parent, ".", "runtime-certificate.json");
    const docker = fakeDocker({ daemon: false });
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: lexicalReport }, { runDocker: docker.runDocker });
    const canonicalReport = resolve(report.parent, "runtime-certificate.json");
    assert.equal(result.report_path, canonicalReport);
    assert.equal(existsSync(canonicalReport), true);
    assert.equal(JSON.parse(readFileSync(canonicalReport, "utf8")).report_path, canonicalReport);
});

test("uses command-specific Docker resource policies", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    const docker = fakeDocker();
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, { runDocker: docker.runDocker });
    assert.equal(result.status, "PASS");
    const daemon = docker.calls.find((call) => call.args[0] === "version");
    const build = docker.calls.find((call) => call.args[0] === "build");
    const inspect = docker.calls.find((call) => call.args[0] === "image");
    const runtime = docker.calls.find((call) => call.args[0] === "run");
    assert.deepEqual({ timeout: daemon.options.timeout, maxBuffer: daemon.options.maxBuffer }, { timeout: 10_000, maxBuffer: 1 * 1024 * 1024 });
    assert.deepEqual({ timeout: build.options.timeout, maxBuffer: build.options.maxBuffer }, { timeout: 30 * 60 * 1000, maxBuffer: 128 * 1024 * 1024 });
    assert.deepEqual({ timeout: inspect.options.timeout, maxBuffer: inspect.options.maxBuffer }, { timeout: 120_000, maxBuffer: 16 * 1024 * 1024 });
    assert.deepEqual({ timeout: runtime.options.timeout, maxBuffer: runtime.options.maxBuffer }, { timeout: 120_000, maxBuffer: 16 * 1024 * 1024 });
});

test("encodes a deterministic ustar prefix for a signed path longer than 100 bytes", () => {
    const longPath = `${"docs/".repeat(20)}artifact.png`;
    const tar = buildDeterministicTar([{ path: longPath, data: Buffer.from("ok") }]);
    const header = tar.subarray(0, 512);
    const name = header.subarray(0, 100).toString("utf8").replace(/\0.*$/u, "");
    const prefix = header.subarray(345, 500).toString("utf8").replace(/\0.*$/u, "");
    assert.equal(`${prefix}/${name}`, longPath);
});

test("malformed image ID fails before runtime checks", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    const docker = fakeDocker();
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, {
        runDocker: (args, options) => args[0] === "image" ? { status: 0, stdout: "sha256:not-a-valid-id\n", stderr: "" } : docker.runDocker(args, options),
    });
    assert.equal(result.status, "FAIL");
    assert.equal(result.image.id, null);
    assert.equal(result.runtime_checks, undefined);
});

test("rejects a report parent junction resolving into the bundle without writing", (t) => {
    const { bundle } = createBundle(t);
    const reportRoot = mkdtempSync(join(tmpdir(), "dovra-runtime-report-junction-"));
    const junction = join(reportRoot, "parent");
    try {
        execFileSync("cmd", ["/c", "mklink", "/J", junction, bundle], { encoding: "utf8", stdio: "ignore" });
    } catch (error) {
        rmSync(reportRoot, { recursive: true, force: true });
        t.skip(`junction unavailable: ${error.code ?? error.message}`);
        return;
    }
    t.after(() => rmSync(reportRoot, { recursive: true, force: true }));
    const report = join(junction, "runtime-certificate.json");
    assert.throws(() => certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report }, { runDocker: fakeDocker().runDocker }), /REPORT_PARENT|symlink|junction|REPORT_INSIDE_BUNDLE/u);
    assert.equal(existsSync(report), false);
});

test("daemon unavailable returns EXTERNAL_BLOCKED and does not build", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    const docker = fakeDocker({ daemon: false });
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, { runDocker: docker.runDocker });
    assert.equal(result.status, "EXTERNAL_BLOCKED");
    assert.equal(result.decision, "NO_GO");
    assert.equal(docker.calls.some((call) => call.args[0] === "build"), false);
    assert.equal(JSON.parse(readFileSync(report.path, "utf8")).evidence.external.status, "EXTERNAL_BLOCKED");
});

test("fake PHP 8.4/Laravel 13 daemon passes local runtime checks with exact tag", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    const docker = fakeDocker();
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, { runDocker: docker.runDocker });
    assert.equal(result.status, "PASS");
    assert.equal(result.evidence.local.status, "PASS");
    assert.equal(result.decision, "NO_GO");
    const build = docker.calls.find((call) => call.args[0] === "build");
    assert.ok(build);
    assert.ok(build.args.includes("--tag"));
    assert.ok(build.args.includes(expectedTag));
});

test("PHP 8.3/Laravel 12/platform failure is FAIL", (t) => {
    const { bundle } = createBundle(t);
    const report = reportPath();
    t.after(() => cleanReport(report));
    const docker = fakeDocker({ php: "8.3.9", laravel: "12.0.0", platform: false });
    const result = certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: report.path }, { runDocker: docker.runDocker });
    assert.equal(result.status, "FAIL");
    assert.equal(result.evidence.local.status, "FAIL");
});

test("rejects an existing report and a report inside the bundle", (t) => {
    const { bundle } = createBundle(t);
    const existing = reportPath();
    writeFileSync(existing.path, "existing\n");
    t.after(() => {
        cleanReport(existing);
        rmSync(join(bundle, "runtime-inside.json"), { force: true });
    });
    assert.throws(() => certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: existing.path }, { runDocker: fakeDocker().runDocker }), /REPORT_EXISTS/u);
    assert.throws(() => certifyDovraRuntime({ bundle, expectedSnapshot: bundleSnapshot(bundle), tag: expectedTag, report: join(bundle, "runtime-inside.json") }, { runDocker: fakeDocker().runDocker }), /REPORT_INSIDE_BUNDLE/u);
});
