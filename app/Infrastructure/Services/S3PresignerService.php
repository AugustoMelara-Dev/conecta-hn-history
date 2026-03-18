<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Shared\Contracts\PresignedUrlGeneratorInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

/**
 * S3PresignerService - The Authorizer (Level 30)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY: STRICT SIGNATURE GENERATION                                   ║
 * ║  1. Enforces Content-Type (No MIME Spoofing)                             ║
 * ║  2. Short TTL (5 Minutes)                                                ║
 * ║  3. Deterministic Keys (Tenant Scope)                                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class S3PresignerService implements PresignedUrlGeneratorInterface
{
    private string $disk;

    public function __construct()
    {
        $config = Config::get('filesystems.disks.s3');

        if (! is_array($config) || ! isset($config['bucket'], $config['region'], $config['key'], $config['secret'])) {
            throw new \RuntimeException('Critical Configuration Failure: S3 Disk (filesystems.disks.s3) is misconfigured or missing required keys.');
        }

        $this->disk = 's3';
    }

    /**
     * Generate a Pre-Signed PUT URL.
     *
     * @param  string  $key  The S3 object key (path)
     * @param  string  $mimeType  The strictly enforced Content-Type
     * @param  int  $sizeBytes  The strictly enforced Content-Length
     * @return array{url: string, headers: array<string, string>}
     */
    public function generatePutUrl(string $key, string $mimeType, int $sizeBytes): array
    {
        /** @var array{url: string, headers: array<string, mixed>} $signed */
        $signed = Storage::disk($this->disk)->temporaryUploadUrl($key, now()->addMinutes(5), [
            'ContentType' => $mimeType,
            'ContentLength' => $sizeBytes,
        ]);

        $headers = [];

        foreach ($signed['headers'] as $header => $value) {
            $headers[$header] = is_array($value)
                ? implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value))
                : (string) $value;
        }

        $headers['Content-Type'] = $mimeType;
        $headers['Content-Length'] = (string) $sizeBytes;

        return ['url' => $signed['url'], 'headers' => $headers];
    }
}
