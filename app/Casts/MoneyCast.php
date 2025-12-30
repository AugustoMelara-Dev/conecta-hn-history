<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * MoneyCast - Eloquent Cast for Money Value Object
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Database ↔ Domain Transformation               ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This cast acts as a strict type boundary between the database layer     ║
 * ║  (integers in cents) and the domain layer (Money Value Objects).         ║
 * ║                                                                          ║
 * ║  INBOUND (Database → Domain):                                            ║
 * ║    - Integer 10050 → Money::fromCents(10050) → L. 100.50                ║
 * ║    - NULL → null (respects nullable columns)                            ║
 * ║                                                                          ║
 * ║  OUTBOUND (Domain → Database):                                           ║
 * ║    - Money object → $money->cents() → 10050                             ║
 * ║    - Primitives (int/float) → THROWS InvalidArgumentException           ║
 * ║                                                                          ║
 * ║  STRICT ENFORCEMENT: Developers MUST use Money objects.                  ║
 * ║    $product->price = 100.50;           // ❌ EXCEPTION                   ║
 * ║    $product->price = Money::fromDecimal(100.50); // ✅ CORRECT          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE in Model:
 *   protected $casts = [
 *       'price' => MoneyCast::class,
 *       'discount_amount' => MoneyCast::class.':USD',  // With currency
 *   ];
 */
class MoneyCast implements CastsAttributes
{
    /**
     * @param string $currency Default currency for this attribute
     */
    public function __construct(
        protected string $currency = 'HNL'
    ) {}

    /**
     * Transform the database value into a Money Value Object.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value Raw database value (integer cents or null)
     * @param array $attributes
     * @return Money|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        // Respect nullable columns - don't create zero-value Money for NULL
        if ($value === null) {
            return null;
        }

        return Money::fromCents((int) $value, $this->currency);
    }

    /**
     * Transform the Money Value Object for database storage.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value Money object, null, or (INVALID) primitive
     * @param array $attributes
     * @return int|null
     * @throws InvalidArgumentException if primitive type is passed
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        // Allow null for nullable columns
        if ($value === null) {
            return null;
        }

        // Allow Money objects (THE CORRECT WAY)
        if ($value instanceof Money) {
            return $value->cents();
        }

        // ═══════════════════════════════════════════════════════════════════════
        // STRICT TYPE ENFORCEMENT: Reject Primitive Types
        // ═══════════════════════════════════════════════════════════════════════
        // This forces developers to use Money objects explicitly, preventing:
        // - Accidental floating-point assignments ($product->price = 99.99)
        // - Ambiguous integer assignments (is 10050 cents or lempiras?)
        // - Silent currency mismatches
        // ═══════════════════════════════════════════════════════════════════════
        if (is_int($value) || is_float($value)) {
            throw new InvalidArgumentException(
                "Cannot assign primitive type to '{$key}'. Use Money::fromCents() or Money::fromDecimal() instead. " .
                "Example: \$model->{$key} = Money::fromDecimal({$value});"
            );
        }

        throw new InvalidArgumentException(
            "Invalid type for '{$key}'. Expected Money object or null, got " . get_debug_type($value)
        );
    }
}
