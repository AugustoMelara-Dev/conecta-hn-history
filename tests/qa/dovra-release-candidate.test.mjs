import test from "node:test";
import assert from "node:assert/strict";
import { execFileSync, spawnSync } from "node:child_process";
import { mkdtempSync, mkdirSync, readFileSync, rmSync, symlinkSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join, resolve } from "node:path";

const repositoryRoot = resolve(import.meta.dirname, "../..");
const assembler = join(repositoryRoot, "scripts/qa/assemble-dovra-rc.mjs");
const verifier = join(repositoryRoot, "scripts/qa/verify-dovra-rc.mjs");

function git(cwd, args) {
    return execFileSync("git", args, {
        cwd,
        encoding: "utf8",
        stdio: ["ignore", "pipe", "pipe"],
    });
}

function writeFixtureFile(root, relativePath, content) {
    const target = join(root, relativePath);
    mkdirSync(join(target, ".."), { recursive: true });
    writeFileSync(target, content);
}

function createFixture() {
    const root = mkdtempSync(join(tmpdir(), "dovra-rc-source-"));
    writeFixtureFile(root, "app/main.php", "<?php\nreturn ['ok' => true];\n");
    writeFixtureFile(root, "docs/README.md", "Dovra release candidate fixture.\n");
    writeFixtureFile(root, "composer.lock", "{\"packages\":[]}");
    writeFixtureFile(root, "package-lock.json", "{\"name\":\"fixture\",\"lockfileVersion\":3}");
    writeFixtureFile(root, "Dockerfile", "FROM php:8.4-cli\n");
    writeFixtureFile(root, ".env.example", "APP_ENV=local\nAPP_KEY=\nPLACEHOLDER_TOKEN=replace-me\n# ignored\n");
    writeFixtureFile(root, ".env", "APP_ENV=production\nREAL_SECRET=never-copy\n");
    writeFixtureFile(root, "vendor/should-not-copy.php", "<?php\n");
    writeFixtureFile(root, "node_modules/ignored.js", "ignored\n");
    writeFixtureFile(root, "storage/logs/laravel.log", "secret log\n");
    writeFixtureFile(root, "artifacts/releases/old.zip", "old artifact\n");
    writeFixtureFile(root, "build/app.js", "generated\n");
    writeFixtureFile(root, "private-key.pem", "-----BEGIN PRIVATE KEY-----\nnot copied\n-----END PRIVATE KEY-----\n");
    writeFixtureFile(root, "secrets/api.txt", "TOKEN=supersecret-value\n");
    writeFixtureFile(root, "config/leaked.txt", "SECRET_KEY=test-secret\n");
    writeFixtureFile(root, "app/static-secret-scanner-fixture.txt", [
        "'password' => \"\",",
        "'password' => $input,",
        "'apiKey' => process.env.API_KEY,",
        "'showPassword' => true,",
        "'maxOutputTokens' => 500,",
        "'API_KEY' => \"test-api-key\",",
        "'PASSWORD' => \"fake-password\",",
        "'ACCESS_TOKEN' => \"example-token\",",
        "'PRIVATE_KEY' => \"fixture-private-key\",",
    ].join("\n"));
    writeFixtureFile(root, "gone.txt", "deleted after commit\n");

    git(root, ["init", "-q"]);
    git(root, ["config", "user.email", "qa@example.invalid"]);
    git(root, ["config", "user.name", "Dovra QA"]);
    git(root, ["add", "."]);
    git(root, ["commit", "-qm", "fixture"]);
    rmSync(join(root, "gone.txt"));
    writeFixtureFile(root, "untracked.txt", "untracked but visible\n");
    return root;
}

function assemble(source, output, extra = []) {
    return spawnSync(process.execPath, [assembler, "--source", source, "--output", output, ...extra], {
        cwd: repositoryRoot,
        encoding: "utf8",
        shell: false,
    });
}

function verify(output, extra = []) {
    return spawnSync(process.execPath, [verifier, "--input", output, "--json", ...extra], {
        cwd: repositoryRoot,
        encoding: "utf8",
        shell: false,
    });
}

