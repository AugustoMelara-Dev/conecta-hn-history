<?php

declare(strict_types=1);

namespace App\Application\Ai\Observability;

use App\Application\Ai\Conversation\AiConversationRetention;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class AiFoundationHealth
{
    public function __construct(
        private AiConversationRetention $retention,
    ) {}

    public function snapshot(): AiFoundationHealthSnapshot
    {
        $registryStatus = $this->registryStatus();
        $eligibleRouteCount = $this->eligibleRouteCount();
        $accountingPending = (int) DB::table('ai_executions')
            ->where('status', 'accounting_pending')
            ->count();
        $retentionBacklog = $this->retention->backlogCount();
        $lastSuccessfulEvaluationAt = DB::table('ai_release_decisions')
            ->where('decision', 'passed')
            ->whereNull('revoked_at')
            ->max('decided_at');

        return new AiFoundationHealthSnapshot(
            ready: $registryStatus === 'ready'
                && $accountingPending === 0
                && $retentionBacklog === 0,
            registryStatus: $registryStatus,
            eligibleRouteCount: $eligibleRouteCount,
            accountingPending: $accountingPending,
            retentionBacklog: $retentionBacklog,
            lastSuccessfulEvaluationAt: is_string($lastSuccessfulEvaluationAt)
                ? Carbon::parse($lastSuccessfulEvaluationAt)->toIso8601String()
                : null,
        );
    }

    private function eligibleRouteCount(): int
    {
        $now = now();

        return (int) DB::table('ai_agent_activations')
            ->join('ai_release_decisions', function ($join): void {
                $join->on('ai_release_decisions.id', '=', 'ai_agent_activations.release_decision_id')
                    ->on('ai_release_decisions.tenant_id', '=', 'ai_agent_activations.tenant_id');
            })
            ->where('ai_release_decisions.decision', 'passed')
            ->whereNull('ai_release_decisions.revoked_at')
            ->where('ai_release_decisions.valid_from', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('ai_release_decisions.valid_until')
                    ->orWhere('ai_release_decisions.valid_until', '>=', $now);
            })
            ->where('ai_agent_activations.effective_from', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('ai_agent_activations.effective_until')
                    ->orWhere('ai_agent_activations.effective_until', '>=', $now);
            })
            ->count();
    }

    private function registryStatus(): string
    {
        if (! $this->hasValidPlatformOwner()) {
            return 'missing_platform_owner';
        }

        if ((bool) config('ai.registry.bootstrap_enabled', false)) {
            return 'bootstrap_enabled';
        }

        if (! $this->enabledProvidersHaveSafeSecretReferences()) {
            return 'provider_secrets_unresolved';
        }

        if (! $this->enabledCapabilitiesHaveActiveProviders()) {
            return 'capability_provider_mismatch';
        }

        return 'ready';
    }

    private function hasValidPlatformOwner(): bool
    {
        $tenantId = (string) config('ai.public.billing_tenant_id', '');

        return Str::isUuid($tenantId)
            && DB::table('tenants')->where('id', $tenantId)->exists();
    }

    private function enabledProvidersHaveSafeSecretReferences(): bool
    {
        foreach ((array) config('ai.providers', []) as $provider) {
            if (! is_array($provider) || ! (bool) ($provider['enabled'] ?? false)) {
                continue;
            }

            $reference = (string) ($provider['secret_reference'] ?? '');
            if ($reference === '' || $this->looksLikeRawSecret($reference)) {
                return false;
            }
        }

        return true;
    }

    private function enabledCapabilitiesHaveActiveProviders(): bool
    {
        foreach ((array) config('ai.capabilities', []) as $capability) {
            if (! is_array($capability) || ! (bool) ($capability['enabled'] ?? false)) {
                continue;
            }

            $provider = (string) ($capability['provider'] ?? '');

            if ($provider === '' || ! (bool) config("ai.providers.{$provider}.enabled", false)) {
                return false;
            }
        }

        return true;
    }

    private function looksLikeRawSecret(string $value): bool
    {
        return Str::startsWith($value, ['sk-', 'rk-', 'pk-', '-----BEGIN'])
            || str_contains($value, '://')
            || preg_match('/\s/', $value) === 1;
    }
}
