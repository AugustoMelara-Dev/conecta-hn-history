<?php

declare(strict_types=1);

namespace App\Application\Ai\Policies;

use App\Application\Ai\DTOs\AiExecutionContext;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Support\Str;

final class AiCapabilityPolicy
{
    public function assertExecutionAllowed(AiExecutionContext $context): void
    {
        if (! (bool) config('ai.enabled', false)) {
            $this->deny('global_kill_switch');
        }

        $environments = (array) config('ai.allowed_environments', []);
        if (! in_array(app()->environment(), $environments, true)) {
            $this->deny('environment');
        }

        $definition = config("ai.capabilities.{$context->capability}");
        if (! is_array($definition)) {
            $this->deny('unknown_capability');
        }

        if (! (bool) ($definition['enabled'] ?? false)) {
            $this->deny('capability_disabled');
        }

        if (($definition['audience'] ?? null) !== $context->audience) {
            $this->deny('capability_audience');
        }

        if (! $context->authorized) {
            $this->deny('authorization');
        }

        if (! $context->entitled) {
            $this->deny('entitlement');
        }

        $requiresTenant = (bool) ($definition['requires_tenant'] ?? false);
        if ($requiresTenant && ($context->tenantId === null || ! Str::isUuid($context->tenantId))) {
            $this->deny('tenant_context');
        }

        $billingMode = $definition['billing_mode'] ?? null;
        if ($billingMode === 'platform') {
            $platformBillingTenant = (string) config('ai.public.billing_tenant_id', '');
            if (! Str::isUuid($platformBillingTenant)
                || ! hash_equals($platformBillingTenant, $context->billingTenantId)) {
                $this->deny('public_billing_tenant');
            }
        } elseif ($billingMode === 'tenant') {
            if ($context->tenantId === null
                || ! hash_equals($context->tenantId, $context->billingTenantId)) {
                $this->deny('billing_tenant');
            }
        } else {
            $this->deny('billing_mode');
        }

        if ($context->audience === 'private') {
            if (! (bool) config('ai.private.enabled', false)) {
                $this->deny('private_kill_switch');
            }

            $allowlist = (array) config('ai.private.tenant_allowlist', []);
            if (! in_array($context->tenantId, $allowlist, true)) {
                $this->deny('tenant_allowlist');
            }
        }

        $requestsPerMinute = $definition['requests_per_minute'] ?? null;
        if (! is_numeric($requestsPerMinute) || (int) $requestsPerMinute < 1) {
            $this->deny('invalid_rate_policy');
        }
    }

    public function assertRequestAllowed(AiExecutionRequest $request): void
    {
        $schemas = config('ai.schemas', []);
        $schema = is_array($schemas) ? ($schemas[$request->outputSchema] ?? null) : null;

        if (! is_array($schema) || ($schema['capability'] ?? null) !== $request->context->capability) {
            $this->deny('output_schema');
        }

        $rules = $schema['rules'] ?? null;
        if (! is_array($rules) || $rules === []) {
            $this->deny('output_schema');
        }

        $this->assertToolsAllowed($request);
    }

    private function assertToolsAllowed(AiExecutionRequest $request): void
    {
        $capabilityTools = (array) config("ai.capabilities.{$request->context->capability}.tools", []);
        $definitions = (array) config('ai.tools', []);

        foreach ($request->requestedTools as $tool) {
            if (
                ! in_array($tool, $request->context->allowedTools, true)
                || ! in_array($tool, $capabilityTools, true)
                || ! isset($definitions[$tool])
            ) {
                $this->deny('tool_not_allowed');
            }

            $risk = (string) ($definitions[$tool]['risk'] ?? 'unknown');
            if (in_array($risk, ['economic', 'mutation'], true)) {
                // The provider-facing gateway is deliberately read-only.
                // Sensitive tools must be prepared and consumed through the
                // durable actor-bound confirmation endpoint instead.
                $this->deny('tool_requires_explicit_confirmation');
            }

            if ($risk !== 'read') {
                $this->deny('tool_risk_unknown');
            }
        }
    }

    private function deny(string $reason): never
    {
        throw new AiExecutionDeniedException($reason);
    }
}
