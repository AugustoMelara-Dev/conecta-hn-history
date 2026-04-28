<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicForms;

use App\Events\DynamicForms\TenantBlueprintCloned;
use App\Models\Dynamic\DynamicPrintTemplate;
use App\Models\Dynamic\EntityDefinition;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DynamicForms\EntityDefinitionClonerService;
use App\Services\DynamicForms\PrintTemplateClonerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class BlueprintCloneServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_clones_global_entity_blueprint_to_tenant_and_dispatches_event(): void
    {
        Event::fake([TenantBlueprintCloned::class]);

        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();

        $source = EntityDefinition::create([
            'tenant_id' => null,
            'owner_scope' => 'superadmin',
            'module_key' => 'crm',
            'entity_key' => 'lead',
            'name' => 'Lead',
            'status' => 'published',
            'allow_tenant_overrides' => true,
            'is_locked' => false,
        ]);

        $cloned = app(EntityDefinitionClonerService::class)->cloneForTenant($source, (string) $tenant->getKey(), (string) $owner->getKey());

        $this->assertSame((string) $tenant->getKey(), (string) $cloned->tenant_id);
        $this->assertSame((string) $source->getKey(), (string) $cloned->source_entity_definition_id);

        Event::assertDispatched(TenantBlueprintCloned::class, function (TenantBlueprintCloned $event) use ($source, $cloned, $tenant): bool {
            return $event->blueprintType === 'entity_definition'
                && $event->sourceId === (string) $source->getKey()
                && $event->targetId === (string) $cloned->getKey()
                && $event->tenantId === (string) $tenant->getKey();
        });
    }

    public function test_it_blocks_cloning_of_tenant_scoped_print_template(): void
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();

        $source = DynamicPrintTemplate::create([
            'tenant_id' => $tenant->getKey(),
            'entity_definition_id' => null,
            'owner_scope' => 'tenant',
            'template_key' => 'tenant-only-template',
            'action_key' => 'default',
            'name' => 'Tenant Template',
            'blocks_json' => [
                ['type' => 'paragraph', 'content' => 'Hola'],
            ],
            'status' => 'draft',
            'is_locked' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(PrintTemplateClonerService::class)->cloneForTenant($source, (string) $tenant->getKey(), (string) $owner->getKey());
    }
}
