<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Conversation\AcceptConversationMessage;
use App\Application\Ai\Conversation\ConversationStore;
use App\Application\Ai\Handoff\AcceptAiHandoff;
use App\Application\Ai\Handoff\AiHandoffConflictException;
use App\Application\Ai\Handoff\AiHandoffForbiddenException;
use App\Application\Ai\Handoff\AiHandoffService;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
}
