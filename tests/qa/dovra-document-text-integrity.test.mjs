import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';

const repositoryRoot = path.resolve(import.meta.dirname, '../..');
const canonicalDocuments = [
    'docs/dovra/implementation-ledger.md',
];
const ledgerSemanticMarkers = [
    '4. Programs 03–04 secure tenant identity and canonical business operations.',
    '5. Programs 05–07 add governed intelligence, actions, workflows, and channel adapters.',
    '6. Programs 08–10 deliver the marketplace, commercial packaging, and complete product experience.',
    '7. Programs 11–12 harden and operate the exact architecture built above.',
    '**8,018–9,020 SELECTs at 501**',
    '## Task 8 evidence boundary — final workforce integration validation',
    '`onboarding → graph backfill → role resolution → simulated conversation → governed action → outcome → channel lineage → Discover capability projection → billing entitlement`',
    'The implementation follows `expand → backfill → validate → contract`',
    '| Program 00–14 scan | Pass: 15 dated program evidence files, AC01–AC16 and LG01–LG18',
    '## 2026-08-04 session — Codex integrated session',
    '- Programs 06, 08, 10–14 bounded re-certification',
    '- Agent A — Program 00 + Landing brand:',
    '- Agent D — Ledger/Evidence:',
];

function documentIntegrityFindings(relativePath, bytes, requiredMarkers = []) {
    const findings = [];

    for (let offset = 0; offset < bytes.length; offset += 1) {
        const byte = bytes[offset];

        if (byte < 0x20 && ! [0x09, 0x0a, 0x0d].includes(byte)) {
            findings.push(`${relativePath}:${offset}:C0_CONTROL`);
        }
    }

    let content;

    try {
        content = new TextDecoder('utf-8', { fatal: true }).decode(bytes);
    } catch {
        findings.push(`${relativePath}:0:INVALID_UTF8`);

        return findings;
    }

    if (content.includes('\uFFFD')) {
        findings.push(`${relativePath}:${content.indexOf('\uFFFD')}:REPLACEMENT_CHARACTER`);
    }

    for (const marker of requiredMarkers) {
        if (! content.includes(marker)) {
            findings.push(`${relativePath}:0:MISSING_SEMANTIC_MARKER`);
        }
    }

    return findings;
}

test('canonical Dovra documents are valid UTF-8 without control-byte or semantic corruption', () => {
    const findings = canonicalDocuments.flatMap((relativePath) => {
        const bytes = fs.readFileSync(path.join(repositoryRoot, relativePath));
        const markers = relativePath.endsWith('implementation-ledger.md') ? ledgerSemanticMarkers : [];

        return documentIntegrityFindings(relativePath, bytes, markers);
    });

    const c0Findings = findings.filter((finding) => finding.endsWith(':C0_CONTROL'));
    const nulOffsetFinding = findings.find((finding) => finding.includes(':51237:'));

    assert.equal(
        findings.length,
        0,
        `document integrity findings: C0=${c0Findings.length}; first=${findings[0] ?? 'none'}; offset51237=${nulOffsetFinding ?? 'none'}`,
    );
});

test('integrity findings identify paths and byte offsets without echoing document content', () => {
    const unsafe = Buffer.concat([
        Buffer.from('safe-prefix', 'utf8'),
        Buffer.from([0x00]),
        Buffer.from('private-document-content', 'utf8'),
    ]);
    const findings = documentIntegrityFindings('docs/dovra/example.md', unsafe);

    assert.deepEqual(findings, ['docs/dovra/example.md:11:C0_CONTROL']);
    assert.doesNotMatch(findings.join('\n'), /private-document-content/);
});

test('semantic markers cannot be replaced by clean but incomplete prose', () => {
    const findings = documentIntegrityFindings(
        'docs/dovra/implementation-ledger.md',
        Buffer.from('A syntactically clean ledger without its canonical chain.', 'utf8'),
        ledgerSemanticMarkers,
    );

    assert.equal(findings.length, ledgerSemanticMarkers.length);
    assert.ok(findings.every((finding) => finding.endsWith(':MISSING_SEMANTIC_MARKER')));
});
