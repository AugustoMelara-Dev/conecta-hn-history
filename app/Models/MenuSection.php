<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MenuSection - Sección de Menú para organización de productos (PRO Feature)
 *
 * Permite a los negocios organizar su catálogo en categorías visuales:
 * Entradas, Bebidas, Postres, etc.
 *
 * ARQUITECTURA:
 * - tenant_id: Aislamiento Multi-Tenant con cascadeOnDelete
 * - sort_order: Motor de ordenamiento para narrativa de ventas
 * - products: Relación pre-ordenada desde DB
 *
 * SECURITY: Uses BelongsToTenant trait for automatic tenant isolation
 */
class MenuSection extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // RELACIONES
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Todos los productos en esta sección (pre-ordenados)
     *
     * IMPORTANTE: orderBy('name') quita carga de procesamiento al frontend
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('name');
    }

    /**
     * Solo productos visibles (para vista pública)
     */
    public function activeProducts(): HasMany
    {
        return $this->hasMany(Product::class)
            ->where('is_visible', true)
            ->orderBy('name');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Solo secciones activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Ordenadas por sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
