<?php

declare(strict_types=1);

use App\Application\Crm\DealVelocityService;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('DealVelocityService averageDaysPerStage returns structure', function () {
    $tenant = Tenant::factory()->create();
    $pipeline = (new CrmPipeline)->forceFill([
        'tenant_id' => $tenant->id,
        'name' => 'Sales',
        'key' => 'sales',
    ]);
    $pipeline->save();
    $stage = (new CrmStage)->forceFill([
        'tenant_id' => $tenant->id,
        'crm_pipeline_id' => $pipeline->id,
        'name' => 'New',
        'key' => 'new',
        'sort_order' => 1,
    ]);
    $stage->save();

    $service = new DealVelocityService;
    $result = $service->averageDaysPerStage($tenant->id, $pipeline->id);
    expect($result)->toBeArray();
});

it('DealVelocityService averageDaysToClose returns float', function () {
    $tenant = Tenant::factory()->create();
    $service = new DealVelocityService;
    $result = $service->averageDaysToClose($tenant->id);
    expect($result)->toBeFloat();
});

it('DealVelocityService bottlenecks returns array', function () {
    $tenant = Tenant::factory()->create();
    $service = new DealVelocityService;
    $result = $service->bottlenecks($tenant->id);
    expect($result)->toBeArray();
});
