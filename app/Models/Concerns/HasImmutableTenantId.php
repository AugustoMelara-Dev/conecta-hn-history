<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HasImmutableTenantId
{
    protected static function bootHasImmutableTenantId(): void
    {
        static::updating(static function (Model $model): void {
            if (! $model->isDirty('tenant_id')) {
                return;
            }

            $model->setAttribute('tenant_id', $model->getOriginal('tenant_id'));
            $model->syncOriginalAttribute('tenant_id');

            throw new \LogicException('Tenant ownership is immutable after creation.');
        });
    }
}
