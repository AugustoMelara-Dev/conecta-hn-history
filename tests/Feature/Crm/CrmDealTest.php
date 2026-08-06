<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\DealStatus;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Tenant;

it('can create a deal and move it through stages', function () {
    $tenant = Tenant::factory()->create();
    $pipeline = CrmPipeline::create(['tenant_id' => $tenant->id, 'name' => 'Sales', 'is_default' => true]);
    $stage1 = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'New', 'probability' => 10, 'sort_order' => 1]);
    $stage2 = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Qualified', 'probability' => 50, 'sort_order' => 2]);

    $deal = CrmDeal::create([
        'tenant_id' => $tenant->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage1->id,
        'name' => 'Big opportunity',
        'value' => 100000,
        'currency' => 'HNL',
        'probability' => 10,
    ]);

    expect($deal->isOpen())->toBeTrue();
    expect((float) $deal->weighted_value)->toBe(10000.0);

    $deal->update([
        'stage_id' => $stage2->id,
        'probability' => 50,
    ]);

    $deal->refresh();
    expect($deal->isOpen())->toBeTrue();
    expect((float) $deal->weighted_value)->toBe(50000.0);

    $deal->update([
        'status' => DealStatus::Won->value,
        'won_at' => now(),
        'probability' => 100,
    ]);

    $deal->refresh();
    expect($deal->isWon())->toBeTrue();
});

it('deal scopes work: open, won, lost', function () {
    $tenant = Tenant::factory()->create();
    $pipeline = CrmPipeline::create(['tenant_id' => $tenant->id, 'name' => 'Sales', 'is_default' => true]);
    $stage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'New', 'probability' => 10, 'sort_order' => 1]);

    CrmDeal::create(['tenant_id' => $tenant->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'name' => 'Open 1', 'value' => 100, 'status' => 'open']);
    CrmDeal::create(['tenant_id' => $tenant->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'name' => 'Won 1', 'value' => 200, 'status' => 'won']);
    CrmDeal::create(['tenant_id' => $tenant->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'name' => 'Lost 1', 'value' => 50, 'status' => 'lost']);

    expect(CrmDeal::forTenant($tenant->id)->open()->count())->toBe(1);
    expect(CrmDeal::forTenant($tenant->id)->won()->count())->toBe(1);
    expect(CrmDeal::forTenant($tenant->id)->lost()->count())->toBe(1);
});
