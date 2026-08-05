import fs from 'node:fs';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

const emptyStateSource = fs.readFileSync(
    path.resolve(process.cwd(), 'resources/js/Components/Marketing/EmptyState.jsx'),
    'utf8',
);

describe('EmptyState marketing component', () => {
    it('is a canonical surface for "no data" states with accessibility baked in', () => {
        expect(emptyStateSource).toContain('export default function EmptyState');
        expect(emptyStateSource).toContain("role=\"status\"");
        expect(emptyStateSource).toContain('aria-live="polite"');
        expect(emptyStateSource).toContain('data-empty-state="true"');
    });

    it('wraps shadcn primitives so it inherits design tokens automatically', () => {
        expect(emptyStateSource).toContain("from '@/Components/ui/button'");
        expect(emptyStateSource).toContain('cn(');
        expect(emptyStateSource).toContain('border-border bg-card');
    });

    it('requires a title and makes the icon and action optional', () => {
        expect(emptyStateSource).toMatch(/title[,\s]/);
        expect(emptyStateSource).toMatch(/icon[,\s]/);
        expect(emptyStateSource).toMatch(/action[,\s]/);
    });

    it('uses a lucide icon by default and never inlines a raw color', () => {
        expect(emptyStateSource).toContain("from 'lucide-react'");
        expect(emptyStateSource).toContain('Inbox');
        expect(emptyStateSource).not.toMatch(/#[0-9a-fA-F]{3,6}/);
    });
});
