<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Campaign;

final class CampaignFormBehaviorReader
{
    public function showsWhatsappFields(mixed $type): bool
    {
        return $type === 'whatsapp';
    }

    public function requiresCadenceInterval(mixed $isRecurring): bool
    {
        return (bool) $isRecurring;
    }

    public function hidesDispatchSection(string $operation): bool
    {
        return $operation === 'create';
    }
}