function verifyWithBundleAlias(output, extra = []) {
    return spawnSync(process.execPath, [verifier, "--bundle", output, "--json", ...extra], {
        cwd: repositoryRoot,
        encoding: "utf8",
        shell: false,
    });
}

function parseJsonOutput(result) {
    assert.ok(result.stdout.trim(), `expected JSON output, stderr=${result.stderr}`);
    return JSON.parse(result.stdout);
}

test("assembles a deterministic index and snapshot hash", (t) => {
    const source = createFixture();
    const first = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    const second = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(dirname(first), { recursive: true, force: true });
        rmSync(dirname(second), { recursive: true, force: true });
    });

    const firstRun = assemble(source, first);
    const secondRun = assemble(source, second);
    assert.equal(firstRun.status, 0, firstRun.stderr || firstRun.stdout);
    assert.equal(secondRun.status, 0, secondRun.stderr || secondRun.stdout);

    const firstIndex = readFileSync(join(first, "index.json"), "utf8");
    const secondIndex = readFileSync(join(second, "index.json"), "utf8");
    assert.equal(firstIndex, secondIndex);
    const firstManifest = JSON.parse(readFileSync(join(first, "manifest.json"), "utf8"));
    const secondManifest = JSON.parse(readFileSync(join(second, "manifest.json"), "utf8"));
    assert.equal(firstManifest.snapshot_sha256, secondManifest.snapshot_sha256);
    assert.equal(firstManifest.release_decision, "LOCALLY_VERIFIED/NO_GO");
});

test("excludes sensitive/generated paths and records environment names without values", (t) => {
    const source = createFixture();
    const output = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(dirname(output), { recursive: true, force: true });
    });

    const result = assemble(source, output);
    assert.equal(result.status, 0, result.stderr || result.stdout);
    const index = JSON.parse(readFileSync(join(output, "index.json"), "utf8"));
    const paths = index.entries.map((entry) => entry.path);
    assert.ok(paths.includes(".env.example"));
    for (const forbidden of [".env", "vendor/should-not-copy.php", "node_modules/ignored.js", "storage/logs/laravel.log", "artifacts/releases/old.zip", "build/app.js", "private-key.pem", "secrets/api.txt"]) {
        assert.ok(!paths.includes(forbidden), `forbidden path copied: ${forbidden}`);
    }
    assert.ok(paths.includes("config/leaked.txt"));
    assert.ok(paths.includes("app/static-secret-scanner-fixture.txt"));
    const manifestText = readFileSync(join(output, "manifest.json"), "utf8");
    const manifest = JSON.parse(manifestText);
    assert.deepEqual(manifest.env_example_names, ["APP_ENV", "APP_KEY", "PLACEHOLDER_TOKEN"]);
    assert.ok(!manifestText.includes("replace-me"));
    assert.ok(manifest.git.deleted_paths.includes("gone.txt"));
    const verified = verify(output);
    assert.equal(verified.status, 0, verified.stderr || verified.stdout);
    assert.equal(parseJsonOutput(verified).status, "PASS");
});

test("verifier rejects tampered and unexpected source files", (t) => {
    const source = createFixture();
    const output = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(dirname(output), { recursive: true, force: true });
    });
    const assembled = assemble(source, output);
    assert.equal(assembled.status, 0, assembled.stderr || assembled.stdout);

    writeFixtureFile(output, "source/app/main.php", "<?php return ['tampered' => true];\n");
    let result = verify(output);
    let report = parseJsonOutput(result);
    assert.notEqual(result.status, 0);
    assert.equal(report.status, "FAIL");
    assert.ok(report.findings.some((finding) => finding.code === "HASH_MISMATCH"));

    writeFixtureFile(output, "source/unexpected.txt", "unexpected\n");
    result = verify(output);
    report = parseJsonOutput(result);
    assert.notEqual(result.status, 0);
    assert.ok(report.findings.some((finding) => finding.code === "UNEXPECTED_FILE"));
});

