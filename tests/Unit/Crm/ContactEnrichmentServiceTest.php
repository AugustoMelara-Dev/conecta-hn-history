<?php

declare(strict_types=1);

use App\Application\Crm\ContactEnrichmentService;
use App\Domain\Crm\ValueObjects\CrmMetrics;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('ContactEnrichmentService enrich returns array structure', function () {
    $service = app(ContactEnrichmentService::class);
    $result = $service->enrich('test@example.com');
    expect($result)->toHaveKeys(['job_title', 'company_name', 'confidence', 'raw']);
    expect($result['confidence'])->toBeGreaterThan(0);
});

it('ContactEnrichmentService applies enrichment to contact', function () {
    $owner = User::factory()->tenantOwner()->create();
    $tenant = Tenant::factory()->forUser($owner)->create();
    $contact = Contact::create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Test',
        'email' => 'test@example.com',
    ]);

    $service = app(ContactEnrichmentService::class);
    $updated = $service->applyToContact($contact, (string) $tenant->id, (string) $owner->id);

    expect($updated->job_title)->toBe('Director de Operaciones');
});

it('ContactEnrichmentService rejects enrichment when explicit tenant authority is foreign', function () {
    $owner = User::factory()->tenantOwner()->create();
    $tenant = Tenant::factory()->forUser($owner)->create();
    $foreignTenant = Tenant::factory()->create();
    $contact = Contact::create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Protected',
        'email' => 'protected@example.com',
        'job_title' => 'Before enrichment',
    ]);

    $service = app(ContactEnrichmentService::class);

    expect(fn () => $service->applyToContact(
        $contact,
        (string) $foreignTenant->id,
        (string) $owner->id,
    ))->toThrow(AuthorizationException::class);

    expect($contact->fresh()->job_title)->toBe('Before enrichment');
});

it('ContactEnrichmentService rejects missing explicit authority identifiers', function () {
    $owner = User::factory()->tenantOwner()->create();
    $tenant = Tenant::factory()->forUser($owner)->create();
    $contact = Contact::create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Protected',
        'email' => 'protected@example.com',
        'job_title' => 'Before enrichment',
    ]);

    $service = app(ContactEnrichmentService::class);

    expect(fn () => $service->applyToContact(
        $contact,
        '',
        (string) $owner->id,
    ))->toThrow(InvalidArgumentException::class);

    expect($contact->fresh()->job_title)->toBe('Before enrichment');
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
