import test from "node:test";
import assert from "node:assert/strict";
import { execFileSync, spawnSync } from "node:child_process";
import { existsSync, mkdtempSync, mkdirSync, readFileSync, rmSync, symlinkSync, writeFileSync } from "node:fs";
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
    writeFixtureFile(root, ".env.example", "APP_ENV=local\nAPP_KEY=\nPLACEHOLDER_TOKEN=${PLACEHOLDER_TOKEN}\n# ignored\n");
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
        "'token' => \"required|string|max:4096\",",
        "'API_TOKEN' => \"sometimes|nullable|string|min:1|max:4096\",",
        "'PASSWORD' => \"required|string|confirmed|between:8,128\",",
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

test("legitimate DeleteUser UpdatePassword and security report corpus is indexed byte for byte", (t) => {
    const source = createFixture();
    const outputParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const output = join(outputParent, "bundle");
    const corpus = [
        "app/Application/Auth/Validators/DeleteUserCommandValidator.php",
        "app/Actions/Authentication/UpdateUserPassword.php",
        "INFORME_SEGURIDAD_CONECTAHN.md",
    ];
    for (const path of corpus) writeFixtureFile(source, path, readFileSync(join(repositoryRoot, path)));
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(outputParent, { recursive: true, force: true });
    });

    const result = assemble(source, output);
    assert.equal(result.status, 0, result.stderr || result.stdout);
    const indexed = new Set(JSON.parse(readFileSync(join(output, "index.json"), "utf8")).entries.map((entry) => entry.path));
    for (const path of corpus) {
        assert.ok(indexed.has(path), path);
        assert.deepEqual(readFileSync(join(output, "source", path)), readFileSync(join(repositoryRoot, path)));
    }
});

test("non-config password prose with Unicode whitespace is indexed byte for byte", (t) => {
    const source = createFixture();
    const outputParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const output = join(outputParent, "bundle");
    const forgotPasswordPath = "resources/js/lib/i18n.js";
    const forgotPasswordContent = readFileSync(join(repositoryRoot, forgotPasswordPath));
    const unicodePath = "resources/js/unicode-password-prose.js";
    const unicodeContent = [
        "forgotPassword = \"Frase con tabulador\tpara recuperar acceso\";",
        "forgotPassword = \"Frase con espacio\u00a0inseparable para acceso\";",
        "forgotPassword = \"Frase con espacio\u2003Unicode para acceso\";",
    ].join("\n");
    writeFixtureFile(source, forgotPasswordPath, forgotPasswordContent);
    writeFixtureFile(source, unicodePath, unicodeContent);
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(outputParent, { recursive: true, force: true });
    });

    const result = assemble(source, output);
    assert.equal(result.status, 0, result.stderr || result.stdout);
    assert.deepEqual(readFileSync(join(output, "source", forgotPasswordPath)), forgotPasswordContent);
    assert.equal(readFileSync(join(output, "source", unicodePath), "utf8"), unicodeContent);
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
        [".env.example", "PASSWORD='correct horse battery staple'\n", "correct horse battery staple"],
        ["config/leaked.yml", "API_KEY: short-real\n", "short-real"],
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

test("non-config sensitive literals require the exact entropy boundary", (t) => {
    const safeSource = createFixture();
    const safeParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const safeOutput = join(safeParent, "bundle");
    writeFixtureFile(safeSource, "app/entropy-safe.php", [
        "API_KEY: short-real",
        "PASSWORD: aB3dE5fG7hJ9kL2mN4pQ6rS",
        "ACCESS_TOKEN: AAAAAAAAAAAA111111111111",
    ].join("\n"));
    const blockedSource = createFixture();
    const blockedParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const blockedOutput = join(blockedParent, "bundle");
    writeFixtureFile(blockedSource, "app/entropy-blocked.php", [
        "PRIVATE_KEY = \"aB3dE5fG7hJ9kL2mN4pQ6rS8\";",
    ].join("\n"));
    t.after(() => {
        for (const path of [safeSource, safeParent, blockedSource, blockedParent]) rmSync(path, { recursive: true, force: true });
    });

    const safe = assemble(safeSource, safeOutput);
    assert.equal(safe.status, 0, safe.stderr || safe.stdout);
    assert.ok(JSON.parse(readFileSync(join(safeOutput, "index.json"), "utf8")).entries.some((entry) => entry.path === "app/entropy-safe.php"));
    const blocked = assemble(blockedSource, blockedOutput);
    const diagnostics = `${blocked.stdout}\n${blocked.stderr}`;
    assert.notEqual(blocked.status, 0);
    assert.match(diagnostics, /app\/entropy-blocked\.php:1:sensitive-literal/u);
    assert.ok(!diagnostics.includes("aB3dE5fG7hJ9kL2mN4pQ6rS8"));
});

