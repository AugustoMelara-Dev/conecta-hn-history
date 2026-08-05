<?php

declare(strict_types=1);

use App\Models\AiToolConfirmation;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\Lead;
use App\Models\Location;
use App\Models\OpeningHour;
use App\Models\TenantMembership;
use App\Models\TenantOnboardingState;

it('guards tenant ownership on tenant-owned CRM models', function (string $modelClass): void {
    $model = new $modelClass;

    expect($model->getFillable())->not->toContain('tenant_id');
    expect($model->getGuarded())->toContain('tenant_id');
})->with([
    AiToolConfirmation::class,
    CrmDeal::class,
    CrmPipeline::class,
    CrmStage::class,
    CustomerNote::class,
    CustomerProfile::class,
    CustomerTask::class,
    Lead::class,
    Location::class,
    OpeningHour::class,
    TenantMembership::class,
    TenantOnboardingState::class,
]);
