<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\Support;

final class AttachmentClassifier
{
    /**
     * @param  list<array<string, mixed>>  $attachments
     * @return list<array<string, mixed>>
     */
    public static function classify(array $attachments): array
    {
        return array_map(
            static function (array $attachment): array {
                $mime = strtolower((string) ($attachment['mime_type'] ?? $attachment['mimeType'] ?? 'application/octet-stream'));
                $classification = match (true) {
                    str_starts_with($mime, 'image/') => 'image',
                    str_starts_with($mime, 'audio/') => 'audio',
                    str_contains($mime, 'pdf'),
                    str_contains($mime, 'document'),
                    str_contains($mime, 'text/') => 'document',
                    default => 'binary',
                };

                return [
                    ...$attachment,
                    'mime_type' => $mime,
                    'classification' => $classification,
                ];
            },
            array_values($attachments),
        );
    }
}
