<?php

declare(strict_types=1);

namespace App\Application\Ai\Workforce;

use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Workforce\Contracts\WorkforceRegistryAuthority;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class WorkforceAgentResolver
{
    public function __construct(
        private WorkforceRegistryAuthority $registry,
    ) {}

    public function resolve(
        string $tenantId,
        ?string $agentVersionId,
        ?string $requestedRole = null,
        string $channel = 'web',
        string $marketCode = 'HN',
        int|string|null $actorId = null,
        ?string $cohortSubject = null,
    ): WorkforceAgentContext {
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

        $marketCode = strtoupper(trim($marketCode));
        $cohortSubject ??= $actorId === null ? $tenantId : (string) $actorId;
        $defaultMarketCode = strtoupper((string) config('ai.workforce.default_market_code', 'HN'));
        if (preg_match('/^[A-Z]{2}$/', $marketCode) !== 1) {
            $marketCode = preg_match('/^[A-Z]{2}$/', $defaultMarketCode) === 1 ? $defaultMarketCode : 'HN';
        }
        $query = new AiRegistryQuery(
            tenantId: $tenantId,
            audience: 'private',
            capability: 'private_tenant_agent',
            channel: $channel,
            marketCode: $marketCode,
            residencyClass: $marketCode,
            actorId: $actorId,
            cohortSubject: $cohortSubject,
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

        $configuredRoleKey = array_key_exists('workforce_role', $configuration)
            ? $configuration['workforce_role']
            : (array_key_exists('role', $configuration) ? $configuration['role'] : null);
        $configuredRole = null;
        if ($configuredRoleKey !== null) {
            if (! is_string($configuredRoleKey) || ($configuredRole = AgentRole::tryFromKey($configuredRoleKey)) === null) {
                throw new AiExecutionDeniedException('workforce_role');
            }
        }

        $derivedRole = AgentRoleCatalog::forAgentKey($definitionKey);
        if ($requested !== null) {
            if (($configuredRole !== null && $configuredRole !== $requested)
                || ($configuredRole === null && $derivedRole !== null && $derivedRole !== $requested)) {
                throw new AiExecutionDeniedException('workforce_role');
            }

            $role = $requested;
        } else {
            $role = $configuredRole ?? $derivedRole;
        }
        if ($role === null) {
            throw new AiExecutionDeniedException('workforce_role');
        }

        return new WorkforceAgentContext(
            tenantId: $tenantId,
            agentVersionId: $selection->agentVersionId,
            role: $role,
            configuration: AgentRoleCatalog::configuration($role),
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
