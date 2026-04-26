<?php

declare(strict_types=1);

namespace App\Events\DynamicForms;

final readonly class SchemaPublished
{
    public function __construct(
        public string $entityDefinitionId,
        public string $entityKey,
        public int $versionNumber,
        public ?string $tenantId,
        public ?string $publishedBy,
    ) {}
}

