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
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Activity as ActivityModel;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\OpeningHours\OpeningHours;

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
        $value = $this->plan_type->value;

        if (! in_array($value, ['pro', 'enterprise'])) {
            return false;
        }

        if (in_array($this->subscription_status, ['past_due', 'canceled'])) {
            return false;
        }

        if (! in_array($this->subscription_status, ['active', 'trial'])) {
            return false;
        }

        if ($this->subscription_ends_at === null) {
            return true;
        }

        return $this->subscription_ends_at->isFuture();
    }

    public function isSubscriptionActive(): bool
    {
        if ($this->subscription_status !== 'active') {
            return false;
        }

        if ($this->subscription_ends_at === null) {
            return true;
        }

        return $this->subscription_ends_at->isFuture();
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if ($this->subscription_ends_at === null) {
            return null;
        }

        return max(0, now()->diffInDays($this->subscription_ends_at, false));
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
        $labels = [
            'active' => 'Activo',
            'trial' => 'Período de Prueba',
            'past_due' => 'Vencido',
            'canceled' => 'Cancelado',
        ];

        return $labels[$this->subscription_status];
    }

    public function getPlanTypeLabelAttribute(): string
    {
        $value = $this->plan_type->value;

        $labels = [
            'semilla' => 'Plan Semilla',
            'pro' => 'Plan Pro',
            'enterprise' => 'Plan Enterprise',
        ];

        return $labels[$value];
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
