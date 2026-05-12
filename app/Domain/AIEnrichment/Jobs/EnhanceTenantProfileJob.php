<?php

declare(strict_types=1);

namespace App\Domain\AIEnrichment\Jobs;

/**
 * Compatibility bridge for legacy AI enrichment dispatches.
 *
 * The canonical implementation lives in Infrastructure\Jobs\AI, but older
 * tests and queued payloads still reference this namespace.
 */
class EnhanceTenantProfileJob extends \App\Infrastructure\Jobs\AI\EnhanceTenantProfileJob
{
}
