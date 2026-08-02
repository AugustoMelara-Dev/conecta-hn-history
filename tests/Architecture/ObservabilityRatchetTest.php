<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ObservabilityRatchetTest extends TestCase
{
    #[Test]
    public function owned_program12_observability_contract_stays_machine_executable_and_secret_safe(): void
    {
        $root = dirname(__DIR__, 2);
        $observability = json_decode((string) file_get_contents($root.'/deployment/dovra/program12-observability-contract.json'), true, 512, JSON_THROW_ON_ERROR);
        $queue = json_decode((string) file_get_contents($root.'/deployment/dovra/program12-queue-resilience-contract.json'), true, 512, JSON_THROW_ON_ERROR);
        $fixture = json_decode((string) file_get_contents($root.'/deployment/dovra/program12-operational-contract-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
        $harness = (string) file_get_contents($root.'/scripts/dovra/program12-operational-contract-harness.php');

        self::assertTrue($observability['operational_harness']['machine_executable']);
        self::assertTrue($observability['operational_harness']['deterministic']);
        self::assertSame('incomplete', $observability['shared_runtime_seams']['logging']['status']);
        self::assertSame('incomplete', $observability['shared_runtime_seams']['sentry']['status']);
        self::assertSame('incomplete', $observability['shared_runtime_seams']['opentelemetry']['status']);
        self::assertSame('incomplete', $observability['shared_runtime_seams']['runtime_topology']['status']);
        self::assertSame('incomplete', $queue['shared_runtime_seams']['queue_config']['status']);
        self::assertSame('incomplete', $queue['shared_runtime_seams']['horizon_config']['status']);
        self::assertNotEmpty($observability['redaction']['forbidden_keys']);
        self::assertNotEmpty($fixture['sensitive_values']);
        self::assertStringContainsString('program12Redact', $harness);
        self::assertStringContainsString('program12SimulateQueues', $harness);
    }

    #[Test]
    public function program12_contracts_cannot_silently_upgrade_local_or_shared_seams_into_managed_proof(): void
    {
        $root = dirname(__DIR__, 2);
        $observability = json_decode((string) file_get_contents($root.'/deployment/dovra/program12-observability-contract.json'), true, 512, JSON_THROW_ON_ERROR);
        $queues = json_decode((string) file_get_contents($root.'/deployment/dovra/program12-queue-resilience-contract.json'), true, 512, JSON_THROW_ON_ERROR);
        $recovery = json_decode((string) file_get_contents($root.'/deployment/dovra/program12-recovery-boundaries.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('incomplete', $observability['shared_runtime_seams']['runtime_topology']['status']);
        self::assertSame('incomplete', $queues['shared_runtime_seams']['queue_config']['status']);
        self::assertSame('incomplete', $queues['shared_runtime_seams']['horizon_config']['status']);
        self::assertSame('external', $recovery['managed_aws_proof']['status']);
        self::assertSame('repository-controlled artifact rehearsal', $recovery['local_rehearsal']['scope']);
    }
}
