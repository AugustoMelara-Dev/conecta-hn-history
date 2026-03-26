<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Models\Payment\InboundWebhook;

interface InboundWebhookRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $attributes
     */
    public function firstOrCreate(array $identity, array $attributes): InboundWebhook;

    public function save(InboundWebhook $webhook): InboundWebhook;
}
