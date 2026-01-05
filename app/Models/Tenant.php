<?php

namespace App\Models;

use App\Scopes\LocationScope;
use App\Traits\CanBeFavorited;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ObservedBy(\App\Observers\TenantObserver::class)]
class Tenant extends Model implements \Spatie\MediaLibrary\HasMedia
{
    use CanBeFavorited, HasFactory, LogsActivity, SoftDeletes, \Spatie\MediaLibrary\InteractsWithMedia;

    // ═══════════════════════════════════════════════════════════════════════════
    // SECURITY: Mass Assignment Protection
    // ═══════════════════════════════════════════════════════════════════════════
    // CRITICAL: Only cosmetic/contact fields are fillable. 
    // Plan, Status, and Subscription fields are STRICTLY GUARDED.
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
        // White-label Branding
        'brand_color',
        'slogan',
        // SEO
        'seo_title',
        'seo_description',
        'tags',
        // Operational (User Controlled)
        'payment_methods',
        'delivery_enabled',
        'pickup_enabled',
        'minimum_order',
    ];

    protected $guarded = [
        'id',
        'user_id',
        'status',          // Admin controlled
        'is_active',       // Admin/Killswitch controlled
        'plan_type',       // Billing Engine controlled
        'subscription_status', 
        'subscription_ends_at',
        'plan_expires_at',
        'product_limit',   // Billing Engine controlled
        'is_pro',          // Computed
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
    ];

    // ═══════════════════════════════════════════════════════════════════════════
    // ASSET MANAGEMENT (Spatie Media Library)
    // ═══════════════════════════════════════════════════════════════════════════
    
    public function registerMediaCollections(): void
    {
        // Identity Assets: Single File Enforcement
        $this->addMediaCollection('logo')
            ->singleFile() // Auto-delete old file on upload
            ->useDisk('public');

        $this->addMediaCollection('cover')
            ->singleFile()
            ->useDisk('public');
            
        // Hero Image for White-Label
        $this->addMediaCollection('hero')
            ->singleFile()
            ->useDisk('public');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // JSON SERIALIZATION: Expose computed attributes to Frontend
    // ═══════════════════════════════════════════════════════════════════════════
    // PERFORMANCE NOTE: is_pro and days_until_expiry REMOVED from global $appends
    // These involve date calculations executed on every toArray()/toJson() call.
    // Use runtime appending in controllers that specifically need them:
    //   $tenant->append(['is_pro', 'days_until_expiry']);
    // ═══════════════════════════════════════════════════════════════════════════
    protected $appends = [
        'subscription_status_label',
        'plan_type_label',
    ];

    // ═══════════════════════════════════════════════════════════════════════════
    // SUBSCRIPTION ENGINE: Computed Pro Status
    // is_pro is NOW COMPUTED dynamically and auto-serialized to JSON
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Determine if tenant has active Pro subscription.
     *
     * BUSINESS LOGIC (CORRECTED):
     * A tenant is Pro IF AND ONLY IF:
     * 1. plan_type is 'pro' or 'enterprise'
     * 2. subscription_status is 'active' OR 'trial' (both grant access)
     * 3. status is NOT 'past_due' or 'canceled' (explicit denial)
     * 4. subscription_ends_at is NULL (perpetual) OR in the future
     *
     * This accessor OVERWRITES the static is_pro column in JSON output.
     * The Frontend receives computed truth, not database value.
     */
    public function getIsProAttribute(): bool
    {
        // ═══════════════════════════════════════════════════════════════
        // STEP 1: Plan Type Validation
        // Only 'pro' and 'enterprise' plans grant premium features
        // ═══════════════════════════════════════════════════════════════
        if (! in_array($this->plan_type, ['pro', 'enterprise'])) {
            return false;
        }

        // ═══════════════════════════════════════════════════════════════
        // STEP 2: Explicit Denial States (Security Gate)
        // 'past_due' and 'canceled' ALWAYS deny access, regardless of dates
        // ═══════════════════════════════════════════════════════════════
        if (in_array($this->subscription_status, ['past_due', 'canceled'])) {
            return false;
        }

        // ═══════════════════════════════════════════════════════════════
        // STEP 3: Valid Access States
        // 'active' = Paid customer
        // 'trial'  = Evaluation period (MUST grant access for conversion)
        // ═══════════════════════════════════════════════════════════════
        if (! in_array($this->subscription_status, ['active', 'trial'])) {
            return false;
        }

        // ═══════════════════════════════════════════════════════════════
        // STEP 4: Temporal Validation
        // NULL = perpetual access (lifetime or free tier)
        // Future date = valid subscription window
        // Past date = expired, deny access
        // ═══════════════════════════════════════════════════════════════
        if ($this->subscription_ends_at === null) {
            return true; // Perpetual access
        }

        return $this->subscription_ends_at->isFuture();
    }

    /**
     * Check if subscription is currently active (not expired).
     */
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

    /**
     * Get days remaining until subscription expires.
     * Returns null for perpetual subscriptions.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if ($this->subscription_ends_at === null) {
            return null; // Perpetual
        }

        return max(0, now()->diffInDays($this->subscription_ends_at, false));
    }

    /**
     * Check if subscription is expiring soon (within 7 days).
     */
    public function isExpiringSoon(int $days = 7): bool
    {
        $remaining = $this->days_until_expiry;

        if ($remaining === null) {
            return false; // Perpetual never expires
        }

        return $remaining <= $days && $remaining > 0;
    }

    /**
     * Human-readable subscription status.
     */
    public function getSubscriptionStatusLabelAttribute(): string
    {
        $labels = [
            'active' => 'Activo',
            'trial' => 'Período de Prueba',
            'past_due' => 'Vencido',
            'canceled' => 'Cancelado',
        ];

        return $labels[$this->subscription_status] ?? 'Desconocido';
    }

    /**
     * Human-readable plan type.
     */
    public function getPlanTypeLabelAttribute(): string
    {
        $labels = [
            'semilla' => 'Plan Semilla',
            'pro' => 'Plan Pro',
            'enterprise' => 'Plan Enterprise',
        ];

        return $labels[$this->plan_type] ?? 'Desconocido';
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // QUERY SCOPES: Encapsulate SQL logic for security and reusability
    // These scopes centralize business rules and eliminate raw SQL from controllers
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Scope: Only tenants with active subscriptions.
     *
     * SECURITY: Centralizes visibility rules.
     * Use: Tenant::query()->published()
     */
    public function scopePublished($query)
    {
        return $query
            ->where('is_active', true)
            ->where('subscription_status', 'active');
    }

    /**
     * Scope: Order by monetization tier (Enterprise > Pro > Semilla).
     *
     * SECURITY: Encapsulates raw SQL to prevent injection.
     * No external parameters accepted - SQL is static and safe.
     * Use: Tenant::query()->inPriorityOrder()
     */
    public function scopeInPriorityOrder($query)
    {
        return $query->orderByRaw("FIELD(plan_type, 'enterprise', 'pro', 'semilla')");
    }

    /**
     * Scope: Search tenants by name, description, or tags.
     *
     * SECURITY: Safely binds search term to prevent SQL injection.
     * ARCHITECTURE: Uses proper grouping to maintain filter precedence.
     * Use: Tenant::query()->search('keyword')
     */
    public function scopeSearch($query, ?string $term)
    {
        if (empty($term)) {
            return $query;
        }

        // Sanitize and limit search term
        $term = mb_substr(trim($term), 0, 100);

        // Grouped OR conditions ensure city/category filters aren't overridden
        // SQL: WHERE (city = X) AND (name LIKE %Y% OR description LIKE %Y% OR tags LIKE %Y%)
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('tags', 'like', "%{$term}%"); // JSON column - partial match
        });
    }

    /**
     * Scope: Filter by category slug.
     *
     * SECURITY: Validates slug exists via relationship.
     * Use: Tenant::query()->inCategory('restaurantes')
     */
    public function scopeInCategory($query, ?string $categorySlug)
    {
        if (empty($categorySlug)) {
            return $query;
        }

        return $query->whereHas('category', function ($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
        });
    }

    /**
     * Scope: Filter by city slug.
     *
     * SECURITY: Validates slug exists via relationship.
     * Use: Tenant::query()->inCity('tocoa')
     */
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

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new LocationScope);
    }

    /**
     * Configuracion de Activity Log - Registro Forense
     *
     * TRACKED FIELDS:
     * - Identity: name, status, user_id
     * - Subscription: is_pro, is_active, plan_expires_at, plan_type, subscription_status
     * - Branding: primary_color, brand_color
     * - Operations: payment_methods, delivery_enabled, pickup_enabled, minimum_order
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                // Identity & Status
                'name',
                'status',
                'user_id',
                // Subscription Engine
                'is_pro',
                'is_active',
                'plan_expires_at',
                'plan_type',
                'subscription_status',
                'subscription_ends_at',
                // Branding (Security: track visual identity changes)
                'primary_color',
                'brand_color',
                // Operational Settings (Security: track payment config changes)
                'payment_methods',
                'delivery_enabled',
                'pickup_enabled',
                'minimum_order',
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

    /**
     * Capturar IP del usuario que realiza la accion
     */
    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->merge([
            'ip' => request()->ip(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /**
     * Get the menu sections for this tenant (for organizing products).
     */
    public function menuSections(): HasMany
    {
        return $this->hasMany(MenuSection::class)->orderBy('sort_order');
    }

    /**
     * Get the payments for the tenant.
     */
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

    /**
     * Get the visits for the tenant.
     */
    public function tenantVisits(): HasMany
    {
        return $this->hasMany(TenantVisit::class);
    }

    /**
     * Get the interactions for the tenant (WhatsApp clicks, calls, etc).
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    /**
     * Get the users who follow this tenant (Social Loyalty).
     */
    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows')
            ->withTimestamps();
    }

    /**
     * Get the campaigns created by this tenant.
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Get the coupons created by this tenant.
     * REQUIRED BY: Marketing Module (Pages/Tenant/Marketing/Index.jsx)
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * Get theme configuration with validated hex colors and defaults.
     *
     * SECURITY: Validates hex color to prevent CSS injection
     */
    public function getThemeConfigAttribute($value): array
    {
        $config = $value ? json_decode($value, true) : [];

        // Default theme
        $defaults = [
            'primary_color' => '#000000',
            'radius' => '0.5rem',
            'font' => 'Inter',
        ];

        $theme = array_merge($defaults, $config ?? []);

        // CRITICAL: Validate hex color to prevent injection
        if (isset($theme['primary_color'])) {
            if (! preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $theme['primary_color'])) {
                // Invalid hex, use default
                $theme['primary_color'] = '#000000';
            }
        }

        return $theme;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // FEATURE GATING: Métodos de Acceso Protegido por Plan
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Obtiene la estructura de menú pública (Feature Gating)
     *
     * LÓGICA DE COMPUERTA (FEATURE GATE):
     * - PRO: Retorna secciones activas con productos eager loaded
     * - FREE: Retorna null (fuerza aplanamiento de estructura)
     *
     * IMPACTO ARQUITECTÓNICO:
     * Esta lógica encapsulada protege el modelo de negocio en cualquier
     * interfaz futura (web, API móvil, endpoints JSON).
     *
     * @param  int|null  $productLimit  Límite de productos (null = sin límite para PRO)
     */
    public function getPublicMenuStructure(?int $productLimit = null): ?\Illuminate\Support\Collection
    {
        // ═══ COMPUERTA DE PLAN ═══
        // Si no es PRO, retornamos null INTENCIONALMENTE
        // Esto fuerza al consumidor a usar una lista plana
        if (! $this->is_pro) {
            return null;
        }

        // ═══ ACCESO PRO: Estructura Completa ═══
        // Carga ansiosa (eager loading) para performance
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

    /**
     * Obtiene los productos públicos con restricciones de plan
     *
     * @param  int  $limit  Límite de productos para FREE
     */
    public function getPublicProducts(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->products()
            ->where('is_visible', true)
            ->orderBy('name');

        if (! $this->is_pro) {
            // FREE: Aplicar límite duro (Guillotina de Inventario)
            $query->take($limit);
        }

        return $query->get();
    }
}
