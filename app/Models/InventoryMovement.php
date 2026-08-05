<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable inventory ledger entry.
 *
 * New entries are recorded through record(); corrections must be compensating
 * movements rather than updates or deletions.
 */
final class InventoryMovement extends Model
{
    /** @var string */
    protected $table = 'inventory_movements';

    /** @var string */
    protected $primaryKey = 'id';

    /** @var bool */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'string';

    /** @var array<int, string> */
    protected $guarded = ['*'];

    /** @var array<string, string> */
    protected $casts = [
        'metadata' => 'array',
        'previous_quantity' => 'integer',
        'resulting_quantity' => 'integer',
        'delta' => 'integer',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function record(array $attributes): void
    {
        $delta = $attributes['delta'] ?? null;
        $previousQuantity = $attributes['previous_quantity'] ?? null;
        $resultingQuantity = $attributes['resulting_quantity'] ?? null;

        if (! is_int($delta) || $delta === 0) {
            throw new \LogicException('Inventory movement delta must be a non-zero integer.');
        }

        if (! is_int($previousQuantity) || ! is_int($resultingQuantity) || $previousQuantity + $delta !== $resultingQuantity) {
            throw new \LogicException('Inventory movement balances must match the signed delta.');
        }

        self::query()->insert($attributes);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('Inventory movements are append-only.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new \LogicException('Inventory movements are append-only.');
    }
}
