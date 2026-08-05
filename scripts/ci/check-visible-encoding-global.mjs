import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const roots = ['app', 'config', 'database', 'docs', 'resources', 'routes', 'tests']
    .map((directory) => path.join(root, directory));
const ignored = new Set(['node_modules', 'vendor', 'storage', 'bootstrap', 'public', 'build', 'coverage']);
const extensions = new Set(['.php', '.js', '.jsx', '.mjs', '.ts', '.tsx', '.json', '.md', '.blade.php']);
const suspiciousEncoding = /\u00c3[\u0080-\u00bf]|\u00c2[\u0080-\u00bf]|\u00e2\u0080[\u0080-\u00bf]|\ufffd/gu;

async function filesIn(directory) {
    const entries = await readdir(directory, { withFileTypes: true });
    const files = [];

    for (const entry of entries) {
        if (ignored.has(entry.name)) continue;
        const target = path.join(directory, entry.name);
        if (entry.isDirectory()) {
            files.push(...await filesIn(target));
            continue;
        }
        const isBlade = entry.name.endsWith('.blade.php');
        if (isBlade || extensions.has(path.extname(entry.name))) files.push(target);
    }

    return files;
}

const matches = [];
for (const scanRoot of roots) {
    for (const file of await filesIn(scanRoot)) {
        const content = await readFile(file, 'utf8');
        const lines = content.split(/\r?\n/u);
        lines.forEach((line, index) => {
            suspiciousEncoding.lastIndex = 0;
            if (suspiciousEncoding.test(line)) {
                matches.push(`${path.relative(root, file).replaceAll('\\', '/')}:${index + 1}`);
            }
        });
    }
}

if (matches.length > 0) {
    console.error(`Visible encoding check failed in ${matches.length} line(s):`);
    matches.slice(0, 100).forEach((match) => console.error(`- ${match}`));
    if (matches.length > 100) console.error(`- …and ${matches.length - 100} more`);
    process.exit(1);
}

console.log('Visible encoding check passed: no common UTF-8 mojibake sequences found.');
