import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

const appSourceDirs = [
    'resources/js/Pages',
    'resources/js/Components',
];

const runtimeVisibleDirs = [
    'app',
    'resources/js',
    'resources/views',
    'config',
    'public',
];

const ignoredDirs = new Set([
    'node_modules',
    'vendor',
    '.git',
    'storage',
    'bootstrap/cache',
    'resources/js/Components/ui',
]);

const debtRules = [
    {
        id: 'raw-tailwind-colors',
        label: 'Raw Tailwind color utilities in React app surfaces',
        dirs: appSourceDirs,
        pattern: /\b(?:bg|text|border|ring|from|to|via)-(?:slate|gray|zinc|neutral|stone|blue|indigo|violet|purple|emerald|green|red|rose|amber|yellow|orange|teal|cyan)-\d{2,3}\b/g,
        maxFiles: 95,
        guidance: 'Use semantic tokens, shadcn variants, or tenant CSS variables.',
    },
    {
        id: 'space-axis-utilities',
        label: 'space-x/space-y utilities',
        dirs: appSourceDirs,
        pattern: /\bspace-[xy]-/g,
        maxFiles: 43,
        guidance: 'Use flex/grid with gap-* instead.',
    },
    {
        id: 'transition-all',
        label: 'transition-all usage',
        dirs: appSourceDirs,
        pattern: /\btransition-all\b/g,
        maxFiles: 42,
        guidance: 'Use component defaults or explicit transition properties.',
    },
    {
        id: 'outline-none',
        label: 'outline-none usage',
        dirs: appSourceDirs,
        pattern: /\b(?:focus:)?outline-none\b/g,
        maxFiles: 44,
        guidance: 'Keep focus visible with focus-visible rings or shadcn defaults.',
    },
    {
        id: 'inline-style',
        label: 'Inline React style objects',
        dirs: appSourceDirs,
        pattern: /style=\{\{/g,
        maxFiles: 30,
        guidance: 'Prefer tokens/classes; allow only audited tenant CSS variable bridges.',
    },
    {
        id: 'manual-modal-shells',
        label: 'Manual modal shells',
        dirs: appSourceDirs,
        pattern: /fixed\s+inset-0/g,
        maxFiles: 8,
        guidance: 'Use Dialog, Sheet, or AlertDialog instead of hand-built overlays.',
    },
    {
        id: 'html-injection',
        label: 'dangerouslySetInnerHTML usage',
        dirs: appSourceDirs,
        pattern: /dangerouslySetInnerHTML/g,
        maxFiles: 5,
        guidance: 'Render structured content or document sanitization/JSON-LD exceptions.',
    },
];

const zeroToleranceRules = [
    {
        id: 'visible-mojibake',
        label: 'Mojibake in user-visible runtime source',
        dirs: runtimeVisibleDirs,
        pattern: /(?:\u00c3|\u00c2|\uFFFD|\u00ef\u00bf\u00bd)/g,
        guidance: 'Fix encoding before shipping user-visible copy.',
    },
];

function toPosix(relativePath) {
    return relativePath.split(path.sep).join('/');
}

function shouldSkip(relativePath) {
    const normalized = toPosix(relativePath);

    return Array.from(ignoredDirs).some((ignored) => normalized === ignored || normalized.startsWith(`${ignored}/`));
}

function walk(dir, extensions = ['.js', '.jsx', '.ts', '.tsx', '.php', '.blade.php', '.css', '.json']) {
    const absoluteDir = path.join(root, dir);

    if (!fs.existsSync(absoluteDir)) {
        return [];
    }

    const files = [];
    const stack = [absoluteDir];

    while (stack.length > 0) {
        const current = stack.pop();
        const entries = fs.readdirSync(current, { withFileTypes: true });

        for (const entry of entries) {
            const absolutePath = path.join(current, entry.name);
            const relativePath = path.relative(root, absolutePath);

            if (shouldSkip(relativePath)) {
                continue;
            }

            if (entry.isDirectory()) {
                stack.push(absolutePath);
                continue;
            }

            if (!entry.isFile()) {
                continue;
            }

            const normalized = toPosix(relativePath);
            const matchesExtension = extensions.some((extension) => normalized.endsWith(extension));

            if (matchesExtension) {
                files.push(absolutePath);
            }
        }
    }

    return files;
}

function scanRule(rule) {
    const files = Array.from(new Set(rule.dirs.flatMap((dir) => walk(dir))));
    const matches = [];

    for (const file of files) {
        const source = fs.readFileSync(file, 'utf8');
        const lineNumbers = [];
        const lines = source.split(/\r?\n/);

        for (let index = 0; index < lines.length; index += 1) {
            rule.pattern.lastIndex = 0;

            if (rule.pattern.test(lines[index])) {
                lineNumbers.push(index + 1);
            }
        }

        if (lineNumbers.length > 0) {
            matches.push({
                file: toPosix(path.relative(root, file)),
                lines: lineNumbers,
            });
        }
    }

    return matches;
}

function formatExamples(matches) {
    return matches
        .slice(0, 8)
        .map((match) => `  - ${match.file}:${match.lines[0]}`)
        .join('\n');
}

const failures = [];

for (const rule of debtRules) {
    const matches = scanRule(rule);
    const count = matches.length;
    const status = count <= rule.maxFiles ? 'ok' : 'fail';

    console.log(`${status.toUpperCase()} ${rule.id}: ${count}/${rule.maxFiles} files`);

    if (count > rule.maxFiles) {
        failures.push([
            `${rule.label} exceeded the current baseline (${count}/${rule.maxFiles}).`,
            rule.guidance,
            formatExamples(matches),
        ].filter(Boolean).join('\n'));
    }
}

for (const rule of zeroToleranceRules) {
    const matches = scanRule(rule);

    console.log(`${matches.length === 0 ? 'OK' : 'FAIL'} ${rule.id}: ${matches.length} files`);

    if (matches.length > 0) {
        failures.push([
            `${rule.label} must be zero.`,
            rule.guidance,
            formatExamples(matches),
        ].filter(Boolean).join('\n'));
    }
}

if (failures.length > 0) {
    console.error('\nUI guardrail violations:\n');
    console.error(failures.join('\n\n'));
    process.exitCode = 1;
} else {
    console.log('\nUI guardrails passed.');
}
