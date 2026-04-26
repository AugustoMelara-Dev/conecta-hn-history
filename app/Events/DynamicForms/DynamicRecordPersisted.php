<?php

declare(strict_types=1);

namespace App\Events\DynamicForms;

final readonly class DynamicRecordPersisted
{
    public function __construct(
        public string $recordId,
        public string $entityDefinitionId,
        public string $tenantId,
        public string $operation,
    ) {}
}

