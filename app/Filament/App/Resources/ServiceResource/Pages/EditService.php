<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ServiceResource\Pages;

use App\Application\ServiceCatalog\MerchantServiceCatalogAdapter;
use App\Filament\App\Resources\ServiceResource;
use App\Models\Service;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->using(function (Service $record): bool {
                    app(MerchantServiceCatalogAdapter::class)->archiveService(
                        ServiceResource::trustedTenant(),
                        ServiceResource::trustedActor(),
                        $record,
                    );

                    return true;
                }),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Service
    {
        if (! $record instanceof Service) {
            throw new \LogicException('The service editor received an invalid record.');
        }

        return app(MerchantServiceCatalogAdapter::class)->updateService(
            ServiceResource::trustedTenant(),
            ServiceResource::trustedActor(),
            $record,
            $data,
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['location_ids'] = DB::table('service_locations')
            ->where('tenant_id', ServiceResource::trustedTenant()->getKey())
            ->where('service_id', $this->record->getKey())
            ->orderBy('location_id')
            ->pluck('location_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        return $data;
    }
}
