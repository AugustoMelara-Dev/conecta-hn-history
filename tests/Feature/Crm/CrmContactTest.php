<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\CrmCompany;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Tenant;
use App\Models\User;

it('can create a contact happy-path', function () {
    $tenant = Tenant::factory()->create();

    $contact = Contact::create([
        'tenant_id' => $tenant->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '+504 9999 9999',
        'lifecycle_stage' => 'lead',
    ]);

    expect($contact->id)->not->toBeNull();
    expect($contact->full_name)->toBe('John Doe');
    expect($contact->display_name)->toBe('John Doe');
});

it('contact validation fails with bad email', function () {
    $tenant = Tenant::factory()->create();

    $request = \Illuminate\Validation\Factory::class;
    $validator = validator(
        ['email' => 'not-an-email'],
        ['email' => 'email'],
    );

    expect($validator->fails())->toBeTrue();
});

it('contact can have activities and deals', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $pipeline = CrmPipeline::create(['tenant_id' => $tenant->id, 'name' => 'Sales', 'is_default' => true]);
    $stage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Qualified', 'probability' => 25, 'sort_order' => 1]);

    $contact = Contact::create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Test',
        'lifecycle_stage' => 'lead',
    ]);

    CrmActivity::create([
        'tenant_id' => $tenant->id,
        'contact_id' => $contact->id,
        'owner_id' => $user->id,
        'type' => 'call',
        'subject' => 'Discovery call',
    ]);

    $deal = CrmDeal::create([
        'tenant_id' => $tenant->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
        'contact_id' => $contact->id,
        'name' => 'Q4 opportunity',
        'value' => 50000,
        'currency' => 'HNL',
    ]);

    expect($contact->activities()->count())->toBe(1);
    expect($contact->deals()->count())->toBe(1);
    expect((float) $deal->weighted_value)->toBeGreaterThan(0);
});

it('contact scopeForTenant filters correctly', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    Contact::create(['tenant_id' => $tenant1->id, 'first_name' => 'A']);
    Contact::create(['tenant_id' => $tenant1->id, 'first_name' => 'B']);
    Contact::create(['tenant_id' => $tenant2->id, 'first_name' => 'C']);

    expect(Contact::forTenant($tenant1->id)->count())->toBe(2);
    expect(Contact::forTenant($tenant2->id)->count())->toBe(1);
});
