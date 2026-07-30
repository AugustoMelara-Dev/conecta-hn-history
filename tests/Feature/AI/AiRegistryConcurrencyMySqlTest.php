<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\AI\AiEligibleRegistryFixture;
use Tests\TestCase;

final class AiRegistryConcurrencyMySqlTest extends TestCase
{
    public function test_activate_and_revoke_serialize_in_both_waiter_orders_without_deadlock(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('This proof requires the certified MySQL 8.4 harness.');
        }

        foreach (['activate-first', 'revoke-first'] as $order) {
            $fixture = $this->fixture($order);
            [$first, $second] = $order === 'activate-first'
                ? ['activate', 'revoke']
                : ['revoke', 'activate'];
            $firstWorker = $this->worker($first, $fixture, true);
            $secondWorker = $this->worker($second, $fixture);
            /** @var Process $firstProcess */
            $firstProcess = $firstWorker['process'];
            /** @var Process $secondProcess */
            $secondProcess = $secondWorker['process'];

            $firstProcess->start();
            $this->awaitReady($firstWorker['ready']);
            file_put_contents($firstWorker['go'], 'go');
            $this->awaitReady($firstWorker['locked']);
            $secondProcess->start();
            $this->awaitReady($secondWorker['ready']);
            file_put_contents($secondWorker['go'], 'go');
            usleep(300_000);
            file_put_contents($firstWorker['release'], 'release');
            $firstProcess->wait();
            $secondProcess->wait();
            $firstResult = $this->workerResult($firstProcess);
            $secondResult = $this->workerResult($secondProcess);

            if ($order === 'activate-first') {
                self::assertSame('committed', $firstResult['status'], json_encode($firstResult));
                self::assertSame('committed', $secondResult['status'], json_encode($secondResult));
            } else {
                self::assertSame('committed', $firstResult['status'], json_encode($firstResult));
                self::assertSame('denied', $secondResult['status'], json_encode($secondResult));
                self::assertSame('evaluation_gate', $secondResult['reason']);
            }
            self::assertNotNull(DB::table('ai_release_decisions')
                ->where('id', $fixture['decision_id'])
                ->value('revoked_at'));
            self::assertSame(0, DB::table('ai_agent_activations')
                ->where('tenant_id', $fixture['tenant_id'])
                ->whereNull('effective_until')
                ->count());
            foreach ([$firstWorker, $secondWorker] as $worker) {
                foreach (['ready', 'go', 'locked', 'release'] as $path) {
                    @unlink($worker[$path]);
                }
            }
        }
    }

    /** @return array<string, string> */
    private function fixture(string $suffix): array
    {
        config()->set('activitylog.enabled', true);
        config()->set('security.admin_mfa_required', false);
        $admin = User::factory()->superAdmin()->create([
            'email' => fake()->unique()->safeEmail(),
        ]);
        config()->set('auth.super_admins', [$admin->email]);
        $tenant = Tenant::factory()->create([
            'slug' => 'registry-race-'.$suffix.'-'.Str::lower(Str::random(6)),
        ]);
        config()->set('ai.public.billing_tenant_id', (string) $tenant->id);
        $selection = AiEligibleRegistryFixture::create(
            tenantId: (string) $tenant->id,
            capability: 'private_agent',
            audience: 'private',
            outputSchema: 'private_agent.answer.v1',
        );
        $activationId = (string) DB::table('ai_agent_activations')
            ->where('tenant_id', $tenant->id)
            ->whereNull('effective_until')
            ->value('id');

        return [
            'actor_id' => (string) $admin->id,
            'actor_email' => (string) $admin->email,
            'tenant_id' => (string) $tenant->id,
            'agent_version_id' => $selection->agentVersionId,
            'decision_id' => $selection->releaseDecisionId,
            'activation_id' => $activationId,
            'adapter_key' => $selection->adapterKey,
            'secret_reference' => $selection->secretReference,
        ];
    }

    /** @param array<string, string> $fixture */
    /** @return array{process:Process,ready:string,go:string,locked:string,release:string} */
    private function worker(string $operation, array $fixture, bool $hold = false): array
    {
        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dovra-registry-race-'.Str::uuid();
        $ready = $prefix.'.ready';
        $go = $prefix.'.go';
        $locked = $prefix.'.locked';
        $release = $prefix.'.release';
        $payload = base64_encode(json_encode([
            ...$fixture,
            'operation' => $operation,
            'ready_path' => $ready,
            'go_path' => $go,
            'locked_path' => $locked,
            'release_path' => $release,
            'hold_after_tenant_lock' => $hold,
        ], JSON_THROW_ON_ERROR));

        return [
            'process' => new Process([
                PHP_BINARY,
                base_path('tests/Support/AI/run_registry_race_worker.php'),
                $payload,
            ], base_path(), null, null, 30),
            'ready' => $ready,
            'go' => $go,
            'locked' => $locked,
            'release' => $release,
        ];
    }

    private function awaitReady(string $path): void
    {
        $deadline = microtime(true) + 10;
        while (! is_file($path)) {
            if (microtime(true) >= $deadline) {
                self::fail("Worker did not become ready: {$path}");
            }
            usleep(10_000);
        }
    }

    /** @return array<string, string> */
    private function workerResult(Process $process): array
    {
        self::assertTrue(
            $process->isSuccessful(),
            trim($process->getErrorOutput()."\n".$process->getOutput()),
        );
        $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($result);

        return $result;
    }
}
