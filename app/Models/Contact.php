<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Contact - Contacto del CRM (lead, prospect, customer).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $company_id
 * @property string|null $owner_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $job_title
 * @property string|null $source
 * @property string $lifecycle_stage
 * @property Carbon|null $last_contact_at
 * @property array|null $tags
 * @property array|null $custom_fields
 */
class Contact extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'crm_contacts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'owner_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'job_title',
        'source',
        'lifecycle_stage',
        'last_contact_at',
        'tags',
        'custom_fields',
    ];

    protected $casts = [
        'last_contact_at' => 'datetime',
        'tags' => 'array',
        'custom_fields' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'contact_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'contact_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function getDisplayNameAttribute(): string
    {
        $full = $this->full_name;
        if ($full !== '') {
            return $full;
        }

        return $this->email ?? 'Contacto #'.$this->id;
    }

    /**
     * Scope: tenant scope for safe queries.
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
