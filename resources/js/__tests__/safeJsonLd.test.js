import { safeJsonLd } from '@/lib/safeJsonLd';

describe('safeJsonLd', () => {
    it('escapes script-breaking characters in structured data', () => {
        const json = safeJsonLd({
            name: '</script><script>alert("x")</script>',
            description: 'A & B',
        });

        expect(json).toContain('\\u003C/script\\u003E');
        expect(json).toContain('\\u0026');
        expect(json).not.toContain('</script>');
    });
});
