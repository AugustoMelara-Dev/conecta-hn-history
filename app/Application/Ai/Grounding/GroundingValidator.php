<?php

declare(strict_types=1);

namespace App\Application\Ai\Grounding;

use App\Exceptions\Ai\AiMalformedResponseException;

final class GroundingValidator
{
    /**
     * @param  array<string, mixed>  $providerOutput
     * @param  list<EvidenceSnapshot>  $authorizedEvidence
     */
    public function validate(
        array $providerOutput,
        array $authorizedEvidence,
        GroundingPolicy $policy,
    ): GroundedAnswer {
        $authorizedById = [];
        foreach ($authorizedEvidence as $snapshot) {
            if (! $snapshot instanceof EvidenceSnapshot
                || isset($authorizedById[$snapshot->evidenceId])
                || $snapshot->tenantId !== $policy->tenantId
                || $snapshot->visibility !== $policy->visibility
                || ($policy->visibility === 'private' && $snapshot->sourceTenantId !== $policy->tenantId)
                || $snapshot->freshAt->lt($policy->freshnessCutoff)
                || ! in_array($snapshot->sourceType, $policy->allowedClaimTypes, true)
                || ($snapshot->currency !== null && $snapshot->currency !== $policy->currency)) {
                $this->reject();
            }
            $authorizedById[$snapshot->evidenceId] = $snapshot;
        }

        $claims = $providerOutput['evidence'] ?? null;
        if (! is_array($claims) || ! array_is_list($claims) || $claims === []) {
            $this->reject();
        }
        $selected = [];
        foreach ($claims as $claim) {
            if (! is_array($claim)
                || ! is_string($claim['id'] ?? null)
                || ! is_string($claim['type'] ?? null)
                || isset($selected[$claim['id']])) {
                $this->reject();
            }
            $snapshot = $authorizedById[$claim['id']] ?? null;
            if (! $snapshot instanceof EvidenceSnapshot
                || $snapshot->sourceType !== $claim['type']
                || (array_key_exists('source_version', $claim)
                    && $claim['source_version'] !== $snapshot->sourceVersion)
                || (array_key_exists('currency', $claim)
                    && $claim['currency'] !== $snapshot->currency)
                || (array_key_exists('price_minor', $claim)
                    && $claim['price_minor'] !== $snapshot->priceMinor)
                || (array_key_exists('availability', $claim)
                    && $claim['availability'] !== $snapshot->availability)) {
                $this->reject();
            }
            $selected[$snapshot->evidenceId] = $snapshot;
        }

        return new GroundedAnswer($providerOutput, array_values($selected));
    }

    private function reject(): never
    {
        throw new AiMalformedResponseException('Ungrounded AI claim.');
    }
}
