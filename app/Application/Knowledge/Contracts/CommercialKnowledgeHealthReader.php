<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

use App\Application\Knowledge\CommercialKnowledgeHealth;

interface CommercialKnowledgeHealthReader
{
    public function read(string $tenantId): CommercialKnowledgeHealth;
}
