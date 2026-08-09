<?php

declare(strict_types=1);

namespace App\Application\Ai\Knowledge;

use App\Application\Privacy\Dovra\DataClassificationPolicy;
use App\Application\Privacy\Dovra\DovraDataIngressGuard;
use App\Domain\Privacy\Dovra\DataEnvelope;
use App\Infrastructure\Logging\SensitiveDataScrubber;
use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use App\Infrastructure\Privacy\Dovra\DovraTenantBusinessContextResolver;
use App\Infrastructure\Privacy\Dovra\LegacySensitivePayloadRedactor;
use RuntimeException;
use Throwable;

final class KnowledgeIngressGuard
{
    private const DOVRA_SOURCE = 'knowledge_ingestion';

    private const CHANNEL = 'knowledge_index_queue';

    public function __construct(
        ?DovraDataIngressGuard $ingress = null,
        ?DovraTenantBusinessContextResolver $businessContext = null,
    ) {
        $this->ingress = $ingress ?? new DovraDataIngressGuard(
            new DataClassificationPolicy(
                new LegacySensitivePayloadRedactor(new SensitiveDataScrubber),
            ),
            new DovraIntegrationSeamRegistry,
        );
        $this->businessContext = $businessContext ?? new DovraTenantBusinessContextResolver;
    }

    private readonly DovraDataIngressGuard $ingress;

    private readonly DovraTenantBusinessContextResolver $businessContext;

    /**
     * @param  array{resource_type:string,resource_id:string,visibility:string,content:string,business_name:string}  $payload
     * @return array{resource_type:string,resource_id:string,visibility:string,content:string,business_name:string}|null
     */
    public function inspect(string $tenantId, array $payload): ?array
    {
        try {
            $businessType = $this->businessContext->resolvePolicyBusinessType($tenantId);
            if (! is_string($businessType) || trim($businessType) === '') {
                throw new RuntimeException;
            }
            $decision = $this->ingress->inspect(new DataEnvelope(
                sourceSystem: self::DOVRA_SOURCE,
                payload: $payload,
                tenantId: $tenantId,
                actorId: null,
                channel: self::CHANNEL,
                context: ['business_type' => $businessType],
            ));
        } catch (Throwable) {
            throw self::unavailable();
        }

        if (! $decision->allowed) {
            if (array_intersect(['entrypoint_not_registered', 'tenant_context_required'], $decision->reasons) !== []) {
                throw self::unavailable();
            }

            return null;
        }

        $redacted = $decision->redactedPayload;
        $keys = array_keys($redacted);
        sort($keys);
        if ($keys !== ['business_name', 'content', 'resource_id', 'resource_type', 'visibility']
            || ! self::boundedString($redacted['content'] ?? null, 8000)
            || ! self::boundedString($redacted['business_name'] ?? null, 255)) {
            throw self::unavailable();
        }

        foreach (['resource_type', 'resource_id', 'visibility'] as $authority) {
            $candidate = $redacted[$authority] ?? null;
            if (! is_string($candidate)
                || ! hash_equals(hash('sha256', $payload[$authority]), hash('sha256', $candidate))) {
                throw self::unavailable();
            }
        }

        /** @var array{resource_type:string,resource_id:string,visibility:string,content:string,business_name:string} $redacted */
        return $redacted;
    }

    private static function boundedString(mixed $value, int $maximum): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= $maximum;
    }

    private static function unavailable(): RuntimeException
    {
        return new RuntimeException('Knowledge ingress validation is unavailable.');
    }
}
