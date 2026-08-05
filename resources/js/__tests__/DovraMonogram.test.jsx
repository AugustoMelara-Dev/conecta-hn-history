import fs from 'node:fs';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

const monogramSource = fs.readFileSync(
    path.resolve(process.cwd(), 'resources/js/Components/Brand/DovraMonogram.jsx'),
    'utf8',
);
const applicationLogoSource = fs.readFileSync(
    path.resolve(process.cwd(), 'resources/js/Components/ApplicationLogo.jsx'),
    'utf8',
);
const authSplitSource = fs.readFileSync(
    path.resolve(process.cwd(), 'resources/js/Components/Auth/AuthSplitLayout.jsx'),
    'utf8',
);
const authShellSource = fs.readFileSync(
    path.resolve(process.cwd(), 'resources/js/Components/Auth/AuthShadcnShell.jsx'),
    'utf8',
);

describe('DovraMonogram brand mark', () => {
    it('is the canonical brand identity surface for the platform', () => {
        expect(monogramSource).toContain('export default function DovraMonogram');
        expect(monogramSource).toContain("variant === 'mark'");
        expect(monogramSource).toContain("variant === 'type'");
        expect(monogramSource).toContain("variant === 'combo'");
        expect(monogramSource).toContain('currentColor');
        expect(monogramSource).toContain('aria-hidden');
    });

    it('replaces the legacy placeholder GalleryVerticalEnd everywhere it was used', () => {
        expect(authSplitSource).not.toContain('GalleryVerticalEnd');
        expect(authShellSource).not.toContain('GalleryVerticalEnd');
    });

    it('is wired into ApplicationLogo so all chrome inherits the canonical mark', () => {
        expect(applicationLogoSource).toContain("from '@/Components/Brand/DovraMonogram'");
        expect(applicationLogoSource).not.toContain('shortName.charAt(0)');
    });
});
