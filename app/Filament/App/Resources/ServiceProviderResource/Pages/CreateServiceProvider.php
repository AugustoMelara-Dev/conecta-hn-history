<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ServiceProviderResource\Pages;

use App\Application\ServiceCatalog\MerchantServiceCatalogAdapter;
use App\Filament\App\Resources\ServiceProviderResource;
use App\Models\ServiceProvider;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateServiceProvider extends CreateRecord
{
    protected static string $resource = ServiceProviderResource::class;

    public string $createAttemptId;

    public function mount(): void
    {
        parent::mount();
        $this->createAttemptId = (string) Str::uuid();
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): ServiceProvider
    {
        return app(MerchantServiceCatalogAdapter::class)->createProvider(
            ServiceProviderResource::trustedTenant(),
            ServiceProviderResource::trustedActor(),
            $data,
            $this->createAttemptId,
        );
    }
}