test("provider value signatures fail in code and markdown without echo", (t) => {
    const source = createFixture();
    const outputParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const output = join(outputParent, "bundle");
    const productionBody = ["Ab3dE5fG7hJ9", "kL2mN4pQ6rS8", "tU0vW1xY2zC7", "aD9eF4G"].join("");
    const jwtHeader = Buffer.from(JSON.stringify({ alg: "HS256", typ: "JWT" })).toString("base64url");
    const jwtPayload = Buffer.from(JSON.stringify({ sub: "1234567890" })).toString("base64url");
    const signatures = [
        ["aws-access-key", ["AK", "IA1234567890ABCDEF"].join("")],
        ["github-token", ["github", "_pat_11AA22BB33CC44DD55EE66FF77GG88HH"].join("")],
        ["openai-key", ["sk", "-proj-", productionBody.slice(0, 40)].join("")],
        ["stripe-key", ["sk", "_live_", productionBody.slice(0, 24)].join("")],
        ["stripe-key", ["rk", "_live_", productionBody.slice(0, 24)].join("")],
        ["stripe-key", ["wh", "sec_", productionBody.slice(0, 32)].join("")],
        ["slack-token", ["xox", "b-1234567890-AbCdEfGhIjKlMnOp"].join("")],
        ["google-api-key", ["AI", "za", "1234567890AbCdEfGhIjKlMnOpQrStUvWxy"].join("")],
        ["jwt", [jwtHeader, jwtPayload, productionBody.slice(0, 32)].join(".")],
    ];
    writeFixtureFile(source, "app/provider.php", `${signatures.slice(0, 4).map(([, value]) => `return '${value}';`).join("\n")}\n`);
    writeFixtureFile(source, "docs/provider.md", `${signatures.slice(4).map(([, value]) => `credential ${value}`).join("\n")}\n`);
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(outputParent, { recursive: true, force: true });
    });

    const result = assemble(source, output);
    const diagnostics = `${result.stdout}\n${result.stderr}`;
    assert.notEqual(result.status, 0);
    for (const [type, value] of signatures) {
        assert.match(diagnostics, new RegExp(`(?:app/provider\\.php|docs/provider\\.md):[0-9]+:${type}`, "u"));
        assert.ok(!diagnostics.includes(value));
    }
});

test("private-key header mentions index but real PEM material aborts", (t) => {
    const safeSource = createFixture();
    const safeParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const safeOutput = join(safeParent, "bundle");
    writeFixtureFile(safeSource, "docs/pem-regex.md", "scanner example: -----BEGIN PRIVATE KEY-----\n");
    const realSource = createFixture();
    const realParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const realOutput = join(realParent, "bundle");
    const pemBody = "QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVo0123456789+/abcd".repeat(2);
    writeFixtureFile(realSource, "docs/leaked.md", `-----BEGIN PRIVATE KEY-----\n${pemBody}\n-----END PRIVATE KEY-----\n`);
    t.after(() => {
        for (const path of [safeSource, safeParent, realSource, realParent]) rmSync(path, { recursive: true, force: true });
    });

    const safe = assemble(safeSource, safeOutput);
    assert.equal(safe.status, 0, safe.stderr || safe.stdout);
    assert.ok(JSON.parse(readFileSync(join(safeOutput, "index.json"), "utf8")).entries.some((entry) => entry.path === "docs/pem-regex.md"));
    const real = assemble(realSource, realOutput);
    assert.notEqual(real.status, 0);
    assert.match(`${real.stdout}\n${real.stderr}`, /docs\/leaked\.md:1:pem-private-key/u);
    assert.ok(!`${real.stdout}\n${real.stderr}`.includes(pemBody));
});

