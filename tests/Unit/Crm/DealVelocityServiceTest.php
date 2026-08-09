<?php

declare(strict_types=1);

use App\Application\Crm\DealVelocityService;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Tenant;

it('DealVelocityService averageDaysPerStage returns structure', function () {
    $tenant = Tenant::factory()->create();
    $pipeline = CrmPipeline::create(['tenant_id' => $tenant->id, 'name' => 'Sales']);
    $stage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'New', 'sort_order' => 1]);

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
