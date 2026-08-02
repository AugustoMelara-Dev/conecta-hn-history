<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Application\Automation\Contracts\WorkflowRepositoryInterface;
use App\Domain\Automation\ToolAuthorizationDecision;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class AutomationContractService
{
    public function __construct(
        private WorkflowRepositoryInterface $repository,
    ) {}

    public function authorizeApi(
        string $tenantId,
        string $actorId,
        string $market,
        string $provider,
        string $capability,
        string $version,
        string $idempotencyKey,
        string $payload,
        string $signature,
    ): ToolAuthorizationDecision {
        return $this->authorizeInbound(
            channel: 'api',
            tenantId: $tenantId,
            actorId: $actorId,
            market: $market,
            provider: $provider,
            capability: $capability,
            version: $version,
            idempotencyKey: $idempotencyKey,
            payload: $payload,
            signature: $signature,
        );
    }

    public function authorizeMcp(
        string $tenantId,
        string $actorId,
        string $market,
        string $provider,
        string $capability,
        string $version,
        string $idempotencyKey,
        string $payload,
        string $signature,
    ): ToolAuthorizationDecision {
        return $this->authorizeInbound(
            channel: 'mcp',
            tenantId: $tenantId,
            actorId: $actorId,
            market: $market,
            provider: $provider,
            capability: $capability,
            version: $version,
            idempotencyKey: $idempotencyKey,
            payload: $payload,
            signature: $signature,
        );
    }

    public function verifyWebhook(
        string $tenantId,
        string $actorId,
        string $market,
        string $provider,
        string $capability,
        string $version,
        string $externalId,
        string $idempotencyKey,
        string $payload,
        string $signature,
    ): ToolAuthorizationDecision {
        if ($tenantId === '' || $actorId === '' || $externalId === '' || $idempotencyKey === '') {
            return ToolAuthorizationDecision::deny('inbound_context_required');
        }

        $contract = $this->providerContract($market, $provider, $capability, $version);
        $secret = is_array($contract) ? (string) ($contract['webhook_secret'] ?? '') : '';
        if ($contract === null || $secret === '') {
            return ToolAuthorizationDecision::deny('provider_capability_unavailable');
        }

        $expected = hash_hmac('sha256', $this->canonicalPayload($tenantId, $actorId, $externalId, $idempotencyKey, $payload), $secret);
        if (! hash_equals($expected, $signature)) {
            return ToolAuthorizationDecision::deny('invalid_signature');
        }

        $occurredAt = CarbonImmutable::now();
        $scope = sprintf('webhook:%s:%s:%s', $provider, $capability, $version);

        try {
            return $this->repository->connection()->transaction(function () use (
                $tenantId,
                $actorId,
                $provider,
                $market,
                $version,
                $capability,
                $externalId,
                $idempotencyKey,
                $payload,
                $occurredAt,
                $scope,
            ): ToolAuthorizationDecision {
                if ($this->repository->hasWebhookDelivery($tenantId, $provider, $externalId)) {
                    return ToolAuthorizationDecision::deny('webhook_replay');
                }

                if (! $this->repository->claimIdempotency(
                    $tenantId,
                    $scope,
                    $idempotencyKey,
                    hash('sha256', $payload),
                    $occurredAt,
                )) {
                    return ToolAuthorizationDecision::deny('idempotency_replay');
                }

                if (! $this->repository->recordWebhookDelivery(
                    $tenantId,
                    $actorId,
                    $provider,
                    $market,
                    $version,
                    $capability,
                    $externalId,
                    hash('sha256', $payload),
                    $occurredAt,
                )) {
                    throw new RuntimeException('webhook_replay');
                }

                return ToolAuthorizationDecision::allow('accepted');
            }, 3);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'webhook_replay') {
                return ToolAuthorizationDecision::deny('webhook_replay');
            }

            throw $exception;
        }
    }

    private function authorizeInbound(
        string $channel,
        string $tenantId,
        string $actorId,
        string $market,
        string $provider,
        string $capability,
        string $version,
        string $idempotencyKey,
        string $payload,
        string $signature,
    ): ToolAuthorizationDecision {
        if ($tenantId === '' || $actorId === '' || $idempotencyKey === '') {
            return ToolAuthorizationDecision::deny('inbound_context_required');
        }

        $contract = $this->providerContract($market, $provider, $capability, $version);
        if ($contract === null) {
            return ToolAuthorizationDecision::deny('provider_capability_unavailable', ['tenant_id' => $tenantId]);
        }

        if (! (bool) config('automation.contracts.'.$channel.'_route_bound', false)) {
            return ToolAuthorizationDecision::deny('route_not_bound', ['tenant_id' => $tenantId, 'actor_id' => $actorId]);
        }

        $secret = (string) ($contract[$channel.'_secret'] ?? '');
        if ($secret === '' || $signature === '') {
            return ToolAuthorizationDecision::deny('authenticity_unavailable');
        }

        $expected = hash_hmac('sha256', $this->canonicalPayload($tenantId, $actorId, null, $idempotencyKey, $payload), $secret);
        if (! hash_equals($expected, $signature)) {
            return ToolAuthorizationDecision::deny('invalid_signature');
        }

        $scope = sprintf('inbound:%s:%s:%s:%s', $channel, $provider, $capability, $version);
        $accepted = $this->repository->claimIdempotency(
            $tenantId,
            $scope,
            $idempotencyKey,
            hash('sha256', $payload),
            CarbonImmutable::now(),
        );

        return $accepted
            ? ToolAuthorizationDecision::allow('accepted', ['tenant_id' => $tenantId, 'actor_id' => $actorId])
            : ToolAuthorizationDecision::deny('idempotency_replay');
    }

    private function canonicalPayload(
        string $tenantId,
        string $actorId,
        ?string $externalId,
        string $idempotencyKey,
        string $payload,
    ): string {
        return implode('|', array_filter([
            $tenantId,
            $actorId,
            $externalId,
            $idempotencyKey,
            $payload,
        ], static fn (?string $value): bool => $value !== null));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function providerContract(string $market, string $provider, string $capability, string $version): ?array
    {
        $providers = (array) config('automation.providers', []);
        $providerConfig = $providers[$provider] ?? null;
        if (! is_array($providerConfig)) {
            return null;
        }

        $marketConfig = $providerConfig['markets'][$market] ?? null;
        if (! is_array($marketConfig)) {
            return null;
        }

        if (($marketConfig['legal_basis'] ?? null) === null) {
            return null;
        }

        if (! in_array($capability, (array) ($marketConfig['capabilities'] ?? []), true)) {
            return null;
        }

        if (($marketConfig['minimum_version'] ?? $version) > $version) {
            return null;
        }

        return $marketConfig;
    }
}
