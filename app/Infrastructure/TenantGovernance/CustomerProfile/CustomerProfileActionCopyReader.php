<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

final class CustomerProfileActionCopyReader
{
    public function emailFollowUpDescription(): string
    {
        return 'Esto crea una entrega auditada en el CRM y actualiza el ultimo contacto del cliente.';
    }

    public function whatsappFollowUpDescription(): string
    {
        return 'Esto usa una plantilla auditable de WhatsApp Cloud API y deja trazabilidad en el CRM.';
    }

    public function reactivationDescription(): string
    {
        return 'Usa el mejor canal segun el cliente y respeta opt-in antes de enviar.';
    }
}
