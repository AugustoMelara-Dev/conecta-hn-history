<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Interactions\Events\InteractionRecorded;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class RunSmokeTestsCommand extends Command
{
    protected $signature = 'vito:smoke-test
                            {--base-url= : Base URL used for HTTP smoke checks}
                            {--force : Continue even if pre-flight warnings are detected}
                            {--skip-webhook : Skip the webhook concurrency phase}
                            {--skip-outbox : Skip the transactional outbox phase}';

    protected $description = 'Run RC1 smoke tests for onboarding, billing idempotency, and the transactional outbox';

    private array $warnings = [];

    private array $failures = [];

    public function handle(): int
    {
        $this->components->info('Vito Business OS RC1 smoke test');

        $baseUrl = rtrim((string) ($this->option('base-url') ?: config('app.url')), '/');
        $force = (bool) $this->option('force');
        $failed = false;

        if (! $this->runPreflightChecks($baseUrl)) {
            if (! $force) {
                $this->error('Pre-flight checks failed. Re-run with --force if you want to continue anyway.');
                $this->renderSummary();

                return self::FAILURE;
            }

            $this->warn('Continuing because --force was provided.');
        }

        $failed = ! $this->runAtomicProvisioningPhase();

        if ($this->option('skip-webhook')) {
            $this->warn('Skipping webhook concurrency phase.');
        } else {
            $failed = ! $this->runWebhookConcurrencyPhase($baseUrl) || $failed;
        }

        if ($this->option('skip-outbox')) {
            $this->warn('Skipping transactional outbox phase.');
        } else {
            $failed = ! $this->runOutboxPhase() || $failed;
        }

        $this->renderSummary();

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function runPreflightChecks(string $baseUrl): bool
    {
        $this->newLine();
        $this->components->twoColumnDetail('Phase 1', 'Environment pre-flight');

        $checks = [
            [
                'label' => 'Queue default',
                'actual' => (string) config('queue.default'),
                'expected' => 'redis',
            ],
            [
                'label' => 'Broadcast default',
                'actual' => (string) config('broadcasting.default'),
                'expected' => 'reverb',
            ],
            [
                'label' => 'Payment webhook secret',
                'actual' => config('services.payment.webhook_secret'),
                'expected' => 'configured',
            ],
            [
                'label' => 'VAPID public key',
                'actual' => config('webpush.vapid.public_key'),
                'expected' => 'configured',
            ],
        ];

        $ok = true;

        foreach ($checks as $check) {
            $passed = match ($check['expected']) {
                'configured' => filled($check['actual']),
                default => $check['actual'] === $check['expected'],
            };

            if ($passed) {
                $this->line("  [OK] {$check['label']}: {$this->stringify($check['actual'])}");

                continue;
            }

            $ok = false;
            $message = "{$check['label']} expected {$check['expected']} but got {$this->stringify($check['actual'])}.";
            $this->warnings[] = $message;
            $this->warn("  [WARN] {$message}");
        }

        if (! $this->option('skip-webhook') && blank($baseUrl)) {
            $ok = false;
            $message = 'Base URL is empty. Provide --base-url to run the webhook phase.';
            $this->warnings[] = $message;
            $this->warn("  [WARN] {$message}");
        }

        if (! Schema::hasTable('users') || ! Schema::hasTable('tenants')) {
            $ok = false;
            $message = 'users/tenants tables are missing, onboarding smoke test cannot run.';
            $this->warnings[] = $message;
            $this->warn("  [WARN] {$message}");
        }

        if (! $this->option('skip-webhook') && ! Schema::hasTable('subscription_payments')) {
            $ok = false;
            $message = 'subscription_payments table is missing, billing smoke test cannot assert persistence.';
            $this->warnings[] = $message;
            $this->warn("  [WARN] {$message}");
        }

        if (! $this->option('skip-outbox') && (! Schema::hasTable('outbox_messages') || ! Schema::hasTable('outbox_shards'))) {
            $ok = false;
            $message = 'outbox_messages/outbox_shards tables are missing, transactional outbox phase cannot run.';
            $this->warnings[] = $message;
            $this->warn("  [WARN] {$message}");
        }

        return $ok;
    }

    private function runAtomicProvisioningPhase(): bool
    {
        $this->newLine();
        $this->components->twoColumnDetail('Phase 2', 'Atomic onboarding rollback');

        $userId = (string) Str::uuid();
        $tenantId = (string) Str::uuid();
        $email = 'smoke+'.Str::lower(Str::random(12)).'@example.test';
        $slug = 'smoke-'.Str::lower(Str::random(14));
        $now = now();

        try {
            DB::transaction(function () use ($userId, $tenantId, $email, $slug, $now): void {
                DB::table('users')->insert([
                    'id' => $userId,
                    'name' => 'Smoke Test User',
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'role' => 'seller',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $tenantPayload = [
                    'id' => $tenantId,
                    'user_id' => $userId,
                    'name' => 'Smoke Test Tenant',
                    'slug' => $slug,
                    'plan' => 'SEED',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (Schema::hasColumn('tenants', 'subscription_status')) {
                    $tenantPayload['subscription_status'] = 'active';
                }

                if (Schema::hasColumn('tenants', 'plan_type')) {
                    $tenantPayload['plan_type'] = 'semilla';
                }

                if (Schema::hasColumn('tenants', 'subscription_ends_at')) {
                    $tenantPayload['subscription_ends_at'] = null;
                }

                DB::table('tenants')->insert($tenantPayload);

                DB::table('users')
                    ->where('id', $userId)
                    ->update(['tenant_id' => $tenantId]);

                throw new RuntimeException('Simulated failure');
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'Simulated failure') {
                throw $e;
            }
        } catch (Throwable $e) {
            return $this->phaseFailure('Atomic onboarding rollback', $e->getMessage());
        }

        $userExists = DB::table('users')->where('email', $email)->exists();
        $tenantExists = DB::table('tenants')->where('slug', $slug)->exists();

        if ($userExists || $tenantExists) {
            return $this->phaseFailure(
                'Atomic onboarding rollback',
                'Rollback left smoke-test records behind in users or tenants.'
            );
        }

        $this->info('  [PASS] User and tenant were both rolled back cleanly.');

        return true;
    }

    private function runWebhookConcurrencyPhase(string $baseUrl): bool
    {
        $this->newLine();
        $this->components->twoColumnDetail('Phase 3', 'Webhook idempotency');

        $fixture = $this->createCommittedTenantFixture();
        $transactionId = 'smoke_tx_'.Str::lower(Str::random(16));
        $eventId = (string) Str::uuid();
        $endpoint = $baseUrl.'/api/webhooks/payment';

        $payload = [
            'id' => $eventId,
            'event' => 'payment.success',
            'meta' => [
                'tenant_id' => $fixture['tenant_id'],
            ],
            'data' => [
                'transaction_id' => $transactionId,
                'object' => [
                    'id' => $transactionId,
                    'metadata' => [
                        'tenant_id' => $fixture['tenant_id'],
                    ],
                ],
            ],
        ];

        $statusCodes = [];

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            $signature = hash_hmac('sha256', $body, (string) config('services.payment.webhook_secret'));

            $responses = Http::pool(fn (Pool $pool) => [
                $pool->withHeaders(['X-Signature' => $signature])->withBody($body, 'application/json')->post($endpoint),
                $pool->withHeaders(['X-Signature' => $signature])->withBody($body, 'application/json')->post($endpoint),
                $pool->withHeaders(['X-Signature' => $signature])->withBody($body, 'application/json')->post($endpoint),
            ]);

            $statusCodes = array_map(
                static fn ($response) => $response instanceof \Illuminate\Http\Client\Response
                    ? $response->status()
                    : 0,
                $responses
            );

            $count = $this->countSmokePayments($fixture['tenant_id'], $transactionId, $eventId);

            if ($count !== 1) {
                $statusSummary = implode(', ', $statusCodes);

                return $this->phaseFailure(
                    'Webhook idempotency',
                    "Expected exactly 1 payment record after concurrent webhook delivery, found {$count}. HTTP statuses: [{$statusSummary}]"
                );
            }

            $this->info('  [PASS] Concurrent webhook delivery materialized exactly one payment record.');
            $this->line('         HTTP statuses: ['.implode(', ', $statusCodes).']');

            return true;
        } catch (Throwable $e) {
            $statusSummary = empty($statusCodes) ? 'n/a' : implode(', ', $statusCodes);

            return $this->phaseFailure(
                'Webhook idempotency',
                $e->getMessage()." (HTTP statuses: {$statusSummary})"
            );
        } finally {
            $this->deleteSmokePayments($fixture['tenant_id'], $transactionId, $eventId);
            $this->deleteCommittedTenantFixture($fixture);
        }
    }

    private function runOutboxPhase(): bool
    {
        $this->newLine();
        $this->components->twoColumnDetail('Phase 4', 'Transactional outbox');

        $messageId = (string) Str::uuid();
        $aggregateId = (string) Str::uuid();
        $tenantId = (string) Str::uuid();
        $now = now();

        $payload = [
            'data' => [
                'interactionId' => $aggregateId,
                'tenantId' => $tenantId,
                'occurredAt' => $now->toIso8601String(),
            ],
            'meta' => [
                'trace_id' => (string) Str::uuid(),
                'smoke_test' => true,
            ],
        ];

        try {
            $insert = [
                'id' => $messageId,
                'type' => InteractionRecorded::class,
                'aggregate_id' => $aggregateId,
                'partition_key' => 0,
                'sequence_number' => 1,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'occurred_at' => $now,
                'published_at' => null,
                'status' => 'PENDING',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('outbox_messages', 'attempts')) {
                $insert['attempts'] = 0;
            }

            if (Schema::hasColumn('outbox_messages', 'recovery_count')) {
                $insert['recovery_count'] = 0;
            }

            if (Schema::hasColumn('outbox_messages', 'locked_at')) {
                $insert['locked_at'] = null;
            }

            if (Schema::hasColumn('outbox_messages', 'worker_id')) {
                $insert['worker_id'] = null;
            }

            if (Schema::hasColumn('outbox_messages', 'batch_correlation_id')) {
                $insert['batch_correlation_id'] = null;
            }

            if (Schema::hasColumn('outbox_messages', 'quarantined_at')) {
                $insert['quarantined_at'] = null;
            }

            if (Schema::hasColumn('outbox_messages', 'error')) {
                $insert['error'] = null;
            }

            if (Schema::hasColumn('outbox_messages', 'last_error')) {
                $insert['last_error'] = null;
            }

            if (Schema::hasColumn('outbox_messages', 'failed_at')) {
                $insert['failed_at'] = null;
            }

            DB::table('outbox_messages')->insert($insert);

            $exitCode = Artisan::call('outbox:process', [
                '--batch-size' => 1,
                '--max-loops' => 1,
                '--max-shards' => 64,
            ]);

            $status = DB::table('outbox_messages')
                ->where('id', $messageId)
                ->value('status');

            if ($exitCode !== self::SUCCESS || $status !== 'COMPLETED') {
                $output = trim(Artisan::output());

                return $this->phaseFailure(
                    'Transactional outbox',
                    "Expected COMPLETED after worker run, got {$status} (exit code {$exitCode}). {$output}"
                );
            }

            $this->info('  [PASS] Pending outbox message was claimed and completed by the worker.');

            return true;
        } catch (Throwable $e) {
            return $this->phaseFailure('Transactional outbox', $e->getMessage());
        } finally {
            DB::table('outbox_messages')->where('id', $messageId)->delete();
        }
    }

    private function createCommittedTenantFixture(): array
    {
        $userId = (string) Str::uuid();
        $tenantId = (string) Str::uuid();
        $email = 'smoke.billing+'.Str::lower(Str::random(10)).'@example.test';
        $slug = 'smoke-billing-'.Str::lower(Str::random(10));
        $now = now();

        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Smoke Billing User',
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'role' => 'seller',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $tenantPayload = [
            'id' => $tenantId,
            'user_id' => $userId,
            'name' => 'Smoke Billing Tenant',
            'slug' => $slug,
            'plan' => 'SEED',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('tenants', 'subscription_status')) {
            $tenantPayload['subscription_status'] = 'active';
        }

        if (Schema::hasColumn('tenants', 'plan_type')) {
            $tenantPayload['plan_type'] = 'semilla';
        }

        if (Schema::hasColumn('tenants', 'subscription_ends_at')) {
            $tenantPayload['subscription_ends_at'] = null;
        }

        DB::table('tenants')->insert($tenantPayload);

        DB::table('users')
            ->where('id', $userId)
            ->update(['tenant_id' => $tenantId]);

        return [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'email' => $email,
            'slug' => $slug,
        ];
    }

    private function deleteCommittedTenantFixture(array $fixture): void
    {
        DB::table('users')->where('id', $fixture['user_id'])->delete();
        DB::table('tenants')->where('id', $fixture['tenant_id'])->delete();
    }

    private function countSmokePayments(string $tenantId, string $transactionId, string $eventId): int
    {
        return DB::table('subscription_payments')
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($transactionId, $eventId): void {
                $query->where('reference_number', $transactionId)
                    ->orWhere('reference_number', $eventId)
                    ->orWhere('idempotency_key', $transactionId)
                    ->orWhere('idempotency_key', $eventId);
            })
            ->count();
    }

    private function deleteSmokePayments(string $tenantId, string $transactionId, string $eventId): void
    {
        DB::table('subscription_payments')
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($transactionId, $eventId): void {
                $query->where('reference_number', $transactionId)
                    ->orWhere('reference_number', $eventId)
                    ->orWhere('idempotency_key', $transactionId)
                    ->orWhere('idempotency_key', $eventId);
            })
            ->delete();
    }

    private function phaseFailure(string $phase, string $message): bool
    {
        $this->failures[] = "{$phase}: {$message}";
        $this->error("  [FAIL] {$message}");

        return false;
    }

    private function renderSummary(): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Summary', 'RC1 smoke test');

        if ($this->warnings !== []) {
            $this->warn('Warnings:');
            foreach ($this->warnings as $warning) {
                $this->line("  - {$warning}");
            }
        }

        if ($this->failures !== []) {
            $this->error('Failures:');
            foreach ($this->failures as $failure) {
                $this->line("  - {$failure}");
            }

            return;
        }

        $this->info('All executed smoke-test phases passed.');
    }

    private function stringify(mixed $value): string
    {
        if (blank($value)) {
            return '[empty]';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
