<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use App\Domain\Shared\Contracts\UpcasterInterface as DomainUpcasterInterface;

/**
 * UpcasterInterface - Deprecated Backward-Compatibility Alias
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DEPRECATED: Use App\Domain\Shared\Contracts\UpcasterInterface instead. ║
 * ║  KEPT to prevent cascade failure across 8 Infrastructure files.         ║
 * ║  All existing implementors continue to work without modification.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Migration path: update Infrastructure imports to Domain namespace, then delete this file.
 *
 * @deprecated Use App\Domain\Shared\Contracts\UpcasterInterface
 */
interface UpcasterInterface extends DomainUpcasterInterface
{
}
