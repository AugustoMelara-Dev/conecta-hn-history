<?php

declare(strict_types=1);

use App\Domain\TenantGovernance\Services\TenantOnboardingProgressService;

uses(Tests\TestCase::class);

it('routes consulting lead offer milestones to settings instead of service catalog', function (): void {
    $service = new TenantOnboardingProgressService();
    $method = new ReflectionMethod($service, 'manifestStateHref');
    $method->setAccessible(true);

    $href = (string) $method->invoke(
        $service,
        'consultoria-demo',
        'service',
        'consulting_leads',
        'services',
    );

    expect($href)
        ->toContain('/settings')
        ->not->toContain('/services');
});
