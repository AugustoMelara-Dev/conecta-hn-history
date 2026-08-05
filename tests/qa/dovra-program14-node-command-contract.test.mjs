import test from "node:test";
import assert from "node:assert/strict";
import { createHash } from "node:crypto";
import { globSync, readFileSync } from "node:fs";
import { resolve } from "node:path";
import { pathToFileURL } from "node:url";
import { configDefaults } from "vitest/config";

const repositoryRoot = resolve(import.meta.dirname, "../..");
const packagePath = resolve(repositoryRoot, "package.json");
const lockPath = resolve(repositoryRoot, "package-lock.json");
const lockBaselineSha256 = "ABD251784E67E2036485B194BFAFB0947DDAD0AE0B107FBE73A5F13B264E88BD";
const vitestConfigPath = resolve(repositoryRoot, "vitest.config.js");

const requiredAliases = Object.freeze({
    typecheck: "tsc --noEmit",
    test: "vitest",
    "test:qa": "node --test \"tests/qa/*.test.mjs\"",
});

const preservedAliases = Object.freeze({
    "type-check": "tsc --noEmit",
    "test:ui": "vitest run resources/js/__tests__ --passWithNoTests",
});

const unsafeCommandPatterns = Object.freeze([
    ["shell chaining/redirection", /(?:&&|\|\||[;|<>]|`|\$\()/u],
    ["network access", /\b(?:curl|wget|invoke-webrequest|start-bitstransfer)\b|https?:\/\//iu],
    ["PHP/Composer", /\b(?:php|artisan|composer)\b/iu],
    ["Docker/Podman", /\b(?:docker|podman)\b/iu],
    ["E2E runner", /\b(?:playwright|cypress|test:e2e)\b|\.spec\.[cm]?[jt]sx?\b/iu],
    ["recursive package alias", /\b(?:npm|pnpm|yarn)\s+(?:run|exec)\b/iu],
    ["shell launcher", /\b(?:powershell|pwsh|cmd(?:\.exe)?|bash|sh)\b/iu],
]);

function readPackage() {
    return JSON.parse(readFileSync(packagePath, "utf8"));
}

function lockSha256() {
    return createHash("sha256").update(readFileSync(lockPath)).digest("hex").toUpperCase();
}

function clonePackage(packageJson) {
    return JSON.parse(JSON.stringify(packageJson));
}

function commandContractIssues(packageJson) {
    const issues = [];
    const scripts = packageJson?.scripts;
    if (scripts === null || typeof scripts !== "object" || Array.isArray(scripts)) {
        return ["scripts must be a JSON object"];
    }

    for (const [name, expected] of Object.entries(requiredAliases)) {
        if (!Object.hasOwn(scripts, name)) {
            issues.push(`missing required alias ${name}`);
            continue;
        }
        if (scripts[name] !== expected) issues.push(`${name} must equal ${JSON.stringify(expected)}`);
        if (typeof scripts[name] !== "string") continue;
        for (const [label, pattern] of unsafeCommandPatterns) {
            if (pattern.test(scripts[name])) issues.push(`${name} contains ${label}`);
        }
    }

    for (const [name, expected] of Object.entries(preservedAliases)) {
        if (scripts[name] !== expected) issues.push(`${name} must remain ${JSON.stringify(expected)}`);
    }

    return issues;
}

function assertQaSuiteGlob() {
    const matches = globSync("tests/qa/*.test.mjs", { cwd: repositoryRoot })
        .map((entry) => entry.replaceAll("\\", "/"))
        .sort();
    const expectedSuites = [
        "tests/qa/dovra-booking-conversion-contract.test.mjs",
        "tests/qa/dovra-buyer-conversion-contract.test.mjs",
        "tests/qa/dovra-filament-assets-contract.test.mjs",
        "tests/qa/dovra-hybrid-global-buyer-contract.test.mjs",
        "tests/qa/dovra-pilot-package.test.mjs",
        "tests/qa/dovra-program05-composite-certifier.test.mjs",
        "tests/qa/dovra-program06-composite-certifier.test.mjs",
        "tests/qa/dovra-program07-composite-certifier.test.mjs",
        "tests/qa/dovra-program14-node-command-contract.test.mjs",
        "tests/qa/dovra-registration-email-helper-contract.test.mjs",
        "tests/qa/dovra-release-candidate.test.mjs",
        "tests/qa/dovra-routed-surface-contract.test.mjs",
        "tests/qa/dovra-runtime-certificate.test.mjs",
    ];

    assert.deepEqual(matches, expectedSuites);
    assert.equal(matches.length, 13);
    for (const relativePath of matches) {
        const source = readFileSync(resolve(repositoryRoot, relativePath), "utf8");
        assert.match(source, /from\s+["']node:test["']/u, `${relativePath} must use the Node test runtime`);
        assert.match(source, /^\s*test\s*\(/mu, `${relativePath} must declare at least one test (no empty PASS)`);
    }
}

test("canonical package command aliases and preserved UI scripts are exact", () => {
    const packageJson = readPackage();
    assert.deepEqual(commandContractIssues(packageJson), []);
    assert.equal(lockSha256(), lockBaselineSha256);
    assert.equal(packageJson.scripts.typecheck, "tsc --noEmit");
    assert.equal(packageJson.scripts.test, "vitest");
    assert.equal(packageJson.scripts["test:qa"], "node --test \"tests/qa/*.test.mjs\"");
    assert.equal(packageJson.scripts["type-check"], preservedAliases["type-check"]);
    assert.equal(packageJson.scripts["test:ui"], preservedAliases["test:ui"]);
    assertQaSuiteGlob();
});

test("rejects missing, wrong, recursive, and unsafe alias mutations", () => {
    const mutations = [
        ["missing typecheck", (pkg) => { delete pkg.scripts.typecheck; }, /missing required alias typecheck/iu],
        ["missing test", (pkg) => { delete pkg.scripts.test; }, /missing required alias test/iu],
        ["missing test:qa", (pkg) => { delete pkg.scripts["test:qa"]; }, /missing required alias test:qa/iu],
        ["wrong typecheck", (pkg) => { pkg.scripts.typecheck = "tsc"; }, /typecheck must equal/iu],
        ["wrong test", (pkg) => { pkg.scripts.test = "vitest run"; }, /test must equal/iu],
        ["wrong test:qa", (pkg) => { pkg.scripts["test:qa"] = "node --test"; }, /test:qa must equal/iu],
        ["recursive typecheck", (pkg) => { pkg.scripts.typecheck = "npm run typecheck"; }, /recursive package alias|typecheck must equal/iu],
        ["recursive test", (pkg) => { pkg.scripts.test = "npm run test"; }, /recursive package alias|test must equal/iu],
        ["recursive test:qa", (pkg) => { pkg.scripts["test:qa"] = "npm run test:qa"; }, /recursive package alias|test:qa must equal/iu],
        ["shell chaining", (pkg) => { pkg.scripts.typecheck = "tsc --noEmit && npm run test"; }, /shell chaining\/redirection/iu],
        ["shell redirection", (pkg) => { pkg.scripts.test = "vitest > test.log"; }, /shell chaining\/redirection/iu],
        ["test:qa shell chaining", (pkg) => { pkg.scripts["test:qa"] = "node --test \"tests/qa/*.test.mjs\" && npm run test"; }, /shell chaining\/redirection|test:qa must equal/iu],
        ["network command", (pkg) => { pkg.scripts.typecheck = "curl https://example.invalid/types"; }, /network access/iu],
        ["test:qa network command", (pkg) => { pkg.scripts["test:qa"] = "curl https://example.invalid/qa"; }, /network access|test:qa must equal/iu],
        ["PHP command", (pkg) => { pkg.scripts.test = "php artisan test"; }, /PHP\/Composer/iu],
        ["test:qa PHP command", (pkg) => { pkg.scripts["test:qa"] = "php artisan test"; }, /PHP\/Composer|test:qa must equal/iu],
        ["Docker command", (pkg) => { pkg.scripts.test = "docker compose run --rm app vitest"; }, /Docker\/Podman/iu],
        ["test:qa Docker command", (pkg) => { pkg.scripts["test:qa"] = "docker compose run --rm app node --test"; }, /Docker\/Podman|test:qa must equal/iu],
        ["E2E command", (pkg) => { pkg.scripts.test = "playwright test"; }, /E2E runner/iu],
        ["unsafe preserved alias", (pkg) => { pkg.scripts["test:ui"] = "npm run test"; }, /test:ui must remain/iu],
    ];

    for (const [label, mutate, expectedIssue] of mutations) {
        const fixture = clonePackage(readPackage());
        mutate(fixture);
        const issues = commandContractIssues(fixture);
        assert.ok(issues.some((issue) => expectedIssue.test(issue)), `${label} was accepted: ${JSON.stringify(issues)}`);
    }
});

test("rejects malformed package JSON before command validation", () => {
    assert.throws(() => JSON.parse('{"scripts":'), SyntaxError);
});

test("separates Vitest from Node-runner QA suites while preserving defaults", async () => {
    const configModule = await import(`${pathToFileURL(vitestConfigPath).href}?node-command-contract`);
    const excludes = configModule.default?.test?.exclude;

    assert.ok(Array.isArray(excludes), "vitest test.exclude must be an array");
    assert.ok(excludes.includes("tests/qa/**"), "Vitest must exclude Node-runner QA suites explicitly");
    for (const defaultExclude of configDefaults.exclude) {
        assert.ok(excludes.includes(defaultExclude), `Vitest default exclusion was removed: ${defaultExclude}`);
    }
});
