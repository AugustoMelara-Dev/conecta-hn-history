<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

use App\Application\Ai\Handoff\AiHandoffContinuationBlock;
use App\Application\Ai\Handoff\AiHandoffService;
use App\Application\Ai\Handoff\RequestAiHandoff;
use App\Application\Ai\PrivateGroundedAgent;
use App\Application\Ai\PublicGroundedDiscovery;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Application\Markets\TenantMarketResolver;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\Ai\AiCircuitOpenException;
use App\Exceptions\Ai\AiConversationConflictException;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Exceptions\Ai\AiExecutionFailedException;
use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderResourceLimitException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Exceptions\InsufficientAiCreditsException;
use App\Infrastructure\Tenancy\PostgresTenantScope;
use App\Models\Tenant;
use App\Scopes\LocationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

final readonly class ConversationRuntime
{
    public function __construct(
        private ConversationStore $store,
        private AiRegistryResolver $registry,
        private TenantMarketResolver $markets,
        private PublicGroundedDiscovery $publicEngine,
        private PrivateGroundedAgent $privateEngine,
        private ConversationFailureMapper $failures,
        private TenantContext $tenantContext,
        private PostgresTenantScope $postgresTenantScope,
        private AiHandoffService $handoffs,
    ) {}

    public function public(PublicConversationInput $input): ConversationResponse
    {
        $tenantId = strtolower(trim((string) config('ai.public.billing_tenant_id', '')));
        if (! Str::isUuid($tenantId)) {
            throw new AiExecutionDeniedException('billing_tenant');
        }

        if ($this->tenantContext->isInitialized()) {
            return $this->runPublic($input, $tenantId);
        }

        $this->tenantContext->setTenant($tenantId);

        try {
            return $this->postgresTenantScope->run(
                fn (): ConversationResponse => $this->runPublic($input, $tenantId),
            );
        } finally {
            $this->tenantContext->reset();
        }
    }

    private function runPublic(PublicConversationInput $input, string $tenantId): ConversationResponse
    {
        // INFRA_TENANT_FILTER: a public city preference cannot override the configured billing tenant.
        $tenant = Tenant::query()
            ->withoutGlobalScope(LocationScope::class)
            ->whereKey($tenantId)
            ->first();
        if ($tenant === null) {
            throw new AiExecutionDeniedException('billing_tenant');
        }
        $market = $this->markets->resolve($tenant);
        $command = $input->toAcceptedMessage($tenantId, $market);
        $claim = $this->store->claim($command);
        if ($claim->replayed) {
            return $this->replay($claim);
        }
        if (($block = $this->handoffs->continuationBlock($tenantId, $claim->conversationId)) !== null) {
            return $this->blockedByHandoff($tenantId, $command, $claim, $block);
        }

        $selection = null;
        try {
            $selection = $this->registry->resolve($input->toRegistryQuery($tenantId, $market));
            if ($this->isExplicitHandoffRequest($input->message)) {
                return $this->failed(
                    tenantId: $tenantId,
                    command: $command,
                    claim: $claim,
                    failure: ['code' => 'ai_handoff_required', 'retryable' => false],
                    fallback: $this->publicEngine->fallback($input),
                    handoffTrigger: 'explicit_user',
                    agentVersionId: $selection->agentVersionId,
                );
            }
            $answer = $this->publicEngine->answer($input, $selection, $claim);

            return $this->completeOrFail(
                tenantId: $tenantId,
                command: $command,
                claim: $claim,
                selection: $selection,
                answer: $answer,
            );
        } catch (AiConversationConflictException $exception) {
            throw $exception;
        } catch (
            AiCircuitOpenException
            |AiExecutionDeniedException
            |AiExecutionFailedException
            |AiMalformedResponseException
            |AiProviderResourceLimitException
            |AiProviderUnavailableException
            |InsufficientAiCreditsException
            |JsonException $exception
        ) {
            return $this->failed(
                tenantId: $tenantId,
                command: $command,
                claim: $claim,
                failure: $this->failures->map($exception),
                fallback: $this->publicEngine->fallback($input),
                handoffTrigger: $selection === null ? null : $this->exceptionHandoffTrigger($exception),
                agentVersionId: $selection?->agentVersionId,
            );
        }
    }

    public function private(PrivateConversationInput $input): ConversationResponse
    {
        // INFRA_TENANT_FILTER: private authority is exact TenantContext identity, not public city preference.
        $tenant = Tenant::query()
            ->withoutGlobalScope(LocationScope::class)
            ->whereKey($input->tenantId)
            ->firstOrFail();
        $market = $this->markets->resolve($tenant);
        $command = $input->toAcceptedMessage($market);
        $claim = $this->store->claim($command);
        if ($claim->replayed) {
            return $this->replay($claim);
        }
        if (($block = $this->handoffs->continuationBlock($input->tenantId, $claim->conversationId)) !== null) {
            return $this->blockedByHandoff($input->tenantId, $command, $claim, $block);
        }

        $selection = null;
        try {
            $selection = $this->registry->resolve($input->toRegistryQuery($market));
            if ($this->isExplicitHandoffRequest($input->message)) {
                return $this->failed(
                    tenantId: $input->tenantId,
                    command: $command,
                    claim: $claim,
                    failure: ['code' => 'ai_handoff_required', 'retryable' => false],
                    fallback: $this->privateEngine->fallback($input),
                    handoffTrigger: 'explicit_user',
                    agentVersionId: $selection->agentVersionId,
                );
            }
            $answer = $this->privateEngine->answer($input, $selection, $claim);

            return $this->completeOrFail(
                tenantId: $input->tenantId,
                command: $command,
                claim: $claim,
                selection: $selection,
                answer: $answer,
            );
        } catch (AiConversationConflictException $exception) {
            throw $exception;
        } catch (
            AiCircuitOpenException
            |AiExecutionDeniedException
            |AiExecutionFailedException
            |AiMalformedResponseException
            |AiProviderResourceLimitException
            |AiProviderUnavailableException
            |InsufficientAiCreditsException
            |JsonException $exception
        ) {
            return $this->failed(
                tenantId: $input->tenantId,
                command: $command,
                claim: $claim,
                failure: $this->failures->map($exception),
                fallback: $this->privateEngine->fallback($input),
                handoffTrigger: $selection === null ? null : $this->exceptionHandoffTrigger($exception),
                agentVersionId: $selection?->agentVersionId,
            );
        }
    }

    private function completeOrFail(
        string $tenantId,
        AcceptConversationMessage $command,
        ConversationMessageClaim $claim,
        AiRuntimeSelection $selection,
        ConversationEngineResult $answer,
    ): ConversationResponse {
        if ($answer->fallback || $answer->execution === null) {
            $failureCode = $answer->failureCode
                ?? ($answer->handoffTrigger === null
                    ? 'ai_evidence_insufficient'
                    : 'ai_handoff_required');

            return $this->failed(
                tenantId: $tenantId,
                command: $command,
                claim: $claim,
                failure: [
                    'code' => $failureCode,
                    'retryable' => false,
                ],
                fallback: $answer->payload,
                handoffTrigger: $answer->handoffTrigger
                    ?? (in_array($failureCode, ['ai_evidence_insufficient', 'ai_response_invalid'], true)
                        ? 'low_confidence'
                        : null),
                agentVersionId: $selection->agentVersionId,
            );
        }

        $message = $this->store->completeAssistantMessage(
            tenantId: $tenantId,
            conversationId: $claim->conversationId,
            requestMessageId: $claim->requestMessage->id,
            selection: $selection,
            result: $answer->execution,
            answer: $answer->payload,
            evidence: $answer->evidence,
        );

        return $this->response(
            claim: $claim,
            message: $message,
            status: 'completed',
            code: null,
        );
    }

    /**
     * @param  array{code:string,retryable:bool}  $failure
     * @param  array<string, mixed>  $fallback
     */
    private function failed(
        string $tenantId,
        AcceptConversationMessage $command,
        ConversationMessageClaim $claim,
        array $failure,
        array $fallback,
        ?string $handoffTrigger = null,
        ?string $agentVersionId = null,
    ): ConversationResponse {
        DB::transaction(function () use (
            $tenantId,
            $claim,
            $failure,
            $fallback,
            $handoffTrigger,
            $agentVersionId,
        ): void {
            if ($handoffTrigger !== null && $agentVersionId !== null) {
                $handoffId = $this->handoffs->request(new RequestAiHandoff(
                    tenantId: $tenantId,
                    conversationId: $claim->conversationId,
                    triggeringMessageId: $claim->requestMessage->id,
                    agentVersionId: $agentVersionId,
                    triggerType: $handoffTrigger,
                    priority: in_array($handoffTrigger, ['policy', 'safety'], true) ? 'urgent' : 'normal',
                    queueKey: 'merchant-support',
                    contextSummary: $this->handoffSummary($handoffTrigger),
                ));
                $fallback['handoff'] = [
                    'id' => $handoffId,
                    'status' => 'queued',
                    'message' => 'A human follow-up has been requested.',
                ];
            }

            $this->store->failAssistantMessage(
                tenantId: $tenantId,
                conversationId: $claim->conversationId,
                requestMessageId: $claim->requestMessage->id,
                publicCode: $failure['code'],
                retryable: $failure['retryable'],
                fallback: $fallback,
            );
        }, 3);

        $stored = $this->store->claim($command);
        if ($stored->response === null) {
            throw new AiExecutionDeniedException('conversation_failure_persistence');
        }

        return $this->response(
            claim: $stored,
            message: $stored->response,
            status: 'failed',
            code: $failure['code'],
        );
    }

    private function replay(ConversationMessageClaim $claim): ConversationResponse
    {
        if ($claim->response === null) {
            return new ConversationResponse(
                conversationId: $claim->conversationId,
                requestMessageId: $claim->requestMessage->id,
                responseMessageId: null,
                status: 'processing',
                payload: [],
                code: null,
            );
        }

        return $this->response(
            claim: $claim,
            message: $claim->response,
            status: $claim->response->status,
            code: $claim->response->status === 'failed'
                ? $this->failureCode($claim->response)
                : null,
        );
    }

    private function blockedByHandoff(
        string $tenantId,
        AcceptConversationMessage $command,
        ConversationMessageClaim $claim,
        AiHandoffContinuationBlock $block,
    ): ConversationResponse {
        DB::transaction(function () use ($tenantId, $claim, $block): void {
            $this->store->failAssistantMessage(
                tenantId: $tenantId,
                conversationId: $claim->conversationId,
                requestMessageId: $claim->requestMessage->id,
                publicCode: 'ai_handoff_required',
                retryable: false,
                fallback: [
                    'answer' => AiHandoffContinuationBlock::MESSAGE,
                    'fallback' => true,
                    'handoff' => $block->response(),
                ],
            );
        }, 3);

        $stored = $this->store->claim($command);
        if ($stored->response === null) {
            throw new AiExecutionDeniedException('conversation_failure_persistence');
        }

        return $this->response(
            claim: $stored,
            message: $stored->response,
            status: 'failed',
            code: 'ai_handoff_required',
        );
    }

    private function response(
        ConversationMessageClaim $claim,
        ConversationMessage $message,
        string $status,
        ?string $code,
    ): ConversationResponse {
        $payload = is_array($message->content) ? $message->content : [];
        $evidence = is_array($payload['evidence'] ?? null)
            ? array_values(array_filter($payload['evidence'], 'is_array'))
            : [];
        $handoff = is_array($payload['handoff'] ?? null) ? $payload['handoff'] : null;

        return new ConversationResponse(
            conversationId: $claim->conversationId,
            requestMessageId: $claim->requestMessage->id,
            responseMessageId: $message->id,
            status: $status,
            payload: $payload,
            evidence: $evidence,
            handoff: $handoff,
            code: $code,
        );
    }

    private function failureCode(ConversationMessage $message): string
    {
        $code = DB::table('ai_message_failures')
            ->where('tenant_id', $message->tenantId)
            ->where('message_id', $message->id)
            ->value('failure_code');

        return is_string($code) && $code !== '' ? $code : 'ai_temporarily_unavailable';
    }

    private function isExplicitHandoffRequest(string $message): bool
    {
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower(trim($message)));
        if (! is_string($normalized)) {
            return false;
        }

        return preg_match(
            '/\b(?:quiero|necesito|deseo|puedo|podria|podría)\b.{0,40}\b(?:hablar|comunicarme|contactar)\b.{0,30}\b(?:persona|humano|humana|asesor|asesora|agente|soporte)\b/u',
            $normalized,
        ) === 1 || preg_match(
            '/\b(?:i want|i need|can i|could i|please)\b.{0,40}\b(?:talk|speak|contact|reach)\b.{0,30}\b(?:human|person|agent|representative|support)\b/u',
            $normalized,
        ) === 1;
    }

    private function exceptionHandoffTrigger(\Throwable $exception): ?string
    {
        if ($exception instanceof AiCircuitOpenException
            || $exception instanceof AiExecutionFailedException
            || $exception instanceof AiProviderResourceLimitException
            || $exception instanceof AiProviderUnavailableException) {
            return 'provider_failure';
        }
        if ($exception instanceof AiMalformedResponseException || $exception instanceof JsonException) {
            return 'low_confidence';
        }
        if ($exception instanceof AiExecutionDeniedException) {
            $reason = strtolower($exception->reason);
            if (str_contains($reason, 'safety')
                || str_contains($reason, 'unsafe')
                || str_contains($reason, 'moderation')) {
                return 'safety';
            }
            if (str_contains($reason, 'policy')
                || str_contains($reason, 'guardrail')
                || in_array($reason, [
                    'global_kill_switch', 'capability_disabled', 'capability_audience',
                    'authorization', 'entitlement', 'private_kill_switch',
                    'tenant_allowlist', 'tool_not_allowed',
                    'tool_requires_explicit_confirmation', 'tool_risk_unknown',
                ], true)) {
                return 'policy';
            }
            if (str_contains($reason, 'merchant_rule')) {
                return 'merchant_rule';
            }
        }

        return null;
    }

    private function handoffSummary(string $trigger): string
    {
        return match ($trigger) {
            'explicit_user' => 'The user explicitly requested human follow-up.',
            'low_confidence' => 'The answer could not be grounded with sufficient confidence.',
            'policy' => 'A policy decision requires human review.',
            'safety' => 'A safety decision requires human review.',
            'provider_failure' => 'Eligible AI providers could not complete the request.',
            'merchant_rule' => 'A merchant rule requires human review.',
            default => 'Human review was requested.',
        };
    }
}
