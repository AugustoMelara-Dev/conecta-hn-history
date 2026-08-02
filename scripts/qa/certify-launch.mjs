import { existsSync } from "node:fs";
import { spawnSync } from "node:child_process";

const args = new Set(process.argv.slice(2));
const dryRun = args.has("--dry-run");
const external = args.has("--external");
const npm = process.platform === "win32" ? "npm.cmd" : "npm";
const php = process.env.PHP_BIN || "php";

const composer = process.env.COMPOSER_BIN
    ? { command: process.env.COMPOSER_BIN, prefix: [] }
    : process.platform === "win32" && existsSync("C:\\xampp\\php\\composer.phar")
        ? { command: php, prefix: ["C:\\xampp\\php\\composer.phar"] }
        : { command: "composer", prefix: [] };

const codeGates = [
    ["Local environment preflight", "node", ["scripts/qa/local-environment-preflight.mjs"]],
    ["Composer manifest validation", composer.command, [...composer.prefix, "validate", "--strict"]],
    ["Composer advisory audit", composer.command, [...composer.prefix, "audit", "--locked", "--no-interaction"]],
    ["PHP static analysis", php, ["vendor/bin/phpstan", "analyse", "--memory-limit=2G"]],
    ["Portable backend regression", php, ["vendor/bin/pest", "--configuration=phpunit.sqlite.xml"]],
    ["npm advisory audit", npm, ["audit", "--audit-level=high"]],
    ["TypeScript", npm, ["run", "type-check"]],
    ["UI unit tests", npm, ["run", "test:ui"]],
    ["UI guardrails", npm, ["run", "check:ui-guardrails"]],
    ["Visible encoding", npm, ["run", "check:encoding-global"]],
    ["Production frontend build", npm, ["run", "build"]],
    ["Frontend asset budget", npm, ["run", "perf:budget"]],
];

const externalGates = [
    ["Authentication cookie boundary", npm, ["run", "perf:auth-cookie"]],
    ["Production-like p95 smoke", npm, ["run", "perf:p95-smoke"]],
    ["Critical deployed E2E/WCAG journeys", npm, ["run", "test:e2e:release:critical"]],
];

function assertExternalConfiguration() {
    const required = [
        "PERF_BASE_URL",
        "PLAYWRIGHT_BASE_URL",
        "SMOKE_OWNER_EMAIL",
        "SMOKE_OWNER_PASSWORD",
        "SMOKE_CUSTOMER_EMAIL",
        "SMOKE_CUSTOMER_PASSWORD",
        "SMOKE_PRODUCT_TENANT_SLUG",
        "SMOKE_PRODUCT_MICROSITE_URL",
    ];
    const missing = required.filter((name) => !process.env[name]?.trim());
    if (missing.length) {
        throw new Error(`Missing external certification variables: ${missing.join(", ")}`);
    }

    for (const name of ["PERF_BASE_URL", "PLAYWRIGHT_BASE_URL", "SMOKE_PRODUCT_MICROSITE_URL"]) {
        if (!process.env[name].startsWith("https://")) {
            throw new Error(`${name} must use HTTPS for external release certification.`);
        }
    }
}

function displayCommand(command, commandArgs) {
    return [command, ...commandArgs]
        .map((part) => part.includes(" ") ? JSON.stringify(part) : part)
        .join(" ");
}

function runGate([name, command, commandArgs]) {
    console.log(`\n[launch-certification] ${name}`);
    console.log(`$ ${displayCommand(command, commandArgs)}`);
    if (dryRun) {
        return;
    }

    const result = spawnSync(command, commandArgs, {
        cwd: process.cwd(),
        env: process.env,
        stdio: "inherit",
        shell: false,
    });
    if (result.error) {
        throw result.error;
    }
    if (result.status !== 0) {
        throw new Error(`${name} failed with exit code ${result.status}.`);
    }
}

if (external && !dryRun) {
    assertExternalConfiguration();
}

const gates = external ? externalGates : codeGates;
for (const gate of gates) {
    runGate(gate);
}

console.log(`\nLaunch ${external ? "external" : "code"} certification ${dryRun ? "plan generated" : "passed"}.`);