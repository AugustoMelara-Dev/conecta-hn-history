<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\Ai\AiExecutionContextFactory;
use App\Application\Ai\Contracts\AiGateway;
use App\Application\Ai\Contracts\AssistantResult;
use App\Application\Ai\Contracts\AssistantRuntime;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\DTOs\AssistantExecutionContext;
use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Privacy\Dovra\DataClassificationPolicy;
use App\Application\Privacy\Dovra\DovraDataIngressGuard;
use App\Domain\Privacy\Dovra\DataEnvelope;
use App\Infrastructure\Logging\SensitiveDataScrubber;
use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use App\Infrastructure\Privacy\Dovra\DovraTenantBusinessContextResolver;
use App\Infrastructure\Privacy\Dovra\LegacySensitivePayloadRedactor;
use Throwable;

final readonly class CanonicalAssistantRuntime implements AssistantRuntime
{
    private const SCHEMA = 'assistant.structured_result.v1';

    public function __construct(
        private AiGateway $gateway,
        private AiRegistryResolver $registry,
        private AiExecutionContextFactory $contexts,
        ?DovraDataIngressGuard $ingress = null,
        ?DovraTenantBusinessContextResolver $businessContext = null,
    ) {
        $this->ingress = $ingress ?? new DovraDataIngressGuard(
            new DataClassificationPolicy(
                new LegacySensitivePayloadRedactor(new SensitiveDataScrubber),
            ),
            new DovraIntegrationSeamRegistry,
        );
        $this->businessContext = $businessContext ?? new DovraTenantBusinessContextResolver;
    }

    private DovraDataIngressGuard $ingress;

    private DovraTenantBusinessContextResolver $businessContext;

    public function execute(string $assistantKey, array $input, AssistantExecutionContext $context): AssistantResult
    {
        try {
            $businessType = $this->businessContext->resolvePolicyBusinessType($context->tenantId);
            if (! is_string($businessType) || trim($businessType) === '') {
                return $this->unavailable('tenant_business_context');
            }

            $decision = $this->ingress->inspect(new DataEnvelope(
                sourceSystem: 'private_ai_prompt',
                payload: ['assistant' => $assistantKey, 'input' => $input],
                tenantId: $context->tenantId,
                actorId: $context->actorId,
                channel: 'ai_assistant_runtime',
                context: ['business_type' => $businessType],
            ));
            if (! $decision->allowed || array_keys($decision->redactedPayload) !== ['assistant', 'input']) {
                return $this->unavailable('ingress_denied');
            }

            $selection = $this->registry->resolve(new AiRegistryQuery(
                tenantId: $context->tenantId,
                audience: 'internal',
                capability: 'assistant_execution',
                channel: 'assistant_'.$assistantKey,
                marketCode: $context->marketCode,
                residencyClass: $context->residencyClass,
                actorId: $context->actorId,
                cohortSubject: $context->correlationId,
            ));
            if (! hash_equals(self::SCHEMA, $selection->outputSchema)) {
                return $this->unavailable('registry_schema_mismatch');
            }

            $executionContext = $this->contexts->create(
                capability: 'assistant_execution',
                rateLimitIdentity: $context->tenantId.'|'.$context->actorId,
                tenantId: $context->tenantId,
                actorId: $context->actorId,
                authorized: $context->authorized,
                entitled: $context->entitled,
                correlationId: $context->correlationId,
            );
            $encoded = json_encode($decision->redactedPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $result = $this->gateway->execute(new AiExecutionRequest(
                context: $executionContext,
                selection: $selection,
                operation: 'assistant.'.$assistantKey,
                userInput: $encoded,
            ));

            return AssistantResult::success($result->data, (float) $result->usage['charged_cost_usd'], $result->latencyMilliseconds, [
                'status' => 'completed',
                'correlation_id' => $result->correlationId,
                'provider' => $result->provider,
                'model' => $result->model,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailable(class_basename($exception));
        }
    }

    private function unavailable(string $reason): AssistantResult
    {
        return AssistantResult::failure('AI assistant is unavailable.', [
            'status' => 'unavailable',
            'reason' => $reason,
        ]);
    }
}