test("strict verification fails until runtime, staging, production, and market evidence are proven", (t) => {
    const source = createFixture();
    const output = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(dirname(output), { recursive: true, force: true });
    });
    const assembled = assemble(source, output, ["--strict"]);
    assert.notEqual(assembled.status, 0);
    assert.ok(readFileSync(join(output, "manifest.json"), "utf8"));

    const result = verify(output, ["--strict"]);
    const report = parseJsonOutput(result);
    assert.notEqual(result.status, 0);
    assert.equal(report.status, "NO_GO");
    assert.equal(report.strict, true);
    assert.equal(report.evidence.staging.status, "NOT_RUN");
    assert.equal(report.evidence.market.status, "EXTERNAL_BLOCKED");
});

test("verifier detects index divergence independently of source hashes", (t) => {
    const source = createFixture();
    const output = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(dirname(output), { recursive: true, force: true });
    });
    const assembled = assemble(source, output);
    assert.equal(assembled.status, 0, assembled.stderr || assembled.stdout);
    const indexPath = join(output, "index.json");
    const index = JSON.parse(readFileSync(indexPath, "utf8"));
    index.entries[0].sha256 = "0".repeat(64);
    writeFileSync(indexPath, `${JSON.stringify(index, null, 2)}\n`);
    const result = verify(output);
    const report = parseJsonOutput(result);
    assert.notEqual(result.status, 0);
    assert.ok(report.findings.some((finding) => finding.code === "INDEX_HASH_MISMATCH" || finding.code === "SNAPSHOT_MISMATCH"));
});

test("assembler refuses a pre-existing output junction and never writes through it", (t) => {
    const source = createFixture();
    const outputParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-parent-"));
    const victim = mkdtempSync(join(tmpdir(), "dovra-rc-victim-"));
    const output = join(outputParent, "bundle");
    writeFixtureFile(victim, "marker.txt", "must remain untouched\n");
    symlinkSync(victim, output, "junction");
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(outputParent, { recursive: true, force: true });
        rmSync(victim, { recursive: true, force: true });
    });

    const result = assemble(source, output);
    assert.notEqual(result.status, 0, result.stdout);
    assert.equal(readFileSync(join(victim, "marker.txt"), "utf8"), "must remain untouched\n");
});

test("verifier rejects manifest claim, safety, and evidence tampering", (t) => {
    const source = createFixture();
    const output = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(dirname(output), { recursive: true, force: true });
    });
    const assembled = assemble(source, output);
    assert.equal(assembled.status, 0, assembled.stderr || assembled.stdout);
    const manifestPath = join(output, "manifest.json");
    const baseline = JSON.parse(readFileSync(manifestPath, "utf8"));

    const mutations = [
        ["claims", (manifest) => { manifest.claims.public_production = true; }, "MANIFEST_CLAIMS_INVALID"],
        ["safety", (manifest) => { manifest.safety.secret_values_copied = true; }, "MANIFEST_SAFETY_INVALID"],
        ["evidence", (manifest) => { for (const area of Object.values(manifest.evidence)) area.status = "PASS"; }, "EVIDENCE_STATUS_INVALID"],
        ["critical hash", (manifest) => { delete manifest.critical_hashes.Dockerfile; }, "CRITICAL_HASH_MISSING"],
        ["strict requirements", (manifest) => { manifest.strict_requirements = []; }, "STRICT_REQUIREMENTS_INVALID"],
    ];
    for (const [, mutate, expectedCode] of mutations) {
        const mutated = structuredClone(baseline);
        mutate(mutated);
        writeFileSync(manifestPath, `${JSON.stringify(mutated, null, 2)}\n`);
        const result = verify(output, ["--strict"]);
        const report = parseJsonOutput(result);
        assert.notEqual(result.status, 0);
        assert.ok(report.findings.some((finding) => finding.code === expectedCode), `${expectedCode} missing`);
    }
});

