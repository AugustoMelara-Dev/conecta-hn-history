import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const encodingPattern = /(?:\u00c3|\u00c2|\uFFFD|\u00ef\u00bf\u00bd)/g;
const allowlistPath = path.join(root, 'scripts/ci/encoding-global-allowlist.json');

const includeExt = new Set([
    '.php',
    '.blade.php',
    '.js',
    '.jsx',
    '.ts',
    '.tsx',
    '.md',
    '.json',
    '.yml',
    '.yaml',
    '.txt',
]);

const ignoredDirs = new Set([
    '.git',
    'vendor',
    'node_modules',
    'storage',
    'bootstrap/cache',
    'public/build',
    '_cleanup-backup',
    'reports',
    'artifacts',
    'docs/frontend/audit-artifacts',
]);

function toPosix(p) {
    return p.split(path.sep).join('/');
}

function isIgnored(relativePath) {
    const normalized = toPosix(relativePath);
    return Array.from(ignoredDirs).some((dir) => normalized === dir || normalized.startsWith(`${dir}/`));
}

function shouldScan(relativePath) {
    const normalized = toPosix(relativePath);
    if (/^phpstan-report.*\.json$/i.test(path.basename(normalized))) {
        return false;
    }
    for (const ext of includeExt) {
        if (normalized.endsWith(ext)) {
            return true;
        }
    }
    return false;
}

function walk(dir) {
    const absDir = path.join(root, dir);
    if (!fs.existsSync(absDir)) {
        return [];
    }

    const files = [];
    const stack = [absDir];

    while (stack.length > 0) {
        const current = stack.pop();
        const entries = fs.readdirSync(current, { withFileTypes: true });

        for (const entry of entries) {
            const absPath = path.join(current, entry.name);
            const relPath = path.relative(root, absPath);

            if (isIgnored(relPath)) {
                continue;
            }

            if (entry.isDirectory()) {
                stack.push(absPath);
                continue;
            }

            if (!entry.isFile() || !shouldScan(relPath)) {
                continue;
            }

            files.push(absPath);
        }
    }

    return files;
}

function readAllowlist() {
    if (!fs.existsSync(allowlistPath)) {
        return new Set();
    }

    const data = JSON.parse(fs.readFileSync(allowlistPath, 'utf8'));
    return new Set((data.allowed ?? []).map((entry) => `${entry.file}:${entry.line}`));
}

const files = walk('.');
const hits = [];
const allowlist = readAllowlist();

for (const file of files) {
    const content = fs.readFileSync(file, 'utf8');
    const lines = content.split(/\r?\n/);
    const badLines = [];

    for (let i = 0; i < lines.length; i += 1) {
        encodingPattern.lastIndex = 0;
        if (encodingPattern.test(lines[i])) {
            badLines.push(i + 1);
        }
    }

    const filtered = badLines.filter((line) => !allowlist.has(`${toPosix(path.relative(root, file))}:${line}`));

    if (filtered.length > 0) {
        hits.push({
            file: toPosix(path.relative(root, file)),
            lines: filtered,
        });
    }
}

if (hits.length === 0) {
    console.log('OK visible-encoding-global: 0 files');
    process.exit(0);
}

console.error(`FAIL visible-encoding-global: ${hits.length} files`);
for (const hit of hits.slice(0, 30)) {
    console.error(` - ${hit.file}:${hit.lines[0]}`);
}

process.exit(1);
