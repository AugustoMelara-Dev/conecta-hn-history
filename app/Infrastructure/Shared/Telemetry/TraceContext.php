<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Telemetry;

use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

/**
 * TraceContext - The Nervous System of Observability
 *
 * Captures or generates a unique Trace ID for the current request lifecycle.
 * Acts as a Singleton/Scoped service to propagate context across layers
 * without pollution of method signatures.
 */
final class TraceContext
{
    private string $traceId;
    private ?string $causationId = null;

    public function __construct(Request $request)
    {
        // 1. Ingestion: Capture upstream trace or generate new root trace
        $this->traceId = $request->header('X-Request-ID') 
            ?? $request->header('X-Correlation-ID') 
            ?? Uuid::uuid7()->toString();
            
        // 2. Causation: If triggered by another event/command
        $this->causationId = $request->header('X-Causation-ID');
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getCausationId(): ?string
    {
        return $this->causationId;
    }
    
    /**
     * For background jobs or tests to manually set context
     */
    public function setContext(string $traceId, ?string $causationId = null): void
    {
        $this->traceId = $traceId;
        $this->causationId = $causationId;
    }
}
