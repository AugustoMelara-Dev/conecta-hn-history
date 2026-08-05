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
use App\Application\TenantGovernance\Contracts\TenantFeatureAccessManagerInterface;
use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\CommercialMode;
use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Domain\TenantGovernance\Events\TenantProfileEnhanced;
use App\Domain\TenantGovernance\Exceptions\TenantDomainException;
use App\Observers\TenantObserver;
use App\Scopes\LocationScope;
use App\Traits\CanBeFavorited;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\OpeningHours\OpeningHours;

#[ObservedBy(TenantObserver::class)]
class Tenant extends Model implements HasMedia
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
        'commercial_mode',
        'rubro_key',
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
        // TITANIUM UPGRADE: STRICT CASTING
        'plan_type' => TenantPlan::class,
        'business_type' => BusinessType::class,
        'commercial_mode' => CommercialMode::class,
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
        return TenantFactory::new();
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
        $this->record(new TenantProfileEnhanced(
            tenant_id: (string) $this->id,
            user_id: (string) $this->user_id,
            occurred_at: now()->toIso8601String(),
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
        $value = $this->attributes['plan_type'] ?? $this->attributes['plan'] ?? self::PLAN_SEMILLA;

        if (! in_array($value, ['pro', 'enterprise'])) {
            return false;
        }

        $subscriptionStatus = $this->attributes['subscription_status'] ?? self::DEFAULT_SUBSCRIPTION_STATUS;

        if (in_array($subscriptionStatus, ['past_due', 'canceled'], true)) {
            return false;
        }

        if (! in_array($subscriptionStatus, ['active', 'trial'], true)) {
            return false;
        }

        $subscriptionEndsAt = $this->attributes['subscription_ends_at'] ?? null;

        if ($subscriptionEndsAt === null) {
            return true;
        }

        return $this->asDateTime($subscriptionEndsAt)->isFuture();
    }

    public function isSubscriptionActive(): bool
    {
        if (($this->attributes['subscription_status'] ?? null) !== 'active') {
            return false;
        }

        $subscriptionEndsAt = $this->attributes['subscription_ends_at'] ?? null;

        if ($subscriptionEndsAt === null) {
            return true;
        }

        return $this->asDateTime($subscriptionEndsAt)->isFuture();
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        $subscriptionEndsAt = $this->attributes['subscription_ends_at'] ?? null;

        if ($subscriptionEndsAt === null) {
            return null;
        }

        return max(0, now()->diffInDays($this->asDateTime($subscriptionEndsAt), false));
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
        $subscriptionStatus = $this->attributes['subscription_status'] ?? self::DEFAULT_SUBSCRIPTION_STATUS;

        $labels = [
            'active' => 'Activo',
            'trial' => 'Período de Prueba',
            'past_due' => 'Vencido',
            'canceled' => 'Cancelado',
        ];

        return $labels[$subscriptionStatus] ?? 'Desconocido';
    }

    public function getPlanTypeLabelAttribute(): string
    {
        $value = $this->attributes['plan_type'] ?? $this->attributes['plan'] ?? self::PLAN_SEMILLA;

        $labels = [
            'semilla' => 'Plan Semilla',
            'pro' => 'Plan Pro',
            'enterprise' => 'Plan Enterprise',
        ];

        return $labels[$value] ?? 'Desconocido';
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
        return $query->orderByRaw("case plan_type when 'enterprise' then 0 when 'pro' then 1 when 'semilla' then 2 else 3 end");
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

    public function registerMediaConversions(?Media $media = null): void
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

    public function tapActivity(Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->merge([
            'ip' => request()->ip(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════════════════════

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function primaryMarket(): HasOne
    {
        return $this->hasOne(TenantMarket::class)->where('is_primary', true);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
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

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class);
    }

    public function hasCapability(Capability $capability): bool
    {
        return app(CapabilityManagerInterface::class)->has($this, $capability);
    }

    public function hasFeature(TenantFeature $feature): bool
    {
        return app(TenantFeatureAccessManagerInterface::class)->has($this, $feature);
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

    // ═══════════════════════════════════════════════════════════════════════════
    // FEATURE GATING
    // ═══════════════════════════════════════════════════════════════════════════

    public function getPublicMenuStructure(?int $productLimit = null): ?Collection
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
}
