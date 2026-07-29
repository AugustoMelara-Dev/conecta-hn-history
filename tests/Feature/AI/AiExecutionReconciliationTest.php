<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\AiExecutionContextFactory;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\DTOs\AiProviderResult;
use App\Infrastructure\AI\AiExecutionStore;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AI\AiRuntimeSelectionFixture;
use Tests\TestCase;

final class AiExecutionReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'ai.commercial_credits.credits_per_provider_usd' => 1.0,
            'ai.commercial_credits.pricing_version' => 'credits-v1-test',
        ]);
    }

    public function test_stale_provider_completed_execution_is_settled_instead_of_refunded(): void
    {
        $tenant = Tenant::factory()->create([
            'ai_balance' => 1.0,
            'ai_reserved_balance' => 0,
        ]);
        config()->set([
            'ai.capabilities.private_tenant_agent.audience' => 'private',
            'ai.capabilities.private_tenant_agent.provider' => 'fake',
        ]);

        $context = (new AiExecutionContextFactory)->create(
            capability: 'private_tenant_agent',
            rateLimitIdentity: (string) $tenant->getKey().'|actor',
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $tenant->user_id,
            authorized: true,
            entitled: true,
            correlationId: (string) Str::uuid(),
        );
        $request = new AiExecutionRequest(
            context: $context,
            selection: AiRuntimeSelectionFixture::create((string) $tenant->getKey()),
            operation: 'private_agent.answer',
            userInput: 'Summarize sales.',
        );
        $store = new AiExecutionStore;
        $claim = $store->claim($request);
        self::assertNull($claim->replay);
        self::assertNotNull($claim->claimToken);

        $reservationId = (new BudgetEnforcer)->reserve(
            (string) $tenant->getKey(),
            0.20,
            $request->operation,
        );
        $store->attachReservation($context->correlationId, $claim->claimToken, $reservationId);
        $store->stageResult(
            $context->correlationId,
            $claim->claimToken,
            new AiProviderResult(
                data: ['answer' => 'Verified.'],
                model: 'fake-1',
                inputTokens: 4,
                outputTokens: 2,
                costUsd: 0.10,
            ),
            ['answer' => 'Verified.'],
            0.10,
            0.10,
            'credits-v1',
            1.0,
            0.01,
            250,
        );
        DB::table('ai_credit_reservations')->where('id', $reservationId)->update([
            'created_at' => now()->utc()->subHours(2),
        ]);
        config()->set([
            'ai.commercial_credits.credits_per_provider_usd' => 999.0,
            'ai.commercial_credits.pricing_version' => 'credits-v2-after-stage',
        ]);

        $this->artisan('ai:reconcile-reservations', ['--older-than' => 60])
            ->expectsOutput('AI reservations reconciled: 1 settled; 0 refunded; 0 skipped.')
            ->assertSuccessful();

        self::assertSame('settled', DB::table('ai_credit_reservations')->where('id', $reservationId)->value('status'));
        $execution = DB::table('ai_executions')->where('correlation_id', $context->correlationId)->first();
        self::assertSame('completed', $execution->status);
        self::assertSame(0.10, (float) $execution->charged_credits);
        self::assertSame('credits-v1', $execution->credit_pricing_version);
        self::assertSame(1.0, (float) $execution->credits_per_provider_usd);
        self::assertSame(0.01, (float) $execution->credit_minimum_charge);
        self::assertSame(0.90, (float) $tenant->refresh()->ai_balance);
        self::assertSame('Verified.', $store->claim($request)->replay?->data['answer']);
    }

    public function test_stale_resource_overrun_is_settled_and_remains_failed(): void
    {
        [$request, $store, $claimToken] = $this->unfundedExecution();
        $tenant = Tenant::query()->findOrFail($request->context->billingTenantId);
        $reservationId = (new BudgetEnforcer)->reserve(
            (string) $tenant->getKey(),
            0.20,
            $request->operation,
        );
        $store->attachReservation($request->context->correlationId, $claimToken, $reservationId);
        $store->stageResourceOverrun(
            $request->context->correlationId,
            $claimToken,
            new AiProviderResult(
                data: ['answer' => 'unsafe'],
                model: 'fake-1',
                inputTokens: 2,
                outputTokens: 900,
                costUsd: 0.30,
            ),
            0.20,
            0.20,
            'credits-v1',
            1.0,
            0.01,
            'provider_output_token_overrun',
            100,
        );
        DB::table('ai_credit_reservations')->where('id', $reservationId)->update([
            'created_at' => now()->utc()->subHours(2),
        ]);
        config()->set([
            'ai.commercial_credits.credits_per_provider_usd' => 999.0,
            'ai.commercial_credits.pricing_version' => 'credits-v2-after-stage',
        ]);

        $this->artisan('ai:reconcile-reservations', ['--older-than' => 60])
            ->expectsOutput('AI reservations reconciled: 1 settled; 0 refunded; 0 skipped.')
            ->assertSuccessful();

        self::assertSame('settled', DB::table('ai_credit_reservations')->value('status'));
        self::assertSame('failed', DB::table('ai_executions')->value('status'));
        self::assertSame('provider_output_token_overrun', DB::table('ai_executions')->value('failure_code'));
        self::assertSame(0.80, (float) $tenant->refresh()->ai_balance);
    }

    public function test_expired_unfunded_claim_can_be_reclaimed_without_duplicate_row(): void
    {
        [$request, $store, $claimToken] = $this->unfundedExecution();
        DB::table('ai_executions')->where('correlation_id', $request->context->correlationId)->update([
            'processing_expires_at' => now()->subMinute(),
        ]);

        $reclaimed = $store->claim($request);
        self::assertNull($reclaimed->replay);
        self::assertNotNull($reclaimed->claimToken);
        self::assertNotSame($claimToken, $reclaimed->claimToken);
        self::assertSame(2, (int) DB::table('ai_executions')->value('claim_version'));
        self::assertSame(1, DB::table('ai_executions')->count());
        self::assertSame('processing', DB::table('ai_executions')->value('status'));
        self::assertTrue(
            now()->lt(Carbon::parse((string) DB::table('ai_executions')->value('processing_expires_at'))),
        );
    }

    public function test_reclaimed_execution_fences_out_the_previous_worker(): void
    {
        [$request, $store, $staleToken] = $this->unfundedExecution();
        DB::table('ai_executions')->where('correlation_id', $request->context->correlationId)->update([
            'processing_expires_at' => now()->subMinute(),
        ]);

        $activeClaim = $store->claim($request);
        self::assertNotNull($activeClaim->claimToken);
        self::assertNotSame($staleToken, $activeClaim->claimToken);

        try {
            DB::transaction(function () use ($request, $store, $staleToken): void {
                $reservationId = (new BudgetEnforcer)->reserve(
                    $request->context->billingTenantId,
                    0.20,
                    $request->operation,
                );
                $store->attachReservation(
                    $request->context->correlationId,
                    $staleToken,
                    $reservationId,
                );
            });
            self::fail('A stale worker must not attach a reservation.');
        } catch (\RuntimeException $exception) {
            self::assertSame('AI execution reservation could not be attached.', $exception->getMessage());
        }

        self::assertSame(0, DB::table('ai_credit_reservations')->count());
        self::assertSame(0.0, (float) Tenant::query()->findOrFail($request->context->billingTenantId)->ai_reserved_balance);

        $reservationId = (new BudgetEnforcer)->reserve(
            $request->context->billingTenantId,
            0.20,
            $request->operation,
        );
        $store->attachReservation(
            $request->context->correlationId,
            (string) $activeClaim->claimToken,
            $reservationId,
        );

        self::assertSame($reservationId, DB::table('ai_executions')->value('reservation_id'));
        self::assertSame(2, (int) DB::table('ai_executions')->value('claim_version'));
    }

    public function test_reconciler_closes_expired_unfunded_claims(): void
    {
        [$request] = $this->unfundedExecution();
        DB::table('ai_executions')->where('correlation_id', $request->context->correlationId)->update([
            'processing_expires_at' => now()->subMinute(),
        ]);

        $this->artisan('ai:reconcile-reservations', ['--older-than' => 60])->assertSuccessful();

        self::assertSame('failed', DB::table('ai_executions')->value('status'));
        self::assertSame('claim_lease_expired', DB::table('ai_executions')->value('failure_code'));
    }

    public function test_expired_terminal_execution_ciphertext_is_pruned_but_ledger_is_retained(): void
    {
        [$request, $store, $claimToken] = $this->unfundedExecution();
        $reservationId = (new BudgetEnforcer)->reserve(
            $request->context->billingTenantId,
            0.20,
            $request->operation,
        );
        $store->attachReservation($request->context->correlationId, $claimToken, $reservationId);
        $store->stageResult(
            $request->context->correlationId,
            $claimToken,
            new AiProviderResult(
                data: ['answer' => 'Sensitive retained payload.'],
                model: 'fake-1',
                inputTokens: 4,
                outputTokens: 2,
                costUsd: 0.10,
            ),
            ['answer' => 'Sensitive retained payload.'],
            0.10,
            0.10,
            'credits-v1',
            1.0,
            0.01,
            250,
        );
        (new BudgetEnforcer)->settle(
            $request->context->billingTenantId,
            0.20,
            0.10,
            $reservationId,
        );
        $store->completeStaged($request->context->correlationId, $claimToken);
        DB::table('ai_executions')->where('correlation_id', $request->context->correlationId)->update([
            'retention_expires_at' => now()->subMinute(),
        ]);

        $this->artisan('ai:prune-executions', ['--limit' => 100])
            ->expectsOutput('Expired AI execution payloads pruned: 1.')
            ->assertSuccessful();

        $execution = DB::table('ai_executions')->first();
        self::assertNotNull($execution);
        self::assertSame('completed', $execution->status);
        self::assertNull($execution->result_ciphertext);
        self::assertNull($execution->result_hash);
        self::assertNotNull($execution->payload_pruned_at);
        self::assertSame(0.10, (float) $execution->charged_cost);
        self::assertSame(1, DB::table('ai_credit_reservations')->count());
    }

    /** @return array{AiExecutionRequest, AiExecutionStore, string} */
    private function unfundedExecution(): array
    {
        $tenant = Tenant::factory()->create([
            'ai_balance' => 1.0,
            'ai_reserved_balance' => 0,
        ]);
        config()->set([
            'ai.capabilities.private_tenant_agent.audience' => 'private',
            'ai.capabilities.private_tenant_agent.provider' => 'fake',
        ]);
        $context = (new AiExecutionContextFactory)->create(
            capability: 'private_tenant_agent',
            rateLimitIdentity: (string) $tenant->getKey().'|actor',
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $tenant->user_id,
            authorized: true,
            entitled: true,
        );
        $request = new AiExecutionRequest(
            context: $context,
            selection: AiRuntimeSelectionFixture::create((string) $tenant->getKey()),
            operation: 'private_agent.answer',
            userInput: 'Summarize sales.',
        );
        $store = new AiExecutionStore;
        $claim = $store->claim($request);
        self::assertNull($claim->replay);
        self::assertNotNull($claim->claimToken);

        return [$request, $store, $claim->claimToken];
    }
}
