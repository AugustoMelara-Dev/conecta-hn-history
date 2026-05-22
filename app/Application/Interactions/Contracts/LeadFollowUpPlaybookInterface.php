<?php

declare(strict_types=1);

namespace App\Application\Interactions\Contracts;

interface LeadFollowUpPlaybookInterface
{
    public function sync(string $tenantId, int|string $leadId): void;
}
