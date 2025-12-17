<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interaction extends Model
{
    use HasFactory;

    /**
     * Tipos de interacción disponibles
     */
    public const TYPE_VISIT = 'visit';

    public const TYPE_PRODUCT_VIEW = 'product_view';

    public const TYPE_WHATSAPP_CLICK = 'whatsapp_click';

    public const TYPE_CALL_CLICK = 'call_click';

    public const TYPE_SHARE = 'share';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'type',
        'ip_address',
        'user_agent',
        'referrer',
        'url',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Relación: Pertenece a un Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relación: Pertenece a un Producto (opcional)
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope: Solo visitas
     */
    public function scopeVisits($query)
    {
        return $query->where('type', self::TYPE_VISIT);
    }

    /**
     * Scope: Visitas de hoy
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope: Visitas de este mes
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }
}
