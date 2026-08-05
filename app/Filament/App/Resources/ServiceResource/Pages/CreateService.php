<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ServiceResource\Pages;

use App\Application\ServiceCatalog\MerchantServiceCatalogAdapter;
use App\Filament\App\Resources\ServiceResource;
use App\Models\Service;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    public string $createAttemptId;

    public function mount(): void
    {
        parent::mount();
        $this->createAttemptId = (string) Str::uuid();
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Service
    {
        return app(MerchantServiceCatalogAdapter::class)->createService(
            ServiceResource::trustedTenant(),
            ServiceResource::trustedActor(),
            $data,
            $this->createAttemptId,
        );
    }
}
