<?php

declare(strict_types=1);

namespace App\Events\DynamicForms;

final readonly class TemplatePublished
{
    public function __construct(
        public string $templateId,
        public string $templateKey,
        public int $versionNumber,
        public ?string $tenantId,
        public ?string $publishedBy,
    ) {}
}

