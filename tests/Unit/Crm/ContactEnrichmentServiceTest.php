<?php

declare(strict_types=1);

use App\Application\Crm\ContactEnrichmentService;
use App\Domain\Crm\ValueObjects\CrmMetrics;
use App\Models\Tenant;

it('ContactEnrichmentService enrich returns array structure', function () {
    $service = new ContactEnrichmentService();
    $result = $service->enrich('test@example.com');
    expect($result)->toHaveKeys(['job_title', 'company_name', 'confidence', 'raw']);
    expect($result['confidence'])->toBeGreaterThan(0);
});

it('ContactEnrichmentService applies enrichment to contact', function () {
    $tenant = Tenant::factory()->create();
    $contact = \App\Models\Contact::create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Test',
        'email' => 'test@example.com',
    ]);

    $service = new ContactEnrichmentService();
    $updated = $service->applyToContact($contact);

    expect($updated->job_title)->toBe('Director de Operaciones');
});

it('CrmMetrics value object exposes all metrics', function () {
    $metrics = new CrmMetrics(
        totalContacts: 100, totalCompanies: 20, totalDeals: 50,
        openDeals: 30, wonDeals: 15, lostDeals: 5,
        totalPipelineValue: 50000.0, weightedPipelineValue: 30000.0,
        wonValue: 25000.0, winRate: 75.0, avgDealValue: 1000.0,
        avgDaysToClose: 14, activitiesLast30Days: 200,
    );

    $array = $metrics->toArray();
    expect($array)->toHaveKeys(['total_contacts', 'total_deals', 'win_rate', 'avg_days_to_close']);
    expect($array['total_contacts'])->toBe(100);
});
