<?php

declare(strict_types=1);

namespace App\Application\Common\Contracts;

/**
 * CircuitBreakerInterface - Infrastructure Resilience Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FAIL FAST: Protects the system from cascading failures.                ║
 * ║  RESOURCE PROTECTION: Prevents saturation of threads/connections.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface CircuitBreakerInterface
{
    /**
     * Check if the service is available to accept requests.
     *
     * @param string $serviceName Unique identifier for the external service
     * @return bool True if Closed or Half-Open, False if Open
     */
    public function isAvailable(string $serviceName): bool;

    /**
     * Report a successful operation.
     * Resets failure counters or transitions to Closed state.
     *
     * @param string $serviceName Unique identifier for the external service
     */
    public function reportSuccess(string $serviceName): void;

    /**
     * Report a failed operation.
     * Increments counters and may trip the circuit to Open state.
     *
     * @param string $serviceName Unique identifier for the external service
     */
    public function reportFailure(string $serviceName): void;
}
