<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * FailureNotifierInterface - System Health Alerting
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OBSERVABILITY: Active alerting for critical system failures.            ║
 * ║  CHANNELS: Slack, Discord, PagerDuty, Email.                            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface FailureNotifierInterface
{
    /**
     * Notify engineering team about a critical system failure.
     *
     * @param string $subject Short summary of the issue
     * @param string $message Detailed technical context (Stack trace, IDs)
     * @param array $metadata Additional context key-values
     */
    public function notifySystemFailure(string $subject, string $message, array $metadata = []): void;
}
