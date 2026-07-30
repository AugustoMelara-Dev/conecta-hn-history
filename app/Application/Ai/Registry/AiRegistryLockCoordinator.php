<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AiRegistryLockCoordinator
{
    public function __construct(private AiReleaseEligibility $eligibility) {}

    public function tenant(string $tenantId): void
    {
        if (DB::table('tenants')
            ->where('id', $tenantId)
            ->lockForUpdate()
            ->first() === null) {
            throw ValidationException::withMessages([
                'tenant_id' => ['Registry owner tenant does not exist.'],
            ]);
        }
    }

    /**
     * @param  list<string>  $allowedLifecycleStatuses
     */
    public function releaseGraph(
        string $tenantId,
        string $decisionId,
        array $allowedLifecycleStatuses,
        string $denialReason,
        ?string $expectedAgentVersionId = null,
    ): AiReleaseCandidateSet {
        return $this->eligibility->assertExact(
            tenantId: $tenantId,
            decisionId: $decisionId,
            allowedLifecycleStatuses: $allowedLifecycleStatuses,
            denialReason: $denialReason,
            expectedAgentVersionId: $expectedAgentVersionId,
            lockForUpdate: true,
        );
    }

    /** @return Collection<int, object> */
    public function activationScope(
        string $tenantId,
        string $scopeType,
        string $scopeKey,
    ): Collection {
        return DB::table('ai_agent_activations')
            ->where('tenant_id', $tenantId)
            ->where('scope_type', $scopeType)
            ->where('scope_key', $scopeKey)
            ->whereNull('effective_until')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function activation(string $tenantId, string $activationId): ?object
    {
        return DB::table('ai_agent_activations')
            ->where('tenant_id', $tenantId)
            ->where('id', $activationId)
            ->lockForUpdate()
            ->first();
    }

    /** @return Collection<int, object> */
    public function decisionActivations(string $tenantId, string $decisionId): Collection
    {
        return DB::table('ai_agent_activations')
            ->where('tenant_id', $tenantId)
            ->where('release_decision_id', $decisionId)
            ->whereNull('effective_until')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** @return Collection<int, object> */
    public function decisionDependentActivations(string $tenantId, string $decisionId): Collection
    {
        $history = DB::table('ai_agent_activations')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get([
                'id',
                'release_decision_id',
                'previous_activation_id',
                'effective_until',
            ]);
        $byId = $history->keyBy(static fn (object $activation): string => (string) $activation->id);

        return $history
            ->filter(static function (object $activation) use ($byId, $decisionId): bool {
                if ($activation->effective_until !== null) {
                    return false;
                }
                $visited = [];
                $current = $activation;
                while ($current !== null) {
                    $currentId = (string) $current->id;
                    if (isset($visited[$currentId])) {
                        return false;
                    }
                    $visited[$currentId] = true;
                    if (hash_equals($decisionId, (string) $current->release_decision_id)) {
                        return true;
                    }
                    $previousId = $current->previous_activation_id;
                    $current = is_string($previousId)
                        ? $byId->get($previousId)
                        : null;
                }

                return false;
            })
            ->values();
    }

    /** @return Collection<int, object> */
    public function agentActivations(string $tenantId, string $agentVersionId): Collection
    {
        return DB::table('ai_agent_activations')
            ->where('tenant_id', $tenantId)
            ->where('agent_version_id', $agentVersionId)
            ->whereNull('effective_until')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
