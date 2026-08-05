<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ServiceProviderResource\Pages;

use App\Application\ServiceCatalog\MerchantServiceCatalogAdapter;
use App\Filament\App\Resources\ServiceProviderResource;
use App\Models\ServiceProvider;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditServiceProvider extends EditRecord
{
    protected static string $resource = ServiceProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->using(function (ServiceProvider $record): bool {
                    app(MerchantServiceCatalogAdapter::class)->archiveProvider(
                        ServiceProviderResource::trustedTenant(),
                        ServiceProviderResource::trustedActor(),
                        $record,
                    );

                    return true;
                }),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): ServiceProvider
    {
        if (! $record instanceof ServiceProvider) {
            throw new \LogicException('The provider editor received an invalid record.');
        }

        return app(MerchantServiceCatalogAdapter::class)->updateProvider(
            ServiceProviderResource::trustedTenant(),
            ServiceProviderResource::trustedActor(),
            $record,
            $data,
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['location_ids'] = DB::table('service_provider_locations')
            ->where('tenant_id', ServiceProviderResource::trustedTenant()->getKey())
            ->where('service_provider_id', $this->record->getKey())
            ->orderBy('location_id')
            ->pluck('location_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        return $data;
    }
}
