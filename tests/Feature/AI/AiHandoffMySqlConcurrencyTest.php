<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Conversation\AcceptConversationMessage;
use App\Application\Ai\Conversation\ConversationStore;
use App\Domain\Shared\Services\TenantContext;
use App\Models\City;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\AI\AiEligibleRegistryFixture;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class AiHandoffMySqlConcurrencyTest extends TestCase
{
    public function test_concurrent_identical_requests_block_then_reuse_one_active_handoff(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('This proof requires the certified disposable MySQL harness.');
        }

        $tenant = Tenant::factory()->pro()->create(['city_id' => City::factory()->create()->id]);
        GlobalMarketFixture::assignCanada($tenant);
        app(TenantContext::class)->setTenant((string) $tenant->id);
        $selection = AiEligibleRegistryFixture::create(
            (string) $tenant->id,
            'private_tenant_agent',
            'private',
            'private_agent.evidence_selection.v1',
            marketCountryCode: 'CA',
        );
        $claim = app(ConversationStore::class)->claim(new AcceptConversationMessage(
            (string) $tenant->id,
            'tenant_private',
            'web',
            null,
            'mysql-handoff-actor',
            'en-CA',
            'CA',
            'CAD',
            'America/Toronto',
            'I need to talk to a human.',
            'text',
            'mysql-handoff-conversation',
        ));
        app(TenantContext::class)->reset();

        $payload = [
            'tenant_id' => (string) $tenant->id,
            'conversation_id' => $claim->conversationId,
            'message_id' => $claim->requestMessage->id,
            'agent_version_id' => $selection->agentVersionId,
        ];
        $first = $this->worker($payload, true);
        $second = $this->worker($payload, false);

        try {
            $first['process']->start();
            $this->awaitFile($first['ready'], $first['process']);
            file_put_contents($first['go'], 'go');
            $this->awaitFile($first['locked'], $first['process']);
            $second['process']->start();
            $this->awaitFile($second['ready'], $second['process']);
            $blockedSince = microtime(true);
            file_put_contents($second['go'], 'go');
            $this->awaitFile($second['attempting'], $second['process']);
            usleep(600_000);
            self::assertTrue($second['process']->isRunning());
            self::assertSame('', trim($second['process']->getOutput()));
            file_put_contents($first['release'], 'release');
            $first['process']->wait();
            $second['process']->wait();

            $firstResult = $this->workerResult($first['process']);
            $secondResult = $this->workerResult($second['process']);
            self::assertSame($firstResult['handoff_id'], $secondResult['handoff_id']);
            self::assertGreaterThanOrEqual(0.5, (float) $secondResult['completed_at'] - $blockedSince);
            self::assertSame(1, DB::table('ai_handoffs')
                ->where('tenant_id', $tenant->id)
                ->where('conversation_id', $claim->conversationId)
                ->count());
            self::assertSame(2, DB::table('outbox_messages')
                ->where('aggregate_id', $firstResult['handoff_id'])
                ->count());
        } finally {
            $this->cleanup($first);
            $this->cleanup($second);
        }
    }

    /** @param array<string,string> $payload
     * @return array{process:Process,ready:string,go:string,attempting:string,locked:string,release:string}
     */
    private function worker(array $payload, bool $hold): array
    {
        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dovra-handoff-race-'.Str::uuid();
        $paths = [
            'ready' => $prefix.'.ready',
            'go' => $prefix.'.go',
            'attempting' => $prefix.'.attempting',
            'locked' => $prefix.'.locked',
            'release' => $prefix.'.release',
        ];
        $encoded = base64_encode(json_encode([
            ...$payload,
            'hold' => $hold,
            ...array_combine(
                array_map(static fn (string $key): string => "{$key}_path", array_keys($paths)),
                array_values($paths),
            ),
        ], JSON_THROW_ON_ERROR));

        return [
            'process' => new Process([
                PHP_BINARY,
                base_path('tests/Support/AI/run_ai_handoff_request_worker.php'),
                $encoded,
            ], base_path(), null, null, 60),
            ...$paths,
        ];
    }

    private function awaitFile(string $path, Process $process): void
    {
        $deadline = microtime(true) + 30;
        while (! is_file($path)) {
            if (! $process->isRunning()) {
                self::fail(trim($process->getErrorOutput()."\n".$process->getOutput()));
            }
            if (microtime(true) >= $deadline) {
                self::fail("Worker did not signal: {$path}");
            }
            usleep(10_000);
        }
    }

    /** @return array<string,mixed> */
    private function workerResult(Process $process): array
    {
        self::assertTrue($process->isSuccessful(), trim($process->getErrorOutput()."\n".$process->getOutput()));
        $result = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($result);

        return $result;
    }

    /** @param array{process:Process,ready:string,go:string,attempting:string,locked:string,release:string} $worker */
    private function cleanup(array $worker): void
    {
        if ($worker['process']->isRunning()) {
            $worker['process']->stop(1);
        }
        foreach (['ready', 'go', 'attempting', 'locked', 'release'] as $path) {
            @unlink($worker[$path]);
        }
    }
}
