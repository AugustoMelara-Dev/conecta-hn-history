<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Leads\Pages;

use App\Filament\App\Resources\Leads\LeadResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;
}
