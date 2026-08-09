<?php

declare(strict_types=1);

use App\Application\Crm\BulkOperationsService;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;

it('BulkOperationsService can assign owner to multiple contacts', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id]);

    $contacts = collect([
        Contact::create(['tenant_id' => $tenant->id, 'first_name' => 'A']),
        Contact::create(['tenant_id' => $tenant->id, 'first_name' => 'B']),
        Contact::create(['tenant_id' => $tenant->id, 'first_name' => 'C']),
    ]);

    $count = (new BulkOperationsService)->bulkAssignOwner(
        $contacts->pluck('id')->toArray(),
        $owner->id,
        $tenant->id,
    );

    expect($count)->toBe(3);
    foreach ($contacts as $c) {
        $c->refresh();
        expect($c->owner_id)->toBe($owner->id);
    }
});

it('BulkOperationsService bulk update lifecycle stage', function () {
    $tenant = Tenant::factory()->create();

    $contacts = collect([
        Contact::create(['tenant_id' => $tenant->id, 'first_name' => 'A', 'lifecycle_stage' => 'lead']),
        Contact::create(['tenant_id' => $tenant->id, 'first_name' => 'B', 'lifecycle_stage' => 'lead']),
    ]);

    $count = (new BulkOperationsService)->bulkUpdateLifecycleStage(
        $contacts->pluck('id')->toArray(),
        'qualified',
        $tenant->id,
    );

    expect($count)->toBe(2);
    expect($contacts->first()->fresh()->lifecycle_stage)->toBe('qualified');
});

it('BulkOperationsService bulk add tag works idempotently', function () {
    $tenant = Tenant::factory()->create();

    $contact = Contact::create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Test',
        'tags' => ['vip'],
    ]);

    $service = new BulkOperationsService;

    // Adding same tag twice should only count once
    $count1 = $service->bulkAddTag([$contact->id], 'vip', $tenant->id);
    $count2 = $service->bulkAddTag([$contact->id], 'new-tag', $tenant->id);

    expect($count1)->toBe(0);
    expect($count2)->toBe(1);

    $contact->refresh();
    expect($contact->tags)->toContain('vip');
    expect($contact->tags)->toContain('new-tag');
});
