/**
 * @jest-environment jsdom
 */

import { describe, expect, it } from 'vitest';

import { safeInternalDestination } from '@/Components/Auth/QuickAccessForm';
import { isTrustedImageSource } from '@/Components/Commercial/AnnouncementBanner';
import { safeJsonLd } from '@/lib/safeJsonLd';

describe('frontend security boundaries', () => {
    it('escapes script-breaking JSON-LD characters', () => {
        const serialized = safeJsonLd({ name: '</script><script>alert(1)</script>&' });

        expect(serialized).not.toContain('</script>');
        expect(serialized).not.toContain('<');
        expect(serialized).toContain('\\u003C/script\\u003E');
        expect(serialized).toContain('\\u0026');
    });

    it('allows only same-origin authentication destinations', () => {
        const origin = 'https://conecta.test';

        expect(safeInternalDestination('/app/negocio?tab=ventas#hoy', '/directorio', origin))
            .toBe('/app/negocio?tab=ventas#hoy');
        expect(safeInternalDestination('https://conecta.test/app', '/directorio', origin))
            .toBe('/app');
        expect(safeInternalDestination('//evil.test/phish', '/directorio', origin))
            .toBe('/directorio');
        expect(safeInternalDestination('javascript:alert(1)', '/directorio', origin))
            .toBe('/directorio');
    });

    it('matches trusted image origins exactly instead of by prefix', () => {
        const trusted = ['https://images.conecta.test'];

        expect(isTrustedImageSource('https://images.conecta.test/banner.webp', trusted)).toBe(true);
        expect(isTrustedImageSource('https://images.conecta.test.evil.test/banner.webp', trusted)).toBe(false);
        expect(isTrustedImageSource('javascript:alert(1)', trusted)).toBe(false);
    });
});
