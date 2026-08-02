<?php

declare(strict_types=1);

namespace App\Application\Ai\Workforce;

use InvalidArgumentException;

final class AgentRoleCatalog
{
    /** @var array<string, array{objective: string, allowedJobs: list<string>, riskProfile: string, metricKeys: list<string>}> */
    private const CONFIGURATION = [
        'front_desk' => [
            'objective' => 'Orientar conversaciones y convertir preguntas en próximos pasos seguros.',
            'allowedJobs' => ['catalog.search', 'availability.read', 'crm.lead.capture', 'conversation.handoff'],
            'riskProfile' => 'low',
            'metricKeys' => ['response_quality', 'handoff_rate', 'lead_capture_rate'],
        ],
        'revenue' => [
            'objective' => 'Aumentar ingresos ayudando a descubrir, cotizar y avanzar oportunidades.',
            'allowedJobs' => ['catalog.search', 'quote.draft', 'crm.opportunity.read', 'conversation.handoff'],
            'riskProfile' => 'medium',
            'metricKeys' => ['qualified_opportunities', 'quote_acceptance_rate', 'revenue_attributed'],
        ],
        'service' => [
            'objective' => 'Resolver solicitudes de servicio y coordinar disponibilidad con precisión.',
            'allowedJobs' => ['catalog.search', 'availability.read', 'appointment.draft', 'conversation.handoff'],
            'riskProfile' => 'medium',
            'metricKeys' => ['resolution_rate', 'booking_completion_rate', 'handoff_rate'],
        ],
        'support' => [
            'objective' => 'Resolver incidencias con contexto, límites claros y escalamiento oportuno.',
            'allowedJobs' => ['crm.customer.read', 'order.read', 'conversation.handoff', 'support.case.draft'],
            'riskProfile' => 'medium',
            'metricKeys' => ['first_response_time', 'resolution_rate', 'safety_escalations'],
        ],
        'operations' => [
            'objective' => 'Mantener operaciones coordinadas, observables y libres de conflictos evitables.',
            'allowedJobs' => ['availability.read', 'appointment.read', 'inventory.read', 'workflow.draft'],
            'riskProfile' => 'high',
            'metricKeys' => ['schedule_accuracy', 'exception_rate', 'workflow_completion_rate'],
        ],
        'growth' => [
            'objective' => 'Convertir señales del negocio en experimentos de crecimiento medibles y reversibles.',
            'allowedJobs' => ['analytics.read', 'campaign.draft', 'crm.segment.read', 'conversation.handoff'],
            'riskProfile' => 'medium',
            'metricKeys' => ['qualified_demand', 'experiment_completion_rate', 'retention_signal'],
        ],
        'manager' => [
            'objective' => 'Coordinar la fuerza de trabajo con visibilidad, aprobación y responsabilidad.',
            'allowedJobs' => ['analytics.read', 'workflow.approve', 'agent.evaluate', 'conversation.handoff'],
            'riskProfile' => 'high',
            'metricKeys' => ['outcome_rate', 'approval_latency', 'policy_violations'],
        ],
    ];

    public static function configuration(AgentRole $role): WorkforceRoleConfiguration
    {
        $definition = self::CONFIGURATION[$role->value] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException("No Workforce catalog configuration exists for [{$role->value}].");
        }

        return new WorkforceRoleConfiguration(
            role: $role,
            objective: $definition['objective'],
            allowedJobs: $definition['allowedJobs'],
            riskProfile: $definition['riskProfile'],
            metricKeys: $definition['metricKeys'],
        );
    }

    public static function forAgentKey(string $agentKey): ?AgentRole
    {
        $key = strtolower(trim($agentKey));

        return match ($key) {
            'front_desk', 'agent.front_desk', 'public.discovery' => AgentRole::FRONT_DESK,
            'revenue', 'agent.revenue', 'tenant.revenue' => AgentRole::REVENUE,
            'service', 'agent.service', 'tenant.service' => AgentRole::SERVICE,
            'support', 'agent.support', 'tenant.support' => AgentRole::SUPPORT,
            'operations', 'agent.operations', 'tenant.operations' => AgentRole::OPERATIONS,
            'growth', 'agent.growth', 'tenant.growth' => AgentRole::GROWTH,
            'manager', 'agent.manager', 'tenant.manager', 'tenant.private_assistant' => AgentRole::MANAGER,
            default => AgentRole::tryFromKey($key),
        };
    }

    /** @return list<AgentRole> */
    public static function roles(): array
    {
        return [
            AgentRole::FRONT_DESK,
            AgentRole::REVENUE,
            AgentRole::SERVICE,
            AgentRole::SUPPORT,
            AgentRole::OPERATIONS,
            AgentRole::GROWTH,
            AgentRole::MANAGER,
        ];
    }
}
