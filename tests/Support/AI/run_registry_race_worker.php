<?php

declare(strict_types=1);

use App\Application\Ai\Contracts\AiProviderPool;
use App\Application\Ai\Registry\AiRegistryLifecycle;
use App\Application\Ai\Registry\Commands\ActivateAgentVersionCommand;
use App\Application\Ai\Registry\Commands\RevokeAiReleaseDecisionCommand;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\AI\FakeAiProvider;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$payload = json_decode(
    (string) base64_decode((string) ($argv[1] ?? ''), true),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
config()->set('auth.super_admins', [(string) $payload['actor_email']]);
config()->set('ai.public.billing_tenant_id', (string) $payload['tenant_id']);
config()->set('activitylog.enabled', true);
$app->instance(AiProviderPool::class, new ConfiguredAiProviderPool([
    (string) $payload['adapter_key'] => [
        (string) $payload['secret_reference'] => new FakeAiProvider,
    ],
]));
if (($payload['hold_after_tenant_lock'] ?? false) === true) {
    $held = false;
    DB::listen(static function (QueryExecuted $event) use (&$held, $payload): void {
        if ($held
            || ! str_contains(strtolower($event->sql), 'from `tenants`')
            || ! str_contains(strtolower($event->sql), 'for update')) {
            return;
        }
        $held = true;
        file_put_contents((string) $payload['locked_path'], 'locked');
        $deadline = microtime(true) + 30;
        while (! is_file((string) $payload['release_path'])) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Timed out holding the tenant lock.');
            }
            usleep(10_000);
        }
    });
}
file_put_contents((string) $payload['ready_path'], 'ready');
$deadline = microtime(true) + 30;
while (! is_file((string) $payload['go_path'])) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, 'Timed out waiting for the race release signal.');
        exit(3);
    }
    usleep(10_000);
}

$lifecycle = $app->make(AiRegistryLifecycle::class);
$attemptedAt = microtime(true);
file_put_contents((string) $payload['attempting_lock_path'], 'attempting_lock');
try {
    if (($payload['operation'] ?? null) === 'activate') {
        $id = $lifecycle->activate(new ActivateAgentVersionCommand(
            actorId: (string) $payload['actor_id'],
            tenantId: (string) $payload['tenant_id'],
            scopeType: 'tenant',
            scopeKey: (string) $payload['tenant_id'],
            agentVersionId: (string) $payload['agent_version_id'],
            releaseDecisionId: (string) $payload['decision_id'],
            rolloutPercentage: 100,
            reason: 'MySQL activate/revoke serialization proof.',
            expectedActivationId: (string) $payload['activation_id'],
            expectedLockVersion: 0,
        ));
        echo json_encode([
            'status' => 'committed',
            'activation_id' => $id,
            'attempted_at' => $attemptedAt,
            'completed_at' => microtime(true),
        ], JSON_THROW_ON_ERROR);
    } else {
        $lifecycle->revokeReleaseDecision(new RevokeAiReleaseDecisionCommand(
            actorId: (string) $payload['actor_id'],
            tenantId: (string) $payload['tenant_id'],
            decisionId: (string) $payload['decision_id'],
            reason: 'MySQL activate/revoke serialization proof.',
        ));
        echo json_encode([
            'status' => 'committed',
            'attempted_at' => $attemptedAt,
            'completed_at' => microtime(true),
        ], JSON_THROW_ON_ERROR);
    }
} catch (AiExecutionDeniedException $exception) {
    echo json_encode([
        'status' => 'denied',
        'reason' => $exception->reason,
        'attempted_at' => $attemptedAt,
        'completed_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(2);
}
