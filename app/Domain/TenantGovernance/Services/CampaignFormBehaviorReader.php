<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

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