test("assembler reports every static literal secret by path line and type without values", (t) => {
    const source = createFixture();
    const outputParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const output = join(outputParent, "bundle");
    writeFixtureFile(source, "config/first.yml", "APP_ENV: test\nAPI_KEY: aggregate-api-value\n");
    writeFixtureFile(source, "config/second.yml", "PASSWORD: aggregate-password-value\n");
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(outputParent, { recursive: true, force: true });
    });

    const result = assemble(source, output);
    const diagnostics = `${result.stdout}\n${result.stderr}`;
    assert.notEqual(result.status, 0);
    assert.match(diagnostics, /config\/first\.yml:2:sensitive-literal/u);
    assert.match(diagnostics, /config\/second\.yml:1:sensitive-literal/u);
    assert.ok(!diagnostics.includes("aggregate-api-value"));
    assert.ok(!diagnostics.includes("aggregate-password-value"));
    assert.ok(!existsSync(join(output, "manifest.json")));
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

test("scanner accepts empty and dynamic credential configuration values", (t) => {
    const source = createFixture();
    const output = join(mkdtempSync(join(tmpdir(), "dovra-rc-output-")), "bundle");
    writeFixtureFile(source, ".env.example", [
        "OPENAI_API_KEY=",
        "PAYMENT_WEBHOOK_SECRET=${PAYMENT_WEBHOOK_SECRET}",
        "API_TOKEN={{ API_TOKEN }}",
        "REDIS_PASSWORD=$runtimePassword",
        "REAL_SECRET=",
    ].join("\n"));
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(dirname(output), { recursive: true, force: true });
    });

    const fixtureResult = assemble(source, output);
    assert.equal(fixtureResult.status, 0, fixtureResult.stderr || fixtureResult.stdout);
});

test("scanner indexes dynamic expressions byte for byte and rejects only closed static scalars", (t) => {
    const source = createFixture();
    const outputParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const output = join(outputParent, "bundle");
    const path = "config/dynamic.yml";
    const content = [
        "API_KEY: $input['api_key']",
        "PASSWORD: ${PASSWORD}",
        "ACCESS_TOKEN: env('ACCESS_TOKEN')",
        "PRIVATE_KEY: Credentials::PRIVATE_KEY",
        "WEBHOOK_SECRET: factory()->secret",
        "SESSION_TOKEN: tokens[0]",
        "CLIENT_SECRET: { injected: true }",
        "AUTH_TOKEN: prefix suffix",
        "API_SECRET: credentials.value",
        "BROKEN_PASSWORD: \"unterminated",
    ].join("\n");
    writeFixtureFile(source, path, content);
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(outputParent, { recursive: true, force: true });
    });

    const result = assemble(source, output);
    assert.equal(result.status, 0, result.stderr || result.stdout);
    assert.deepEqual(readFileSync(join(output, "source", path), "utf8"), content);
});

test("scanner recognizes balanced quoted scalars with escapes delimiters and comments", (t) => {
    const source = createFixture();
    const outputParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const output = join(outputParent, "bundle");
    const first = "Ab3!dEf5-Gh7_jKl9.Mn2#Qr4";
    const second = "Zy8@xW6v-Ut4_sR2q.Po0\\\"Nm";
    const third = "Qr4!sTu6-Vw8_xYz0.Ab2\\'Cd";
    writeFixtureFile(source, "app/quoted.php", [
        `API_KEY = "${first}"; // retained comment`,
        `PASSWORD = "${second}"; # retained comment`,
        `ACCESS_TOKEN = '${third}', // retained comment`,
    ].join("\n"));
    t.after(() => {
        rmSync(source, { recursive: true, force: true });
        rmSync(outputParent, { recursive: true, force: true });
    });

    const result = assemble(source, output);
    const diagnostics = `${result.stdout}\n${result.stderr}`;
    assert.notEqual(result.status, 0);
    assert.match(diagnostics, /app\/quoted\.php:1:sensitive-literal/u);
    assert.match(diagnostics, /app\/quoted\.php:2:sensitive-literal/u);
    assert.match(diagnostics, /app\/quoted\.php:3:sensitive-literal/u);
    assert.ok(!diagnostics.includes(first));
    assert.ok(!diagnostics.includes(second));
    assert.ok(!diagnostics.includes(third));
    assert.ok(!existsSync(join(output, "manifest.json")));
});

