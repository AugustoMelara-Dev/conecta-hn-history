<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BillingResource\Pages;

use App\Filament\Admin\Resources\BillingResource;
use Filament\Resources\Pages\ListRecords;

class ListBilling extends ListRecords
{
    protected static string $resource = BillingResource::class;
}
