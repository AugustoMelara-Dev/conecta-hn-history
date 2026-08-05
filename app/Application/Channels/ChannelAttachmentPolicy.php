<?php

declare(strict_types=1);

namespace App\Application\Channels;

use InvalidArgumentException;

final class ChannelAttachmentPolicy
{
    private const MAX_DESCRIPTORS = 10;

    private const MAX_DESCRIPTOR_BYTES = 10_485_760;

    private const MAX_TOTAL_BYTES = 26_214_400;

    /** @var array<string, array{classification: string, extensions: list<string>}> */
    private const MIME_POLICY = [
        'image/jpeg' => ['classification' => 'image', 'extensions' => ['jpg', 'jpeg']],
        'image/png' => ['classification' => 'image', 'extensions' => ['png']],
        'image/webp' => ['classification' => 'image', 'extensions' => ['webp']],
        'application/pdf' => ['classification' => 'document', 'extensions' => ['pdf']],
        'text/plain' => ['classification' => 'document', 'extensions' => ['txt']],
        'audio/mpeg' => ['classification' => 'audio', 'extensions' => ['mp3']],
        'audio/ogg' => ['classification' => 'audio', 'extensions' => ['ogg', 'oga']],
        'audio/wav' => ['classification' => 'audio', 'extensions' => ['wav']],
    ];

    /**
     * @param  array<int|string, mixed>  $attachments
     * @return list<array<string, mixed>>
     */
    public static function canonicalize(array $attachments): array
    {
        if (count($attachments) > self::MAX_DESCRIPTORS) {
            self::invalid();
        }
        if ($attachments === []) {
            return [];
        }

        $canonical = [];
        $seen = [];
        $totalBytes = 0;
        foreach (array_values($attachments) as $attachment) {
            if (! is_array($attachment)) {
                self::invalid();
            }

            $name = self::canonicalName($attachment);
            $mime = self::canonicalMime($attachment);
            $size = self::canonicalSize($attachment);
            self::assertExtension($name, $mime);
            $referenceHash = self::canonicalReferenceHash($attachment);

            $totalBytes += $size;
            if ($totalBytes > self::MAX_TOTAL_BYTES) {
                self::invalid();
            }

            $duplicateKey = json_encode([$name, $mime, $size, $referenceHash ?? ''], JSON_THROW_ON_ERROR);
            if (isset($seen[$duplicateKey])) {
                self::invalid();
            }
            $seen[$duplicateKey] = true;

            $descriptor = [
                'name' => $name,
                'mime_type' => $mime,
                'size_bytes' => $size,
                'classification' => self::MIME_POLICY[$mime]['classification'],
                'security_status' => 'descriptor_only',
                'content_verified' => false,
                'download_allowed' => false,
            ];
            if ($referenceHash !== null) {
                $descriptor['provider_reference_sha256'] = $referenceHash;
            }
            $canonical[] = $descriptor;
        }

        return $canonical;
    }

    /** @param array<string, mixed> $attachment */
    private static function canonicalName(array $attachment): string
    {
        $keys = array_values(array_filter(['name', 'filename'], static fn (string $key): bool => array_key_exists($key, $attachment)));
        if (count($keys) !== 1 || ! is_string($attachment[$keys[0]])) {
            self::invalid();
        }

        $rawName = $attachment[$keys[0]];
        if (preg_match('//u', $rawName) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $rawName) === 1) {
            self::invalid();
        }

        $name = trim($rawName);
        if ($name === ''
            || strlen($name) > 255
            || strpbrk($name, '/\\:') !== false
            || in_array('..', explode('/', str_replace('\\', '/', $name)), true)) {
            self::invalid();
        }

        return $name;
    }

    /** @param array<string, mixed> $attachment */
    private static function canonicalMime(array $attachment): string
    {
        $keys = array_values(array_filter(['mime_type', 'mimeType'], static fn (string $key): bool => array_key_exists($key, $attachment)));
        if (count($keys) !== 1 || ! is_string($attachment[$keys[0]])) {
            self::invalid();
        }

        $rawMime = $attachment[$keys[0]];
        if (strlen($rawMime) > 127
            || preg_match('//u', $rawMime) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $rawMime) === 1) {
            self::invalid();
        }

        $mime = strtolower(trim($rawMime));
        if ($mime === '' || strlen($mime) > 127 || ! isset(self::MIME_POLICY[$mime])) {
            self::invalid();
        }

        return $mime;
    }

    /** @param array<string, mixed> $attachment */
    private static function canonicalSize(array $attachment): int
    {
        $keys = array_values(array_filter(['size_bytes', 'size'], static fn (string $key): bool => array_key_exists($key, $attachment)));
        if (count($keys) !== 1 || ! is_int($attachment[$keys[0]])) {
            self::invalid();
        }

        $size = $attachment[$keys[0]];
        if ($size < 1 || $size > self::MAX_DESCRIPTOR_BYTES) {
            self::invalid();
        }

        return $size;
    }

    /** @param array<string, mixed> $attachment */
    private static function canonicalReferenceHash(array $attachment): ?string
    {
        $keys = array_values(array_filter(
            ['provider_attachment_id', 'provider_reference', 'id'],
            static fn (string $key): bool => array_key_exists($key, $attachment),
        ));
        if (count($keys) > 1) {
            self::invalid();
        }
        if ($keys === []) {
            return null;
        }

        $reference = $attachment[$keys[0]];
        if (! is_string($reference)
            || trim($reference) === ''
            || strlen($reference) > 512
            || preg_match('/[\x00-\x1F\x7F]/', $reference) === 1) {
            self::invalid();
        }

        return hash('sha256', $reference);
    }

    private static function assertExtension(string $name, string $mime): void
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($extension === '' || ! in_array($extension, self::MIME_POLICY[$mime]['extensions'], true)) {
            self::invalid();
        }
    }

    private static function invalid(): never
    {
        throw new InvalidArgumentException('Channel attachment descriptor is invalid.');
    }
}
