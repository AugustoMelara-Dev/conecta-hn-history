<?php

declare(strict_types=1);

/**
 * Tenant Model - Canonical Location (app/Models)
 *
 * MIGRATION: Moved from App\Domain\TenantGovernance\Models\Tenant
 * as part of Domain Purification (2026-01-07).
 */

namespace App\Models;

use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Domain\TenantGovernance\Exceptions\TenantDomainException;
use App\Scopes\LocationScope;
use App\Traits\CanBeFavorited;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity as ActivityModel;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\OpeningHours\OpeningHours;

/**
 * @property bool $is_pro
 */
#[ObservedBy(\App\Observers\TenantObserver::class)]
class Tenant extends Model implements \Spatie\MediaLibrary\HasMedia
{
    use CanBeFavorited, HasDomainEvents, HasFactory, HasUuids, LogsActivity, SoftDeletes, \Spatie\MediaLibrary\InteractsWithMedia;

    // ═══════════════════════════════════════════════════════════════════════════
    // DOMAIN CONSTANTS: Centralized business values
    // ═══════════════════════════════════════════════════════════════════════════
    public const PLAN_SEMILLA = 'semilla';

    public const PLAN_CRECIMIENTO = 'crecimiento';

    public const PLAN_COSECHA = 'cosecha';

    public const DEFAULT_PRODUCT_LIMIT = 12;

    public const DEFAULT_BRAND_COLOR = '#2563EB';

    public const DEFAULT_SUBSCRIPTION_STATUS = 'active';

    public const CRM_STATUS_NEW = 'new';

    public const CRM_STATUS_ONBOARDING = 'onboarding';

    public const CRM_STATUS_ACTIVE = 'active';

    public const CRM_STATUS_UPSELL = 'upsell';

    public const CRM_STATUS_RENEWAL = 'renewal';

    public const CRM_STATUS_AT_RISK = 'at_risk';

    public const CRM_STATUS_PAUSED = 'paused';

    public const CRM_PRIORITY_LOW = 'low';

    public const CRM_PRIORITY_NORMAL = 'normal';

    public const CRM_PRIORITY_HIGH = 'high';

    public const CRM_PRIORITY_CRITICAL = 'critical';

    public const CRM_TEAM_ACTIVATION = 'activation';

    public const CRM_TEAM_GROWTH = 'growth';

    public const CRM_TEAM_RETENTION = 'retention';

    public const CRM_TEAM_BILLING = 'billing';

    public const CRM_TEAM_SUPPORT = 'support';

    public const DEFAULT_CRM_SLA_HOURS = 24;

    // ═══════════════════════════════════════════════════════════════════════════
    // SECURITY: Mass Assignment Protection
    // ═══════════════════════════════════════════════════════════════════════════
    protected $fillable = [
        'name',
        'slug',
        'description',
        'address',
        'city_id',
        'category_id',
        'whatsapp_number',
        'whatsapp_message',
        'primary_color',
        'phone',
        'email',
        'website_url',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'simple_schedule',
        'brand_color',
        'slogan',
        'seo_title',
        'seo_description',
        'tags',
        'payment_methods',
        'delivery_enabled',
        'pickup_enabled',
        'minimum_order',
        'business_type',
        'business_segment_key',
        // Premium Profile Fields (unlocked for microsite customization)
        'about',
        'hero_image',
        'latitude',
        'longitude',
        'cover_path',
    ];

    protected $guarded = [
        'id',
        'user_id',
        'status',
        'is_active',
        'plan_type',
        'subscription_status',
        'subscription_ends_at',
        'plan_expires_at',
        'product_limit',
        'is_pro',
    ];

    protected $casts = [
        'hours_data' => 'array',
        'is_active' => 'boolean',
        'theme_config' => 'array',
        'social_links' => 'array',
        'tags' => 'array',
        'plan_expires_at' => 'datetime',
        'next_billing_date' => 'date',
        'subscription_ends_at' => 'datetime',
        'payment_methods' => 'array',
        'delivery_enabled' => 'boolean',
        'pickup_enabled' => 'boolean',
        'minimum_order' => 'decimal:2',
        'is_ai_enhanced' => 'boolean',
        'landing_page_data' => 'array',
        'crm_sla_hours' => 'integer',
        'crm_watch_score' => 'integer',
        'crm_next_follow_up_at' => 'datetime',
        'crm_last_contacted_at' => 'datetime',
        'crm_watch_flagged_at' => 'datetime',
        // TITANIUM UPGRADE: STRICT CASTING
        'plan_type' => TenantPlan::class,
        'business_type' => BusinessType::class,
    ];

