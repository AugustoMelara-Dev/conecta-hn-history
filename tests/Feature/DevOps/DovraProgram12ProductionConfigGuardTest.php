<?php

declare(strict_types=1);

function program12ProductionGuardOwnedRoot(): string
{
    return dirname(__DIR__, 3);
}

it('documents fail-closed owned proof and unproven shared runtime boundaries exactly', function (): void {
    $root = program12ProductionGuardOwnedRoot();
    $evidence = (string) file_get_contents($root.'/docs/dovra/evidence/program-12-2026-08-01.md');
    $script = (string) file_get_contents($root.'/scripts/dovra/verify-program12-slo-contracts.ps1');

    expect($script)
        ->toContain('operational-contract-harness')
        ->toContain('shared-observability-runtime')
        ->toContain('shared-runtime-topology')
        ->toContain("'incomplete'")
        ->toContain("'external'")
        ->toContain('managed AWS/PITR/RPO/RTO')
        ->and($evidence)
        ->toContain('proved')
        ->toContain('incomplete')
        ->toContain('external')
        ->toContain('shared runtime')
        ->toContain('AWS')
        ->toContain('DNS')
        ->toContain('secrets')
        ->toContain('managed restore')
        ->toContain('deployment smoke');
});
