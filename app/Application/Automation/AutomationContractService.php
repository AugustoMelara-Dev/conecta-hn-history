<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Application\Automation\Contracts\WorkflowRepositoryInterface;
use App\Domain\Automation\ToolAuthorizationDecision;
use Carbon\CarbonImmutable;

final readonly class AutomationContractService
{
    public function __construct(
        private WorkflowRepositoryInterface $repository,
    ) {}

    public function authorizeApi(
        string $tenantId,
        string $market,
        string $provider,
        string $capability,
        string $version,
    ): ToolAuthorizationDecision {
        return $this->capabilityDecision($tenantId, $market, $provider, $capability, $version);
    }

    public function authorizeMcp(
        string $tenantId,
        string $market,
        string $provider,
        string $capability,
        string $version,
    ): ToolAuthorizationDecision {
        return $this->capabilityDecision($tenantId, $market, $provider, $capability, $version);
    }

    public function verifyWebhook(
        string $tenantId,
        string $market,
        string $provider,
        string $capability,
        string $version,
        string $externalId,
        string $payload,
        string $signature,
    ): ToolAuthorizationDecision {
        $contract = $this->providerContract($market, $provider, $capability, $version);
        if ($contract === null) {
            return ToolAuthorizationDecision::deny('provider_capability_unavailable');
        }

        $expected = hash_hmac('sha256', $payload, (string) $contract['webhook_secret']);
        if (! hash_equals($expected, $signature)) {
            return ToolAuthorizationDecision::deny('invalid_signature');
        }

        $accepted = $this->repository->recordWebhookDelivery(
            $tenantId,
            $provider,
            $market,
            $version,
            $capability,
            $externalId,
            hash('sha256', $payload),
            CarbonImmutable::now(),
        );

        return $accepted
            ? ToolAuthorizationDecision::allow('accepted')
            : ToolAuthorizationDecision::deny('webhook_replay');
    }

    private function capabilityDecision(
        string $tenantId,
        string $market,
        string $provider,
        string $capability,
        string $version,
    ): ToolAuthorizationDecision {
        $contract = $this->providerContract($market, $provider, $capability, $version);

        return $contract === null
            ? ToolAuthorizationDecision::deny('provider_capability_unavailable', ['tenant_id' => $tenantId])
            : ToolAuthorizationDecision::allow('capability_available', ['tenant_id' => $tenantId]);
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
