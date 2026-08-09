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
use Throwable;

final class KnowledgeQueryIngressGuard
{
    private const PUBLIC_SOURCE = 'directory_guide_chat';

    private const PRIVATE_SOURCE = 'tenant_agent_chat';

    private const CHANNEL = 'knowledge_query_runtime';

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

    public function inspectPublic(string $query): ?string
    {
        return $this->inspect($query, self::PUBLIC_SOURCE, null, []);
    }

    public function inspectTenant(string $query, string $tenantId): ?string
    {
        try {
            $businessType = $this->businessContext->resolvePolicyBusinessType($tenantId);
        } catch (Throwable) {
            return null;
        }
        if (! is_string($businessType) || trim($businessType) === '') {
            return null;
        }

        return $this->inspect(
            $query,
            self::PRIVATE_SOURCE,
            $tenantId,
            ['business_type' => $businessType],
        );
    }

    /** @param array<string, mixed> $context */
    private function inspect(string $query, string $source, ?string $tenantId, array $context): ?string
    {
        $normalized = self::normalize($query);
        if ($normalized === '') {
            return null;
        }
        $normalized = mb_substr($normalized, 0, 500);

        try {
            $decision = $this->ingress->inspect(new DataEnvelope(
                sourceSystem: $source,
                payload: ['query' => $normalized],
                tenantId: $tenantId,
                actorId: null,
                channel: self::CHANNEL,
                context: $context,
            ));
        } catch (Throwable) {
            return null;
        }

        if (! $decision->allowed) {
            return null;
        }

        $redacted = $decision->redactedPayload;
        if (array_keys($redacted) !== ['query']
            || ! is_string($redacted['query'])
            || trim($redacted['query']) === ''
            || mb_strlen($redacted['query']) > 500) {
            return null;
        }

        return self::normalize($redacted['query']);
    }

    private static function normalize(string $query): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $query));
    }
}
