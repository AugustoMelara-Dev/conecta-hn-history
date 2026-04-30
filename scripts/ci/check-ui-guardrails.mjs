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

const surfaceBudgets = [
    {
        id: 'public',
        label: 'Public acquisition React surfaces',
        paths: [
            'resources/js/Pages/Welcome.jsx',
            'resources/js/Pages/Public/Explore.jsx',
            'resources/js/Pages/Directory/Index.jsx',
            'resources/js/Pages/Legal/',
            'resources/js/Layouts/PublicLayout.jsx',
            'resources/js/Components/Organisms/SmartNavbar.jsx',
            'resources/js/Components/Feedback/',
            'resources/js/Components/Public/',
        ],
        budgets: {
            'raw-tailwind-colors': 10,
            'space-axis-utilities': 4,
            'transition-all': 4,
            'outline-none': 4,
            'inline-style': 7,
            'manual-modal-shells': 0,
            'html-injection': 0,
        },
    },
    {
        id: 'microsite',
        label: 'Microsite, storefront, checkout, and booking React surfaces',
        paths: [
            'resources/js/Pages/Tenant/Show.jsx',
            'resources/js/Pages/Tenant/StoreHome.jsx',
            'resources/js/Pages/Tenant/ProductDetails.jsx',
            'resources/js/Pages/Tenant/Checkout.jsx',
            'resources/js/Pages/Public/Book/',
            'resources/js/Components/Tenant/',
        ],
        budgets: {
            'raw-tailwind-colors': 9,
            'space-axis-utilities': 1,
            'transition-all': 5,
            'outline-none': 2,
            'inline-style': 6,
            'manual-modal-shells': 0,
            'html-injection': 1,
        },
    },
    {
        id: 'auth-profile',
        label: 'Authentication, registration, account, and buyer profile surfaces',
        paths: [
            'resources/js/Pages/Auth/',
            'resources/js/Components/Auth/',
            'resources/js/Pages/Profile/',
            'resources/js/Pages/Buyer/Profile.jsx',
        ],
        budgets: {
            'raw-tailwind-colors': 15,
            'space-axis-utilities': 7,
            'transition-all': 0,
            'outline-none': 2,
            'inline-style': 2,
            'manual-modal-shells': 0,
            'html-injection': 0,
        },
    },
    {
        id: 'app-panel',
        label: 'Filament business app panel surfaces',
        paths: [
            'resources/views/filament/app/',
            'app/Filament/App/',
            'resources/css/filament/shared/',
        ],
        budgets: {
            'raw-tailwind-colors': 2,
            'space-axis-utilities': 1,
            'transition-all': 0,
            'outline-none': 1,
            'inline-style': 0,
            'manual-modal-shells': 0,
            'html-injection': 0,
        },
    },
    {
        id: 'admin-panel',
        label: 'Filament super-admin panel surfaces',
        paths: [
            'resources/views/filament/pages/',
            'resources/views/filament/resources/',
            'resources/views/filament/widgets/',
            'resources/views/filament/auth/',
            'resources/views/filament/components/',
            'resources/views/filament/qr-card.blade.php',
            'app/Filament/Resources/',
            'app/Filament/Pages/',
            'app/Filament/Widgets/',
            'app/Filament/Auth/',
        ],
        budgets: {
            'raw-tailwind-colors': 2,
            'space-axis-utilities': 0,
            'transition-all': 0,
            'outline-none': 0,
            'inline-style': 0,
            'manual-modal-shells': 0,
            'html-injection': 0,
        },
    },
    {
        id: 'legacy',
        label: 'Legacy workspace, Livewire, and compatibility surfaces',
        paths: [
            'resources/views/livewire/',
            'resources/views/components/',
            'resources/views/layouts/',
            'resources/js/Pages/Tenant/Products/',
            'resources/js/Pages/Tenant/Settings/',
            'resources/js/Pages/Tenant/Billing/',
            'resources/js/Pages/Tenant/Orders/',
            'resources/js/Pages/Tenant/Reports/',
            'resources/js/Pages/Tenant/Marketing/',
            'resources/js/Pages/Tenant/Dashboard.jsx',
            'resources/js/Pages/Tenant/Upgrade.jsx',
            'resources/js/Pages/UserDashboard.jsx',
            'resources/js/Pages/AuthDemo.jsx',
        ],
        budgets: {
            'raw-tailwind-colors': 38,
            'space-axis-utilities': 23,
            'transition-all': 26,
            'outline-none': 3,
            'inline-style': 3,
            'manual-modal-shells': 6,
            'html-injection': 1,
        },
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

    return scanRuleInFiles(rule, files);
}

function scanRuleInFiles(rule, files) {
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
                file: path.isAbsolute(file) ? toPosix(path.relative(root, file)) : toPosix(file),
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

function matchesSurface(file, surface) {
    return surface.paths.some((surfacePath) => file === surfacePath || file.startsWith(surfacePath));
}

function surfaceFiles(surface) {
    const files = [];

    for (const surfacePath of surface.paths) {
        const absolutePath = path.join(root, surfacePath);

        if (!fs.existsSync(absolutePath)) {
            continue;
        }

        if (fs.statSync(absolutePath).isFile()) {
            files.push(surfacePath);
            continue;
        }

        files.push(...walk(surfacePath));
    }

    return Array.from(new Set(files));
}

function scanSurfaceRule(rule, surface) {
    return scanRuleInFiles(rule, surfaceFiles(surface)).filter((match) => matchesSurface(match.file, surface));
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

console.log('\nSurface UI budgets:');

for (const surface of surfaceBudgets) {
    for (const rule of debtRules) {
        const maxFiles = surface.budgets[rule.id];

        if (typeof maxFiles !== 'number') {
            continue;
        }

        const matches = scanSurfaceRule(rule, surface);
        const count = matches.length;
        const status = count <= maxFiles ? 'ok' : 'fail';

        console.log(`${status.toUpperCase()} ${surface.id}:${rule.id}: ${count}/${maxFiles} files`);

        if (count > maxFiles) {
            failures.push([
                `${surface.label} exceeded ${rule.label} surface budget (${count}/${maxFiles}).`,
                rule.guidance,
                formatExamples(matches),
            ].filter(Boolean).join('\n'));
        }
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