test("config scanner limits reference time and empty-value exemptions", (t) => {
    const safeSource = createFixture();
    const safeParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const safeOutput = join(safeParent, "bundle");
    writeFixtureFile(safeSource, "config/safe.yml", [
        "OPENAI_SECRET_REFERENCE: openai-primary",
        "PAYMENT_TOKEN_REF: vault-payment",
        "SECURITY_TOKEN_TTL_DAYS: 30",
        "PASSWORD_TIMEOUT_SECONDS: 120",
        "OPTIONAL_PASSWORD: null",
        "LEGACY_TOKEN: none",
    ].join("\n"));
    const blockedSource = createFixture();
    const blockedParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const blockedOutput = join(blockedParent, "bundle");
    writeFixtureFile(blockedSource, "config/blocked.yml", [
        "OPENAI_SECRET_REFERENCE_EXTRA: openai-primary",
        "SECURITY_TOKEN_TTL_DAYS: forever",
    ].join("\n"));
    t.after(() => {
        for (const path of [safeSource, safeParent, blockedSource, blockedParent]) rmSync(path, { recursive: true, force: true });
    });

    const safe = assemble(safeSource, safeOutput);
    assert.equal(safe.status, 0, safe.stderr || safe.stdout);
    const blocked = assemble(blockedSource, blockedOutput);
    const diagnostics = `${blocked.stdout}\n${blocked.stderr}`;
    assert.notEqual(blocked.status, 0);
    assert.match(diagnostics, /config\/blocked\.yml:1:sensitive-literal/u);
    assert.match(diagnostics, /config\/blocked\.yml:2:sensitive-literal/u);
});

test("provider signatures use production boundaries entropy and structured JWTs", (t) => {
    const safeSource = createFixture();
    const safeParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const safeOutput = join(safeParent, "bundle");
    const productionBody = ["Ab3dE5fG7hJ9", "kL2mN4pQ6rS8", "tU0vW1xY2zC7", "aD9eF4G"].join("");
    const safeValues = [
        ["AK", "IAIOSFODNN7EXAMPLE"].join(""),
        ["wh", "sec_dummy_secret_for_local_dev_only"].join(""),
        ["sk", "-proj-", productionBody.slice(0, 39)].join(""),
        ["sk", "-proj-", "A".repeat(40)].join(""),
        ["sk", "-", productionBody.slice(0, 31)].join(""),
        ["sk", "_live_", productionBody.slice(0, 23)].join(""),
        ["eyJ", "ub3QiOiJqc29uIn0.not-json-payload.", productionBody.slice(0, 32)].join(""),
    ];
    writeFixtureFile(safeSource, "docs/provider-examples.md", safeValues.join("\n"));
    writeFixtureFile(safeSource, ".env.example", [
        `AWS_ACCESS_KEY_ID=${safeValues[0]}`,
        `PAYMENT_WEBHOOK_SECRET=${safeValues[1]}`,
    ].join("\n"));
    const blockedSource = createFixture();
    const blockedParent = mkdtempSync(join(tmpdir(), "dovra-rc-output-"));
    const blockedOutput = join(blockedParent, "bundle");
    const jwtHeader = Buffer.from(JSON.stringify({ alg: "HS256", typ: "JWT" })).toString("base64url");
    const jwtPayload = Buffer.from(JSON.stringify({ sub: "1234567890" })).toString("base64url");
    const blockedValues = [
        ["sk", "-proj-", productionBody.slice(0, 40)].join(""),
        ["sk", "-", productionBody.slice(0, 32)].join(""),
        ["rk", "_live_", productionBody.slice(0, 24)].join(""),
        ["wh", "sec_", productionBody.slice(0, 32)].join(""),
        [jwtHeader, jwtPayload, productionBody.slice(0, 32)].join("."),
    ];
    writeFixtureFile(blockedSource, "app/provider.php", blockedValues.map((value) => `return '${value}';`).join("\n"));
    t.after(() => {
        for (const path of [safeSource, safeParent, blockedSource, blockedParent]) rmSync(path, { recursive: true, force: true });
    });

    const safe = assemble(safeSource, safeOutput);
    assert.equal(safe.status, 0, safe.stderr || safe.stdout);
    const blocked = assemble(blockedSource, blockedOutput);
    const diagnostics = `${blocked.stdout}\n${blocked.stderr}`;
    assert.notEqual(blocked.status, 0);
    for (const type of ["openai-key", "stripe-key", "jwt"]) assert.match(diagnostics, new RegExp(`app/provider\\.php:[0-9]+:${type}`, "u"));
    for (const value of blockedValues) assert.ok(!diagnostics.includes(value));
    assert.ok(!existsSync(join(blockedOutput, "manifest.json")));
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
