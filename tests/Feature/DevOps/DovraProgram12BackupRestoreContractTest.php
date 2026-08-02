<?php

declare(strict_types=1);

it('documents local restore rehearsal separately from managed aws proof', function (): void {
    $boundaries = json_decode(
        (string) file_get_contents(base_path('deployment/dovra/program12-recovery-boundaries.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $fixture = json_decode(
        (string) file_get_contents(base_path('deployment/dovra/program12-local-restore-fixture.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $runbook = (string) file_get_contents(base_path('devops/runbooks/dovra/program12-backup-restore.md'));

    expect($boundaries['targets'])->toMatchArray([
        'rpo_minutes' => 15,
        'rto_hours' => 4,
    ])
        ->and($boundaries['local_rehearsal']['status'])->toBe('proved')
        ->and($boundaries['local_rehearsal']['scope'])->toBe('repository-controlled artifact rehearsal')
        ->and($boundaries['managed_aws_proof']['status'])->toBe('external')
        ->and($boundaries['managed_aws_proof']['required_evidence'])->toContain(
            'LatestRestorableTime',
            'requested_restore_point',
            'observed_rpo_minutes',
            'observed_rto_minutes',
        )
        ->and($fixture['snapshot']['tenants'])->toBeArray()->toHaveCount(2)
        ->and($fixture['snapshot']['artifacts'])->toContain('postgres-schema.json', 'private-objects.json')
        ->and($runbook)
        ->toContain('local rehearsal does not prove managed PITR')
        ->toContain('LatestRestorableTime')
        ->toContain('isolated restore directory')
        ->toContain('synthetic fixture')
        ->toContain('external AWS proof');
});

it('ships reproducible local rehearsal scripts without converting them into cloud proof', function (): void {
    $rehearsal = (string) file_get_contents(base_path('scripts/dovra/rehearse-program12-local-restore.ps1'));
    $verification = (string) file_get_contents(base_path('scripts/dovra/verify-program12-slo-contracts.ps1'));

    expect($rehearsal)
        ->toContain('param(')
        ->toContain('program12-local-restore-fixture.json')
        ->toContain('Get-FileHash')
        ->toContain('isolated restore directory')
        ->toContain('managed AWS proof remains external')
        ->and($verification)
        ->toContain("'proved'")
        ->toContain("'incomplete'")
        ->toContain("'external'")
        ->toContain("'failed'")
        ->toContain('program12-observability-contract.json')
        ->toContain('program12-queue-resilience-contract.json')
        ->toContain('program12-recovery-boundaries.json');
});
