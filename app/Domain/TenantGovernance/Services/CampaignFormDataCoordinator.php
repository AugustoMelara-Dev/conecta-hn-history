<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\Campaign;

final class CampaignFormDataCoordinator
{
    public function __construct(
        private readonly CampaignFormNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data, ?Campaign $record = null): array
    {
        return $this->normalizer->normalize($data, $record);
    }
}
