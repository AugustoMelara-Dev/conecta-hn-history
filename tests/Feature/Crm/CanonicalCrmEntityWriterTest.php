<?php

declare(strict_types=1);

use App\Application\Crm\CanonicalCrmEntityWriter;
use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\CrmCompany;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
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
        'granted_permissions' => null,
        'status' => 'active',
        'accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

function canonicalWriterSpatiePermissionId(string $permission): int
{
    $permissionId = DB::table('permissions')
        ->where('name', $permission)
        ->where('guard_name', 'web')
        ->value('id');
    $permissionId ??= DB::table('permissions')->insertGetId([
        'name' => $permission,
        'guard_name' => 'web',
    ]);

    return (int) $permissionId;
}

function canonicalWriterGrantSpatie(User $user, string ...$permissions): void
{
    foreach ($permissions as $permission) {
        DB::table('model_has_permissions')->insert([
            'permission_id' => canonicalWriterSpatiePermissionId($permission),
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->id,
        ]);
    }
}

function canonicalWriterGrantSpatieRole(User $user, string $role, string ...$permissions): void
{
    $roleId = DB::table('roles')
        ->where('name', $role)
        ->where('guard_name', 'web')
        ->value('id');
    $roleId ??= DB::table('roles')->insertGetId([
        'name' => $role,
        'guard_name' => 'web',
    ]);
    DB::table('model_has_roles')->insert([
        'role_id' => $roleId,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->id,
    ]);

    foreach ($permissions as $permission) {
        DB::table('role_has_permissions')->insert([
            'permission_id' => canonicalWriterSpatiePermissionId($permission),
            'role_id' => $roleId,
        ]);
    }
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

function canonicalWriterActivity(Tenant $tenant, array $attributes = []): CrmActivity
{
    return CrmActivity::withoutGlobalScopes()->forceCreate(array_merge([
        'tenant_id' => $tenant->id,
        'type' => 'call',
    ], $attributes));
}

function canonicalWriterDeal(Tenant $tenant, string $key = 'pipeline'): int
{
    $pipelineId = DB::table('crm_pipelines')->insertGetId([
        'tenant_id' => $tenant->id,
        'name' => 'Pipeline',
        'key' => $key,
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

    return (int) DB::table('crm_deals')->insertGetId([
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
    canonicalWriterGrantSpatie($member, 'crm.companies.create');
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
    $ambiguousSeeded = false;
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
        $ambiguousSeeded = true;
    } catch (QueryException) {
        // A tenant/user uniqueness constraint may make an ambiguous membership
        // impossible to seed.
    }
    if ($ambiguousSeeded) {
        expect(fn () => $writer->createCompany((string) $tenant->id, (string) $duplicate->id, ['name' => 'Duplicate']))
            ->toThrow(AuthorizationException::class);
    }

    $foreign = canonicalWriterUser();
    ['tenant' => $otherTenant] = canonicalWriterTenant();
    canonicalWriterMembership($otherTenant, $foreign);
    expect(fn () => $writer->createCompany((string) $tenant->id, (string) $foreign->id, ['name' => 'Foreign']))
        ->toThrow(AuthorizationException::class);
});

it('authorizes an active member through a canonical Spatie role permission pivot', function (): void {
    ['tenant' => $tenant] = canonicalWriterTenant();
    $member = canonicalWriterUser();
    canonicalWriterMembership($tenant, $member);
    canonicalWriterGrantSpatieRole($member, 'crm-manager', 'crm.companies.create');

    expect(app(CanonicalCrmEntityWriter::class)->createCompany(
        (string) $tenant->id,
        (string) $member->id,
        ['name' => 'Role member company'],
    ))->toBeInstanceOf(CrmCompany::class);
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

    $dealId = canonicalWriterDeal($tenant);
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

it('denies an active member when only membership grants or role config provide the CRM permission', function (): void {
    ['tenant' => $tenant] = canonicalWriterTenant();
    $member = canonicalWriterUser();
    canonicalWriterMembership($tenant, $member, [
        'role' => 'owner',
        'granted_permissions' => json_encode(['crm.companies.create'], JSON_THROW_ON_ERROR),
    ]);

    expect(fn () => app(CanonicalCrmEntityWriter::class)->createCompany(
        (string) $tenant->id,
        (string) $member->id,
        ['name' => 'No Spatie assignment'],
    ))->toThrow(AuthorizationException::class);
});

it('validates bulk UUIDs before canonicalization and accepts case-insensitive duplicate IDs', function (): void {
    ['tenant' => $tenant, 'owner' => $owner] = canonicalWriterTenant();
    $contact = canonicalWriterContact($tenant);
    $writer = app(CanonicalCrmEntityWriter::class);

    expect(fn () => $writer->bulkAssignContactOwner(
        (string) $tenant->id,
        (string) $owner->id,
        [$contact->id, 123],
        null,
    ))->toThrow(InvalidArgumentException::class);

    expect($writer->bulkAssignContactOwner(
        (string) $tenant->id,
        (string) $owner->id,
        [$contact->id, strtoupper($contact->id)],
        null,
    ))->toBe(1);
});

it('rejects unknown attributes before any create or update write', function (): void {
    ['tenant' => $tenant, 'owner' => $owner] = canonicalWriterTenant();
    $writer = app(CanonicalCrmEntityWriter::class);
    $before = CrmCompany::query()->where('tenant_id', $tenant->id)->count();

    expect(fn () => $writer->createCompany((string) $tenant->id, (string) $owner->id, [
        'name' => 'Rejected',
        'tenant_id' => $tenant->id,
    ]))->toThrow(InvalidArgumentException::class);
    expect(CrmCompany::query()->where('tenant_id', $tenant->id)->count())->toBe($before);

    $company = canonicalWriterCompany($tenant, 'Stable');
    expect(fn () => $writer->updateCompany((string) $tenant->id, (string) $owner->id, (string) $company->id, [
        'unknown' => 'Rejected',
    ]))->toThrow(InvalidArgumentException::class);
    expect($company->fresh()->name)->toBe('Stable');
});

it('updates each entity only when its target belongs to the explicit tenant', function (): void {
    ['tenant' => $tenant, 'owner' => $owner] = canonicalWriterTenant();
    ['tenant' => $foreignTenant] = canonicalWriterTenant();
    $company = canonicalWriterCompany($tenant);
    $contact = canonicalWriterContact($tenant, ['company_id' => $company->id]);
    $activity = canonicalWriterActivity($tenant);
    $tag = Tag::withoutGlobalScopes()->forceCreate([
        'tenant_id' => $tenant->id,
        'name' => 'Original tag',
        'slug' => 'original-tag',
    ]);
    $foreignCompany = canonicalWriterCompany($foreignTenant);
    $foreignContact = canonicalWriterContact($foreignTenant);
    $foreignActivity = canonicalWriterActivity($foreignTenant);
    $foreignTag = Tag::withoutGlobalScopes()->forceCreate([
        'tenant_id' => $foreignTenant->id,
        'name' => 'Foreign tag',
        'slug' => 'foreign-tag',
    ]);
    $writer = app(CanonicalCrmEntityWriter::class);

    expect($writer->updateCompany((string) $tenant->id, (string) $owner->id, (string) $company->id, ['name' => 'Changed'])->name)
        ->toBe('Changed');
    expect($writer->updateContact((string) $tenant->id, (string) $owner->id, (string) $contact->id, ['first_name' => 'Changed'])->first_name)
        ->toBe('Changed');
    expect($writer->updateActivity((string) $tenant->id, (string) $owner->id, (string) $activity->id, ['subject' => 'Changed'])->subject)
        ->toBe('Changed');
    expect($writer->updateTag((string) $tenant->id, (string) $owner->id, (string) $tag->id, ['name' => 'Changed'])->name)
        ->toBe('Changed');

    expect(fn () => $writer->updateCompany((string) $tenant->id, (string) $owner->id, (string) $foreignCompany->id, ['name' => 'Nope']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $writer->updateContact((string) $tenant->id, (string) $owner->id, (string) $foreignContact->id, ['first_name' => 'Nope']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $writer->updateActivity((string) $tenant->id, (string) $owner->id, (string) $foreignActivity->id, ['subject' => 'Nope']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $writer->updateTag((string) $tenant->id, (string) $owner->id, (string) $foreignTag->id, ['name' => 'Nope']))
        ->toThrow(AuthorizationException::class);
});

it('rejects absent, foreign, pending, suspended, and revoked owners uniformly', function (): void {
    ['tenant' => $tenant, 'owner' => $owner] = canonicalWriterTenant();
    ['tenant' => $foreignTenant, 'owner' => $foreignOwner] = canonicalWriterTenant();
    $pending = canonicalWriterUser();
    canonicalWriterMembership($tenant, $pending, ['accepted_at' => null]);
    $suspended = canonicalWriterUser();
    canonicalWriterMembership($tenant, $suspended, ['suspended_at' => now()]);
    $revoked = canonicalWriterUser();
    canonicalWriterMembership($tenant, $revoked, ['revoked_at' => now()]);
    $writer = app(CanonicalCrmEntityWriter::class);
    $ownerIds = [$pending->id, $suspended->id, $revoked->id, $foreignOwner->id, (string) Str::uuid()];
    $messages = [];
    foreach ($ownerIds as $ownerId) {
        try {
            $writer->createContact((string) $tenant->id, (string) $owner->id, [
                'owner_id' => $ownerId,
                'first_name' => 'Rejected',
            ]);
        } catch (ValidationException $exception) {
            $messages[] = $exception->errors()['owner_id'][0] ?? '';
        }
    }

    expect(count($messages))->toBe(count($ownerIds))
        ->and(array_unique($messages))->toHaveCount(1);
});

it('rejects foreign and absent activity contact, company, and bigint deal references uniformly', function (): void {
    ['tenant' => $tenant, 'owner' => $owner] = canonicalWriterTenant();
    ['tenant' => $foreignTenant] = canonicalWriterTenant();
    $foreignContact = canonicalWriterContact($foreignTenant);
    $foreignCompany = canonicalWriterCompany($foreignTenant);
    $foreignDeal = canonicalWriterDeal($foreignTenant, 'foreign-pipeline');
    $writer = app(CanonicalCrmEntityWriter::class);
    $cases = [
        'contact_id' => [$foreignContact->id, (string) Str::uuid()],
        'company_id' => [$foreignCompany->id, (string) Str::uuid()],
        'deal_id' => [$foreignDeal, $foreignDeal + 1000000],
    ];

    foreach ($cases as $field => [$foreignId, $absentId]) {
        $message = function (mixed $reference) use ($tenant, $owner, $writer, $field): string {
            try {
                $writer->createActivity((string) $tenant->id, (string) $owner->id, [
                    $field => $reference,
                    'type' => 'call',
                ]);
            } catch (ValidationException $exception) {
                return $exception->errors()[$field][0] ?? '';
            }

            throw new LogicException('Expected an invalid activity reference.');
        };

        expect($message($foreignId))->toBe($message($absentId));
    }
});

it('isolates tag slugs by tenant and permits updating a tag to its existing slug', function (): void {
    ['tenant' => $tenant, 'owner' => $owner] = canonicalWriterTenant();
    ['tenant' => $otherTenant, 'owner' => $otherOwner] = canonicalWriterTenant();
    $writer = app(CanonicalCrmEntityWriter::class);
    $tag = $writer->createTag((string) $tenant->id, (string) $owner->id, [
        'name' => 'Shared',
        'slug' => 'shared',
    ]);

    expect($writer->createTag((string) $otherTenant->id, (string) $otherOwner->id, [
        'name' => 'Shared',
        'slug' => 'shared',
    ]))->toBeInstanceOf(Tag::class);
    expect(fn () => $writer->createTag((string) $tenant->id, (string) $owner->id, [
        'name' => 'Duplicate',
        'slug' => 'shared',
    ]))->toThrow(ValidationException::class);
    expect($writer->updateTag((string) $tenant->id, (string) $owner->id, (string) $tag->id, [
        'slug' => 'shared',
    ])->slug)->toBe('shared');
});

it('assigns bulk owners once per distinct canonical UUID and clears them atomically', function (): void {
    ['tenant' => $tenant, 'owner' => $owner] = canonicalWriterTenant();
    $assignee = canonicalWriterUser();
    canonicalWriterMembership($tenant, $assignee);
    $first = canonicalWriterContact($tenant, ['first_name' => 'First']);
    $second = canonicalWriterContact($tenant, ['first_name' => 'Second']);
    $writer = app(CanonicalCrmEntityWriter::class);

    expect($writer->bulkAssignContactOwner(
        (string) $tenant->id,
        (string) $owner->id,
        [$first->id, strtoupper($first->id), $second->id],
        strtoupper($assignee->id),
    ))->toBe(2);
    expect($first->fresh()->owner_id)->toBe($assignee->id)
        ->and($second->fresh()->owner_id)->toBe($assignee->id);

    expect($writer->bulkAssignContactOwner(
        (string) $tenant->id,
        (string) $owner->id,
        [$first->id, $second->id],
        null,
    ))->toBe(2);
    expect($first->fresh()->owner_id)->toBeNull()
        ->and($second->fresh()->owner_id)->toBeNull();

    expect(fn () => $writer->bulkAssignContactOwner(
        (string) $tenant->id,
        (string) $owner->id,
        [],
        null,
    ))->toThrow(InvalidArgumentException::class);
    expect(fn () => $writer->bulkAssignContactOwner(
        (string) $tenant->id,
        (string) $owner->id,
        ['not-a-uuid'],
        null,
    ))->toThrow(InvalidArgumentException::class);
});
