<?php

declare(strict_types=1);

namespace App\Domain\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * OtpGenerated - Domain Event
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE DTO: Carries only primitive data. No Models.                      ║
 * ║  EPHEMERAL: Contains the plain text code. SHOULD NOT BE LOGGED.         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class OtpGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $identifier,
        public readonly string $code,
        public readonly string $recipientName,
        public readonly int $expiryMinutes,
    ) {}
}
