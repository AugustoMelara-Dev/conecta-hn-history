<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Domain\TenantGovernance\Repositories\InboundWebhookRepositoryInterface;
use App\Models\Payment\InboundWebhook;

final class EloquentInboundWebhookRepository implements InboundWebhookRepositoryInterface
{
    public function firstOrCreate(array $identity, array $attributes): InboundWebhook
    {
        return InboundWebhook::query()
            ->withoutGlobalScopes()
            ->firstOrCreate($identity, $attributes);
    }

    public function save(InboundWebhook $webhook): InboundWebhook
    {
        $webhook->save();

        /** @var InboundWebhook $fresh */
        $fresh = $webhook->fresh();

        return $fresh;
    }
}
