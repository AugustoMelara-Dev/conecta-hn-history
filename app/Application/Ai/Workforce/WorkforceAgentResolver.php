<?php

declare(strict_types=1);

namespace App\Application\Ai\Workforce;

use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class WorkforceAgentResolver
{
    public function __construct(
        private AiRegistryResolver $registry,
    ) {}

    public function resolve(string $tenantId, ?string $agentVersionId, ?string $requestedRole = null): WorkforceAgentContext
    {
        $tenantId = strtolower(trim($tenantId));
        if (! Str::isUuid($tenantId)) {
            throw new InvalidArgumentException('Workforce tenant ID must be a UUID.');
        }

        $requested = null;
        if ($requestedRole !== null) {
            $requested = AgentRole::tryFromKey($requestedRole);
            if ($requested === null) {
                throw new AiExecutionDeniedException('workforce_role');
            }
        }

        $marketCode = strtoupper((string) config('ai.workforce.default_market_code', 'HN'));
        $query = new AiRegistryQuery(
            tenantId: $tenantId,
            audience: 'private',
            capability: 'private_tenant_agent',
            channel: 'web',
            marketCode: preg_match('/^[A-Z]{2}$/', $marketCode) === 1 ? $marketCode : 'HN',
            residencyClass: preg_match('/^[A-Z]{2}$/', $marketCode) === 1 ? $marketCode : 'HN',
            actorId: null,
            cohortSubject: $tenantId,
        );
        $selection = $agentVersionId === null
            ? $this->registry->resolve($query)
            : $this->registry->resolvePinned($query, $agentVersionId);

        if ($agentVersionId !== null && ! hash_equals($agentVersionId, $selection->agentVersionId)) {
            throw new AiExecutionDeniedException('workforce_version');
        }

        $version = DB::table('ai_agent_versions')
            ->where('tenant_id', $tenantId)
            ->where('id', $selection->agentVersionId)
            ->first();
        if ($version === null) {
            throw new AiExecutionDeniedException('workforce_version');
        }

        if (property_exists($version, 'lifecycle_status')
            && ! in_array((string) $version->lifecycle_status, ['active', 'eligible'], true)) {
            throw new AiExecutionDeniedException('workforce_version');
        }

        if (property_exists($version, 'kill_switch_enabled') && (bool) $version->kill_switch_enabled) {
            throw new AiExecutionDeniedException('workforce_version');
        }

        $configuration = $this->decodeConfiguration($version->configuration ?? null);
        $definitionKey = '';
        if (isset($version->agent_definition_id)) {
            $definitionKey = (string) (DB::table('ai_agent_definitions')
                ->where('tenant_id', $tenantId)
                ->where('id', $version->agent_definition_id)
                ->value('key') ?? '');
        }

        $role = $requested
            ?? AgentRole::tryFromKey((string) ($configuration['workforce_role'] ?? $configuration['role'] ?? ''))
            ?? AgentRoleCatalog::forAgentKey($definitionKey);
        if ($role === null) {
            throw new AiExecutionDeniedException('workforce_role');
        }

        $configuredRole = AgentRole::tryFromKey((string) ($configuration['workforce_role'] ?? ''));
        if ($configuredRole !== null && $configuredRole !== $role) {
            throw new AiExecutionDeniedException('workforce_role');
        }

        return new WorkforceAgentContext(
            tenantId: $tenantId,
            agentVersionId: $selection->agentVersionId,
            role: $role,
            configuration: AgentRoleCatalog::configuration($role),
            selection: $selection,
        );
    }

    /** @return array<string, mixed> */
    private function decodeConfiguration(mixed $configuration): array
    {
        if (is_array($configuration)) {
            return $configuration;
        }

        if ($configuration === null || trim((string) $configuration) === '') {
            return [];
        }

        $decoded = json_decode((string) $configuration, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new AiExecutionDeniedException('workforce_configuration');
        }

        return $decoded;
    }
}
