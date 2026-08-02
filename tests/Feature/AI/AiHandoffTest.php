<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Conversation\AcceptConversationMessage;
use App\Application\Ai\Conversation\ConversationStore;
use App\Application\Ai\Handoff\AcceptAiHandoff;
use App\Application\Ai\Handoff\AiHandoffConflictException;
use App\Application\Ai\Handoff\AiHandoffForbiddenException;
use App\Application\Ai\Handoff\AiHandoffService;
use App\Application\Ai\Handoff\AiHandoffUniqueViolation;
use App\Application\Ai\Handoff\AssignAiHandoff;
use App\Application\Ai\Handoff\CancelAiHandoff;
use App\Application\Ai\Handoff\ExpireAiHandoff;
use App\Application\Ai\Handoff\RequestAiHandoff;
use App\Application\Ai\Handoff\ResolveAiHandoff;
use App\Application\Ai\Security\AiContentCipher;
use App\Application\Ai\Security\EncryptedAiContent;
use App\Domain\Access\Enums\Role;
use App\Domain\Shared\Services\TenantContext;
use App\Models\City;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDOException;
use Tests\Support\AI\AiEligibleRegistryFixture;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class AiHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_is_encrypted_deduplicated_and_records_real_state_events(): void
    {
        config()->set('activitylog.enabled', true);
        [$tenant, $owner, $conversationId, $messageId, $agentVersionId] = $this->fixture();
        $command = new RequestAiHandoff(
            tenantId: (string) $tenant->id,
            conversationId: $conversationId,
            triggeringMessageId: $messageId,
            agentVersionId: $agentVersionId,
            triggerType: 'explicit_user',
            priority: 'normal',
            queueKey: 'merchant-support',
            contextSummary: 'Customer explicitly requested a person.',
        );

        $service = app(AiHandoffService::class);
        $id = $service->request($command);
        $same = $service->request($command);

        self::assertSame($id, $same);
        self::assertSame(1, DB::table('ai_handoffs')->count());
        $row = DB::table('ai_handoffs')->where('id', $id)->firstOrFail();
        self::assertSame('queued', $row->status);
        self::assertSame(2, (int) $row->version);
        self::assertNotSame($command->contextSummary, $row->context_ciphertext);
        self::assertStringNotContainsString($command->contextSummary, json_encode($row, JSON_THROW_ON_ERROR));
        self::assertSame($command->contextSummary, app(AiContentCipher::class)->decrypt(
            (string) $tenant->id,
            $id,
            'handoff_context',
            new EncryptedAiContent(
                (string) $row->context_ciphertext,
                (string) $row->context_hash,
                (string) $row->encryption_key_version,
            ),
        ));
        self::assertSame(2, DB::table('outbox_messages')
            ->where('aggregate_id', $id)
            ->where('type', 'App\\Domain\\Ai\\Events\\AiHandoffChanged')
            ->count());
        $serialized = DB::table('outbox_messages')->where('aggregate_id', $id)->pluck('payload')->implode(' ');
        self::assertStringNotContainsString($command->contextSummary, $serialized);
        self::assertSame(2, DB::table('activity_log')
            ->where('log_name', 'ai-handoff')
            ->where('event', 'ai_handoff_changed')
            ->count());
        self::assertStringNotContainsString(
            $command->contextSummary,
            DB::table('activity_log')->where('log_name', 'ai-handoff')->pluck('properties')->implode(' '),
        );

        $this->expectException(AiHandoffConflictException::class);
        $service->request(new RequestAiHandoff(
            tenantId: (string) $tenant->id,
            conversationId: $conversationId,
            triggeringMessageId: $messageId,
            agentVersionId: $agentVersionId,
            triggerType: 'merchant_rule',
            priority: 'high',
            queueKey: 'risk-specialists',
            contextSummary: 'Requires specialist review.',
        ));
    }

    public function test_assign_accept_resolve_enforces_staff_versions_and_encrypted_resolution(): void
    {
        [$tenant, $owner, $conversationId, $messageId, $agentVersionId] = $this->fixture();
        $assignee = User::factory()->create();
        $tenant->memberships()->create([
            'user_id' => $assignee->id,
            'role' => Role::OPERATOR,
            'status' => 'active',
            'accepted_at' => now(),
        ]);
        $service = app(AiHandoffService::class);
        $id = $service->request(new RequestAiHandoff(
            tenantId: (string) $tenant->id,
            conversationId: $conversationId,
            triggeringMessageId: $messageId,
            agentVersionId: $agentVersionId,
            triggerType: 'low_confidence',
            priority: 'high',
            queueKey: 'merchant-support',
            contextSummary: 'Grounding confidence was insufficient.',
        ));

        $service->assign(new AssignAiHandoff((string) $tenant->id, $id, (string) $owner->id, (string) $assignee->id, 2));
        $service->accept(new AcceptAiHandoff((string) $tenant->id, $id, (string) $assignee->id, 3));
        $service->resolve(new ResolveAiHandoff(
            (string) $tenant->id,
            $id,
            (string) $assignee->id,
            4,
            'Customer received a verified human answer.',
            true,
        ));

        $row = DB::table('ai_handoffs')->where('id', $id)->firstOrFail();
        self::assertSame('resolved', $row->status);
        self::assertSame(5, (int) $row->version);
        self::assertTrue((bool) $row->return_to_agent);
        self::assertNull($row->active_deduplication_key);
        self::assertStringNotContainsString('verified human answer', json_encode($row, JSON_THROW_ON_ERROR));
        self::assertSame('Customer received a verified human answer.', app(AiContentCipher::class)->decrypt(
            (string) $tenant->id,
            $id,
            'handoff_resolution',
            new EncryptedAiContent(
                (string) $row->resolution_ciphertext,
                (string) $row->resolution_hash,
                (string) $row->resolution_key_version,
            ),
        ));

        $this->expectException(AiHandoffConflictException::class);
        $service->resolve(new ResolveAiHandoff(
            (string) $tenant->id,
            $id,
            (string) $assignee->id,
            4,
            'Stale duplicate.',
            false,
        ));
    }

    public function test_cross_tenant_or_unauthorized_staff_assignment_fails_closed(): void
    {
        [$tenant, $owner, $conversationId, $messageId, $agentVersionId] = $this->fixture();
        $service = app(AiHandoffService::class);
        $id = $service->request(new RequestAiHandoff(
            (string) $tenant->id,
            $conversationId,
            $messageId,
            $agentVersionId,
            'safety',
            'urgent',
            'merchant-support',
            'Safety review required.',
        ));
        $outsider = User::factory()->create();

        try {
            $service->assign(new AssignAiHandoff(
                (string) $tenant->id,
                $id,
                (string) $owner->id,
                (string) $outsider->id,
                2,
            ));
            self::fail('An outsider must not be assigned.');
        } catch (AiHandoffForbiddenException) {
            self::assertSame('queued', DB::table('ai_handoffs')->where('id', $id)->value('status'));
        }

        $other = Tenant::factory()->pro()->create(['city_id' => City::factory()->create()->id]);
        app(TenantContext::class)->reset();
        app(TenantContext::class)->setTenant((string) $other->id);
        $this->expectException(AiHandoffForbiddenException::class);
        $service->assign(new AssignAiHandoff(
            (string) $other->id,
            $id,
            (string) $owner->id,
            (string) $outsider->id,
            2,
        ));
    }

    public function test_only_requested_or_queued_handoffs_can_cancel_or_expire_and_release_active_identity(): void
    {
        [$tenant, $owner, $conversationId, $messageId, $agentVersionId] = $this->fixture();
        $service = app(AiHandoffService::class);
        $first = $service->request(new RequestAiHandoff(
            (string) $tenant->id,
            $conversationId,
            $messageId,
            $agentVersionId,
            'merchant_rule',
            'normal',
            'merchant-support',
            'A merchant rule requires review.',
        ));
        $service->cancel(new CancelAiHandoff((string) $tenant->id, $first, (string) $owner->id, 2));
        self::assertSame('cancelled', DB::table('ai_handoffs')->where('id', $first)->value('status'));
        self::assertNull(DB::table('ai_handoffs')->where('id', $first)->value('active_deduplication_key'));

        $second = $service->request(new RequestAiHandoff(
            (string) $tenant->id,
            $conversationId,
            $messageId,
            $agentVersionId,
            'merchant_rule',
            'low',
            'merchant-support',
            'A later rule requires review.',
        ));
        self::assertNotSame($first, $second);
        $service->expire(new ExpireAiHandoff((string) $tenant->id, $second, 2));
        self::assertSame('expired', DB::table('ai_handoffs')->where('id', $second)->value('status'));
        self::assertNull(DB::table('ai_handoffs')->where('id', $second)->value('active_deduplication_key'));

        $this->expectException(AiHandoffConflictException::class);
        $service->cancel(new CancelAiHandoff((string) $tenant->id, $second, (string) $owner->id, 3));
    }

    public function test_staff_mutation_resolves_exact_tenant_despite_a_different_selected_public_city(): void
    {
        [$tenant, $owner, $conversationId, $messageId, $agentVersionId] = $this->fixture();
        $service = app(AiHandoffService::class);
        $handoffId = $service->request(new RequestAiHandoff(
            (string) $tenant->id,
            $conversationId,
            $messageId,
            $agentVersionId,
            'explicit_user',
            'normal',
            'merchant-support',
            'The customer requested a person.',
        ));
        $selectedCity = City::factory()->create();
        $originalRequest = $this->app->make('request');
        $consoleProperty = new \ReflectionProperty($this->app, 'isRunningInConsole');
        $originalConsoleState = $consoleProperty->getValue($this->app);
        $session = $this->app->make('session')->driver();
        $session->start();
        $session->put('user_selected_city_id', $selectedCity->id);
        $request = Request::create('/api/v1/ai/handoffs/'.$handoffId.'/assign', 'POST');
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);
        $consoleProperty->setValue($this->app, false);

        try {
            $service->assign(new AssignAiHandoff(
                (string) $tenant->id,
                $handoffId,
                (string) $owner->id,
                (string) $owner->id,
                2,
            ));

            self::assertSame('assigned', DB::table('ai_handoffs')->where('id', $handoffId)->value('status'));
        } finally {
            $consoleProperty->setValue($this->app, $originalConsoleState);
            $this->app->instance('request', $originalRequest);
        }
    }

    public function test_migration_preserves_historical_statuses_and_nonterminal_duplicate_ownership_across_rollback(): void
    {
        [$tenant, , $conversationId, $messageId, $agentVersionId] = $this->fixture();
        $migration = require database_path('migrations/2026_07_29_000008_expand_ai_handoff_lifecycle.php');
        $migration->down();
        $first = (string) Str::uuid();
        $second = (string) Str::uuid();
        $third = (string) Str::uuid();
        $completed = (string) Str::uuid();
        $requestedAt = now()->subHour();
        foreach ([
            [$first, 'pending', $requestedAt->copy(), null],
            [$second, 'pending', $requestedAt->copy()->addMinute(), null],
            [$third, 'pending', $requestedAt->copy()->addMinutes(2), null],
            [$completed, 'completed', $requestedAt->copy()->addMinutes(3), $requestedAt->copy()->addMinutes(4)],
        ] as [$id, $status, $requested, $completedAt]) {
            DB::table('ai_handoffs')->insert([
                'id' => $id,
                'tenant_id' => $tenant->id,
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'from_type' => 'ai_agent',
                'to_type' => 'human',
                'status' => $status,
                'requested_at' => $requested,
                'completed_at' => $completedAt,
                'created_at' => $requested,
                'updated_at' => $completedAt ?? $requested,
            ]);
        }

        try {
            $migration->up();
            self::assertSame('queued', DB::table('ai_handoffs')->where('id', $first)->value('status'));
            self::assertNotNull(DB::table('ai_handoffs')->where('id', $first)->value('active_deduplication_key'));
            foreach ([$second, $third] as $duplicate) {
                self::assertSame('requested', DB::table('ai_handoffs')->where('id', $duplicate)->value('status'));
                self::assertNull(DB::table('ai_handoffs')->where('id', $duplicate)->value('active_deduplication_key'));
                self::assertSame('pending', DB::table('ai_handoffs')->where('id', $duplicate)->value('legacy_status'));
            }
            self::assertSame('resolved', DB::table('ai_handoffs')->where('id', $completed)->value('status'));
            self::assertSame('completed', DB::table('ai_handoffs')->where('id', $completed)->value('legacy_status'));

            self::assertSame($first, app(AiHandoffService::class)->request(new RequestAiHandoff(
                (string) $tenant->id,
                $conversationId,
                $messageId,
                $agentVersionId,
                'merchant_rule',
                'normal',
                'legacy-support',
                'Legacy ownership remains active.',
            )));

            $newQueued = (string) Str::uuid();
            $newResolved = (string) Str::uuid();
            foreach ([[$newQueued, 'queued'], [$newResolved, 'resolved']] as [$id, $status]) {
                DB::table('ai_handoffs')->insert([
                    'id' => $id,
                    'tenant_id' => $tenant->id,
                    'conversation_id' => $conversationId,
                    'message_id' => $messageId,
                    'from_type' => 'ai_agent',
                    'to_type' => 'human',
                    'status' => $status,
                    'requested_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $migration->down();
            self::assertFalse(Schema::hasColumn('ai_handoffs', 'legacy_status'));
            foreach ([$first, $second, $third] as $historicalPending) {
                self::assertSame('pending', DB::table('ai_handoffs')->where('id', $historicalPending)->value('status'));
            }
            self::assertSame('completed', DB::table('ai_handoffs')->where('id', $completed)->value('status'));
            self::assertSame('pending', DB::table('ai_handoffs')->where('id', $newQueued)->value('status'));
            self::assertSame('completed', DB::table('ai_handoffs')->where('id', $newResolved)->value('status'));

            $migration->up();
            self::assertTrue(Schema::hasColumn('ai_handoffs', 'legacy_status'));
            self::assertSame(0, DB::table('ai_handoffs')->where('status', 'cancelled')->count());
        } finally {
            if (! Schema::hasColumn('ai_handoffs', 'trigger_type')) {
                $migration->up();
            }
        }
    }

    public function test_unique_violation_detection_is_driver_exact_and_the_exact_key_still_deduplicates(): void
    {
        $detector = new AiHandoffUniqueViolation;
        self::assertTrue($detector->matches('pgsql', $this->queryException(['23505', 7, 'duplicate key'])));
        self::assertFalse($detector->matches('pgsql', $this->queryException(['23502', 7, 'not null'])));
        self::assertTrue($detector->matches('mysql', $this->queryException(['23000', 1062, 'duplicate entry'])));
        self::assertFalse($detector->matches('mysql', $this->queryException(['23000', 1452, 'foreign key'])));
        self::assertTrue($detector->matches('sqlite', $this->queryException(['23000', 19, 'UNIQUE constraint failed: ai_handoffs.tenant_id'])));
        self::assertFalse($detector->matches('sqlite', $this->queryException(['23000', 19, 'FOREIGN KEY constraint failed'])));
        self::assertFalse($detector->matches('sqlite', $this->queryException(['23000', 19, 'NOT NULL constraint failed: ai_handoffs.tenant_id'])));

        $notNull = $this->capturedQueryException(static fn (): bool => DB::table('ai_handoffs')->insert([
            'id' => (string) Str::uuid(),
        ]));
        self::assertFalse($detector->matches('sqlite', $notNull));
        $foreignKey = $this->capturedQueryException(static fn (): bool => DB::table('ai_handoffs')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) Str::uuid(),
            'conversation_id' => (string) Str::uuid(),
            'from_type' => 'ai_agent',
            'to_type' => 'human',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        self::assertFalse($detector->matches('sqlite', $foreignKey));

        [$tenant, , $conversationId, $messageId, $agentVersionId] = $this->fixture();
        $service = app(AiHandoffService::class);
        $command = new RequestAiHandoff(
            (string) $tenant->id,
            $conversationId,
            $messageId,
            $agentVersionId,
            'explicit_user',
            'normal',
            'merchant-support',
            'The user requested human follow-up.',
        );
        $handoffId = $service->request($command);
        DB::table('ai_handoffs')->where('id', $handoffId)->update(['status' => 'cancelled']);

        self::assertSame($handoffId, $service->request($command));
        self::assertSame(1, DB::table('ai_handoffs')->count());
    }

    /** @return array{Tenant, User, string, string, string} */
    private function fixture(): array
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create([
            'city_id' => City::factory()->create()->id,
            'subscription_status' => 'active',
        ]);
        $owner->forceFill(['tenant_id' => (string) $tenant->id])->save();
        GlobalMarketFixture::assignCanada($tenant);
        $tenant->memberships()->create([
            'user_id' => $owner->id,
            'role' => Role::OWNER,
            'status' => 'active',
            'accepted_at' => now(),
        ]);
        app(TenantContext::class)->reset();
        app(TenantContext::class)->setTenant((string) $tenant->id);
        $selection = AiEligibleRegistryFixture::create(
            (string) $tenant->id,
            'private_tenant_agent',
            'private',
            'private_agent.evidence_selection.v1',
        );
        $claim = app(ConversationStore::class)->claim(new AcceptConversationMessage(
            tenantId: (string) $tenant->id,
            visibility: 'tenant_private',
            originType: 'web',
            externalThreadReference: null,
            actorId: (string) $owner->id,
            locale: 'en-CA',
            marketCountryCode: 'CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            content: 'Necesito hablar con una persona.',
            contentType: 'text',
            idempotencyKey: 'handoff-'.Str::uuid(),
        ));

        return [$tenant, $owner, $claim->conversationId, $claim->requestMessage->id, $selection->agentVersionId];
    }

    /** @param array{0:string,1:int,2:string} $errorInfo */
    private function queryException(array $errorInfo): QueryException
    {
        $previous = new PDOException($errorInfo[2]);
        $previous->errorInfo = $errorInfo;

        return new QueryException('test', 'insert into ai_handoffs', [], $previous);
    }

    /** @param callable():bool $operation */
    private function capturedQueryException(callable $operation): QueryException
    {
        try {
            $operation();
        } catch (QueryException $exception) {
            return $exception;
        }

        self::fail('Expected a database constraint violation.');
    }
}
