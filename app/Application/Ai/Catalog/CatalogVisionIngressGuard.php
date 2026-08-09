<?php

declare(strict_types=1);

namespace App\Application\Ai\Catalog;

use App\Application\Privacy\Dovra\DataClassificationPolicy;
use App\Application\Privacy\Dovra\DovraDataIngressGuard;
use App\Domain\Privacy\Dovra\DataDecision;
use App\Domain\Privacy\Dovra\DataEnvelope;
use App\Infrastructure\Logging\SensitiveDataScrubber;
use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use App\Infrastructure\Privacy\Dovra\LegacySensitivePayloadRedactor;
use InvalidArgumentException;

final class CatalogVisionIngressGuard
{
    private const DOVRA_SOURCE = 'upload';

    private const CHANNEL = 'catalog_vision_queue';

    public function __construct(?DovraDataIngressGuard $ingress = null)
    {
        $this->ingress = $ingress ?? new DovraDataIngressGuard(
            new DataClassificationPolicy(
                new LegacySensitivePayloadRedactor(new SensitiveDataScrubber),
            ),
            new DovraIntegrationSeamRegistry,
        );
    }

    private readonly DovraDataIngressGuard $ingress;

    /** @param array<string, mixed> $descriptor */
    public function inspect(string $tenantId, string $actorId, array $descriptor): DataDecision
    {
        if (trim($tenantId) === '' || trim($actorId) === '') {
            throw new InvalidArgumentException('Catalog vision ingress context is unavailable.');
        }

        return $this->ingress->inspect(new DataEnvelope(
            sourceSystem: self::DOVRA_SOURCE,
            payload: $descriptor,
            tenantId: $tenantId,
            actorId: $actorId,
            channel: self::CHANNEL,
            context: [],
        ));
    }

    /** @return array{filename:string,mime:string,size_bytes:int,sha256:string}|null */
    public function validatedDescriptor(DataDecision $decision): ?array
    {
        if (! $decision->allowed) {
            return null;
        }

        $descriptor = $decision->redactedPayload;
        $keys = array_keys($descriptor);
        sort($keys);
        if ($keys !== ['filename', 'mime', 'sha256', 'size_bytes']) {
            return null;
        }
        if (! is_string($descriptor['filename'])
            || trim($descriptor['filename']) === ''
            || mb_strlen($descriptor['filename']) > 255
            || ! is_string($descriptor['mime'])
            || trim($descriptor['mime']) === ''
            || mb_strlen($descriptor['mime']) > 255
            || ! is_int($descriptor['size_bytes'])
            || $descriptor['size_bytes'] < 1
            || ! is_string($descriptor['sha256'])
            || preg_match('/^[a-f0-9]{64}$/D', $descriptor['sha256']) !== 1) {
            return null;
        }

        return [
            'filename' => $descriptor['filename'],
            'mime' => $descriptor['mime'],
            'size_bytes' => $descriptor['size_bytes'],
            'sha256' => $descriptor['sha256'],
        ];
    }
}
