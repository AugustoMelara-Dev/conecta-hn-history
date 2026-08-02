<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ObservabilityRatchetTest extends TestCase
{
    #[Test]
    public function shared_observability_defaults_stay_fail_closed_and_secret_safe(): void
    {
        $sentry = require dirname(__DIR__, 2).'/config/sentry.php';
        $observability = require dirname(__DIR__, 2).'/config/observability.php';
        $logging = (string) file_get_contents(dirname(__DIR__, 2).'/config/logging.php');

        self::assertFalse($sentry['send_default_pii']);
        self::assertFalse($sentry['breadcrumbs']['sql_bindings']);
        self::assertFalse($sentry['tracing']['sql_bindings']);
        self::assertFalse($sentry['tracing']['redis_commands']);
        self::assertFalse($sentry['tracing']['gen_ai']);
        self::assertFalse($observability['opentelemetry']['enabled']);
        self::assertStringContainsString("'security' => [", $logging);
        self::assertStringContainsString("'security_file' => [", $logging);
        self::assertStringContainsString("'security_slack' => [", $logging);
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
