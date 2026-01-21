<?php

declare(strict_types=1);

namespace App\Events\Analytics;

use App\Domain\TenantGovernance\Entities\TenantEntity;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MicrositeViewed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly TenantEntity $tenant,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent
    ) {}
}
