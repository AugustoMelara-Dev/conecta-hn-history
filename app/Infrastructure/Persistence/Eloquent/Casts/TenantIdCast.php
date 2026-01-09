<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Casts;

use App\Domain\Shared\ValueObjects\TenantId;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * TenantId Custom Cast
 *
 * Bridges the gap between Eloquent's hydration engine and
 * strict Domain Value Objects. Ensures Crash-on-Read safety.
 */
class TenantIdCast implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param  Model  $model
     * @param  mixed  $value
     */
    public function get($model, string $key, $value, array $attributes): ?TenantId
    {
        if ($value === null) {
            return null;
        }

        return new TenantId((int) $value);
    }

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param  Model  $model
     * @param  TenantId|int|string|null  $value
     */
    public function set($model, string $key, $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof TenantId) {
            return $value->value;
        }

        // TOLERANCE: Accept numeric strings from HTTP payloads (Forms/APIs)
        if (is_numeric($value)) {
            return (new TenantId((int) $value))->value;
        }

        throw new InvalidArgumentException('The given value is not a valid TenantId instance or integer.');
    }
}
