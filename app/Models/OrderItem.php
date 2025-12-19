<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_price',
        'quantity',
        'unit_price',
        'total_price',
        'selected_options',
        'notes',
    ];

    protected $casts = [
        'product_price' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'selected_options' => 'array',
    ];

    // ═══ RELACIONES ═══

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ═══ HELPERS ═══

    /**
     * Calcula el precio total basado en el precio unitario y cantidad
     */
    public function calculateTotal(): float
    {
        return $this->unit_price * $this->quantity;
    }

    /**
     * Obtiene una descripción legible de las opciones seleccionadas
     */
    public function getOptionsDescription(): string
    {
        if (empty($this->selected_options)) {
            return '';
        }

        return collect($this->selected_options)
            ->map(fn ($opt) => $opt['option'] ?? '')
            ->filter()
            ->implode(', ');
    }
}

