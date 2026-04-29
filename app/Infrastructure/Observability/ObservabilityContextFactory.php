<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use App\Application\Shared\Contracts\TraceContextInterface;
use App\Domain\Shared\Services\TenantContext;
use Throwable;

final readonly class ObservabilityContextFactory
{
    public function __construct(
        private TraceContextInterface $traceContext,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function make(array $context = []): array
    {
        return array_merge([
            'trace_id' => $this->traceContext->getTraceId(),
            'causation_id' => $this->traceContext->getCausationId(),
            'tenant_id' => $this->resolveTenantId(),
            'environment' => config('app.env'),
            'service' => config('app.name', 'vito-business-os'),
        ], $context);
    }

    private function resolveTenantId(): ?string
    {
        if (! $this->tenantContext->isInitialized() || $this->tenantContext->isRoot()) {
            return null;
        }

        try {
            return (string) $this->tenantContext->id();
        } catch (Throwable) {
            return null;
        }
    }
}
