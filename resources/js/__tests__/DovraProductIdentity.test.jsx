import fs from 'node:fs';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

import { PUBLIC_BRAND } from '@/lib/publicBrand';

describe('Dovra product identity', () => {
    it('exposes the canonical global product facts without invented contact endpoints', () => {
        expect(PUBLIC_BRAND).toEqual({
            name: 'Dovra AI',
            shortName: 'Dovra',
            category: 'Global Conversational Business OS',
            commercialPromise:
                'Dovra deploys AI employees that know a business, work through its channels, and turn conversations into measurable results.',
            supportEmail: null,
            supportWhatsAppUrl: null,
            websiteUrl: null,
        });
    });

    it('has no active frontend source on the legacy product identity', () => {
        const root = path.resolve(process.cwd(), 'resources/js');
        const violations = [];

        const visit = (directory) => {
            for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
                const absolutePath = path.join(directory, entry.name);

                if (entry.isDirectory()) {
                    if (entry.name !== '__tests__') {
                        visit(absolutePath);
                    }
                    continue;
                }

                if (!entry.isFile() || !/\.(?:js|jsx|ts|tsx)$/.test(entry.name)) {
                    continue;
                }

                const contents = fs.readFileSync(absolutePath, 'utf8');
                if (/Conecta HN|ConectaHN|conecta-hn/ui.test(contents)) {
                    violations.push(path.relative(process.cwd(), absolutePath).replaceAll('\\', '/'));
                }
            }
        };

        visit(root);
        violations.sort();

        expect(violations).toEqual([]);
    });

    it('keeps shared brand-bearing components on the canonical identity module', () => {
        const sharedComponents = [
            'resources/js/Components/ApplicationLogo.jsx',
            'resources/js/Components/Auth/AuthShadcnShell.jsx',
            'resources/js/Components/Organisms/Auth/GlobalLoginModal.jsx',
            'resources/js/Components/Seo/SeoHead.jsx',
        ];

        for (const relativePath of sharedComponents) {
            const contents = fs.readFileSync(path.resolve(process.cwd(), relativePath), 'utf8');

            expect(contents, relativePath).toContain(
                "import { PUBLIC_BRAND } from '@/lib/publicBrand';",
            );
            expect(contents, relativePath).toContain('PUBLIC_BRAND.');
        }
    });

    it('keeps the global Inertia title independent from legacy environment branding', () => {
        const contents = fs.readFileSync(
            path.resolve(process.cwd(), 'resources/js/app.jsx'),
            'utf8',
        );

        expect(contents).toContain("import { PUBLIC_BRAND } from '@/lib/publicBrand';");
        expect(contents).toContain('const appName = PUBLIC_BRAND.name;');
        expect(contents).not.toContain('VITE_APP_NAME');
    });
});
