<?php

declare(strict_types=1);

namespace App\Application\Discovery;

use App\Infrastructure\Discovery\DiscoveryProjectionStore;

final class MarketplaceIntegrityService
{
    public function __construct(
        private readonly DiscoveryProjectionStore $store,
    ) {}

    public function reportProjection(string $projectionId, string $reporterFingerprint, string $reason): string
    {
        return $this->store->insertIntegrityRecord([
            'projection_id' => $projectionId,
            'record_type' => 'report',
            'status' => 'open',
            'reason' => $reason,
            'actor_scope' => 'buyer',
            'actor_reference' => $reporterFingerprint,
            'details' => ['reason' => $reason],
        ]);
    }

    public function moderateProjection(string $projectionId, string $status, ?string $reason): void
    {
        $this->store->insertIntegrityRecord([
            'projection_id' => $projectionId,
            'record_type' => 'moderation',
            'status' => $status,
            'score' => $status === 'rejected' ? 0 : 100,
            'reason' => $reason,
            'actor_scope' => 'platform',
            'actor_reference' => 'marketplace_integrity',
            'details' => ['reason' => $reason],
        ]);

        $this->store->updateModeration($projectionId, $status, $reason);
    }

    public function appealReport(string $projectionId, string $reportId, string $sellerReference, string $reason): string
    {
        return $this->store->insertIntegrityRecord([
            'projection_id' => $projectionId,
            'record_type' => 'appeal',
            'status' => 'submitted',
            'reason' => $reason,
            'actor_scope' => 'seller',
            'actor_reference' => $sellerReference,
            'details' => ['report_id' => $reportId, 'reason' => $reason],
        ]);
    }
}
