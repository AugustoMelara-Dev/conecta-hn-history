import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();

const scanRoots = [
    path.join(root, 'resources', 'js'),
    path.join(root, 'resources', 'views'),
];

const ignoredSegments = new Set([
    'node_modules',
    'vendor',
    'storage',
    'bootstrap',
    'public',
]);

const fileExtensions = new Set(['.jsx', '.js', '.tsx', '.ts', '.blade.php']);

const guardrails = [
    {
        id: 'raw-tailwind-palettes',
        label: 'Raw Tailwind palette classes',
        budget: 4355,
        pattern: /\b(?:bg|text|border|from|via|to|ring|shadow|placeholder|divide|decoration|accent|caret|fill|stroke)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-[0-9]{2,3}(?:\/[0-9]{1,3})?\b/g,
    },
    {
        id: 'transition-all',
        label: 'transition-all usage',
        budget: 215,
        pattern: /\btransition-all\b/g,
    },
    {
        id: 'outline-none',
        label: 'outline-none usage',
        budget: 65,
        pattern: /\boutline-none\b/g,
    },
    {
        id: 'space-utilities',
        label: 'space-x/space-y layout coupling',
        budget: 203,
        pattern: /\bspace-[xy]-\d/g,
    },
    {
        id: 'inline-jsx-style',
        label: 'Inline JSX style objects',
        budget: 90,
        pattern: /style=\{\{/g,
    },
    {
        id: 'inline-blade-style',
        label: 'Inline Blade/HTML style attributes',
        budget: 182,
        pattern: /\sstyle=(?:"|')/g,
    },
    {
        id: 'dangerous-html',
        label: 'dangerouslySetInnerHTML',
        budget: 5,
        pattern: /dangerouslySetInnerHTML/g,
    },
    {
        id: 'div-onclick',
        label: 'Clickable div handlers',
        budget: 12,
        pattern: /<div\b[^>]*\bonClick=/g,
    },
];

async function listFiles(dir) {
    const entries = await readdir(dir, { withFileTypes: true });
    const files = [];

    for (const entry of entries) {
        if (ignoredSegments.has(entry.name)) {
            continue;
        }

        const fullPath = path.join(dir, entry.name);

        if (entry.isDirectory()) {
            files.push(...await listFiles(fullPath));
            continue;
        }

        const isBlade = entry.name.endsWith('.blade.php');
        const extension = path.extname(entry.name);

        if (isBlade || fileExtensions.has(extension)) {
            files.push(fullPath);
        }
    }

    return files;
}

function countMatches(content, pattern) {
    pattern.lastIndex = 0;
    return Array.from(content.matchAll(pattern)).length;
}

const files = [];
for (const scanRoot of scanRoots) {
    files.push(...await listFiles(scanRoot));
}

const totals = guardrails.map((guardrail) => ({
    ...guardrail,
    count: 0,
    files: 0,
}));

for (const file of files) {
    const content = await readFile(file, 'utf8');

    for (const total of totals) {
        const count = countMatches(content, total.pattern);
        if (count > 0) {
            total.count += count;
            total.files += 1;
        }
    }
}

const failures = totals.filter((total) => total.count > total.budget);

console.log('UI guardrails baseline check');
for (const total of totals) {
    const status = total.count <= total.budget ? 'OK' : 'FAIL';
    console.log(`${status} ${total.id}: ${total.count}/${total.budget} matches in ${total.files} files`);
}

if (failures.length > 0) {
    console.error('\nUI guardrails exceeded. Refactor touched surfaces to use shadcn primitives and semantic tokens, or ratchet the baseline intentionally.');
    process.exit(1);
}
