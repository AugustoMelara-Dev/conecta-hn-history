<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

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
        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            throw new AiExecutionDeniedException('billing_tenant');
        }
        $market = $this->markets->resolve($tenant);
        $command = $input->toAcceptedMessage($tenantId, $market);
        $claim = $this->store->claim($command);
        if ($claim->replayed) {
            return $this->replay($claim);
        }

        try {
            $selection = $this->registry->resolve($input->toRegistryQuery($tenantId, $market));
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
            );
        }
    }

    public function private(PrivateConversationInput $input): ConversationResponse
    {
        $tenant = Tenant::query()->findOrFail($input->tenantId);
        $market = $this->markets->resolve($tenant);
        $command = $input->toAcceptedMessage($market);
        $claim = $this->store->claim($command);
        if ($claim->replayed) {
            return $this->replay($claim);
        }

        try {
            $selection = $this->registry->resolve($input->toRegistryQuery($market));
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
            return $this->failed(
                tenantId: $tenantId,
                command: $command,
                claim: $claim,
                failure: [
                    'code' => $answer->failureCode
                        ?? ($answer->handoffTrigger === null
                            ? 'ai_evidence_insufficient'
                            : 'ai_handoff_required'),
                    'retryable' => false,
                ],
                fallback: $answer->payload,
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
    ): ConversationResponse {
        $this->store->failAssistantMessage(
            tenantId: $tenantId,
            conversationId: $claim->conversationId,
            requestMessageId: $claim->requestMessage->id,
            publicCode: $failure['code'],
            retryable: $failure['retryable'],
            fallback: $fallback,
        );

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
}
