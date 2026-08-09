<?php

declare(strict_types=1);

use App\Application\Crm\WinLossAnalysisService;
use App\Models\Tenant;

it('WinLossAnalysisService topLostReasons returns array', function () {
    $tenant = Tenant::factory()->create();
    $service = new WinLossAnalysisService;
    $result = $service->topLostReasons($tenant->id);
    expect($result)->toBeArray();
});

it('WinLossAnalysisService winRate returns float', function () {
    $tenant = Tenant::factory()->create();
    $service = new WinLossAnalysisService;
    expect($service->winRate($tenant->id))->toBeFloat();
});

it('WinLossAnalysisService monthlyWinLoss returns array', function () {
    $tenant = Tenant::factory()->create();
    $service = new WinLossAnalysisService;
    $result = $service->monthlyWinLoss($tenant->id, 6);
    expect($result)->toBeArray();
});
