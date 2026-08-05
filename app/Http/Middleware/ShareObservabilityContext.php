<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Shared\Contracts\TraceContextInterface;
use App\Domain\Shared\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ShareObservabilityContext
{
    public function __construct(
        private readonly TraceContextInterface $traceContext,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Log::shareContext($this->buildContext($request));

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-ID', $this->traceContext->getTraceId());

        if ($this->traceContext->getCausationId() !== null) {
            $response->headers->set('X-Causation-ID', $this->traceContext->getCausationId());
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(Request $request): array
    {
        return array_filter([
            'trace_id' => $this->traceContext->getTraceId(),
            'causation_id' => $this->traceContext->getCausationId(),
            'tenant_id' => $this->resolveTenantId(),
            'request_method' => $request->getMethod(),
            'request_path' => '/'.ltrim($request->path(), '/'),
            'request_host' => $request->getHost(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function resolveTenantId(): ?string
    {
        if (! $this->tenantContext->isInitialized() || $this->tenantContext->isRoot()) {
            return null;
        }

        try {
            return $this->tenantContext->id();
        } catch (Throwable) {
            return null;
        }
    }
}
