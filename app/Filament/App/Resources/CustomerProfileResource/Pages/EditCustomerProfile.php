<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerProfileResource\Pages;

use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Domain\TenantGovernance\Services\CustomerProfileManager;
use App\Domain\TenantGovernance\Services\TenantCustomerProfileEditReader;
use App\Filament\App\Resources\CustomerProfileResource;
use App\Filament\App\Resources\Pages\EditTenantRecord;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class EditCustomerProfile extends EditTenantRecord
{
    protected static string $resource = CustomerProfileResource::class;

    public function getSubheading(): string
    {
        return $this->editReader()->subheading();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            ...$this->editReader()->formState($this->getRecord()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mergeDuplicate')
                ->label('Fusionar duplicado')
                ->icon('heroicon-o-squares-plus')
                ->color('warning')
                ->form([
                    Select::make('duplicate_profile_id')
                        ->label('Perfil duplicado')
                        ->searchable()
                        ->required()
                        ->getSearchResultsUsing(fn (string $search): array => $this->duplicateOptions($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => $this->duplicateLabel($value)),
                ])
                ->action(function (array $data): void {
                    $duplicate = $this->profileRepository()->findTenantRecord(
                        (string) $this->getRecord()->tenant_id,
                        (int) $data['duplicate_profile_id'],
                    );

                    if ($duplicate === null) {
                        Notification::make()
                            ->title('No se encontró el duplicado')
                            ->danger()
                            ->body('El perfil seleccionado ya no está disponible dentro de este negocio.')
                            ->send();

                        return;
                    }

                    $merged = $this->profileManager()->mergeProfiles(
                        primary: $this->getRecord(),
                        duplicate: $duplicate,
                        actorUserId: auth()->id(),
                    );

                    Notification::make()
                        ->title('Perfiles fusionados')
                        ->success()
                        ->body('La ficha ahora concentra historial, tareas, conversaciones y fidelidad en un solo cliente.')
                        ->send();

                    $tenant = Filament::getTenant();

                    $this->redirect(CustomerProfileResource::getUrl('edit', [
                        'record' => $merged,
                        'tenant' => $tenant instanceof Tenant ? $tenant->slug : null,
                    ]));
                }),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function duplicateOptions(string $search): array
    {
        return $this->editReader()->duplicateOptions($this->getRecord(), $search);
    }

    private function duplicateLabel(mixed $value): ?string
    {
        return $this->editReader()->duplicateLabel($this->getRecord(), $value);
    }

    private function profileRepository(): CustomerProfileRepositoryInterface
    {
        return app(CustomerProfileRepositoryInterface::class);
    }

    private function editReader(): TenantCustomerProfileEditReader
    {
        return app(TenantCustomerProfileEditReader::class);
    }

    private function profileManager(): CustomerProfileManager
    {
        return app(CustomerProfileManager::class);
    }
}
