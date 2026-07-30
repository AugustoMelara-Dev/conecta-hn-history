<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Application\Ai\AiExecutionContextFactory;
use App\Application\Ai\Contracts\AiGateway;
use App\Application\Ai\Contracts\AiProviderPool;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\DTOs\AiProviderResult;
use App\Application\Privacy\AccountPrivacyService;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use App\Infrastructure\Tenancy\PostgresRlsPolicyInventory;
use App\Infrastructure\Tenancy\PostgresTenantScope;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Fakes\AI\FakeAiProvider;
use Tests\Support\AI\AiRuntimeSelectionFixture;
use Tests\TestCase;

final class PostgresRlsRuntimeTest extends TestCase
{
    private const TENANT_A = '019d0000-0000-7000-8000-000000000001';

    private const TENANT_B = '019d0000-0000-7000-8000-000000000002';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql' || ! (bool) config('tenancy.postgres_rls.verification_enabled', false)) {
            $this->markTestSkipped('Requires the isolated PostgreSQL RLS verification database and restricted role.');
        }

        config()->set('tenancy.postgres_rls.enabled', true);
        app(TenantContext::class)->reset();
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();
        parent::tearDown();
    }

    public function test_runtime_role_and_required_policy_inventory_cannot_drift(): void
    {
        $role = DB::selectOne(
            'select current_user as role_name, rolsuper, rolbypassrls '
            .'from pg_roles where rolname = current_user'
        );

        self::assertNotNull($role);
        self::assertFalse((bool) $role->rolsuper);
        self::assertFalse((bool) $role->rolbypassrls);

        foreach (PostgresRlsPolicyInventory::requiredTables() as $table) {
            $metadata = DB::selectOne(
                'select c.relrowsecurity, c.relforcerowsecurity, '
                .'exists (select 1 from pg_policies p '
                .'where p.schemaname = current_schema() and p.tablename = c.relname) as has_policy, '
                .'(pg_get_userbyid(c.relowner) = current_user) as runtime_is_owner '
                .'from pg_class c join pg_namespace n on n.oid = c.relnamespace '
                .'where n.nspname = current_schema() and c.relname = ?',
                [$table],
            );

            self::assertNotNull($metadata, "Missing required RLS table [{$table}].");
            self::assertTrue((bool) $metadata->relrowsecurity, "RLS is disabled for [{$table}].");
            self::assertTrue((bool) $metadata->relforcerowsecurity, "RLS is not forced for [{$table}].");
            self::assertTrue((bool) $metadata->has_policy, "RLS policy is missing for [{$table}].");
            self::assertFalse((bool) $metadata->runtime_is_owner, "Runtime role owns [{$table}].");
        }
    }

    public function test_governance_memberships_are_isolated_by_the_restricted_runtime_role(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $membershipA = (string) Str::uuid();
        $membershipB = (string) Str::uuid();

        DB::connection('pgsql_platform')->table('tenant_memberships')->insert([
            [
                'id' => $membershipA,
                'tenant_id' => self::TENANT_A,
                'user_id' => $userA->getKey(),
                'role' => 'member',
                'status' => 'active',
                'accepted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $membershipB,
                'tenant_id' => self::TENANT_B,
                'user_id' => $userB->getKey(),
                'role' => 'member',
                'status' => 'active',
                'accepted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        app(TenantContext::class)->setTenant(self::TENANT_A);
        app(PostgresTenantScope::class)->run(function () use ($membershipB): void {
            self::assertSame(1, DB::table('tenant_memberships')->count());
            self::assertSame(
                0,
                DB::table('tenant_memberships')->where('id', $membershipB)->update(['role' => 'manager']),
            );
            self::assertSame(
                0,
                DB::table('tenant_memberships')->where('id', $membershipB)->delete(),
            );
        });

        try {
            app(PostgresTenantScope::class)->run(function () use ($membershipA): void {
                DB::table('tenant_memberships')
                    ->where('id', $membershipA)
                    ->update(['tenant_id' => self::TENANT_B]);
            });
            self::fail('Membership ownership move unexpectedly succeeded.');
        } catch (QueryException) {
            self::assertTrue(true);
        }

        DB::connection('pgsql_platform')->table('tenant_memberships')
            ->whereIn('id', [$membershipA, $membershipB])
            ->delete();
        $userA->delete();
        $userB->delete();
    }

    public function test_runtime_scope_sees_only_one_tenant_and_clears_context_after_commit(): void
    {
        app(TenantContext::class)->setTenant(self::TENANT_A);

        $count = app(PostgresTenantScope::class)->run(
            fn (): int => DB::table('orders')->count(),
        );

        self::assertSame(1, $count);
        self::assertSame(0, DB::table('orders')->count());
    }

    public function test_cross_tenant_write_is_rejected_and_context_clears_after_rollback(): void
    {
        app(TenantContext::class)->setTenant(self::TENANT_A);

        try {
            app(PostgresTenantScope::class)->run(function (): void {
                DB::table('orders')->insert([
                    'tenant_id' => self::TENANT_B,
                    'order_number' => 'RLS-CROSS-WRITE',
                    'total' => 1,
                ]);
            });

            self::fail('Cross-tenant insert unexpectedly succeeded.');
        } catch (QueryException) {
            self::assertSame(0, DB::table('orders')->count());
        }
    }

    public function test_rollback_and_reused_connection_do_not_retain_tenant_setting(): void
    {
        app(TenantContext::class)->setTenant(self::TENANT_A);

        try {
            app(PostgresTenantScope::class)->run(function (): never {
                self::assertSame(1, DB::table('orders')->count());

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('force rollback', $exception->getMessage());
        }

        self::assertSame(0, DB::table('orders')->count());
    }

    public function test_cross_tenant_update_and_delete_cannot_see_foreign_rows(): void
    {
        app(TenantContext::class)->setTenant(self::TENANT_A);

        app(PostgresTenantScope::class)->run(function (): void {
            self::assertSame(
                0,
                DB::table('orders')
                    ->where('tenant_id', self::TENANT_B)
                    ->update(['total' => 999]),
            );
            self::assertSame(
                0,
                DB::table('orders')
                    ->where('tenant_id', self::TENANT_B)
                    ->delete(),
            );
            self::assertSame(1, DB::table('orders')->count());
        });
    }

    public function test_runtime_cannot_move_its_own_row_to_another_tenant(): void
    {
        app(TenantContext::class)->setTenant(self::TENANT_A);

        $this->expectException(QueryException::class);

        app(PostgresTenantScope::class)->run(function (): void {
            DB::table('orders')
                ->where('tenant_id', self::TENANT_A)
                ->update(['tenant_id' => self::TENANT_B]);
        });
    }

    public function test_program_04_quote_rows_enforce_crud_and_ownership_with_the_restricted_role(): void
    {
        $quoteA = '019d0000-0000-7000-8000-000000000091';
        $quoteB = '019d0000-0000-7000-8000-000000000092';
        $runtimeQuote = '019d0000-0000-7000-8000-000000000093';
        $hostileQuote = '019d0000-0000-7000-8000-000000000094';
        $quoteIds = [$quoteA, $quoteB, $runtimeQuote, $hostileQuote];

        DB::connection('pgsql_platform')->table('quotes')->insert([
            $this->quoteFixture($quoteA, self::TENANT_A, 'rls-quote-a'),
            $this->quoteFixture($quoteB, self::TENANT_B, 'rls-quote-b'),
        ]);

        try {
            app(TenantContext::class)->setTenant(self::TENANT_A);
            app(PostgresTenantScope::class)->run(function () use ($quoteA, $quoteB, $runtimeQuote): void {
                self::assertSame([$quoteA], DB::table('quotes')->pluck('id')->all());
                self::assertSame(
                    0,
                    DB::table('quotes')->where('id', $quoteB)->update(['status' => 'issued']),
                );
                self::assertSame(0, DB::table('quotes')->where('id', $quoteB)->delete());

                DB::table('quotes')->insert(
                    $this->quoteFixture($runtimeQuote, self::TENANT_A, 'rls-quote-runtime'),
                );
                self::assertSame(
                    1,
                    DB::table('quotes')->where('id', $runtimeQuote)->update(['status' => 'issued']),
                );
                self::assertSame(1, DB::table('quotes')->where('id', $runtimeQuote)->delete());
            });

            try {
                app(PostgresTenantScope::class)->run(function () use ($hostileQuote): void {
                    DB::table('quotes')->insert(
                        $this->quoteFixture($hostileQuote, self::TENANT_B, 'rls-quote-hostile'),
                    );
                });
                self::fail('Cross-tenant Program 04 quote insert unexpectedly succeeded.');
            } catch (QueryException) {
                self::assertTrue(true);
            }

            try {
                app(PostgresTenantScope::class)->run(function () use ($quoteA): void {
                    DB::table('quotes')
                        ->where('id', $quoteA)
                        ->update(['tenant_id' => self::TENANT_B]);
                });
                self::fail('Program 04 quote ownership move unexpectedly succeeded.');
            } catch (QueryException) {
                self::assertTrue(true);
            }
        } finally {
            app(TenantContext::class)->reset();
            DB::connection('pgsql_platform')->table('quotes')
                ->whereIn('id', $quoteIds)
                ->delete();
        }
    }

    public function test_pgvector_extension_and_public_private_knowledge_visibility_are_enforced(): void
    {
        self::assertNotNull(DB::selectOne("select extversion from pg_extension where extname = 'vector'"));
        self::assertSame(2, DB::table('ai_knowledge_chunks')->count());

        app(TenantContext::class)->setTenant(self::TENANT_A);

        app(PostgresTenantScope::class)->run(function (): void {
            self::assertSame(3, DB::table('ai_knowledge_chunks')->count());
            self::assertSame(
                0,
                DB::table('ai_knowledge_chunks')
                    ->where('tenant_id', self::TENANT_B)
                    ->where('visibility', 'private')
                    ->count(),
            );
        });
    }

    public function test_public_ai_execution_and_credit_accounting_use_platform_rls_scope(): void
    {
        DB::table('tenants')->where('id', self::TENANT_A)->update([
            'ai_balance' => 5,
            'ai_reserved_balance' => 0,
        ]);
        config()->set([
            'ai.enabled' => true,
            'ai.allowed_environments' => ['testing'],
            'ai.providers.fake.enabled' => true,
            'ai.public.billing_tenant_id' => self::TENANT_A,
            'ai.capabilities.public_discovery.enabled' => true,
            'ai.capabilities.public_discovery.provider' => 'fake',
            'ai.capabilities.public_discovery.models' => ['fake-1'],
            'ai.capabilities.public_discovery.max_cost_usd' => 0.02,
            'ai.capabilities.public_discovery.requests_per_minute' => 100,
        ]);

        $provider = new FakeAiProvider;
        $provider->queueResult(new AiProviderResult(
            data: [
                'answer' => 'Opcion publica verificada.',
                'evidence' => [[
                    'type' => 'product',
                    'id' => 'product:100',
                    'reason' => 'Dato publicado.',
                ]],
            ],
            model: 'fake-1',
            inputTokens: 10,
            outputTokens: 8,
            costUsd: 0.01,
            finishReason: 'stop',
        ));
        $this->app->instance(AiProviderPool::class, new ConfiguredAiProviderPool([
            'fake_structured_text' => ['fake.primary' => $provider],
        ]));
        $this->app->forgetInstance(AiGateway::class);

        $context = app(AiExecutionContextFactory::class)->create(
            capability: 'public_discovery',
            rateLimitIdentity: 'postgres-public-ai-test',
            authorized: true,
            entitled: true,
            allowedTools: ['public_catalog.search'],
        );
        self::assertSame(self::TENANT_A, $context->tenantId);
        self::assertSame(self::TENANT_A, $context->billingTenantId);

        app(TenantContext::class)->setTenant(self::TENANT_A);
        $before = app(PostgresTenantScope::class)->run(fn (): array => [
            'executions' => DB::table('ai_executions')->where('status', 'completed')->count(),
            'reservations' => DB::table('ai_credit_reservations')->where('status', 'settled')->count(),
            'ledger' => DB::table('ai_credit_ledger_entries')->count(),
        ]);
        try {
            $result = app(PostgresTenantScope::class)->run(
                fn () => app(AiGateway::class)->execute(new AiExecutionRequest(
                    context: $context,
                    selection: AiRuntimeSelectionFixture::create(
                        tenantId: self::TENANT_A,
                        systemPrompt: 'Use only supplied public evidence.',
                        outputSchema: 'public_discovery.answer.v1',
                        maxOutputTokens: 100,
                    ),
                    operation: 'public_discovery.answer',
                    userInput: '{"evidence_id":"product:100"}',
                    requestedTools: ['public_catalog.search'],
                )),
            );
        } catch (AiExecutionDeniedException $exception) {
            self::fail('AI policy denied the RLS fixture: '.$exception->reason);
        }
        self::assertSame('fake', $result->provider);

        app(PostgresTenantScope::class)->run(function () use ($before): void {
            self::assertSame($before['executions'] + 1, DB::table('ai_executions')->where('status', 'completed')->count());
            self::assertSame($before['reservations'] + 1, DB::table('ai_credit_reservations')->where('status', 'settled')->count());
            self::assertGreaterThanOrEqual($before['ledger'] + 2, DB::table('ai_credit_ledger_entries')->count());
            self::assertSame(
                0,
                DB::table('ai_executions')->whereNull('tenant_id')->count(),
            );
        });
    }

    public function test_knowledge_index_rejects_cross_tenant_writes(): void
    {
        app(TenantContext::class)->setTenant(self::TENANT_A);
        $this->expectException(QueryException::class);

        app(PostgresTenantScope::class)->run(function (): void {
            DB::table('ai_knowledge_chunks')->insert([
                'id' => '019d0000-0000-7000-8000-000000000099',
                'tenant_id' => self::TENANT_B,
                'resource_type' => 'product',
                'resource_id' => '999',
                'visibility' => 'private',
                'content' => 'cross tenant write',
                'content_hash' => str_repeat('f', 64),
                'embedding' => DB::raw('array_fill(0::real, ARRAY[1536])::vector'),
                'embedding_model' => 'test',
                'metadata' => '{}',
                'indexed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_privacy_export_uses_audited_platform_scope_across_user_tenants(): void
    {
        $user = User::factory()->create(['tenant_id' => self::TENANT_A]);

        foreach ([self::TENANT_A => 'A-1', self::TENANT_B => 'B-1'] as $tenantId => $orderNumber) {
            app(TenantContext::class)->setTenant($tenantId);
            app(PostgresTenantScope::class)->run(function () use ($user, $orderNumber, $tenantId): void {
                DB::table('orders')->where('order_number', $orderNumber)->update(['user_id' => $user->getKey()]);
                DB::table('ai_tool_confirmations')->insert([
                    'id' => $tenantId === self::TENANT_A
                        ? '019d0000-0000-7000-8000-000000000081'
                        : '019d0000-0000-7000-8000-000000000082',
                    'tenant_id' => $tenantId,
                    'actor_id' => $user->getKey(),
                    'tool' => 'tenant_lead.prepare',
                    'payload' => 'encrypted-private-payload',
                    'payload_hash' => str_repeat('e', 64),
                    'confirmation_token' => 'encrypted-private-token',
                    'confirmation_token_hash' => str_repeat('f', 64),
                    'idempotency_key' => 'privacy-export-'.$tenantId,
                    'status' => 'pending',
                    'expires_at' => now()->addMinutes(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
            app(TenantContext::class)->reset();
        }

        $export = app(AccountPrivacyService::class)->export($user);

        self::assertSame(['A-1', 'B-1'], collect($export['orders'])->pluck('order_number')->sort()->values()->all());
        self::assertCount(2, $export['ai_tool_confirmations']);
        foreach ($export['ai_tool_confirmations'] as $confirmation) {
            self::assertArrayNotHasKey('payload', $confirmation);
            self::assertArrayNotHasKey('confirmation_token', $confirmation);
            self::assertArrayNotHasKey('result', $confirmation);
        }

        DB::connection('pgsql_platform')->table('ai_tool_confirmations')
            ->where('actor_id', $user->getKey())
            ->delete();
        DB::connection('pgsql_platform')->table('orders')
            ->where('user_id', $user->getKey())
            ->update(['user_id' => null]);
        $user->delete();
    }

    public function test_tool_confirmation_rows_are_forced_into_the_active_tenant_scope(): void
    {
        $actor = User::factory()->create();
        app(TenantContext::class)->setTenant(self::TENANT_A);

        app(PostgresTenantScope::class)->run(function () use ($actor): void {
            DB::table('ai_tool_confirmations')->insert([
                'id' => '019d0000-0000-7000-8000-000000000088',
                'tenant_id' => self::TENANT_A,
                'actor_id' => $actor->getKey(),
                'tool' => 'tenant_lead.prepare',
                'payload' => 'encrypted-payload',
                'payload_hash' => str_repeat('a', 64),
                'confirmation_token' => 'encrypted-token',
                'confirmation_token_hash' => str_repeat('b', 64),
                'idempotency_key' => 'rls-tool-confirmation',
                'status' => 'pending',
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            self::assertSame(1, DB::table('ai_tool_confirmations')->count());
        });

        try {
            app(PostgresTenantScope::class)->run(function () use ($actor): void {
                DB::table('ai_tool_confirmations')->insert([
                    'id' => '019d0000-0000-7000-8000-000000000089',
                    'tenant_id' => self::TENANT_B,
                    'actor_id' => $actor->getKey(),
                    'tool' => 'tenant_lead.prepare',
                    'payload' => 'encrypted-payload',
                    'payload_hash' => str_repeat('c', 64),
                    'confirmation_token' => 'encrypted-token',
                    'confirmation_token_hash' => str_repeat('d', 64),
                    'idempotency_key' => 'rls-tool-confirmation-hostile',
                    'status' => 'pending',
                    'expires_at' => now()->addMinutes(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
            self::fail('Cross-tenant tool confirmation unexpectedly succeeded.');
        } catch (QueryException) {
            self::assertSame(0, DB::table('ai_tool_confirmations')->count());
        }

        app(PostgresTenantScope::class)->run(function (): void {
            self::assertSame(1, DB::table('ai_tool_confirmations')->count());
            DB::table('ai_tool_confirmations')->delete();
        });
        self::assertSame(0, DB::table('ai_tool_confirmations')->count());
    }

    /** @return array<string, mixed> */
    private function quoteFixture(string $id, string $tenantId, string $idempotencyKey): array
    {
        return [
            'id' => $id,
            'tenant_id' => $tenantId,
            'public_id' => Str::uuid()->toString(),
            'currency' => 'HNL',
            'idempotency_key' => $idempotencyKey,
            'payload_hash' => hash('sha256', $idempotencyKey),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
