<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 */
class MenuSection extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Tenant dueño de esta sección
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

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

