<?php

declare(strict_types=1);

use App\Application\Ai\Conversation\AcceptConversationMessage;
use App\Application\Ai\Conversation\ConversationStore;
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
        if ($held
            || ! str_contains(strtolower($event->sql), 'from `ai_conversations`')
            || ! str_contains(strtolower($event->sql), 'for update')) {
            return;
        }
        $held = true;
        file_put_contents((string) $payload['locked_path'], 'locked');
        $deadline = microtime(true) + 30;
        while (! is_file((string) $payload['release_path'])) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Timed out holding the conversation lock.');
            }
            usleep(10_000);
        }
    });
}

file_put_contents((string) $payload['ready_path'], 'ready');
$deadline = microtime(true) + 30;
while (! is_file((string) $payload['go_path'])) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, 'Timed out waiting for claim release.');
        exit(3);
    }
    usleep(10_000);
}

file_put_contents((string) $payload['attempting_path'], 'attempting');
$startedAt = microtime(true);
try {
    $claim = $app->make(ConversationStore::class)->claim(new AcceptConversationMessage(
        tenantId: (string) $payload['tenant_id'],
        visibility: 'tenant_private',
        originType: 'web',
        externalThreadReference: (string) $payload['external_thread_reference'],
        actorId: 'owner-001',
        locale: 'en-CA',
        marketCountryCode: 'CA',
        currency: 'CAD',
        timezone: 'America/Toronto',
        content: 'Concurrent claim '.$payload['idempotency_key'],
        contentType: 'text',
        idempotencyKey: (string) $payload['idempotency_key'],
    ));
    echo json_encode([
        'conversation_id' => $claim->conversationId,
        'message_id' => $claim->requestMessage->id,
        'sequence' => $claim->requestMessage->sequence,
        'started_at' => $startedAt,
        'completed_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(2);
}
