<?php

declare(strict_types=1);

namespace App\Events\DynamicForms;

final readonly class TenantBlueprintCloned
{
    public function __construct(
        public string $blueprintType,
        public string $sourceId,
        public string $targetId,
        public string $tenantId,
        public ?string $actorId,
    ) {}
}

