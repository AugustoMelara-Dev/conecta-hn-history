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

    protected $fillable = [
        'name',
        'slug',
        'category',
        'category_id',
        'logo_path',
        'banner_path',
        'blur_hash',
        'primary_color',
        'is_pro',
        'is_active',
        'plan_expires_at',
        'status',
        'email',
        'phone',
        'whatsapp_number',
        'whatsapp_message',
        'whatsapp_template',
        'address',
        'city',
        'city_id',
        'latitude',
        'longitude',
        'description',
        'user_id',
        'hours_data',
        'theme_config',
        'product_limit',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'website_url',
        'simple_schedule',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'hours_data' => 'array', // Array para compatibilidad con Filament Repeater
        'is_pro' => 'boolean',
        'is_active' => 'boolean',
        'theme_config' => 'array',
        'social_links' => 'array',
        'plan_expires_at' => 'datetime',
    ];

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
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'is_pro', 'is_active', 'plan_expires_at', 'user_id'])
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

    // ═══════════════════════════════════════════════════════════════════════
    // FEATURE GATING: Métodos de Acceso Protegido por Plan
    // ═══════════════════════════════════════════════════════════════════════

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
