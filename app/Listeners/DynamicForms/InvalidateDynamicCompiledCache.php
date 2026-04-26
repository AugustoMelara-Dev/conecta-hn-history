<?php

declare(strict_types=1);

namespace App\Listeners\DynamicForms;

use App\Application\DynamicForms\Contracts\DynamicCompiledCacheInterface;
use App\Events\DynamicForms\SchemaPublished;
use App\Events\DynamicForms\TemplatePublished;
use App\Events\DynamicForms\TenantBlueprintCloned;

final class InvalidateDynamicCompiledCache
{
    public function __construct(
        private readonly DynamicCompiledCacheInterface $compiledCache
    ) {}

    public function handle(object $event): void
    {
        if ($event instanceof SchemaPublished) {
            $this->compiledCache->forgetSchema(
                tenantId: $event->tenantId,
                entityKey: $event->entityKey,
                schemaVersion: $event->versionNumber,
            );

            return;
        }

        if ($event instanceof TemplatePublished) {
            $this->compiledCache->forgetTemplate(
                tenantId: $event->tenantId,
                templateKey: $event->templateKey,
                templateVersion: $event->versionNumber,
            );

            return;
        }

        if ($event instanceof TenantBlueprintCloned) {
            $this->compiledCache->flushTenant($event->tenantId);
        }
    }
}

