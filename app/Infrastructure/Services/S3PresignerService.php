<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Config;

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
final class S3PresignerService
{
    private S3Client $client;
    private string $bucket;

    public function __construct()
    {
        $config = Config::get('filesystems.disks.s3');
        
        if (!is_array($config) || !isset($config['bucket'], $config['region'], $config['key'], $config['secret'])) {
            throw new \RuntimeException("Critical Configuration Failure: S3 Disk (filesystems.disks.s3) is misconfigured or missing required keys.");
        }

        $this->bucket = $config['bucket'];
        
        $this->client = new S3Client([
            'version' => 'latest',
            'region'  => $config['region'],
            'credentials' => [
                'key'    => $config['key'],
                'secret' => $config['secret'],
            ],
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
        ]);
    }

    /**
     * Generate a Pre-Signed PUT URL.
     * 
     * @param string $key The S3 object key (path)
     * @param string $mimeType The strictly enforced Content-Type
     * @param int $sizeBytes The strictly enforced Content-Length
     * @return array{url: string, headers: array<string, string>}
     */
    public function generatePutUrl(string $key, string $mimeType, int $sizeBytes): array
    {
        $cmd = $this->client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key'    => $key,
            'ContentType' => $mimeType,
            'ContentLength' => $sizeBytes, 
        ]);

        $request = $this->client->createPresignedRequest($cmd, '+5 minutes');

        return [
            'url' => (string) $request->getUri(),
            'headers' => [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $sizeBytes,
            ],
        ];
    }
}
