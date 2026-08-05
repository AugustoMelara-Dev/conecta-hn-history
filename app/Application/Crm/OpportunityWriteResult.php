<?php

declare(strict_types=1);

namespace App\Application\Crm;

use App\Models\CrmDeal;

final readonly class OpportunityWriteResult
{
    public function __construct(
        public CrmDeal $deal,
        public bool $replayed = false,
    ) {}
}
