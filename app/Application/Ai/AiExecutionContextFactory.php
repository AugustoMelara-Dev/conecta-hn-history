<?php

declare(strict_types=1);

namespace App\Application\Ai;

use App\Application\Ai\DTOs\AiExecutionContext;
use App\Application\Ai\Workforce\WorkforceAgentContext;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Support\Str;

final class AiExecutionContextFactory
{
    /**
     * @param  list<string>  $allowedTools
     */
    public function create(
        string $capability,
        string $rateLimitIdentity,
        ?string $tenantId = null,
        int|string|null $actorId = null,
        bool $authorized = false,
        bool $entitled = false,
        array $allowedTools = [],
        ?string $correlationId = null,
        ?string $conversationId = null,
        ?string $requestMessageId = null,
        ?WorkforceAgentContext $workforceContext = null,
    ): AiExecutionContext {
        if (trim($rateLimitIdentity) === '') {
            throw new AiExecutionDeniedException('rate_limit_identity');
        }

        $definition = config("ai.capabilities.{$capability}");

        if (! is_array($definition)) {
            throw new AiExecutionDeniedException('unknown_capability');
        }

        $audience = $definition['audience'] ?? null;

        if (! is_string($audience)) {
            throw new AiExecutionDeniedException('capability_config');
        }

        $billingMode = $definition['billing_mode'] ?? null;
        $billingTenantId = match ($billingMode) {
            'platform' => (string) config('ai.public.billing_tenant_id', ''),
            'tenant' => $tenantId,
            default => null,
        };

        if (! is_string($billingTenantId) || ! Str::isUuid($billingTenantId)) {
            throw new AiExecutionDeniedException('billing_tenant');
        }

        if ($workforceContext !== null) {
            if ($tenantId === null || ! hash_equals($tenantId, $workforceContext->tenantId)) {
                throw new AiExecutionDeniedException('workforce_tenant');
            }

            $allowedTools = array_values(array_intersect(
                $allowedTools,
                $workforceContext->configuration->allowedJobs,
            ));
        }

        // Platform-funded public work still needs an owning tenant for RLS,
        // execution retention and accounting. Audience remains public; this
        // tenant ID is an internal persistence scope, never request input.
        $executionTenantId = $billingMode === 'platform' ? $billingTenantId : $tenantId;

        return new AiExecutionContext(
            correlationId: $correlationId ?? (string) Str::uuid(),
            audience: $audience,
            capability: $capability,
            tenantId: $executionTenantId,
            billingTenantId: $billingTenantId,
            actorId: $actorId,
            authorized: $authorized,
            entitled: $entitled,
            rateLimitSubject: hash('sha256', $rateLimitIdentity),
            allowedTools: $allowedTools,
            conversationId: $conversationId,
            requestMessageId: $requestMessageId,
        );
    }
}
