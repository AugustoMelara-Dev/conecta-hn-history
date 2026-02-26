<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * TraceContextInterface - Distributed Tracing Contract
 *
 * Provides trace and causation IDs for the current request lifecycle.
 * The Application Layer uses this contract; the Infrastructure Layer
 * provides the implementation (e.g., HTTP header extraction, OpenTelemetry).
 */
interface TraceContextInterface
{
    public function getTraceId(): string;

    public function getCausationId(): ?string;
}
