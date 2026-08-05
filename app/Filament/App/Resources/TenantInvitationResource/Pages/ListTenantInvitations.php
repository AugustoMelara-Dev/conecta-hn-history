<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\TenantInvitationResource\Pages;

use App\Filament\App\Resources\TenantInvitationResource;
use Filament\Resources\Pages\ListRecords;

final class ListTenantInvitations extends ListRecords
{
    protected static string $resource = TenantInvitationResource::class;
}