test("assembler rejects real secrets in .env.example and missing mandatory critical files", (t) => {
    const sourceWithSecret = createFixture();
    const secretOutput = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    writeFixtureFile(sourceWithSecret, ".env.example", [
        "APP_ENV=local",
        "STRIPE_SECRET=x",
        "TWILIO_TOKEN=real-token-value",
        "RESEND_API_KEY=re_secret-value",
        "VAPID_PRIVATE_KEY=real-private-key",
        "WHATSAPP_CLOUD_ACCESS_TOKEN=real-whatsapp-token",
        "GENERIC_PASSWORD=real-password-value",
    ].join("\n"));
    const sourceMissingCritical = createFixture();
    const missingOutput = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    rmSync(join(sourceMissingCritical, "Dockerfile"));
    t.after(() => {
        rmSync(sourceWithSecret, { recursive: true, force: true });
        rmSync(dirname(secretOutput), { recursive: true, force: true });
        rmSync(sourceMissingCritical, { recursive: true, force: true });
        rmSync(dirname(missingOutput), { recursive: true, force: true });
    });

    const secretResult = assemble(sourceWithSecret, secretOutput);
    assert.notEqual(secretResult.status, 0, secretResult.stdout);
    const missingResult = assemble(sourceMissingCritical, missingOutput);
    assert.notEqual(missingResult.status, 0, missingResult.stdout);
});

test("assembler fails closed on static literal secrets without echoing their values", (t) => {
    const fixtures = [
        ["config/leaked.txt", "API_KEY: \"rc-api-key-marker\",\n", "rc-api-key-marker"],
        ["config/leaked.txt", "'password' => 'rc-password-marker',\n", "rc-password-marker"],
        ["config/leaked.yml", "API_KEY: postgres\n", "postgres"],
        ["config/leaked.yml", "PASSWORD: production-value\n", "production-value"],
        ["config/leaked.yml", "ACCESS_TOKEN: root\n", "root"],
        ["config/leaked.yml", "PRIVATE_KEY: conecta\n", "conecta"],
        ["config/leaked.yml", "DB_PASSWORD: production-value\n", "production-value"],
    ];
    const cleanup = [];
    t.after(() => {
        for (const path of cleanup) {
            rmSync(path, { recursive: true, force: true });
        }
    });

    for (const [path, content, marker] of fixtures) {
        const source = createFixture();
        const outputParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
        const output = join(outputParent, "bundle");
        cleanup.push(source, outputParent);
        writeFixtureFile(source, path, content);

        const result = assemble(source, output);
        assert.notEqual(result.status, 0, `static literal secret in ${path} must fail assembly`);
        assert.ok(!`${result.stdout}\n${result.stderr}`.includes(marker), "scanner failure must not echo the secret value");
    }
});

test("assembler indexes only the verified local database controls and sentinels", (t) => {
    const source = createFixture();
    const outputParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const output = join(outputParent, "bundle");
    const fragmentPath = ".github/workflows/local-database-fixture.yml";
    writeFixtureFile(source, fragmentPath, [
        "MYSQL_ALLOW_EMPTY_PASSWORD: \"yes\"",
        "PGPASSWORD: postgres",
        "POSTGRES_PASSWORD: postgres",
        "DB_PASSWORD: postgres",
        "DB_PLATFORM_PASSWORD: rls-platform-password",
        "DB_PASSWORD: rls-test-password",
        "MYSQL_PASSWORD: conecta",
        "MYSQL_ROOT_PASSWORD: root",
        "DB_PASSWORD: \"${DB_PASSWORD:-conecta}\"",
    ].join("\n"));
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(outputParent, { recursive: true, force: true });
    });

    const result = assemble(source, output);
    assert.equal(result.status, 0, result.stderr || result.stdout);
    const index = JSON.parse(readFileSync(join(output, "index.json"), "utf8"));
    assert.ok(index.entries.some((entry) => entry.path === fragmentPath));
    assert.equal(readFileSync(join(output, "source", fragmentPath), "utf8").includes("${DB_PASSWORD:-conecta}"), true);
});

