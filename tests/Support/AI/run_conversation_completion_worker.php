<?php

declare(strict_types=1);

use App\Application\Ai\Conversation\ConversationStore;
use App\Application\Ai\DTOs\AiExecutionResult;
use App\Application\Ai\Registry\AiRuntimeSelection;
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

if (($payload['mode'] ?? '') === 'complete') {
    $held = false;
    DB::listen(static function (QueryExecuted $event) use (&$held, $payload): void {
        $sql = strtolower($event->sql);
        $target = $payload['hold_on'] === 'execution' ? 'from `ai_executions`' : 'from `products`';
        if ($held || ! str_contains($sql, $target) || ! str_contains($sql, 'for update')) {
            return;
        }
        $held = true;
        file_put_contents((string) $payload['locked_path'], 'locked');
        $deadline = microtime(true) + 30;
        while (! is_file((string) $payload['release_path'])) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Timed out holding the completion resource lock.');
            }
            usleep(10_000);
        }
    });
}

file_put_contents((string) $payload['ready_path'], 'ready');
$deadline = microtime(true) + 30;
while (! is_file((string) $payload['go_path'])) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, 'Timed out waiting to start.');
        exit(3);
    }
    usleep(10_000);
}

file_put_contents((string) $payload['attempting_path'], 'attempting');
$startedAt = microtime(true);
try {
    if ($payload['mode'] === 'mutate_execution') {
        DB::table('ai_executions')
            ->where('correlation_id', $payload['correlation_id'])
            ->update(['provider' => 'tampered']);
    } elseif ($payload['mode'] === 'delete_evidence') {
        DB::table('products')->where('id', $payload['resource_id'])->delete();
    } else {
        $selection = new AiRuntimeSelection(...$payload['selection']);
        $result = new AiExecutionResult(...$payload['result']);
        $message = $app->make(ConversationStore::class)->completeAssistantMessage(
            (string) $payload['tenant_id'],
            (string) $payload['conversation_id'],
            (string) $payload['request_message_id'],
            $selection,
            $result,
            ['answer' => 'Atomic completion.'],
            $payload['evidence'],
        );
    }

    echo json_encode([
        'status' => 'completed',
        'message_id' => isset($message) ? $message->id : null,
        'started_at' => $startedAt,
        'completed_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(2);
}
