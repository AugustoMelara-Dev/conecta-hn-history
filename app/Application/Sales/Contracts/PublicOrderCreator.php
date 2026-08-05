<?php

declare(strict_types=1);

namespace App\Application\Sales\Contracts;

use App\Application\Sales\DTOs\CreatePublicOrderData;
use App\Application\Sales\Results\PublicOrderCreationResult;

interface PublicOrderCreator
{
    public function create(CreatePublicOrderData $data): PublicOrderCreationResult;
}