    protected $appends = [
        'subscription_status_label',
        'plan_type_label',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\TenantFactory::new();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DOMAIN BEHAVIOR: Rich Model Methods (Business Logic Encapsulation)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Apply AI-generated enhancements to this tenant's profile.
     *
     * @param  array  $enhancedData  AI-generated profile data
     * @param  bool  $allowReEnhancement  Override if already enhanced
     *
     * @throws TenantDomainException If invariants are violated
     */
    public function applyAiEnhancement(array $enhancedData, bool $allowReEnhancement = false): void
    {
        if ($this->subscription_status === 'canceled' || $this->subscription_status === 'past_due') {
            throw TenantDomainException::cannotEnhanceSuspendedProfile();
        }

        if ($this->trashed()) {
            throw TenantDomainException::cannotEnhanceArchivedProfile();
        }

        if ($this->user_id === null) {
            throw TenantDomainException::cannotEnhanceWithoutOwner();
        }

        if ($this->is_ai_enhanced && ! $allowReEnhancement) {
            throw TenantDomainException::alreadyEnhanced();
        }

        $enhancedFields = [];

        if (isset($enhancedData['description']) && ! empty($enhancedData['description'])) {
            $this->description = $enhancedData['description'];
            $enhancedFields[] = 'description';
        }

        if (isset($enhancedData['seo_title']) && ! empty($enhancedData['seo_title'])) {
            $this->seo_title = $enhancedData['seo_title'];
            $enhancedFields[] = 'seo_title';
        }

        if (isset($enhancedData['seo_description']) && ! empty($enhancedData['seo_description'])) {
            $this->seo_description = $enhancedData['seo_description'];
            $enhancedFields[] = 'seo_description';
        }

        if (isset($enhancedData['tags']) && is_array($enhancedData['tags'])) {
            $this->tags = $enhancedData['tags'];
            $enhancedFields[] = 'tags';
        }

        if (isset($enhancedData['slogan']) && ! empty($enhancedData['slogan'])) {
            $this->slogan = $enhancedData['slogan'];
            $enhancedFields[] = 'slogan';
        }

        $this->is_ai_enhanced = true;
        $this->record(new \App\Domain\TenantGovernance\Events\TenantProfileEnhanced(
            tenant_id: (string) $this->id,
            user_id: (string) $this->user_id,
            occurredAt: now()->toDateTimeImmutable(),
            enhanced_fields: $enhancedFields
        ));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ASSET MANAGEMENT (Spatie Media Library)
    // ═══════════════════════════════════════════════════════════════════════════

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->useDisk('public');

        $this->addMediaCollection('cover')
            ->singleFile()
            ->useDisk('public');

        $this->addMediaCollection('hero')
            ->singleFile()
            ->useDisk('public');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SUBSCRIPTION ENGINE
    // ═══════════════════════════════════════════════════════════════════════════

    public function getIsProAttribute(): bool
    {
        $rawAttributes = $this->getAttributes();
        $rawPlanType = $rawAttributes['plan_type'] ?? null;
        $rawSubscriptionStatus = (string) ($rawAttributes['subscription_status'] ?? self::DEFAULT_SUBSCRIPTION_STATUS);

        $value = match (true) {
            $rawPlanType instanceof TenantPlan => $rawPlanType->value,
            is_string($rawPlanType) => $rawPlanType,
            default => '',
        };

        if ($value === '' && array_key_exists('is_pro', $rawAttributes)) {
            return (bool) $rawAttributes['is_pro'];
        }

        if (! in_array($value, ['pro', 'enterprise'])) {
            return false;
        }

        if (in_array($rawSubscriptionStatus, ['past_due', 'canceled', 'inactive'], true)) {
            return false;
        }

        if (! in_array($rawSubscriptionStatus, ['active', 'trial'], true)) {
            return false;
        }

        $subscriptionEndsAt = $this->resolveSubscriptionEndsAt();

        if ($subscriptionEndsAt === null) {
            return true;
        }

        return $subscriptionEndsAt->isFuture();
    }

    public function isSubscriptionActive(): bool
    {
        $rawSubscriptionStatus = (string) ($this->getAttributes()['subscription_status'] ?? self::DEFAULT_SUBSCRIPTION_STATUS);

        if ($rawSubscriptionStatus !== 'active') {
            return false;
        }

        $subscriptionEndsAt = $this->resolveSubscriptionEndsAt();

        if ($subscriptionEndsAt === null) {
            return true;
        }

        return $subscriptionEndsAt->isFuture();
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        $subscriptionEndsAt = $this->resolveSubscriptionEndsAt();

        if ($subscriptionEndsAt === null) {
            return null;
        }

        return (int) max(0, now()->diffInDays($subscriptionEndsAt, false));
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        $remaining = $this->days_until_expiry;

        if ($remaining === null) {
            return false;
        }

        return $remaining <= $days && $remaining > 0;
    }

    public function getSubscriptionStatusLabelAttribute(): string
    {
        $status = (string) ($this->getAttributes()['subscription_status'] ?? 'inactive');

        $labels = [
            'active' => 'Activo',
            'trial' => 'Período de Prueba',
            'past_due' => 'Vencido',
            'canceled' => 'Cancelado',
            'inactive' => 'Inactivo',
            'pending' => 'Pendiente',
        ];

        return $labels[$status] ?? Str::headline(str_replace('_', ' ', $status));
    }

    public function getPlanTypeLabelAttribute(): string
    {
        $rawPlanType = $this->getAttributes()['plan_type'] ?? null;
        $value = match (true) {
            $rawPlanType instanceof TenantPlan => $rawPlanType->value,
            is_string($rawPlanType) => $rawPlanType,
            default => '',
        };

        $labels = [
            'semilla' => 'Plan Semilla',
            'pro' => 'Plan Pro',
            'enterprise' => 'Plan Enterprise',
        ];

        return $labels[$value] ?? 'Plan Semilla';
    }

    public function getCrmStatusLabelAttribute(): string
    {
        return match ((string) ($this->getAttributes()['crm_status'] ?? self::CRM_STATUS_NEW)) {
            self::CRM_STATUS_ONBOARDING => 'En onboarding',
            self::CRM_STATUS_ACTIVE => 'Operando',
            self::CRM_STATUS_UPSELL => 'Upsell',
            self::CRM_STATUS_RENEWAL => 'Renovacion',
            self::CRM_STATUS_AT_RISK => 'En riesgo',
            self::CRM_STATUS_PAUSED => 'Pausado',
            default => 'Nuevo',
        };
    }

    public function getCrmPriorityLabelAttribute(): string
    {
        return match ((string) ($this->getAttributes()['crm_priority'] ?? self::CRM_PRIORITY_NORMAL)) {
            self::CRM_PRIORITY_LOW => 'Baja',
            self::CRM_PRIORITY_HIGH => 'Alta',
            self::CRM_PRIORITY_CRITICAL => 'Critica',
            default => 'Normal',
        };
    }

    public function getCrmTeamLabelAttribute(): string
    {
        return match ((string) ($this->getAttributes()['crm_team'] ?? self::CRM_TEAM_ACTIVATION)) {
            self::CRM_TEAM_GROWTH => 'Growth',
            self::CRM_TEAM_RETENTION => 'Retencion',
            self::CRM_TEAM_BILLING => 'Cobros',
            self::CRM_TEAM_SUPPORT => 'Soporte',
            default => 'Activacion',
        };
    }

    public function getCrmWatchLabelAttribute(): string
    {
        $score = (int) ($this->getAttributes()['crm_watch_score'] ?? 0);

        if ($score >= 90) {
            return 'Critico';
        }

        if ($score >= 80) {
            return 'Alto';
        }

        if ($score >= 60) {
            return 'En observacion';
        }

        return 'Sin alerta';
    }

    public function getBusinessSegmentLabelAttribute(): string
    {
        $segmentKey = (string) ($this->getAttributes()['business_segment_key'] ?? '');

        if ($segmentKey === '') {
            return 'General';
        }

        $label = config("business-segments.profiles.{$segmentKey}.label");

        return is_string($label) && $label !== ''
            ? $label
            : Str::of($segmentKey)->replace('_', ' ')->title()->value();
    }

    public function getPlanExpiresAtAttribute(mixed $value): ?Carbon
    {
        return $this->resolveDateAttribute($value) ?? $this->resolveSubscriptionEndsAt();
    }

    public function approveForOperations(): void
    {
        $this->forceFill([
            'status' => 'approved',
            'is_active' => true,
        ])->save();
    }

    public function rejectForOperations(): void
    {
        $this->forceFill([
            'status' => 'rejected',
            'is_active' => false,
        ])->save();
    }

    public function toggleOperationalStatus(): void
    {
        $this->forceFill([
            'is_active' => ! (bool) $this->getAttribute('is_active'),
        ])->save();
    }

    public function activateProManually(int $months): void
    {
        $baseDate = $this->resolveSubscriptionEndsAt() ?? now();

        if ($baseDate->isPast()) {
            $baseDate = now();
        }

        $this->forceFill([
            'is_pro' => true,
            'is_active' => true,
            'plan_type' => TenantPlan::PRO->value,
            'subscription_status' => 'active',
            'subscription_ends_at' => $baseDate->copy()->addMonths($months),
        ])->save();
    }

    /**
     * Record an operational event without allowing audit issues to break admin flows.
     *
     * @param  array<string, mixed>  $properties
     */
    public function logOperationalEvent(string $description, ?User $actor = null, array $properties = []): void
    {
        try {
            $activity = activity('operations')->performedOn($this);

            if ($actor instanceof User) {
                $activity->causedBy($actor);
            }

            $activity
                ->event('operations')
                ->withProperties(array_merge([
                    'source' => 'superpanel',
                    'ip' => request()?->ip(),
                ], $properties))
                ->log($description);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // QUERY SCOPES
    // ═══════════════════════════════════════════════════════════════════════════

    public function scopePublished($query)
    {
        return $query
            ->where('is_active', true)
            ->where('subscription_status', 'active');
    }

    public function scopeInPriorityOrder($query)
    {
        return $query->orderByRaw("FIELD(plan_type, 'enterprise', 'pro', 'semilla')");
    }

    public function scopeSearch($query, ?string $term)
    {
        if (empty($term)) {
            return $query;
        }

        $term = mb_substr(trim($term), 0, 100);

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('tags', 'like', "%{$term}%");
        });
    }

    public function scopeInCategory($query, ?string $categorySlug)
    {
        if (empty($categorySlug)) {
            return $query;
        }

        return $query->whereHas('category', function ($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
        });
    }

    public function scopeInCity($query, ?string $citySlug)
    {
        if (empty($citySlug)) {
            return $query;
        }

        return $query->whereHas('city', function ($q) use ($citySlug) {
            $q->where('slug', $citySlug);
        });
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400)
            ->sharpen(10);

        $this->addMediaConversion('optimized')
            ->width(800)
            ->format('webp')
            ->quality(80);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new LocationScope);
    }

