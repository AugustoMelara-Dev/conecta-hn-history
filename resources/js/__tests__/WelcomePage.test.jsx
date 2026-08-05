import fs from 'node:fs';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

const welcomeSource = fs.readFileSync(
    path.resolve(process.cwd(), 'resources/js/Pages/Welcome.jsx'),
    'utf8',
);

describe('Dovra canonical landing narrative', () => {
    it('presents the four connected product engines and both customer journeys', () => {
        expect(welcomeSource).toContain('Dovra Business');
        expect(welcomeSource).toContain('Dovra Agents');
        expect(welcomeSource).toContain('Dovra Automate');
        expect(welcomeSource).toContain('Dovra Discover');
        expect(welcomeSource).toContain('Para negocios');
        expect(welcomeSource).toContain('Para compradores');
    });

    it('states governed AI, Explore boundaries, and the commission-free model honestly', () => {
        expect(welcomeSource).toContain('La IA propone; las políticas de Dovra autorizan');
        expect(welcomeSource).toContain('Explore');
        expect(welcomeSource).toContain('sandbox');
        expect(welcomeSource).toContain('sin comisión por transacción');
        expect(welcomeSource).toContain('según disponibilidad del proveedor y del mercado');
    });

    it('keeps Honduras as origin without fabricating traction, compliance, or legal clearance', () => {
        expect(welcomeSource).toContain('Nacida en Honduras');
        expect(welcomeSource).toContain('construida para operar globalmente');
        expect(welcomeSource).not.toMatch(/\b(?:más de\s+)?\d[\d,.]*\s+(?:clientes|negocios activos|empresas)\b/i);
        expect(welcomeSource).not.toMatch(/\b(?:SOC 2|ISO 27001|HIPAA|GDPR)\s+(?:certified|certificada|cumplimos)\b/i);
        expect(welcomeSource).not.toContain('marca registrada');
        expect(welcomeSource).not.toContain('negocios hondureños');
    });
});
