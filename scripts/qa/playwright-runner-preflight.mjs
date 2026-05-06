import { spawnSync } from 'node:child_process';
import { existsSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const failures = [];
const warnings = [];

function addFailure(id, message, recovery) {
    failures.push({ id, message, recovery });
}

function addWarning(id, message, recovery) {
    warnings.push({ id, message, recovery });
}

function runNodeProbe(code, title) {
    const result = spawnSync(process.execPath, ['-e', code], {
        cwd: root,
        encoding: 'utf8',
        windowsHide: true,
    });

    if (result.error) {
        const message = String(result.error?.message ?? result.error);
        if (message.includes('EPERM')) {
            addFailure(
                `${title}-eperm`,
                `Node child_process is blocked by host policy: ${message}`,
                'Run terminal as Administrator, allow node.exe in endpoint protection, and disable Controlled Folder Access for this workspace.',
            );
            return;
        }

        addFailure(
            `${title}-spawn`,
            `Node probe failed to spawn child process: ${message}`,
            'Use a host shell/CI runner with unrestricted process spawn permissions.',
        );
        return;
    }

    if (result.status !== 0) {
        const output = `${result.stdout || ''}${result.stderr || ''}`.trim();
        addFailure(
            `${title}-status`,
            `Node probe exited with code ${result.status}. Output: ${output}`,
            'Inspect antivirus/policy restrictions. If output includes EPERM, treat as runner blocker.',
        );
    }
}

function checkPlaywrightPackage() {
    const playwrightCli = path.join(root, 'node_modules', 'playwright', 'cli.js');
    const playwrightTestCli = path.join(root, 'node_modules', '@playwright', 'test', 'cli.js');

    if (!existsSync(playwrightCli) && !existsSync(playwrightTestCli)) {
        addWarning(
            'playwright-missing',
            'Playwright package was not found in node_modules.',
            'Run npm install before browser gates.',
        );
    }
}

function checkNodeSpawnAndFork() {
    runNodeProbe("console.log('spawn-ok')", 'spawn-basic');

    const tmpDir = mkdtempSync(path.join(os.tmpdir(), 'playwright-fork-probe-'));
    const childPath = path.join(tmpDir, 'child.js');
    writeFileSync(childPath, "console.log('child-ok'); process.exit(0);", 'utf8');

    const forkCode = `
        const { fork } = require('node:child_process');
        const child = fork(${JSON.stringify(childPath)}, [], { stdio: 'pipe' });
        child.on('error', (err) => {
            console.error(String(err && err.message ? err.message : err));
            process.exit(1);
        });
        child.on('exit', (code) => {
            process.exit(code === 0 ? 0 : code || 1);
        });
    `;
    runNodeProbe(forkCode, 'fork-basic');

    rmSync(tmpDir, { recursive: true, force: true });
}

checkPlaywrightPackage();
checkNodeSpawnAndFork();

for (const warning of warnings) {
    console.warn(`WARN ${warning.id}: ${warning.message}`);
    console.warn(`  Recovery: ${warning.recovery}`);
}

if (failures.length > 0) {
    console.error('\nPlaywright runner preflight failed:\n');
    for (const failure of failures) {
        console.error(`FAIL ${failure.id}: ${failure.message}`);
        console.error(`  Recovery: ${failure.recovery}`);
    }
    process.exit(1);
}

console.log('Playwright runner preflight passed.');
