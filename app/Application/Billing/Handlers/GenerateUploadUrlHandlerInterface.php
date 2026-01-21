<?php

declare(strict_types=1);

namespace App\Application\Billing\Handlers;

interface GenerateUploadUrlHandlerInterface
{
    /**
     * @return array{upload_url: string, key: string, headers: array<string, string>}
     */
    public function handle(string $tenantId, string $mimeType, int $sizeBytes): array;
}
