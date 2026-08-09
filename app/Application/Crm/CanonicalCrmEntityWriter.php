<?php

declare(strict_types=1);

namespace App\Application\Crm;

use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\CrmCompany;
use App\Models\CrmDeal;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The single application boundary for authenticated CRM entity mutations.
 */
final readonly class CanonicalCrmEntityWriter
{
    private const array COMPANY_ATTRIBUTES = [
        'name', 'industry', 'size', 'website', 'country', 'city', 'tax_id', 'phone', 'notes', 'tags', 'custom_fields',
    ];

    private const array CONTACT_ATTRIBUTES = [
        'company_id', 'owner_id', 'first_name', 'last_name', 'email', 'phone', 'job_title', 'source',
        'lifecycle_stage', 'last_contact_at', 'tags', 'custom_fields',
    ];

    private const array ACTIVITY_ATTRIBUTES = [
        'contact_id', 'deal_id', 'company_id', 'owner_id', 'type', 'subject', 'body', 'due_at',
        'completed_at', 'priority', 'outcome',
    ];

    private const array TAG_ATTRIBUTES = ['name', 'slug', 'color', 'type'];

    private const string REFERENCE_MESSAGE = 'The selected reference is invalid for this tenant.';

    private const string OWNER_MESSAGE = 'The selected owner is invalid for this tenant.';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createCompany(string $tenantId, string $actorId, array $attributes): CrmCompany
    {
        $this->assertAttributes($attributes, self::COMPANY_ATTRIBUTES);
        $this->assertIdentity($tenantId, $actorId);

        return DB::transaction(function () use ($tenantId, $actorId, $attributes): CrmCompany {
            $this->authority($tenantId, $actorId, 'crm.companies.create');
            $company = new CrmCompany;
            $company->forceFill(['tenant_id' => $tenantId, ...$attributes])->save();

            return $company;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateCompany(string $tenantId, string $actorId, string $companyId, array $attributes): CrmCompany
    {
        $this->assertAttributes($attributes, self::COMPANY_ATTRIBUTES);
        $this->assertIdentity($tenantId, $actorId);
        $this->assertUuid($companyId, 'company_id');

        return DB::transaction(function () use ($tenantId, $actorId, $companyId, $attributes): CrmCompany {
            $this->authority($tenantId, $actorId, 'crm.companies.update');
            $company = $this->target(CrmCompany::class, $tenantId, $companyId, true);
            $company->forceFill($attributes)->save();

            return $this->target(CrmCompany::class, $tenantId, $companyId, true);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createContact(string $tenantId, string $actorId, array $attributes): Contact
    {
        $this->assertAttributes($attributes, self::CONTACT_ATTRIBUTES);
        $this->assertIdentity($tenantId, $actorId);

        return DB::transaction(function () use ($tenantId, $actorId, $attributes): Contact {
            $this->authority($tenantId, $actorId, 'crm.contacts.create');
            $attributes = $this->contactReferences($tenantId, $attributes);
            $contact = new Contact;
            $contact->forceFill(['tenant_id' => $tenantId, ...$attributes])->save();

            return $contact;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateContact(string $tenantId, string $actorId, string $contactId, array $attributes): Contact
    {
        $this->assertAttributes($attributes, self::CONTACT_ATTRIBUTES);
        $this->assertIdentity($tenantId, $actorId);
        $this->assertUuid($contactId, 'contact_id');

        return DB::transaction(function () use ($tenantId, $actorId, $contactId, $attributes): Contact {
            $this->authority($tenantId, $actorId, 'crm.contacts.update');
            $contact = $this->target(Contact::class, $tenantId, $contactId, true);
            $contact->forceFill($this->contactReferences($tenantId, $attributes))->save();

            return $this->target(Contact::class, $tenantId, $contactId, true);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createActivity(string $tenantId, string $actorId, array $attributes): CrmActivity
    {
        $this->assertAttributes($attributes, self::ACTIVITY_ATTRIBUTES);
        $this->assertIdentity($tenantId, $actorId);

        return DB::transaction(function () use ($tenantId, $actorId, $attributes): CrmActivity {
            $this->authority($tenantId, $actorId, 'crm.activities.create');
            $attributes = $this->activityReferences($tenantId, $attributes);
            $activity = new CrmActivity;
            $activity->forceFill(['tenant_id' => $tenantId, ...$attributes])->save();

            return $activity;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateActivity(string $tenantId, string $actorId, string $activityId, array $attributes): CrmActivity
    {
        $this->assertAttributes($attributes, self::ACTIVITY_ATTRIBUTES);
        $this->assertIdentity($tenantId, $actorId);
        $this->assertUuid($activityId, 'activity_id');

        return DB::transaction(function () use ($tenantId, $actorId, $activityId, $attributes): CrmActivity {
            $this->authority($tenantId, $actorId, 'crm.activities.update');
            $activity = $this->target(CrmActivity::class, $tenantId, $activityId);
            $activity->forceFill($this->activityReferences($tenantId, $attributes))->save();

            return $this->target(CrmActivity::class, $tenantId, $activityId);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTag(string $tenantId, string $actorId, array $attributes): Tag
    {
        $this->assertAttributes($attributes, self::TAG_ATTRIBUTES);
        $this->assertIdentity($tenantId, $actorId);

        return DB::transaction(function () use ($tenantId, $actorId, $attributes): Tag {
            $this->authority($tenantId, $actorId, 'crm.tags.create');
            $this->assertUniqueTagSlug($tenantId, $attributes['slug'] ?? null);
            $tag = new Tag;
            $tag->forceFill(['tenant_id' => $tenantId, ...$attributes])->save();

            return $tag;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateTag(string $tenantId, string $actorId, string $tagId, array $attributes): Tag
    {
        $this->assertAttributes($attributes, self::TAG_ATTRIBUTES);
        $this->assertIdentity($tenantId, $actorId);
        $this->assertUuid($tagId, 'tag_id');

        return DB::transaction(function () use ($tenantId, $actorId, $tagId, $attributes): Tag {
            $this->authority($tenantId, $actorId, 'crm.tags.update');
            $tag = $this->target(Tag::class, $tenantId, $tagId);
            $this->assertUniqueTagSlug($tenantId, $attributes['slug'] ?? null, $tagId);
            $tag->forceFill($attributes)->save();

            return $this->target(Tag::class, $tenantId, $tagId);
        }, 3);
    }

    /**
     * @param  list<string>  $contactIds
     */
    public function bulkAssignContactOwner(
        string $tenantId,
        string $actorId,
        array $contactIds,
        ?string $ownerId,
    ): int {
        $this->assertIdentity($tenantId, $actorId);
        if ($contactIds === []) {
            throw new \InvalidArgumentException('Contact IDs must not be empty.');
        }

        $contactIds = array_values(array_unique($contactIds));
        foreach ($contactIds as $contactId) {
            if (! is_string($contactId) || ! Str::isUuid($contactId)) {
                throw new \InvalidArgumentException('Contact IDs must be UUIDs.');
            }
        }
        if ($ownerId !== null) {
            $this->assertUuid($ownerId, 'owner_id');
        }

        return DB::transaction(function () use ($tenantId, $actorId, $contactIds, $ownerId): int {
            $this->authority($tenantId, $actorId, 'crm.contacts.update');
            if ($ownerId !== null) {
                $this->resolveOwner($tenantId, $ownerId);
            }

            $contacts = Contact::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->whereIn('id', $contactIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($contacts->count() !== count($contactIds)) {
                throw ValidationException::withMessages([
                    'contact_ids' => [self::REFERENCE_MESSAGE],
                ]);
            }

            foreach ($contacts as $contact) {
                $contact->forceFill(['owner_id' => $ownerId])->save();
            }

            return count($contactIds);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $allowed
     */
    private function assertAttributes(array $attributes, array $allowed): void
    {
        $unknown = array_diff(array_keys($attributes), $allowed);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unknown CRM entity attributes: '.implode(', ', $unknown).'.');
        }
    }

    private function assertIdentity(string $tenantId, string $actorId): void
    {
        if (! Str::isUuid($tenantId) || ! Str::isUuid($actorId)) {
            throw new \InvalidArgumentException('CRM authority requires UUID tenant and actor identifiers.');
        }
    }

    private function assertUuid(string $value, string $field): void
    {
        if (! Str::isUuid($value)) {
            throw ValidationException::withMessages([$field => [self::REFERENCE_MESSAGE]]);
        }
    }

    private function authority(string $tenantId, string $actorId, string $permission): void
    {
        $tenant = Tenant::query()
            ->withoutGlobalScopes()
            ->whereKey($tenantId)
            ->lockForUpdate()
            ->first();
        $actor = User::query()
            ->withoutGlobalScopes()
            ->whereKey($actorId)
            ->lockForUpdate()
            ->first();
        if (! $tenant instanceof Tenant || ! $actor instanceof User) {
            throw new AuthorizationException('Actor cannot mutate CRM entities for this tenant.');
        }

        if ((string) $tenant->user_id === (string) $actor->id) {
            return;
        }

        $memberships = DB::table('tenant_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $actorId)
            ->where('status', 'active')
            ->whereNotNull('accepted_at')
            ->whereNull('suspended_at')
            ->whereNull('revoked_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($memberships->count() !== 1 || ! $this->membershipAllows($memberships->first(), $permission, $actor)) {
            throw new AuthorizationException('Actor cannot mutate CRM entities for this tenant.');
        }
    }

    private function membershipAllows(object $membership, string $permission, User $actor): bool
    {
        $role = $membership->role instanceof \BackedEnum
            ? $membership->role->value
            : (string) $membership->role;
        $rolePermissions = config("rbac.roles.{$role}", []);
        if (in_array($permission, (array) $rolePermissions, true)) {
            return true;
        }

        $granted = $membership->granted_permissions;
        if (is_string($granted)) {
            $granted = json_decode($granted, true);
        }
        if (is_array($granted) && in_array($permission, $granted, true)) {
            return true;
        }

        return $this->spatieAllows($actor, $permission);
    }

    private function spatieAllows(User $actor, string $permission): bool
    {
        if (! Schema::hasTable('permissions')
            || ! Schema::hasTable('model_has_permissions')
            || ! Schema::hasTable('model_has_roles')
            || ! Schema::hasTable('role_has_permissions')) {
            return false;
        }

        $permissionId = DB::table('permissions')
            ->where('name', $permission)
            ->where('guard_name', 'web')
            ->value('id');
        if ($permissionId === null) {
            return false;
        }

        if (DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->where('model_type', User::class)
            ->where('model_id', $actor->getKey())
            ->exists()) {
            return true;
        }

        return DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $actor->getKey())
            ->join('role_has_permissions', 'model_has_roles.role_id', '=', 'role_has_permissions.role_id')
            ->where('role_has_permissions.permission_id', $permissionId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function contactReferences(string $tenantId, array $attributes): array
    {
        if (array_key_exists('company_id', $attributes) && $attributes['company_id'] !== null) {
            $attributes['company_id'] = $this->resolveReference(CrmCompany::class, $tenantId, $attributes['company_id'], 'company_id', true);
        }
        if (array_key_exists('owner_id', $attributes) && $attributes['owner_id'] !== null) {
            $attributes['owner_id'] = $this->resolveOwner($tenantId, $attributes['owner_id']);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function activityReferences(string $tenantId, array $attributes): array
    {
        if (array_key_exists('contact_id', $attributes) && $attributes['contact_id'] !== null) {
            $attributes['contact_id'] = $this->resolveReference(Contact::class, $tenantId, $attributes['contact_id'], 'contact_id', true);
        }
        if (array_key_exists('company_id', $attributes) && $attributes['company_id'] !== null) {
            $attributes['company_id'] = $this->resolveReference(CrmCompany::class, $tenantId, $attributes['company_id'], 'company_id', true);
        }
        if (array_key_exists('deal_id', $attributes) && $attributes['deal_id'] !== null) {
            if (! is_int($attributes['deal_id']) || $attributes['deal_id'] < 1) {
                throw ValidationException::withMessages(['deal_id' => [self::REFERENCE_MESSAGE]]);
            }
            $attributes['deal_id'] = $this->resolveReference(CrmDeal::class, $tenantId, $attributes['deal_id'], 'deal_id');
        }
        if (array_key_exists('owner_id', $attributes) && $attributes['owner_id'] !== null) {
            $attributes['owner_id'] = $this->resolveOwner($tenantId, $attributes['owner_id']);
        }

        return $attributes;
    }

    private function resolveOwner(string $tenantId, mixed $ownerId): string
    {
        if (! is_string($ownerId) || ! Str::isUuid($ownerId)) {
            throw ValidationException::withMessages(['owner_id' => [self::OWNER_MESSAGE]]);
        }

        $tenant = Tenant::query()
            ->withoutGlobalScopes()
            ->whereKey($tenantId)
            ->first();
        $owner = User::query()
            ->withoutGlobalScopes()
            ->whereKey($ownerId)
            ->lockForUpdate()
            ->first();
        if (! $tenant instanceof Tenant || ! $owner instanceof User) {
            throw ValidationException::withMessages(['owner_id' => [self::OWNER_MESSAGE]]);
        }
        if ((string) $tenant->user_id === $ownerId) {
            return $ownerId;
        }

        $memberships = DB::table('tenant_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $ownerId)
            ->where('status', 'active')
            ->whereNotNull('accepted_at')
            ->whereNull('suspended_at')
            ->whereNull('revoked_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($memberships->count() !== 1) {
            throw ValidationException::withMessages(['owner_id' => [self::OWNER_MESSAGE]]);
        }

        return $ownerId;
    }

    private function resolveReference(
        string $modelClass,
        string $tenantId,
        mixed $id,
        string $field,
        bool $uuid = false,
    ): string|int {
        if ($uuid && (! is_string($id) || ! Str::isUuid($id))) {
            throw ValidationException::withMessages([$field => [self::REFERENCE_MESSAGE]]);
        }
        if (! $uuid && (! is_int($id) || $id < 1)) {
            throw ValidationException::withMessages([$field => [self::REFERENCE_MESSAGE]]);
        }

        $query = $modelClass::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($id);
        if (in_array($modelClass, [CrmCompany::class, Contact::class], true)) {
            $query->whereNull('deleted_at');
        }
        $record = $query->lockForUpdate()->first();
        if (! $record instanceof Model) {
            throw ValidationException::withMessages([$field => [self::REFERENCE_MESSAGE]]);
        }

        return $record->getKey();
    }

    private function assertUniqueTagSlug(string $tenantId, mixed $slug, ?string $ignoreId = null): void
    {
        if ($slug === null) {
            return;
        }
        $query = Tag::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug);
        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }
        if ($query->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['slug' => ['The slug has already been taken for this tenant.']]);
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function target(string $modelClass, string $tenantId, string $id, bool $softDeletable = false): Model
    {
        $query = $modelClass::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($id);
        if ($softDeletable) {
            $query->whereNull('deleted_at');
        }
        $target = $query->lockForUpdate()->first();
        if (! $target instanceof Model) {
            throw new AuthorizationException('CRM entity is not available for this tenant.');
        }

        return $target;
    }
}