test("verifier rejects a symlinked source component and supports --bundle alias", (t) => {
    const source = createFixture();
    const output = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    const victim = mkdtempSync(join(tmpdir(), "dovra-rc-victim-"));
    writeFixtureFile(victim, "external.txt", "-----BEGIN PRIVATE KEY-----\nexternal sentinel\n-----END PRIVATE KEY-----\n");
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(dirname(output), { recursive: true, force: true });
        rmSync(victim, { recursive: true, force: true });
    });
    const assembled = assemble(source, output);
    assert.equal(assembled.status, 0, assembled.stderr || assembled.stdout);
    rmSync(join(output, "source"), { recursive: true, force: true });
    symlinkSync(victim, join(output, "source"), "junction");
    const result = verifyWithBundleAlias(output);
    const report = parseJsonOutput(result);
    assert.notEqual(result.status, 0);
    assert.ok(report.findings.some((finding) => finding.code === "BUNDLE_COMPONENT_LINK"));
    assert.ok(!report.findings.some((finding) => finding.path === "external.txt"), "verifier must not inspect a linked external source");
});

test("scanner accepts empty placeholders, TTL values, dummy values, and the repository .env.example in a fixture", (t) => {
    const source = createFixture();
    const output = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    const realExample = readFileSync(join(repositoryRoot, ".env.example"), "utf8");
    writeFixtureFile(source, ".env.example", [
        "OPENAI_API_KEY=",
        "SECURITY_TOKEN_TTL_DAYS=30",
        "PAYMENT_WEBHOOK_SECRET=whsec_dummy_secret_for_local_dev_only",
        "AI_OPENAI_SECRET_REFERENCE=openai.primary",
        "REDIS_PASSWORD=null",
        "REAL_SECRET=",
    ].join("\n"));
    const placeholderSource = join(mkdtempSync(join(tmpdir(), "dovra-rc-source-")), "real-example");
    const realOutput = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    mkdirSync(placeholderSource, { recursive: true });
    for (const path of ["app/main.php", "docs/README.md", "composer.lock", "package-lock.json", "Dockerfile"]) {
        const sourcePath = join(source, path);
        writeFixtureFile(placeholderSource, path, readFileSync(sourcePath));
    }
    writeFixtureFile(placeholderSource, ".env.example", realExample);
    git(placeholderSource, ["init", "-q"]);
    git(placeholderSource, ["config", "user.email", "qa@example.invalid"]);
    git(placeholderSource, ["config", "user.name", "Dovra QA"]);
    git(placeholderSource, ["add", "."]);
    git(placeholderSource, ["commit", "-qm", "fixture"]);
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(dirname(output), { recursive: true, force: true });
        rmSync(dirname(placeholderSource), { recursive: true, force: true });
        rmSync(dirname(realOutput), { recursive: true, force: true });
    });

    const fixtureResult = assemble(source, output);
    assert.equal(fixtureResult.status, 0, fixtureResult.stderr || fixtureResult.stdout);
    const realResult = assemble(placeholderSource, realOutput);
    assert.equal(realResult.status, 0, realResult.stderr || realResult.stdout);
});

test("assembler rejects a symlinked source candidate before reading its target", (t) => {
    const source = createFixture();
    const output = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    const victim = mkdtempSync(join(tmpdir(), "dovra-rc-victim-"));
    writeFixtureFile(victim, "main.php", "<?php return ['external' => true];\n");
    rmSync(join(source, "app/main.php"));
    try {
        symlinkSync(join(victim, "main.php"), join(source, "app/main.php"), "file");
    } catch (error) {
        t.skip(`file symlinks unavailable on this host: ${error instanceof Error ? error.message : String(error)}`);
        rmSync(source, { recursive: true, force: true });
        rmSync(dirname(output), { recursive: true, force: true });
        rmSync(victim, { recursive: true, force: true });
        return;
    }
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(dirname(output), { recursive: true, force: true });
        rmSync(victim, { recursive: true, force: true });
    });

    const result = assemble(source, output);
    assert.notEqual(result.status, 0, result.stdout);
});
