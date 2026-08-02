<?php

declare(strict_types=1);

it('documents the fail closed production guard inputs and external dependencies exactly', function (): void {
    $evidence = (string) file_get_contents(base_path('docs/dovra/evidence/program-12-2026-08-01.md'));
    $script = (string) file_get_contents(base_path('scripts/dovra/verify-program12-slo-contracts.ps1'));

    expect($script)
        ->toContain('config/observability.php')
        ->toContain('config/sentry.php')
        ->toContain('config/logging.php')
        ->toContain('config/queue.php')
        ->toContain('config/horizon.php')
        ->toContain('OTEL_PHP_AUTOLOAD_ENABLED')
        ->toContain('send_default_pii')
        ->toContain('shared config seam')
        ->toContain('managed AWS/PITR/RPO/RTO')
        ->and($evidence)
        ->toContain('proved')
        ->toContain('incomplete')
        ->toContain('external')
        ->toContain('AWS')
        ->toContain('DNS')
        ->toContain('secrets')
        ->toContain('managed restore')
        ->toContain('deployment smoke');
});