    public function getActivitylogOptions(): LogOptions
    {
        // FIXED: Only log attributes that are always present during model lifecycle.
        // Removed: status (doesn't exist), plan_expires_at, subscription_ends_at,
        // is_pro (computed accessor), and other nullable/conditional fields that
        // cause MissingAttributeException during factory creation.
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'is_active',
                'plan_type',
                'subscription_status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => "Negocio '{$this->name}' registrado",
                'updated' => "Negocio '{$this->name}' actualizado",
                'deleted' => "Negocio '{$this->name}' eliminado",
                default => "Negocio '{$this->name}' {$eventName}",
            })
            ->useLogName('negocios');
    }

    public function tapActivity(ActivityModel $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge([
            'ip' => request()->ip(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════════════════════

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function crmOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'crm_owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function primaryLocation(): HasOne
    {
        return $this->hasOne(Location::class)->where('is_primary', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function menuSections(): HasMany
    {
        return $this->hasMany(MenuSection::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityModel::class, 'subject')->latest('created_at');
    }

    public function tenantVisits(): HasMany
    {
        return $this->hasMany(TenantVisit::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows')
            ->withTimestamps();
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class)->orderBy('day_of_week');
    }

    /**
     * Get a Spatie OpeningHours object from the DB records.
     *
     * Converts the OpeningHour models into a format consumable by
     * spatie/opening-hours for availability calculations.
     */
    public function getOpeningHours(): OpeningHours
    {
        $hours = [];
        $dayMap = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        foreach ($this->openingHours as $openingHour) {
            /** @var OpeningHour $openingHour */
            $dayName = $dayMap[$openingHour->day_of_week] ?? null;
            if ($dayName === null) {
                continue;
            }

            $open = $openingHour->getRawOpeningTime();
            $close = $openingHour->getRawClosingTime();

            if ($open && $close) {
                // Convert H:i:s to H:i format for Spatie
                $hours[$dayName][] = substr($open, 0, 5).'-'.substr($close, 0, 5);
            }
        }

        // Fallback to default 9-5 M-F if no hours defined
        if (empty($hours)) {
            return OpeningHours::create([
                'monday' => ['09:00-17:00'],
                'tuesday' => ['09:00-17:00'],
                'wednesday' => ['09:00-17:00'],
                'thursday' => ['09:00-17:00'],
                'friday' => ['09:00-17:00'],
            ]);
        }

        return OpeningHours::create($hours);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SERVICE DOMAIN RELATIONSHIPS (Appointments System)
    // ═══════════════════════════════════════════════════════════════════════════

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function serviceProviders(): HasMany
    {
        return $this->hasMany(ServiceProvider::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function getThemeConfigAttribute($value): array
    {
        $config = $value ? json_decode($value, true) : [];

        $defaults = [
            'primary_color' => '#000000',
            'radius' => '0.5rem',
            'font' => 'Inter',
        ];

        $theme = array_merge($defaults, $config ?? []);

        if (isset($theme['primary_color'])) {
            if (! preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $theme['primary_color'])) {
                $theme['primary_color'] = '#000000';
            }
        }

        return $theme;
    }

    /**
     * Legacy compatibility layer for old UI/forms that still expect tenant-level
     * `hours_data`, even though the canonical schedule now lives in `opening_hours`.
     *
     * @return array<int, array{day: string, open: string|null, close: string|null, is_closed: bool}>
     */
    public function getHoursDataAttribute(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $dayMap = [
            0 => 'domingo',
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
        ];

        try {
            return OpeningHour::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $this->getKey())
                ->orderBy('day_of_week')
                ->get()
                ->map(static function (OpeningHour $openingHour) use ($dayMap): array {
                    return [
                        'day' => $dayMap[$openingHour->day_of_week] ?? 'lunes',
                        'open' => $openingHour->getRawOpeningTime() !== null
                            ? substr($openingHour->getRawOpeningTime(), 0, 5)
                            : null,
                        'close' => $openingHour->getRawClosingTime() !== null
                            ? substr($openingHour->getRawClosingTime(), 0, 5)
                            : null,
                        'is_closed' => false,
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolveSubscriptionEndsAt(): ?Carbon
    {
        return $this->resolveDateAttribute($this->getAttributes()['subscription_ends_at'] ?? null);
    }

    private function resolveDateAttribute(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // FEATURE GATING
    // ═══════════════════════════════════════════════════════════════════════════

    public function getPublicMenuStructure(?int $productLimit = null): ?\Illuminate\Support\Collection
    {
        if (! $this->is_pro) {
            return null;
        }

        return $this->menuSections()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['activeProducts' => function ($query) use ($productLimit) {
                if ($productLimit) {
                    $query->take($productLimit);
                }
            }])
            ->withCount(['products' => fn ($q) => $q->where('is_visible', true)])
            ->get();
    }

    public function getPublicProducts(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->products()
            ->where('is_visible', true)
            ->orderBy('name');

        if (! $this->is_pro) {
            $query->take($limit);
        }

        return $query->get();
    }

    public function hasCapability(Capability $capability): bool
    {
        return app(CapabilityManagerInterface::class)->has($this, $capability);
    }
}
