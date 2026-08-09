<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\DealStatus;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a deal and move it through stages', function () {
    $tenant = Tenant::factory()->create();
    $pipeline = CrmPipeline::query()->withoutGlobalScopes()->forceCreate([
        'tenant_id' => $tenant->id,
        'name' => 'Sales',
        'key' => 'sales',
        'is_default' => true,
        'is_active' => true,
    ]);
    $stage1 = CrmStage::query()->withoutGlobalScopes()->forceCreate([
        'tenant_id' => $tenant->id,
        'crm_pipeline_id' => $pipeline->id,
        'name' => 'New',
        'key' => 'new',
        'type' => CrmStage::TYPE_OPEN,
        'probability' => 10,
        'sort_order' => 1,
    ]);
    $stage2 = CrmStage::query()->withoutGlobalScopes()->forceCreate([
        'tenant_id' => $tenant->id,
        'crm_pipeline_id' => $pipeline->id,
        'name' => 'Qualified',
        'key' => 'qualified',
        'type' => CrmStage::TYPE_OPEN,
        'probability' => 50,
        'sort_order' => 2,
    ]);

    $deal = CrmDeal::query()->withoutGlobalScopes()->forceCreate([
        'tenant_id' => $tenant->id,
        'crm_pipeline_id' => $pipeline->id,
        'crm_stage_id' => $stage1->id,
        'title' => 'Big opportunity',
        'value' => 100000,
        'currency' => 'HNL',
        'status' => DealStatus::Open->value,
    ]);

    expect($deal->isOpen())->toBeTrue();
    expect($deal->title)->toBe('Big opportunity');
    expect((int) $deal->crm_stage_id)->toBe((int) $stage1->id);

    $deal->update([
        'crm_stage_id' => $stage2->id,
    ]);

    $deal->refresh();
    expect($deal->isOpen())->toBeTrue();
    expect((int) $deal->crm_stage_id)->toBe((int) $stage2->id);

    $deal->update([
        'status' => DealStatus::Won->value,
        'closed_at' => now(),
    ]);

    $deal->refresh();
    expect($deal->isWon())->toBeTrue();
});

it('deal scopes work: open, won, lost', function () {
    $tenant = Tenant::factory()->create();
    $pipeline = CrmPipeline::query()->withoutGlobalScopes()->forceCreate([
        'tenant_id' => $tenant->id,
        'name' => 'Sales',
        'key' => 'sales',
        'is_default' => true,
        'is_active' => true,
    ]);
    $stage = CrmStage::query()->withoutGlobalScopes()->forceCreate([
        'tenant_id' => $tenant->id,
        'crm_pipeline_id' => $pipeline->id,
        'name' => 'New',
        'key' => 'new',
        'type' => CrmStage::TYPE_OPEN,
        'probability' => 10,
        'sort_order' => 1,
    ]);

    foreach ([
        ['title' => 'Open 1', 'value' => 100, 'status' => 'open'],
        ['title' => 'Won 1', 'value' => 200, 'status' => 'won'],
        ['title' => 'Lost 1', 'value' => 50, 'status' => 'lost'],
    ] as $deal) {
        CrmDeal::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->id,
            'crm_pipeline_id' => $pipeline->id,
            'crm_stage_id' => $stage->id,
            ...$deal,
        ]);
    }

    expect(CrmDeal::forTenant($tenant->id)->open()->count())->toBe(1);
    expect(CrmDeal::forTenant($tenant->id)->won()->count())->toBe(1);
    expect(CrmDeal::forTenant($tenant->id)->lost()->count())->toBe(1);
});
