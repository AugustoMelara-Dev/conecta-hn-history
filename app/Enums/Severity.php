<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Severity - PSR-3 Compliant Log Severity Levels
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  AUDIT EVENT SEVERITY: Standardized Security Event Classification       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  USAGE IN AUDIT EVENTS:                                                 ║
 * ║    - DEBUG: Verbose diagnostic information                              ║
 * ║    - INFO: Normal operations (successful login)                        ║
 * ║    - NOTICE: Significant events (password change)                      ║
 * ║    - WARNING: Unusual situations (failed login)                        ║
 * ║    - ERROR: Runtime errors (integration failure)                       ║
 * ║    - CRITICAL: Component unavailable (payment gateway down)            ║
 * ║    - ALERT: Action required now (data breach detected)                 ║
 * ║    - EMERGENCY: System unusable (database corruption)                  ║
 * ║                                                                          ║
 * ║  PSR-3 COMPLIANCE: Values match Psr\Log\LogLevel constants             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
enum Severity: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case NOTICE = 'notice';
    case WARNING = 'warning';
    case ERROR = 'error';
    case CRITICAL = 'critical';
    case ALERT = 'alert';
    case EMERGENCY = 'emergency';

    /**
     * Get numeric priority for sorting/filtering.
     *
     * Higher number = more severe.
     */
    public function priority(): int
    {
        return match ($this) {
            self::DEBUG => 100,
            self::INFO => 200,
            self::NOTICE => 250,
            self::WARNING => 300,
            self::ERROR => 400,
            self::CRITICAL => 500,
            self::ALERT => 550,
            self::EMERGENCY => 600,
        };
    }

    /**
     * Check if this severity requires immediate attention.
     */
    public function requiresImmediateAction(): bool
    {
        return $this->priority() >= self::CRITICAL->priority();
    }

    /**
     * Check if this severity should trigger alerts.
     */
    public function shouldAlert(): bool
    {
        return $this->priority() >= self::WARNING->priority();
    }

    /**
     * Get display label for UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::DEBUG => 'Debug',
            self::INFO => 'Informational',
            self::NOTICE => 'Notice',
            self::WARNING => 'Warning',
            self::ERROR => 'Error',
            self::CRITICAL => 'Critical',
            self::ALERT => 'Alert',
            self::EMERGENCY => 'Emergency',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::DEBUG => 'gray',
            self::INFO => 'blue',
            self::NOTICE => 'cyan',
            self::WARNING => 'yellow',
            self::ERROR => 'orange',
            self::CRITICAL => 'red',
            self::ALERT => 'red',
            self::EMERGENCY => 'red',
        };
    }
}
