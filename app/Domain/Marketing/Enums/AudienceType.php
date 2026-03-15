<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Enums;

enum AudienceType: string
{
    case ALL_CUSTOMERS = 'all_customers';
    case FOLLOWERS = 'followers';
    case WAITLIST = 'waitlist';
    case LEADS = 'leads';

    public function label(): string
    {
        return match ($this) {
            self::ALL_CUSTOMERS => 'Todos los Clientes (Reservas)',
            self::FOLLOWERS => 'Seguidores',
            self::WAITLIST => 'Lista de Espera',
            self::LEADS => 'Leads / Prospectos',
        };
    }
}
