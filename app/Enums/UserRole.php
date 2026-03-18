<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case SUPER_ADMIN = 'super_admin';
    case TENANT_OWNER = 'seller'; // Legacy compatibility
    case STAFF = 'staff';
    case CUSTOMER = 'user'; // Legacy compatibility

    public function getLabel(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrador',
            self::TENANT_OWNER => 'Dueño de Negocio',
            self::STAFF => 'Colaborador',
            self::CUSTOMER => 'Cliente Final',
        };
    }
}
