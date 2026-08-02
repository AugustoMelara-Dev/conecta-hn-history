<?php

declare(strict_types=1);

namespace App\Application\Privacy\Dovra;

use App\Domain\Privacy\Dovra\DataDecision;
use App\Domain\Privacy\Dovra\DataEnvelope;
use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;

final class DovraDataIngressGuard
{
    public function __construct(
        private readonly DataClassificationPolicy $policy,
        private readonly DovraIntegrationSeamRegistry $seams,
    ) {}

    public function inspect(DataEnvelope $data): DataDecision
    {
        $decision = $this->policy->classifyAndValidate($data);

        if (! $decision->allowed) {
            return $decision;
        }

        if ($this->seams->entrypointRequiresTenantContext($data->sourceSystem)
            && ($data->tenantId === null || trim($data->tenantId) === '')) {
            return new DataDecision(
                allowed: false,
                classification: 'prohibited',
                retentionCategory: 'prohibited_quarantine',
                reasons: ['tenant_context_required'],
                redactedPayload: $decision->redactedPayload,
                integrationSeams: $decision->integrationSeams,
            );
        }

        return $decision;
    }
}
