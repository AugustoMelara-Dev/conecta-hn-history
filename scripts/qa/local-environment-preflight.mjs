import { access } from "node:fs/promises";
import { constants } from "node:fs";
import { spawnSync } from "node:child_process";

const failures = [];
const required = [
    "artisan",
    "composer.json",
    "package.json",
    ".env",
    "storage/framework",
    "bootstrap/cache",
];

if (Number(process.versions.node.split(".")[0]) < 20) {
    failures.push(`Node ${process.versions.node} is unsupported; use Node 20 or newer.`);
}

for (const path of required) {
    try {
        await access(path, constants.R_OK);
    } catch {
        failures.push(`Missing or unreadable: ${path}`);
    }
}

for (const path of ["storage/framework", "bootstrap/cache"]) {
    try {
        await access(path, constants.W_OK);
    } catch {
        failures.push(`Directory is not writable: ${path}`);
    }
}

const phpCommand = process.env.PHP_BIN || "php";
const php = spawnSync(phpCommand, ["--version"], {
    encoding: "utf8",
});
if (php.status !== 0) {
    failures.push("PHP is not available on PATH.");
}

const phpVersion = php.status === 0
    ? php.stdout.match(/^PHP\s+(\d+\.\d+\.\d+)/)?.[1]
    : undefined;
if (php.status === 0 && !phpVersion) {
    failures.push("PHP version could not be determined.");
} else if (phpVersion && Number(phpVersion.split(".")[0]) < 8) {
    failures.push(`PHP ${phpVersion} is unsupported; use PHP 8.2 or newer.`);
}

if (failures.length) {
    console.error("Local preflight failed:");
    failures.forEach((failure) => console.error(`- ${failure}`));
    process.exit(1);
}

console.log(`Local preflight passed: Node ${process.versions.node}, PHP ${phpVersion}.`);
