<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

function program12FixRoundTwoRoot(): string
{
    return dirname(__DIR__, 3);
}

it('classifies shared runtime seams as incomplete or external without loading them', function (): void {
    $root = program12FixRoundTwoRoot();
    $observability = json_decode(
        (string) file_get_contents($root.'/deployment/dovra/program12-observability-contract.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $queue = json_decode(
        (string) file_get_contents($root.'/deployment/dovra/program12-queue-resilience-contract.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $script = (string) file_get_contents($root.'/scripts/dovra/verify-program12-slo-contracts.ps1');

    foreach (['logging', 'sentry', 'opentelemetry', 'runtime_topology'] as $seam) {
        expect(in_array($observability['shared_runtime_seams'][$seam]['status'] ?? null, ['incomplete', 'external'], true))
            ->toBeTrue();
    }

    expect($script)
        ->toContain("'shared-observability-runtime'")
        ->toContain("'incomplete'")
        ->toContain("'external'")
        ->not->toContain('Get-Content -Raw $loggingPath')
        ->not->toContain('Get-Content -Raw $sentryPath')
        ->not->toContain('Get-Content -Raw $observabilityPath')
        ->not->toContain('Get-Content -Raw $queuePath')
        ->not->toContain('Get-Content -Raw $horizonPath');
});

it('runs the operational harness using only owned artifacts and no shared config references', function (): void {
    $root = program12FixRoundTwoRoot();
    $observability = json_decode(
        (string) file_get_contents($root.'/deployment/dovra/program12-observability-contract.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $queue = json_decode(
        (string) file_get_contents($root.'/deployment/dovra/program12-queue-resilience-contract.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $process = new Process([
        PHP_BINARY,
        $root.'/scripts/dovra/program12-operational-contract-harness.php',
        '--json',
    ], $root);

    $process->setTimeout(20);
    $exitCode = $process->run();
    $payload = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    $ownedSources = [
        $root.'/scripts/dovra/verify-program12-slo-contracts.ps1',
        $root.'/scripts/dovra/program12-operational-contract-harness.php',
        $root.'/tests/Feature/DevOps/DovraProgram12ObservabilityContractTest.php',
        $root.'/tests/Feature/DevOps/DovraProgram12QueueResilienceContractTest.php',
        $root.'/tests/Feature/DevOps/DovraProgram12OperationalContractHarnessTest.php',
        $root.'/tests/Feature/DevOps/DovraProgram12ProductionConfigGuardTest.php',
        $root.'/tests/Architecture/ObservabilityRatchetTest.php',
        __FILE__,
    ];
    $forbiddenReferences = [];
    foreach ([$observability['shared_runtime_seams'], $queue['shared_runtime_seams']] as $seamGroup) {
        foreach ($seamGroup as $seam) {
            foreach (preg_split('/\s+\+\s+/', (string) $seam['path']) as $path) {
                if ($path !== '') {
                    $forbiddenReferences[] = $path;
                }
            }
        }
    }
    $forbiddenReferences = array_values(array_unique($forbiddenReferences));

    expect($exitCode)->toBe(0)
        ->and($payload['status'] ?? null)->toBe('proved')
        ->and($payload['trace']['status'] ?? null)->toBe('proved')
        ->and($payload['queue']['status'] ?? null)->toBe('proved');

    foreach ($ownedSources as $source) {
        $contents = (string) file_get_contents($source);

        foreach ($forbiddenReferences as $forbiddenReference) {
            expect($contents)->not->toContain($forbiddenReference);
        }
    }
});
