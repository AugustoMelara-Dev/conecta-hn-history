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
 * strict Domain Value Objects (UUIDs).
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

        return new TenantId((string) $value);
    }

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param  Model  $model
     * @param  TenantId|string|null  $value
     */
    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof TenantId) {
            return $value->value;
        }

        if (is_string($value)) {
            return (new TenantId($value))->value;
        }

        throw new InvalidArgumentException('The given value is not a valid TenantId instance or UUID string.');
    }
}
