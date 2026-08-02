<?php

declare(strict_types=1);

use App\Application\Ai\Handoff\AiHandoffConflictException;
use App\Application\Ai\Handoff\AiHandoffService;
use App\Application\Ai\Handoff\RequestAiHandoff;
use App\Domain\Shared\Services\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$payload = json_decode(
    (string) base64_decode((string) ($argv[1] ?? ''), true),
    true,
    32,
    JSON_THROW_ON_ERROR,
);
$app->make(TenantContext::class)->setTenant((string) $payload['tenant_id']);

if (($payload['hold'] ?? false) === true) {
    $held = false;
    DB::listen(static function (QueryExecuted $event) use (&$held, $payload): void {
        $sql = strtolower($event->sql);
        if ($held || ! str_contains($sql, 'from `ai_conversations`') || ! str_contains($sql, 'for update')) {
            return;
        }
        $held = true;
        file_put_contents((string) $payload['locked_path'], 'locked');
        $deadline = microtime(true) + 30;
        while (! is_file((string) $payload['release_path'])) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Timed out holding the AI handoff conversation lock.');
            }
            usleep(10_000);
        }
    });
}

file_put_contents((string) $payload['ready_path'], 'ready');
$deadline = microtime(true) + 30;
while (! is_file((string) $payload['go_path'])) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, 'Timed out waiting to request AI handoff.');
        exit(3);
    }
    usleep(10_000);
}

file_put_contents((string) $payload['attempting_path'], 'attempting');
$startedAt = microtime(true);
try {
    $id = $app->make(AiHandoffService::class)->request(new RequestAiHandoff(
        (string) $payload['tenant_id'],
        (string) $payload['conversation_id'],
        (string) $payload['message_id'],
        (string) $payload['agent_version_id'],
        'explicit_user',
        'normal',
        (string) ($payload['queue_key'] ?? 'merchant-support'),
        'The user explicitly requested human follow-up.',
    ));
    echo json_encode([
        'handoff_id' => $id,
        'started_at' => $startedAt,
        'completed_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);
} catch (AiHandoffConflictException) {
    echo json_encode([
        'status' => 'conflict',
        'started_at' => $startedAt,
        'completed_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(2);
}
