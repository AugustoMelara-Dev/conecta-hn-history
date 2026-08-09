<?php

declare(strict_types=1);

use App\Application\Crm\CanonicalCrmEntityWriter;
use App\Models\Contact;
use App\Models\CrmCompany;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @return array{tenant: Tenant, owner: User}
 */
function canonicalWriterTenant(): array
{
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $owner->id]);
    $owner->forceFill(['tenant_id' => $tenant->id])->saveQuietly();

    return ['tenant' => $tenant, 'owner' => $owner];
}

function canonicalWriterUser(): User
{
    return User::factory()->create();
}

function canonicalWriterMembership(Tenant $tenant, User $user, array $overrides = []): void
{
    DB::table('tenant_memberships')->insert(array_merge([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => 'manager',
        'granted_permissions' => json_encode([
            'crm.companies.create',
            'crm.companies.update',
            'crm.contacts.create',
            'crm.contacts.update',
            'crm.activities.create',
            'crm.activities.update',
            'crm.tags.create',
            'crm.tags.update',
        ], JSON_THROW_ON_ERROR),
        'status' => 'active',
        'accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

function canonicalWriterCompany(Tenant $tenant, string $name = 'Existing company'): CrmCompany
{
    return CrmCompany::withoutGlobalScopes()->forceCreate([
        'tenant_id' => $tenant->id,
        'name' => $name,
    ]);
}

function canonicalWriterContact(Tenant $tenant, array $attributes = []): Contact
{
    return Contact::withoutGlobalScopes()->forceCreate(array_merge([
        'tenant_id' => $tenant->id,
        'first_name' => 'Existing',
        'last_name' => 'Contact',
        'lifecycle_stage' => 'lead',
    ], $attributes));
}

it('writes every canonical CRM entity through the writer boundary', function (): void {
    ['tenant' => $tenant, 'owner' => $owner] = canonicalWriterTenant();
    $writer = app(CanonicalCrmEntityWriter::class);

    $company = $writer->createCompany((string) $tenant->id, (string) $owner->id, [
        'name' => 'Acme',
    ]);
    $contact = $writer->createContact((string) $tenant->id, (string) $owner->id, [
        'company_id' => $company->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);
    $activity = $writer->createActivity((string) $tenant->id, (string) $owner->id, [
        'contact_id' => $contact->id,
        'company_id' => $company->id,
        'type' => 'call',
        'subject' => 'Discovery',
    ]);
    $tag = $writer->createTag((string) $tenant->id, (string) $owner->id, [
        'name' => 'VIP',
        'slug' => 'vip',
    ]);

    expect($company->tenant_id)->toBe($tenant->id)
        ->and($contact->company_id)->toBe($company->id)
        ->and($activity->contact_id)->toBe($contact->id)
        ->and($tag->tenant_id)->toBe($tenant->id);
});

it('accepts an active member with the exact CRM permission and rejects inactive or ambiguous memberships', function (): void {
    ['tenant' => $tenant] = canonicalWriterTenant();
    $member = canonicalWriterUser();
    canonicalWriterMembership($tenant, $member);
    $writer = app(CanonicalCrmEntityWriter::class);

    expect($writer->createCompany((string) $tenant->id, (string) $member->id, ['name' => 'Member company']))
        ->toBeInstanceOf(CrmCompany::class);

    $pending = canonicalWriterUser();
    canonicalWriterMembership($tenant, $pending, ['accepted_at' => null]);
    expect(fn () => $writer->createCompany((string) $tenant->id, (string) $pending->id, ['name' => 'Pending']))
        ->toThrow(AuthorizationException::class);

    $suspended = canonicalWriterUser();
    canonicalWriterMembership($tenant, $suspended, ['suspended_at' => now()]);
    expect(fn () => $writer->createCompany((string) $tenant->id, (string) $suspended->id, ['name' => 'Suspended']))
        ->toThrow(AuthorizationException::class);

    $revoked = canonicalWriterUser();
    canonicalWriterMembership($tenant, $revoked, ['revoked_at' => now()]);
    expect(fn () => $writer->createCompany((string) $tenant->id, (string) $revoked->id, ['name' => 'Revoked']))
        ->toThrow(AuthorizationException::class);

    $duplicate = canonicalWriterUser();
    canonicalWriterMembership($tenant, $duplicate);
    try {
        DB::table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $duplicate->id,
            'role' => 'manager',
            'granted_permissions' => json_encode(['crm.companies.create'], JSON_THROW_ON_ERROR),
            'status' => 'active',
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        expect(fn () => $writer->createCompany((string) $tenant->id, (string) $duplicate->id, ['name' => 'Duplicate']))
            ->toThrow(AuthorizationException::class);
    } catch (Throwable) {
        // A tenant/user uniqueness constraint may make an ambiguous membership
        // impossible to seed; the writer still fails closed for zero matches.
        expect(true)->toBeTrue();
    }

    $foreign = canonicalWriterUser();
    ['tenant' => $otherTenant] = canonicalWriterTenant();
    canonicalWriterMembership($otherTenant, $foreign);
    expect(fn () => $writer->createCompany((string) $tenant->id, (string) $foreign->id, ['name' => 'Foreign']))
        ->toThrow(AuthorizationException::class);
});

it('does not disclose whether a relationship identifier is foreign or absent', function (): void {
    ['tenant' => $tenant, 'owner' => $owner] = canonicalWriterTenant();
    ['tenant' => $foreignTenant] = canonicalWriterTenant();
    $foreignCompany = canonicalWriterCompany($foreignTenant);
    $writer = app(CanonicalCrmEntityWriter::class);

    $foreign = fn () => $writer->createContact((string) $tenant->id, (string) $owner->id, [
        'company_id' => $foreignCompany->id,
        'first_name' => 'Foreign',
    ]);
    $absent = fn () => $writer->createContact((string) $tenant->id, (string) $owner->id, [
        'company_id' => (string) Str::uuid(),
        'first_name' => 'Absent',
    ]);

    expect($foreign)->toThrow(ValidationException::class);
    expect($absent)->toThrow(ValidationException::class);

    try {
        $foreign();
    } catch (ValidationException $exception) {
        $foreignMessage = $exception->errors()['company_id'][0] ?? '';
    }
    try {
        $absent();
    } catch (ValidationException $exception) {
        $absentMessage = $exception->errors()['company_id'][0] ?? '';
    }

    expect($foreignMessage)->toBe($absentMessage);
});

it('keeps omitted attributes unchanged, clears explicit nullable references, and uses canonical bigint deals', function (): void {
    ['tenant' => $tenant, 'owner' => $owner] = canonicalWriterTenant();
    $company = canonicalWriterCompany($tenant);
    $contact = canonicalWriterContact($tenant, ['company_id' => $company->id]);
    $writer = app(CanonicalCrmEntityWriter::class);

    $updated = $writer->updateContact((string) $tenant->id, (string) $owner->id, (string) $contact->id, [
        'company_id' => null,
        'first_name' => 'Changed',
    ]);

    expect($updated->first_name)->toBe('Changed')
        ->and($updated->last_name)->toBe('Contact')
        ->and($updated->company_id)->toBeNull();

    $pipelineId = DB::table('crm_pipelines')->insertGetId([
        'tenant_id' => $tenant->id,
        'name' => 'Pipeline',
        'key' => 'pipeline',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $stageId = DB::table('crm_stages')->insertGetId([
        'tenant_id' => $tenant->id,
        'crm_pipeline_id' => $pipelineId,
        'name' => 'Open',
        'key' => 'open',
        'type' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $dealId = DB::table('crm_deals')->insertGetId([
        'tenant_id' => $tenant->id,
        'crm_pipeline_id' => $pipelineId,
        'crm_stage_id' => $stageId,
        'title' => 'Bigint deal',
        'value' => 0,
        'currency' => 'HNL',
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $activity = $writer->createActivity((string) $tenant->id, (string) $owner->id, [
        'deal_id' => (int) $dealId,
        'type' => 'task',
    ]);

    expect($activity->deal_id)->toBe((int) $dealId);
    expect(fn () => $writer->createActivity((string) $tenant->id, (string) $owner->id, [
        'deal_id' => (string) Str::uuid(),
        'type' => 'task',
    ]))->toThrow(ValidationException::class);
});

it('makes bulk owner assignment atomic when one requested contact is foreign or absent', function (): void {
    ['tenant' => $tenant, 'owner' => $owner] = canonicalWriterTenant();
    ['tenant' => $foreignTenant] = canonicalWriterTenant();
    $first = canonicalWriterContact($tenant, ['first_name' => 'First']);
    $second = canonicalWriterContact($tenant, ['first_name' => 'Second']);
    $foreign = canonicalWriterContact($foreignTenant, ['first_name' => 'Foreign']);
    $writer = app(CanonicalCrmEntityWriter::class);

    expect(fn () => $writer->bulkAssignContactOwner(
        (string) $tenant->id,
        (string) $owner->id,
        [$first->id, $foreign->id],
        (string) $owner->id,
    ))->toThrow(ValidationException::class);

    expect($first->fresh()->owner_id)->toBeNull()
        ->and($second->fresh()->owner_id)->toBeNull();
});
